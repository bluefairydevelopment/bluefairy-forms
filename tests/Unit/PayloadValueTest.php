<?php
declare(strict_types=1);

namespace BlueFairy\Forms\Tests\Unit;

use BlueFairy\Forms\PayloadValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * WPMAP-147: a submitted value as one line of text, whatever its shape —
 * never the word "Array", never a PHP warning.
 */
#[CoversClass(PayloadValue::class)]
final class PayloadValueTest extends TestCase
{
    private const UPLOAD = ['original_name' => 'photo.jpg', 'disk_name' => 'a1b2c3.jpg', 'mime_type' => 'image/jpeg', 'size' => 12345];

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function values(): array
    {
        return [
            'a string'                       => ['Jane', 'Jane'],
            'an int'                         => [42, '42'],
            'a float'                        => [1.5, '1.5'],
            'true, as (string) did'          => [true, '1'],
            'false, as (string) did'         => [false, ''],
            'null'                           => [null, ''],
            'a Forms Pro upload: its name'   => [self::UPLOAD, 'photo.jpg'],
            'an upload with no original name: the disk name' => [['original_name' => '', 'disk_name' => 'a1b2c3.jpg'], 'a1b2c3.jpg'],
            'several uploads'                => [[self::UPLOAD, ['original_name' => 'b.pdf', 'disk_name' => 'x.pdf']], 'photo.jpg, b.pdf'],
            'a multi-value field'            => [['red', 'green'], 'red, green'],
            'a keyed array keeps its keys'   => [['city' => 'Denton', 'zip' => '76201'], 'city: Denton, zip: 76201'],
            'empty items are skipped'        => [['a', '', null, 'b'], 'a, b'],
            'an empty array'                 => [[], ''],
            'an object'                      => [new \stdClass(), ''],
        ];
    }

    #[DataProvider('values')]
    public function test_a_value_becomes_one_line_of_text(mixed $value, string $expected): void
    {
        $this->assertSame($expected, $this->formatWithoutWarnings($value));
    }

    public function test_an_upload_record_is_recognised_by_its_original_name(): void
    {
        $this->assertTrue(PayloadValue::is_file_record(self::UPLOAD));
        $this->assertFalse(PayloadValue::is_file_record(['name' => 'photo.jpg']), 'another shape is not a file record');
        $this->assertFalse(PayloadValue::is_file_record(['original_name' => ['x']]), 'nor is a non-string name');
    }

    public function test_the_stored_disk_name_is_not_shown_when_there_is_an_original_name(): void
    {
        $this->assertStringNotContainsString('a1b2c3', $this->formatWithoutWarnings(self::UPLOAD));
    }

    public function test_deep_nesting_stops_rather_than_recursing_without_end(): void
    {
        $deep = ['x'];
        for ($i = 0; $i < 10; $i++) {
            $deep = [$deep];
        }
        $this->assertSame('', $this->formatWithoutWarnings($deep), 'below the depth limit nothing is followed');
        $this->assertSame('x', $this->formatWithoutWarnings([[['x']]]), 'control: a shallow nest is read');
    }

    /**
     * The value, with any PHP warning or notice turned into a failure.
     */
    private function formatWithoutWarnings(mixed $value): string
    {
        set_error_handler(static function (int $errno, string $errstr): bool {
            throw new \ErrorException($errstr, 0, $errno);
        });
        try {
            return PayloadValue::format($value);
        } finally {
            restore_error_handler();
        }
    }

    // --- for_display (WPMAP-148) -------------------------------------------

    protected function tearDown(): void
    {
        $GLOBALS['bffrm_test_filters'] = [];
    }

    public function test_for_display_decodes_the_stored_json(): void
    {
        $this->assertSame(['name' => 'Jane', 'n' => 2], PayloadValue::for_display(['payload_plaintext' => '{"name":"Jane","n":2}']));
    }

    public function test_for_display_is_empty_for_an_empty_or_undecodable_column(): void
    {
        $this->assertSame([], PayloadValue::for_display(['payload_plaintext' => null]));
        $this->assertSame([], PayloadValue::for_display([]));
        $this->assertSame([], PayloadValue::for_display(['payload_plaintext' => '"a string, not an object"']));
        $this->assertSame([], PayloadValue::for_display(['payload_plaintext' => '{not json']));
    }

    public function test_for_display_passes_the_decoded_payload_and_the_row_to_the_filter(): void
    {
        $seen = [];
        $GLOBALS['bffrm_test_filters']['bffrm_submission_display_payload'][] = static function ($payload, $row) use (&$seen) {
            $seen = [$payload, $row];
            return ['from' => 'filter'];
        };
        $row = ['id' => 5, 'payload_plaintext' => '{"a":"b"}'];

        $this->assertSame(['from' => 'filter'], PayloadValue::for_display($row));
        $this->assertSame([['a' => 'b'], $row], $seen);
    }

    public function test_for_display_ignores_a_filter_that_returns_something_other_than_an_array(): void
    {
        $GLOBALS['bffrm_test_filters']['bffrm_submission_display_payload'][] = static fn () => 'oops';

        $this->assertSame(['a' => 'b'], PayloadValue::for_display(['payload_plaintext' => '{"a":"b"}']));
    }
}
