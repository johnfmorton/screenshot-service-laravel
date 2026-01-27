<?php

namespace App\Models;

use App\Enums\ScreenshotStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Screenshot extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'api_key_id',
        'url',
        'url_hash',
        'viewport_width',
        'viewport_height',
        'max_width',
        'thumbnail_width',
        'thumbnail_height',
        'wait_until',
        'timeout',
        'user_agent',
        'status',
        'full_image_path',
        'thumbnail_path',
        'error_message',
        'webhook_url',
        'webhook_secret',
        'webhook_sent_at',
        'captured_at',
        'expires_at',
    ];

    protected $casts = [
        'viewport_width' => 'integer',
        'viewport_height' => 'integer',
        'max_width' => 'integer',
        'thumbnail_width' => 'integer',
        'thumbnail_height' => 'integer',
        'timeout' => 'integer',
        'status' => ScreenshotStatus::class,
        'webhook_sent_at' => 'datetime',
        'captured_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $hidden = [
        'webhook_secret',
    ];

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }

    public function getFullImageUrlAttribute(): ?string
    {
        if (!$this->full_image_path) {
            return null;
        }

        return Storage::disk(config('screenshot.storage_disk'))->url($this->full_image_path);
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        if (!$this->thumbnail_path) {
            return null;
        }

        return Storage::disk(config('screenshot.storage_disk'))->url($this->thumbnail_path);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isCompleted(): bool
    {
        return $this->status === ScreenshotStatus::Completed;
    }

    public function isFailed(): bool
    {
        return $this->status === ScreenshotStatus::Failed;
    }

    public function isPending(): bool
    {
        return $this->status === ScreenshotStatus::Pending;
    }

    public function isProcessing(): bool
    {
        return $this->status === ScreenshotStatus::Processing;
    }

    public static function generateUrlHash(
        string $url,
        int $viewportWidth,
        int $viewportHeight,
        ?int $maxWidth,
        int $thumbnailWidth,
        int $thumbnailHeight
    ): string {
        return hash('sha256', implode('|', [
            $url,
            $viewportWidth,
            $viewportHeight,
            $maxWidth ?? '',
            $thumbnailWidth,
            $thumbnailHeight,
        ]));
    }
}
