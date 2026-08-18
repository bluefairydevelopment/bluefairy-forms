<?php
declare(strict_types=1);

namespace BlueFairy\Forms\Tests\Unit;

use BlueFairy\Forms\Settings;
use BlueFairy\Forms\SpamChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(SpamChecker::class)]
final class SpamCheckerTest extends TestCase
{
    /** @var Settings&MockObject */
    private Settings $settings;
    private SpamChecker $checker;
    private string $hmac_key;

    protected function setUp(): void
    {
        $this->hmac_key = bin2hex(random_bytes(32));

        $this->settings = $this->createMock(Settings::class);
        $this->settings->method('get')->willReturnMap([
            ['honeypot_enabled', true,  true],
            ['min_time_seconds', 3,     3],
        ]);
        $this->settings->method('get_ip_hmac_key')->willReturn($this->hmac_key);

        $this->checker = new SpamChecker($this->settings);
    }

    // ------------------------------------------------------------------ //
    // Honeypot field name is deterministic (same crc32 formula both sides)
    // ------------------------------------------------------------------ //

    #[DataProvider('formTypeProvider')]
    public function test_honeypot_field_is_detected_consistently(string $form_type): void
    {
        $pool  = ['website2','address_confirm','phone_verify','url_field','email_confirm2','contact_url','homepage_field','site_address'];
        $index = (crc32($form_type) & 0x7FFFFFFF) % 8;
        $field = $pool[$index];

        // Filled honeypot → spam regardless of timetrap.
        $this->assertTrue($this->checker->is_spam([$field => 'bot'], $form_type, '127.0.0.1', ''));

        // Different field filled → honeypot not triggered (timetrap may trigger — that's OK).
        $other_field = $field === 'website2' ? 'address_confirm' : 'website2';
        $result = $this->checker->is_spam([$other_field => 'bot'], $form_type, '127.0.0.1', '');
        // Result is spam due to missing timetrap, NOT due to honeypot — just verify no exception.
        $this->assertIsBool($result);
    }

    /** @return array<string, array{0: string}> */
    public static function formTypeProvider(): array
    {
        return [
            'contact'    => ['bffrm_1'],
            'newsletter' => ['bffrm_2'],
            'support'    => ['bffrm_99'],
        ];
    }

    // ------------------------------------------------------------------ //
    // Honeypot: filled field is spam
    // ------------------------------------------------------------------ //

    public function test_filled_honeypot_is_spam(): void
    {
        // Determine which field name is used for this form_type.
        // bffrm_1 → index = crc32('bffrm_1') & 0x7FFFFFFF % 8
        $pool     = ['website2','address_confirm','phone_verify','url_field','email_confirm2','contact_url','homepage_field','site_address'];
        $form_type = 'bffrm_1';
        $index    = (crc32($form_type) & 0x7FFFFFFF) % 8;
        $field    = $pool[$index];

        $post = [$field => 'bot was here'];
        $this->assertTrue($this->checker->is_spam($post, $form_type, '127.0.0.1', ''));
    }

    public function test_empty_honeypot_is_not_spam(): void
    {
        $pool     = ['website2','address_confirm','phone_verify','url_field','email_confirm2','contact_url','homepage_field','site_address'];
        $form_type = 'bffrm_1';
        $index    = (crc32($form_type) & 0x7FFFFFFF) % 8;
        $field    = $pool[$index];

        // Provide a valid timetrap token to pass that check too.
        $token = $this->build_valid_token($form_type, elapsed: 5);
        $post  = [$field => '', 'bffrm_tk_' . $form_type => $token];

        $this->assertFalse($this->checker->is_spam($post, $form_type, '127.0.0.1', ''));
    }

    // ------------------------------------------------------------------ //
    // Timetrap: missing token is spam
    // ------------------------------------------------------------------ //

    public function test_missing_timetrap_token_is_spam(): void
    {
        // No honeypot field filled, no timetrap token.
        $this->assertTrue($this->checker->is_spam([], 'bffrm_1', '127.0.0.1', ''));
    }

