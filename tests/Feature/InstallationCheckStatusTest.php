<?php

namespace Tests\Feature;

use App\Enums\ScreenshotStatus;
use App\Models\ApiKey;
use App\Models\Screenshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallationCheckStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_sub_user_cannot_read_another_keys_capture(): void
    {
        $owner = User::factory()->create(['is_super_admin' => false]);
        $screenshot = $this->failedScreenshotFor($owner);

        $this->actingAs(User::factory()->create(['is_super_admin' => false]))
            ->getJson(route('admin.installation-check.status', $screenshot))
            ->assertNotFound();
    }

    public function test_a_sub_user_can_read_their_own_capture(): void
    {
        $owner = User::factory()->create(['is_super_admin' => false]);
        $screenshot = $this->failedScreenshotFor($owner);

        $this->actingAs($owner)
            ->getJson(route('admin.installation-check.status', $screenshot))
            ->assertOk();
    }

    /**
     * The installation check is for diagnosing the server, so it shows the
     * raw error that API clients no longer see.
     */
    public function test_admins_see_the_raw_error_detail(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $screenshot = $this->failedScreenshotFor(User::factory()->create(['is_super_admin' => false]));

        $this->actingAs($admin)
            ->getJson(route('admin.installation-check.status', $screenshot))
            ->assertOk()
            ->assertJsonPath('error_message', 'Failed to launch the browser process! /usr/bin/chromium')
            ->assertJsonPath('troubleshooting.0', 'Check that SCREENSHOT_CHROME_PATH points to a valid Chrome/Chromium executable');
    }

    private function failedScreenshotFor(User $user): Screenshot
    {
        $apiKey = ApiKey::generate('Key');
        $apiKey->update(['user_id' => $user->id]);

        return Screenshot::create([
            'api_key_id' => $apiKey->id,
            'url' => 'https://example.com/',
            'url_hash' => hash('sha256', uniqid()),
            'viewport_width' => 1280,
            'viewport_height' => 800,
            'thumbnail_width' => 400,
            'thumbnail_height' => 300,
            'status' => ScreenshotStatus::Failed,
            'error_message' => 'The browser failed to capture the page.',
            'error_detail' => 'Failed to launch the browser process! /usr/bin/chromium',
            'expires_at' => now()->addDay(),
        ]);
    }
}
