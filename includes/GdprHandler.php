<?php
declare(strict_types=1);

namespace BlueFairy\Forms;

/**
 * Registers WP privacy exporters and erasers for GDPR compliance.
 */
class GdprHandler
{
    /**
     * Register the data exporter.
     *
     * @param array<int, array<string, mixed>> $exporters
     * @return array<int, array<string, mixed>>
     */
    public function register_exporter(array $exporters): array
    {
        $exporters[] = [
            'exporter_friendly_name' => __('BlueFairy Forms Submissions', 'bluefairy-forms-lite'),
            'callback'               => [$this, 'export_user_data'],
        ];
        return $exporters;
    }

    /**
     * Register the data eraser.
     *
     * @param array<int, array<string, mixed>> $erasers
     * @return array<int, array<string, mixed>>
     */
    public function register_eraser(array $erasers): array
    {
        $erasers[] = [
            'eraser_friendly_name' => __('BlueFairy Forms Submissions', 'bluefairy-forms-lite'),
            'callback'             => [$this, 'erase_user_data'],
        ];
        return $erasers;
    }

    /**
     * Export form submissions for the given email address.
     *
     * @param string $email_address
     * @param int    $page
     * @return array{data: array<int, array<string, mixed>>, done: bool}
     */
    public function export_user_data(string $email_address, int $page = 1): array
    {
        $settings   = new Settings();
        $hmac_key   = $settings->get_ip_hmac_key();
        $email_hash = $hmac_key !== ''
            ? hash_hmac('sha256', strtolower(trim($email_address)), $hmac_key)
            : hash('sha256', strtolower(trim($email_address)));

        $repo        = new SubmissionRepository();
        $submissions = $repo->find_by_email_hash($email_hash);

        $export_items = [];

        foreach ($submissions as $sub) {
            $payload = [];
            if (! empty($sub['payload_plaintext'])) {
                $decoded = json_decode((string) $sub['payload_plaintext'], true);
                if (is_array($decoded)) {
                    foreach ($decoded as $key => $val) {
                        $payload[] = [
                            'name'  => esc_html(sanitize_text_field($key)),
                            'value' => esc_html(sanitize_text_field((string) $val)),
                        ];
                    }
                }
            }

            $export_items[] = [
                'group_id'          => 'bffrm_submissions',
                'group_label'       => __('BlueFairy Forms Submissions', 'bluefairy-forms-lite'),
                'item_id'           => 'bffrm-submission-' . (int) $sub['id'],
                'data'              => array_merge(
                    [
                        [
                            'name'  => __('Form ID', 'bluefairy-forms-lite'),
                            'value' => (int) $sub['form_id'],
                        ],
                        [
                            'name'  => __('Submitted At', 'bluefairy-forms-lite'),
                            'value' => esc_html((string) $sub['submitted_at']),
                        ],
                        [
                            'name'  => __('Email', 'bluefairy-forms-lite'),
                            'value' => esc_html((string) ($sub['email'] ?? '')),
                        ],
                    ],
                    $payload
                ),
            ];
        }

        return [
            'data' => $export_items,
            'done' => true,
        ];
    }

    /**
     * Erase form submissions for the given email address.
     *
     * @param string $email_address
     * @param int    $page
     * @return array{items_removed: int, items_retained: int, messages: string[], done: bool}
     */
    public function erase_user_data(string $email_address, int $page = 1): array
    {
        $settings   = new Settings();
        $hmac_key   = $settings->get_ip_hmac_key();
        $email_hash = $hmac_key !== ''
            ? hash_hmac('sha256', strtolower(trim($email_address)), $hmac_key)
            : hash('sha256', strtolower(trim($email_address)));

        $repo    = new SubmissionRepository();
        $removed = $repo->erase_by_email_hash($email_hash);

        return [
            'items_removed'  => $removed,
            'items_retained' => 0,
            'messages'       => $removed > 0
                ? [sprintf(
                    /* translators: %d: number of submissions erased */
                    __('%d form submission(s) anonymised.', 'bluefairy-forms-lite'),
                    $removed
                )]
                : [],
            'done'           => true,
        ];
    }
}
