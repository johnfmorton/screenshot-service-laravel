<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The current stable Chrome release, from Google's version history API.
 *
 * Exists because the server's Chrome silently fell five releases behind:
 * Ubuntu's unattended upgrades only cover Ubuntu's own packages, so Google's
 * repository was never updated. That outdated browser is what got captures
 * blocked by github.com and WordPress.com, and it was rendering untrusted
 * pages without months of security fixes.
 */
class ChromeReleases
{
    private const URL = 'https://versionhistory.googleapis.com/v1/chrome/platforms/linux/channels/stable/versions?pageSize=1';

    private const CACHE_KEY = 'screenshot:chrome-latest-stable';

    public function latestStableMajor(): ?int
    {
        if ($cached = Cache::get(self::CACHE_KEY)) {
            return $cached;
        }

        try {
            $version = Http::timeout(5)->get(self::URL)->throw()->json('versions.0.version');
        } catch (Throwable) {
            return null;
        }

        if (! is_string($version) || ! preg_match('/^(\d+)\./', $version, $matches)) {
            return null;
        }

        // Stable ships about every four weeks, so half a day is plenty fresh.
        // Failures aren't cached, so a blip doesn't hide the check for a day.
        Cache::put(self::CACHE_KEY, $major = (int) $matches[1], now()->addHours(12));

        return $major;
    }
}
