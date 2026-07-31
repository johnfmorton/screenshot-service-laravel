<?php

namespace Tests\Unit;

use App\Services\ChromeUserAgent;
use Tests\TestCase;

class ChromeUserAgentTest extends TestCase
{
    private string $fakeChrome;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'screenshot.default_user_agent' => null,
            'screenshot.user_agent_template' => 'Mozilla/5.0 (X11; Linux x86_64) Chrome/{version} Safari/537.36',
        ]);
    }

    protected function tearDown(): void
    {
        if (isset($this->fakeChrome) && file_exists($this->fakeChrome)) {
            unlink($this->fakeChrome);
        }

        parent::tearDown();
    }

    /**
     * The whole point of deriving the version: it has to match what Chrome
     * reports in sec-ch-ua, or the user agent contradicts the hints on every
     * request and the capture looks more automated, not less.
     */
    public function test_the_version_is_read_from_the_installed_chrome(): void
    {
        $this->fakeChrome('Google Chrome 149.0.7827.196');

        $this->assertStringContainsString('Chrome/149.0.0.0', (new ChromeUserAgent())->resolve());
    }

    public function test_a_chromium_build_is_recognised_too(): void
    {
        $this->fakeChrome('Chromium 143.0.7295.0');

        $this->assertStringContainsString('Chrome/143.0.0.0', (new ChromeUserAgent())->resolve());
    }

    /**
     * A broken Chrome path means the capture is going to fail anyway. What
     * matters is that it doesn't fail *while* advertising HeadlessChrome.
     */
    public function test_a_missing_chrome_falls_back_rather_than_leaking_headless(): void
    {
        config(['screenshot.chrome_path' => '/nonexistent/google-chrome']);

        $userAgent = (new ChromeUserAgent())->resolve();

        $this->assertStringNotContainsString('Headless', $userAgent);
        $this->assertStringNotContainsString('{version}', $userAgent);
        $this->assertMatchesRegularExpression('/Chrome\/\d+\.0\.0\.0/', $userAgent);
    }

    public function test_an_explicitly_configured_user_agent_wins(): void
    {
        $this->fakeChrome('Google Chrome 149.0.7827.196');
        config(['screenshot.default_user_agent' => 'Custom UA']);

        $this->assertSame('Custom UA', (new ChromeUserAgent())->resolve());
    }

    /**
     * An unset env var arrives as an empty string rather than null, and that
     * must not be mistaken for "the operator pinned an empty user agent",
     * which would hand Chrome its HeadlessChrome default.
     */
    public function test_a_blank_configured_user_agent_is_treated_as_unset(): void
    {
        $this->fakeChrome('Google Chrome 149.0.7827.196');
        config(['screenshot.default_user_agent' => '   ']);

        $this->assertStringContainsString('Chrome/149.0.0.0', (new ChromeUserAgent())->resolve());
    }

    public function test_the_template_placeholder_is_always_replaced(): void
    {
        $this->fakeChrome('Google Chrome 149.0.7827.196');

        $this->assertStringNotContainsString('{version}', (new ChromeUserAgent())->resolve());
    }

    private function fakeChrome(string $versionOutput): void
    {
        $this->fakeChrome = tempnam(sys_get_temp_dir(), 'chrome-') ?: '';
        file_put_contents($this->fakeChrome, "#!/bin/sh\necho \"{$versionOutput}\"\n");
        chmod($this->fakeChrome, 0755);

        config(['screenshot.chrome_path' => $this->fakeChrome]);
    }
}
