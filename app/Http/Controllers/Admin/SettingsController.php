<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        $settings = [
            'Screenshot TTL (Hours)' => config('screenshot.ttl_hours'),
            'Default Viewport Width' => config('screenshot.default_viewport_width') . 'px',
            'Default Viewport Height' => config('screenshot.default_viewport_height') . 'px',
            'Default Thumbnail Width' => config('screenshot.default_thumbnail_width') . 'px',
            'Default Thumbnail Height' => config('screenshot.default_thumbnail_height') . 'px',
            'Chrome Path' => config('screenshot.chrome_path'),
            'Storage Disk' => config('screenshot.storage_disk'),
            'Queue Connection' => config('queue.default'),
            'App Environment' => config('app.env'),
            'Debug Mode' => config('app.debug') ? 'Enabled' : 'Disabled',
        ];

        return view('admin.settings', compact('settings'));
    }
}
