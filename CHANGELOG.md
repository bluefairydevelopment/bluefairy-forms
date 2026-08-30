# Changelog

## [1.1.8] — 2026-08-30

### Added
- `bffrm_submissions_page_actions` action, fired above the submissions table so
  add-ons can render controls on that screen. Forms Pro's CSV export had a
  working `admin_post` handler and documentation telling the customer to click
  "Export CSV", but no way to put a button anywhere in the admin. The feature
  was reachable only from WP-CLI or a hand-built URL.

## [1.1.7] — 2026-08-30

### Fixed
- "Remove Data on Uninstall" left `bffrm_db_version` in the options table. The
  plugin creates three options and `uninstall.php` deleted two, while its own
  docblock promised it removed all plugin data. Caught by T-14-7 the first time
  that test was ever allowed to run — all seven uninstall tests had been skipped
  as "not reversible in an automated run", which stopped being true once the rig
  began restoring from a baseline dump.
- The Forms Pro notice on the settings page advertised "multi-step forms, file
  uploads, conditional logic, payment integration". Only file uploads exists;
  the other three appear nowhere in bluefairy-forms-pro. It now names only
  shipped features and states that Pro runs alongside this plugin.

### Changed
- The Pro notice and the plugins-list link are hidden when Forms Pro is already
  active, rather than offering it to people who have it.
- `docs/FEATURES.md` separates shipped Pro features from planned ones. Five
  items under "planned" had already shipped, which is where the false settings
  page claims came from.

## [1.1.6] — 2026-08-30

### Changed
- Renamed from BlueFairy Forms Lite to BlueFairy Forms (slug `bluefairy-forms`).
  The paid add-on is now BlueFairy Forms Pro. Nothing is called "Lite" any more.
- Compatibility: tested against WordPress 7.1.

### Fixed
- Database schema changes now run on plugin UPDATE, not only on activation.
  WordPress does not fire `register_activation_hook` on update, so new indexes
  and columns reached fresh installs and silently skipped every existing site.
  Guarded by `BFFRM_DB_VERSION` plus an `admin_init` check.
- Personal-data erasure could fail because `ip_hash` was not nullable. The
  column now accepts NULL, with a migration for existing installs.
- File-field validation, and a nonce-checked admin AJAX endpoint.

### Added
- `bffrm_submission_payload` filter, so add-ons can extend a submission payload
  before it is stored.
- Audit log retention: `idx_created_at`, an `audit_retention_days` setting and a
  daily prune. The callback is bound outside `is_admin()` — WP-Cron requests are
  not admin requests.
- An upgrade notice for BlueFairy Forms Pro on the settings page and in the
  plugins list row.

### Performance
- Indexes on the rate-limit expiry and audit-log timestamp columns.
- The submissions-by-date query rewritten from `DATE(submitted_at)` to a range,
  so it can use its index instead of scanning the table.

All notable changes to BlueFairy Forms are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
Versioning follows Semantic Versioning (Major.Minor.Patch).

## [1.1.5] - 2026-08-19

### Added
- Upgrade to Forms Pro banner on the Settings page.
- "Upgrade to Pro" action link in the Plugins screen plugin row.

## [1.1.4] - 2026-08-19

### Fixed
- `Validator`: `file` type fields were silently skipped during validation because `$_POST` values for file inputs are always empty. The validator now checks `$_FILES` directly, enforces `required` for file fields, and delegates MIME/size checks to the `bffrm_validate_field` filter used by Pro's `FileUploadHandler`.

### Added
- `Plugin`: `wp_ajax_bffrm_nonce` endpoint allows admin-authenticated callers to generate arbitrary WP nonces via AJAX.

## [1.1.2] - 2026-08-18

### Fixed
- `FormsListTable` and `SubmissionsListTable`: `namespace` declaration appeared after the `ABSPATH` guard, violating PHP's rule that namespace must be the first statement (or after `declare`). Both files now declare namespace immediately after `declare(strict_types=1)`, with the ABSPATH guard moved below the `use` imports.

## [1.1.1] - 2026-08-18

### Fixed
- `bffrm_plugin()` helper moved to global scope (was accidentally placed in `BlueFairy\Forms` namespace, causing BlueFairy Forms Pro to always display the "Lite not found" admin notice even when Lite was active).
- Plugin Check (PCP) compliance: output escaping in `Renderer`, `SpamChecker`, `CliCommands`, and block render template; `wp_rand()` replaces `mt_rand()` in `RateLimiter`; nonce verification comments and `wp_unslash()` added throughout; text domain corrected in `SpamChecker`; removed deprecated `load_plugin_textdomain()` call; added ABSPATH guard to `SubmissionsListTable` and `FormsListTable`; translators comments corrected in `Mailer`.
- `readme.txt` Tested up to updated to 7.0; plugin name corrected in readme header.

## [1.1.0] - 2026-08-17

### Added
- Extension API for BlueFairy Forms Pro: `bffrm_submission_created` action, `bffrm_pre_submit` filter, `bffrm_notification_recipients` filter, `bffrm_before_render_form` filter, `bffrm_field_types` filter, `bffrm_validate_field` filter, `bffrm_error_message` filter.
- `Renderer::get_supported_types()` static method exposing filterable list of supported field types.
- Native `file` field type rendering (`<input type="file">`) with automatic `enctype="multipart/form-data"` on the form tag.

## [1.0.0] — 2026-08-17

### Added

- Form definition storage (`bffrm_forms` table, JSON config schema v1.0).
- Gutenberg block `bluefairy/forms` with apiVersion 3, server-side render, editorScript in block.json.
- Native browser validation (no JS validator) — HTML5 required, pattern, minlength, maxlength, type attributes.
- Server-side submission handler via `admin_post_bffrm_submit` / `admin_post_nopriv_bffrm_submit`.
- All errors redirect (never `wp_die`, never HTTP 4xx): nonce_fail, rate_limit, spam, validation, success.
- Built-in honeypot + timetrap spam protection.
- Automatic delegation to BlueFairy Anti-Spam when active (via `bluefairy_antispam_render_fields` action and `bluefairy_antispam_check` filter).
- GDPR-safe IP hashing via HMAC-SHA256 with a site-specific key (`bffrm_ip_hmac_key`).
- WP privacy export and erase hooks (GDPR).
- `bffrm_submissions` table with `ROW_FORMAT=DYNAMIC`, `MEDIUMTEXT` payload, `DATETIME(6)` timestamps.
- `bffrm_rate_limits` table with atomic `INSERT … ON DUPLICATE KEY UPDATE` rate limiter.
- `bffrm_audit_log` table for admin action audit trail.
- Admin submissions list (WP_List_Table, 50 rows/page, detail view).
- Admin forms list with inline create/edit/delete and CodeMirror JSON editor (WP bundled).
- Settings page at Tools > BlueFairy Forms > Settings.
- Shortcode `[bluefairy_form id="N"]` with on-demand style enqueue.
- WP-CLI: `wp bluefairy-forms list-forms` and `wp bluefairy-forms doctor`.
- Synchronous `wp_mail()` notification to admin on each new submission.
- Multisite support via `wp_initialize_site` hook.
- Default "Contact" form (name, email, message) seeded on activation.
- Dismissible admin notice when BlueFairy Anti-Spam is not active.
- `uninstall.php` with `WP_UNINSTALL_PLUGIN` guard and optional table/option cleanup.
- `schema/form.v1.json` — JSON Schema (draft-07) for `config_json` validation.
