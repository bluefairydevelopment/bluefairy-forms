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
 * Detection: antispam_active() / delegates(). Until 1.1.9 it was
 * function_exists('bfas_plugin'), a function BlueFairy Anti-Spam has never
 * defined (1.1.4 defines BFAS_VERSION and registers the two hooks), so Forms
 * never delegated: with Anti-Spam active, Forms still rendered and checked
 * its own honeypot and time trap, and the settings page and admin notice
 * kept recommending a plugin that was already installed.
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
     * Is BlueFairy Anti-Spam active? (It defines BFAS_VERSION when it loads.)
     */
    public static function antispam_active(): bool
    {
        return defined('BFAS_VERSION');
    }

    /**
     * Hand spam checking to Anti-Spam: only when it is active AND has
     * registered both hooks, so a form is never left with no protection.
     */
    public static function delegates(): bool
    {
        return self::antispam_active()
            && false !== has_action('bluefairy_antispam_render_fields')
            && false !== has_filter('bluefairy_antispam_check');
    }

    /**
     * Render spam-protection fields into the form output.
     *
     * @param string $form_type  bffrm_{form_id}
     */
    public function render_fields(string $form_type): void
    {
        if (self::delegates()) {
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
        return '' !== $this->reason($post_data, $form_type, $ip, $email);
    }

    /**
     * Why a submission is refused, or '' when it is not:
     *   antispam           BlueFairy Anti-Spam said spam
     *   honeypot           the hidden field was filled
     *   timetrap_missing   no time token
     *   timetrap_tampered  the token does not decode or its signature fails
     *   timetrap_fast      sent sooner than Minimum Submission Time
     *   timetrap_expired   the page was loaded more than a day ago. Not
     *                      spam (WPMAP-152): a page cache serves the form
     *                      with the token it was cached with, and refusing
     *                      it as spam lost genuine enquiries silently.
     *                      SubmissionHandler asks the visitor to send again.
     *
     * @param array<string, mixed> $post_data
     */
    public function reason(array $post_data, string $form_type, string $ip, string $email): string
    {
        if (self::delegates()) {
            return (bool) apply_filters(
                'bluefairy_antispam_check',
                false,
                $post_data,
                $form_type,
                $ip,
                $email
            ) ? 'antispam' : '';
        }

        // Self-contained fallback.
        if ((bool) $this->settings->get('honeypot_enabled', true)) {
            if ($this->check_honeypot($post_data, $form_type)) {
                return 'honeypot';
            }
        }

        return $this->timetrap_reason($post_data, $form_type);
    }

    /**
     * A fresh time token for $form_type: [field name => value], or [] when
     * Anti-Spam renders the fields or no key exists yet. The form's script
     * fetches this (TokenEndpoint) so a page served from a cache gets a
     * token from now, not from when the page was cached.
     *
     * @return array<string, string>
     */
    public function fresh_fields(string $form_type): array
    {
        if (self::delegates()) {
            return [];
        }

        $token = $this->timetrap_token($form_type);

        return null === $token ? [] : ['bffrm_tk_' . $form_type => $token];
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
        $token = $this->timetrap_token($form_type);
        if (null === $token) {
            return;
        }

        echo '<input type="hidden" name="bffrm_tk_' . esc_attr($form_type) . '" value="' . esc_attr($token) . '">';
    }

    /**
     * The signed time token: base64(nonce|timestamp|hmac), or null without a key.
     */
    public function timetrap_token(string $form_type, ?int $timestamp = null): ?string
    {
        $key = $this->settings->get_ip_hmac_key();
        if ($key === '') {
            return null;
        }

        $nonce     = wp_create_nonce('bffrm_tt_' . $form_type);
        $timestamp = $timestamp ?? time();
        $hmac      = hash_hmac('sha256', $nonce . ':' . $timestamp . ':' . $form_type, $key);

        return base64_encode($nonce . '|' . $timestamp . '|' . $hmac);
    }

    /**
     * @param array<string, mixed> $post_data
     */
    private function timetrap_reason(array $post_data, string $form_type): string
    {
        if (is_user_logged_in()) {
            return '';
        }

        $key = $this->settings->get_ip_hmac_key();
        if ($key === '') {
            return ''; // Key not yet generated — skip.
        }

        $field = 'bffrm_tk_' . $form_type;
        $token = isset($post_data[$field]) ? (string) $post_data[$field] : '';

        if ($token === '') {
            return 'timetrap_missing';
        }

        $decoded = base64_decode($token, true);
        if ($decoded === false) {
            return 'timetrap_tampered';
        }

        $parts = explode('|', $decoded, 3);
        if (count($parts) !== 3) {
            return 'timetrap_tampered';
        }

        [$nonce, $timestamp_str, $hmac] = $parts;
        $timestamp = (int) $timestamp_str;

        $expected = hash_hmac('sha256', $nonce . ':' . $timestamp . ':' . $form_type, $key);
        if (! hash_equals($expected, $hmac)) {
            return 'timetrap_tampered';
        }

        $elapsed = time() - $timestamp;
        $min     = (int) $this->settings->get('min_time_seconds', 3);

        if ($elapsed < $min) {
            return 'timetrap_fast';
        }

        if ($elapsed > 86400) { // One day.
            return 'timetrap_expired';
        }

        return '';
    }
}
