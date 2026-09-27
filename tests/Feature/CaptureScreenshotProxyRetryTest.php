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
use Spatie\Browsershot\Exceptions\UnsuccessfulResponse;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use Throwable;

/**
 * Most blocks are about the server's datacenter IP, so a blocked capture is
 * retried once through a proxy on a residential line.
 */
class CaptureScreenshotProxyRetryTest extends TestCase
{
    use RefreshDatabase;

    private const PROXY = 'http://100.111.58.60:3128';

    /** @var list<array{proxy: ?string, processTimeout: int}> */
    private array $attempts = [];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'screenshot.detect_blocks' => true,
            'screenshot.blocked_retry_proxy' => self::PROXY,
        ]);
    }

    public function test_a_blocked_capture_is_retried_through_the_proxy(): void
    {
        $this->freezeTime();

        $this->capture(function (int $attempt) {
            if ($attempt === 1) {
                $this->travel(3)->seconds();
                throw UnsuccessfulResponse::make('https://github.com/', '403');
            }
        });

        $this->assertSame([
            ['proxy' => null, 'processTimeout' => 120],
            ['proxy' => self::PROXY, 'processTimeout' => 117],
        ], $this->attempts, 'Direct first; the retry gets what is left of the budget.');
    }

    public function test_captures_go_direct_unless_blocked(): void
    {
        $this->capture(fn () => null);

        $this->assertSame([['proxy' => null, 'processTimeout' => 120]], $this->attempts);
    }

    public function test_nothing_is_retried_without_a_proxy(): void
    {
        config(['screenshot.blocked_retry_proxy' => null]);

        $this->assertThrows(fn () => $this->capture(function () {
            throw UnsuccessfulResponse::make('https://github.com/', '403');
        }), UnsuccessfulResponse::class);

        $this->assertCount(1, $this->attempts);
    }

    /**
     * A 404 or a network error isn't about the IP; the proxy wouldn't help.
     */
    public function test_ordinary_failures_are_not_retried(): void
    {
        $this->assertThrows(fn () => $this->capture(function () {
            throw UnsuccessfulResponse::make('https://example.com/', '404');
        }), UnsuccessfulResponse::class);

        $this->assertThrows(fn () => $this->capture(function () {
            throw $this->browserFailure('Error: net::ERR_NAME_NOT_RESOLVED at https://example.com/');
        }), ProcessFailedException::class);

        $this->assertCount(2, $this->attempts);
        $this->assertSame([null, null], array_column($this->attempts, 'proxy'));
    }

    /**
     * The NAS being asleep says nothing about the site. The block does.
     */
    public function test_an_unreachable_proxy_keeps_the_original_block(): void
    {
        $thrown = $this->captureExpectingFailure(function (int $attempt) {
            throw $attempt === 1
                ? UnsuccessfulResponse::make('https://github.com/', '403')
                : $this->browserFailure('Error: net::ERR_PROXY_CONNECTION_FAILED at https://github.com/');
        });

        $this->assertInstanceOf(UnsuccessfulResponse::class, $thrown);
        $this->assertStringContainsString('403', $thrown->getMessage());
        $this->assertCount(2, $this->attempts);
    }

    public function test_a_block_through_the_proxy_too_is_reported(): void
    {
        $thrown = $this->captureExpectingFailure(function (int $attempt) {
            throw UnsuccessfulResponse::make('https://www.nytimes.com/', $attempt === 1 ? '403' : '429');
        });

        $this->assertStringContainsString('429', $thrown->getMessage());
    }

    public function test_no_retry_without_enough_budget_left(): void
    {
        $this->freezeTime();

        $this->assertThrows(fn () => $this->capture(function () {
            $this->travel(115)->seconds();
            throw UnsuccessfulResponse::make('https://github.com/', '403');
        }), UnsuccessfulResponse::class);

        $this->assertCount(1, $this->attempts);
    }

    private function captureExpectingFailure(Closure $onAttempt): Throwable
    {
        try {
            $this->capture($onAttempt);
        } catch (Throwable $e) {
            return $e;
        }

        $this->fail('The capture was expected to fail.');
    }

    /**
     * @param  Closure(int): void  $onAttempt  throws to simulate a failed attempt
     */
    private function capture(Closure $onAttempt): void
    {
        $screenshot = Screenshot::create([
            'api_key_id' => ApiKey::generate('Key')->id,
            'url' => 'https://github.com/',
            'url_hash' => hash('sha256', uniqid()),
            'viewport_width' => 1280,
            'viewport_height' => 800,
            'thumbnail_width' => 400,
            'thumbnail_height' => 300,
            'wait_until' => 'load',
            'timeout' => 120,
            'status' => ScreenshotStatus::Processing,
            'expires_at' => now()->addDay(),
        ]);

        $attempts = &$this->attempts;

        $job = new class($screenshot, $onAttempt, $attempts) extends CaptureScreenshot
        {
            public function __construct(Screenshot $screenshot, private Closure $onAttempt, private array &$attempts)
            {
                parent::__construct($screenshot);
            }

            protected function takeScreenshot(Browsershot $browsershot, string $path): void
            {
                $this->attempts[] = [
                    'proxy' => (new ReflectionProperty(Browsershot::class, 'proxyServer'))->getValue($browsershot) ?: null,
                    'processTimeout' => (new ReflectionProperty(Browsershot::class, 'timeout'))->getValue($browsershot),
                ];

                ($this->onAttempt)(count($this->attempts));
            }
        };

        (new ReflectionMethod(CaptureScreenshot::class, 'captureAvoidingIpBlocks'))
            ->invoke($job, new ChallengeDetector(), app(ChromeUserAgent::class), '/tmp/unused.png');
    }

    private function browserFailure(string $stderr): ProcessFailedException
    {
        $process = Process::fromShellCommandline("printf '%s\\n' \"\$STDERR\" >&2; exit 1");
        $process->run(null, ['STDERR' => $stderr]);

        return new ProcessFailedException($process);
    }
}
