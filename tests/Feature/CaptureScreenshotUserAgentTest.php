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
 * The user agent has to reach Chrome as a launch flag, never as Puppeteer's
 * per-page override. The override silently costs more than it buys: Chrome
 * stops sending sec-ch-ua and reports an empty navigator.userAgentData.brands,
 * so the capture advertises itself as a Chrome with no brands — and requests
 * the page didn't initiate can still escape carrying the real headless UA.
 */
class CaptureScreenshotUserAgentTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_user_agent_is_passed_as_a_launch_flag(): void
    {
        config(['screenshot.default_user_agent' => 'Pinned UA']);

        $this->assertContains('--user-agent=Pinned UA', $this->chromiumArguments());
    }

    public function test_the_page_level_override_is_not_used(): void
    {
        config(['screenshot.default_user_agent' => 'Pinned UA']);

        $this->assertArrayNotHasKey('userAgent', $this->browsershotOptions());
    }

    public function test_a_per_request_user_agent_takes_precedence(): void
    {
        config(['screenshot.default_user_agent' => 'Pinned UA']);

        $arguments = $this->chromiumArguments(['user_agent' => 'Per Request UA']);

        $this->assertContains('--user-agent=Per Request UA', $arguments);
        $this->assertNotContains('--user-agent=Pinned UA', $arguments);
    }

    /**
     * Chrome sends accurate sec-ch-ua-platform and sec-ch-ua-mobile itself once
     * the user agent is set at launch. Forcing them here could only disagree
     * with what scripts read from navigator.userAgentData.
     */
    public function test_no_client_hint_headers_are_forced(): void
    {
        $headers = $this->browsershotOptions()['extraHTTPHeaders'] ?? [];

        foreach (array_keys($headers) as $header) {
            $this->assertStringNotContainsString('sec-ch-ua', strtolower($header));
        }
    }

    public function test_the_language_header_is_still_sent(): void
    {
        config(['screenshot.default_headers' => ['Accept-Language' => 'en-US,en;q=0.9']]);

        $this->assertSame(
            'en-US,en;q=0.9',
            $this->browsershotOptions()['extraHTTPHeaders']['Accept-Language'] ?? null
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<int, string>
     */
    private function chromiumArguments(array $attributes = []): array
    {
        $property = new ReflectionProperty(Browsershot::class, 'chromiumArguments');
        $property->setAccessible(true);

        return $property->getValue($this->buildBrowsershot($attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function browsershotOptions(array $attributes = []): array
    {
        $property = new ReflectionProperty(Browsershot::class, 'additionalOptions');
        $property->setAccessible(true);

        return $property->getValue($this->buildBrowsershot($attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function buildBrowsershot(array $attributes = []): Browsershot
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
            'status' => ScreenshotStatus::Pending,
            'expires_at' => now()->addHours(24),
        ], $attributes));

        $method = new ReflectionMethod(CaptureScreenshot::class, 'buildBrowsershot');
        $method->setAccessible(true);

        return $method->invoke(
            new CaptureScreenshot($screenshot),
            new ChallengeDetector(),
            new ChromeUserAgent()
        );
    }
}
