<?php
declare(strict_types=1);

namespace BlueFairy\Forms\Cli;

use BlueFairy\Forms\FormRepository;
use BlueFairy\Forms\Settings;

/**
 * WP-CLI commands for BlueFairy Forms.
 *
 * Usage:
 *   wp bluefairy-forms list-forms
 *   wp bluefairy-forms doctor
 */
class CliCommands
{
    /**
     * List all forms in the database.
     *
     * ## OPTIONS
     *
     * [--format=<format>]
     * : Output format (table, json, csv, ids). Default: table.
     *
     * ## EXAMPLES
     *
     *     wp bluefairy-forms list-forms
     *     wp bluefairy-forms list-forms --format=json
     *
     * @subcommand list-forms
     *
     * @param array<int, string>    $args       Positional arguments.
     * @param array<string, string> $assoc_args Associative arguments.
     */
    public function list_forms(array $args, array $assoc_args): void
    {
        $format = $assoc_args['format'] ?? 'table';
        $repo   = new FormRepository();
        $forms  = $repo->all(500, 0);

        if (empty($forms)) {
            \WP_CLI::line('No forms found.');
            return;
        }

        // WP-CLI 2.x Formatter implodes raw arrays for 'ids' format, producing 'Array'.
        // Handle ids explicitly so space-separated IDs are returned correctly.
        if ($format === 'ids') {
            echo esc_html( implode(' ', array_column($forms, 'id')) );
            return;
        }

        \WP_CLI\Utils\format_items($format, $forms, ['id', 'title', 'slug', 'status', 'created_at']);
    }

    /**
     * Run a health check on the BlueFairy Forms installation.
     *
     * Checks tables, options, and configuration.
     *
     * ## EXAMPLES
     *
     *     wp bluefairy-forms doctor
     *
     * @subcommand doctor
     *
     * @param array<int, string>    $args       Positional arguments.
     * @param array<string, string> $assoc_args Associative arguments.
     */
    public function doctor(array $args, array $assoc_args): void
    {
        global $wpdb;

        \WP_CLI::line('BlueFairy Forms — Doctor Report');
        \WP_CLI::line('================================');

        $p      = $wpdb->prefix;
        $tables = [
            $p . 'bffrm_forms',
            $p . 'bffrm_submissions',
            $p . 'bffrm_rate_limits',
            $p . 'bffrm_audit_log',
        ];

        $all_ok = true;

        // Check tables exist.
        foreach ($tables as $table) {
            $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            if ($exists === $table) {
                \WP_CLI::success("Table exists: {$table}");
            } else {
                \WP_CLI::error("Table MISSING: {$table}", false);
                $all_ok = false;
            }
        }

        // Check HMAC key.
        $hmac_key = get_option('bffrm_ip_hmac_key', '');
        if (! empty($hmac_key)) {
            \WP_CLI::success('HMAC key is set (' . strlen((string) $hmac_key) . ' chars).');
        } else {
            \WP_CLI::error('HMAC key is missing. Run wp plugin deactivate bluefairy-forms && wp plugin activate bluefairy-forms to regenerate.', false);
            $all_ok = false;
        }

        // Check settings.
        $settings = get_option('bffrm_settings', false);
        if (is_array($settings)) {
            \WP_CLI::success('Settings option is present.');
        } else {
            \WP_CLI::error('Settings option is missing.', false);
            $all_ok = false;
        }

        // Check Anti-Spam integration.
        if (\BlueFairy\Forms\SpamChecker::antispam_active()) {
            \WP_CLI::success('BlueFairy Anti-Spam is active — enhanced spam protection enabled.');
        } else {
            \WP_CLI::warning('BlueFairy Anti-Spam not active — using built-in fallback spam protection.');
        }

        // Count forms.
        $form_repo   = new FormRepository();
        $form_count  = $form_repo->count();
        \WP_CLI::line("Forms in database: {$form_count}");

        // Summary.
        \WP_CLI::line('');
        if ($all_ok) {
            \WP_CLI::success('All checks passed.');
        } else {
            \WP_CLI::warning('Some checks failed — see errors above.');
        }
    }
}
