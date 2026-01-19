<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class ApiDocumentationController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'name' => 'URL Screenshot Service API',
            'version' => '1.0.0',
            'description' => 'Capture screenshots of URLs using headless Chrome. Returns full-size and thumbnail images.',
            'authentication' => [
                'type' => 'API Key',
                'header' => 'X-API-Key',
                'description' => 'Include your API key in the X-API-Key header with every request.',
            ],
            'endpoints' => [
                [
                    'method' => 'POST',
                    'path' => '/api/screenshots',
                    'description' => 'Create a new screenshot request',
                    'headers' => [
                        'X-API-Key' => 'Your API key (required)',
                        'Content-Type' => 'application/json',
                    ],
                    'body' => [
                        'url' => '(required) The URL to capture',
                        'viewport_width' => '(optional) Viewport width in pixels, default: 1280',
                        'viewport_height' => '(optional) Viewport height in pixels, default: 800',
                        'max_width' => '(optional) Maximum width for full-size image',
                        'thumbnail_width' => '(optional) Thumbnail width, default: 400',
                        'thumbnail_height' => '(optional) Thumbnail height, default: 300',
                        'force_refresh' => '(optional) Bypass cache and capture fresh screenshot, default: false',
                        'webhook_url' => '(optional) URL to receive webhook when screenshot is ready',
                        'webhook_secret' => '(optional) Secret for webhook signature verification',
                    ],
                    'response' => [
                        'status' => '202 Accepted (processing) or 200 OK (cached)',
                        'body' => [
                            'id' => 'Screenshot UUID',
                            'status' => 'pending | processing | completed | failed',
                            'poll_url' => 'URL to check screenshot status',
                        ],
                    ],
                ],
                [
                    'method' => 'GET',
                    'path' => '/api/screenshots/{id}',
                    'description' => 'Get screenshot status and image URLs',
                    'headers' => [
                        'X-API-Key' => 'Your API key (required)',
                    ],
                    'response' => [
                        'status' => '200 OK',
                        'body' => [
                            'id' => 'Screenshot UUID',
                            'status' => 'pending | processing | completed | failed',
                            'url' => 'Original URL that was captured',
                            'images' => '(when completed) { full: "...", thumbnail: "..." }',
                            'captured_at' => '(when completed) ISO 8601 timestamp',
                            'expires_at' => '(when completed) ISO 8601 timestamp',
                            'error' => '(when failed) Error message',
                        ],
                    ],
                ],
                [
                    'method' => 'DELETE',
                    'path' => '/api/screenshots/{id}',
                    'description' => 'Delete a screenshot and invalidate cache',
                    'headers' => [
                        'X-API-Key' => 'Your API key (required)',
                    ],
                    'response' => [
                        'status' => '204 No Content',
                    ],
                ],
            ],
            'example' => [
                'request' => 'curl -X POST ' . url('/api/screenshots') . ' -H "Content-Type: application/json" -H "X-API-Key: YOUR_API_KEY" -d \'{"url": "https://example.com"}\'',
                'response' => [
                    'id' => '019bd849-68c3-738d-b9d2-6218828f35c4',
                    'status' => 'pending',
                    'poll_url' => url('/api/screenshots/019bd849-68c3-738d-b9d2-6218828f35c4'),
                ],
            ],
        ]);
    }
}
