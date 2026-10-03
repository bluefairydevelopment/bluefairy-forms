<?php
/**
 * Personal-data erasure lets an add-on erase its own copy first (Forms Pro's
 * encrypted payload stayed decryptable on an "erased" row), and uninstall
 * cleans every site of a network (it cleaned only the current one).
 *
 * @package BlueFairy\Forms
 */

declare(strict_types=1);

namespace BlueFairy\Forms\Tests\Unit;

use BlueFairy\Forms\SubmissionRepository;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class EraseAndUninstallTest extends TestCase
{
    public function test_the_add_on_hook_runs_before_the_rows_lose_their_email_hash(): void
    {
        $GLOBALS['wpdb']               = new \BFFRM_Test_Wpdb();
        $GLOBALS['bffrm_test_actions'] = [];
        $seen                          = [];
        $GLOBALS['bffrm_test_actions']['bffrm_before_erase_submissions'][] = static function (string $hash) use (&$seen): void {
            $seen[] = [$hash, count($GLOBALS['wpdb']->queries)];
        };

        (new SubmissionRepository())->erase_by_email_hash('abc123');

        $this->assertSame([['abc123', 0]], $seen, 'called with the hash, before any query');
        $this->assertCount(1, $GLOBALS['wpdb']->queries, 'control: the anonymising UPDATE ran after it');
        $this->assertStringContainsString("WHERE `email_hash` = 'abc123'", $GLOBALS['wpdb']->queries[0]);
        $GLOBALS['bffrm_test_actions'] = [];
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_uninstall_cleans_every_site_of_a_network_that_asked_for_it(): void
    {
        $sites                         = [1 => true, 2 => false, 3 => true]; // site => Remove Data on Uninstall
        $GLOBALS['bffrm_test_blog']    = 1;
        $GLOBALS['bffrm_test_dropped'] = [];

        eval('
            function is_multisite() { return true; }
            function get_sites($args = []) { return [1, 2, 3]; }
            function switch_to_blog($id) { $GLOBALS["bffrm_test_blog"] = (int) $id; $GLOBALS["wpdb"]->prefix = "wp_" . (int) $id . "_"; }
            function restore_current_blog() { $GLOBALS["bffrm_test_blog"] = 1; $GLOBALS["wpdb"]->prefix = "wp_"; }
            function delete_option($name) { return true; }
            function delete_transient($name) { return true; }
            function wp_clear_scheduled_hook($hook, $args = []) { return 0; }
        ');
        $GLOBALS['wpdb'] = new class () {
            public string $prefix = 'wp_';
            public function query(string $q) { $GLOBALS['bffrm_test_dropped'][] = $q; return 1; }
        };
        // Each site answers with its own Remove Data on Uninstall.
        $GLOBALS['bffrm_test_option_cb'] = static fn (string $name, $default) => 'bffrm_settings' === $name
            ? ['remove_data_on_uninstall' => $sites[$GLOBALS['bffrm_test_blog']]]
            : $default;
        define('WP_UNINSTALL_PLUGIN', 'bluefairy-forms/bluefairy-forms.php');

        include dirname(__DIR__, 2) . '/uninstall.php';

        $drops = implode("\n", $GLOBALS['bffrm_test_dropped']);
        $this->assertStringContainsString('DROP TABLE IF EXISTS `wp_1_bffrm_submissions`', $drops);
        $this->assertStringContainsString('DROP TABLE IF EXISTS `wp_3_bffrm_submissions`', $drops);
        $this->assertStringNotContainsString('wp_2_bffrm', $drops, 'a site that kept its data keeps it');
    }
}
