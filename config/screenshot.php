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
    | Chrome Path
    |--------------------------------------------------------------------------
    |
    | The path to the Chrome/Chromium executable for Browsershot.
    |
    */
    'chrome_path' => env('SCREENSHOT_CHROME_PATH', '/usr/bin/chromium'),

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
    | Storage Path
    |--------------------------------------------------------------------------
    |
    | The subfolder/prefix path within the storage disk where screenshots
    | will be stored. Do not include leading or trailing slashes.
    |
    */
    'storage_path' => trim(env('SCREENSHOT_STORAGE_PATH', 'screenshots'), '/'),
];
