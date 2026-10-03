<?php
declare(strict_types=1);

/**
 * PHPUnit bootstrap — stubs WordPress functions used by the plugin classes.
 *
 * Only functions exercised by the unit-test suite are defined here.
 * Integration and E2E coverage lives in the Playwright suite at
 * ~/Sites/wp-testkit/suites/forms/ (the rig that certifies this plugin).
 */

require_once __DIR__ . '/../vendor/autoload.php';

// ---------------------------------------------------------------------------
// WordPress function stubs
// ---------------------------------------------------------------------------

if (! function_exists('is_email')) {
    function is_email(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (! function_exists('is_user_logged_in')) {
    function is_user_logged_in(): bool
    {
        return ! empty($GLOBALS['bffrm_test_logged_in']);
    }
}

if (! function_exists('apply_filters')) {
    /** @param mixed ...$args */
    function apply_filters(string $tag, mixed $value, mixed ...$args): mixed
    {
        // A test hooks a filter by listing callables in
        // $GLOBALS['bffrm_test_filters'][$tag]; with none, the value passes through.
        foreach ($GLOBALS['bffrm_test_filters'][$tag] ?? [] as $callback) {
            $value = $callback($value, ...$args);
        }
        return $value;
    }
}

if (! function_exists('do_action')) {
    /** @param mixed ...$args */
    function do_action(string $tag, mixed ...$args): void
    {
        $GLOBALS['bffrm_test_did'][] = [$tag, $args];
        foreach ($GLOBALS['bffrm_test_actions'][$tag] ?? [] as $callback) {
            $callback(...$args);
        }
    }
}

if (! function_exists('get_option')) {
    function get_option(string $option, mixed $default = false): mixed
    {
        if (isset($GLOBALS['bffrm_test_option_cb'])) {
            return ($GLOBALS['bffrm_test_option_cb'])($option, $default);
        }
        return array_key_exists($option, $GLOBALS['bffrm_test_options'] ?? []) ? $GLOBALS['bffrm_test_options'][$option] : $default;
    }
}

if (! function_exists('update_option')) {
    function update_option(string $option, mixed $value, bool|string $autoload = true): bool
    {
        $GLOBALS['bffrm_test_options'][$option] = $value;
        return true;
    }
}

if (! function_exists('wp_generate_password')) {
    function wp_generate_password(int $length = 12, bool $special = true, bool $extra_special = false): string
    {
        return bin2hex(random_bytes((int) ceil($length / 2)));
    }
}

if (! function_exists('hash_hmac')) {
    // hash_hmac is a native PHP function — no stub needed.
}

if (! function_exists('esc_attr')) {
    function esc_attr(string $text): string { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
}

if (! function_exists('esc_html')) {
    function esc_html(string $text): string { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
}

if (! function_exists('esc_html__')) {
    function esc_html__(string $text, string $domain = 'default'): string { return $text; }
}

if (! function_exists('wp_create_nonce')) {
    function wp_create_nonce(string $action = ''): string
    {
        return substr(md5($action . time()), 0, 10);
    }
}

if (! function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $str): string { return trim($str); }
}

if (! function_exists('absint')) {
    function absint(mixed $maybeint): int { return abs((int) $maybeint); }
}
require_once __DIR__ . '/payload-stubs.php';
require_once __DIR__ . '/handler-stubs.php';
