# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

URL Screenshot Service - A Laravel API that captures screenshots of URLs using headless Chrome and returns image URLs for full-size and thumbnail versions. Designed to be consumed by external applications.

**Tech Stack:**
- Laravel 11 with PHP 8.3
- Browsershot (spatie/browsershot) for screenshot capture
- Intervention Image for resizing/thumbnails
- S3 storage for images
- Redis/database queues for async processing
- MariaDB 10.11 database

## Development Environment

This project uses DDEV for local development.

```bash
# Start the development environment
ddev start

# Stop the environment
ddev stop

# SSH into web container
ddev ssh

# Run artisan commands
ddev artisan <command>

# Run composer commands
ddev composer <command>

# Access the site
https://screenshot-service.ddev.site
```

### Debugging & Profiling

```bash
# Enable/disable Xdebug
ddev xdebug on
ddev xdebug off

# Enable/disable XHProf profiling
ddev xhprof on
ddev xhprof off

# XHGui profiling UI available at:
# https://screenshot-service.ddev.site:8142
```

## Architecture

### API Flow

1. Client sends POST to `/api/screenshots` with URL and options
2. API validates API key via `X-API-Key` header
3. Checks cache for existing screenshot with same parameters
4. If not cached, creates Screenshot record and dispatches `CaptureScreenshot` job
5. Returns 202 with poll URL, or optionally sends webhook on completion

### Key Components

```
app/
├── Http/
│   ├── Controllers/ScreenshotController.php   # API endpoints
│   ├── Middleware/ValidateApiKey.php          # API key auth
│   └── Requests/CreateScreenshotRequest.php   # Request validation
├── Jobs/
│   ├── CaptureScreenshot.php                  # Browsershot capture job
│   └── SendWebhook.php                        # Webhook delivery job
├── Models/
│   ├── ApiKey.php                             # API key model
│   └── Screenshot.php                         # Screenshot model
├── Services/
│   └── ScreenshotService.php                  # Screenshot business logic
└── Enums/
    └── ScreenshotStatus.php                   # pending, processing, completed, failed
```

### Database Tables

- `api_keys` - API authentication keys with rate limits
- `screenshots` - Screenshot requests with status, image paths, webhook config

### Caching Strategy

Screenshots are cached based on URL hash (URL + viewport + dimensions). Cache TTL is configurable via `SCREENSHOT_TTL_HOURS`. Use `force_refresh: true` to bypass cache.

## API Endpoints

- `POST /api/screenshots` - Create screenshot request (returns 202 with poll URL)
- `GET /api/screenshots/{id}` - Check status and get image URLs
- `DELETE /api/screenshots/{id}` - Invalidate cached screenshot

## Configuration

Key environment variables in `.env`:

```
SCREENSHOT_TTL_HOURS=24
SCREENSHOT_DEFAULT_VIEWPORT_WIDTH=1280
SCREENSHOT_DEFAULT_VIEWPORT_HEIGHT=800
SCREENSHOT_CHROME_PATH=/usr/bin/google-chrome
```

Screenshot settings are in `config/screenshot.php`.

## Queue Workers

Screenshot capture runs asynchronously. Start workers with:

```bash
ddev artisan queue:work --tries=3
```
