<?php
declare(strict_types=1);

namespace BlueFairy\Forms;

/**
 * Spam-checking facade.
 *
 * When BlueFairy Anti-Spam is active it delegates to that plugin via its
 * public action/filter hooks. Otherwise it falls back to self-contained
 * honeypot + timetrap logic that mirrors what Anti-Spam does.
 *
 * Detection: function_exists('bfas_plugin') — avoids autoloader coupling.
 */
class SpamChecker
{
    private Settings $settings;

    /** @var string[] */
    private const FIELD_POOL = [
        'website2',
        'address_confirm',
        'phone_verify',
        'url_field',
        'email_confirm2',
        'contact_url',
        'homepage_field',
        'site_address',
    ];

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Render spam-protection fields into the form output.
     *
     * @param string $form_type  bffrm_{form_id}
     */
    public function render_fields(string $form_type): void
    {
        if (function_exists('bfas_plugin')) {
            do_action('bluefairy_antispam_render_fields', $form_type);
            return;
        }

        // Self-contained fallback.
        if ((bool) $this->settings->get('honeypot_enabled', true)) {
            $this->render_honeypot($form_type);
        }

        $this->render_timetrap($form_type);
    }

    /**
     * Check whether a submission is spam.
     *
     * @param array<string, mixed> $post_data
     * @param string               $form_type
     * @param string               $ip
     * @param string               $email
     */
    public function is_spam(array $post_data, string $form_type, string $ip, string $email): bool
    {
        if (function_exists('bfas_plugin')) {
            return (bool) apply_filters(
                'bluefairy_antispam_check',
                false,
                $post_data,
                $form_type,
                $ip,
                $email
            );
        }

        // Self-contained fallback.
        if ((bool) $this->settings->get('honeypot_enabled', true)) {
            if ($this->check_honeypot($post_data, $form_type)) {
                return true;
            }
        }

        return $this->check_timetrap($post_data, $form_type);
    }

    // ------------------------------------------------------------------ //
    // Self-contained honeypot
    // ------------------------------------------------------------------ //

    private function honeypot_field_name(string $form_type): string
    {
        $index = (crc32($form_type) & 0x7FFFFFFF) % 8;
        return self::FIELD_POOL[$index];
    }

    private function render_honeypot(string $form_type): void
    {
        $name = esc_attr($this->honeypot_field_name($form_type));
        echo '<div class="bfas-hp-wrap" aria-hidden="true">';
        echo '<label for="bfas_' . esc_attr( $name ) . '">' . esc_html__('Leave this field empty', 'bluefairy-forms') . '</label>';
        echo '<input type="text" id="bfas_' . esc_attr( $name ) . '" name="' . esc_attr( $name ) . '" value="" class="bfas-hp-field" aria-hidden="true" tabindex="-1" autocomplete="off">';
        echo '</div>';
    }

    /**
     * @param array<string, mixed> $post_data
     */
    private function check_honeypot(array $post_data, string $form_type): bool
    {
        if (is_user_logged_in()) {
            return false;
        }

        $field = $this->honeypot_field_name($form_type);
        return isset($post_data[$field]) && trim((string) $post_data[$field]) !== '';
    }

    // ------------------------------------------------------------------ //
    // Self-contained timetrap
    // ------------------------------------------------------------------ //

    private function render_timetrap(string $form_type): void
    {
        $key  = $this->settings->get_ip_hmac_key();
        if ($key === '') {
            return;
        }

        $nonce     = wp_create_nonce('bffrm_tt_' . $form_type);
        $timestamp = time();
        $hmac      = hash_hmac('sha256', $nonce . ':' . $timestamp . ':' . $form_type, $key);
        $token     = base64_encode($nonce . '|' . $timestamp . '|' . $hmac);

        echo '<input type="hidden" name="bffrm_tk_' . esc_attr($form_type) . '" value="' . esc_attr($token) . '">';
    }

    /**
     * @param array<string, mixed> $post_data
     */
    private function check_timetrap(array $post_data, string $form_type): bool
    {
        if (is_user_logged_in()) {
            return false;
        }

        $key = $this->settings->get_ip_hmac_key();
        if ($key === '') {
            return false; // Key not yet generated — skip.
        }

        $field = 'bffrm_tk_' . $form_type;
        $token = isset($post_data[$field]) ? (string) $post_data[$field] : '';

        if ($token === '') {
            return true; // Missing token = spam.
        }

        $decoded = base64_decode($token, true);
        if ($decoded === false) {
            return true;
        }

        $parts = explode('|', $decoded, 3);
        if (count($parts) !== 3) {
            return true;
        }

        [$nonce, $timestamp_str, $hmac] = $parts;
        $timestamp = (int) $timestamp_str;

        $expected = hash_hmac('sha256', $nonce . ':' . $timestamp . ':' . $form_type, $key);
        if (! hash_equals($expected, $hmac)) {
            return true; // Tampered.
        }

        $elapsed = time() - $timestamp;
        $min     = (int) $this->settings->get('min_time_seconds', 3);

        if ($elapsed < $min) {
            return true; // Too fast.
        }

        if ($elapsed > 86400) {
            return true; // Expired.
        }

        return false;
    }
}
