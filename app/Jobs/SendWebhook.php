<?php

namespace App\Jobs;

use App\Enums\ScreenshotStatus;
use App\Exceptions\UrlNotAllowed;
use App\Models\Screenshot;
use App\Services\PublicUrlGuard;
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

    public function handle(PublicUrlGuard $guard): void
    {
        if (!$this->screenshot->webhook_url) {
            return;
        }

        try {
            $target = $guard->check($this->screenshot->webhook_url);
        } catch (UrlNotAllowed $e) {
            // Not retried: the address won't become acceptable by waiting.
            Log::warning('Webhook not sent: URL not allowed', [
                'screenshot_id' => $this->screenshot->id,
                'webhook_url' => $this->screenshot->webhook_url,
                'reason' => $e->getMessage(),
            ]);

            return;
        }

        // Signed and sent as the same bytes. Letting the HTTP client encode
        // the payload separately relied on two encoders happening to agree.
        $body = json_encode($this->buildPayload());
        $timestamp = (string) time();
        $headers = ['X-Webhook-Timestamp' => $timestamp];

        if ($this->screenshot->webhook_secret) {
            $secret = $this->screenshot->webhook_secret;

            // Body only, as clients have always verified it.
            $headers['X-Signature-256'] = hash_hmac('sha256', $body, $secret);

            // Covers the timestamp too, so a receiver that checks it can
            // reject a captured request replayed later.
            $headers['X-Signature-256-Timestamped'] = hash_hmac('sha256', "{$timestamp}.{$body}", $secret);
        }

        // Connect to the address that was just checked rather than letting
        // the HTTP client resolve the host again, which a DNS record with a
        // short TTL could answer differently. Redirects are refused for the
        // same reason: the target would skip the check entirely.
        $response = Http::withHeaders($headers)
            ->withOptions($this->pinnedResolution($target))
            ->withoutRedirecting()
            ->timeout(30)
            ->withBody($body, 'application/json')
            ->post($this->screenshot->webhook_url);

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

    /**
     * @param  array{host: string, addresses: list<string>}  $target
     */
    private function pinnedResolution(array $target): array
    {
        if ($target['addresses'] === []) {
            return [];
        }

        $address = $target['addresses'][0];
        $port = parse_url($this->screenshot->webhook_url, PHP_URL_PORT)
            ?? (str_starts_with(strtolower($this->screenshot->webhook_url), 'https:') ? 443 : 80);

        if (str_contains($address, ':')) {
            $address = "[{$address}]";
        }

        return ['curl' => [CURLOPT_RESOLVE => ["{$target['host']}:{$port}:{$address}"]]];
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

        if (in_array($this->screenshot->status, [ScreenshotStatus::Failed, ScreenshotStatus::Blocked], true)) {
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
