=== BlueFairy Forms ===
Contributors: bluefairydevelopment
Tags: forms, contact form, form builder, spam protection, GDPR
Requires at least: 6.4
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 1.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Zero-asset-bloat WordPress forms plugin. Build forms with a Gutenberg block or shortcode, with GDPR-safe submissions and spam protection — no jQuery, no bloat.

== Description ==

BlueFairy Forms is a lightweight, privacy-first WordPress forms plugin with no unnecessary JavaScript or CSS bloat. Forms are rendered server-side via a Gutenberg block or shortcode, and submissions are stored in your own database — no third-party services required.

**Key features:**

* **Gutenberg block** — drag the "BlueFairy Form" block onto any post or page and choose a form by ID.
* **Shortcode** — `[bluefairy_form id="1"]` works in Classic Editor, widgets, and theme templates.
* **JSON form builder** — define form fields in a structured JSON config with full CodeMirror editor in wp-admin.
* **Server-side validation** — required fields, email/URL/tel/number/date types, pattern constraints, minlength/maxlength.
* **Native browser validation** — no JavaScript validator required; HTML5 attributes are generated automatically.
* **GDPR-safe IP storage** — IP addresses are HMAC-hashed before storage. Supports WP privacy export and erasure by email.
* **Spam protection** — built-in honeypot + timetrap. Integrates automatically with the free BlueFairy Anti-Spam plugin for Stop Forum Spam lookup and enhanced coverage.
* **Rate limiting** — atomic DB-backed rate limiter (no transient race conditions). Default: 5 submissions/minute per IP per form.
* **Email notifications** — synchronous wp_mail() notification to admin on each new submission.
* **Admin submissions list** — WP_List_Table with last 200 rows, with a detail view per submission.
* **Audit log** — every form creation, update, and deletion is logged to a dedicated table.
* **WP-CLI** — `wp bluefairy-forms list-forms` and `wp bluefairy-forms doctor`.
* **Multisite ready** — tables created per-site on new site creation.
* **No external dependencies** — pure PHP 8.1+, no Composer dependencies beyond the autoloader.

**Works great alongside:**

* **BlueFairy Anti-Spam** (free) — automatically detected; no configuration needed. Adds Stop Forum Spam lookup and a shared block log to BlueFairy Forms.

== Installation ==

1. Upload the `bluefairy-forms` folder to the `/wp-content/plugins/` directory, or install via Plugins > Add New.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Go to **Tools > BlueFairy Forms** to create your first form.
4. Add the **BlueFairy Form** block to any page/post and enter the form ID, or use the shortcode `[bluefairy_form id="1"]`.

== Frequently Asked Questions ==

= How do I create a form? =

Go to **Tools > BlueFairy Forms**, click "Add New", enter a title and slug, then edit the JSON config to define your fields. A default Contact form is created automatically on activation.

= What field types are supported? =

text, email, tel, url, textarea, select, radio, checkbox, number, date.

= How is spam blocked? =

BlueFairy Forms includes a built-in honeypot field and time-trap token. If the free **BlueFairy Anti-Spam** plugin is also active, it automatically delegates to that plugin for stronger protection including Stop Forum Spam lookup.

= Is it GDPR compliant? =

IP addresses are HMAC-hashed before storage using a site-specific key — raw IPs are never stored. Submission data can be exported or erased via WordPress's built-in Privacy tools (Tools > Export Personal Data / Erase Personal Data).

= What is the shortcode? =

`[bluefairy_form id="1"]` — replace `1` with your form's numeric ID.

= Does it work with Multisite? =

Yes. Tables are created per-site automatically when a new site is added to the network.

= Can I export submissions to CSV? =

Not in the Lite version. CSV export is available in BlueFairy Forms Pro.

= How does the rate limiter work? =

Submissions are limited to 5 per IP per form per minute by default. The limit is DB-backed using an atomic `INSERT … ON DUPLICATE KEY UPDATE` to avoid race conditions. You can adjust the limit in **Tools > BlueFairy Forms > Settings**.

== Screenshots ==

1. The BlueFairy Form Gutenberg block with form ID picker in the inspector.
2. The admin forms list (Tools > BlueFairy Forms).
3. The JSON form editor with CodeMirror syntax highlighting.
4. The submissions list table.
5. A rendered contact form on the front end.

== Changelog ==

= 1.1.0 =
* Add Shortcode column to the Forms list table so shortcodes are visible at a glance.
* Fix GDPR personal data exporter/eraser to use named array keys, preventing conflicts with other plugins' exporters.
* Fix style sheet enqueue guard — CSS is no longer enqueued during AJAX, REST, or WP-CLI requests.
* Enqueue form stylesheet on the Gutenberg block render path (previously only enqueued on shortcode path).
* Stamp `bffrm_db_version = 2` in options on plugin upgrade.
* Fix `wp bluefairy-forms list-forms --format=ids` to return space-separated IDs correctly.

= 1.0.0 =
* Initial release.
* Form definition storage with JSON config schema.
* Gutenberg block with server-side render.
* Shortcode `[bluefairy_form id="N"]`.
* Native browser validation (no JS validator).
* Server-side submission handler via admin_post.
* Built-in honeypot + timetrap spam protection.
* Automatic integration with BlueFairy Anti-Spam plugin.
* GDPR-safe IP hashing; WP privacy export and erase.
* Admin submissions list (WP_List_Table).
* Settings page (Tools > BlueFairy Forms > Settings).
* WP-CLI commands: `list-forms`, `doctor`.
* DB-backed atomic rate limiting.
* Synchronous email notification via wp_mail().
* Audit log table.
* Multisite: per-site tables via wp_initialize_site.

== Upgrade Notice ==

= 1.1.0 =
Bug fix release. Deactivate and reactivate the plugin after updating to stamp the new DB version option.

= 1.0.0 =
Initial release. No upgrade steps required.
