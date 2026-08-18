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
        $this->load_textdomain();
        $this->register_block();
        $this->register_shortcode();
        $this->register_submission_handler();
        $this->register_gdpr_hooks();
        $this->register_multisite_hook();
        $this->register_cli();

        if (is_admin()) {
            $this->boot_admin();
        }
    }

    private function load_textdomain(): void
    {
        load_plugin_textdomain(
            'bluefairy-forms',
            false,
            dirname(plugin_basename(BFFRM_FILE)) . '/languages'
        );
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

            // Enqueue style for shortcode path (block.json handles it for block path).
            wp_enqueue_style(
                'bffrm-form',
                BFFRM_URL . 'build/blocks/form/style.css',
                [],
                BFFRM_VERSION
            );

            return Renderer::render_form($formId);
        });
    }

    private function register_submission_handler(): void
    {
        $handler = new SubmissionHandler($this->settings);
        // Both logged-in and guest users submit.
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

    private function register_cli(): void
    {
        if (! defined('WP_CLI') || ! WP_CLI) {
            return;
        }
        \WP_CLI::add_command('bluefairy-forms-lite', Cli\CliCommands::class);
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
