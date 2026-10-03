<?php
declare(strict_types=1);

namespace BlueFairy\Forms;

/**
 * One submitted value as a line of text, whatever its shape.
 *
 * A submission's payload is mostly strings, but not only: the
 * `bffrm_submission_payload` filter lets an add-on store structured values —
 * BlueFairy Forms Pro stores each uploaded file as
 * `{ original_name, disk_name, mime_type, size }` — and a multi-value field
 * arrives as a list. Every place that shows a value (the admin notification,
 * the submission screen, the personal-data export) used to cast it with
 * `(string)`, which turned an array into the word "Array" and logged
 * "Array to string conversion" on each one (WPMAP-147).
 *
 * The result is plain text; callers sanitise and escape it for their medium
 * as before.
 */
final class PayloadValue
{
    /**
     * Nested arrays deeper than this are not followed (a hostile or broken
     * payload must not recurse without end).
     */
    private const MAX_DEPTH = 4;

    /**
     * @param mixed $value A payload value.
     */
    public static function format(mixed $value): string
    {
        return self::text($value, 0);
    }

    /**
     * A stored submission's fields, ready to show.
     *
     * Free Forms keeps the payload as JSON in payload_plaintext. An add-on may
     * store it otherwise — Forms Pro's encryption at rest moves it into
     * payload_encrypted and empties payload_plaintext — so every place that
     * shows a submission's fields (the submission screen, the personal-data
     * export) asks through the `bffrm_submission_display_payload` filter
     * rather than reading the column itself (WPMAP-148).
     *
     * @param array<string,mixed> $submission A row from the submissions table.
     * @return array<mixed> Field name => value; values still need format().
     */
    public static function for_display(array $submission): array
    {
        $payload = [];

        if (! empty($submission['payload_plaintext'])) {
            $decoded = json_decode((string) $submission['payload_plaintext'], true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        /**
         * Filters the fields shown for one stored submission.
         *
         * @param array<mixed>        $payload    Decoded payload_plaintext ([] when empty).
         * @param array<string,mixed> $submission The submission row.
         */
        $filtered = apply_filters('bffrm_submission_display_payload', $payload, $submission);

        return is_array($filtered) ? $filtered : $payload;
    }

    /**
     * An uploaded-file record: an array carrying the original file name.
     *
     * @param array<mixed> $value
     */
    public static function is_file_record(array $value): bool
    {
        return isset($value['original_name']) && is_string($value['original_name']);
    }

    /**
     * @param mixed $value A payload value.
     * @param int   $depth How deep in the payload this value sits.
     */
    private static function text(mixed $value, int $depth): string
    {
        if (is_scalar($value)) {
            // As the (string) cast this replaces: true is "1", false is "".
            return (string) $value;
        }

        // null, objects, resources: nothing to show.
        if (! is_array($value) || $depth >= self::MAX_DEPTH) {
            return '';
        }

        // An uploaded file: its name, which is what a person reading the
        // submission needs. The stored (random) disk name is not shown.
        if (self::is_file_record($value)) {
            return '' !== $value['original_name'] ? $value['original_name'] : (string) ($value['disk_name'] ?? '');
        }

        $parts = [];

        foreach ($value as $key => $item) {
            $text = self::text($item, $depth + 1);

            if ('' === $text) {
                continue;
            }

            // A list (several files, a multi-select) reads as its items; any
            // other array keeps its keys so nothing reads out of context.
            $parts[] = array_is_list($value) ? $text : $key . ': ' . $text;
        }

        return implode(', ', $parts);
    }
}
