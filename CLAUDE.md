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

- `api_keys` - API authentication keys with rate limits. Only a SHA-256 hash
  (`key_hash`) and a display prefix are stored; the full key exists once, on the
  instance `ApiKey::generate()` returns (`plainTextKey`). Look keys up with
  `ApiKey::findByPlainTextKey()`. Delete keys through
  `ScreenshotService::deleteApiKey()`, which also clears their stored images.
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
SCREENSHOT_DEFAULT_TIMEOUT=120
SCREENSHOT_CHROME_PATH=/usr/bin/google-chrome
SCREENSHOT_CHROME_MEMORY_OPTIMIZED=true
SCREENSHOT_DETECT_BLOCKS=true
SCREENSHOT_CHALLENGE_WAIT_MS=15000
```

Screenshot settings are in `config/screenshot.php`.

## AWS S3 & CloudFront Setup

Screenshots are stored on S3 and served via CloudFront for production deployments.

### 1. Create an S3 Bucket

1. Go to AWS Console → S3 → Create bucket
2. Choose a unique bucket name (e.g., `myapp-screenshots`)
3. Select your preferred region (e.g., `us-east-1`)
4. Leave "Block all public access" **on**. CloudFront reads the bucket through
   origin access control (step 3), so nothing needs to be public
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
SCREENSHOT_S3_PUBLIC_ACL=false
```

The `AWS_URL` setting makes screenshot URLs use your CloudFront distribution instead of direct S3 URLs.

`SCREENSHOT_S3_PUBLIC_ACL` defaults to `true` for older deployments that serve
images straight from S3. Set it to `false` with CloudFront: a bucket with Block
Public Access on rejects public ACLs, so leaving it `true` fails every upload.

## Queue Workers

Screenshot capture runs asynchronously. Start workers with:

```bash
ddev artisan queue:work --tries=3
```

`--tries` is only a fallback for jobs that don't set their own limit, and both
of this project's jobs do. `SendWebhook` sets `$tries` directly; `CaptureScreenshot`
defines `retryUntil()`, which makes Laravel skip the attempt-count check
altogether. Change retry behaviour on the job, not on the worker command.

`retryUntil()` is a **deadline measured from dispatch**, not a duration budget
for the work. Laravel resolves it once into the job payload and checks it before
running the job, so queued time counts against it and an expired job is failed
without ever being attempted. That is what `screenshot.queue_wait_grace` exists
to absorb — size the window for backlog, not for one capture.

Each API key may have at most `SCREENSHOT_MAX_PENDING_PER_KEY` (default 25)
captures queued or running; more get a 429. With one worker, a single key
submitting in bulk would otherwise delay every other client for hours, whatever
its hourly rate limit. Rows past the capture deadline (`CaptureScreenshot::deadlineSeconds()`)
don't count, so a worker that dies mid-job can't lock a key out.

Three queue settings have to stay ordered: longest real capture (~360s, since
the API caps `timeout` at 300 and Browsershot applies it to the Node process)
< the worker's `--timeout` < `retry_after` on the queue connection. A worker
timeout above `retry_after` lets a hung job be re-dispatched while it is still
running; with a single worker it also stalls the whole queue for the duration.

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

### Chrome Memory Optimization

By default, memory optimization flags are enabled (`SCREENSHOT_CHROME_MEMORY_OPTIMIZED=true`). This adds the following Chrome flags:

- `--disable-dev-shm-usage`: Uses `/tmp` instead of `/dev/shm` for shared memory. Critical on VPS/containers where `/dev/shm` is often limited to 64MB.
- `--disable-gpu`: Disables GPU hardware acceleration.

These flags help prevent Chrome from crashing or hanging on memory-intensive pages (WebGL, Three.js, heavy SPAs). If you have a server with ample resources and need maximum rendering fidelity, you can disable this:

```
SCREENSHOT_CHROME_MEMORY_OPTIMIZED=false
```

### Chrome Sandbox

Chrome runs sandboxed by default (`SCREENSHOT_CHROME_SANDBOX=true`). It renders
pages API clients choose, and without the sandbox a renderer exploit in one of
them runs as the worker's user, with that user's access to `.env` files and the
other sites on the box. Google Chrome from Google's apt repository ships the
setuid helper the sandbox needs (`/opt/google/chrome/chrome-sandbox`, owned by
root, mode `4755`).

Check it works as the worker's user before deploying:

```bash
sudo -u forge /usr/bin/google-chrome --headless=new --disable-gpu --disable-dev-shm-usage \
  --dump-dom 'data:text/html,<p>sandbox-ok</p>'
```

It should print the page with `sandbox-ok`. `No usable sandbox!` means the
helper is missing or lost its setuid bit, or Chrome is running as root. Fix
that rather than turning the sandbox off, and set `SCREENSHOT_CHROME_SANDBOX=false`
only as a stopgap. DDEV sets it to false because Docker's default seccomp
profile blocks the namespaces the sandbox needs.

`--single-process` used to be part of this bundle and is now opt-in via
`SCREENSHOT_CHROME_SINGLE_PROCESS=true`. Almost no real browser runs that way,
so it is a strong bot-detection signal, and it crashes on heavy pages. Reach for
it only when `--disable-dev-shm-usage` alone isn't enough.

