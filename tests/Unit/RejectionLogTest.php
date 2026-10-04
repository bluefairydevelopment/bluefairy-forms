<?php
/**
 * WPMAP-152: a page cache served the form with day-old tokens; the expired
 * time token was refused as SPAM and nothing recorded any refusal, so a site
 * lost every enquiry silently. An expired token now asks the visitor to send
 * again, and every refusal is logged with its reason (no field values).
 *
 * @package BlueFairy\Forms
 */

declare(strict_types=1);

namespace BlueFairy\Forms\Tests\Unit;

use BlueFairy\Forms\Settings;
use BlueFairy\Forms\SpamChecker;
use BlueFairy\Forms\SubmissionHandler;
use PHPUnit\Framework\TestCase;

final class RejectionLogTest extends TestCase
{
    private const KEY = 'test-hmac-key-0123456789abcdef';

    protected function setUp(): void
    {
        $GLOBALS['wpdb']                 = new \BFFRM_Test_Wpdb();
        $GLOBALS['bffrm_test_options']   = ['bffrm_ip_hmac_key' => self::KEY];
        $GLOBALS['bffrm_test_filters']   = [];
        $GLOBALS['bffrm_test_logged_in'] = false;
        $GLOBALS['bffrm_test_rows']      = [[
            'id'          => 4,
            'config_json' => (string) json_encode(['fields' => [['id' => 'name', 'type' => 'text', 'required' => true], ['id' => 'email', 'type' => 'email']]]),
        ]];
    }

    protected function tearDown(): void
    {
        $_POST = [];
        $GLOBALS['bffrm_test_options'] = [];
    }

    private static function token(int $age): string
    {
        return (string) (new SpamChecker(new Settings()))->timetrap_token('bffrm_4', time() - $age);
    }

    /** Post the form; return [where it was sent, the rejections logged]. */
    private static function submit(array $post): array
    {
        $_POST = $post + ['bffrm_form_id' => '4', 'bffrm_nonce' => 'nonce-bffrm_submit_4', 'name' => 'Dana Private', 'email' => 'dana@example.test'];
        try {
            (new SubmissionHandler(new Settings()))->handle();
            self::fail('handle() did not redirect');
        } catch (\BFFRM_Test_Redirect $r) {
            $logged = array_values(array_filter($GLOBALS['wpdb']->inserts, static fn ($i) => str_ends_with($i[0], 'bffrm_audit_log') && 'submission.rejected' === $i[1]['action']));
            return [$r->getMessage(), array_map(static fn ($i) => json_decode((string) $i[1]['metadata'], true), $logged)];
        }
    }

    public function test_a_day_old_token_is_not_spam_the_visitor_is_asked_to_send_again(): void
    {
        [$url, $logged] = self::submit(['bffrm_tk_bffrm_4' => self::token(25 * 3600)]);

        $this->assertStringContainsString('bffrm_error=expired', $url);
        $this->assertStringNotContainsString('bffrm_error=spam', $url);
        $this->assertSame([['form_id' => 4, 'reason' => 'timetrap_expired']], $logged);
    }

    public function test_each_spam_refusal_is_logged_with_its_reason(): void
    {
        $cases = [
            'timetrap_fast'     => ['bffrm_tk_bffrm_4' => self::token(0)],
            'timetrap_missing'  => [],
            'timetrap_tampered' => ['bffrm_tk_bffrm_4' => base64_encode('x|1|y')],
        ];
        foreach ($cases as $reason => $post) {
            $GLOBALS['wpdb'] = new \BFFRM_Test_Wpdb();
            [$url, $logged] = self::submit($post);
            $this->assertStringContainsString('bffrm_error=spam', $url, $reason);
            $this->assertSame([['form_id' => 4, 'reason' => $reason]], $logged, $reason);
        }
    }

    public function test_a_stale_nonce_and_a_closed_form_are_logged_too(): void
    {
        [$url, $logged] = self::submit(['bffrm_nonce' => 'nonce-from-yesterday']);
        $this->assertStringContainsString('bffrm_error=nonce_fail', $url);
        $this->assertSame([['form_id' => 4, 'reason' => 'nonce']], $logged);

        $GLOBALS['wpdb'] = new \BFFRM_Test_Wpdb();
        $GLOBALS['bffrm_test_options']['bffrm_settings'] = ['enabled' => false];
        [, $logged] = self::submit([]);
        $this->assertSame([['form_id' => 4, 'reason' => 'closed']], $logged);
    }

    public function test_the_log_holds_no_field_values_or_address(): void
    {
        $GLOBALS['wpdb'] = new \BFFRM_Test_Wpdb();
        self::submit(['bffrm_tk_bffrm_4' => self::token(25 * 3600)]);

        $row = json_encode($GLOBALS['wpdb']->inserts);
        $this->assertStringNotContainsString('Dana Private', (string) $row);
        $this->assertStringNotContainsString('dana@example.test', (string) $row);
        $this->assertStringContainsString('timetrap_expired', (string) $row, 'control: the row was written');
    }

    public function test_a_fresh_token_passes_the_time_trap(): void
    {
        $this->assertSame('', (new SpamChecker(new Settings()))->reason(['bffrm_tk_bffrm_4' => self::token(60)], 'bffrm_4', '192.0.2.1', ''));
        $this->assertSame(['bffrm_tk_bffrm_4'], array_keys((new SpamChecker(new Settings()))->fresh_fields('bffrm_4')));
    }
}
