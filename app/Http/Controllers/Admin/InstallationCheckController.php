<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\Screenshot;
use App\Services\ScreenshotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InstallationCheckController extends Controller
{
    public function index(): View
    {
        $checks = $this->getEnvironmentChecks();

        return view('admin.installation-check', compact('checks'));
    }

    public function runTest(Request $request, ScreenshotService $screenshotService): JsonResponse
    {
        $request->validate([
            'url' => ['required', 'url', 'max:2048'],
        ]);

        // Get or create a system API key for testing
        $apiKey = $this->getTestApiKey();

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'error' => 'No API key available for testing. Please create an API key first.',
            ], 400);
        }

        try {
            $screenshot = $screenshotService->createScreenshot(
                apiKey: $apiKey,
                url: $request->input('url'),
                viewportWidth: config('screenshot.default_viewport_width'),
                viewportHeight: config('screenshot.default_viewport_height'),
                maxWidth: null,
                thumbnailWidth: config('screenshot.default_thumbnail_width'),
                thumbnailHeight: config('screenshot.default_thumbnail_height'),
                forceRefresh: true
            );

            return response()->json([
                'success' => true,
                'id' => $screenshot->id,
                'status' => $screenshot->status->value,
                'poll_url' => route('admin.installation-check.status', $screenshot),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to create screenshot: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function checkStatus(Screenshot $screenshot): JsonResponse
    {
        $data = [
            'id' => $screenshot->id,
            'status' => $screenshot->status->value,
            'is_completed' => $screenshot->isCompleted(),
            'is_failed' => $screenshot->isFailed(),
        ];

        if ($screenshot->isCompleted()) {
            $data['full_image_url'] = $screenshot->full_image_url;
            $data['thumbnail_url'] = $screenshot->thumbnail_url;
        }

        if ($screenshot->isFailed()) {
            $data['error_message'] = $screenshot->error_message;
            $data['troubleshooting'] = $this->getTroubleshootingTips($screenshot->error_message);
        }

        return response()->json($data);
    }

    private function getEnvironmentChecks(): array
    {
        $storageDisk = config('screenshot.storage_disk');
        $chromePath = config('screenshot.chrome_path');

        $checks = [
            [
                'name' => 'SCREENSHOT_CHROME_PATH',
                'value' => $chromePath,
                'status' => $chromePath && file_exists($chromePath) ? 'success' : 'error',
                'message' => $chromePath && file_exists($chromePath)
                    ? 'Chrome/Chromium found'
                    : 'Browser not found at path',
            ],
            [
                'name' => 'SCREENSHOT_STORAGE_DISK',
                'value' => $storageDisk,
                'status' => 'success',
                'message' => null,
            ],
            [
                'name' => 'QUEUE_CONNECTION',
                'value' => config('queue.default'),
                'status' => config('queue.default') !== 'sync' ? 'success' : 'warning',
                'message' => config('queue.default') === 'sync'
                    ? 'Using sync - screenshots processed immediately (not recommended for production)'
                    : null,
            ],
        ];

        // Add AWS checks if storage disk is s3
        if ($storageDisk === 's3') {
            $checks[] = [
                'name' => 'AWS_BUCKET',
                'value' => config('filesystems.disks.s3.bucket') ? '(configured)' : '(not set)',
                'status' => config('filesystems.disks.s3.bucket') ? 'success' : 'error',
                'message' => config('filesystems.disks.s3.bucket')
                    ? null
                    : 'Required for S3 storage',
            ];

            $checks[] = [
                'name' => 'AWS_ACCESS_KEY_ID',
                'value' => config('filesystems.disks.s3.key') ? '(configured)' : '(not set)',
                'status' => config('filesystems.disks.s3.key') ? 'success' : 'error',
                'message' => config('filesystems.disks.s3.key')
                    ? null
                    : 'Required for S3 storage',
            ];

            $checks[] = [
                'name' => 'AWS_SECRET_ACCESS_KEY',
                'value' => config('filesystems.disks.s3.secret') ? '(configured)' : '(not set)',
                'status' => config('filesystems.disks.s3.secret') ? 'success' : 'error',
                'message' => config('filesystems.disks.s3.secret')
                    ? null
                    : 'Required for S3 storage',
            ];

            $checks[] = [
                'name' => 'AWS_DEFAULT_REGION',
                'value' => config('filesystems.disks.s3.region') ?: '(not set)',
                'status' => config('filesystems.disks.s3.region') ? 'success' : 'warning',
                'message' => config('filesystems.disks.s3.region')
                    ? null
                    : 'Region not set, may use default',
            ];

            $checks[] = [
                'name' => 'AWS_URL',
                'value' => config('filesystems.disks.s3.url') ? '(configured)' : '(not set)',
                'status' => config('filesystems.disks.s3.url') ? 'success' : 'warning',
                'message' => config('filesystems.disks.s3.url')
                    ? 'CloudFront URL configured'
                    : 'Not set - will use direct S3 URLs',
            ];
        }

        return $checks;
    }

    private function getTestApiKey(): ?ApiKey
    {
        // First try to get an active API key belonging to the current user
        $user = auth()->user();
        $apiKey = $user->apiKeys()->where('is_active', true)->first();

        if ($apiKey) {
            return $apiKey;
        }

        // If super admin, try any active API key
        if ($user->isSuperAdmin()) {
            return ApiKey::where('is_active', true)->first();
        }

        return null;
    }

    private function getTroubleshootingTips(?string $errorMessage): array
    {
        if (!$errorMessage) {
            return [];
        }

        $tips = [];

        $lowerError = strtolower($errorMessage);

        if (str_contains($lowerError, 'browser') || str_contains($lowerError, 'chrome') || str_contains($lowerError, 'chromium')) {
            $tips[] = 'Check that SCREENSHOT_CHROME_PATH points to a valid Chrome/Chromium executable';
            $tips[] = 'Install chromium-browser if not present: apt-get install chromium-browser';
        }

        if (str_contains($lowerError, 'timeout')) {
            $tips[] = 'The screenshot took too long - check network access from the server';
            $tips[] = 'The target URL may be slow to load or unreachable';
        }

        if (str_contains($lowerError, 'permission') || str_contains($lowerError, 'denied')) {
            $tips[] = 'Check file permissions for the Chrome executable';
            $tips[] = 'Ensure the web server user has execute permissions';
        }

        if (str_contains($lowerError, 's3') || str_contains($lowerError, 'aws') || str_contains($lowerError, 'bucket')) {
            $tips[] = 'Verify AWS credentials are correctly configured';
            $tips[] = 'Check that the S3 bucket exists and is accessible';
            $tips[] = 'Ensure IAM permissions allow PutObject and GetObject';
        }

        if (empty($tips)) {
            $tips[] = 'Check the Laravel log file for more details: storage/logs/laravel.log';
            $tips[] = 'Ensure the queue worker is running: php artisan queue:work';
        }

        return $tips;
    }
}
