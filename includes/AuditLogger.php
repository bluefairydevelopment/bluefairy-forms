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
                'action'       => sanitize_key($action),
                'actor_id'     => $actor_id,
                'subject_type' => $subject_type ? sanitize_key($subject_type) : null,
                'subject_id'   => $subject_id,
                'metadata'     => ! empty($context) ? wp_json_encode($context) : null,
            ],
            ['%s', '%d', '%s', '%d', '%s']
        );
    }
}
