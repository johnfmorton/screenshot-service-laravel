<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The server's Chrome fell five releases behind unnoticed, and that — not its
 * IP — is what got captures blocked. The installation check now says so.
 */
class InstallationBrowserChecksTest extends TestCase
{
    use RefreshDatabase;

    private string $fakeChrome;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['screenshot.blocked_retry_proxy' => null]);
    }

    protected function tearDown(): void
    {
        if (isset($this->fakeChrome) && file_exists($this->fakeChrome)) {
            unlink($this->fakeChrome);
        }

        parent::tearDown();
    }

    public function test_a_current_chrome_passes(): void
    {
        $this->chrome('Google Chrome 154.0.8037.57', latest: '154.0.8037.57');

        $this->assertSame(['success', '154 (latest stable: 154)'], $this->row('Chrome version'));
    }

    /**
     * Rollouts take days, so being one release behind is normal.
     */
    public function test_one_release_behind_still_passes(): void
    {
        $this->chrome('Google Chrome 153.0.8010.52', latest: '154.0.8037.57');

        $this->assertSame('success', $this->row('Chrome version')[0]);
    }

    public function test_an_outdated_chrome_is_flagged(): void
    {
        $this->chrome('Google Chrome 149.0.7827.196', latest: '154.0.8037.57');

        $this->assertSame(['error', '149 (latest stable: 154)'], $this->row('Chrome version'));
        $this->assertStringContainsString('5 releases behind', $this->message('Chrome version'));
    }

    public function test_an_unreachable_release_api_is_a_warning_not_an_error(): void
    {
        $this->chrome('Google Chrome 154.0.8037.57', latest: null);

        $this->assertSame(['warning', '154'], $this->row('Chrome version'));
    }

    public function test_the_latest_version_is_cached_between_page_loads(): void
    {
        $this->chrome('Google Chrome 154.0.8037.57', latest: '154.0.8037.57');

        $this->row('Chrome version');
        $this->row('Chrome version');

        Http::assertSentCount(1);
    }

    public function test_an_unset_proxy_is_fine(): void
    {
        $this->chrome('Google Chrome 154.0.8037.57', latest: '154.0.8037.57');

        $this->assertSame(['success', '(not set)'], $this->row('SCREENSHOT_BLOCKED_RETRY_PROXY'));
    }

    /**
     * SCREENSHOT_BLOCKED_RETRY_PROXY=true resolves to boolean true.
     */
    public function test_a_proxy_that_is_not_a_url_is_flagged(): void
    {
        $this->chrome('Google Chrome 154.0.8037.57', latest: '154.0.8037.57');
        config(['screenshot.blocked_retry_proxy' => true]);

        $this->assertSame(['error', 'true'], $this->row('SCREENSHOT_BLOCKED_RETRY_PROXY'));
    }

    public function test_a_reachable_proxy_passes(): void
    {
        $this->chrome('Google Chrome 154.0.8037.57', latest: '154.0.8037.57');
        $server = stream_socket_server('tcp://127.0.0.1:0');
        config(['screenshot.blocked_retry_proxy' => 'http://' . stream_socket_get_name($server, false)]);

        try {
            $this->assertSame('success', $this->row('SCREENSHOT_BLOCKED_RETRY_PROXY')[0]);
        } finally {
            fclose($server);
        }
    }

    public function test_an_unreachable_proxy_is_a_warning(): void
    {
        $this->chrome('Google Chrome 154.0.8037.57', latest: '154.0.8037.57');
        $server = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($server, false);
        fclose($server); // the port is now closed
        config(['screenshot.blocked_retry_proxy' => "http://{$address}"]);

        $this->assertSame('warning', $this->row('SCREENSHOT_BLOCKED_RETRY_PROXY')[0]);
    }

    private function chrome(string $versionOutput, ?string $latest): void
    {
        $this->fakeChrome = tempnam(sys_get_temp_dir(), 'chrome-') ?: '';
        file_put_contents($this->fakeChrome, "#!/bin/sh\necho \"{$versionOutput}\"\n");
        chmod($this->fakeChrome, 0755);
        config(['screenshot.chrome_path' => $this->fakeChrome]);

        Http::fake(['versionhistory.googleapis.com/*' => $latest === null
            ? Http::response(null, 503)
            : Http::response(['versions' => [['version' => $latest]]])]);
    }

    /**
     * @return array{0: string, 1: string} status, value
     */
    private function row(string $name): array
    {
        $check = $this->checks()[$name];

        return [$check['status'], $check['value']];
    }

    private function message(string $name): ?string
    {
        return $this->checks()[$name]['message'];
    }

    private function checks(): array
    {
        $admin = User::factory()->create(['is_super_admin' => true]);

        return collect($this->actingAs($admin)->get(route('admin.installation-check'))->viewData('checks'))
            ->keyBy('name')
            ->all();
    }
}
