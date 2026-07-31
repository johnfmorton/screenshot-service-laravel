<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Screenshot TTL
    |--------------------------------------------------------------------------
    |
    | The number of hours to cache screenshots before they expire.
    |
    */
    'ttl_hours' => (int) env('SCREENSHOT_TTL_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Default Viewport Dimensions
    |--------------------------------------------------------------------------
    |
    | The default viewport width and height for screenshots when not specified.
    |
    */
    'default_viewport_width' => (int) env('SCREENSHOT_DEFAULT_VIEWPORT_WIDTH', 1280),
    'default_viewport_height' => (int) env('SCREENSHOT_DEFAULT_VIEWPORT_HEIGHT', 800),

    /*
    |--------------------------------------------------------------------------
    | Default Thumbnail Dimensions
    |--------------------------------------------------------------------------
    |
    | The default thumbnail width and height when not specified.
    |
    */
    'default_thumbnail_width' => (int) env('SCREENSHOT_DEFAULT_THUMBNAIL_WIDTH', 400),
    'default_thumbnail_height' => (int) env('SCREENSHOT_DEFAULT_THUMBNAIL_HEIGHT', 300),

    /*
    |--------------------------------------------------------------------------
    | Default Wait Until Strategy
    |--------------------------------------------------------------------------
    |
    | The default page load strategy for screenshots.
    | Options: networkidle0, networkidle2, load, domcontentloaded
    |
    | - networkidle0: Wait until 0 network connections for 500ms (strictest)
    | - networkidle2: Wait until ≤2 network connections for 500ms (recommended)
    | - load: Wait for the load event
    | - domcontentloaded: Wait for DOMContentLoaded event (fastest)
    |
    */
    'default_wait_until' => env('SCREENSHOT_DEFAULT_WAIT_UNTIL', 'networkidle2'),

    /*
    |--------------------------------------------------------------------------
    | Default Timeout
    |--------------------------------------------------------------------------
    |
    | The default timeout in seconds for page loading when capturing screenshots.
    | Heavy pages with lots of assets may require longer timeouts.
    |
    */
    'default_timeout' => (int) env('SCREENSHOT_DEFAULT_TIMEOUT', 120),

    /*
    |--------------------------------------------------------------------------
    | Chrome Path
    |--------------------------------------------------------------------------
    |
    | The path to the Chrome/Chromium executable for Browsershot.
    |
    */
    'chrome_path' => env('SCREENSHOT_CHROME_PATH', '/usr/bin/chromium'),

    /*
    |--------------------------------------------------------------------------
    | Chrome Memory Optimization
    |--------------------------------------------------------------------------
    |
    | Enable Chrome flags that reduce memory usage. Recommended for production
    | servers, especially VPS/containers where /dev/shm may be limited.
    |
    | Flags enabled: --disable-dev-shm-usage, --disable-gpu
    |
    */
    'chrome_memory_optimized' => env('SCREENSHOT_CHROME_MEMORY_OPTIMIZED', true),

    /*
    |--------------------------------------------------------------------------
    | Chrome Single Process Mode
    |--------------------------------------------------------------------------
    |
    | Adds the --single-process Chromium flag. This used to be bundled into the
    | memory optimization above, but it is off by default now: almost no real
    | browser runs this way, so it is a strong signal to bot-detection services,
    | and it is a known source of crashes on heavy pages.
    |
    | Only enable it if you are genuinely memory constrained and have confirmed
    | that --disable-dev-shm-usage alone isn't enough.
    |
    */
    'chrome_single_process' => (bool) env('SCREENSHOT_CHROME_SINGLE_PROCESS', false),

    /*
    |--------------------------------------------------------------------------
    | New Headless Mode
    |--------------------------------------------------------------------------
    |
    | Run captures under Chrome's modern headless mode rather than the legacy
    | chrome-headless-shell binary. The old mode is a materially different
    | browser build and is trivially fingerprinted as automation, so this
    | should stay enabled unless you hit a rendering regression.
    |
    */
    'new_headless' => (bool) env('SCREENSHOT_NEW_HEADLESS', true),

    /*
    |--------------------------------------------------------------------------
    | Force HTTP/1.1
    |--------------------------------------------------------------------------
    |
    | Adds the --disable-http2 Chromium flag. Some sites and CDNs cause
    | headless Chrome to fail with net::ERR_HTTP2_PROTOCOL_ERROR, aborting
    | the capture entirely. Forcing HTTP/1.1 is slightly slower but far more
    | reliable. Leave enabled unless you have a specific reason not to.
    |
    */
    'force_http1' => (bool) env('SCREENSHOT_FORCE_HTTP1', true),

    /*
    |--------------------------------------------------------------------------
    | Default User Agent
    |--------------------------------------------------------------------------
    |
    | Sent when a screenshot request doesn't specify its own user agent. A
    | real desktop Chrome UA renders pages the way a visitor would see them
    | and avoids the many sites that block the default "HeadlessChrome" UA.
    | Set to an empty string to let Chrome use its own default.
    |
    | This deliberately claims Linux, which is what the server actually runs.
    | Claiming macOS or Windows instead leaves the UA contradicting the client
    | hints below and the JS-level navigator, and that inconsistency scores
    | worse with bot detection than an honest but less common platform does.
    |
    | Keep the Chrome version roughly current. A UA pinned to a browser version
    | that is a year old is itself a signal worth flagging.
    |
    */
    'default_user_agent' => env(
        'SCREENSHOT_DEFAULT_USER_AGENT',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36'
    ),

    /*
    |--------------------------------------------------------------------------
    | Default Request Headers
    |--------------------------------------------------------------------------
    |
    | Sent with every capture. The client hints here must agree with the user
    | agent above — change the two together, or you reintroduce exactly the
    | mismatch the user agent comment warns about.
    |
    | Note this only fixes the HTTP header layer. Scripts reading
    | navigator.userAgentData still see the real platform, so these headers
    | make an honest UA coherent rather than making a spoofed one convincing.
    |
    */
    'default_headers' => [
        'Accept-Language' => env('SCREENSHOT_ACCEPT_LANGUAGE', 'en-US,en;q=0.9'),
        'sec-ch-ua-platform' => '"Linux"',
        'sec-ch-ua-mobile' => '?0',
    ],

    /*
    |--------------------------------------------------------------------------
    | Block Detection
    |--------------------------------------------------------------------------
    |
    | Without this, a Cloudflare challenge or a WAF 403 is captured, stored and
    | cached exactly like a real screenshot, and every client polling that URL
    | gets the block page until the TTL expires. With it on, those captures are
    | recorded as 'blocked', never written to storage, and never served from
    | cache, so the next request retries against the origin.
    |
    */
    'detect_blocks' => (bool) env('SCREENSHOT_DETECT_BLOCKS', true),

    /*
    |--------------------------------------------------------------------------
    | Fail On Error Responses
    |--------------------------------------------------------------------------
    |
    | Treat a 4xx/5xx response as a failed capture rather than screenshotting
    | whatever the server returned. Disable this if you specifically want to
    | capture error pages — with it off, a 403 block page is indistinguishable
    | from a successful capture again.
    |
    */
    'fail_on_error_response' => (bool) env('SCREENSHOT_FAIL_ON_ERROR_RESPONSE', true),

    /*
    |--------------------------------------------------------------------------
    | Blocking Status Codes
    |--------------------------------------------------------------------------
    |
    | Which error responses mean "bot protection turned us away" rather than
    | "this page is broken". These are reported as 'blocked'; every other
    | error status is an ordinary 'failed'.
    |
    | 404 and 503 are deliberately absent. A 404 is a missing page, and while
    | Cloudflare's legacy interstitial did use 503, that status overwhelmingly
    | means the origin is down or in maintenance — calling it bot protection
    | sends you chasing a WAF rule when the site is simply offline.
    |
    */
    'blocking_status_codes' => [401, 403, 406, 429, 451],

    /*
    |--------------------------------------------------------------------------
    | Challenge Wait
    |--------------------------------------------------------------------------
    |
    | How long to let an interstitial ("We're verifying your browser") resolve
    | before giving up and calling the capture blocked. Many challenges clear
    | on their own within a few seconds, and waiting is what rescues them —
    | the default networkidle2 strategy will otherwise happily photograph the
    | spinner. Time spent here counts against the capture timeout.
    |
    */
    'challenge_wait_ms' => (int) env('SCREENSHOT_CHALLENGE_WAIT_MS', 15000),

    /*
    |--------------------------------------------------------------------------
    | Challenge Fingerprints
    |--------------------------------------------------------------------------
    |
    | Markers that identify an interstitial. Selectors are matched anywhere in
    | the document; phrases are matched against the title, and against body
    | text only on pages shorter than challenge_max_body_length, so an article
    | discussing these phrases isn't mistaken for a challenge.
    |
    | Selectors here are deliberately limited to full-page interstitials. A
    | Turnstile widget embedded in an ordinary page is not a block, so its
    | script and iframe are not listed.
    |
    */
    'challenge_selectors' => [
        '#cf-challenge-running',
        '#cf-please-wait',
        '#challenge-running',
        '#challenge-form',
        '#challenge-error-title',
        '.cf-browser-verification',
        '#px-captcha',
    ],

    'challenge_phrases' => [
        'just a moment',
        'checking your browser',
        'verifying your browser',
        'security checkpoint',
        'enable javascript and cookies to continue',
        'verify you are human',
        'additional verification required',
        'attention required! | cloudflare',
        'ddos protection by cloudflare',
        'access denied',
    ],

    'challenge_max_body_length' => (int) env('SCREENSHOT_CHALLENGE_MAX_BODY_LENGTH', 2000),

    /*
    |--------------------------------------------------------------------------
    | Storage Disk
    |--------------------------------------------------------------------------
    |
    | The filesystem disk to use for storing screenshots.
    | Use 'public' for local development, 's3' for production.
    |
    */
    'storage_disk' => env('SCREENSHOT_STORAGE_DISK', 's3'),

    /*
    |--------------------------------------------------------------------------
    | AWS Storage Path (S3 only)
    |--------------------------------------------------------------------------
    |
    | The prefix path within the S3 bucket where screenshots will be stored.
    | This setting only applies when using the 's3' storage disk.
    | For the 'public' disk, screenshots are always stored in 'screenshots/'.
    |
    */
    'storage_path' => trim(env('AWS_SCREENSHOT_STORAGE_PATH', 'screenshots'), '/'),
];
