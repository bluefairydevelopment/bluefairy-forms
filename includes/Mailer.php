<?php
declare(strict_types=1);

namespace BlueFairy\Forms;

/**
 * Sends email notifications synchronously via wp_mail().
 */
class Mailer
{
    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Send the admin notification for a new submission.
     *
     * @param array<string, mixed> $form        The form row from bffrm_forms.
     * @param array<string, mixed> $payload     Sanitized POST fields.
     * @param int                  $submission_id
     */
    public function notify_admin(array $form, array $payload, int $submission_id): void
    {
        $to = (string) $this->settings->get('notification_email', get_option('admin_email'));

        $recipients = apply_filters( 'bffrm_notification_recipients', [ $to ], (int) ( $form['id'] ?? 0 ), $form );
        $to = implode( ', ', array_filter( array_map( 'sanitize_email', (array) $recipients ) ) );

        if (empty($to)) {
            return;
        }

        $form_title = esc_html((string) ($form['title'] ?? 'Unknown Form'));

        $subject = sprintf(
            // translators: %1$s is the site name, %2$s is the form title.
            __('[%1$s] New form submission: %2$s', 'bluefairy-forms-lite'),
            get_bloginfo('name'),
            $form_title
        );

        $body  = sprintf(
            /* translators: 1: form title, 2: submission ID */
            __("A new submission was received for form \"%1\$s\" (ID: #%2\$d).\n\n", 'bluefairy-forms-lite'),
            $form_title,
            $submission_id
        );

        $body .= __("--- Submission Details ---\n", 'bluefairy-forms-lite');

        foreach ($payload as $key => $value) {
            $body .= sanitize_text_field($key) . ': ' . sanitize_text_field((string) $value) . "\n";
        }

        $admin_url = admin_url('tools.php?page=bffrm-submissions&submission_id=' . $submission_id);
        $body     .= "\n" . sprintf(
            /* translators: %s: admin URL */
            __("View in admin: %s\n", 'bluefairy-forms-lite'),
            $admin_url
        );

        $headers = ['Content-Type: text/plain; charset=UTF-8'];

        wp_mail($to, $subject, $body, $headers);
    }
}
