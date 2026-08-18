<?php
declare(strict_types=1);

namespace BlueFairy\Forms;

/**
 * Server-side field validation against the form config_json schema.
 */
class Validator
{
    /**
     * Validate POST data against a parsed config array.
     *
     * @param array<string, mixed>   $post_data  Raw $_POST data.
     * @param array<string, mixed>   $config     Decoded config_json.
     * @return array{valid: bool, error_field: string}
     */
    public function validate(array $post_data, array $config): array
    {
        $fields = $config['fields'] ?? [];

        foreach ($fields as $field) {
            $id       = (string) ($field['id'] ?? '');
            $type     = (string) ($field['type'] ?? 'text');
            $required = (bool) ($field['required'] ?? false);
            $value    = isset($post_data[$id]) ? trim((string) $post_data[$id]) : '';

            // Required check.
            if ($required && $value === '') {
                return ['valid' => false, 'error_field' => $id];
            }

            // Skip further checks on empty optional fields.
            if ($value === '') {
                continue;
            }

            // Type-specific validation.
            if ($type === 'email' && ! is_email($value)) {
                return ['valid' => false, 'error_field' => $id];
            }

            if ($type === 'url' && ! filter_var($value, FILTER_VALIDATE_URL)) {
                return ['valid' => false, 'error_field' => $id];
            }

            if ($type === 'tel') {
                // Basic: allow +, digits, spaces, dashes, parentheses.
                if (! preg_match('/^[\+\d\s\-\(\)]{3,30}$/', $value)) {
                    return ['valid' => false, 'error_field' => $id];
                }
            }

            if ($type === 'number') {
                if (! is_numeric($value)) {
                    return ['valid' => false, 'error_field' => $id];
                }
            }

            if ($type === 'date') {
                $d = \DateTime::createFromFormat('Y-m-d', $value);
                if (! $d || $d->format('Y-m-d') !== $value) {
                    return ['valid' => false, 'error_field' => $id];
                }
            }

            // Pattern check.
            if (! empty($field['pattern'])) {
                $pattern = (string) $field['pattern'];
                // Wrap in delimiters for preg_match.
                if (@preg_match('/' . $pattern . '/', $value) === false) {
                    return ['valid' => false, 'error_field' => $id];
                }
                if (! preg_match('/' . $pattern . '/', $value)) {
                    return ['valid' => false, 'error_field' => $id];
                }
            }

            // Length checks.
            if (isset($field['minlength']) && mb_strlen($value) < (int) $field['minlength']) {
                return ['valid' => false, 'error_field' => $id];
            }
            if (isset($field['maxlength']) && mb_strlen($value) > (int) $field['maxlength']) {
                return ['valid' => false, 'error_field' => $id];
            }

            // Select / radio: value must be in options list.
            if (in_array($type, ['select', 'radio'], true) && ! empty($field['options'])) {
                if (! in_array($value, (array) $field['options'], true)) {
                    return ['valid' => false, 'error_field' => $id];
                }
            }

            // Allow Pro to run custom validation (conditional logic, file type, CAPTCHA).
            $custom_valid = apply_filters( 'bffrm_validate_field', true, $field, $value, $config );
            if ( true !== $custom_valid ) {
                return [ 'valid' => false, 'error_field' => $id ];
            }
        }

        return ['valid' => true, 'error_field' => ''];
    }
}
