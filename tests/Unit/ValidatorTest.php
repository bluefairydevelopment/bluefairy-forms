<?php
declare(strict_types=1);

namespace BlueFairy\Forms\Tests\Unit;

use BlueFairy\Forms\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Validator::class)]
final class ValidatorTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new Validator();
    }

    // ------------------------------------------------------------------ //
    // Required field
    // ------------------------------------------------------------------ //

    public function test_required_field_missing_is_invalid(): void
    {
        $config = ['fields' => [['id' => 'name', 'type' => 'text', 'required' => true]]];
        $result = $this->validator->validate([], $config);

        $this->assertFalse($result['valid']);
        $this->assertSame('name', $result['error_field']);
    }

    public function test_required_field_whitespace_only_is_invalid(): void
    {
        $config = ['fields' => [['id' => 'name', 'type' => 'text', 'required' => true]]];
        $result = $this->validator->validate(['name' => '   '], $config);

        $this->assertFalse($result['valid']);
    }

    public function test_required_field_present_is_valid(): void
    {
        $config = ['fields' => [['id' => 'name', 'type' => 'text', 'required' => true]]];
        $result = $this->validator->validate(['name' => 'Alice'], $config);

        $this->assertTrue($result['valid']);
        $this->assertSame('', $result['error_field']);
    }

    public function test_optional_empty_field_passes(): void
    {
        $config = ['fields' => [['id' => 'notes', 'type' => 'text', 'required' => false]]];
        $result = $this->validator->validate([], $config);

        $this->assertTrue($result['valid']);
    }

    // ------------------------------------------------------------------ //
    // Email type
    // ------------------------------------------------------------------ //

    public function test_valid_email_passes(): void
    {
        $config = ['fields' => [['id' => 'email', 'type' => 'email', 'required' => true]]];
        $result = $this->validator->validate(['email' => 'user@example.com'], $config);

        $this->assertTrue($result['valid']);
    }

    public function test_invalid_email_fails(): void
    {
        $config = ['fields' => [['id' => 'email', 'type' => 'email', 'required' => true]]];
        $result = $this->validator->validate(['email' => 'not-an-email'], $config);

        $this->assertFalse($result['valid']);
        $this->assertSame('email', $result['error_field']);
    }

    public function test_empty_optional_email_passes(): void
    {
        $config = ['fields' => [['id' => 'email', 'type' => 'email', 'required' => false]]];
        $result = $this->validator->validate(['email' => ''], $config);

        $this->assertTrue($result['valid']);
    }

    // ------------------------------------------------------------------ //
    // URL type
    // ------------------------------------------------------------------ //

    public function test_valid_url_passes(): void
    {
        $config = ['fields' => [['id' => 'site', 'type' => 'url', 'required' => true]]];
        $result = $this->validator->validate(['site' => 'https://example.com'], $config);

        $this->assertTrue($result['valid']);
    }

    public function test_invalid_url_fails(): void
    {
        $config = ['fields' => [['id' => 'site', 'type' => 'url', 'required' => true]]];
        $result = $this->validator->validate(['site' => 'not a url'], $config);

        $this->assertFalse($result['valid']);
        $this->assertSame('site', $result['error_field']);
    }

    // ------------------------------------------------------------------ //
    // Tel type
    // ------------------------------------------------------------------ //

    public function test_valid_phone_passes(): void
    {
        $config = ['fields' => [['id' => 'phone', 'type' => 'tel', 'required' => true]]];
        $result = $this->validator->validate(['phone' => '+1 (555) 123-4567'], $config);

        $this->assertTrue($result['valid']);
    }

    public function test_invalid_phone_fails(): void
    {
        $config = ['fields' => [['id' => 'phone', 'type' => 'tel', 'required' => true]]];
        $result = $this->validator->validate(['phone' => 'not a phone!@#'], $config);

        $this->assertFalse($result['valid']);
    }

    // ------------------------------------------------------------------ //
    // Number type
    // ------------------------------------------------------------------ //

    public function test_integer_passes_number_field(): void
    {
        $config = ['fields' => [['id' => 'qty', 'type' => 'number', 'required' => true]]];
        $result = $this->validator->validate(['qty' => '42'], $config);

        $this->assertTrue($result['valid']);
    }

    public function test_decimal_passes_number_field(): void
    {
        $config = ['fields' => [['id' => 'qty', 'type' => 'number', 'required' => true]]];
        $result = $this->validator->validate(['qty' => '3.14'], $config);

        $this->assertTrue($result['valid']);
    }

    public function test_non_numeric_fails_number_field(): void
    {
        $config = ['fields' => [['id' => 'qty', 'type' => 'number', 'required' => true]]];
        $result = $this->validator->validate(['qty' => 'twelve'], $config);

        $this->assertFalse($result['valid']);
    }

    // ------------------------------------------------------------------ //
    // Date type
    // ------------------------------------------------------------------ //

    public function test_valid_date_passes(): void
    {
        $config = ['fields' => [['id' => 'dob', 'type' => 'date', 'required' => true]]];
        $result = $this->validator->validate(['dob' => '2024-03-15'], $config);

        $this->assertTrue($result['valid']);
    }

    public function test_invalid_date_fails(): void
    {
        $config = ['fields' => [['id' => 'dob', 'type' => 'date', 'required' => true]]];
        $result = $this->validator->validate(['dob' => '15/03/2024'], $config);

        $this->assertFalse($result['valid']);
    }

    public function test_impossible_date_fails(): void
    {
        $config = ['fields' => [['id' => 'dob', 'type' => 'date', 'required' => true]]];
        $result = $this->validator->validate(['dob' => '2024-02-30'], $config);

        $this->assertFalse($result['valid']);
    }

    // ------------------------------------------------------------------ //
    // Pattern
    // ------------------------------------------------------------------ //

    public function test_value_matching_pattern_passes(): void
    {
        $config = ['fields' => [['id' => 'code', 'type' => 'text', 'required' => true, 'pattern' => '^\d{4}$']]];
        $result = $this->validator->validate(['code' => '1234'], $config);

        $this->assertTrue($result['valid']);
    }

    public function test_value_not_matching_pattern_fails(): void
    {
        $config = ['fields' => [['id' => 'code', 'type' => 'text', 'required' => true, 'pattern' => '^\d{4}$']]];
        $result = $this->validator->validate(['code' => 'ABCD'], $config);

        $this->assertFalse($result['valid']);
    }

    public function test_invalid_regex_pattern_does_not_block_submission(): void
    {
        $config = ['fields' => [['id' => 'name', 'type' => 'text', 'required' => true, 'pattern' => '[invalid(']]];
        $result = $this->validator->validate(['name' => 'Alice'], $config);

        $this->assertTrue($result['valid']);
    }

    // ------------------------------------------------------------------ //
    // minlength / maxlength
    // ------------------------------------------------------------------ //

    public function test_value_shorter_than_minlength_fails(): void
    {
        $config = ['fields' => [['id' => 'msg', 'type' => 'textarea', 'required' => true, 'minlength' => 10]]];
        $result = $this->validator->validate(['msg' => 'Hi'], $config);

        $this->assertFalse($result['valid']);
    }

    public function test_value_at_minlength_passes(): void
    {
        $config = ['fields' => [['id' => 'msg', 'type' => 'textarea', 'required' => true, 'minlength' => 3]]];
        $result = $this->validator->validate(['msg' => 'Hey'], $config);

        $this->assertTrue($result['valid']);
    }

    public function test_value_exceeding_maxlength_fails(): void
    {
        $config = ['fields' => [['id' => 'name', 'type' => 'text', 'required' => true, 'maxlength' => 5]]];
        $result = $this->validator->validate(['name' => 'Toolongname'], $config);

        $this->assertFalse($result['valid']);
    }

    // ------------------------------------------------------------------ //
    // Select / radio options
    // ------------------------------------------------------------------ //

    public function test_select_value_in_options_passes(): void
    {
        $config = ['fields' => [['id' => 'size', 'type' => 'select', 'required' => true, 'options' => ['S', 'M', 'L']]]];
        $result = $this->validator->validate(['size' => 'M'], $config);

        $this->assertTrue($result['valid']);
    }

    public function test_select_value_not_in_options_fails(): void
    {
        $config = ['fields' => [['id' => 'size', 'type' => 'select', 'required' => true, 'options' => ['S', 'M', 'L']]]];
        $result = $this->validator->validate(['size' => 'XL'], $config);

        $this->assertFalse($result['valid']);
    }

    public function test_radio_value_not_in_options_fails(): void
    {
        $config = ['fields' => [['id' => 'colour', 'type' => 'radio', 'required' => true, 'options' => ['Red', 'Blue']]]];
        $result = $this->validator->validate(['colour' => 'Green'], $config);

        $this->assertFalse($result['valid']);
    }

    // ------------------------------------------------------------------ //
    // Multiple fields
    // ------------------------------------------------------------------ //

    public function test_all_valid_fields_pass(): void
    {
        $config = [
            'fields' => [
                ['id' => 'name',  'type' => 'text',  'required' => true],
                ['id' => 'email', 'type' => 'email', 'required' => true],
                ['id' => 'msg',   'type' => 'textarea', 'required' => true, 'minlength' => 5],
            ],
        ];
        $post = ['name' => 'Alice', 'email' => 'alice@example.com', 'msg' => 'Hello there'];
        $result = $this->validator->validate($post, $config);

        $this->assertTrue($result['valid']);
    }

    public function test_second_field_invalid_returns_its_id(): void
    {
        $config = [
            'fields' => [
                ['id' => 'name',  'type' => 'text',  'required' => true],
                ['id' => 'email', 'type' => 'email', 'required' => true],
            ],
        ];
        $post = ['name' => 'Alice', 'email' => 'bad-email'];
        $result = $this->validator->validate($post, $config);

        $this->assertFalse($result['valid']);
        $this->assertSame('email', $result['error_field']);
    }

    public function test_empty_config_always_passes(): void
    {
        $result = $this->validator->validate(['foo' => 'bar'], []);

        $this->assertTrue($result['valid']);
    }
}
