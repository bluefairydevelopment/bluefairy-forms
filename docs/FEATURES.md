# BlueFairy Forms — Features

## Lite (1.0.0)

### Core

- **F1 Form storage** — Custom `bffrm_forms` table with JSON config, slug, status, DATETIME(6) timestamps.
- **F2 Gutenberg block** — `bluefairy/forms` block with `apiVersion: 3`, server-side render, `editorScript` in block.json, style auto-loaded by block.json.
- **F3 Native validation** — HTML5 `required`, `pattern`, `minlength`, `maxlength`, `type` attributes generated server-side; no JavaScript validator.
- **F4 Submission handler** — `admin_post_bffrm_submit` / `admin_post_nopriv_bffrm_submit`. All errors redirect (no wp_die, no HTTP 4xx).
- **F5 Spam protection** — Delegates to BlueFairy Anti-Spam when active; falls back to self-contained honeypot + timetrap.
- **F6 GDPR-safe IP storage** — HMAC-SHA256 with own per-site key (not wp_salt). WP privacy export and erase hooks registered.
- **F7 Admin submissions list** — WP_List_Table, 50 rows/page, filterable by form, with detail view.
- **F8 Settings page** — Tools > BlueFairy Forms > Settings. Single `bffrm_settings` option (autoloaded).
- **Shortcode** — `[bluefairy_form id="N"]` with style enqueued inside callback.
- **WP-CLI** — `wp bluefairy-forms list-forms` and `wp bluefairy-forms doctor`.
- **Rate limiting** — DB-backed atomic `INSERT … ON DUPLICATE KEY UPDATE`. Default: 5/min/IP/form.
- **Email notification** — Synchronous `wp_mail()` to notification_email on each new submission.
- **Multisite** — Tables created per-site via `wp_initialize_site` hook.
- **Audit log** — `bffrm_audit_log` table for form create/update/delete events.
- **Default form** — "Contact" form (name, email, message) seeded on activation.
- **CodeMirror editor** — WP bundled CodeMirror used for JSON form config editing (no extra assets).

### Critical fixes incorporated

- **C2** — All timestamp columns use `DATETIME(6)` for microsecond resolution.
- **C3** — Audit log table created.
- **C6** — `idx_form_status_submitted` covers `(form_id, status, submitted_at)`.
- **C7** — `wp_verify_nonce()` used directly; nonce fail redirects, never `wp_die()`.
- **C8** — `dbDelta()` SQL strings use exactly 2-space indentation per column/key line.
- **C9** — All form errors are redirects with query-string error codes.
- **C10** — Style declared in `block.json`; shortcode enqueues it manually in callback.
- **C11** — Own HMAC key `bffrm_ip_hmac_key` generated with `bin2hex(random_bytes(32))` on activation; never `wp_salt()`.
- **C15** — `uninstall.php` guard: `if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }`.
- **C16** — `payload_plaintext` is `MEDIUMTEXT`; submissions table uses `ROW_FORMAT=DYNAMIC`.
- **M1** — Form fetched from DB before nonce check; missing form redirects identically to nonce fail.
- **M2** — `sanitize_bffrm_settings()` implemented inline with per-field type enforcement.
- **M3** — Rate limiter uses `INSERT … ON DUPLICATE KEY UPDATE count = count + 1` (atomic, no TOCTOU).
- **M5** — Timetrap threshold is `min_time_seconds` setting (default 3, range 1–30).
- **M6** — `wp_mail()` called synchronously in submission handler.
- **M10** — `$wpdb->prefix` used throughout; `wp_initialize_site` hook for multisite.
- **M12** — `editorScript: "file:./index.js"` in block.json; no `enqueue_block_editor_assets`.

## Available in BlueFairy Forms Pro

Shipped in bluefairy-forms-pro 1.0.4. Pro is an add-on: it runs alongside this
plugin and needs it to stay active.

- **F1** — Streamed CSV export, protected against formula injection
- **F2** — HTML confirmation email with `{{fieldid}}` placeholders in the body
- **F3** — Webhooks with HMAC-SHA256 signature and exponential-backoff retry
- **F4** — Dashboard widget with submission stats
- **F5** — Form scheduling (open/close dates)
- **F6** — Submission limits per form
- **F7** — Custom From name and address, with header-injection protection
- **F8** — Multiple recipients, with conditional CC routing (`notify_if`)
- **F9** — Encryption at rest (libsodium XSalsa20-Poly1305), with key rotation
- **F10** — File uploads with MIME validation and a private upload directory
- **F12** — CAPTCHA: reCAPTCHA v3, hCaptcha, Cloudflare Turnstile

## Pro (planned)

Genuinely unbuilt. Half of the previous list under this heading had already
shipped, which made the heading useless for telling the two apart — and three
of these were being advertised on the settings page as though they existed.

- Conditional logic at the FIELD level — show/hide fields on another field's
  value. Note the notification side of this already ships as `notify_if`, so
  the config vocabulary exists.
- Multi-step forms
- Email integrations (Mailchimp, etc.)
- REST API endpoint — no `register_rest_route` call exists today
- Drag-and-drop React form builder
- Payment integration
