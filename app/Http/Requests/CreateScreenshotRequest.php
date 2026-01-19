<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateScreenshotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'url' => ['required', 'url', 'max:2048'],
            'viewport_width' => ['sometimes', 'integer', 'min:320', 'max:3840'],
            'viewport_height' => ['sometimes', 'integer', 'min:240', 'max:2160'],
            'max_width' => ['sometimes', 'nullable', 'integer', 'min:100', 'max:3840'],
            'thumbnail_width' => ['sometimes', 'integer', 'min:50', 'max:1920'],
            'thumbnail_height' => ['sometimes', 'integer', 'min:50', 'max:1920'],
            'force_refresh' => ['sometimes', 'boolean'],
            'webhook_url' => ['sometimes', 'nullable', 'url', 'max:2048'],
            'webhook_secret' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function getViewportWidth(): int
    {
        return $this->input('viewport_width', config('screenshot.default_viewport_width'));
    }

    public function getViewportHeight(): int
    {
        return $this->input('viewport_height', config('screenshot.default_viewport_height'));
    }

    public function getMaxWidth(): ?int
    {
        return $this->input('max_width');
    }

    public function getThumbnailWidth(): int
    {
        return $this->input('thumbnail_width', config('screenshot.default_thumbnail_width'));
    }

    public function getThumbnailHeight(): int
    {
        return $this->input('thumbnail_height', config('screenshot.default_thumbnail_height'));
    }

    public function getForceRefresh(): bool
    {
        return $this->boolean('force_refresh', false);
    }

    public function getWebhookUrl(): ?string
    {
        return $this->input('webhook_url');
    }

    public function getWebhookSecret(): ?string
    {
        return $this->input('webhook_secret');
    }
}
