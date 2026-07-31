<?php

namespace Tests\Unit;

use App\Services\ChallengeDetector;
use RuntimeException;
use Spatie\Browsershot\Exceptions\UnsuccessfulResponse;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ChallengeDetectorTest extends TestCase
{
    private ChallengeDetector $detector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->detector = new ChallengeDetector();
    }

    public function test_a_forbidden_response_is_reported_as_a_block(): void
    {
        $reason = $this->detector->blockReason(
            UnsuccessfulResponse::make('https://example.com', 403)
        );

        $this->assertNotNull($reason);
        $this->assertStringContainsString('403', $reason);
    }

    public function test_rate_limiting_is_reported_as_a_block(): void
    {
        $this->assertNotNull($this->detector->blockReason(
            UnsuccessfulResponse::make('https://example.com', 429)
        ));
    }

    public function test_a_missing_page_is_not_a_block(): void
    {
        $this->assertNull($this->detector->blockReason(
            UnsuccessfulResponse::make('https://example.com', 404)
        ));
    }

    public function test_a_server_error_is_not_a_block(): void
    {
        $this->assertNull($this->detector->blockReason(
            UnsuccessfulResponse::make('https://example.com', 500)
        ));
    }

    public function test_an_unresolved_challenge_is_reported_as_a_block(): void
    {
        $reason = $this->detector->blockReason(
            new RuntimeException('TimeoutError: Waiting failed: 15000ms exceeded')
        );

        $this->assertNotNull($reason);
        $this->assertStringContainsString('challenge', $reason);
    }

    public function test_the_legacy_puppeteer_timeout_phrasing_is_recognised(): void
    {
        $this->assertNotNull($this->detector->blockReason(
            new RuntimeException('waiting for function failed: timeout 15000ms exceeded')
        ));
    }

    /**
     * A slow page is not a blocked page. Both surface as a TimeoutError, so
     * this is the distinction most at risk of regressing.
     */
    public function test_a_navigation_timeout_is_not_a_block(): void
    {
        $this->assertNull($this->detector->blockReason(
            new RuntimeException('TimeoutError: Navigation timeout of 120000 ms exceeded')
        ));
    }

    public function test_a_network_error_is_not_a_block(): void
    {
        $this->assertNull($this->detector->blockReason(
            new RuntimeException('net::ERR_CONNECTION_REFUSED at https://example.com')
        ));
    }

    public function test_an_ordinary_page_passes_immediately(): void
    {
        $this->assertTrue($this->evaluatePredicate(
            title: 'Example Domain',
            bodyText: 'Example Domain. This domain is for use in illustrative examples.',
        ));
    }

    public function test_the_vercel_checkpoint_is_detected(): void
    {
        $this->assertFalse($this->evaluatePredicate(
            title: '',
            bodyText: "We're verifying your browser\nVercel Security Checkpoint | iad1::1783939266",
        ));
    }

    public function test_the_cloudflare_interstitial_is_detected_by_title(): void
    {
        $this->assertFalse($this->evaluatePredicate(
            title: 'Just a moment...',
            bodyText: 'Enable JavaScript and cookies to continue',
        ));
    }

    public function test_a_challenge_is_detected_by_selector_alone(): void
    {
        $this->assertFalse($this->evaluatePredicate(
            title: 'example.com',
            bodyText: 'Please wait while your request is processed.',
            selectors: ['#cf-challenge-running'],
        ));
    }

    /**
     * The body-length guard exists so real content discussing bot protection
     * isn't mistaken for bot protection.
     */
    public function test_a_long_article_mentioning_a_challenge_phrase_is_not_flagged(): void
    {
        $article = 'Cloudflare shows "checking your browser" when it challenges a visitor. '
            . str_repeat('This paragraph explains how that interstitial works in detail. ', 60);

        $this->assertGreaterThan(2000, strlen($article));

        $this->assertTrue($this->evaluatePredicate(
            title: 'How bot protection works',
            bodyText: $article,
        ));
    }

    public function test_an_embedded_turnstile_widget_is_not_treated_as_a_block(): void
    {
        $this->assertTrue($this->evaluatePredicate(
            title: 'Contact us',
            bodyText: 'Send us a message and we will get back to you.',
            selectors: ['script[src*="challenges.cloudflare.com"]', '.cf-turnstile'],
        ));
    }

    /**
     * Run the generated predicate under Node against a stubbed document.
     *
     * The predicate is evaluated as a bare expression, exactly as Puppeteer
     * evaluates a string passed to waitForFunction. That is deliberate: a
     * predicate written as `() => {...}` evaluates to a truthy function object
     * and disables detection silently, so this harness asserting a real
     * boolean is what keeps that regression visible.
     *
     * @param  array<int, string>  $selectors  Selectors that "exist" in the page.
     */
    private function evaluatePredicate(string $title, string $bodyText, array $selectors = []): bool
    {
        $node = (new ExecutableFinder())->find('node');

        if ($node === null) {
            $this->markTestSkipped('Node is required to evaluate the challenge predicate.');
        }

        $harness = sprintf(
            <<<'JS'
            const present = new Set(%s);
            global.document = {
                title: %s,
                body: { innerText: %s },
                querySelector: (selector) => (present.has(selector) ? {} : null),
            };
            const result = %s;
            process.stdout.write(JSON.stringify(result));
            JS,
            json_encode($selectors),
            json_encode($title),
            json_encode($bodyText),
            (new ChallengeDetector())->waitPredicate(),
        );

        $path = tempnam(sys_get_temp_dir(), 'predicate') . '.cjs';
        file_put_contents($path, $harness);

        try {
            $process = new Process([$node, $path]);
            $process->mustRun();

            $result = json_decode($process->getOutput(), true);

            $this->assertIsBool($result, 'The predicate must evaluate to a boolean, not a function object.');

            return $result;
        } finally {
            @unlink($path);
        }
    }
}
