<?php

namespace Tests\Feature;

use App\Enums\ScreenshotStatus;
use App\Models\ApiKey;
use App\Models\Screenshot;
use App\Services\PublicUrlGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ApiRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->app->instance(PublicUrlGuard::class, new PublicUrlGuard(fn () => ['93.184.215.14']));
    }

    public function test_requests_over_the_hourly_limit_are_rejected(): void
    {
        $apiKey = $this->key(rateLimit: 3);

        for ($i = 1; $i <= 3; $i++) {
            $this->capture($apiKey, "https://example.com/{$i}")
                ->assertAccepted()
                ->assertHeader('X-RateLimit-Remaining', (string) (3 - $i));
        }

        $this->capture($apiKey, 'https://example.com/4')
            ->assertTooManyRequests()
            ->assertHeader('X-RateLimit-Remaining', '0')
            ->assertHeader('Retry-After');
    }

    /**
     * The old limiter read the counter and wrote it back as two steps, so
     * requests arriving together all saw the same count. The decision now
     * rests on the value the atomic increment returns.
     */
    public function test_the_decision_uses_the_atomic_count_not_a_separate_read(): void
    {
        $apiKey = $this->key(rateLimit: 5);

        // Simulate four concurrent requests having already incremented.
        RateLimiter::increment("api-key:{$apiKey->id}", 3600, 4);

        $this->capture($apiKey, 'https://example.com/a')->assertAccepted();
        $this->capture($apiKey, 'https://example.com/b')->assertTooManyRequests();
    }

    public function test_keys_without_a_rate_limit_are_not_throttled_hourly(): void
    {
        config(['screenshot.max_pending_per_key' => 0]);
        $apiKey = $this->key(rateLimit: null);

        for ($i = 0; $i < 30; $i++) {
            $this->capture($apiKey, "https://example.com/{$i}")->assertAccepted();
        }
    }

    public function test_a_key_cannot_queue_more_than_its_share_of_captures(): void
    {
        config(['screenshot.max_pending_per_key' => 2]);
        $apiKey = $this->key(rateLimit: null);

        $this->capture($apiKey, 'https://example.com/1')->assertAccepted();
        $this->capture($apiKey, 'https://example.com/2')->assertAccepted();

        $this->capture($apiKey, 'https://example.com/3')
            ->assertTooManyRequests()
            ->assertJsonPath('error', 'Too many pending captures');

        // Other keys are unaffected.
        $this->capture($this->key(rateLimit: null), 'https://example.com/1')->assertAccepted();
    }

    public function test_finished_captures_free_up_the_share(): void
    {
        config(['screenshot.max_pending_per_key' => 1]);
        $apiKey = $this->key(rateLimit: null);

        $this->capture($apiKey, 'https://example.com/1')->assertAccepted();
        Screenshot::query()->update(['status' => ScreenshotStatus::Failed]);

        $this->capture($apiKey, 'https://example.com/2')->assertAccepted();
    }

    /**
     * A worker that dies mid-capture leaves its row in processing. Once the
     * job's deadline has passed it can never run, so it mustn't keep counting.
     */
    public function test_abandoned_captures_stop_counting_after_their_deadline(): void
    {
        config(['screenshot.max_pending_per_key' => 1]);
        $apiKey = $this->key(rateLimit: null);

        $this->capture($apiKey, 'https://example.com/1')->assertAccepted();
        Screenshot::query()->update(['status' => ScreenshotStatus::Processing]);

        $this->capture($apiKey, 'https://example.com/2')->assertTooManyRequests();

        $this->travel(3)->hours();

        $this->capture($apiKey, 'https://example.com/2')->assertAccepted();
    }

    public function test_a_cached_result_is_returned_even_at_the_cap(): void
    {
        config(['screenshot.max_pending_per_key' => 1]);
        $apiKey = $this->key(rateLimit: null);

        $this->capture($apiKey, 'https://example.com/done')->assertAccepted();
        Screenshot::query()->update(['status' => ScreenshotStatus::Completed]);
        $this->capture($apiKey, 'https://example.com/pending')->assertAccepted();

        $this->capture($apiKey, 'https://example.com/done')->assertOk();
    }

    private function key(?int $rateLimit): ApiKey
    {
        return ApiKey::generate('Test Key', $rateLimit);
    }

    private function capture(ApiKey $apiKey, string $url): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/screenshots', ['url' => $url], ['X-API-Key' => $apiKey->plainTextKey]);
    }
}
