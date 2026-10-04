<?php
/**
 * Forms Pro up to 1.0.6 sanitises the shared bffrm_settings option from the
 * STORED value plus a fixed list of free 1.1.8 keys, so Trusted Proxies (new
 * in 1.1.9) could not be saved while it was active. Free's late sanitiser
 * puts its own keys back from the value being saved, sanitised. And the audit
 * log's pruning cutoff is in the zone its rows are stamped in.
 *
 * @package BlueFairy\Forms
 */

declare(strict_types=1);

namespace BlueFairy\Forms\Tests\Unit;

require_once dirname(__DIR__) . '/payload-stubs.php';
require_once dirname(__DIR__) . '/handler-stubs.php';

use BlueFairy\Forms\AuditLogger;
use BlueFairy\Forms\Settings;
use PHPUnit\Framework\TestCase;

final class SettingsLateSanitiserTest extends TestCase
{
    /** What Forms Pro 1.0.6's sanitize_settings() returns: stored + its fixed 1.1.8 list from the input. */
    private static function pro_106(array $stored, array $input): array
    {
        $clean = $stored;
        foreach (['enabled', 'notification_email', 'rate_limit_max', 'min_time_seconds', 'honeypot_enabled', 'debug_log', 'remove_data_on_uninstall'] as $key) {
            if (array_key_exists($key, $input)) {
                $clean[$key] = $input[$key];
            }
        }
        return $clean;
    }

    public function test_trusted_proxies_survives_forms_pro_1_0_6s_sanitiser(): void
    {
        $stored = ['trusted_proxies' => '', 'rate_limit_max' => 5, 'debug_log' => true, 'recaptcha_site_key' => 'pro-key'];
        $input  = ['trusted_proxies' => "10.0.0.0/8\n192.0.2.7", 'rate_limit_max' => 9, 'enabled' => true];

        $after_pro = self::pro_106($stored, $input);
        $this->assertSame('', $after_pro['trusted_proxies'], 'control: Pro 1.0.6 alone drops the new value');

        $final = Settings::keep_own_keys($after_pro, 'bffrm_settings', $input);

        $this->assertSame("10.0.0.0/8\n192.0.2.7", $final['trusted_proxies']);
        $this->assertSame(9, $final['rate_limit_max']);
        $this->assertSame('pro-key', $final['recaptcha_site_key'], 'an add-on\'s key is left as its sanitiser left it');
        $this->assertArrayNotHasKey('debug_log', $final, 'the removed setting does not come back');
    }

    public function test_own_keys_are_sanitised_whatever_asked_for_the_save(): void
    {
        $clean = Settings::sanitize_own([
            'trusted_proxies'      => "10.0.0.0/8\n<script>\n192.0.2.7, junk",
            'rate_limit_max'       => '9999',
            'min_time_seconds'     => '-4',
            'audit_retention_days' => '999999',
            'enabled'              => 'yes',
            'honeypot_enabled'     => 0,
            'notification_email'   => ['x'],
            'someone_elses'        => 'left alone',
        ]);

        $this->assertSame("10.0.0.0/8\n192.0.2.7", $clean['trusted_proxies']);
        $this->assertSame(60, $clean['rate_limit_max']);
        $this->assertSame(1, $clean['min_time_seconds']);
        $this->assertSame(3650, $clean['audit_retention_days']);
        $this->assertTrue($clean['enabled']);
        $this->assertFalse($clean['honeypot_enabled']);
        $this->assertSame('', $clean['notification_email']);
        $this->assertArrayNotHasKey('someone_elses', $clean);
        $this->assertArrayNotHasKey('remove_data_on_uninstall', $clean, 'an absent key stays absent');
    }

    public function test_a_value_that_is_not_an_array_is_left_alone(): void
    {
        $this->assertSame('x', Settings::keep_own_keys('x', 'bffrm_settings', ['trusted_proxies' => '10.0.0.1']));
        $this->assertSame(['a' => 1], Settings::keep_own_keys(['a' => 1], 'bffrm_settings', null));
    }

    public function test_the_filter_is_registered_last_with_the_original_value(): void
    {
        $source = (string) file_get_contents(dirname(__DIR__, 2) . '/includes/Settings.php');
        $this->assertStringContainsString("add_filter('sanitize_option_bffrm_settings', [self::class, 'keep_own_keys'], PHP_INT_MAX, 3);", $source);
        $this->assertStringContainsString('Settings::register();', (string) file_get_contents(dirname(__DIR__, 2) . '/includes/Plugin.php'));
    }

    public function test_the_audit_log_is_pruned_against_the_databases_own_clock(): void
    {
        $GLOBALS['wpdb']               = new \BFFRM_Test_Wpdb();
        $GLOBALS['bffrm_test_options'] = [];

        (new AuditLogger())->prune();

        $sql = (string) end($GLOBALS['wpdb']->queries);
        $this->assertStringContainsString('`created_at` < DATE_SUB(NOW(), INTERVAL 90 DAY)', $sql, 'rows are stamped by CURRENT_TIMESTAMP: the same zone');
        $this->assertStringNotContainsString('UTC_TIMESTAMP', $sql);
    }
}
