<?php
declare(strict_types=1);

namespace BlueFairy\Forms\Tests\Unit;

use BlueFairy\Forms\Settings;
use BlueFairy\Forms\SpamChecker;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Forms detected BlueFairy Anti-Spam with function_exists('bfas_plugin'), a
 * function Anti-Spam has never defined, so it never delegated: with Anti-Spam
 * active the form still carried Forms' own honeypot and time trap (the rig's
 * T-05-5, with the released 1.1.4). Anti-Spam defines BFAS_VERSION and
 * registers its two hooks.
 */
final class AntiSpamDetectionTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['bffrm_test_actions'] = [];
        $GLOBALS['bffrm_test_filters'] = [];
        $GLOBALS['bffrm_test_did']     = [];
    }

    public function test_without_anti_spam_forms_keeps_its_own_fields(): void
    {
        $this->assertFalse(SpamChecker::antispam_active());
        $this->assertFalse(SpamChecker::delegates());
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_with_anti_spam_active_forms_delegates_rendering_and_checking(): void
    {
        define('BFAS_VERSION', '1.1.4');
        $rendered = [];
        $GLOBALS['bffrm_test_actions']['bluefairy_antispam_render_fields'] = [static function (string $type) use (&$rendered): void { $rendered[] = $type; }];
        $GLOBALS['bffrm_test_filters']['bluefairy_antispam_check']         = [static fn (bool $spam): bool => true];

        $this->assertTrue(SpamChecker::antispam_active());
        $this->assertTrue(SpamChecker::delegates());

        $checker = new SpamChecker(new Settings());
        ob_start();
        $checker->render_fields('bffrm_1');
        $html = (string) ob_get_clean();
        $this->assertSame(['bffrm_1'], $rendered, 'Anti-Spam renders the fields');
        $this->assertStringNotContainsString('bffrm_tk_', $html, 'and Forms renders none of its own');
        $this->assertTrue($checker->is_spam([], 'bffrm_1', '192.0.2.1', ''), 'Anti-Spam decides');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_active_without_its_hooks_forms_does_not_go_unprotected(): void
    {
        define('BFAS_VERSION', '1.1.4');

        $this->assertTrue(SpamChecker::antispam_active());
        $this->assertFalse(SpamChecker::delegates(), 'no hooks');

        $GLOBALS['bffrm_test_filters']['bluefairy_antispam_check'] = [static fn (bool $spam): bool => $spam];
        $this->assertFalse(SpamChecker::delegates(), 'the check without the renderer');

        $GLOBALS['bffrm_test_filters']                                     = [];
        $GLOBALS['bffrm_test_actions']['bluefairy_antispam_render_fields'] = [static function (): void {}];
        $this->assertFalse(SpamChecker::delegates(), 'the renderer without the check');
    }
}
