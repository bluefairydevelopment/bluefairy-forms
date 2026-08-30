<?php
declare(strict_types=1);

namespace BlueFairy\Forms;

/**
 * Handles plugin activation, deactivation, and database installation.
 *
 * IMPORTANT: dbDelta() requires exactly 2-space indentation per column/key line.
 * Do not reformat the SQL strings inside install_tables().
 */
class Installer
{
    /**
     * Run on plugin activation.
     */
    public function activate(): void
    {
        $this->install_tables();
        $this->maybe_run_migrations();
        $this->maybe_seed_default_settings();
        $this->maybe_generate_ip_hmac_key();
        $this->maybe_seed_default_form();
        $this->schedule_events();
        update_option('bffrm_db_version', BFFRM_DB_VERSION);
    }

    /**
     * Run on plugin deactivation.
     */
    public function deactivate(): void
    {
        wp_clear_scheduled_hook('bffrm_prune_audit_log');
        // Nothing to unschedule — no cron events registered by this plugin (Lite).
    }

    /**
     * Create or upgrade all plugin tables using dbDelta.
     * Called on activation and also on new multisite site creation.
     */
    public function install_tables(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();
        $p               = $wpdb->prefix;

        // ------------------------------------------------------------------ //
        // bffrm_forms
        // ------------------------------------------------------------------ //
        $sql_forms = "CREATE TABLE `{$p}bffrm_forms` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `slug` varchar(64) NOT NULL,
  `title` varchar(191) NOT NULL,
  `config_json` longtext NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  PRIMARY KEY  (id),
  UNIQUE KEY uq_slug (slug),
  KEY idx_status (status)
) {$charset_collate};";

        dbDelta($sql_forms);

        // ------------------------------------------------------------------ //
        // bffrm_submissions
        // ------------------------------------------------------------------ //
        $sql_submissions = "CREATE TABLE `{$p}bffrm_submissions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `form_id` bigint(20) unsigned NOT NULL,
  `submitted_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `email` varchar(191) DEFAULT NULL,
  `email_hash` char(64) DEFAULT NULL,
  `ip_hash` char(64) DEFAULT NULL,
  `user_agent_hash` char(64) DEFAULT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'new',
  `payload_plaintext` mediumtext DEFAULT NULL,
  `consent_text_snapshot` longtext DEFAULT NULL,
  `consent_given_at` datetime(6) DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY idx_form_status_submitted (form_id,status,submitted_at),
  KEY idx_email_hash (email_hash),
  KEY idx_ip_hash (ip_hash),
  KEY idx_submitted_at (submitted_at)
) ROW_FORMAT=DYNAMIC {$charset_collate};";

        dbDelta($sql_submissions);

        // ------------------------------------------------------------------ //
        // bffrm_rate_limits
        // ------------------------------------------------------------------ //
        $sql_rate = "CREATE TABLE `{$p}bffrm_rate_limits` (
  `ip_hash` char(64) NOT NULL,
  `form_id` bigint(20) unsigned NOT NULL,
  `window_key` varchar(20) NOT NULL,
  `count` int(10) unsigned NOT NULL DEFAULT 0,
  `expires_at` datetime(6) NOT NULL,
  PRIMARY KEY  (ip_hash,form_id,window_key),
  KEY idx_expires (expires_at)
) {$charset_collate};";

        dbDelta($sql_rate);

        // ------------------------------------------------------------------ //
        // bffrm_audit_log
        // ------------------------------------------------------------------ //
        $sql_audit = "CREATE TABLE `{$p}bffrm_audit_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `action` varchar(64) NOT NULL,
  `actor_id` bigint(20) unsigned DEFAULT NULL,
  `subject_type` varchar(32) DEFAULT NULL,
  `subject_id` bigint(20) unsigned DEFAULT NULL,
  `metadata` text DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY  (id),
  KEY idx_subject (subject_type,subject_id),
  KEY idx_actor (actor_id),
  KEY idx_created_at (created_at)
) {$charset_collate};";

        dbDelta($sql_audit);
    }

    /**
     * Run incremental DB migrations for already-active installs.
     * dbDelta() can add columns but cannot change nullability, so we ALTER explicitly.
     */
    private function maybe_run_migrations(): void
    {
        global $wpdb;
        $current = (int) get_option('bffrm_db_version', '1');

        if ($current < 3) {
            // v2 → v3: make ip_hash nullable to support GDPR erasure.
            $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                "ALTER TABLE `{$wpdb->prefix}bffrm_submissions` MODIFY COLUMN `ip_hash` char(64) DEFAULT NULL"
            );
        }
    }

    /**
     * Persist default settings on fresh install so get_option() returns a full array.
     */
    private function maybe_seed_default_settings(): void
    {
        if (! get_option('bffrm_settings')) {
            add_option('bffrm_settings', Settings::defaults());
        }
    }

    /**
     * Generate the HMAC key for IP hashing on first activation (non-autoloaded).
     */
    private function maybe_generate_ip_hmac_key(): void
    {
        if (get_option('bffrm_ip_hmac_key')) {
            return;
        }

        $key = bin2hex(random_bytes(32));
        add_option('bffrm_ip_hmac_key', $key, '', 'no'); // non-autoloaded
    }

    /**
     * Insert a default "Contact" form on first activation.
     */
    private function maybe_seed_default_form(): void
    {
        global $wpdb;

        $table = esc_sql( $wpdb->prefix . 'bffrm_forms' );
        $existing = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare("SELECT COUNT(*) FROM `{$table}` WHERE slug = %s", 'contact') // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        );

        if ((int) $existing > 0) {
            return;
        }

        $config = [
            'schema_version' => '1.0',
            'submit_label'   => 'Send Message',
            'success_message' => 'Thank you! Your message has been sent.',
            'fields'         => [
                [
                    'id'       => 'name',
                    'type'     => 'text',
                    'label'    => 'Your Name',
                    'required' => true,
                ],
                [
                    'id'       => 'email',
                    'type'     => 'email',
                    'label'    => 'Email Address',
                    'required' => true,
                ],
                [
                    'id'       => 'message',
                    'type'     => 'textarea',
                    'label'    => 'Message',
                    'required' => true,
                ],
            ],
        ];

        $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $table,
            [
                'slug'        => 'contact',
                'title'       => 'Contact',
                'config_json' => wp_json_encode($config),
                'status'      => 'active',
            ],
            ['%s', '%s', '%s', '%s']
        );
    }

    /**
     * Register recurring maintenance events.
     *
     * Idempotent: wp_next_scheduled() guards against duplicate registration when
     * activate() runs again, which it now does after every plugin update.
     */
    private function schedule_events(): void
    {
        if (! wp_next_scheduled('bffrm_prune_audit_log')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'bffrm_prune_audit_log');
        }
    }

}
