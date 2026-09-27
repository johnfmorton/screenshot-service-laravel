<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallationSecurityChecksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The page also checks Chrome's version against Google's release API.
        \Illuminate\Support\Facades\Http::fake(['*' => ['versions' => [['version' => '154.0.0.0']]]]);
    }

    public function test_risky_production_settings_are_flagged(): void
    {
        $this->app['env'] = 'production';
        config([
            'app.debug' => true,
            'app.url' => 'https://shots.example.com',
            'session.secure' => null,
            'screenshot.allow_private_urls' => true,
            'screenshot.chrome_sandbox' => false,
            'screenshot.storage_disk' => 's3',
            'filesystems.disks.s3.url' => 'https://d123.cloudfront.net',
            'screenshot.s3_public_acl' => true,
        ]);

        $checks = $this->checks();

        $this->assertSame('error', $checks['APP_DEBUG']);
        $this->assertSame('error', $checks['SCREENSHOT_ALLOW_PRIVATE_URLS']);
        $this->assertSame('warning', $checks['SCREENSHOT_CHROME_SANDBOX']);
        $this->assertSame('warning', $checks['SESSION_SECURE_COOKIE']);
        $this->assertSame('warning', $checks['SCREENSHOT_S3_PUBLIC_ACL']);
    }

    public function test_a_hardened_production_config_passes(): void
    {
        $this->app['env'] = 'production';
        config([
            'app.debug' => false,
            'app.url' => 'https://shots.example.com',
            'session.secure' => true,
            'screenshot.allow_private_urls' => false,
            'screenshot.chrome_sandbox' => true,
            'screenshot.storage_disk' => 's3',
            'filesystems.disks.s3.url' => 'https://d123.cloudfront.net',
            'screenshot.s3_public_acl' => false,
        ]);

        foreach (['APP_DEBUG', 'SCREENSHOT_ALLOW_PRIVATE_URLS', 'SCREENSHOT_CHROME_SANDBOX', 'SESSION_SECURE_COOKIE', 'SCREENSHOT_S3_PUBLIC_ACL'] as $name) {
            $this->assertSame('success', $this->checks()[$name], $name);
        }
    }

    public function test_local_development_settings_are_not_flagged(): void
    {
        $this->app['env'] = 'local';
        config(['app.debug' => true, 'screenshot.chrome_sandbox' => false]);

        $this->assertSame('success', $this->checks()['APP_DEBUG']);
        $this->assertSame('success', $this->checks()['SCREENSHOT_CHROME_SANDBOX']);
    }

    /**
     * @return array<string, string> name => status
     */
    private function checks(): array
    {
        $admin = User::factory()->create(['is_super_admin' => true]);

        return collect($this->actingAs($admin)->get(route('admin.installation-check'))->viewData('checks'))
            ->pluck('status', 'name')
            ->all();
    }
}
