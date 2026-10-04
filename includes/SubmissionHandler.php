<?php
declare(strict_types=1);

namespace BlueFairy\Forms;

/**
 * Handles form submissions posted to admin-post.php.
 *
 * All error paths redirect — never wp_die(), never HTTP 4xx.
 *
 * Error codes:
 *   nonce_fail   — nonce verification failed or form not found
 *   rate_limit   — IP exceeded rate limit
 *   spam         — honeypot, time trap (missing, altered, too fast) or Anti-Spam
 *   expired      — the time token is more than a day old: the page was open
 *                  that long, or a page cache served it (WPMAP-152). Not spam;
 *                  the visitor is asked to send again.
 *   validation   — server-side field validation failed
 *   consent      — the form asks for consent and the box was not ticked
 *   closed       — Settings > Enable Forms is off
 *   success      — bffrm_success=1 appended to redirect URL
 *
 * Every refusal is recorded (WPMAP-152): audit-log action
 * "submission.rejected" with the form id and a reason code, no field values
 * and no address. Until 1.1.9 nothing was, so a site could not see that a
 * cached form page was losing every enquiry.
 */
class SubmissionHandler
{
    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Refuse the submission: record why, then send the visitor back with the
     * error code. Records the form id and the reason only (no field values,
     * no address, no IP) as audit-log action "submission.rejected"; the
     * Submissions screen lists them and the audit-log retention prunes them.
     *
     * @param array<string, string> $extra More non-personal context (a field id).
     */
    protected function reject(string $code, string $reason, int $form_id, string $url, array $extra = []): never
    {
        ( new AuditLogger() )->log(
            'submission.rejected',
            array_merge(['form_id' => $form_id, 'reason' => $reason], $extra),
            null,
            'form',
            $form_id > 0 ? $form_id : null
        );

        wp_safe_redirect(add_query_arg('bffrm_error', $code, $url));
        exit;
    }

