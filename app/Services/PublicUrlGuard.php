<?php

namespace App\Services;

use App\Exceptions\UrlNotAllowed;
use Closure;

/**
 * Decides whether a URL points somewhere this service is allowed to fetch.
 *
 * Capture and webhook URLs come from API clients, and both are fetched from
 * inside our network. Without this check, a client can ask for the cloud
 * metadata endpoint (169.254.169.254) or any service listening on localhost —
 * on a shared server that includes other sites' internals — and read the
 * result back as a publicly hosted image.
 *
 * The check resolves the host and requires *every* address to be public. It
 * fails closed: a host that doesn't resolve is rejected rather than waved
 * through, because some spellings PHP's resolver doesn't understand (hex
 * octets like 0x7f.1) are still read by Chrome as loopback addresses.
 *
 * This is an application-level check only. It can't see redirects or
 * subresources that Chrome follows, or DNS answers that change between the
 * check and the fetch. An egress firewall on the worker is what actually
 * closes those; see "SSRF protection" in CLAUDE.md.
 */
class PublicUrlGuard
{
    /**
     * @param  (Closure(string): list<string>)|null  $resolver  host => IP addresses; overridable for tests
     */
    public function __construct(private ?Closure $resolver = null) {}

    /**
     * Returns the host and the addresses it resolved to, so a caller can pin
     * the connection to what was checked.
     *
     * @return array{host: string, addresses: list<string>}
     *
     * @throws UrlNotAllowed
     */
    public function check(string $url): array
    {
        // Browsers treat a backslash as a path separator; parse_url doesn't.
        // Refusing them outright avoids having to reason about which host
        // each parser would pick.
        if (preg_match('/[\\\\\s\x00-\x1f]/', $url)) {
            throw new UrlNotAllowed('The URL contains characters that are not allowed.');
        }

        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower(trim($parts['host'] ?? '', '[]'));

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new UrlNotAllowed('Only http and https URLs are allowed.');
        }

        if ($host === '') {
            throw new UrlNotAllowed('The URL has no host.');
        }

        if (config('screenshot.allow_private_urls')) {
            return ['host' => $host, 'addresses' => []];
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolve($host);

        if ($addresses === []) {
            throw new UrlNotAllowed('The URL host could not be resolved.');
        }

        foreach ($addresses as $address) {
            if (! self::isPublicAddress($address)) {
                throw new UrlNotAllowed('The URL must point to a public address.');
            }
        }

        return ['host' => $host, 'addresses' => $addresses];
    }

    public function isAllowed(string $url): bool
    {
        try {
            $this->check($url);

            return true;
        } catch (UrlNotAllowed) {
            return false;
        }
    }

    public static function isPublicAddress(string $address): bool
    {
        if (! filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE)) {
            return false;
        }

        // FILTER_FLAG_GLOBAL_RANGE lets these through. Multicast is never a
        // web server, and NAT64 embeds an IPv4 address that the gateway will
        // happily translate into a private one.
        $packed = inet_pton($address);

        if (strlen($packed) === 4) {
            return (ord($packed[0]) & 0xF0) !== 0xE0; // 224.0.0.0/4
        }

        return ! str_starts_with($packed, hex2bin('0064ff9b0000000000000000')) // 64:ff9b::/96
            && ord($packed[0]) !== 0xFF;                                          // ff00::/8
    }

    /**
     * @return list<string>
     */
    private function resolve(string $host): array
    {
        if ($this->resolver) {
            return ($this->resolver)($host);
        }

        // gethostbynamel goes through the system resolver, so it reads numeric
        // forms like 2130706433 and 0177.0.0.1 the same way Chrome does.
        $addresses = gethostbynamel($host) ?: [];

        foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $record) {
            if (isset($record['ipv6'])) {
                $addresses[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($addresses));
    }
}
