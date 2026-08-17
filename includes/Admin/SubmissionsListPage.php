<?php
declare(strict_types=1);

namespace BlueFairy\Forms\Admin;

use BlueFairy\Forms\FormRepository;
use BlueFairy\Forms\SubmissionRepository;

/**
 * Tools > BlueFairy Forms — Submissions list page.
 */
class SubmissionsListPage
{
    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'bluefairy-forms'));
        }

        $form_id = isset($_GET['form_id']) ? (int) $_GET['form_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification

        // Check if viewing a single submission.
        if (! empty($_GET['submission_id'])) { // phpcs:ignore WordPress.Security.NonceVerification
            $this->render_detail((int) $_GET['submission_id']); // phpcs:ignore WordPress.Security.NonceVerification
            return;
        }

        $sub_repo = new SubmissionRepository();
        $table    = new SubmissionsListTable($sub_repo, $form_id);
        $table->prepare_items();

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Form Submissions', 'bluefairy-forms'); ?></h1>

            <?php if ($form_id > 0): ?>
                <?php
                $form_repo = new FormRepository();
                $form      = $form_repo->find($form_id);
                if ($form):
                ?>
                <p>
                    <?php
                    printf(
                        /* translators: %s: form title */
                        esc_html__('Showing submissions for: %s', 'bluefairy-forms'),
                        '<strong>' . esc_html((string) $form['title']) . '</strong>'
                    );
                    ?>
                    &nbsp; <a href="<?php echo esc_url(admin_url('tools.php?page=bffrm-submissions')); ?>"><?php esc_html_e('View all', 'bluefairy-forms'); ?></a>
                </p>
                <?php endif; ?>
            <?php endif; ?>

            <?php $table->display(); ?>
        </div>
        <?php
    }

    private function render_detail(int $submission_id): void
    {
        $repo       = new SubmissionRepository();
        $submission = $repo->find($submission_id);

        if (null === $submission) {
            echo '<div class="wrap"><p>' . esc_html__('Submission not found.', 'bluefairy-forms') . '</p></div>';
            return;
        }

        $back_url = admin_url('tools.php?page=bffrm-submissions');
        $payload  = [];

        if (! empty($submission['payload_plaintext'])) {
            $decoded = json_decode((string) $submission['payload_plaintext'], true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        ?>
        <div class="wrap">
            <h1>
                <?php
                printf(
                    /* translators: %d: submission ID */
                    esc_html__('Submission #%d', 'bluefairy-forms'),
                    (int) $submission['id']
                );
                ?>
            </h1>
            <p><a href="<?php echo esc_url($back_url); ?>">&larr; <?php esc_html_e('Back to submissions', 'bluefairy-forms'); ?></a></p>

            <table class="widefat striped">
                <tbody>
                    <tr>
                        <th><?php esc_html_e('Form ID', 'bluefairy-forms'); ?></th>
                        <td><?php echo (int) $submission['form_id']; ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Submitted At', 'bluefairy-forms'); ?></th>
                        <td><?php echo esc_html((string) $submission['submitted_at']); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Email', 'bluefairy-forms'); ?></th>
                        <td><?php echo esc_html((string) ($submission['email'] ?? '—')); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Status', 'bluefairy-forms'); ?></th>
                        <td><?php echo esc_html((string) ($submission['status'] ?? '')); ?></td>
                    </tr>
                    <?php foreach ($payload as $key => $val): ?>
                    <tr>
                        <th><?php echo esc_html(sanitize_text_field($key)); ?></th>
                        <td><?php echo esc_html(sanitize_text_field((string) $val)); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (! empty($submission['consent_text_snapshot'])): ?>
                    <tr>
                        <th><?php esc_html_e('Consent Text', 'bluefairy-forms'); ?></th>
                        <td><?php echo esc_html((string) $submission['consent_text_snapshot']); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Consent Given At', 'bluefairy-forms'); ?></th>
                        <td><?php echo esc_html((string) ($submission['consent_given_at'] ?? '—')); ?></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
