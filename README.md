# URL Screenshot Service

A Laravel API that captures screenshots of URLs using headless Chrome and returns image URLs for full-size and thumbnail versions.

## Requirements

- [DDEV](https://ddev.readthedocs.io/en/stable/) for local development
- Docker

## Installation

```bash
# Clone the repository
git clone <repository-url>
cd screenshot-service

# Start DDEV
ddev start

# Install dependencies
ddev composer install

# Copy environment file and generate app key
cp .env.example .env
ddev artisan key:generate

# Run migrations
ddev artisan migrate

# Create initial admin user (set credentials in .env first)
# ADMIN_EMAIL=admin@example.com
# ADMIN_PASSWORD=your-secure-password
ddev artisan db:seed --class=AdminSeeder

# Create an API key (see CLI Commands below)
ddev artisan apikey:create "my-app"
```

The service will be available at `https://screenshot-service.ddev.site`

The admin panel is accessible at `https://screenshot-service.ddev.site/admin`

## CLI Commands

### Create an API Key

```bash
ddev artisan apikey:create {name} [--rate-limit=]
```

Creates a new API key for authenticating with the screenshot API.

**Arguments:**
- `name` (required): A name to identify this API key

**Options:**
- `--rate-limit`: Maximum requests per minute (optional)

**Example:**
```bash
# Create a basic API key
ddev artisan apikey:create "my-application"

# Create an API key with rate limiting
ddev artisan apikey:create "my-application" --rate-limit=60
```

The command outputs the API key once. Copy it immediately—it cannot be retrieved later.

### List API Keys

```bash
ddev artisan apikey:list
```

Lists all API keys with their ID, name, status, rate limit, screenshot count, and creation date.

## API Usage

All API requests (except documentation) require an `X-API-Key` header.

### Create a Screenshot

```
POST /api/screenshots
```

**Headers:**
```
X-API-Key: your-api-key
Content-Type: application/json
```

**Request Body:**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `url` | string | Yes | URL to capture (max 2048 chars) |
| `viewport_width` | integer | No | Viewport width in pixels (320-3840, default: 1280) |
| `viewport_height` | integer | No | Viewport height in pixels (240-2160, default: 800) |
| `max_width` | integer | No | Maximum width for the full-size image (100-3840) |
| `thumbnail_width` | integer | No | Thumbnail width in pixels (50-1920, default: 400) |
| `thumbnail_height` | integer | No | Thumbnail height in pixels (50-1920, default: 300) |
| `force_refresh` | boolean | No | Bypass cache and capture fresh screenshot (default: false) |
| `webhook_url` | string | No | URL to receive completion webhook |
| `webhook_secret` | string | No | Secret for webhook signature verification |

**Example:**
```bash
curl -X POST https://screenshot-service.ddev.site/api/screenshots \
  -H "X-API-Key: your-api-key" \
  -H "Content-Type: application/json" \
  -d '{"url": "https://example.com"}'
```

**Response (202 Accepted):**
```json
{
  "id": "9e5b4a3c-...",
  "status": "pending",
  "poll_url": "https://screenshot-service.ddev.site/api/screenshots/9e5b4a3c-..."
}
```

### Check Screenshot Status

```
GET /api/screenshots/{id}
```

**Response (completed):**
```json
{
  "id": "9e5b4a3c-...",
  "status": "completed",
  "url": "https://example.com",
  "image_url": "https://s3.../full.png",
  "thumbnail_url": "https://s3.../thumb.png"
}
```

**Status values:** `pending`, `processing`, `completed`, `failed`

### Delete a Screenshot

```
DELETE /api/screenshots/{id}
```

Invalidates a cached screenshot.

## Running Queue Workers

Screenshot capture runs asynchronously. Start workers with:

```bash
ddev artisan queue:work --tries=3
```

## Configuration

Key environment variables:

| Variable | Default | Description |
|----------|---------|-------------|
| `SCREENSHOT_TTL_HOURS` | 24 | Hours to cache screenshots |
| `SCREENSHOT_DEFAULT_VIEWPORT_WIDTH` | 1280 | Default viewport width |
| `SCREENSHOT_DEFAULT_VIEWPORT_HEIGHT` | 800 | Default viewport height |
| `SCREENSHOT_DEFAULT_THUMBNAIL_WIDTH` | 400 | Default thumbnail width |
| `SCREENSHOT_DEFAULT_THUMBNAIL_HEIGHT` | 300 | Default thumbnail height |
| `SCREENSHOT_CHROME_PATH` | /usr/bin/chromium | Path to Chrome executable |
| `SCREENSHOT_STORAGE_DISK` | s3 | Storage disk (s3 or public) |

## License

[MIT license](https://opensource.org/licenses/MIT)
