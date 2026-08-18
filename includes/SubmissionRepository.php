<?php
declare(strict_types=1);

namespace BlueFairy\Forms;

/**
 * CRUD operations for the bffrm_submissions table.
 * Also provides GDPR export and erase by email hash.
 */
class SubmissionRepository
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'bffrm_submissions';
    }

    /**
     * Insert a new submission row.
     *
     * @param array<string, mixed> $data
     */
    public function insert(array $data): int
    {
        global $wpdb;

        $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $this->table,
            [
                'form_id'              => (int) ($data['form_id'] ?? 0),
                'email'                => isset($data['email']) ? sanitize_email((string) $data['email']) : null,
                'email_hash'           => $data['email_hash'] ?? null,
                'ip_hash'              => (string) ($data['ip_hash'] ?? ''),
                'user_agent_hash'      => $data['user_agent_hash'] ?? null,
                'user_id'              => isset($data['user_id']) ? (int) $data['user_id'] : null,
                'status'               => sanitize_key($data['status'] ?? 'new'),
                'payload_plaintext'    => $data['payload_plaintext'] ?? null,
                'consent_text_snapshot' => $data['consent_text_snapshot'] ?? null,
                'consent_given_at'     => $data['consent_given_at'] ?? null,
            ],
            ['%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s']
        );

        return (int) $wpdb->insert_id;
    }

    /**
     * Fetch a single submission by ID.
     *
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "SELECT * FROM `{$this->table}` WHERE `id` = %d LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * Return the most recent submissions (for admin list).
     *
     * @param int $form_id  0 = all forms.
     * @return array<int, array<string, mixed>>
     */
    public function recent(int $limit = 200, int $offset = 0, int $form_id = 0): array
    {
        global $wpdb;

        if ($form_id > 0) {
            $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $wpdb->prepare(
                    "SELECT s.*, f.title AS form_title FROM `{$this->table}` s LEFT JOIN `{$wpdb->prefix}bffrm_forms` f ON f.id = s.form_id WHERE s.form_id = %d ORDER BY s.submitted_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $form_id,
                    $limit,
                    $offset
                ),
                ARRAY_A
            );
        } else {
            $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $wpdb->prepare(
                    "SELECT s.*, f.title AS form_title FROM `{$this->table}` s LEFT JOIN `{$wpdb->prefix}bffrm_forms` f ON f.id = s.form_id ORDER BY s.submitted_at DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $limit,
                    $offset
                ),
                ARRAY_A
            );
        }

        return is_array($rows) ? $rows : [];
    }

    /**
     * Count total submissions, optionally by form.
     */
    public function count(int $form_id = 0): int
    {
        global $wpdb;

        if ($form_id > 0) {
            return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM `{$this->table}` WHERE form_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                    $form_id
                )
            );
        }

        return (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$this->table}`"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    /**
     * Update the status of a submission.
     */
    public function update_status(int $id, string $status): bool
    {
        global $wpdb;

        $result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $this->table,
            ['status' => sanitize_key($status)],
            ['id' => $id],
            ['%s'],
            ['%d']
        );

        return $result !== false;
    }

    // ------------------------------------------------------------------ //
    // GDPR — Privacy export / erase
    // ------------------------------------------------------------------ //

    /**
     * Export submissions matching an email hash.
     *
     * @return array<int, array<string, mixed>>
     */
    public function find_by_email_hash(string $email_hash): array
    {
        global $wpdb;

        $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "SELECT `id`, `form_id`, `submitted_at`, `email`, `payload_plaintext`, `status` FROM `{$this->table}` WHERE `email_hash` = %s ORDER BY `submitted_at` DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $email_hash
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * Erase all PII for submissions matching an email hash.
     * Sets email and payload to null; keeps the row for audit integrity.
     */
    public function erase_by_email_hash(string $email_hash): int
    {
        global $wpdb;

        $result = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "UPDATE `{$this->table}` SET `email` = NULL, `email_hash` = NULL, `ip_hash` = NULL, `user_agent_hash` = NULL, `payload_plaintext` = NULL, `consent_text_snapshot` = NULL, `status` = 'erased' WHERE `email_hash` = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $email_hash
            )
        );

        return is_int($result) ? $result : 0;
    }
}
