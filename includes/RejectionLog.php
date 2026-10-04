<?php
declare(strict_types=1);

namespace BlueFairy\Forms;

/**
 * Refused submissions, as SubmissionHandler records them (WPMAP-152): audit
 * log rows with action "submission.rejected", the form id and a reason code.
 * No field values and no addresses are stored. Kept as long as the audit log
 * (audit_retention_days, 90 by default; pruned daily).
 */
final class RejectionLog
{
    /**
     * The most recent refusals, newest first.
     *
     * @return array<int, array{at: string, form_id: int, reason: string, field: string}>
     */
    public static function recent(int $limit = 50, int $form_id = 0): array
    {
        global $wpdb;

        $table = esc_sql($wpdb->prefix . 'bffrm_audit_log');
        $where = $form_id > 0 ? $wpdb->prepare(' AND `subject_id` = %d', $form_id) : '';
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = (array) $wpdb->get_results($wpdb->prepare("SELECT `created_at`, `metadata` FROM `{$table}` WHERE `action` = 'submission.rejected'{$where} ORDER BY `id` DESC LIMIT %d", max(1, $limit)), ARRAY_A);

        $out = [];
        foreach ($rows as $row) {
            $meta  = json_decode((string) ($row['metadata'] ?? ''), true);
            $meta  = is_array($meta) ? $meta : [];
            $out[] = [
                'at'      => (string) ($row['created_at'] ?? ''),
                'form_id' => (int) ($meta['form_id'] ?? 0),
                'reason'  => (string) ($meta['reason'] ?? ''),
                'field'   => (string) ($meta['field'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * What a reason code means, for the admin screen.
     */
    public static function label(string $reason): string
    {
        if (str_starts_with($reason, 'addon:')) {
            /* translators: %s: the code an add-on (Forms Pro) gave */
            return sprintf(__('Refused by an add-on (%s)', 'bluefairy-forms'), substr($reason, 6));
        }

        return match ($reason) {
            'timetrap_expired'  => __('Page open over a day (or served from a cache): visitor asked to send again', 'bluefairy-forms'),
            'nonce'             => __('Expired or invalid security token: visitor asked to send again', 'bluefairy-forms'),
            'nonce_missing'     => __('No security token', 'bluefairy-forms'),
            'timetrap_fast'     => __('Spam: sent faster than the minimum time', 'bluefairy-forms'),
            'timetrap_missing'  => __('Spam: no time token', 'bluefairy-forms'),
            'timetrap_tampered' => __('Spam: altered time token', 'bluefairy-forms'),
            'honeypot'          => __('Spam: hidden field filled', 'bluefairy-forms'),
            'antispam'          => __('Spam: refused by BlueFairy Anti-Spam', 'bluefairy-forms'),
            'rate_limit'        => __('Too many submissions from one address', 'bluefairy-forms'),
            'validation'        => __('A field did not validate', 'bluefairy-forms'),
            'consent'           => __('Consent box not ticked', 'bluefairy-forms'),
            'closed'            => __('Forms switched off (Settings)', 'bluefairy-forms'),
            'form_missing'      => __('No such form', 'bluefairy-forms'),
            'config'            => __('The form\'s configuration is not valid', 'bluefairy-forms'),
            default             => $reason,
        };
    }
}
