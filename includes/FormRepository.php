<?php
declare(strict_types=1);

namespace BlueFairy\Forms;

/**
 * CRUD operations for the bffrm_forms table.
 */
class FormRepository
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'bffrm_forms';
    }

    /**
     * Find a form by its numeric ID.
     *
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "SELECT * FROM `{$this->table}` WHERE `id` = %d AND `status` = 'active' LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $id
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * Find a form by its slug.
     *
     * @return array<string, mixed>|null
     */
    public function find_by_slug(string $slug): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "SELECT * FROM `{$this->table}` WHERE `slug` = %s AND `status` = 'active' LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $slug
            ),
            ARRAY_A
        );

        return is_array($row) ? $row : null;
    }

    /**
     * Return all forms (for admin list).
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(int $limit = 200, int $offset = 0): array
    {
        global $wpdb;

        $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "SELECT `id`, `slug`, `title`, `status`, `created_at`, `updated_at` FROM `{$this->table}` ORDER BY `id` DESC LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $limit,
                $offset
            ),
            ARRAY_A
        );

        return is_array($rows) ? $rows : [];
    }

    /**
     * Total count of all forms.
     */
    public function count(): int
    {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$this->table}`"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    /**
     * Insert a new form row.
     *
     * @param array<string, mixed> $data
     */
    public function insert(array $data): int
    {
        global $wpdb;

        $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $this->table,
            [
                'slug'        => sanitize_key($data['slug'] ?? ''),
                'title'       => sanitize_text_field($data['title'] ?? ''),
                'config_json' => $data['config_json'] ?? '{}',
                'status'      => sanitize_key($data['status'] ?? 'active'),
            ],
            ['%s', '%s', '%s', '%s']
        );

        return (int) $wpdb->insert_id;
    }

    /**
     * Update an existing form row.
     *
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): bool
    {
        global $wpdb;

        $update = [];
        $format = [];

        if (array_key_exists('title', $data)) {
            $update['title'] = sanitize_text_field($data['title']);
            $format[]        = '%s';
        }
        if (array_key_exists('config_json', $data)) {
            $update['config_json'] = $data['config_json'];
            $format[]              = '%s';
        }
        if (array_key_exists('status', $data)) {
            $update['status'] = sanitize_key($data['status']);
            $format[]         = '%s';
        }

        if (empty($update)) {
            return false;
        }

        $result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $this->table,
            $update,
            ['id' => $id],
            $format,
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Soft-delete a form by setting status to 'deleted'.
     */
    public function delete(int $id): bool
    {
        global $wpdb;

        $result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $this->table,
            ['status' => 'deleted'],
            ['id' => $id],
            ['%s'],
            ['%d']
        );

        return $result !== false;
    }
}
