<?php

namespace Tests\Unit;

use App\Services\PublicUrlGuard;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Capture and webhook URLs are fetched from inside our network, so anything
 * that lets one reach a private address turns the service into a proxy for
 * the metadata endpoint and whatever listens on localhost.
 */
class PublicUrlGuardTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function blockedUrls(): array
    {
        return [
            'loopback' => ['http://127.0.0.1/'],
            'loopback, short form' => ['http://127.1/'],
            'loopback, decimal' => ['http://2130706433/'],
            'loopback, octal' => ['http://0177.0.0.1/'],
            'loopback, hex (unresolvable to PHP, loopback to Chrome)' => ['http://0x7f.1/'],
            'localhost' => ['http://localhost:8080/admin'],
            'cloud metadata' => ['http://169.254.169.254/latest/meta-data/'],
            'private 10/8' => ['http://10.0.0.5/'],
            'private 172.16/12' => ['https://172.16.4.1/'],
            'private 192.168/16' => ['http://192.168.1.1/'],
            'carrier-grade NAT' => ['http://100.64.0.1/'],
            'unspecified' => ['http://0.0.0.0/'],
            'multicast' => ['http://224.0.0.1/'],
            'IPv6 loopback' => ['http://[::1]/'],
            'IPv6 unique local' => ['http://[fd00::1]/'],
            'IPv6 link local' => ['http://[fe80::1]/'],
            'IPv4-mapped IPv6 loopback' => ['http://[::ffff:127.0.0.1]/'],
            'NAT64 embedding loopback' => ['http://[64:ff9b::7f00:1]/'],
            'userinfo hiding the real host' => ['http://example.com@127.0.0.1/'],
            'backslash parser confusion' => ['http://127.0.0.1\\@example.com/'],
            'file scheme' => ['file:///etc/passwd'],
            'chrome scheme' => ['chrome://version'],
            'ftp scheme' => ['ftp://example.com/'],
        ];
    }

    #[DataProvider('blockedUrls')]
    public function test_non_public_urls_are_rejected(string $url): void
    {
        $this->assertFalse($this->guard()->isAllowed($url), "{$url} should be rejected");
    }

    public function test_a_public_ip_literal_is_allowed(): void
    {
        $this->assertTrue($this->guard()->isAllowed('https://93.184.215.14/'));
        $this->assertTrue($this->guard()->isAllowed('https://[2606:4700::1111]/'));
    }

    public function test_a_hostname_resolving_to_public_addresses_is_allowed(): void
    {
        $guard = $this->guard(['example.com' => ['93.184.215.14', '2606:2800:21f:cb07:6820:80da:af6b:8b2c']]);

        $this->assertSame(
            ['host' => 'example.com', 'addresses' => ['93.184.215.14', '2606:2800:21f:cb07:6820:80da:af6b:8b2c']],
            $guard->check('https://example.com/page')
        );
    }

    public function test_a_hostname_resolving_to_a_private_address_is_rejected(): void
    {
        $guard = $this->guard(['internal.example.com' => ['10.0.0.8']]);

        $this->assertFalse($guard->isAllowed('https://internal.example.com/'));
    }

    /**
     * An attacker controls every record for their own domain. Accepting the
     * host because *one* answer is public would let Chrome pick the other.
     */
    public function test_one_private_record_among_public_ones_is_enough_to_reject(): void
    {
        $guard = $this->guard(['mixed.example.com' => ['93.184.215.14', '127.0.0.1']]);

        $this->assertFalse($guard->isAllowed('https://mixed.example.com/'));
    }

    public function test_an_unresolvable_host_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('could not be resolved');

        $this->guard()->check('https://nothing-here.invalid/');
    }

    public function test_private_urls_can_be_allowed_for_local_development(): void
    {
        config(['screenshot.allow_private_urls' => true]);

        $this->assertTrue($this->guard()->isAllowed('https://screenshot-service.ddev.site/'));
        $this->assertTrue($this->guard()->isAllowed('http://127.0.0.1/'));
    }

    public function test_the_scheme_is_enforced_even_when_private_urls_are_allowed(): void
    {
        config(['screenshot.allow_private_urls' => true]);

        $this->assertFalse($this->guard()->isAllowed('file:///etc/passwd'));
    }

    /**
     * Fake DNS for named hosts; everything else goes to the real resolver,
     * which handles localhost and the numeric spellings without the network.
     *
     * @param  array<string, list<string>>  $records
     */
    private function guard(array $records = []): PublicUrlGuard
    {
        return new PublicUrlGuard(function (string $host) use ($records): array {
            if (array_key_exists($host, $records)) {
                return $records[$host];
            }

            if (str_ends_with($host, '.invalid')) {
                return [];
            }

            return gethostbynamel($host) ?: [];
        });
    }
}
