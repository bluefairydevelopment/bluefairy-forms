<?php
declare(strict_types=1);

namespace BlueFairy\Forms;

/**
 * DB-backed atomic rate limiter.
 *
 * Uses INSERT … ON DUPLICATE KEY UPDATE to atomically increment the counter,
 * avoiding the TOCTOU race condition that transients have.
 *
 * Window: 1 minute. Limit: configurable (default 5).
 */
class RateLimiter
{
    private string $table;
    private int $max_per_window;

    public function __construct(int $max_per_window = 5)
    {
        global $wpdb;
        $this->table          = $wpdb->prefix . 'bffrm_rate_limits';
        $this->max_per_window = $max_per_window;
    }

    /**
     * Attempt to record a hit and check whether the limit is exceeded.
     *
     * Returns true when the rate limit is exceeded (i.e. this submission should be blocked).
     * Returns false when the submission is allowed.
     *
     * @param string $ip_hash   HMAC-hashed IP address.
     * @param int    $form_id   The form being submitted.
     */
    public function is_limited(string $ip_hash, int $form_id): bool
    {
        global $wpdb;

        // Window key: current minute (UTC, 14-char string: YYYYMMDDHHII).
        $window_key = gmdate('YmdHi');
        $expires_at = gmdate('Y-m-d H:i:s', strtotime('+1 minute'));

        // Atomic upsert — increments count if the row already exists,
        // inserts with count=1 otherwise.
        $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "INSERT INTO `{$this->table}` (`ip_hash`, `form_id`, `window_key`, `count`, `expires_at`) VALUES (%s, %d, %s, 1, %s) ON DUPLICATE KEY UPDATE `count` = `count` + 1, `expires_at` = VALUES(`expires_at`)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $ip_hash,
                $form_id,
                $window_key,
                $expires_at
            )
        );

        // Read back the current count for this window.
        $count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
            $wpdb->prepare(
                "SELECT `count` FROM `{$this->table}` WHERE `ip_hash` = %s AND `form_id` = %d AND `window_key` = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                $ip_hash,
                $form_id,
                $window_key
            )
        );

        // Prune expired rows occasionally (1% of requests) to keep the table small.
        if (wp_rand(1, 100) === 1) {
            $this->prune_expired();
        }

        return $count > $this->max_per_window;
    }

    /**
     * Delete expired rate-limit rows.
     */
    private function prune_expired(): void
    {
        global $wpdb;

        $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.NotPrepared
            "DELETE FROM `{$this->table}` WHERE `expires_at` < UTC_TIMESTAMP() LIMIT 500" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        );
    }
}