## SSRF Protection

Capture and webhook URLs come from API clients but are fetched from inside our
network, so every one goes through `app/Services/PublicUrlGuard.php`: `http`/`https`
only, and every address the host resolves to must be public. It runs at
validation (`App\Rules\PublicUrl`), again when `CaptureScreenshot` starts, and
over Chrome's redirect chain before anything is uploaded. `SendWebhook` pins the
connection to the checked address via `CURLOPT_RESOLVE` and refuses redirects.

That is an application-level check, and it cannot see what Chrome does after
navigation: subresources and iframes (`<iframe src="http://127.0.0.1:8080">`
renders straight into the screenshot), or a DNS answer that changes between the
check and Chrome's own lookup. **The real boundary is an egress firewall on the
worker.** Run the queue worker as a dedicated user and drop its traffic to
non-public ranges, e.g.:

```bash
for net in 127.0.0.0/8 10.0.0.0/8 172.16.0.0/12 192.168.0.0/16 169.254.0.0/16 100.64.0.0/10 0.0.0.0/8; do
  sudo iptables -A OUTPUT -m owner --uid-owner screenshot -d "$net" -j REJECT
done
sudo ip6tables -A OUTPUT -m owner --uid-owner screenshot -d ::1/128 -j REJECT
sudo ip6tables -A OUTPUT -m owner --uid-owner screenshot -d fc00::/7 -j REJECT
sudo ip6tables -A OUTPUT -m owner --uid-owner screenshot -d fe80::/10 -j REJECT
```

On Forge every site runs as `forge` unless site isolation is on, so an owner
match on `forge` would cut off every site on the box from its database and
Redis. The worker needs its own user first. The worker's own database and Redis
connections go to localhost as well, so that user needs `ACCEPT` rules for
those ports ahead of the `REJECT`s.

`SCREENSHOT_ALLOW_PRIVATE_URLS=true` disables the address check (the scheme is
still enforced) for local development against `*.ddev.site` URLs.

## Bot Protection

`CaptureScreenshot` recognises WAF blocks and challenge interstitials and records
them as `ScreenshotStatus::Blocked` instead of storing the block page as a real
screenshot. Blocked captures are never uploaded and never satisfy a cache lookup.

Detection lives in `app/Services/ChallengeDetector.php` and works at two layers:

- **HTTP status** — Browsershot's `preventUnsuccessfulResponse()` surfaces 4xx/5xx
  as `UnsuccessfulResponse`. Codes in `screenshot.blocking_status_codes` become
  `blocked`; everything else (404, 500, 503) stays an ordinary `failed`.
- **Page content** — interstitials that return a 200, matched by the JS predicate
  from `ChallengeDetector::waitPredicate()`. It runs via `waitForFunction`, so a
  challenge that clears within `SCREENSHOT_CHALLENGE_WAIT_MS` is waited out and
  captured properly; one that doesn't times out and is reported as blocked.

The predicate **must** be an immediately-invoked expression. Puppeteer evaluates
a string predicate as an expression rather than calling it, so a bare
`() => {...}` evaluates to a truthy function object and silently disables
detection. `tests/Unit/ChallengeDetectorTest.php` runs the predicate through Node
as a bare expression specifically to keep that regression visible.

The user agent **must** reach Chrome as a `--user-agent` launch flag, never via
Browsershot's `userAgent()` (Puppeteer's `page.setUserAgent()`). The per-page
override makes Chrome stop sending `sec-ch-ua` and report an empty
`navigator.userAgentData.brands`, which identifies the capture as automated more
plainly than the `HeadlessChrome` UA it replaces, and it doesn't cover
browser-initiated requests — an implicit favicon fetch intermittently goes out
with the real headless UA. `tests/Feature/CaptureScreenshotUserAgentTest.php`
asserts the flag is set and the `userAgent` option is not.

Because the flag leaves Chrome's real `sec-ch-ua` in place, the UA version has to
match the installed browser. `app/Services/ChromeUserAgent.php` reads it from
`chrome_path --version` (cached an hour, since workers outlive Chrome upgrades)
and fills `screenshot.user_agent_template`. Setting `SCREENSHOT_DEFAULT_USER_AGENT`
overrides all of that and re-pins the version, so leave it unset unless you have
a reason.

`SCREENSHOT_HIDE_AUTOMATION=true` (off by default) launches Chrome with
`--disable-blink-features=AutomationControlled`, so `navigator.webdriver` reads
false. It only affects JavaScript checks: blocks decided on the first request
(nytimes.com's 403, measured from both a DigitalOcean and a residential IP,
over HTTP/1.1 and HTTP/2) are unchanged by it.

A `networkidle0`/`networkidle2` capture waits at most
`SCREENSHOT_NETWORK_IDLE_TIMEOUT` (default 30s) for the page to go quiet, then
re-navigates and captures at `load` with the remaining budget. Only Puppeteer's
navigation timeout triggers that; see `CaptureScreenshot::capture()`.

**Timeout on heavy pages**: If screenshots timeout even with memory optimization, try using `wait_until: "load"` instead of `networkidle2` in your API requests. WebGL sites often maintain continuous network activity and never reach "network idle".
