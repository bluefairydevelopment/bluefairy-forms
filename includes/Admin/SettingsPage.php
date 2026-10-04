<?php
declare(strict_types=1);

namespace BlueFairy\Forms\Admin;

use BlueFairy\Forms\Settings;

/**
 * Tools > BlueFairy Forms — Settings page.
 */
class SettingsPage
{
    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'bluefairy-forms'));
        }

        // Save on POST.
        if (! empty($_POST['bffrm_save_settings'])) {
            check_admin_referer('bffrm_save_settings');
            $this->save_settings();
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved.', 'bluefairy-forms') . '</p></div>';
        }

        $s = $this->settings;

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('BlueFairy Forms — Settings', 'bluefairy-forms'); ?></h1>

            <form method="post" action="">
                <?php wp_nonce_field('bffrm_save_settings'); ?>
                <input type="hidden" name="bffrm_save_settings" value="1">

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="bffrm_enabled"><?php esc_html_e('Enable Forms', 'bluefairy-forms'); ?></label></th>
                        <td>
                            <input type="checkbox" id="bffrm_enabled" name="bffrm_enabled" value="1" <?php checked((bool) $s->get('enabled', true)); ?>>
                            <label for="bffrm_enabled"><?php esc_html_e('Accept new form submissions', 'bluefairy-forms'); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bffrm_notification_email"><?php esc_html_e('Notification Email', 'bluefairy-forms'); ?></label></th>
                        <td>
                            <input type="email" id="bffrm_notification_email" name="bffrm_notification_email" value="<?php echo esc_attr((string) $s->get('notification_email', '')); ?>" class="regular-text">
                            <p class="description"><?php esc_html_e('Receive an email on each new submission. Leave blank to use the admin email.', 'bluefairy-forms'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bffrm_rate_limit_max"><?php esc_html_e('Rate Limit (per minute)', 'bluefairy-forms'); ?></label></th>
                        <td>
                            <input type="number" id="bffrm_rate_limit_max" name="bffrm_rate_limit_max" value="<?php echo (int) $s->get('rate_limit_max', 5); ?>" min="1" max="60" class="small-text">
                            <p class="description"><?php esc_html_e('Maximum submissions per IP per form per minute. Default: 5.', 'bluefairy-forms'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bffrm_min_time_seconds"><?php esc_html_e('Minimum Submission Time (seconds)', 'bluefairy-forms'); ?></label></th>
                        <td>
                            <input type="number" id="bffrm_min_time_seconds" name="bffrm_min_time_seconds" value="<?php echo (int) $s->get('min_time_seconds', 3); ?>" min="1" max="30" class="small-text">
                            <p class="description"><?php esc_html_e('Submissions arriving faster than this (in seconds) are rejected as spam. Range: 1–30. Default: 3.', 'bluefairy-forms'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bffrm_honeypot_enabled"><?php esc_html_e('Built-in Honeypot', 'bluefairy-forms'); ?></label></th>
                        <td>
                            <input type="checkbox" id="bffrm_honeypot_enabled" name="bffrm_honeypot_enabled" value="1" <?php checked((bool) $s->get('honeypot_enabled', true)); ?>>
                            <label for="bffrm_honeypot_enabled"><?php esc_html_e('Enable honeypot (only used when BlueFairy Anti-Spam is not active)', 'bluefairy-forms'); ?></label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bffrm_trusted_proxies"><?php esc_html_e('Trusted Proxies', 'bluefairy-forms'); ?></label></th>
                        <td>
                            <textarea id="bffrm_trusted_proxies" name="bffrm_trusted_proxies" rows="3" class="large-text code"><?php echo esc_textarea((string) $s->get('trusted_proxies', '')); ?></textarea>
                            <p class="description"><?php esc_html_e('Only if the site sits behind a proxy or load balancer other than Cloudflare: its IP addresses or CIDR ranges, one per line. Requests from them are attributed to the address in X-Forwarded-For or X-Real-IP. Leave empty otherwise: Cloudflare is recognised automatically, and any other forwarded header is ignored, so it cannot be used to dodge the rate limit.', 'bluefairy-forms'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="bffrm_remove_data"><?php esc_html_e('Remove Data on Uninstall', 'bluefairy-forms'); ?></label></th>
                        <td>
                            <input type="checkbox" id="bffrm_remove_data" name="bffrm_remove_data" value="1" <?php checked((bool) $s->get('remove_data_on_uninstall', false)); ?>>
                            <label for="bffrm_remove_data"><?php esc_html_e('Drop all plugin tables and options when the plugin is deleted. Cannot be undone.', 'bluefairy-forms'); ?></label>
                        </td>
                    </tr>
                </table>

                <?php
                /**
                 * Add-on settings, inside this form (Forms Pro: CAPTCHA keys,
                 * upload size, retention). Their values arrive through the
                 * bffrm_settings_input filter on save.
                 *
                 * @param \BlueFairy\Forms\Settings $s The current settings.
                 */
                do_action('bffrm_settings_fields', $s);
                ?>

                <?php submit_button(__('Save Settings', 'bluefairy-forms')); ?>
            </form>

            <?php
            // Name only features Pro actually ships. This block used to offer
            // "multi-step forms, file uploads, conditional logic, payment
            // integration" — of those four only file uploads exists. The other
            // three appear nowhere in bluefairy-forms-pro, so the free plugin
            // was advertising a paid product it could not deliver.
            //
            // It also says how to install it, because Pro is an ADD-ON: it
            // requires this plugin to stay active (Plugin.php bails with a
            // notice otherwise), and "upgrade" wrongly suggests a replacement.
            if ( ! defined( 'BFFRMP_VERSION' ) ) :
            ?>
            <div style="margin-top:2em;padding:1em 1.25em;background:#eff6ff;border:1px solid #bfdbfe;border-radius:4px;max-width:700px;color:#1e3a5f;font-size:.9em;">
                <p style="margin:0 0 .5em;">
                    <?php
                    printf(
                        /* translators: %s: link to the Forms Pro product page */
                        esc_html__( '%s adds CSV export, HTML confirmation emails, webhooks, file uploads, CAPTCHA, form scheduling, submission limits and encryption at rest.', 'bluefairy-forms' ),
                        sprintf(
                            '<a href="%s" target="_blank" rel="noopener noreferrer" style="font-weight:600;color:#1d4ed8;">%s</a>',
                            esc_url( 'https://wordpress.bluefairydevelopment.com/product/bluefairy-forms-pro/' ),
                            esc_html__( 'BlueFairy Forms Pro', 'bluefairy-forms' )
                        )
                    );
                    ?>
                </p>
                <p style="margin:0;">
                    <?php esc_html_e( 'It installs alongside this plugin rather than replacing it — keep BlueFairy Forms active and your forms, submissions and settings carry straight over.', 'bluefairy-forms' ); ?>
                </p>
            </div>
            <?php endif; ?>

            <?php if (! \BlueFairy\Forms\SpamChecker::antispam_active()): ?>
            <div class="card" style="max-width:600px;margin-top:2em;padding:1em 2em;">
                <h2><?php esc_html_e('Upgrade Spam Protection', 'bluefairy-forms'); ?></h2>
                <p>
                    <?php
                    printf(
                        /* translators: 1: opening link tag, 2: closing link tag */
                        esc_html__(
                            'The free %1$sBlueFairy Anti-Spam%2$s plugin adds Stop Forum Spam lookup, a shared block log, and works automatically with BlueFairy Forms — no configuration needed.',
                            'bluefairy-forms'
                        ),
                        '<a href="https://wordpress.org/plugins/bluefairy-antispam/" target="_blank" rel="noopener noreferrer">',
                        '</a>'
                    );
                    ?>
                </p>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Sanitize and persist submitted settings (M2 — sanitize_callback per field).
     */
    private function save_settings(): void
    {
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce verified via check_admin_referer() in render() before this method is called
        $input = [
            'enabled'                  => ! empty( $_POST['bffrm_enabled'] ),
            'notification_email'       => sanitize_email( wp_unslash( (string) ( $_POST['bffrm_notification_email'] ?? '' ) ) ),
            'rate_limit_max'           => min( 60, max( 1, absint( wp_unslash( $_POST['bffrm_rate_limit_max'] ?? 5 ) ) ) ),
            'min_time_seconds'         => min( 30, max( 1, absint( wp_unslash( $_POST['bffrm_min_time_seconds'] ?? 3 ) ) ) ),
            'honeypot_enabled'         => ! empty( $_POST['bffrm_honeypot_enabled'] ),
            'trusted_proxies'          => implode( "\n", \BlueFairy\Forms\ClientIp::parse_list( sanitize_textarea_field( wp_unslash( (string) ( $_POST['bffrm_trusted_proxies'] ?? '' ) ) ) ) ),
            'remove_data_on_uninstall' => ! empty( $_POST['bffrm_remove_data'] ),
        ];

        /**
         * Add-on settings saved with this page (see bffrm_settings_fields).
         * Each add-on reads and sanitises its own fields from $_POST.
         *
         * @param array<string, mixed> $input The plugin's own settings, sanitised.
         */
        $input = (array) apply_filters( 'bffrm_settings_input', $input );
        // phpcs:enable WordPress.Security.NonceVerification.Missing

        $this->settings->save($input);
    }
}
