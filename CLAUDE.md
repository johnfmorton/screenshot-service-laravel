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
SCREENSHOT_DEFAULT_WAIT_UNTIL=networkidle2
SCREENSHOT_CHROME_PATH=/usr/bin/google-chrome
```

Screenshot settings are in `config/screenshot.php`.

## AWS S3 & CloudFront Setup

Screenshots are stored on S3 and served via CloudFront for production deployments.

### 1. Create an S3 Bucket

1. Go to AWS Console → S3 → Create bucket
2. Choose a unique bucket name (e.g., `myapp-screenshots`)
3. Select your preferred region (e.g., `us-east-1`)
4. Uncheck "Block all public access" (screenshots are served via CloudFront)
5. Create the bucket

### 2. Create an IAM User

Create an IAM user with programmatic access and attach this policy:

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "s3:PutObject",
                "s3:GetObject",
                "s3:DeleteObject",
                "s3:ListBucket"
            ],
            "Resource": [
                "arn:aws:s3:::myapp-screenshots",
                "arn:aws:s3:::myapp-screenshots/*"
            ]
        }
    ]
}
```

### 3. Create a CloudFront Distribution

1. Go to CloudFront → Create distribution
2. **Origin domain**: Select your S3 bucket
3. **Origin access**: Use "Origin access control settings (recommended)" and create a new OAC
4. **Viewer protocol policy**: Redirect HTTP to HTTPS
5. **Cache policy**: Use `CachingOptimized`
6. After creation, copy the provided S3 bucket policy to your bucket's permissions

### 4. Configure Environment Variables

Add to your `.env`:

```
FILESYSTEM_DISK=s3
SCREENSHOT_STORAGE_DISK=s3

AWS_ACCESS_KEY_ID=your-access-key-id
AWS_SECRET_ACCESS_KEY=your-secret-access-key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=myapp-screenshots
AWS_URL=https://d1234abcd.cloudfront.net
AWS_SCREENSHOT_STORAGE_PATH=screenshots
```

The `AWS_URL` setting makes screenshot URLs use your CloudFront distribution instead of direct S3 URLs.

## Queue Workers

Screenshot capture runs asynchronously. Start workers with:

```bash
ddev artisan queue:work --tries=3
```

## Production Server Setup

### Installing Chrome on Ubuntu (Forge/Production)

On Ubuntu servers, avoid using the Snap version of Chromium (`/usr/bin/chromium-browser`) as it has sandboxing restrictions that conflict with running from Supervisor/systemd services. Instead, install Google Chrome from the official repository:

```bash
# Add Google's signing key
wget -q -O - https://dl.google.com/linux/linux_signing_key.pub | sudo gpg --dearmor -o /usr/share/keyrings/google-chrome.gpg

# Add the Chrome repository
echo "deb [arch=amd64 signed-by=/usr/share/keyrings/google-chrome.gpg] http://dl.google.com/linux/chrome/deb/ stable main" | sudo tee /etc/apt/sources.list.d/google-chrome.list

# Install Chrome
sudo apt update
sudo apt install -y google-chrome-stable
```

Then set the Chrome path in your `.env`:

```
SCREENSHOT_CHROME_PATH=/usr/bin/google-chrome-stable
```

After making the change, restart your queue workers:

```bash
sudo supervisorctl restart all
```

### Common Chrome/Chromium Errors

**Snap confinement error**: If you see `/system.slice/supervisor.service is not a snap cgroup`, this means Chromium was installed via Snap. Install Google Chrome as shown above instead.

**Missing xdg-settings**: The error `xdg-settings: not found` occurs because Chromium checks for desktop utilities. This is harmless in headless mode but often accompanies the snap confinement error.
