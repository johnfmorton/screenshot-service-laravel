# URL Screenshot Service API Documentation

## Overview

This service captures screenshots of URLs using headless Chrome and returns image URLs for both full-size and thumbnail versions. Screenshots are processed asynchronously and cached for 24 hours by default.

## Base URL

Replace with the actual deployment URL (e.g., `https://screenshots.example.com`).

## Authentication

All requests require an API key passed via the `X-API-Key` header.

```
X-API-Key: your-api-key-here
```

---

## Endpoints

### 1. Create Screenshot

**POST** `/api/screenshots`

Creates a new screenshot request. Processing happens asynchronously.

#### Request Body (JSON)

| Field | Type | Required | Constraints | Default | Description |
|-------|------|----------|-------------|---------|-------------|
| `url` | string | Yes | Valid URL, max 2048 chars | - | The URL to screenshot |
| `viewport_width` | integer | No | 320-3840 | 1280 | Browser viewport width |
| `viewport_height` | integer | No | 240-2160 | 800 | Browser viewport height |
| `max_width` | integer | No | 100-3840 | null | Max width to resize full image |
| `thumbnail_width` | integer | No | 50-1920 | 400 | Thumbnail width |
| `thumbnail_height` | integer | No | 50-1920 | 300 | Thumbnail height |
| `wait_until` | string | No | networkidle0, networkidle2, load, domcontentloaded | networkidle2 | Page load strategy |
| `timeout` | integer | No | 10-300 | 120 | Page load timeout in seconds |
| `user_agent` | string | No | Max 512 chars | null | Custom browser user agent |
| `force_refresh` | boolean | No | - | false | Bypass cache, capture fresh |
| `webhook_url` | string | No | Valid URL, max 2048 chars | null | URL for completion callback |
| `webhook_secret` | string | No | Max 255 chars | null | HMAC secret for webhook signature |

#### Example Request

```json
{
  "url": "https://example.com",
  "viewport_width": 1920,
  "viewport_height": 1080,
  "thumbnail_width": 300,
  "thumbnail_height": 200,
  "webhook_url": "https://myapp.com/webhook/screenshot"
}
```

#### Example Request (Heavy Page)

```json
{
  "url": "https://threejs-journey.com",
  "wait_until": "load",
  "timeout": 180,
  "webhook_url": "https://myapp.com/webhook/screenshot"
}
```

#### Responses

**200 OK** - Screenshot already cached and immediately available:
```json
{
  "id": "01hxyz123...",
  "status": "completed",
  "url": "https://example.com",
  "images": {
    "full": "https://cdn.example.com/screenshots/abc123-full.png",
    "thumbnail": "https://cdn.example.com/screenshots/abc123-thumb.png"
  },
  "captured_at": "2024-01-15T10:30:00+00:00",
  "expires_at": "2024-01-16T10:30:00+00:00"
}
```

**202 Accepted** - Screenshot is being processed:
```json
{
  "id": "01hxyz456...",
  "status": "pending",
  "poll_url": "https://api.example.com/api/screenshots/01hxyz456..."
}
```

---

### 2. Check Screenshot Status

**GET** `/api/screenshots/{id}`

Retrieves the current status and image URLs for a screenshot.

#### Responses

**Pending/Processing:**
```json
{
  "id": "01hxyz456...",
  "status": "processing",
  "url": "https://example.com"
}
```

**Completed:**
```json
{
  "id": "01hxyz456...",
  "status": "completed",
  "url": "https://example.com",
  "images": {
    "full": "https://cdn.example.com/screenshots/abc123-full.png",
    "thumbnail": "https://cdn.example.com/screenshots/abc123-thumb.png"
  },
  "captured_at": "2024-01-15T10:30:00+00:00",
  "expires_at": "2024-01-16T10:30:00+00:00"
}
```

**Failed:**
```json
{
  "id": "01hxyz456...",
  "status": "failed",
  "url": "https://example.com",
  "error": "Page load timeout after 30 seconds"
}
```

---

### 3. Delete Screenshot

**DELETE** `/api/screenshots/{id}`

Invalidates a cached screenshot.

**Response:** `204 No Content`

---

## Status Values

| Status | Description |
|--------|-------------|
| `pending` | Request received, waiting in queue |
| `processing` | Screenshot capture in progress |
| `completed` | Screenshot ready, images available |
| `failed` | Capture failed, check `error` field |

---

## Webhooks

If you provide a `webhook_url`, the service will POST to it when processing completes (success or failure).

### Webhook Payload

Same structure as the GET response:

```json
{
  "id": "01hxyz456...",
  "status": "completed",
  "url": "https://example.com",
  "images": {
    "full": "https://cdn.example.com/screenshots/abc123-full.png",
    "thumbnail": "https://cdn.example.com/screenshots/abc123-thumb.png"
  },
  "captured_at": "2024-01-15T10:30:00+00:00",
  "expires_at": "2024-01-16T10:30:00+00:00"
}
```

### Signature Verification

If you provide a `webhook_secret`, the service sends an `X-Signature-256` header containing an HMAC-SHA256 signature of the JSON payload.

Verify like this (pseudocode):
```
expected = hmac_sha256(webhook_secret, json_body)
return constant_time_compare(expected, request.headers["X-Signature-256"])
```

### Retry Policy

Webhooks retry up to 3 times with backoff: 5s, 30s, 120s.

---

## Recommended Integration Pattern

### Option A: Polling

1. POST to `/api/screenshots` with your URL
2. If response is `202`, poll the `poll_url` every 2-5 seconds
3. Stop polling when `status` is `completed` or `failed`

### Option B: Webhook (Preferred)

1. POST to `/api/screenshots` with `webhook_url`
2. Return immediately to your user
3. Process the webhook callback when it arrives

---

## Error Responses

**401 Unauthorized:**
```json
{
  "error": "Unauthorized",
  "message": "Invalid or missing API key"
}
```

**404 Not Found:**
```json
{
  "error": "Not found",
  "message": "Screenshot not found"
}
```

**422 Validation Error:**
```json
{
  "message": "The url field is required.",
  "errors": {
    "url": ["The url field is required."]
  }
}
```

**429 Rate Limited:**
```json
{
  "error": "Too Many Requests",
  "message": "Rate limit exceeded"
}
```

---

## Caching Behavior

- Screenshots are cached based on: URL + viewport dimensions
- Default cache TTL: 24 hours
- Use `force_refresh: true` to bypass cache and capture a fresh screenshot
- The `expires_at` field indicates when the cached screenshot will be purged

---

## Page Load Strategies (`wait_until`)

| Strategy | Description | Use Case |
|----------|-------------|----------|
| `networkidle0` | Wait until 0 network connections for 500ms | Static pages with finite resources |
| `networkidle2` | Wait until ≤2 network connections for 500ms | Most websites (default) |
| `load` | Wait for the `load` event | Heavy pages, SPAs, WebGL sites |
| `domcontentloaded` | Wait for `DOMContentLoaded` event | Fast captures, above-fold content only |

**Tip:** For heavy pages (WebGL, Three.js, complex SPAs) that timeout with `networkidle2`, use `wait_until: "load"` with an increased `timeout`.
