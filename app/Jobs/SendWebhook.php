<?php

namespace App\Jobs;

use App\Enums\ScreenshotStatus;
use App\Models\Screenshot;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public array $backoff = [5, 30, 120];

    public function __construct(
        public Screenshot $screenshot
    ) {}

    public function handle(): void
    {
        if (!$this->screenshot->webhook_url) {
            return;
        }

        $payload = $this->buildPayload();
        $headers = ['Content-Type' => 'application/json'];

        if ($this->screenshot->webhook_secret) {
            $signature = hash_hmac('sha256', json_encode($payload), $this->screenshot->webhook_secret);
            $headers['X-Signature-256'] = $signature;
        }

        $response = Http::withHeaders($headers)
            ->timeout(30)
            ->post($this->screenshot->webhook_url, $payload);

        if ($response->successful()) {
            $this->screenshot->update(['webhook_sent_at' => Carbon::now()]);
        } else {
            Log::warning('Webhook delivery failed', [
                'screenshot_id' => $this->screenshot->id,
                'webhook_url' => $this->screenshot->webhook_url,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new \Exception('Webhook delivery failed with status: ' . $response->status());
        }
    }

    private function buildPayload(): array
    {
        $payload = [
            'id' => $this->screenshot->id,
            'status' => $this->screenshot->status->value,
            'url' => $this->screenshot->url,
        ];

        if ($this->screenshot->status === ScreenshotStatus::Completed) {
            $payload['images'] = [
                'full' => $this->screenshot->full_image_url,
                'thumbnail' => $this->screenshot->thumbnail_url,
            ];
            $payload['captured_at'] = $this->screenshot->captured_at?->toIso8601String();
            $payload['expires_at'] = $this->screenshot->expires_at?->toIso8601String();
        }

        if ($this->screenshot->status === ScreenshotStatus::Failed) {
            $payload['error'] = $this->screenshot->error_message;
        }

        return $payload;
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Webhook delivery ultimately failed after retries', [
            'screenshot_id' => $this->screenshot->id,
            'webhook_url' => $this->screenshot->webhook_url,
            'error' => $exception->getMessage(),
        ]);
    }
}
