<?php

declare(strict_types=1);

namespace App\Support\Http;

/**
 * Rejects URLs that must never be fetched or emitted.
 *
 * Any tool that accepts a URL is an SSRF vector: a link to `http://169.254.169.254`
 * or `http://localhost:6379` turns our server into the attacker's proxy. Every such
 * tool validates here first, and anything that actually fetches goes through
 * {@see SafeHttpClient}, which re-checks after each redirect.
 */
final class UrlGuard
{
    private const ALLOWED_SCHEMES = ['http', 'https'];

    /** IPv4/IPv6 ranges that are never legitimate targets for user-supplied URLs. */
    private const BLOCKED_RANGES = [
        '0.0.0.0/8',        // "this network"
        '10.0.0.0/8',       // private
        '127.0.0.0/8',      // loopback
        '169.254.0.0/16',   // link-local, includes cloud metadata endpoints
        '172.16.0.0/12',    // private
        '192.0.0.0/24',     // IETF protocol assignments
        '192.168.0.0/16',   // private
        '198.18.0.0/15',    // benchmarking
        '224.0.0.0/4',      // multicast
        '240.0.0.0/4',      // reserved
    ];

    public static function isPublicHttpUrl(string $url): bool
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        if (! in_array(strtolower($parts['scheme']), self::ALLOWED_SCHEMES, true)) {
            return false;
        }

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        return self::hostIsPublic($parts['host']);
    }

    /**
     * Resolves the host and checks every address it points at.
     *
     * Checking *all* resolved addresses matters: a hostname can legitimately return
     * one public and one private address, and checking only the first is a bypass.
     */
    public static function hostIsPublic(string $host): bool
    {
        $host = trim($host, '[]');

        if (in_array(strtolower($host), ['localhost', 'localhost.localdomain', 'metadata.google.internal'], true)) {
            return false;
        }

        $addresses = self::resolve($host);

        if ($addresses === []) {
            return false;
        }

        foreach ($addresses as $address) {
            if (! self::addressIsPublic($address)) {
                return false;
            }
        }

        return true;
    }

    public static function addressIsPublic(string $address): bool
    {
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            // ::1 loopback, fc00::/7 unique-local, fe80::/10 link-local.
            $normalised = strtolower($address);

            return ! ($normalised === '::1'
                || str_starts_with($normalised, 'fc')
                || str_starts_with($normalised, 'fd')
                || str_starts_with($normalised, 'fe8')
                || str_starts_with($normalised, 'fe9')
                || str_starts_with($normalised, 'fea')
                || str_starts_with($normalised, 'feb'));
        }

        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return false;
        }

        foreach (self::BLOCKED_RANGES as $range) {
            if (self::inCidr($address, $range)) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> */
    private static function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];

        return array_values(array_filter(array_map(
            fn (array $record) => $record['ip'] ?? $record['ipv6'] ?? null,
            $records,
        )));
    }

    private static function inCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr);

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if ($ipLong === false || $subnetLong === false) {
            return false;
        }

        $mask = -1 << (32 - (int) $bits);

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}