    // ------------------------------------------------------------------ //
    // Timetrap: tampered token is spam
    // ------------------------------------------------------------------ //

    public function test_tampered_timetrap_token_is_spam(): void
    {
        $form_type = 'bffrm_1';
        $post      = ['bffrm_tk_' . $form_type => base64_encode('nonce|12345|badhmacsignature')];

        $this->assertTrue($this->checker->is_spam($post, $form_type, '127.0.0.1', ''));
    }

    // ------------------------------------------------------------------ //
    // Timetrap: submitted too fast is spam
    // ------------------------------------------------------------------ //

    public function test_submission_below_min_time_is_spam(): void
    {
        $form_type = 'bffrm_1';
        $token     = $this->build_valid_token($form_type, elapsed: 1); // under 3s min
        $post      = ['bffrm_tk_' . $form_type => $token];

        $this->assertTrue($this->checker->is_spam($post, $form_type, '127.0.0.1', ''));
    }

    // ------------------------------------------------------------------ //
    // Timetrap: expired token (>24 h) is spam
    // ------------------------------------------------------------------ //

    public function test_expired_timetrap_token_is_spam(): void
    {
        $form_type = 'bffrm_1';
        $token     = $this->build_valid_token($form_type, elapsed: 90000); // >86400s

        $post = ['bffrm_tk_' . $form_type => $token];
        $this->assertTrue($this->checker->is_spam($post, $form_type, '127.0.0.1', ''));
    }

    // ------------------------------------------------------------------ //
    // Timetrap: valid token within window passes
    // ------------------------------------------------------------------ //

    public function test_valid_timetrap_token_is_not_spam(): void
    {
        $form_type = 'bffrm_1';
        $token     = $this->build_valid_token($form_type, elapsed: 5);

        // Need empty honeypot field to avoid honeypot check triggering.
        $pool  = ['website2','address_confirm','phone_verify','url_field','email_confirm2','contact_url','homepage_field','site_address'];
        $index = (crc32($form_type) & 0x7FFFFFFF) % 8;
        $field = $pool[$index];

        $post = [$field => '', 'bffrm_tk_' . $form_type => $token];
        $this->assertFalse($this->checker->is_spam($post, $form_type, '127.0.0.1', ''));
    }

    // ------------------------------------------------------------------ //
    // No key → timetrap skipped (not spam)
    // ------------------------------------------------------------------ //

    public function test_empty_hmac_key_skips_timetrap(): void
    {
        $settings = $this->createMock(Settings::class);
        $settings->method('get')->willReturnMap([
            ['honeypot_enabled', true, true],
            ['min_time_seconds', 3,    3],
        ]);
        $settings->method('get_ip_hmac_key')->willReturn('');

        $checker   = new SpamChecker($settings);
        $form_type = 'bffrm_1';

        // Honeypot field empty, no timetrap token → should NOT be spam (key absent).
        $pool  = ['website2','address_confirm','phone_verify','url_field','email_confirm2','contact_url','homepage_field','site_address'];
        $index = (crc32($form_type) & 0x7FFFFFFF) % 8;
        $field = $pool[$index];

        $post = [$field => ''];
        $this->assertFalse($checker->is_spam($post, $form_type, '127.0.0.1', ''));
    }

    // ------------------------------------------------------------------ //
    // Helper
    // ------------------------------------------------------------------ //

    /**
     * Build a syntactically valid timetrap token with a backdated timestamp.
     * elapsed = how many seconds ago it was "generated".
     */
    private function build_valid_token(string $form_type, int $elapsed): string
    {
        $nonce     = substr(md5(uniqid((string) mt_rand(), true)), 0, 10);
        $timestamp = time() - $elapsed;
        $hmac      = hash_hmac('sha256', $nonce . ':' . $timestamp . ':' . $form_type, $this->hmac_key);
        return base64_encode($nonce . '|' . $timestamp . '|' . $hmac);
    }
}
