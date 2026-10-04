<?php
/**
 * WPMAP-152: a page cache served the form with the nonce and time token it
 * was cached with. Pages with a form are now uncacheable, and the form's
 * script fetches fresh tokens (TokenEndpoint), uncached and rate-limited.
 *
 * @package BlueFairy\Forms
 */

declare(strict_types=1);

namespace BlueFairy\Forms\Tests\Unit;

require_once dirname(__DIR__) . '/payload-stubs.php';
require_once dirname(__DIR__) . '/handler-stubs.php';
require_once dirname(__DIR__) . '/cache-stubs.php';

use BlueFairy\Forms\PageCache;
use BlueFairy\Forms\RejectionLog;
use BlueFairy\Forms\Settings;
use BlueFairy\Forms\SpamChecker;
use BlueFairy\Forms\TokenEndpoint;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class PageCacheTokensTest extends TestCase
{
    private const KEY = 'test-hmac-key-0123456789abcdef';

    protected function setUp(): void
    {
        $GLOBALS['wpdb']                  = new \BFFRM_Test_Wpdb();
        $GLOBALS['bffrm_test_options']    = ['bffrm_ip_hmac_key' => self::KEY];
        $GLOBALS['bffrm_test_transients'] = [];
        $GLOBALS['bffrm_test_nocache']    = 0;
        $GLOBALS['bffrm_test_rows']       = [['id' => 4, 'config_json' => '{"fields":[]}']];
        $_SERVER['REMOTE_ADDR']           = '192.0.2.10';
    }

    public function test_a_post_with_the_shortcode_or_the_block_has_a_form(): void
    {
        $this->assertTrue(PageCache::has_form('Hello [bluefairy_form id="2"]'));
        $this->assertTrue(PageCache::has_form('<!-- wp:bluefairy/forms {"formId":2} /-->'));
        $this->assertFalse(PageCache::has_form('No form here [gallery]'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_a_form_response_is_marked_uncacheable(): void
    {
        $this->assertFalse(defined('DONOTCACHEPAGE'), 'control');
        PageCache::bypass();
        $this->assertTrue(defined('DONOTCACHEPAGE') && DONOTCACHEPAGE);
        // Headers: the PHP CLI has already "sent" them, so nocache_headers() is
        // skipped here, as it would be after output on a real page.
        $this->assertSame(headers_sent() ? 0 : 1, $GLOBALS['bffrm_test_nocache']);
    }

    public function test_the_endpoint_returns_a_fresh_nonce_and_time_token_uncached(): void
    {
        $response = (new TokenEndpoint(new Settings()))->tokens(['form_id' => 4]);

        $this->assertSame(200, $response->get_status());
        $this->assertMatchesRegularExpression('/^[0-9a-f]{10}$/', $response->get_data()['nonce']);
        $token = $response->get_data()['fields']['bffrm_tk_bffrm_4'] ?? '';
        $this->assertNotSame('', $token);
        [, $ts] = explode('|', (string) base64_decode($token, true));
        $this->assertEqualsWithDelta(time(), (int) $ts, 5, 'a token from now');
        $this->assertStringContainsString('no-store', $response->headers['Cache-Control'] ?? '');

        $GLOBALS['bffrm_test_logged_in'] = false;
        $this->assertSame('timetrap_fast', (new SpamChecker(new Settings()))->reason(['bffrm_tk_bffrm_4' => $token], 'bffrm_4', '', ''), 'sent the moment it is fetched: the time trap still applies');
    }

    public function test_an_unknown_form_is_not_found(): void
    {
        $GLOBALS['bffrm_test_rows'] = [];
        $this->assertSame(404, (new TokenEndpoint(new Settings()))->tokens(['form_id' => 9])->get_status());
    }

    public function test_the_endpoint_is_rate_limited_per_address(): void
    {
        $endpoint = new TokenEndpoint(new Settings());
        for ($i = 1; $i <= TokenEndpoint::LIMIT; $i++) {
            $this->assertSame(200, $endpoint->tokens(['form_id' => 4])->get_status(), 'request ' . $i);
        }
        $this->assertSame(429, $endpoint->tokens(['form_id' => 4])->get_status());

        $_SERVER['REMOTE_ADDR'] = '192.0.2.11';
        $this->assertSame(200, $endpoint->tokens(['form_id' => 4])->get_status(), 'another address has its own count');
    }

    public function test_refusals_read_back_for_the_admin_screen_with_a_label(): void
    {
        $GLOBALS['bffrm_test_rows'] = [
            ['ts' => '1791075600', 'metadata' => '{"form_id":2,"reason":"timetrap_expired"}'],
            ['ts' => '1791075540', 'metadata' => '{"form_id":2,"reason":"validation","field":"email"}'],
        ];
        $rows = RejectionLog::recent(50, 2);

        $this->assertSame([2, 'timetrap_expired', ''], [$rows[0]['form_id'], $rows[0]['reason'], $rows[0]['field']]);
        $this->assertSame('email', $rows[1]['field']);
        $this->assertSame(gmdate('Y-m-d H:i:s', 1791075600), $rows[0]['at'], 'UTC from the epoch, whatever the database\'s time zone');
        $this->assertStringContainsString('UNIX_TIMESTAMP(`created_at`)', (string) end($GLOBALS['wpdb']->queries));
        $this->assertStringContainsString("`action` = 'submission.rejected'", (string) end($GLOBALS['wpdb']->queries));
        $this->assertStringContainsString('`subject_id` = 2', (string) end($GLOBALS['wpdb']->queries));
        $this->assertStringContainsString('send again', RejectionLog::label('timetrap_expired'));
        $this->assertSame('Refused by an add-on (form_full)', RejectionLog::label('addon:form_full'));
    }
}
