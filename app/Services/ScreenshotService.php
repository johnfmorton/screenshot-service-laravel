<?php

namespace App\Services;

use App\Enums\ScreenshotStatus;
use App\Jobs\CaptureScreenshot;
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
        string $waitUntil = 'networkidle0',
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
            'wait_until' => $waitUntil,
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
}
