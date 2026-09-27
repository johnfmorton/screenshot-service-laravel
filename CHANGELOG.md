# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Security

- Capture and webhook URLs must now resolve to public addresses. Any API key could previously capture the cloud metadata endpoint (`169.254.169.254`), anything listening on `localhost`, or the private network, and read the result back as a publicly hosted image; webhooks could POST to the same places. `App\Services\PublicUrlGuard` resolves the host and rejects it if *any* address is loopback, private, link-local, CGNAT, reserved, multicast or NAT64, and fails closed on hosts that don't resolve (Chrome reads spellings like `0x7f.1` as loopback even though PHP can't resolve them). Only `http` and `https` are accepted
- The check runs when the request arrives, again when the capture job starts (DNS can change in between, and jobs already queued never had it), and against Chrome's redirect chain after the capture — a public page that redirects to an internal address is recorded as `failed` and its image is never stored. Webhooks connect to the exact address that was checked and no longer follow redirects
- Added `SCREENSHOT_ALLOW_PRIVATE_URLS` (default `false`) for local development against `*.ddev.site` URLs. Never enable it in production
- API keys are stored as a SHA-256 hash with a short prefix for display, instead of in plaintext. A database dump or backup no longer yields working keys, and the admin panel and `apikey:list` show `sk_xxxx…` rather than the full key. The full key is shown once, when it's created. The migration hashes existing keys in place, so clients keep working unchanged, but it is one-way: back up the database first if anyone needs to read an existing key out of it
- Deleting a user now deletes their API keys. The foreign key used to null the owner instead, leaving the deleted user's key active and still authenticating. The key's foreign key now cascades too, covering users deleted outside the admin panel
- Deleting an API key now removes its stored images (queued, in chunks of 500) instead of orphaning them in storage
- API authentication runs before route model binding. An unauthenticated request for an unknown screenshot ID used to return 404 rather than 401, revealing which IDs exist
- Chrome now runs sandboxed. It rendered every client-chosen page with `--no-sandbox`, so a renderer exploit would have run as the worker's user. Controlled by `SCREENSHOT_CHROME_SANDBOX` (default `true`); DDEV sets it to `false` because Docker's default seccomp profile blocks the sandbox. **Check the sandbox starts on the server before deploying** (see "Chrome Sandbox" in CLAUDE.md), or every capture will fail with `No usable sandbox!`
- The hourly API rate limit read the counter and wrote it back as separate steps, so concurrent requests could all pass on the same count. The decision now uses the value from an atomic increment
- Each API key may have at most `SCREENSHOT_MAX_PENDING_PER_KEY` (default `25`, `0` disables) captures queued or running at once; further requests get a 429. Keys with no hourly limit could otherwise fill the single worker's queue and delay every other client. Cached results are still returned at the cap, and captures past their deadline stop counting so a dead worker can't lock a key out
- Failure messages returned to API clients and webhooks no longer include raw exception text. Browsershot's failures carried the full node command line, server paths, every Chrome flag and a stack trace; storage errors named the bucket. Clients now get the navigation error (`net::ERR_CERT_DATE_INVALID at https://...`, `Navigation timeout of 30000 ms exceeded`), our own URL-check and HTTP-status messages, or a generic one. The raw text is kept in a new `error_detail` column, never serialized, and shown on the admin installation check
- The installation check's status endpoint returned any screenshot by ID to any logged-in admin user. Sub users now only see captures made with their own keys
- Webhooks carry `X-Webhook-Timestamp` and `X-Signature-256-Timestamped` (HMAC-SHA256 of `{timestamp}.{body}`), so receivers can reject replayed requests. `X-Signature-256` is unchanged. The body is now sent as exactly the bytes that were signed, rather than encoded a second time by the HTTP client
- Added `SCREENSHOT_S3_PUBLIC_ACL` (default `true`, preserving the current public-read uploads). Behind CloudFront with origin access control, set it to `false` and keep Block Public Access on; CLAUDE.md no longer says to turn Block Public Access off
- A rejected upload now fails the capture. The S3 disk doesn't throw, so a failed `put` used to be recorded as `completed` with image URLs that 404
- The admin installation check flags risky settings outside local development: `APP_DEBUG`, `SCREENSHOT_ALLOW_PRIVATE_URLS`, the Chrome sandbox, `SESSION_SECURE_COOKIE` on HTTPS, and a public S3 ACL behind CloudFront
- Updated `league/commonmark` to 2.10.3 (10 advisories; not reachable from this app's own code) and, within existing ranges, `puppeteer` to 24.43.1 and `js-yaml` to 4.3.2. `puppeteer`'s `extract-zip` advisory remains: fixing it needs Puppeteer 25, which requires Node 22.12+, and the affected code only runs when Puppeteer downloads its own Chrome, which this app never does
- The admin login is rate limited: five failures per email and IP locks that pair out for a minute, and the route allows 20 attempts per minute per IP to slow password spraying across accounts

## [1.1.1] - 2026-07-31

### Fixed

- Captures could be marked `failed` without ever running. `CaptureScreenshot::retryUntil()` is a deadline measured from dispatch, not a budget for the work — Laravel resolves it once, stores the timestamp in the job payload, and checks it *before* executing the job on each pickup. Time spent queued therefore counted against a window sized for a single capture, so on a single worker two slow captures ahead of a job was enough to expire it in the queue. The client saw `has been attempted too many times or run too long` for a URL that was never fetched, and the webhook fired for it
- Added `SCREENSHOT_QUEUE_WAIT_GRACE` (default `1800`), which extends that deadline to cover queue wait. Raise it if captures are queued deeper than half an hour. A wide window costs nothing: a failed or blocked capture is recorded rather than rethrown, so it never retries — only a worker dying mid-job requeues the work

## [1.1.0] - 2026-07-31

### Fixed

- The default user agent is now passed to Chrome as a `--user-agent` launch flag instead of through Puppeteer's per-page override. The override cost more than it bought: Chrome responds to it by dropping `sec-ch-ua` entirely and reporting an empty `navigator.userAgentData.brands`, so every capture presented as a browser claiming to be Chrome with no brand list — a plainer automation signal than the `HeadlessChrome` user agent 1.0.0 set out to hide. Verified end to end: captures now send `sec-ch-ua`, `sec-ch-ua-platform` and `sec-ch-ua-mobile` that agree with the user agent and with what scripts read from `navigator.userAgentData`
- Requests the page didn't initiate escaped the per-page override. An implicit `/favicon.ico` fetch intermittently went out with the real `HeadlessChrome` user agent and the real platform, on a race that made the resulting blocks look random. A launch flag applies before any request is made
- The two hardcoded `sec-ch-ua-platform` / `sec-ch-ua-mobile` request headers are gone. Chrome emits accurate ones itself once the user agent is set at launch, and a forced value could only disagree with the JS layer

### Changed

- `SCREENSHOT_DEFAULT_USER_AGENT` now defaults to unset, and the user agent is built from the installed Chrome by `App\Services\ChromeUserAgent` (cached an hour, since queue workers outlive Chrome's own upgrades). Restoring `sec-ch-ua` means those hints carry the real browser version, so a version pinned in config would contradict them on every request — deriving it keeps the two in agreement without anyone remembering to bump a string after an upgrade. Setting the variable still pins a specific string, and a per-request `user_agent` still takes precedence over both
- Added `SCREENSHOT_USER_AGENT_TEMPLATE` for the shape of the derived string, with `{version}` standing in for the installed browser's major version

## [1.0.1] - 2026-07-31

### Added

- `docs/client-integration.md` — an integration guide for projects that consume this service. Covers the new terminal `blocked` status and the polling pattern it breaks, the verified request and response shapes, webhook signature verification, and why `blocked` warrants a different retry policy and UI fallback than `failed`

### Fixed

- The API documentation endpoint (`GET /api/`) still advertised the pre-1.0.0 status set, so a client reading the service's own docs would treat `blocked` as an unknown status. It now lists `blocked` and notes that `error` accompanies it
- The README's completed-response example documented top-level `image_url` and `thumbnail_url` keys. The endpoint has never returned those — it returns a nested `images.full` / `images.thumbnail` object. This error predates 1.0.0, so any client coded against that example was already broken

## [1.0.0] - 2026-07-31

### Added

- Bot-protection detection. A Cloudflare challenge or WAF 403 used to be captured, uploaded and cached exactly like a real screenshot, so every client polling that URL got the block page until the TTL expired. These are now recorded under a new `blocked` status, never written to storage, and never served from cache
- `blocked` screenshot status, surfaced in the API response, the webhook payload, and the admin dashboard (including a new stat card and list filter)
- `SCREENSHOT_DETECT_BLOCKS` (default `true`) — master switch for block detection
- `SCREENSHOT_FAIL_ON_ERROR_RESPONSE` (default `true`) — treat a 4xx/5xx as a failed capture rather than screenshotting the error page. Set to `false` if you specifically want to capture error pages
- `SCREENSHOT_CHALLENGE_WAIT_MS` (default `15000`) — how long to let a "verifying your browser" interstitial resolve before calling the capture blocked. This doubles as a fix: challenges that clear on their own are now waited out and captured properly, where `networkidle2` would previously photograph the spinner
- `SCREENSHOT_NEW_HEADLESS` (default `true`) — run captures under modern headless Chrome instead of the legacy, easily fingerprinted headless shell
- `SCREENSHOT_CHROME_SINGLE_PROCESS` (default `false`) — see below
- `SCREENSHOT_ACCEPT_LANGUAGE` (default `en-US,en;q=0.9`)
- `SCREENSHOT_FORCE_HTTP1` (default `true`) — adds the `--disable-http2` Chromium flag so captures don't fail with `net::ERR_HTTP2_PROTOCOL_ERROR` on sites and CDNs that break headless Chrome's HTTP/2 stack. Forcing HTTP/1.1 is slightly slower but far more reliable
- `SCREENSHOT_DEFAULT_USER_AGENT` — the user agent used when a request doesn't specify its own, defaulting to a real desktop Chrome UA

### Changed

- Screenshot requests now send a real desktop Chrome user agent by default instead of the block-prone "HeadlessChrome" UA, so pages render the way a visitor would see them
- **`--single-process` is no longer part of `SCREENSHOT_CHROME_MEMORY_OPTIMIZED`.** Almost no real browser runs this way, making it a strong bot-detection signal, and it is a known cause of crashes on heavy pages. It is now opt-in via `SCREENSHOT_CHROME_SINGLE_PROCESS`. If you are memory constrained and were relying on it, set that to `true` — but try `--disable-dev-shm-usage` alone first, which is still enabled by default
- The default user agent now claims Linux (matching the server) rather than macOS. A UA contradicting the client hints and the JS-level navigator scores worse with bot detection than an honest but less common platform does. The Chrome version was also refreshed; a UA pinned to a year-old browser is itself a signal
- Failed captures no longer leave temporary PNGs behind in the system temp directory
- Removed the inert `$tries` property from `CaptureScreenshot`. It never applied, because Laravel skips the attempt-count check entirely for jobs defining `retryUntil()` — as this one does. No behaviour change; the retry window is unchanged and is still bounded by `retryUntil()`. Note the same rule means `queue:work --tries` does not affect this job

### Fixed

- `net::ERR_HTTP2_PROTOCOL_ERROR` capture failures on some sites/CDNs, by forcing HTTP/1.1 via the new `--disable-http2` flag
