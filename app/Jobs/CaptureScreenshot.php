<?php

namespace App\Jobs;

use App\Enums\ScreenshotStatus;
use App\Exceptions\UrlNotAllowed;
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
use RuntimeException;
use Spatie\Browsershot\Browsershot;
use Spatie\Browsershot\Exceptions\UnsuccessfulResponse;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
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
        return now()->addSeconds(self::deadlineSeconds($this->screenshot->timeout));
    }

    /**
     * Seconds from dispatch until a capture can no longer run. Past this, a
     * row still marked pending or processing belongs to a job that is gone.
     */
    public static function deadlineSeconds(?int $timeout = null): int
    {
        $browsershotTimeout = $timeout ?? config('screenshot.default_timeout');

        $challengeWait = config('screenshot.detect_blocks')
            ? (int) ceil(config('screenshot.challenge_wait_ms') / 1000)
            : 0;

        $queueWaitGrace = (int) config('screenshot.queue_wait_grace');

        return $queueWaitGrace + $browsershotTimeout + $challengeWait + 60;
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

            $browsershot = $this->capture($detector, $userAgents, $fullPath);

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

            $this->store($disk, $s3FullPath, $fullPath);
            $this->store($disk, $s3ThumbnailPath, $thumbnailPath);

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

    /**
     * The shortest budget worth re-navigating with. Below this, a fallback
     * would only turn one timeout into another.
     */
    private const MIN_FALLBACK_SECONDS = 10;

    /**
     * Captures the page, falling back to the `load` event when it never goes
     * network-idle.
     *
     * networkidle0/2 waits for the page to stop making requests, and pages
     * heavy with ads and trackers sometimes never do — maxroll.gg settles in
     * 8s from a residential connection but can churn past two minutes from a
     * datacenter IP, while its `load` event fires in ~13s every time. Waiting
     * out the whole budget for idleness and then failing gave the client
     * nothing, so idleness now gets a bounded window and the page is captured
     * at `load` with whatever budget remains.
     *
     * Only a navigation timeout falls back. A 403, a network error or a
     * challenge that never clears means something different, and a second
     * attempt would just repeat it.
     */
    private function capture(ChallengeDetector $detector, ChromeUserAgent $userAgents, string $path): Browsershot
    {
        $budget = (int) ($this->screenshot->timeout ?? config('screenshot.default_timeout'));
        $idleWindow = (int) config('screenshot.network_idle_timeout');
        $waitUntil = $this->screenshot->wait_until ?? config('screenshot.default_wait_until');

        $canFallBack = in_array($waitUntil, ['networkidle0', 'networkidle2'], true)
            && $idleWindow > 0
            && $idleWindow < $budget;

        $browsershot = $this->buildBrowsershot($detector, $userAgents);

        if (! $canFallBack) {
            $this->takeScreenshot($browsershot, $path);

            return $browsershot;
        }

        // Caps only Puppeteer's navigation wait. The process keeps the full
        // budget, so a stalled page ends in a navigation timeout we can
        // recognise rather than the process being killed mid-wait.
        $browsershot->setOption('timeout', $idleWindow * 1000);
        $started = now();

        try {
            $this->takeScreenshot($browsershot, $path);

            return $browsershot;
        } catch (ProcessFailedException $e) {
            $remaining = $budget - (int) ceil($started->diffInSeconds(now(), true));

            if (! $this->isNavigationTimeout($e) || $remaining < self::MIN_FALLBACK_SECONDS) {
                throw $e;
            }
        }

        Log::info('Page never went network-idle; capturing at load', [
            'screenshot_id' => $this->screenshot->id,
            'url' => $this->screenshot->url,
            'wait_until' => $waitUntil,
            'idle_window' => $idleWindow,
            'remaining_budget' => $remaining,
        ]);

        $fallback = $this->buildBrowsershot($detector, $userAgents, 'load', $remaining);
        $this->takeScreenshot($fallback, $path);

        return $fallback;
    }

    protected function takeScreenshot(Browsershot $browsershot, string $path): void
    {
        $browsershot->save($path);
    }

    private function isNavigationTimeout(ProcessFailedException $e): bool
    {
        return (bool) preg_match('/^\w*Error: Navigation timeout of \d+ ms exceeded/m', $e->getProcess()->getErrorOutput());
    }

    private function buildBrowsershot(
        ChallengeDetector $detector,
        ChromeUserAgent $userAgents,
        ?string $waitUntil = null,
        ?int $timeout = null,
    ): Browsershot {
        $timeout ??= $this->screenshot->timeout ?? config('screenshot.default_timeout');

        $browsershot = Browsershot::url($this->screenshot->url)
            ->windowSize($this->screenshot->viewport_width, $this->screenshot->viewport_height)
            ->setOption('waitUntil', $waitUntil ?? $this->screenshot->wait_until)
            ->timeout($timeout)
            ->setChromePath(config('screenshot.chrome_path'));

        if (! config('screenshot.chrome_sandbox')) {
            $browsershot->noSandbox();
        }

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

        if (config('screenshot.hide_automation')) {
            // Opt-in; see config/screenshot.php for what this does and doesn't cover.
            $arguments['disable-blink-features'] = 'AutomationControlled';
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
     * The S3 disk is configured not to throw, so a rejected upload used to come
     * back as false and the capture was marked completed with image URLs that
     * 404. A bucket with Block Public Access on rejects the public ACL exactly
     * that way.
     */
    private function store(string $disk, string $path, string $localPath): void
    {
        // Behind CloudFront with origin access control, objects need no ACL
        // and the bucket can block public access entirely.
        $options = $disk === 's3' && ! config('screenshot.s3_public_acl')
            ? []
            : ['visibility' => 'public'];

        if (! Storage::disk($disk)->put($path, file_get_contents($localPath), $options)) {
            throw new RuntimeException("Could not write {$path} to the {$disk} disk.");
        }
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
                throw new UrlNotAllowed('The page redirected to an address that is not allowed.');
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
            'error_message' => $blockReason ?? $this->clientMessage($e),
            'error_detail' => $e->getMessage(),
        ]);

        $this->notify();
    }

    /**
     * What the API and webhooks report. Raw exception text is no good here:
     * Browsershot's failures carry the whole node command line, server paths
     * and a stack trace, and storage errors name the bucket. So only messages
     * known to be about the client's URL pass through; the rest are generic,
     * with the original kept in error_detail for admins.
     */
    private function clientMessage(Throwable $e): string
    {
        return match (true) {
            $e instanceof UrlNotAllowed, $e instanceof UnsuccessfulResponse => trim($e->getMessage()),
            $e instanceof ProcessTimedOutException => "The capture timed out after {$e->getExceededTimeout()} seconds.",
            $e instanceof ProcessFailedException => $this->browserError($e) ?? 'The browser failed to capture the page.',
            default => 'The capture failed because of an internal error.',
        };
    }

    /**
     * Picks the navigation error out of the browser's stderr, e.g.
     * "net::ERR_NAME_NOT_RESOLVED at https://..." or "Navigation timeout of
     * 30000 ms exceeded". Anything else (a Chrome that won't launch, say) is
     * our problem rather than the client's, and stays out of the response.
     */
    private function browserError(ProcessFailedException $e): ?string
    {
        $pattern = '/^\w*Error: (net::ERR_[A-Z0-9_]+ at \S+|Navigation timeout of \d+ ms exceeded)\s*$/m';

        return preg_match($pattern, $e->getProcess()->getErrorOutput(), $matches) ? $matches[1] : null;
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
            'error_message' => 'The capture could not be completed before its deadline.',
            'error_detail' => $exception->getMessage(),
        ]);

        $this->notify();
    }
}
