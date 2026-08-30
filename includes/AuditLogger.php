<?php
declare(strict_types=1);

namespace BlueFairy\Forms;

/**
 * Writes audit events to the bffrm_audit_log table.
 */
class AuditLogger
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'bffrm_audit_log';
    }

    /**
     * Log an audit event.
     *
     * @param string               $action       e.g. 'submission.created', 'form.deleted'
     * @param array<string, mixed> $context      Additional context data stored as JSON.
     * @param int|null             $actor_id     WP user ID (null = guest/system).
     * @param string|null          $subject_type e.g. 'submission', 'form'
     * @param int|null             $subject_id   ID of the subject entity.
     */
    public function log(
        string $action,
        array $context = [],
        ?int $actor_id = null,
        ?string $subject_type = null,
        ?int $subject_id = null
    ): void {
        global $wpdb;

        if ($actor_id === null && is_user_logged_in()) {
            $actor_id = get_current_user_id() ?: null;
        }

        $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $this->table,
            [
                'action'       => sanitize_text_field( wp_unslash( $action ) ),
                'actor_id'     => $actor_id,
                'subject_type' => $subject_type ? sanitize_key($subject_type) : null,
                'subject_id'   => $subject_id,
                'metadata'     => ! empty($context) ? wp_json_encode($context) : null,
            ],
            ['%s', '%d', '%s', '%d', '%s']
        );
    }

    /**
     * Delete audit rows older than the configured retention window.
     *
     * Runs on the bffrm_prune_audit_log cron event. Batched so a first run
     * against a large backlog cannot hold a long lock or exhaust memory; the
     * event fires daily, so a backlog drains over successive runs rather than
     * in one transaction.
     *
     * Relies on idx_created_at, added in schema revision 4. Without that index
     * this is a full table scan of the very table it exists to keep small.
     *
     * @return int Rows deleted.
     */
    public function prune(): int
    {
        global $wpdb;

        $settings = new Settings();
        $days     = (int) $settings->get('audit_retention_days', 90);

        if ($days <= 0) {
            return 0;   // Retention disabled.
        }

        return (int) $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "DELETE FROM `{$this->table}` WHERE `created_at` < DATE_SUB(UTC_TIMESTAMP(), INTERVAL %d DAY) LIMIT 1000",
                $days
            )
        );
    }

}
