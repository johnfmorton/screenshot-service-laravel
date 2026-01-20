<?php

namespace App\Http\Controllers;

use App\Enums\ScreenshotStatus;
use App\Http\Requests\CreateScreenshotRequest;
use App\Models\Screenshot;
use App\Services\ScreenshotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScreenshotController extends Controller
{
    public function __construct(
        private ScreenshotService $screenshotService
    ) {}

    public function store(CreateScreenshotRequest $request): JsonResponse
    {
        $apiKey = $request->attributes->get('api_key');

        $screenshot = $this->screenshotService->createScreenshot(
            apiKey: $apiKey,
            url: $request->input('url'),
            viewportWidth: $request->getViewportWidth(),
            viewportHeight: $request->getViewportHeight(),
            maxWidth: $request->getMaxWidth(),
            thumbnailWidth: $request->getThumbnailWidth(),
            thumbnailHeight: $request->getThumbnailHeight(),
            waitUntil: $request->getWaitUntil(),
            forceRefresh: $request->getForceRefresh(),
            webhookUrl: $request->getWebhookUrl(),
            webhookSecret: $request->getWebhookSecret(),
        );

        if ($screenshot->isCompleted()) {
            return response()->json($this->formatScreenshot($screenshot), 200);
        }

        return response()->json([
            'id' => $screenshot->id,
            'status' => $screenshot->status->value,
            'poll_url' => route('screenshots.show', $screenshot),
        ], 202);
    }

    public function show(Request $request, Screenshot $screenshot): JsonResponse
    {
        $apiKey = $request->attributes->get('api_key');

        if ($screenshot->api_key_id !== $apiKey->id) {
            return response()->json([
                'error' => 'Not found',
                'message' => 'Screenshot not found',
            ], 404);
        }

        return response()->json($this->formatScreenshot($screenshot));
    }

    public function destroy(Request $request, Screenshot $screenshot): JsonResponse
    {
        $apiKey = $request->attributes->get('api_key');

        if ($screenshot->api_key_id !== $apiKey->id) {
            return response()->json([
                'error' => 'Not found',
                'message' => 'Screenshot not found',
            ], 404);
        }

        $this->screenshotService->deleteScreenshot($screenshot);

        return response()->json(null, 204);
    }

    private function formatScreenshot(Screenshot $screenshot): array
    {
        $data = [
            'id' => $screenshot->id,
            'status' => $screenshot->status->value,
            'url' => $screenshot->url,
        ];

        if ($screenshot->status === ScreenshotStatus::Completed) {
            $data['images'] = [
                'full' => $screenshot->full_image_url,
                'thumbnail' => $screenshot->thumbnail_url,
            ];
            $data['captured_at'] = $screenshot->captured_at?->toIso8601String();
            $data['expires_at'] = $screenshot->expires_at?->toIso8601String();
        }

        if ($screenshot->status === ScreenshotStatus::Failed) {
            $data['error'] = $screenshot->error_message;
        }

        return $data;
    }
}
