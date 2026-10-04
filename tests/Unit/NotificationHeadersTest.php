<?php
declare(strict_types=1);

namespace BlueFairy\Forms\Tests\Unit;

require_once dirname(__DIR__) . '/payload-stubs.php';

use BlueFairy\Forms\Mailer;
use BlueFairy\Forms\Settings;
use PHPUnit\Framework\TestCase;

/**
 * WPMAP-151: the admin notification carried only a Content-Type header, so
 * replying to it did not reach the visitor. It now has Reply-To from the
 * submission's email field, header-safe, and a bffrm_notification_headers
 * filter. (CronusMSP's contact test expects "Reply-To: Ada Lovelace
 * <ada@example.test>".)
 */
final class NotificationHeadersTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['bffrm_test_mail']    = [];
        $GLOBALS['bffrm_test_filters'] = [];
    }

    private static function send(array $payload): array
    {
        $settings = new Settings();
        $data     = new \ReflectionProperty(Settings::class, 'data');
        $data->setValue($settings, ['notification_email' => 'owner@example.test']);
        (new Mailer($settings))->notify_admin(['id' => 3, 'title' => 'Contact'], $payload, 17);

        return (array) $GLOBALS['bffrm_test_mail'][0]['headers'];
    }

    public function test_the_notification_replies_to_the_visitor(): void
    {
        $headers = self::send(['name' => 'Ada Lovelace', 'email' => 'ada@example.test', 'message' => 'Hi']);

        $this->assertContains('Content-Type: text/plain; charset=UTF-8', $headers);
        $this->assertContains('Reply-To: Ada Lovelace <ada@example.test>', $headers);
    }

    public function test_first_and_last_name_when_there_is_no_name_field_and_the_address_alone_without_either(): void
    {
        $this->assertSame('Reply-To: Ada Lovelace <ada@example.test>', Mailer::reply_to(['first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada@example.test']));
        $this->assertSame('Reply-To: ada@example.test', Mailer::reply_to(['email' => ' ada@example.test ']));
    }

    public function test_no_valid_address_means_no_reply_to(): void
    {
        foreach ([[], ['email' => ''], ['email' => 'not an address'], ['email' => ['ada@example.test']], ['name' => 'Ada']] as $payload) {
            $this->assertSame('', Mailer::reply_to($payload), json_encode($payload));
        }
        $this->assertSame(['Content-Type: text/plain; charset=UTF-8'], self::send(['name' => 'Ada', 'email' => 'nope']));
    }

    public function test_a_header_injection_in_the_name_or_the_address_goes_nowhere(): void
    {
        $line = Mailer::reply_to(['name' => "Ada\r\nBcc: victim@example.test", 'email' => 'ada@example.test']);
        $this->assertStringNotContainsString("\r", $line);
        $this->assertStringNotContainsString("\n", $line);
        $this->assertSame('Reply-To: Ada Bcc: victim@example.test <ada@example.test>', $line, 'one header, the injected text left as part of the name');

        $this->assertSame('Reply-To: Ada Evil victim@example.test <ada@example.test>', Mailer::reply_to(['name' => 'Ada "Evil" <victim@example.test>', 'email' => 'ada@example.test']), 'no second address through < > or quotes');
        $this->assertSame('Reply-To: Ada Lovelace <ada@example.test>', Mailer::reply_to(['name' => 'Ada, Lovelace;', 'email' => 'ada@example.test']), 'no second address through a comma');

        foreach (["ada@example.test\r\nBcc: victim@example.test", "ada@example.test,victim@example.test", "ada@example.test\nX: y"] as $email) {
            $this->assertSame('', Mailer::reply_to(['name' => 'Ada', 'email' => $email]), json_encode($email));
        }

        $this->assertSame('Reply-To: ' . str_repeat('a', 100) . ' <a@example.test>', Mailer::reply_to(['name' => str_repeat('a', 300), 'email' => 'a@example.test']), 'the name is capped at 100');
    }

    public function test_a_site_can_change_the_headers_with_the_form_and_submission(): void
    {
        $seen = [];
        $GLOBALS['bffrm_test_filters']['bffrm_notification_headers'] = [
            static function (array $headers, array $form, array $submission) use (&$seen): array {
                $seen      = [$form['id'], $submission['id'], $submission['payload']['email']];
                $headers[] = 'X-Form: ' . $form['id'];
                return $headers;
            },
        ];

        $headers = self::send(['name' => 'Ada', 'email' => 'ada@example.test']);

        $this->assertSame([3, 17, 'ada@example.test'], $seen);
        $this->assertContains('X-Form: 3', $headers);
        $this->assertContains('Reply-To: Ada <ada@example.test>', $headers, 'after Reply-To is added');
    }
}
