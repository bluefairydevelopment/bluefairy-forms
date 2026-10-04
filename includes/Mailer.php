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
            __('[%1$s] New form submission: %2$s', 'bluefairy-forms'),
            get_bloginfo('name'),
            $form_title
        );

        $body  = sprintf(
            /* translators: 1: form title, 2: submission ID */
            __("A new submission was received for form \"%1\$s\" (ID: #%2\$d).\n\n", 'bluefairy-forms'),
            $form_title,
            $submission_id
        );

        $body .= __("--- Submission Details ---\n", 'bluefairy-forms');

        foreach ($payload as $key => $value) {
            // PayloadValue, not (string): an add-on's structured value (a Forms
            // Pro upload) printed "Array" with a PHP warning (WPMAP-147).
            $body .= sanitize_text_field((string) $key) . ': ' . sanitize_text_field(PayloadValue::format($value)) . "\n";
        }

        $admin_url = admin_url('tools.php?page=bffrm-submissions&submission_id=' . $submission_id);
        $body     .= "\n" . sprintf(
            /* translators: %s: admin URL */
            __("View in admin: %s\n", 'bluefairy-forms'),
            $admin_url
        );

        $headers = ['Content-Type: text/plain; charset=UTF-8'];

        // Reply goes to the visitor (WPMAP-151). Until 1.1.9 only the
        // Content-Type was sent, so replying to a notification wrote to the
        // site itself.
        $reply_to = self::reply_to($payload);
        if ('' !== $reply_to) {
            $headers[] = $reply_to;
        }

        /**
         * Filter the admin notification's headers.
         *
         * @param string[]             $headers    "Name: value" lines.
         * @param array<string, mixed> $form       The form row.
         * @param array<string, mixed> $submission ['id' => int, 'payload' => array].
         */
        $headers = (array) apply_filters('bffrm_notification_headers', $headers, $form, ['id' => $submission_id, 'payload' => $payload]);

        wp_mail($to, $subject, $body, $headers);
    }

    /**
     * "Reply-To: Name <address>" from the submission's email field (id
     * "email", as the privacy tools use) and its name (id "name", else
     * "first_name" and "last_name"); "" when there is no valid address.
     *
     * Header-safe: the address must pass is_email(), whose allowed
     * characters exclude CR, LF, spaces, commas, quotes and < >. The name
     * loses control characters (CR, LF, tab: no header injection) and the
     * characters wp_mail() splits or parses on (, ; < > "), and is capped at
     * 100 characters. PHPMailer encodes a non-ASCII name.
     *
     * @param array<string, mixed> $payload
     */
    public static function reply_to(array $payload): string
    {
        $email = is_scalar($payload['email'] ?? null) ? trim((string) $payload['email']) : '';
        if ('' === $email || ! is_email($email)) {
            return '';
        }

        $name = is_scalar($payload['name'] ?? null) ? (string) $payload['name'] : '';
        if ('' === trim($name)) {
            $first = is_scalar($payload['first_name'] ?? null) ? (string) $payload['first_name'] : '';
            $last  = is_scalar($payload['last_name'] ?? null) ? (string) $payload['last_name'] : '';
            $name  = $first . ' ' . $last;
        }
        $name = (string) preg_replace('/[\x00-\x1F\x7F,;<>"]+/u', ' ', $name);
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));
        $name = function_exists('mb_substr') ? mb_substr($name, 0, 100) : substr($name, 0, 100);
        $name = trim($name);

        return '' !== $name ? 'Reply-To: ' . $name . ' <' . $email . '>' : 'Reply-To: ' . $email;
    }
}
