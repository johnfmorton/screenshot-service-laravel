<?php

namespace Tests\Feature;

use App\Enums\ScreenshotStatus;
use App\Jobs\CaptureScreenshot;
use App\Models\ApiKey;
use App\Models\Screenshot;
use App\Services\ChallengeDetector;
use App\Services\ChromeUserAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use ReflectionProperty;
use Spatie\Browsershot\Browsershot;
use Tests\TestCase;

/**
 * Chrome renders pages chosen by API clients. Without the sandbox, a renderer
 * exploit in one of them runs as the worker's user.
 */
class CaptureScreenshotSandboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_chrome_is_sandboxed_by_default(): void
    {
        config(['screenshot.chrome_sandbox' => true]);

        $this->assertFalse($this->noSandbox());
    }

    public function test_the_sandbox_can_be_disabled_where_it_cannot_start(): void
    {
        config(['screenshot.chrome_sandbox' => false]);

        $this->assertTrue($this->noSandbox());
    }

    public function test_the_shipped_default_is_sandboxed(): void
    {
        $this->assertSame(
            "'chrome_sandbox' => (bool) env('SCREENSHOT_CHROME_SANDBOX', true),",
            trim(collect(file(config_path('screenshot.php')))->first(
                fn (string $line) => str_contains($line, "'chrome_sandbox'")
            ))
        );
    }

    private function noSandbox(): bool
    {
        $apiKey = ApiKey::generate('Test Key');

        $screenshot = Screenshot::create([
            'api_key_id' => $apiKey->id,
            'url' => 'https://example.com',
            'url_hash' => Screenshot::generateUrlHash('https://example.com', 1280, 800, null, 400, 300),
            'viewport_width' => 1280,
            'viewport_height' => 800,
            'thumbnail_width' => 400,
            'thumbnail_height' => 300,
            'status' => ScreenshotStatus::Pending,
            'expires_at' => now()->addHours(24),
        ]);

        $job = new CaptureScreenshot($screenshot);
        $method = new ReflectionMethod($job, 'buildBrowsershot');
        $method->setAccessible(true);
        $browsershot = $method->invoke($job, new ChallengeDetector(), app(ChromeUserAgent::class));

        $property = new ReflectionProperty(Browsershot::class, 'noSandbox');
        $property->setAccessible(true);

        return $property->getValue($browsershot);
    }
}
