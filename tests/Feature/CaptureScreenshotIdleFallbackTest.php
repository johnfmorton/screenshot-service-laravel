<?php

namespace Tests\Feature;

use App\Enums\ScreenshotStatus;
use App\Jobs\CaptureScreenshot;
use App\Models\ApiKey;
use App\Models\Screenshot;
use App\Services\ChallengeDetector;
use App\Services\ChromeUserAgent;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use ReflectionProperty;
use Spatie\Browsershot\Browsershot;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Ad- and tracker-heavy pages can stay busy indefinitely, so networkidle never
 * arrives and the capture used to fail after its whole budget. Now idleness
 * gets a bounded window and the page is captured at `load` instead.
 */
class CaptureScreenshotIdleFallbackTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array{waitUntil: ?string, navigationTimeout: ?int, processTimeout: int}> */
    private array $attempts = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['screenshot.network_idle_timeout' => 30, 'screenshot.detect_blocks' => false]);
    }

    public function test_the_first_attempt_caps_only_the_wait_for_idleness(): void
    {
        $this->capture(['wait_until' => 'networkidle2', 'timeout' => 120], fn () => null);

        $this->assertSame(
            [['waitUntil' => 'networkidle2', 'navigationTimeout' => 30000, 'processTimeout' => 120]],
            $this->attempts
        );
    }

    public function test_a_page_that_never_goes_idle_is_captured_at_load(): void
    {
        $this->freezeTime();

        $this->capture(['wait_until' => 'networkidle2', 'timeout' => 120], function (int $attempt) {
            if ($attempt === 1) {
                $this->travel(31)->seconds();
                throw $this->browserFailure('TimeoutError: Navigation timeout of 30000 ms exceeded');
            }
        });

        $this->assertCount(2, $this->attempts);
        $this->assertSame(
            ['waitUntil' => 'load', 'navigationTimeout' => 89000, 'processTimeout' => 89],
            $this->attempts[1],
            'The fallback gets what is left of the budget, so the total never grows.'
        );
    }

    public function test_networkidle0_falls_back_too(): void
    {
        $this->capture(['wait_until' => 'networkidle0', 'timeout' => 120], function (int $attempt) {
            if ($attempt === 1) {
                throw $this->browserFailure('TimeoutError: Navigation timeout of 30000 ms exceeded');
            }
        });

        $this->assertSame('load', $this->attempts[1]['waitUntil'] ?? null);
    }

    /**
     * A network error or a 403 would only repeat, so it isn't retried.
     */
    public function test_other_failures_are_not_retried(): void
    {
        $this->expectException(ProcessFailedException::class);

        try {
            $this->capture(['wait_until' => 'networkidle2', 'timeout' => 120], function () {
                throw $this->browserFailure('Error: net::ERR_NAME_NOT_RESOLVED at https://example.com/');
            });
        } finally {
            $this->assertCount(1, $this->attempts);
        }
    }

    public function test_there_is_no_fallback_without_enough_budget_left(): void
    {
        $this->expectException(ProcessFailedException::class);

        try {
            $this->capture(['wait_until' => 'networkidle2', 'timeout' => 35], function () {
                $this->travel(31)->seconds();
                throw $this->browserFailure('TimeoutError: Navigation timeout of 30000 ms exceeded');
            });
        } finally {
            $this->assertCount(1, $this->attempts);
        }
    }

    public function test_load_captures_are_unchanged(): void
    {
        $this->capture(['wait_until' => 'load', 'timeout' => 120], fn () => null);

        $this->assertSame(
            [['waitUntil' => 'load', 'navigationTimeout' => 120000, 'processTimeout' => 120]],
            $this->attempts
        );
    }

    public function test_a_budget_shorter_than_the_idle_window_is_left_alone(): void
    {
        $this->capture(['wait_until' => 'networkidle2', 'timeout' => 20], fn () => null);

        $this->assertSame(20000, $this->attempts[0]['navigationTimeout']);
    }

    public function test_the_fallback_can_be_disabled(): void
    {
        config(['screenshot.network_idle_timeout' => 0]);

        $this->capture(['wait_until' => 'networkidle2', 'timeout' => 120], fn () => null);

        $this->assertSame(120000, $this->attempts[0]['navigationTimeout']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  Closure(int): void  $onAttempt  throws to simulate a failed attempt
     */
    private function capture(array $attributes, Closure $onAttempt): void
    {
        $screenshot = Screenshot::create(array_merge([
            'api_key_id' => ApiKey::generate('Key')->id,
            'url' => 'https://example.com/',
            'url_hash' => hash('sha256', uniqid()),
            'viewport_width' => 1280,
            'viewport_height' => 800,
            'thumbnail_width' => 400,
            'thumbnail_height' => 300,
            'status' => ScreenshotStatus::Processing,
            'expires_at' => now()->addDay(),
        ], $attributes));

        $attempts = &$this->attempts;

        $job = new class($screenshot, $onAttempt, $attempts) extends CaptureScreenshot
        {
            public function __construct(Screenshot $screenshot, private Closure $onAttempt, private array &$attempts)
            {
                parent::__construct($screenshot);
            }

            protected function takeScreenshot(Browsershot $browsershot, string $path): void
            {
                $options = (new ReflectionProperty(Browsershot::class, 'additionalOptions'))->getValue($browsershot);

                $this->attempts[] = [
                    'waitUntil' => $options['waitUntil'] ?? null,
                    'navigationTimeout' => $options['timeout'] ?? null,
                    'processTimeout' => (new ReflectionProperty(Browsershot::class, 'timeout'))->getValue($browsershot),
                ];

                ($this->onAttempt)(count($this->attempts));
            }
        };

        $method = new ReflectionMethod(CaptureScreenshot::class, 'capture');
        $method->invoke($job, new ChallengeDetector(), app(ChromeUserAgent::class), '/tmp/unused.png');
    }

    private function browserFailure(string $stderr): ProcessFailedException
    {
        $process = Process::fromShellCommandline("printf '%s\\n' \"\$STDERR\" >&2; exit 1");
        $process->run(null, ['STDERR' => $stderr]);

        return new ProcessFailedException($process);
    }
}
