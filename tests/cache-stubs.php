<?php
/**
 * Stubs for PageCache and TokenEndpoint (WPMAP-152).
 *
 * @package BlueFairy\Forms
 */

if (! function_exists('has_shortcode')) {
    function has_shortcode($content, $tag) { return str_contains((string) $content, '[' . $tag); }
}
if (! function_exists('has_block')) {
    function has_block($name, $content = null) { return str_contains((string) $content, '<!-- wp:' . $name); }
}
if (! function_exists('nocache_headers')) {
    function nocache_headers() { $GLOBALS['bffrm_test_nocache'] = ($GLOBALS['bffrm_test_nocache'] ?? 0) + 1; }
}
if (! function_exists('get_transient')) {
    function get_transient($name) { return $GLOBALS['bffrm_test_transients'][$name] ?? false; }
}
if (! function_exists('set_transient')) {
    function set_transient($name, $value, $ttl = 0) { $GLOBALS['bffrm_test_transients'][$name] = $value; return true; }
}
if (! function_exists('rest_url')) {
    function rest_url($path = '') { return 'https://example.test/wp-json/' . ltrim((string) $path, '/'); }
}
if (! class_exists('WP_REST_Response')) {
    class WP_REST_Response
    {
        /** @var array<string, string> */
        public array $headers = [];

        public function __construct(public mixed $data = null, public int $status = 200) {}

        public function header($key, $value, $replace = true) { $this->headers[$key] = (string) $value; }

        public function get_status() { return $this->status; }

        public function get_data() { return $this->data; }
    }
}
if (! function_exists('esc_sql')) {
    function esc_sql($value) { return addslashes((string) $value); }
}
