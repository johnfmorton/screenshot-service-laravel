<?php

namespace Tests\Feature;

use App\Enums\ScreenshotStatus;
use App\Jobs\DeleteStoredScreenshots;
use App\Models\ApiKey;
use App\Models\Screenshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApiKeyRevocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_a_user_revokes_their_api_key(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $user = User::factory()->create();
        $apiKey = $this->keyFor($user);

        $this->actingAs($admin)->delete(route('admin.users.destroy', $user))->assertRedirect();

        $this->assertModelMissing($apiKey);
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/screenshots/' . fake()->uuid(), ['X-API-Key' => $apiKey->plainTextKey])
            ->assertUnauthorized();
    }

    /**
     * Covers deletions that bypass the admin controller, e.g. from tinker.
     */
    public function test_the_database_also_removes_a_deleted_users_keys(): void
    {
        $user = User::factory()->create();
        $apiKey = $this->keyFor($user);

        $user->delete();

        $this->assertModelMissing($apiKey);
    }

    public function test_deleting_a_key_clears_its_stored_images(): void
    {
        Storage::fake('public');
        config(['screenshot.storage_disk' => 'public']);

        $admin = User::factory()->create(['is_super_admin' => true]);
        $apiKey = $this->keyFor($admin);
        $screenshot = $this->storedScreenshot($apiKey);

        $this->actingAs($admin)->delete(route('admin.api-keys.destroy', $apiKey))->assertRedirect();

        $this->assertModelMissing($screenshot);
        Storage::disk('public')->assertMissing($screenshot->full_image_path);
        Storage::disk('public')->assertMissing($screenshot->thumbnail_path);
    }

    /**
     * A key can hold a day's worth of captures; removing them one storage call
     * at a time belongs on the queue, not in the admin's request.
     */
    public function test_image_deletion_is_queued_in_chunks(): void
    {
        Queue::fake();
        Storage::fake('public');
        config(['screenshot.storage_disk' => 'public']);

        $admin = User::factory()->create(['is_super_admin' => true]);
        $apiKey = $this->keyFor($admin);
        for ($i = 0; $i < 300; $i++) {
            $this->storedScreenshot($apiKey);
        }

        $this->actingAs($admin)->delete(route('admin.api-keys.destroy', $apiKey));

        // 600 files: one chunk of 500, one of 100.
        Queue::assertPushed(DeleteStoredScreenshots::class, 2);
        Queue::assertPushed(DeleteStoredScreenshots::class, fn ($job) => count($job->paths) === 100);
    }

    private function keyFor(User $user): ApiKey
    {
        $apiKey = ApiKey::generate('Test Key');
        $apiKey->update(['user_id' => $user->id]);

        return $apiKey;
    }

    private function storedScreenshot(ApiKey $apiKey): Screenshot
    {
        $id = fake()->uuid();
        Storage::disk('public')->put("screenshots/{$id}-full.png", 'png');
        Storage::disk('public')->put("screenshots/{$id}-thumb.png", 'png');

        return Screenshot::create([
            'api_key_id' => $apiKey->id,
            'url' => 'https://example.com/',
            'url_hash' => hash('sha256', $id),
            'viewport_width' => 1280,
            'viewport_height' => 800,
            'thumbnail_width' => 400,
            'thumbnail_height' => 300,
            'status' => ScreenshotStatus::Completed,
            'full_image_path' => "screenshots/{$id}-full.png",
            'thumbnail_path' => "screenshots/{$id}-thumb.png",
            'expires_at' => now()->addDay(),
        ]);
    }
}
