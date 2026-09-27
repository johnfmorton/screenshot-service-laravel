<?php

namespace App\Jobs;

use App\Enums\ScreenshotStatus;
use App\Models\Screenshot;
use App\Services\ChallengeDetector;
use App\Services\ChromeUserAgent;
use App\Services\PublicUrlGuard;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use InvalidArgumentException;
use Spatie\Browsershot\Browsershot;
use Throwable;

class CaptureScreenshot implements ShouldQueue
{
    use Queueable;

    public array $backoff = [10, 30, 60];

    public function __construct(
        public Screenshot $screenshot
    ) {}

    /**
     * Bound how long this job stays valid, measured from dispatch.
     *
     * This is a deadline, not a duration budget for the work. Laravel resolves
     * it once and bakes the timestamp into the payload (Queue::createPayload),
     * then checks it *before* running the job on every pickup. Time the job
     * spends queued therefore counts against the window exactly like time spent
     * capturing, and a job whose deadline passed while it waited is failed
     * without ever being attempted.
     *
     * Hence the queue-wait grace: without it the window only covered one
     * capture, so a short backlog on a single worker was enough to fail jobs
     * that had never run.
     *
     * Note this also overrides any attempt limit — Laravel skips the
     * max-attempts check entirely when retryUntil() is set, so neither a $tries
     * property here nor `queue:work --tries` applies to this job.
     *
     * Being generous costs nothing. A capture that fails or is blocked is
     * recorded rather than rethrown, so it never retries at all; only a worker
     * dying mid-job puts the work back on the queue.
     */
    public function retryUntil(): \DateTime
    {
        $browsershotTimeout = $this->screenshot->timeout ?? config('screenshot.default_timeout');

        $challengeWait = config('screenshot.detect_blocks')
            ? (int) ceil(config('screenshot.challenge_wait_ms') / 1000)
            : 0;

        $queueWaitGrace = (int) config('screenshot.queue_wait_grace');

        return now()->addSeconds($queueWaitGrace + $browsershotTimeout + $challengeWait + 60);
    }

    public function handle(ChallengeDetector $detector, ChromeUserAgent $userAgents, PublicUrlGuard $guard): void
    {
        $this->screenshot->update(['status' => ScreenshotStatus::Processing]);

        $tempDir = sys_get_temp_dir();
        $fullPath = $tempDir . '/' . $this->screenshot->id . '-full.png';
        $thumbnailPath = $tempDir . '/' . $this->screenshot->id . '-thumb.png';

        try {
            // Checked again here, not just at request time: DNS can change in
            // between, and jobs queued before the check existed never had it.
            $guard->check($this->screenshot->url);

            $browsershot = $this->buildBrowsershot($detector, $userAgents);
            $browsershot->save($fullPath);

            $this->assertRedirectsStayedPublic($browsershot, $guard);

            $manager = new ImageManager(new Driver());

            if ($this->screenshot->max_width) {
                $image = $manager->read($fullPath);
                if ($image->width() > $this->screenshot->max_width) {
                    $image->scale(width: $this->screenshot->max_width);
                    $image->save($fullPath);
                }
            }

            $thumbnail = $manager->read($fullPath);
            $thumbnail->cover($this->screenshot->thumbnail_width, $this->screenshot->thumbnail_height);
            $thumbnail->save($thumbnailPath);

            $disk = config('screenshot.storage_disk');
            $storagePath = $disk === 's3'
                ? config('screenshot.storage_path', 'screenshots')
                : 'screenshots';
            $s3FullPath = $storagePath . '/' . $this->screenshot->id . '-full.png';
            $s3ThumbnailPath = $storagePath . '/' . $this->screenshot->id . '-thumb.png';

            Storage::disk($disk)->put($s3FullPath, file_get_contents($fullPath), 'public');
            Storage::disk($disk)->put($s3ThumbnailPath, file_get_contents($thumbnailPath), 'public');

            $this->screenshot->update([
                'status' => ScreenshotStatus::Completed,
                'full_image_path' => $s3FullPath,
                'thumbnail_path' => $s3ThumbnailPath,
                'captured_at' => Carbon::now(),
            ]);

            $this->notify();
        } catch (Throwable $e) {
            $this->recordFailure($e, $detector);
        } finally {
            @unlink($fullPath);
            @unlink($thumbnailPath);
        }
    }

