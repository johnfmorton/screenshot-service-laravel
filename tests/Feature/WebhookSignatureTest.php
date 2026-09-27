<?php

namespace Tests\Feature;

use App\Enums\ScreenshotStatus;
use App\Jobs\SendWebhook;
use App\Models\ApiKey;
use App\Models\Screenshot;
use App\Services\PublicUrlGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebhookSignatureTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'whsec_test';

    public function test_the_body_signature_is_unchanged_for_existing_clients(): void
    {
        $request = $this->deliver();

        $this->assertSame(
            hash_hmac('sha256', $request->body(), self::SECRET),
            $request->header('X-Signature-256')[0]
        );
    }

    /**
     * Clients verify against the raw bytes they receive. Those must be the
     * bytes that were signed, not a second encoding of the same payload.
     */
    public function test_the_signed_bytes_are_the_bytes_sent(): void
    {
        $request = $this->deliver();

        $this->assertSame('application/json', $request->header('Content-Type')[0]);
        $this->assertSame('completed', json_decode($request->body(), true)['status']);
        $this->assertTrue(hash_equals(
            hash_hmac('sha256', $request->body(), self::SECRET),
            $request->header('X-Signature-256')[0]
        ));
    }

    public function test_the_timestamped_signature_covers_the_timestamp(): void
    {
        $this->freezeTime();
        $request = $this->deliver();

        $timestamp = $request->header('X-Webhook-Timestamp')[0];
        $this->assertSame((string) now()->getTimestamp(), $timestamp);

        $this->assertSame(
            hash_hmac('sha256', "{$timestamp}.{$request->body()}", self::SECRET),
            $request->header('X-Signature-256-Timestamped')[0]
        );

        // The same body under a different timestamp doesn't verify.
        $this->assertNotSame(
            hash_hmac('sha256', ($timestamp - 3600) . ".{$request->body()}", self::SECRET),
            $request->header('X-Signature-256-Timestamped')[0]
        );
    }

    public function test_no_signatures_without_a_secret(): void
    {
        $request = $this->deliver(secret: null);

        $this->assertFalse($request->hasHeader('X-Signature-256'));
        $this->assertFalse($request->hasHeader('X-Signature-256-Timestamped'));
    }

    private function deliver(?string $secret = self::SECRET): Request
    {
        Http::fake(['*' => Http::response(null, 200)]);

        $screenshot = Screenshot::create([
            'api_key_id' => ApiKey::generate('Key')->id,
            'url' => 'https://example.com/a/b?c=d',
            'url_hash' => hash('sha256', uniqid()),
            'viewport_width' => 1280,
            'viewport_height' => 800,
            'thumbnail_width' => 400,
            'thumbnail_height' => 300,
            'status' => ScreenshotStatus::Completed,
            'full_image_path' => 'screenshots/x-full.png',
            'thumbnail_path' => 'screenshots/x-thumb.png',
            'webhook_url' => 'https://example.com/hook',
            'webhook_secret' => $secret,
            'captured_at' => now(),
            'expires_at' => now()->addDay(),
        ]);

        (new SendWebhook($screenshot))->handle(new PublicUrlGuard(fn () => ['93.184.215.14']));

        $sent = null;
        Http::assertSent(function (Request $request) use (&$sent) {
            $sent = $request;

            return true;
        });

        return $sent;
    }
}
