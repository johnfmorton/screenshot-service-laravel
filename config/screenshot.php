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
    | Flags enabled: --disable-dev-shm-usage, --disable-gpu, --single-process
    |
    */
    'chrome_memory_optimized' => env('SCREENSHOT_CHROME_MEMORY_OPTIMIZED', true),

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
