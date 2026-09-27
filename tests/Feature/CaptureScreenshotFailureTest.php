<?php

namespace Tests\Feature;

use App\Enums\ScreenshotStatus;
use App\Jobs\CaptureScreenshot;
use App\Jobs\SendWebhook;
use App\Models\ApiKey;
use App\Models\Screenshot;
use App\Services\ChallengeDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use ReflectionMethod;
use RuntimeException;
use Spatie\Browsershot\Exceptions\UnsuccessfulResponse;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use Throwable;

class CaptureScreenshotFailureTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_bot_protection_block_is_recorded_as_blocked(): void
    {
        $screenshot = $this->recordFailure(
            UnsuccessfulResponse::make('https://example.com', 403)
        );

        $this->assertSame(ScreenshotStatus::Blocked, $screenshot->status);
        $this->assertStringContainsString('403', $screenshot->error_message);
    }

    public function test_an_ordinary_error_is_still_recorded_as_failed(): void
    {
        $screenshot = $this->recordFailure($this->browserFailure(
            'Error: net::ERR_NAME_NOT_RESOLVED at https://example.com/'
        ));

        $this->assertSame(ScreenshotStatus::Failed, $screenshot->status);
        $this->assertSame('net::ERR_NAME_NOT_RESOLVED at https://example.com/', $screenshot->error_message);
    }

    /**
     * Browsershot's exception message is the whole node command line — server
     * paths, every Chrome flag — followed by a stack trace. error_message goes
     * to API clients and webhooks, so none of that can reach it.
     */
    public function test_the_command_line_and_stack_trace_stay_out_of_the_client_message(): void
    {
        $screenshot = $this->recordFailure($this->browserFailure(implode("\n", [
            'Error: net::ERR_CERT_DATE_INVALID at https://example.com/',
            '    at navigate (/var/www/html/node_modules/puppeteer-core/lib/cjs/puppeteer/cdp/Frame.js:184:27)',
        ])));

        $this->assertSame('net::ERR_CERT_DATE_INVALID at https://example.com/', $screenshot->error_message);
        $this->assertStringContainsString('/var/www/html', $screenshot->error_detail);
        $this->assertStringContainsString('The command', $screenshot->error_detail);
    }

    public function test_a_navigation_timeout_is_reported(): void
    {
        $screenshot = $this->recordFailure($this->browserFailure(
            'TimeoutError: Navigation timeout of 30000 ms exceeded'
        ));

        $this->assertSame('Navigation timeout of 30000 ms exceeded', $screenshot->error_message);
    }

    /**
     * A Chrome that won't launch is our problem, and its error names paths and
     * flags. The client gets a generic message; the admin gets the detail.
     */
    public function test_a_server_side_browser_failure_is_reported_generically(): void
    {
        $screenshot = $this->recordFailure($this->browserFailure(
            'Error: Failed to launch the browser process! /usr/bin/chromium: No usable sandbox!'
        ));

        $this->assertSame('The browser failed to capture the page.', $screenshot->error_message);
        $this->assertStringContainsString('No usable sandbox', $screenshot->error_detail);
    }

    public function test_an_unexpected_error_is_reported_generically(): void
    {
        $screenshot = $this->recordFailure(
            new RuntimeException('Error executing "PutObject" on "https://my-private-bucket.s3.amazonaws.com/..."')
        );

        $this->assertSame('The capture failed because of an internal error.', $screenshot->error_message);
        $this->assertStringContainsString('my-private-bucket', $screenshot->error_detail);
    }

    public function test_the_raw_detail_is_not_serialized(): void
    {
        $screenshot = $this->recordFailure(new RuntimeException('internal'));

        $this->assertArrayNotHasKey('error_detail', $screenshot->toArray());
    }

    /**
     * A block leaves no stored image, so it must never satisfy a cache lookup
     * — otherwise every client polling that URL gets the block page until the
     * TTL expires, which is the failure this whole feature exists to prevent.
     */
    public function test_a_blocked_capture_is_never_served_from_cache(): void
    {
        $screenshot = $this->recordFailure(
            UnsuccessfulResponse::make('https://example.com', 403)
        );

        $this->assertNull($screenshot->full_image_path);

        $cached = app(\App\Services\ScreenshotService::class)->findCachedScreenshot(
            $screenshot->apiKey,
            $screenshot->url_hash
        );

        $this->assertNull($cached);
    }

    public function test_a_block_still_notifies_the_webhook(): void
    {
        Queue::fake();

        $this->recordFailure(
            UnsuccessfulResponse::make('https://example.com', 403),
            ['webhook_url' => 'https://example.com/hook']
        );

        Queue::assertPushed(SendWebhook::class);
    }

    /**
     * The retry window has to cover the challenge wait as well as the capture
     * timeout, or a job that spends its budget waiting out an interstitial is
     * killed before it can record the outcome.
     */
    public function test_the_retry_window_accounts_for_the_challenge_wait(): void
    {
        config([
            'screenshot.detect_blocks' => true,
            'screenshot.challenge_wait_ms' => 15000,
            'screenshot.queue_wait_grace' => 0,
        ]);

        $screenshot = new Screenshot(['timeout' => 120]);
        $window = (new CaptureScreenshot($screenshot))->retryUntil()->getTimestamp() - now()->getTimestamp();

        $this->assertGreaterThanOrEqual(120 + 15, $window);
    }

    public function test_the_retry_window_ignores_the_challenge_wait_when_detection_is_off(): void
    {
        config([
            'screenshot.detect_blocks' => false,
            'screenshot.challenge_wait_ms' => 15000,
            'screenshot.queue_wait_grace' => 0,
        ]);

        $screenshot = new Screenshot(['timeout' => 120]);
        $window = (new CaptureScreenshot($screenshot))->retryUntil()->getTimestamp() - now()->getTimestamp();

        $this->assertLessThan(120 + 15 + 60, $window);
    }

    /**
     * retryUntil() is a deadline measured from dispatch, and Laravel checks it
     * before running the job. Queued time therefore counts against it, so a
     * window sized for a single capture fails jobs that are merely waiting
     * their turn — on one worker, two slow captures ahead is enough.
     */
    public function test_a_job_delayed_by_a_backlog_is_still_runnable_when_picked_up(): void
    {
        config([
            'screenshot.detect_blocks' => true,
            'screenshot.challenge_wait_ms' => 15000,
            'screenshot.queue_wait_grace' => 1800,
        ]);

        $deadline = (new CaptureScreenshot(new Screenshot(['timeout' => 120])))->retryUntil();

        // Two slow captures ahead of this one on a single worker.
        $this->travel(240)->seconds();

        $this->assertGreaterThan(
            now()->getTimestamp(),
            $deadline->getTimestamp(),
            'The job would be failed without ever being attempted.'
        );
    }

    public function test_the_queue_wait_grace_widens_the_window(): void
    {
        config([
            'screenshot.detect_blocks' => false,
            'screenshot.queue_wait_grace' => 1800,
        ]);

        $screenshot = new Screenshot(['timeout' => 120]);
        $window = (new CaptureScreenshot($screenshot))->retryUntil()->getTimestamp() - now()->getTimestamp();

        $this->assertGreaterThanOrEqual(1800 + 120, $window);
    }

    /**
     * A real failed process, so the exception carries Browsershot's actual
     * message shape: the command line, then the process's stderr.
     */
    private function browserFailure(string $stderr): ProcessFailedException
    {
        $process = Process::fromShellCommandline(
            // ':' makes the node invocation a no-op; only its text matters.
            ": node '/var/www/html/vendor/spatie/browsershot/bin/browser.cjs' '{\"args\":[\"--no-sandbox\"]}'; printf '%s\\n' \"\$STDERR\" >&2; exit 1"
        );
        $process->run(null, ['STDERR' => $stderr]);

        return new ProcessFailedException($process);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function recordFailure(Throwable $exception, array $attributes = []): Screenshot
    {
        $apiKey = ApiKey::generate('Test Key');

        $screenshot = Screenshot::create(array_merge([
            'api_key_id' => $apiKey->id,
            'url' => 'https://example.com',
            'url_hash' => Screenshot::generateUrlHash('https://example.com', 1280, 800, null, 400, 300),
            'viewport_width' => 1280,
            'viewport_height' => 800,
            'thumbnail_width' => 400,
            'thumbnail_height' => 300,
            'status' => ScreenshotStatus::Processing,
            'expires_at' => now()->addHours(24),
        ], $attributes));

        $job = new CaptureScreenshot($screenshot);
        $method = new ReflectionMethod($job, 'recordFailure');
        $method->setAccessible(true);
        $method->invoke($job, $exception, new ChallengeDetector());

        return $screenshot->fresh();
    }
}
