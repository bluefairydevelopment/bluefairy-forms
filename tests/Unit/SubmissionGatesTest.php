<?php
/**
 * Two gates that were not there: "Enable Forms" off now refuses submissions
 * (the setting was saved and never read), and a form that asks for consent
 * refuses a submission without it (the checkbox was `required` in a
 * `novalidate` form, and the server only recorded the answer).
 *
 * @package BlueFairy\Forms
 */

declare(strict_types=1);

namespace BlueFairy\Forms\Tests\Unit;

use BlueFairy\Forms\Settings;
use BlueFairy\Forms\SubmissionHandler;
use PHPUnit\Framework\TestCase;

final class SubmissionGatesTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['wpdb']                 = new \BFFRM_Test_Wpdb();
        $GLOBALS['bffrm_test_options']   = [];
        $GLOBALS['bffrm_test_filters']   = [];
        $GLOBALS['bffrm_test_logged_in'] = true; // past the spam fallback, which has its own tests
        $GLOBALS['bffrm_test_rows']      = [[
            'id'          => 4,
            'config_json' => (string) json_encode([
                'fields'       => [['id' => 'name', 'type' => 'text', 'required' => true]],
                'consent_text' => 'I agree to be contacted.',
            ]),
        ]];
    }

    protected function tearDown(): void
    {
        $_POST = [];
        $GLOBALS['bffrm_test_logged_in'] = false;
        $GLOBALS['bffrm_test_options']   = [];
        $GLOBALS['bffrm_test_filters']   = [];
    }

    /** Post the form; return where it was sent. */
    private static function submit(array $post): string
    {
        $_POST = $post + ['bffrm_form_id' => '4', 'bffrm_nonce' => 'nonce-bffrm_submit_4', 'name' => 'Dana'];
        try {
            (new SubmissionHandler(new Settings()))->handle();
        } catch (\BFFRM_Test_Redirect $r) {
            return $r->getMessage();
        }
        self::fail('handle() did not redirect');
    }

    public function test_forms_switched_off_refuse_every_submission(): void
    {
        $GLOBALS['bffrm_test_options']['bffrm_settings'] = ['enabled' => false];

        $this->assertStringContainsString('bffrm_error=closed', self::submit(['bffrm_consent' => '1']));
    }

    public function test_a_form_that_asks_for_consent_refuses_a_submission_without_it(): void
    {
        $this->assertStringContainsString('bffrm_error=consent', self::submit([]));
    }

    public function test_with_consent_the_submission_goes_on_past_both_gates(): void
    {
        // Control: the same request with consent reaches bffrm_pre_submit
        // (an add-on's block there is the next stop).
        $GLOBALS['bffrm_test_filters']['bffrm_pre_submit'][] = static fn (): string => 'stop_here';

        $this->assertStringContainsString('bffrm_error=stop_here', self::submit(['bffrm_consent' => '1']));
    }

    public function test_a_form_without_consent_text_needs_no_consent(): void
    {
        $GLOBALS['bffrm_test_rows'][0]['config_json']        = (string) json_encode(['fields' => [['id' => 'name', 'type' => 'text']]]);
        $GLOBALS['bffrm_test_filters']['bffrm_pre_submit'][] = static fn (): string => 'stop_here';

        $this->assertStringContainsString('bffrm_error=stop_here', self::submit([]));
    }

    /**
     * The rate limit counts the connecting address: a spoofed X-Forwarded-For
     * from a sender that is not a trusted proxy does not give it a fresh count.
     */
    public function test_the_rate_limit_counts_the_connecting_address_not_a_spoofed_header(): void
    {
        $_SERVER['REMOTE_ADDR']          = '203.0.113.9';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.2';
        $GLOBALS['bffrm_test_filters']['bffrm_pre_submit'][] = static fn (): string => 'stop_here';
        try {
            self::submit(['bffrm_consent' => '1']);
        } finally {
            unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_X_FORWARDED_FOR']);
        }

        $sql = implode("\n", $GLOBALS['wpdb']->queries);
        $this->assertStringContainsString(hash('sha256', '203.0.113.9'), $sql, 'counted under the connecting address');
        $this->assertStringNotContainsString(hash('sha256', '198.51.100.2'), $sql);
    }
}
