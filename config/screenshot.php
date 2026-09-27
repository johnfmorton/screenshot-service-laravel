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
    | Network Idle Timeout
    |--------------------------------------------------------------------------
    |
    | How long a networkidle0/networkidle2 capture waits for the page to stop
    | making requests before giving up on idleness and capturing at the `load`
    | event instead, within the same overall timeout. Ad- and tracker-heavy
    | pages can stay busy indefinitely; this turns that from a failure into a
    | screenshot. Set to 0 to wait the full timeout for idleness, as before.
    |
    */
    'network_idle_timeout' => (int) env('SCREENSHOT_NETWORK_IDLE_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Queue Wait Grace
    |--------------------------------------------------------------------------
    |
    | How long a capture may sit in the queue before its deadline expires, on
    | top of the time allowed for the capture itself.
    |
    | CaptureScreenshot::retryUntil() is a deadline measured from dispatch, not
    | a budget for the work: Laravel resolves it once, stores it in the payload,
    | and checks it before running the job. Queued time counts against it, and
    | an expired job is failed without ever being attempted. Sized only for one
    | capture, a brief backlog on a single worker is enough to fail requests
    | that never ran — so this covers the wait.
    |
    | Raise it if captures are queued deeper than half an hour. There is no cost
    | to a wide window: a failed or blocked capture is recorded rather than
    | rethrown, so it never retries; only a worker dying requeues the work.
    |
    */
    'queue_wait_grace' => (int) env('SCREENSHOT_QUEUE_WAIT_GRACE', 1800),

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
    | Chrome Sandbox
    |--------------------------------------------------------------------------
    |
    | Chrome renders arbitrary pages chosen by API clients. The sandbox confines
    | each renderer, so a browser exploit in a hostile page is contained there
    | instead of running as the worker's user with access to .env and every
    | other site on the box.
    |
    | Google Chrome from Google's apt repository ships the setuid helper the
    | sandbox needs. Disable it only where it can't start: running as root, or
    | inside a container with Docker's default seccomp profile (DDEV sets this
    | to false for that reason). Chrome's error is "No usable sandbox!".
    |
    */
    'chrome_sandbox' => (bool) env('SCREENSHOT_CHROME_SANDBOX', true),

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
    | Hide Automation
    |--------------------------------------------------------------------------
    |
    | Launches Chrome with --disable-blink-features=AutomationControlled, so
    | navigator.webdriver reads false instead of true. Some JavaScript bot
    | checks (e.g. "checking your browser" interstitials) look at it.
    |
    | Off by default: it disguises automation rather than making the browser
    | more coherent, and a site's terms may forbid getting around its bot
    | protection. It also can't help with blocks decided before any page
    | JavaScript runs: nytimes.com returns 403 on the first request with or
    | without it, from a datacenter or a residential IP.
    |
    */
    'hide_automation' => (bool) env('SCREENSHOT_HIDE_AUTOMATION', false),

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
    |
    | Leave this unset. Chrome receives it as a --user-agent launch flag and so
    | keeps sending its own sec-ch-ua headers, which report the real browser
    | version — a version pinned here would contradict them on every request.
    | Unset, the string is built from the installed binary by ChromeUserAgent
    | and stays correct across Chrome upgrades on its own.
    |
    | Set it only to pin something specific, and then keep the version current
    | yourself. A per-request user_agent takes precedence over both.
    |
    */
    'default_user_agent' => env('SCREENSHOT_DEFAULT_USER_AGENT'),

    /*
    |--------------------------------------------------------------------------
    | User Agent Template
    |--------------------------------------------------------------------------
    |
    | Shapes the user agent built from the installed Chrome. {version} is
    | replaced with that browser's major version in the reduced form Chrome
    | itself uses (major.0.0.0).
    |
    | This deliberately claims Linux, which is what the server actually runs.
    | Claiming macOS or Windows instead leaves the UA contradicting the
    | sec-ch-ua-platform hint Chrome sends and the JS-level navigator, and that
    | inconsistency scores worse with bot detection than an honest but less
    | common platform does.
    |
    */
    'user_agent_template' => env(
        'SCREENSHOT_USER_AGENT_TEMPLATE',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/{version} Safari/537.36'
    ),

    /*
    |--------------------------------------------------------------------------
    | Default Request Headers
    |--------------------------------------------------------------------------
    |
    | Sent with every capture. Deliberately does not include any sec-ch-ua-*
    | hints: setting the user agent at launch leaves Chrome emitting accurate
    | ones of its own, matching both the real platform and what scripts read
    | from navigator.userAgentData. Overriding them here could only introduce a
    | disagreement between the header and the JS layer.
    |
    */
    'default_headers' => [
        'Accept-Language' => env('SCREENSHOT_ACCEPT_LANGUAGE', 'en-US,en;q=0.9'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Max Pending Captures Per Key
    |--------------------------------------------------------------------------
    |
    | How many captures one API key may have queued or running at once. The
    | hourly rate limit doesn't stop a key filling the queue in a burst, and
    | with one worker that delays every other client behind it. Requests over
    | the cap get a 429 (cache hits still return normally). 0 disables it.
    |
    */
    'max_pending_per_key' => (int) env('SCREENSHOT_MAX_PENDING_PER_KEY', 25),

    /*
    |--------------------------------------------------------------------------
    | Allow Private URLs
    |--------------------------------------------------------------------------
    |
    | Capture and webhook URLs must resolve to public addresses, so API clients
    | can't use this service to reach the cloud metadata endpoint or anything
    | listening on localhost or the private network. Enable only for local
    | development (e.g. capturing a *.ddev.site URL) — never in production.
    |
    */
    'allow_private_urls' => (bool) env('SCREENSHOT_ALLOW_PRIVATE_URLS', false),

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
    | S3 Public ACL
    |--------------------------------------------------------------------------
    |
    | Upload screenshots to S3 with a public-read ACL. Needed only if images
    | are served straight from the bucket (AWS_URL unset). Behind CloudFront
    | with origin access control, set this to false and keep the bucket's
    | Block Public Access on: CloudFront reads through its own bucket policy.
    | With Block Public Access on, a public ACL makes every upload fail.
    |
    */
    's3_public_acl' => (bool) env('SCREENSHOT_S3_PUBLIC_ACL', true),

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
