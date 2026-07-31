# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

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
