<?php
declare(strict_types=1);

namespace BlueFairy\Forms\Tests\Unit;

use BlueFairy\Forms\Admin\SubmissionsListPage;
use BlueFairy\Forms\GdprHandler;
use BlueFairy\Forms\Mailer;
use BlueFairy\Forms\Settings;
use PHPUnit\Framework\TestCase;

/**
 * WPMAP-147: the three places a submitted value is shown — the admin
 * notification, the submission screen and the personal-data export — show a
 * Forms Pro upload by its file name, never as "Array", and log nothing.
 */
final class PayloadDisplayTest extends TestCase
{
    private const PAYLOAD = [
        'name'       => 'Jane',
        'attachment' => ['original_name' => 'photo.jpg', 'disk_name' => 'a1b2c3.jpg', 'mime_type' => 'image/jpeg', 'size' => 12345],
    ];

    protected function setUp(): void
    {
        $GLOBALS['bffrm_test_mail'] = [];
        $GLOBALS['bffrm_test_rows'] = [];
        $GLOBALS['wpdb']            = new \BFFRM_Test_Wpdb();
        $GLOBALS['bffrm_test_filters'] = [];
        set_error_handler(static function (int $errno, string $errstr): bool {
            throw new \ErrorException($errstr, 0, $errno);
        });
    }

    protected function tearDown(): void
    {
        restore_error_handler();
        $GLOBALS['bffrm_test_filters'] = [];
    }

    /**
     * WPMAP-148: a row whose payload an add-on stored elsewhere (Forms Pro's
     * encryption at rest empties payload_plaintext) — the add-on supplies the
     * fields through bffrm_submission_display_payload.
     *
     * @return array<string,mixed>
     */
    private function encrypted_row(): array
    {
        return [
            'id' => 18, 'form_id' => 3, 'submitted_at' => '2026-10-03 11:00:00', 'email' => null,
            'status' => 'received', 'payload_plaintext' => null, 'payload_encrypted' => 'ciphertext',
        ];
    }

    private function hook_decryptor(): void
    {
        $GLOBALS['bffrm_test_filters']['bffrm_submission_display_payload'][] = static function (array $payload, array $row): array {
            return 'ciphertext' === ($row['payload_encrypted'] ?? '') ? ['name' => 'Decrypted Dana', 'city' => 'Leeds'] : $payload;
        };
    }

    public function test_the_submission_screen_shows_the_fields_an_add_on_supplies(): void
    {
        $GLOBALS['bffrm_test_rows'] = [$this->encrypted_row()];
        $this->hook_decryptor();

        ob_start();
        try {
            (new \ReflectionMethod(SubmissionsListPage::class, 'render_detail'))->invoke(new SubmissionsListPage(), 18);
        } finally {
            $html = (string) ob_get_clean();
        }

        $this->assertStringContainsString('Submission #18', $html, 'control: the screen rendered');
        $this->assertStringContainsString('<th>city</th>', $html);
        $this->assertStringContainsString('<td>Decrypted Dana</td>', $html);
        $this->assertStringContainsString('<td>Leeds</td>', $html);
    }

    public function test_the_personal_data_export_includes_the_fields_an_add_on_supplies(): void
    {
        $GLOBALS['bffrm_test_rows'] = [$this->encrypted_row()];
        $this->hook_decryptor();

        $export = (new GdprHandler())->export_user_data('dana@example.test');

        $this->assertCount(1, $export['data'], 'control: the row was exported');
        $pairs = array_column($export['data'][0]['data'], 'value', 'name');
        $this->assertSame('Decrypted Dana', $pairs['name'] ?? null);
        $this->assertSame('Leeds', $pairs['city'] ?? null);
    }

    public function test_the_admin_notification_names_the_uploaded_file(): void
    {
        $settings = new Settings();
        $data     = new \ReflectionProperty(Settings::class, 'data');
        $data->setValue($settings, ['notification_email' => 'owner@example.test']);
        (new Mailer($settings))->notify_admin(['id' => 3, 'title' => 'Contact'], self::PAYLOAD, 17);

        $this->assertCount(1, $GLOBALS['bffrm_test_mail'], 'control: one notification was sent');
        $body = $GLOBALS['bffrm_test_mail'][0]['body'];
        $this->assertStringContainsString("name: Jane\n", $body);
        $this->assertStringContainsString("attachment: photo.jpg\n", $body);
        $this->assertStringNotContainsString('Array', $body);
    }

    public function test_the_submission_screen_names_the_uploaded_file(): void
    {
        $GLOBALS['bffrm_test_rows'] = [[
            'id' => 17, 'form_id' => 3, 'submitted_at' => '2026-10-03 10:00:00', 'email' => 'jane@example.test',
            'status' => 'received', 'payload_plaintext' => json_encode(self::PAYLOAD),
        ]];

        ob_start();
        try {
            (new \ReflectionMethod(SubmissionsListPage::class, 'render_detail'))->invoke(new SubmissionsListPage(), 17);
        } finally {
            $html = (string) ob_get_clean();
        }

        $this->assertStringContainsString('<td>Jane</td>', $html, 'control: the payload was rendered');
        $this->assertStringContainsString('<td>photo.jpg</td>', $html);
        $this->assertStringNotContainsString('Array', $html);
    }

    public function test_the_personal_data_export_names_the_uploaded_file(): void
    {
        $GLOBALS['bffrm_test_rows'] = [[
            'id' => 17, 'form_id' => 3, 'submitted_at' => '2026-10-03 10:00:00', 'email' => 'jane@example.test',
            'status' => 'received', 'payload_plaintext' => json_encode(self::PAYLOAD),
        ]];

        $export = (new GdprHandler())->export_user_data('jane@example.test');

        $values = [];
        array_walk_recursive($export, static function ($v, $k) use (&$values): void {
            $values[] = (string) $v;
        });
        $this->assertContains('Jane', $values, 'control: the payload was exported');
        $this->assertContains('photo.jpg', $values);
        $this->assertNotContains('Array', $values);
    }

    public function test_the_submission_screen_offers_add_ons_a_place_after_the_fields(): void
    {
        $GLOBALS['bffrm_test_rows'] = [[
            'id' => 17, 'form_id' => 3, 'submitted_at' => '2026-10-03 10:00:00', 'email' => 'jane@example.test',
            'status' => 'received', 'payload_plaintext' => json_encode(self::PAYLOAD),
        ]];
        $seen = [];
        $GLOBALS['bffrm_test_actions']['bffrm_submission_detail_after'][] = static function (array $row, array $payload) use (&$seen): void {
            $seen = [$row['id'], array_keys($payload)];
            echo '<div id="addon-after"></div>';
        };

        ob_start();
        try {
            (new \ReflectionMethod(SubmissionsListPage::class, 'render_detail'))->invoke(new SubmissionsListPage(), 17);
        } finally {
            $html = (string) ob_get_clean();
            $GLOBALS['bffrm_test_actions'] = [];
        }

        $this->assertSame([17, ['name', 'attachment']], $seen);
        $this->assertGreaterThan(strpos($html, '</table>'), strpos($html, '<div id="addon-after"></div>'), 'after the fields table');
    }
}
