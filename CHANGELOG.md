# Changelog

All notable changes to BlueFairy Forms are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).
Versioning follows Semantic Versioning (Major.Minor.Patch).

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
