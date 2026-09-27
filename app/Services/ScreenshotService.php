<?php

namespace App\Services;

use App\Enums\ScreenshotStatus;
use App\Jobs\CaptureScreenshot;
use App\Jobs\DeleteStoredScreenshots;
use App\Models\ApiKey;
use App\Models\Screenshot;
use Carbon\Carbon;

class ScreenshotService
{
    public function createScreenshot(
        ApiKey $apiKey,
        string $url,
        int $viewportWidth,
        int $viewportHeight,
        ?int $maxWidth,
        int $thumbnailWidth,
        int $thumbnailHeight,
        ?string $waitUntil = null,
        ?int $timeout = null,
        ?string $userAgent = null,
        bool $forceRefresh = false,
        ?string $webhookUrl = null,
        ?string $webhookSecret = null
    ): Screenshot {
        $urlHash = Screenshot::generateUrlHash(
            $url,
            $viewportWidth,
            $viewportHeight,
            $maxWidth,
            $thumbnailWidth,
            $thumbnailHeight
        );

        if (!$forceRefresh) {
            $cached = $this->findCachedScreenshot($apiKey, $urlHash);
            if ($cached) {
                return $cached;
            }
        }

        $screenshot = Screenshot::create([
            'api_key_id' => $apiKey->id,
            'url' => $url,
            'url_hash' => $urlHash,
            'viewport_width' => $viewportWidth,
            'viewport_height' => $viewportHeight,
            'max_width' => $maxWidth,
            'thumbnail_width' => $thumbnailWidth,
            'thumbnail_height' => $thumbnailHeight,
            'wait_until' => $waitUntil ?? config('screenshot.default_wait_until'),
            'timeout' => $timeout ?? config('screenshot.default_timeout'),
            'user_agent' => $userAgent,
            'status' => ScreenshotStatus::Pending,
            'webhook_url' => $webhookUrl,
            'webhook_secret' => $webhookSecret,
            'expires_at' => Carbon::now()->addHours(config('screenshot.ttl_hours')),
        ]);

        CaptureScreenshot::dispatch($screenshot);

        return $screenshot;
    }

    public function findCachedScreenshot(ApiKey $apiKey, string $urlHash): ?Screenshot
    {
        return Screenshot::where('api_key_id', $apiKey->id)
            ->where('url_hash', $urlHash)
            ->where('status', ScreenshotStatus::Completed)
            ->where('expires_at', '>', Carbon::now())
            ->first();
    }

    public function deleteScreenshot(Screenshot $screenshot): void
    {
        $disk = config('screenshot.storage_disk');

        if ($screenshot->full_image_path) {
            \Storage::disk($disk)->delete($screenshot->full_image_path);
        }

        if ($screenshot->thumbnail_path) {
            \Storage::disk($disk)->delete($screenshot->thumbnail_path);
        }

        $screenshot->delete();
    }

    /**
     * Revokes the key immediately and clears its stored images afterwards.
     *
     * The rows go with the key through the foreign key cascade. Deleting the
     * key directly used to orphan every image it had captured, since nothing
     * else knows those paths once the rows are gone.
     */
    public function deleteApiKey(ApiKey $apiKey): void
    {
        $paths = $apiKey->screenshots()
            ->toBase()
            ->get(['full_image_path', 'thumbnail_path'])
            ->flatMap(fn (object $row): array => [$row->full_image_path, $row->thumbnail_path])
            ->filter()
            ->values();

        $apiKey->delete();

        $disk = config('screenshot.storage_disk');

        foreach ($paths->chunk(500) as $chunk) {
            DeleteStoredScreenshots::dispatch($disk, $chunk->values()->all());
        }
    }
}
