<?php
/**
 * The address a submission is attributed to (rate limit, IP hash). Headers are
 * written by whoever sends the request, so they are believed only from a
 * proxy in front of the site: Cloudflare's own ranges for CF-Connecting-IP,
 * and the site's configured proxies for X-Forwarded-For / X-Real-IP.
 *
 * @package BlueFairy\Forms
 */

declare(strict_types=1);

namespace BlueFairy\Forms\Tests\Unit;

use BlueFairy\Forms\ClientIp;
use BlueFairy\Forms\Settings;
use PHPUnit\Framework\TestCase;

final class ClientIpTest extends TestCase
{
    private const CF = ClientIp::CLOUDFLARE_RANGES;

    public function test_with_no_proxy_the_connecting_address_is_used_and_every_header_ignored(): void
    {
        $server = [
            'REMOTE_ADDR'           => '203.0.113.9',
            'HTTP_CF_CONNECTING_IP' => '198.51.100.1',
            'HTTP_X_FORWARDED_FOR'  => '198.51.100.2',
            'HTTP_X_REAL_IP'        => '198.51.100.3',
        ];

        $this->assertSame('203.0.113.9', ClientIp::resolve($server, [], self::CF), 'a spoofed CF-Connecting-IP from outside Cloudflare is ignored');
    }

    public function test_cloudflare_connecting_ip_is_believed_from_cloudflare_only(): void
    {
        $this->assertSame('198.51.100.1', ClientIp::resolve(['REMOTE_ADDR' => '172.64.10.20', 'HTTP_CF_CONNECTING_IP' => '198.51.100.1'], [], self::CF));
        $this->assertSame('2001:db8::5', ClientIp::resolve(['REMOTE_ADDR' => '2606:4700:10::1', 'HTTP_CF_CONNECTING_IP' => '2001:db8::5'], [], self::CF), 'IPv6 range');
        $this->assertSame('172.64.10.20', ClientIp::resolve(['REMOTE_ADDR' => '172.64.10.20', 'HTTP_CF_CONNECTING_IP' => 'not-an-ip'], [], self::CF), 'a bad header falls back to the connecting address');
        $this->assertSame('172.64.10.20', ClientIp::resolve(['REMOTE_ADDR' => '172.64.10.20', 'HTTP_X_FORWARDED_FOR' => '198.51.100.9'], [], self::CF), 'X-Forwarded-For from Cloudflare is not trusted on its own');
    }

    public function test_a_configured_proxy_gives_the_right_most_untrusted_forwarded_address(): void
    {
        $trusted = ['10.0.0.0/8', '192.0.2.7'];
        $server  = ['REMOTE_ADDR' => '10.1.2.3', 'HTTP_X_FORWARDED_FOR' => '6.6.6.6, 198.51.100.4, 192.0.2.7'];

        // 192.0.2.7 is a trusted hop; 198.51.100.4 is what the proxies saw;
        // 6.6.6.6 was written by the client and is not believed.
        $this->assertSame('198.51.100.4', ClientIp::resolve($server, $trusted, self::CF));
        $this->assertSame('198.51.100.8', ClientIp::resolve(['REMOTE_ADDR' => '10.1.2.3', 'HTTP_X_REAL_IP' => '198.51.100.8'], $trusted, self::CF), 'X-Real-IP when there is no X-Forwarded-For');
        $this->assertSame('10.1.2.3', ClientIp::resolve(['REMOTE_ADDR' => '10.1.2.3'], $trusted, self::CF), 'no header: the proxy itself');
        $this->assertSame('203.0.113.9', ClientIp::resolve(['REMOTE_ADDR' => '203.0.113.9', 'HTTP_X_FORWARDED_FOR' => '198.51.100.4'], $trusted, self::CF), 'not from a trusted proxy: ignored');
    }

    public function test_no_or_a_bad_connecting_address_is_zero(): void
    {
        $this->assertSame('0.0.0.0', ClientIp::resolve([], [], self::CF));
        $this->assertSame('0.0.0.0', ClientIp::resolve(['REMOTE_ADDR' => 'garbage', 'HTTP_X_FORWARDED_FOR' => '198.51.100.4'], [], self::CF));
    }

    public function test_ranges_match_by_prefix_in_their_own_family(): void
    {
        $this->assertTrue(ClientIp::in_range('104.16.0.1', '104.16.0.0/13'));
        $this->assertTrue(ClientIp::in_range('104.23.255.255', '104.16.0.0/13'));
        $this->assertFalse(ClientIp::in_range('104.24.0.0', '104.16.0.0/13'));
        $this->assertTrue(ClientIp::in_range('192.0.2.7', '192.0.2.7'), 'a bare address is /32');
        $this->assertFalse(ClientIp::in_range('192.0.2.8', '192.0.2.7'));
        $this->assertTrue(ClientIp::in_range('2a06:98c7::1', '2a06:98c0::/29'));
        $this->assertFalse(ClientIp::in_range('2a06:98c8::1', '2a06:98c0::/29'));
        $this->assertFalse(ClientIp::in_range('::ffff:104.16.0.1', '104.16.0.0/13'), 'never across families');
        $this->assertFalse(ClientIp::in_range('10.1.2.3', 'a00::/8'), 'an IPv4 address whose first byte matches an IPv6 prefix');
        $this->assertFalse(ClientIp::in_range('::1', '0.0.0.0/0'), 'all of IPv4 is not all addresses');
        $this->assertTrue(ClientIp::in_range('10.1.2.3', '0.0.0.0/0'), 'control');
        $this->assertFalse(ClientIp::in_range('104.16.0.1', '104.16.0.0/33'));
    }

    public function test_the_trusted_proxies_list_keeps_only_addresses_and_ranges(): void
    {
        $this->assertSame(['10.0.0.0/8', '192.0.2.7', '2001:db8::/32'], ClientIp::parse_list("10.0.0.0/8\n192.0.2.7, junk\n2001:db8::/32\n10.0.0.0/99\n192.0.2.7"));
        $this->assertSame([], ClientIp::parse_list(''));
    }

    public function test_current_reads_the_setting_and_both_filters(): void
    {
        $GLOBALS['bffrm_test_options']['bffrm_settings'] = ['trusted_proxies' => '10.0.0.0/8'];
        $_SERVER['REMOTE_ADDR']          = '10.1.2.3';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.4';
        try {
            $this->assertSame('198.51.100.4', ClientIp::current(new Settings()));

            $GLOBALS['bffrm_test_filters']['bffrm_trusted_proxies'][] = static fn (): array => [];
            $this->assertSame('10.1.2.3', ClientIp::current(new Settings()), 'the filter can withdraw trust');
        } finally {
            unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_X_FORWARDED_FOR']);
            $GLOBALS['bffrm_test_options'] = [];
            $GLOBALS['bffrm_test_filters'] = [];
        }
    }
}
