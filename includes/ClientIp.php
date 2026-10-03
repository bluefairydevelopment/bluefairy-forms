<?php
declare(strict_types=1);

namespace BlueFairy\Forms;

/**
 * The address a submission came from, for the rate limit and the IP hash.
 *
 * Until 1.1.9 the first of CF-Connecting-IP, X-Forwarded-For, X-Real-IP and
 * REMOTE_ADDR that held an address was believed. Those headers are written by
 * whoever sends the request, so a sender that set a new X-Forwarded-For on
 * every submission was never rate limited.
 *
 * Now REMOTE_ADDR — the address that actually connected — is the answer,
 * except:
 *
 *  - CF-Connecting-IP is used when REMOTE_ADDR is one of Cloudflare's
 *    published ranges (shipped below; filter `bffrm_cloudflare_ranges`), so a
 *    site behind Cloudflare needs no configuration;
 *  - X-Forwarded-For (right-most address that is not a trusted proxy) or
 *    X-Real-IP is used when REMOTE_ADDR is a proxy the site trusts: the
 *    "Trusted proxies" setting, one IP or CIDR per line, and filter
 *    `bffrm_trusted_proxies`.
 */
final class ClientIp
{
    /**
     * Cloudflare's published ranges (https://www.cloudflare.com/ips-v4 and
     * https://www.cloudflare.com/ips-v6), as of 2026-10-03.
     */
    public const CLOUDFLARE_RANGES = [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
        '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
        '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
        '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
        '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
    ];

    /**
     * The client address for this request, as the site is configured.
     */
    public static function current(Settings $settings): string
    {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- read as data, every value is validated with filter_var() below.
        $server = is_array($_SERVER) ? $_SERVER : [];

        /** @var array<int, string> $cloudflare */
        $cloudflare = (array) apply_filters('bffrm_cloudflare_ranges', self::CLOUDFLARE_RANGES);
        /** @var array<int, string> $trusted */
        $trusted = (array) apply_filters('bffrm_trusted_proxies', self::parse_list((string) $settings->get('trusted_proxies', '')));

        return self::resolve($server, $trusted, $cloudflare);
    }

    /**
     * @param array<string, mixed> $server     $_SERVER, or a test's.
     * @param array<int, string>   $trusted    Trusted proxies (IP or CIDR).
     * @param array<int, string>   $cloudflare Cloudflare's ranges.
     */
    public static function resolve(array $server, array $trusted, array $cloudflare): string
    {
        $remote = self::valid((string) ($server['REMOTE_ADDR'] ?? ''));

        if ('' === $remote) {
            return '0.0.0.0';
        }

        if (self::in_any($remote, $cloudflare)) {
            $cf = self::valid(trim((string) ($server['HTTP_CF_CONNECTING_IP'] ?? '')));
            if ('' !== $cf) {
                return $cf;
            }
        }

        if (! self::in_any($remote, $trusted)) {
            return $remote;
        }

        // Right to left: the right-most address is the one the trusted proxy
        // itself saw; skip any further proxies the site trusts, and the first
        // address that is not one of them is the client. Anything to its left
        // was written by the client and is not believed.
        $hops = array_reverse(array_map('trim', explode(',', (string) ($server['HTTP_X_FORWARDED_FOR'] ?? ''))));
        foreach ($hops as $hop) {
            $ip = self::valid($hop);
            if ('' === $ip) {
                break;
            }
            if (! self::in_any($ip, $trusted)) {
                return $ip;
            }
        }

        $real = self::valid(trim((string) ($server['HTTP_X_REAL_IP'] ?? '')));

        return '' !== $real ? $real : $remote;
    }

    /**
     * One IP or CIDR per line (or comma-separated); anything else is dropped.
     *
     * @return array<int, string>
     */
    public static function parse_list(string $text): array
    {
        $out = [];
        foreach (preg_split('/[\s,]+/', $text) ?: [] as $item) {
            $item = trim($item);
            if ('' !== $item && self::is_range($item)) {
                $out[] = $item;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * An IP address or a CIDR range with a prefix that fits its family.
     */
    public static function is_range(string $item): bool
    {
        $parts = explode('/', $item, 2);
        $ip    = self::valid($parts[0]);

        if ('' === $ip) {
            return false;
        }
        if (! isset($parts[1])) {
            return true;
        }

        $max = str_contains($ip, ':') ? 128 : 32;

        return ctype_digit($parts[1]) && (int) $parts[1] <= $max;
    }

    /**
     * Is $ip inside $range (an IP or a CIDR)? IPv4 and IPv6; never across families.
     */
    public static function in_range(string $ip, string $range): bool
    {
        $parts = explode('/', $range, 2);
        $a     = @inet_pton($ip); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- invalid input answers false.
        $b     = @inet_pton($parts[0]); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

        if (false === $a || false === $b || strlen($a) !== strlen($b)) {
            return false;
        }

        $bits = isset($parts[1]) && ctype_digit($parts[1]) ? (int) $parts[1] : strlen($a) * 8;
        if ($bits > strlen($a) * 8) {
            return false;
        }

        $bytes = intdiv($bits, 8);
        if (substr($a, 0, $bytes) !== substr($b, 0, $bytes)) {
            return false;
        }

        $rest = $bits % 8;
        if (0 === $rest) {
            return true;
        }

        $mask = 0xFF << (8 - $rest) & 0xFF;

        return (ord($a[$bytes]) & $mask) === (ord($b[$bytes]) & $mask);
    }

    /**
     * @param array<int, string> $ranges
     */
    private static function in_any(string $ip, array $ranges): bool
    {
        foreach ($ranges as $range) {
            if (is_string($range) && self::in_range($ip, $range)) {
                return true;
            }
        }

        return false;
    }

    private static function valid(string $ip): string
    {
        return false !== filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
    }
}