    /**
     * Main handler — hooked to admin_post_bffrm_submit and admin_post_nopriv_bffrm_submit.
     */
    public function handle(): void
    {
        // ------------------------------------------------------------------ //
        // Determine redirect target from HTTP_REFERER.
        // ------------------------------------------------------------------ //
        $referer = wp_get_referer();
        if (! $referer) {
            $referer = home_url('/');
        }
        // wp_validate_redirect ensures we only redirect to our own domain.
        $safe_referer = wp_validate_redirect($referer, home_url('/'));

        // ------------------------------------------------------------------ //
        // M1 — Fetch the form BEFORE nonce check (same timing, safe redirect).
        // ------------------------------------------------------------------ //
        $form_id = isset($_POST['bffrm_form_id']) ? (int) $_POST['bffrm_form_id'] : 0;

        if ($form_id < 1) {
            $this->reject('nonce_fail', 'form_missing', 0, $safe_referer);
        }

        $repo = new FormRepository();
        $form = $repo->find($form_id);

        if (null === $form) {
            // C7/M1: form not found — same redirect as nonce fail, no timing difference.
            $this->reject('nonce_fail', 'form_missing', $form_id, $safe_referer);
        }

        // "Enable Forms — Accept new form submissions" was saved and never read
        // until 1.1.9: unticking it changed nothing.
        if (! (bool) $this->settings->get('enabled', true)) {
            $this->reject('closed', 'closed', $form_id, $safe_referer);
        }

        // ------------------------------------------------------------------ //
        // C7 — Nonce verification via wp_verify_nonce(), NOT check_admin_referer().
        // ------------------------------------------------------------------ //
        $nonce = isset($_POST['bffrm_nonce']) ? wp_unslash( (string) $_POST['bffrm_nonce'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce value, passed directly to wp_verify_nonce()

        if (! wp_verify_nonce($nonce, 'bffrm_submit_' . $form_id)) {
            $this->reject('nonce_fail', '' === $nonce ? 'nonce_missing' : 'nonce', $form_id, $safe_referer);
        }

        // ------------------------------------------------------------------ //
        // Gather contextual data.
        // ------------------------------------------------------------------ //
        $ip    = ClientIp::current($this->settings);
        $email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( (string) $_POST['email'] ) ) : '';

        $hmac_key = $this->settings->get_ip_hmac_key();
        $ip_hash  = $hmac_key !== ''
            ? hash_hmac('sha256', $ip, $hmac_key)
            : hash('sha256', $ip);

        $ua      = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_USER_AGENT'] ) ) : '';
        $ua_hash = $hmac_key !== ''
            ? hash_hmac('sha256', $ua, $hmac_key)
            : hash('sha256', $ua);

        $email_hash = ($email !== '' && $hmac_key !== '')
            ? hash_hmac('sha256', strtolower($email), $hmac_key)
            : null;

        // ------------------------------------------------------------------ //
        // Rate limiting (M3 — atomic DB-backed).
        // ------------------------------------------------------------------ //
        $max_hits    = (int) $this->settings->get('rate_limit_max', 5);
        $rate_limiter = new RateLimiter($max_hits);

        if ($rate_limiter->is_limited($ip_hash, $form_id)) {
            $this->reject('rate_limit', 'rate_limit', $form_id, $safe_referer);
        }

        // ------------------------------------------------------------------ //
        // Spam check (F5).
        // ------------------------------------------------------------------ //
        $spam_checker = new SpamChecker($this->settings);
        $form_type    = 'bffrm_' . $form_id;

        $spam_reason  = $spam_checker->reason($_POST, $form_type, $ip, $email);
        if ('timetrap_expired' === $spam_reason) {
            // Not spam: the page was loaded more than a day ago (a page cache
            // serves the token it cached). The visitor is told to send again;
            // the reloaded form is fresh (Renderer marks it uncacheable).
            $this->reject('expired', $spam_reason, $form_id, $safe_referer);
        }
        if ('' !== $spam_reason) {
            $this->reject('spam', $spam_reason, $form_id, $safe_referer);
        }

        // ------------------------------------------------------------------ //
        // Server-side field validation.
        // ------------------------------------------------------------------ //
        $config = json_decode((string) $form['config_json'], true);
        if (! is_array($config)) {
            $this->reject('nonce_fail', 'config', $form_id, $safe_referer);
        }

        $validator = new Validator();
        $validation = $validator->validate($_POST, $config);

        if (! $validation['valid']) {
            $this->reject('validation', 'validation', $form_id, add_query_arg('field', $validation['error_field'], $safe_referer), ['field' => sanitize_key((string) $validation['error_field'])]);
        }

        // Consent. The checkbox is rendered `required`, but the form carries
        // novalidate and the server only recorded the answer, so a submission
        // without consent was accepted and stored (until 1.1.9).
        if (! empty($config['consent_text']) && empty($_POST['bffrm_consent'])) {
            $this->reject('consent', 'consent', $form_id, $safe_referer);
        }

        // Allow Pro to block submission before storage (e.g., form closed, submission limit reached).
        $block_error = apply_filters( 'bffrm_pre_submit', '', $form_id, $form, $_POST );
        if ( '' !== $block_error ) {
            $code = sanitize_key( (string) $block_error );
            $this->reject( $code, 'addon:' . $code, $form_id, $safe_referer );
        }

        // ------------------------------------------------------------------ //
        // Sanitize and collect payload.
        // ------------------------------------------------------------------ //
        $fields  = $config['fields'] ?? [];
        $payload = [];

        foreach ($fields as $field_def) {
            $field_id = sanitize_key((string) ($field_def['id'] ?? ''));
            if ($field_id === '') {
                continue;
            }
            $raw   = isset( $_POST[ $field_id ] ) ? wp_unslash( (string) $_POST[ $field_id ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized via sanitize_textarea_field() on next line
            $value = sanitize_textarea_field( $raw );

            if ($value !== '') {
                $payload[$field_id] = $value;
            }
        }

        // Consent snapshot.
        $consent_given   = ! empty($_POST['bffrm_consent']);
        $consent_text    = $consent_given && ! empty($config['consent_text'])
            ? wp_strip_all_tags((string) $config['consent_text'])
            : null;
        $consent_given_at = $consent_given ? current_time('mysql', true) : null;

        // ------------------------------------------------------------------ //
        // Persist submission.
        // ------------------------------------------------------------------ //
        $sub_repo = new SubmissionRepository();

        /**
         * Filter the payload immediately before it is persisted.
         *
         * Exists so add-ons can attach data they collected during validation —
         * Pro's file uploads are the motivating case. Before this filter, Pro
         * moved uploaded files to disk and recorded their metadata in
         * $GLOBALS['bffrmp_uploaded_files'], which nothing ever read: the file
         * was stored, the submission never referenced it, and there was no route
         * to retrieve it. Uploads accumulated on disk, orphaned.
         *
         * Passing data through a filter rather than a superglobal also removes
         * the ordering dependency that made that bug possible.
         *
         * @param array $payload      Sanitised field values, keyed by field id.
         * @param int   $form_id      Form being submitted.
         * @param array $form         Form row.
         */
        $payload = (array) apply_filters( 'bffrm_submission_payload', $payload, $form_id, $form );

        $submission_id = $sub_repo->insert([
            'form_id'               => $form_id,
            'email'                 => $email ?: null,
            'email_hash'            => $email_hash,
            'ip_hash'               => $ip_hash,
            'user_agent_hash'       => $ua_hash ?: null,
            'user_id'               => is_user_logged_in() ? get_current_user_id() : null,
            'status'                => 'new',
            'payload_plaintext'     => ! empty($payload) ? wp_json_encode($payload) : null,
            'consent_text_snapshot' => $consent_text,
            'consent_given_at'      => $consent_given_at,
        ]);

        // ------------------------------------------------------------------ //
        // Audit log.
        // ------------------------------------------------------------------ //
        $audit = new AuditLogger();
        $audit->log(
            'submission.created',
            ['form_id' => $form_id, 'submission_id' => $submission_id],
            null,
            'submission',
            $submission_id
        );

        // Extension API — Pro hooks here for webhooks, confirmation email, integrations.
        do_action( 'bffrm_submission_created', $form_id, $submission_id, $form, $payload );

        // ------------------------------------------------------------------ //
        // Email notification (M6 — synchronous).
        // ------------------------------------------------------------------ //
        $mailer = new Mailer($this->settings);
        // The payload is passed to notify_admin() directly. The superglobal is
        // retained ONLY so an add-on written against the old contract keeps
        // working; new code should read the $payload argument on the
        // bffrm_notification_recipients filter instead.
        $GLOBALS['bffrmp_current_payload'] = $payload;
        $mailer->notify_admin($form, $payload, $submission_id);
        unset( $GLOBALS['bffrmp_current_payload'] );

        // ------------------------------------------------------------------ //
        // Redirect to success URL.
        // ------------------------------------------------------------------ //
        $success_url = ! empty($config['redirect_url'])
            ? wp_validate_redirect(
                esc_url_raw( (string) $config['redirect_url'] ),
                add_query_arg( 'bffrm_success', '1', $safe_referer )
            )
            : add_query_arg( 'bffrm_success', '1', $safe_referer );

        wp_safe_redirect($success_url);
        exit;
    }

}
