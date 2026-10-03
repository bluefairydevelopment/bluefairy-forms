<?php
declare(strict_types=1);

/**
 * Stubs for the payload-value tests (WPMAP-147): wp_mail() and the helpers
 * Mailer::notify_admin(), GdprHandler::export_user_data() and the submission
 * screen touch, and a $wpdb that answers with seeded rows. Every definition is
 * guarded, so a stub tests/bootstrap.php already defines wins.
 */

// phpcs:disable

defined('ABSPATH') || define('ABSPATH', __DIR__ . '/');
defined('ARRAY_A') || define('ARRAY_A', 'ARRAY_A');

if (! function_exists('__')) {
    function __($text, $domain = 'default') { return (string) $text; }
}
if (! function_exists('esc_html_e')) {
    function esc_html_e($text, $domain = 'default') { echo htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
}
if (! function_exists('esc_url')) {
    function esc_url($url) { return htmlspecialchars((string) $url, ENT_QUOTES, 'UTF-8'); }
}
if (! function_exists('admin_url')) {
    function admin_url($path = '') { return 'https://example.test/wp-admin/' . ltrim((string) $path, '/'); }
}
if (! function_exists('get_bloginfo')) {
    function get_bloginfo($show = '') { return 'Example Site'; }
}
if (! function_exists('sanitize_email')) {
    function sanitize_email($email) { return (string) filter_var((string) $email, FILTER_SANITIZE_EMAIL); }
}
if (! function_exists('wp_mail')) {
    /** Records each call in $GLOBALS['bffrm_test_mail']. */
    function wp_mail($to, $subject, $message, $headers = '', $attachments = []) {
        $GLOBALS['bffrm_test_mail'][] = ['to' => $to, 'subject' => $subject, 'body' => $message];
        return true;
    }
}

/**
 * A $wpdb whose get_row()/get_results() answer with $GLOBALS['bffrm_test_rows'].
 */
final class BFFRM_Test_Wpdb
{
    public string $prefix = 'wp_';

    public function prepare(string $query, mixed ...$args): string { return $query; }

    public function get_row(string $query, $output = null): ?array { return $GLOBALS['bffrm_test_rows'][0] ?? null; }

    public function get_results(string $query, $output = null): array { return $GLOBALS['bffrm_test_rows'] ?? []; }
}
