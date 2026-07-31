<?php

namespace App\Services;

use Spatie\Browsershot\Exceptions\UnsuccessfulResponse;
use Throwable;

/**
 * Recognises bot-protection interstitials and WAF blocks.
 *
 * Two things get detected, at two different layers:
 *
 * 1. HTTP-level blocks (Cloudflare's 403 managed challenge, 429 rate limits,
 *    Akamai's 406, and so on). Browsershot surfaces these as
 *    UnsuccessfulResponse once preventUnsuccessfulResponse() is enabled.
 *
 * 2. Interstitials served with a 200, which no status check can catch —
 *    "We're verifying your browser" (Vercel), "Just a moment..." (Cloudflare).
 *    These are caught in the page itself by waitPredicate(), which doubles as
 *    the fix: many challenges clear on their own within a few seconds, and the
 *    predicate keeps polling until they do.
 */
class ChallengeDetector
{
    /**
     * A JS predicate that is true once the page is showing real content.
     *
     * Passed to Puppeteer's waitForFunction, so returning false means "keep
     * waiting" — a challenge that resolves lets the capture proceed, and one
     * that never does times out and is reported as a block. On an ordinary
     * page this is true on the first evaluation and costs nothing.
     *
     * This must stay an immediately-invoked expression. Puppeteer evaluates a
     * string predicate as an expression rather than calling it, so a bare
     * `() => {...}` would evaluate to a function object — always truthy, which
     * silently disables detection entirely instead of failing loudly.
     */
    public function waitPredicate(): string
    {
        $selectors = json_encode(array_values(config('screenshot.challenge_selectors', [])));
        $phrases = json_encode(array_values(array_map(
            'mb_strtolower',
            config('screenshot.challenge_phrases', [])
        )));
        $maxBodyLength = (int) config('screenshot.challenge_max_body_length', 2000);

        return <<<JS
        (() => {
            for (const selector of {$selectors}) {
                try {
                    if (document.querySelector(selector)) return false;
                } catch (e) {
                    // Ignore selectors this browser can't parse.
                }
            }

            const phrases = {$phrases};
            const title = (document.title || '').toLowerCase();

            for (const phrase of phrases) {
                if (title.includes(phrase)) return false;
            }

            // Only match body text on short pages. Challenge pages are nearly
            // empty, so this keeps an article that happens to discuss
            // "checking your browser" from being flagged as one.
            const body = document.body ? (document.body.innerText || '') : '';

            if (body.length <= {$maxBodyLength}) {
                const text = body.toLowerCase();

                for (const phrase of phrases) {
                    if (text.includes(phrase)) return false;
                }
            }

            return true;
        })()
        JS;
    }

    /**
     * Describe why a capture failure counts as a block, or null if it doesn't.
     *
     * Anything unrecognised returns null and stays an ordinary failure, so a
     * misclassification costs a status label rather than a cached bad capture.
     */
    public function blockReason(Throwable $e): ?string
    {
        if ($e instanceof UnsuccessfulResponse) {
            return $this->blockReasonForResponse($e);
        }

        return $this->blockReasonForChallenge($e);
    }

    private function blockReasonForResponse(UnsuccessfulResponse $e): ?string
    {
        if (! preg_match('/responds with code (\d+)\s*$/', $e->getMessage(), $matches)) {
            return null;
        }

        $status = (int) $matches[1];
        $blockingCodes = config('screenshot.blocking_status_codes', []);

        if (! in_array($status, $blockingCodes, true)) {
            return null;
        }

        return "Blocked by bot protection: the site responded with HTTP {$status}.";
    }

    private function blockReasonForChallenge(Throwable $e): ?string
    {
        $message = $e->getMessage();

        // Puppeteer's phrasing for a waitForFunction timeout, current and
        // legacy. Matched narrowly on purpose: a navigation timeout is a slow
        // page, not a block, and reads "Navigation timeout of Nms exceeded".
        $timedOut = str_contains($message, 'Waiting failed:')
            || str_contains($message, 'waiting for function failed');

        if (! $timedOut) {
            return null;
        }

        $seconds = round(config('screenshot.challenge_wait_ms', 15000) / 1000, 1);

        return "Blocked by bot protection: an interstitial challenge page did not clear within {$seconds}s.";
    }
}
