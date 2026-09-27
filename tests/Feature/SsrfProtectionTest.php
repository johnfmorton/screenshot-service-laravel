<?php

namespace Tests\Feature;

use App\Enums\ScreenshotStatus;
use App\Jobs\CaptureScreenshot;
use App\Jobs\SendWebhook;
use App\Models\ApiKey;
use App\Models\Screenshot;
use App\Services\ChallengeDetector;
use App\Services\ChromeUserAgent;
use App\Services\PublicUrlGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use ReflectionMethod;
use ReflectionProperty;
use Spatie\Browsershot\Browsershot;
use Spatie\Browsershot\ChromiumResult;
use Tests\TestCase;

/**
 * Every point where a client-supplied URL gets fetched: validation when the
 * request arrives, the capture job (which runs later, after DNS may have
 * changed), Chrome's redirect chain, and webhook delivery.
 */
class SsrfProtectionTest extends TestCase
{
    use RefreshDatabase;

    private ApiKey $apiKey;

    protected function setUp(): void
    {
        parent::setUp();

        config(['screenshot.allow_private_urls' => false]);

        // Offline DNS: example.com is public, rebind.example.com is not.
        $this->app->instance(PublicUrlGuard::class, new PublicUrlGuard(fn (string $host): array => match ($host) {
            'example.com' => ['93.184.215.14'],
            'rebind.example.com' => ['127.0.0.1'],
            default => gethostbynamel($host) ?: [],
        }));

        $this->apiKey = ApiKey::generate('Test Key');
    }

    public function test_the_api_rejects_a_private_capture_url(): void
    {
        Queue::fake();

        $this->postJson('/api/screenshots', ['url' => 'http://169.254.169.254/latest/meta-data/'], $this->headers())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('url');

        Queue::assertNothingPushed();
    }

    public function test_the_api_rejects_a_private_webhook_url(): void
    {
        Queue::fake();

        $this->postJson('/api/screenshots', [
            'url' => 'https://example.com',
            'webhook_url' => 'http://127.0.0.1:6379/',
        ], $this->headers())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('webhook_url');
    }

    public function test_the_api_rejects_non_http_schemes(): void
    {
        Queue::fake();

        $this->postJson('/api/screenshots', ['url' => 'chrome://version'], $this->headers())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('url');
    }

    public function test_the_api_accepts_a_public_url(): void
    {
        Queue::fake();

        $this->postJson('/api/screenshots', [
            'url' => 'https://example.com',
            'webhook_url' => 'https://example.com/hook',
        ], $this->headers())->assertAccepted();

        Queue::assertPushed(CaptureScreenshot::class);
    }

    /**
     * The URL passed validation, then its DNS changed before the worker got to
     * it — or it was queued before validation existed. Either way the job must
     * refuse it without launching Chrome.
     */
    public function test_the_capture_job_rechecks_the_url_before_launching_chrome(): void
    {
        Queue::fake();

        $screenshot = $this->screenshot(['url' => 'https://rebind.example.com/']);

        (new CaptureScreenshot($screenshot))->handle(
            new ChallengeDetector(),
            app(ChromeUserAgent::class),
            app(PublicUrlGuard::class),
        );

        $screenshot->refresh();
        $this->assertSame(ScreenshotStatus::Failed, $screenshot->status);
        $this->assertStringContainsString('public address', $screenshot->error_message);
        $this->assertNull($screenshot->full_image_path);
    }

    public function test_a_redirect_to_a_private_address_is_not_stored(): void
    {
        $browsershot = $this->browsershotThatFollowed([
            ['url' => 'https://example.com/', 'status' => 302],
            ['url' => 'http://169.254.169.254/latest/meta-data/', 'status' => 200],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('redirected');

        $this->assertRedirectsStayedPublic($browsershot);
    }

    public function test_a_redirect_between_public_addresses_is_fine(): void
    {
        $browsershot = $this->browsershotThatFollowed([
            ['url' => 'http://example.com/', 'status' => 301],
            ['url' => 'https://example.com/', 'status' => 200],
        ]);

        $this->assertRedirectsStayedPublic($browsershot);
        $this->addToAssertionCount(1);
    }

    public function test_a_webhook_to_a_private_address_is_not_sent(): void
    {
        Http::fake();

        $screenshot = $this->screenshot(['webhook_url' => 'https://rebind.example.com/hook']);

        (new SendWebhook($screenshot))->handle(app(PublicUrlGuard::class));

        Http::assertNothingSent();
        $this->assertNull($screenshot->fresh()->webhook_sent_at);
    }

    public function test_a_webhook_to_a_public_address_is_sent(): void
    {
        Http::fake(['https://example.com/*' => Http::response(null, 200)]);

        $screenshot = $this->screenshot(['webhook_url' => 'https://example.com/hook']);

        (new SendWebhook($screenshot))->handle(app(PublicUrlGuard::class));

        Http::assertSent(fn ($request) => $request->url() === 'https://example.com/hook');
        $this->assertNotNull($screenshot->fresh()->webhook_sent_at);
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return ['X-API-Key' => $this->apiKey->plainTextKey];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function screenshot(array $attributes = []): Screenshot
    {
        return Screenshot::create(array_merge([
            'api_key_id' => $this->apiKey->id,
            'url' => 'https://example.com/',
            'url_hash' => Screenshot::generateUrlHash('https://example.com/', 1280, 800, null, 400, 300),
            'viewport_width' => 1280,
            'viewport_height' => 800,
            'thumbnail_width' => 400,
            'thumbnail_height' => 300,
            'status' => ScreenshotStatus::Completed,
            'expires_at' => now()->addHours(24),
        ], $attributes));
    }

    /**
     * @param  list<array{url: string, status: int}>  $hops
     */
    private function browsershotThatFollowed(array $hops): Browsershot
    {
        $browsershot = Browsershot::url('https://example.com/');

        $property = new ReflectionProperty(Browsershot::class, 'chromiumResult');
        $property->setAccessible(true);
        $property->setValue($browsershot, new ChromiumResult(['redirectHistory' => $hops]));

        return $browsershot;
    }

    private function assertRedirectsStayedPublic(Browsershot $browsershot): void
    {
        $job = new CaptureScreenshot($this->screenshot());
        $method = new ReflectionMethod($job, 'assertRedirectsStayedPublic');
        $method->setAccessible(true);
        $method->invoke($job, $browsershot, app(PublicUrlGuard::class));
    }
}
