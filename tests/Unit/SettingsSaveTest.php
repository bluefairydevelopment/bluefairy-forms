<?php
/**
 * The settings page: Trusted Proxies, the add-on hooks, and saving that keeps
 * an add-on's keys. bffrm_settings is shared with Forms Pro (CAPTCHA keys,
 * upload size, retention, the encryption switch); replacing it wholesale from
 * this page's fields wiped them whenever Pro was not active to put them back.
 * "Debug Logging" did nothing and is gone.
 *
 * @package BlueFairy\Forms
 */

declare(strict_types=1);

namespace BlueFairy\Forms\Tests\Unit;

use BlueFairy\Forms\Admin\SettingsPage;
use BlueFairy\Forms\Settings;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class SettingsSaveTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['bffrm_test_options'] = [];
        $GLOBALS['bffrm_test_filters'] = [];
        $GLOBALS['bffrm_test_actions'] = [];
        $GLOBALS['bffrm_test_caps']    = ['manage_options' => true];
    }

    protected function tearDown(): void
    {
        $_POST = [];
        $GLOBALS['bffrm_test_filters'] = [];
        $GLOBALS['bffrm_test_actions'] = [];
    }

    private static function save(array $post): array
    {
        $_POST = $post;
        (new ReflectionMethod(SettingsPage::class, 'save_settings'))->invoke(new SettingsPage(new Settings()));

        return $GLOBALS['bffrm_test_options']['bffrm_settings'];
    }

    public function test_saving_keeps_an_add_ons_keys_and_drops_debug_log(): void
    {
        $GLOBALS['bffrm_test_options']['bffrm_settings'] = ['encryption_enabled' => true, 'recaptcha_secret_key' => 'sek', 'debug_log' => true, 'rate_limit_max' => 9];

        $saved = self::save(['bffrm_rate_limit_max' => '7', 'bffrm_enabled' => '1']);

        $this->assertTrue($saved['encryption_enabled'], 'Pro\'s encryption switch survives');
        $this->assertSame('sek', $saved['recaptcha_secret_key']);
        $this->assertSame(7, $saved['rate_limit_max'], 'control: this page\'s own field was saved');
        $this->assertArrayNotHasKey('debug_log', $saved);
    }

    public function test_trusted_proxies_keeps_addresses_and_ranges_only(): void
    {
        $saved = self::save(['bffrm_trusted_proxies' => "10.0.0.0/8\r\nnot an ip\r\n192.0.2.7"]);

        $this->assertSame("10.0.0.0/8\n192.0.2.7", $saved['trusted_proxies']);
    }

    public function test_an_add_on_adds_its_fields_through_the_input_filter(): void
    {
        $GLOBALS['bffrm_test_filters']['bffrm_settings_input'][] = static fn (array $input): array => $input + ['addon_key' => 'x'];

        $this->assertSame('x', self::save([])['addon_key']);
    }

    public function test_the_page_renders_the_add_on_fields_inside_the_form(): void
    {
        $GLOBALS['bffrm_test_actions']['bffrm_settings_fields'][] = static function (): void {
            echo '<tr id="addon-row"></tr>';
        };

        ob_start();
        (new SettingsPage(new Settings()))->render();
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('name="bffrm_trusted_proxies"', $html);
        $this->assertStringNotContainsString('bffrm_debug_log', $html);
        $form = substr($html, (int) strpos($html, '<form'), (int) strpos($html, '</form>') - (int) strpos($html, '<form'));
        $this->assertStringContainsString('<tr id="addon-row"></tr>', $form, 'inside the form, so it is saved with it');
    }
}
