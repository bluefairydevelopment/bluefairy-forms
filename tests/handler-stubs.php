<?php
/**
 * What SubmissionHandler::handle() and the settings save call, recorded so a
 * test can read them back. A redirect ends the request: here it throws.
 *
 * @package BlueFairy\Forms
 */

declare(strict_types=1);

final class BFFRM_Test_Redirect extends RuntimeException {}

function wp_get_referer() { return 'https://example.test/contact/'; }
function home_url($path = '') { return 'https://example.test' . $path; }
function wp_validate_redirect($location, $fallback = '') { return str_starts_with((string) $location, 'https://example.test') ? $location : $fallback; }
function esc_url_raw($url) { return (string) $url; }
function wp_unslash($value) { return is_string($value) ? stripslashes($value) : $value; }
function sanitize_key($key) { return (string) preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key)); }
function sanitize_textarea_field($text) { return trim((string) $text); }
function wp_strip_all_tags($text) { return strip_tags((string) $text); }
function current_time($type, $gmt = false) { return 'mysql' === $type ? '2026-10-03 12:00:00' : 1790000000; }
function wp_verify_nonce($nonce, $action = -1) { return 'nonce-' . $action === (string) $nonce ? 1 : false; }
function add_query_arg(...$args)
{
    [$params, $url] = is_array($args[0]) ? [$args[0], (string) ($args[1] ?? '')] : [[(string) $args[0] => $args[1]], (string) ($args[2] ?? '')];
    $sep = str_contains($url, '?') ? '&' : '?';

    return $url . $sep . http_build_query($params);
}
function wp_safe_redirect($location, $status = 302)
{
    throw new BFFRM_Test_Redirect((string) $location);
}
function wp_rand($min = 0, $max = 0) { return $max > 0 ? $max : 1; }
function current_user_can($cap, ...$args) { return ! empty($GLOBALS['bffrm_test_caps'][$cap]); }
function wp_nonce_field($action = -1, $name = '_wpnonce', $referer = true, $display = true) { echo '<input type="hidden" name="' . $name . '" value="nonce-' . $action . '">'; }
function checked($checked, $current = true, $display = true) { $out = (string) $checked === (string) $current ? ' checked' : ''; if ($display) { echo $out; } return $out; }
function esc_textarea($text) { return htmlspecialchars((string) $text, ENT_QUOTES); }
function submit_button($text = null, $type = 'primary', $name = 'submit', $wrap = true, $other = null) { echo '<button>' . $text . '</button>'; }
function check_admin_referer($action = -1, $query_arg = '_wpnonce') { return 1; }

if (! function_exists('get_current_user_id')) {
    function get_current_user_id() { return ! empty($GLOBALS['bffrm_test_logged_in']) ? 1 : 0; }
}

if (! function_exists('wp_json_encode')) {
    function wp_json_encode($data, $options = 0, $depth = 512) { return json_encode($data, $options, $depth); }
}
