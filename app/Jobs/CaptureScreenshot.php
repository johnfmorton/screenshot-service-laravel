<?php

namespace App\Jobs;

use App\Enums\ScreenshotStatus;
use App\Models\Screenshot;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Spatie\Browsershot\Browsershot;
use Throwable;

class CaptureScreenshot implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 120;
    public array $backoff = [10, 30, 60];

    public function __construct(
        public Screenshot $screenshot
    ) {}

    public function handle(): void
    {
        $this->screenshot->update(['status' => ScreenshotStatus::Processing]);

        try {
            $tempDir = sys_get_temp_dir();
            $fullPath = $tempDir . '/' . $this->screenshot->id . '-full.png';
            $thumbnailPath = $tempDir . '/' . $this->screenshot->id . '-thumb.png';

            $browsershot = Browsershot::url($this->screenshot->url)
                ->windowSize($this->screenshot->viewport_width, $this->screenshot->viewport_height)
                ->setOption('waitUntil', $this->screenshot->wait_until)
                ->timeout(60)
                ->setChromePath(config('screenshot.chrome_path'))
                ->noSandbox();

            if ($this->screenshot->user_agent) {
                $browsershot->userAgent($this->screenshot->user_agent);
            }

            $browsershot->save($fullPath);

            $manager = new ImageManager(new Driver());

            if ($this->screenshot->max_width) {
                $image = $manager->read($fullPath);
                if ($image->width() > $this->screenshot->max_width) {
                    $image->scale(width: $this->screenshot->max_width);
                    $image->save($fullPath);
                }
            }

            $thumbnail = $manager->read($fullPath);
            $thumbnail->cover($this->screenshot->thumbnail_width, $this->screenshot->thumbnail_height);
            $thumbnail->save($thumbnailPath);

            $disk = config('screenshot.storage_disk');
            $storagePath = $disk === 's3'
                ? config('screenshot.storage_path', 'screenshots')
                : 'screenshots';
            $s3FullPath = $storagePath . '/' . $this->screenshot->id . '-full.png';
            $s3ThumbnailPath = $storagePath . '/' . $this->screenshot->id . '-thumb.png';

            Storage::disk($disk)->put($s3FullPath, file_get_contents($fullPath), 'public');
            Storage::disk($disk)->put($s3ThumbnailPath, file_get_contents($thumbnailPath), 'public');

            @unlink($fullPath);
            @unlink($thumbnailPath);

            $this->screenshot->update([
                'status' => ScreenshotStatus::Completed,
                'full_image_path' => $s3FullPath,
                'thumbnail_path' => $s3ThumbnailPath,
                'captured_at' => Carbon::now(),
            ]);

            if ($this->screenshot->webhook_url) {
                SendWebhook::dispatch($this->screenshot);
            }

        } catch (Throwable $e) {
            Log::error('Screenshot capture failed', [
                'screenshot_id' => $this->screenshot->id,
                'url' => $this->screenshot->url,
                'error' => $e->getMessage(),
            ]);

            $this->screenshot->update([
                'status' => ScreenshotStatus::Failed,
                'error_message' => $e->getMessage(),
            ]);

            if ($this->screenshot->webhook_url) {
                SendWebhook::dispatch($this->screenshot);
            }
        }
    }

    public function failed(Throwable $exception): void
    {
        $this->screenshot->update([
            'status' => ScreenshotStatus::Failed,
            'error_message' => $exception->getMessage(),
        ]);

        if ($this->screenshot->webhook_url) {
            SendWebhook::dispatch($this->screenshot);
        }
    }
}
