<?php
declare(strict_types=1);

namespace BlueFairy\Forms;

use BlueFairy\Forms\Admin\AdminMenu;

/**
 * Central plugin bootstrap — wires every component together.
 */
final class Plugin
{
    private static ?Plugin $instance = null;

    private Settings $settings;

    private function __construct()
    {
        $this->settings = new Settings();
    }

    public static function instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Boot all plugin components.
     */
    public function boot(): void
    {
        $this->register_block();
        $this->register_shortcode();
        $this->register_submission_handler();
        $this->register_ajax_nonce();

        // WPMAP-152: pages with a form are not page-cached, and a form served
        // from a cache anyway fetches fresh tokens on load.
        Settings::register(); // Own keys survive an older Forms Pro's sanitiser.
        PageCache::register();
        ( new TokenEndpoint($this->settings) )->register();
        $this->register_gdpr_hooks();
        $this->register_multisite_hook();
        $this->register_cli();

        if (is_admin()) {
            $this->boot_admin();
        }
    }

    private function register_block(): void
    {
        add_action('init', function (): void {
            register_block_type(BFFRM_DIR . 'build/blocks/form/block.json');
        });
    }

    private function register_shortcode(): void
    {
        add_shortcode('bluefairy_form', function (array|string $atts): string {
            $atts   = shortcode_atts(['id' => 0], is_array($atts) ? $atts : []);
            $formId = (int) $atts['id'];

            if ($formId < 1) {
                return '';
            }

            // Skip style enqueue in non-page contexts (REST, AJAX, CLI).
            if (! \wp_doing_ajax() && ! defined('WP_CLI') && ! (defined('REST_REQUEST') && REST_REQUEST)) {
                \wp_enqueue_style(
                    'bffrm-form',
                    BFFRM_URL . 'build/blocks/form/style.css',
                    [],
                    BFFRM_VERSION
                );
            }

            return Renderer::render_form($formId);
        });
    }

    private function register_submission_handler(): void
    {
        $handler = new SubmissionHandler($this->settings);
        // Both logged-in and guest users submit.
        // Cron callback for audit-log retention. Bound outside is_admin() because
        // WP-Cron requests are not admin requests — binding it in boot_admin()
        // would mean the scheduled event fired with no listener and the table
        // kept growing while the schedule looked healthy.
        add_action('bffrm_prune_audit_log', static function (): void {
            ( new AuditLogger() )->prune();
        });

        add_action('admin_post_bffrm_submit', [$handler, 'handle']);
        add_action('admin_post_nopriv_bffrm_submit', [$handler, 'handle']);
    }

    private function register_gdpr_hooks(): void
    {
        $gdpr = new GdprHandler();
        add_filter('wp_privacy_personal_data_exporters', [$gdpr, 'register_exporter']);
        add_filter('wp_privacy_personal_data_erasers', [$gdpr, 'register_eraser']);
    }

    private function register_multisite_hook(): void
    {
        // Create tables on new site creation in a multisite network.
        add_action('wp_initialize_site', function (\WP_Site $new_site): void {
            switch_to_blog((int) $new_site->blog_id);
            ( new Installer() )->install_tables();
            restore_current_blog();
        });
    }

    private function register_ajax_nonce(): void
    {
        add_action('wp_ajax_bffrm_nonce', function (): void {
            if (! current_user_can('manage_options')) {
                wp_die('', 403);
            }
            $action = sanitize_text_field(wp_unslash($_POST['nonce_action'] ?? ''));
            wp_send_json_success(wp_create_nonce($action));
        });
    }

    private function register_cli(): void
    {
        if (! defined('WP_CLI') || ! WP_CLI) {
            return;
        }
        \WP_CLI::add_command('bluefairy-forms', Cli\CliCommands::class);
    }

    private function boot_admin(): void
    {
        $menu = new AdminMenu($this->settings);
        $menu->register();
    }

    public function get_settings(): Settings
    {
        return $this->settings;
    }
}
