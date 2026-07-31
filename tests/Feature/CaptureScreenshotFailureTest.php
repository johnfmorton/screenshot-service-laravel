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
        $screenshot = $this->recordFailure(
            new RuntimeException('net::ERR_NAME_NOT_RESOLVED')
        );

        $this->assertSame(ScreenshotStatus::Failed, $screenshot->status);
        $this->assertStringContainsString('ERR_NAME_NOT_RESOLVED', $screenshot->error_message);
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
        ]);

        $screenshot = new Screenshot(['timeout' => 120]);
        $window = (new CaptureScreenshot($screenshot))->retryUntil()->getTimestamp() - now()->getTimestamp();

        $this->assertLessThan(120 + 15 + 60, $window);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function recordFailure(Throwable $exception, array $attributes = []): Screenshot
    {
        $apiKey = ApiKey::create([
            'name' => 'Test Key',
            'key' => 'test-key-' . uniqid(),
        ]);

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