    private function buildBrowsershot(ChallengeDetector $detector, ChromeUserAgent $userAgents): Browsershot
    {
        $timeout = $this->screenshot->timeout ?? config('screenshot.default_timeout');

        $browsershot = Browsershot::url($this->screenshot->url)
            ->windowSize($this->screenshot->viewport_width, $this->screenshot->viewport_height)
            ->setOption('waitUntil', $this->screenshot->wait_until)
            ->timeout($timeout)
            ->setChromePath(config('screenshot.chrome_path'))
            ->noSandbox();

        if (config('screenshot.new_headless')) {
            // Modern headless Chrome rather than the legacy headless shell,
            // which is trivially fingerprinted as automation.
            $browsershot->newHeadless();
        }

        $arguments = [];

        if (config('screenshot.chrome_memory_optimized')) {
            $arguments[] = 'disable-dev-shm-usage';
            $arguments[] = 'disable-gpu';
        }

        if (config('screenshot.chrome_single_process')) {
            $arguments[] = 'single-process';
        }

        if (config('screenshot.force_http1')) {
            // Force HTTP/1.1. Some sites/CDNs trigger
            // net::ERR_HTTP2_PROTOCOL_ERROR under headless Chrome's HTTP/2
            // stack, which fails the capture outright.
            $arguments[] = 'disable-http2';
        }

        // Set the user agent as a launch flag rather than through Puppeteer's
        // per-page override. The override makes Chrome stop sending sec-ch-ua
        // and report an empty navigator.userAgentData.brands, which marks the
        // browser as automated more plainly than the HeadlessChrome UA it
        // replaces, and it doesn't reliably cover browser-initiated requests
        // such as an implicit favicon fetch — those intermittently go out with
        // the real headless UA. A launch flag has neither problem.
        $arguments['user-agent'] = $this->screenshot->user_agent ?: $userAgents->resolve();

        $browsershot->addChromiumArguments($arguments);

        // No client hints here: Chrome sends accurate ones by itself now that
        // the user agent is set at launch. See config/screenshot.php.
        $headers = config('screenshot.default_headers', []);
        if ($headers) {
            $browsershot->setExtraHttpHeaders($headers);
        }

        if (config('screenshot.detect_blocks')) {
            if (config('screenshot.fail_on_error_response')) {
                $browsershot->preventUnsuccessfulResponse();
            }

            $browsershot->waitForFunction(
                $detector->waitPredicate(),
                null,
                (int) config('screenshot.challenge_wait_ms')
            );
        }

        return $browsershot;
    }

    /**
     * A public page can redirect Chrome to an internal address. The request has
     * already happened by now, but refusing to store the result keeps what
     * came back from being published at a public image URL.
     */
    private function assertRedirectsStayedPublic(Browsershot $browsershot, PublicUrlGuard $guard): void
    {
        foreach ($browsershot->getOutput()?->getRedirectHistory() ?? [] as $hop) {
            $url = $hop['url'] ?? '';

            if (preg_match('#^https?://#i', $url) && ! $guard->isAllowed($url)) {
                throw new InvalidArgumentException('The page redirected to an address that is not allowed.');
            }
        }
    }

    private function recordFailure(Throwable $e, ChallengeDetector $detector): void
    {
        $blockReason = config('screenshot.detect_blocks')
            ? $detector->blockReason($e)
            : null;

        $status = $blockReason ? ScreenshotStatus::Blocked : ScreenshotStatus::Failed;

        Log::error('Screenshot capture failed', [
            'screenshot_id' => $this->screenshot->id,
            'url' => $this->screenshot->url,
            'status' => $status->value,
            'error' => $e->getMessage(),
        ]);

        $this->screenshot->update([
            'status' => $status,
            'error_message' => $blockReason ?? $e->getMessage(),
        ]);

        $this->notify();
    }

    private function notify(): void
    {
        if ($this->screenshot->webhook_url) {
            SendWebhook::dispatch($this->screenshot);
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->screenshot->update([
            'status' => ScreenshotStatus::Failed,
            'error_message' => $exception->getMessage(),
        ]);

        $this->notify();
    }
}
