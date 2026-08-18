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
 *   spam         — honeypot or timetrap triggered
 *   validation   — server-side field validation failed
 *   success      — bffrm_success=1 appended to redirect URL
 */
class SubmissionHandler
{
    private Settings $settings;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
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
            wp_safe_redirect(add_query_arg('bffrm_error', 'nonce_fail', $safe_referer));
            exit;
        }

        $repo = new FormRepository();
        $form = $repo->find($form_id);

        if (null === $form) {
            // C7/M1: form not found — same redirect as nonce fail, no timing difference.
            wp_safe_redirect(add_query_arg('bffrm_error', 'nonce_fail', $safe_referer));
            exit;
        }

        // ------------------------------------------------------------------ //
        // C7 — Nonce verification via wp_verify_nonce(), NOT check_admin_referer().
        // ------------------------------------------------------------------ //
        $nonce = isset($_POST['bffrm_nonce']) ? (string) $_POST['bffrm_nonce'] : '';

        if (! wp_verify_nonce($nonce, 'bffrm_submit_' . $form_id)) {
            wp_safe_redirect(add_query_arg('bffrm_error', 'nonce_fail', $safe_referer));
            exit;
        }

        // ------------------------------------------------------------------ //
        // Gather contextual data.
        // ------------------------------------------------------------------ //
        $ip    = $this->get_client_ip();
        $email = isset($_POST['email']) ? sanitize_email((string) $_POST['email']) : '';

        $hmac_key = $this->settings->get_ip_hmac_key();
        $ip_hash  = $hmac_key !== ''
            ? hash_hmac('sha256', $ip, $hmac_key)
            : hash('sha256', $ip);

        $ua      = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
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
            wp_safe_redirect(add_query_arg('bffrm_error', 'rate_limit', $safe_referer));
            exit;
        }

        // ------------------------------------------------------------------ //
        // Spam check (F5).
        // ------------------------------------------------------------------ //
        $spam_checker = new SpamChecker($this->settings);
        $form_type    = 'bffrm_' . $form_id;

        if ($spam_checker->is_spam($_POST, $form_type, $ip, $email)) {
            wp_safe_redirect(add_query_arg('bffrm_error', 'spam', $safe_referer));
            exit;
        }

        // ------------------------------------------------------------------ //
        // Server-side field validation.
        // ------------------------------------------------------------------ //
        $config = json_decode((string) $form['config_json'], true);
        if (! is_array($config)) {
            wp_safe_redirect(add_query_arg('bffrm_error', 'nonce_fail', $safe_referer));
            exit;
        }

        $validator = new Validator();
        $validation = $validator->validate($_POST, $config);

        if (! $validation['valid']) {
            $redirect = add_query_arg([
                'bffrm_error' => 'validation',
                'field'       => $validation['error_field'],
            ], $safe_referer);
            wp_safe_redirect($redirect);
            exit;
        }

        // Allow Pro to block submission before storage (e.g., form closed, submission limit reached).
        $block_error = apply_filters( 'bffrm_pre_submit', '', $form_id, $form, $_POST );
        if ( '' !== $block_error ) {
            wp_safe_redirect( add_query_arg( 'bffrm_error', sanitize_key( (string) $block_error ), $safe_referer ) );
            exit;
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
            $raw   = isset($_POST[$field_id]) ? (string) $_POST[$field_id] : '';
            $value = sanitize_textarea_field($raw);

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
        $mailer->notify_admin($form, $payload, $submission_id);

        // ------------------------------------------------------------------ //
        // Redirect to success URL.
        // ------------------------------------------------------------------ //
        $success_url = ! empty($config['redirect_url'])
            ? esc_url_raw($config['redirect_url'])
            : add_query_arg('bffrm_success', '1', $safe_referer);

        wp_safe_redirect($success_url);
        exit;
    }

    /**
     * Retrieve the real client IP, respecting common reverse-proxy headers.
     */
    private function get_client_ip(): string
    {
        $candidates = [
            'HTTP_CF_CONNECTING_IP',  // Cloudflare.
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ($candidates as $key) {
            if (! empty($_SERVER[$key])) {
                $ip = trim(explode(',', (string) $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }
}
