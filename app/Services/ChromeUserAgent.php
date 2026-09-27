<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Resolves the user agent sent when a capture request doesn't specify its own.
 *
 * The value is handed to Chrome as a --user-agent launch flag rather than
 * overridden per page, and that choice is what makes the version here matter.
 * Overriding a page's user agent makes Chrome drop its sec-ch-ua headers
 * entirely and report an empty navigator.userAgentData.brands — a browser
 * claiming to be Chrome with no brand list is a cleaner automation tell than
 * the HeadlessChrome user agent it was meant to hide. Setting the flag instead
 * keeps those hints, but they carry the *real* browser version, so a version
 * pinned in config would contradict them on every request.
 *
 * Reading the version off the installed binary keeps the two agreeing without
 * anyone having to remember to bump a string after a Chrome upgrade.
 */
class ChromeUserAgent
{
    /**
     * Used only when the installed Chrome can't be interrogated, which means
     * the capture is about to fail on a missing browser anyway. It exists so
     * that path never falls back to advertising HeadlessChrome.
     */
    private const FALLBACK_MAJOR_VERSION = '138';

    private const CACHE_KEY = 'screenshot:chrome-user-agent';

    /**
     * Cached because queue workers are long-lived and Chrome updates itself
     * underneath them. An hour bounds how long the string can disagree with
     * the binary after an unattended upgrade, at one cheap subprocess per hour.
     */
    private const CACHE_TTL_MINUTES = 60;

    public function resolve(): string
    {
        $configured = config('screenshot.default_user_agent');

        if (is_string($configured) && trim($configured) !== '') {
            return $configured;
        }

        return Cache::remember(
            self::CACHE_KEY,
            now()->addMinutes(self::CACHE_TTL_MINUTES),
            fn (): string => $this->buildFromInstalledChrome()
        );
    }

    public function buildFromInstalledChrome(): string
    {
        $version = ($this->installedMajorVersion() ?? self::FALLBACK_MAJOR_VERSION) . '.0.0.0';

        return str_replace('{version}', $version, (string) config('screenshot.user_agent_template'));
    }

    /**
     * The major version is all Chrome itself reveals in a modern user agent —
     * the minor, build and patch positions have been frozen at 0 since the
     * user-agent reduction, so matching that shape means only the major has to
     * be accurate.
     */
    public function installedMajorVersion(): ?string
    {
        $path = config('screenshot.chrome_path');

        if (! is_string($path) || $path === '' || ! is_executable($path)) {
            return null;
        }

        try {
            $process = new Process([$path, '--version']);
            $process->setTimeout(10);
            $process->run();
        } catch (Throwable) {
            return null;
        }

        if (! $process->isSuccessful()) {
            return null;
        }

        // "Google Chrome 149.0.7827.196", "Chromium 143.0.7295.0"
        return preg_match('/\b(\d+)\.\d+\.\d+\.\d+\b/', $process->getOutput(), $matches)
            ? $matches[1]
            : null;
    }
}
