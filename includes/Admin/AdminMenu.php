<?php
declare(strict_types=1);

namespace BlueFairy\Forms\Admin;

use BlueFairy\Forms\Settings;

/**
 * Registers the Tools > BlueFairy Forms admin menu and sub-pages.
 * Also handles the dismissible anti-spam notice.
 */
class AdminMenu
{
    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_notices', [$this, 'maybe_render_antispam_notice']);
        add_action('wp_ajax_bffrm_dismiss_notice', [$this, 'ajax_dismiss_notice']);
    }

    public function add_menu(): void
    {
        // Main entry under Tools.
        add_management_page(
            __('BlueFairy Forms', 'bluefairy-forms-lite'),
            __('BlueFairy Forms', 'bluefairy-forms-lite'),
            'manage_options',
            'bffrm-forms',
            [new FormsListPage(), 'render']
        );

        add_submenu_page(
            'tools.php',
            __('BlueFairy Forms — Submissions', 'bluefairy-forms-lite'),
            __('BF Forms — Submissions', 'bluefairy-forms-lite'),
            'manage_options',
            'bffrm-submissions',
            [new SubmissionsListPage(), 'render']
        );

        add_submenu_page(
            'tools.php',
            __('BlueFairy Forms — Settings', 'bluefairy-forms-lite'),
            __('BF Forms — Settings', 'bluefairy-forms-lite'),
            'manage_options',
            'bffrm-settings',
            [new SettingsPage($this->settings), 'render']
        );
    }

    /**
     * Display a dismissible admin notice when Anti-Spam is not active.
     */
    public function maybe_render_antispam_notice(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        // Already dismissed?
        if (get_transient('bffrm_antispam_notice_dismissed')) {
            return;
        }

        // Anti-Spam is active?
        if (function_exists('bfas_plugin')) {
            return;
        }

        $nonce     = wp_create_nonce('bffrm_dismiss_notice');
        $nonce_url = admin_url('admin-ajax.php?action=bffrm_dismiss_notice&_wpnonce=' . $nonce);

        ?>
        <div class="notice notice-warning is-dismissible bffrm-antispam-notice" data-dismiss-url="<?php echo esc_url($nonce_url); ?>">
            <p>
                <strong><?php esc_html_e('Spam protection notice:', 'bluefairy-forms-lite'); ?></strong>
                <?php
                printf(
                    /* translators: 1: opening link tag, 2: closing link tag */
                    esc_html__(
                        'BlueFairy Forms includes basic spam protection, but for much stronger coverage — including Stop Forum Spam lookup and a shared block log — install the free %1$sBlueFairy Anti-Spam%2$s plugin. No configuration needed; the two plugins detect and use each other automatically.',
                        'bluefairy-forms-lite'
                    ),
                    '<a href="https://wordpress.org/plugins/bluefairy-antispam/" target="_blank" rel="noopener noreferrer">',
                    '</a>'
                );
                ?>
            </p>
        </div>
        <script>
        (function () {
            document.addEventListener('DOMContentLoaded', function () {
                var notice = document.querySelector('.bffrm-antispam-notice');
                if (!notice) return;
                notice.addEventListener('click', function (e) {
                    if (e.target.classList.contains('notice-dismiss')) {
                        var url = notice.getAttribute('data-dismiss-url');
                        if (url) {
                            fetch(url, { method: 'POST', credentials: 'same-origin' });
                        }
                    }
                });
            });
        }());
        </script>
        <?php
    }

    /**
     * AJAX handler: dismiss the anti-spam notice for 30 days.
     */
    public function ajax_dismiss_notice(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('', '', ['response' => 403]);
        }

        check_ajax_referer('bffrm_dismiss_notice');

        set_transient('bffrm_antispam_notice_dismissed', '1', 30 * DAY_IN_SECONDS);

        wp_send_json_success();
    }
}
