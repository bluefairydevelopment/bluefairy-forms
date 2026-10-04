=== BlueFairy Forms ===
Contributors: bluefairydevelopment
Tags: forms, contact form, form builder, spam protection, GDPR
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.1.9
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Zero-bloat WordPress forms plugin. Gutenberg block, shortcode, GDPR-safe submissions, spam protection — no jQuery, no bloat.

== Description ==

BlueFairy Forms is a lightweight, privacy-first WordPress forms plugin with no unnecessary JavaScript or CSS bloat. Forms are rendered server-side via a Gutenberg block or shortcode, and submissions are stored in your own database — no third-party services required.

**Key features:**

* **Gutenberg block** — drag the "BlueFairy Form" block onto any post or page and choose a form by ID.
* **Shortcode** — `[bluefairy_form id="1"]` works in Classic Editor, widgets, and theme templates.
* **JSON form builder** — define form fields in a structured JSON config with full CodeMirror editor in wp-admin.
* **Server-side validation** — required fields, email/URL/tel/number/date types, pattern constraints, minlength/maxlength.
* **HTML5 markup** — input types and attributes (required, pattern, minlength, maxlength) are rendered; the form itself is validated on the server, so the same rules apply whatever the browser.
* **GDPR-safe IP storage** — IP addresses are HMAC-hashed before storage. Supports WP privacy export and erasure by email.
* **Spam protection** — built-in honeypot + timetrap. Integrates automatically with the free BlueFairy Anti-Spam plugin for Stop Forum Spam lookup and enhanced coverage.
* **Rate limiting** — atomic DB-backed rate limiter (no transient race conditions). Default: 5 submissions/minute per IP per form. The IP is the address that connected; behind Cloudflare the visitor's address is recognised automatically, and any other proxy is configured under Trusted Proxies.
* **Email notifications** — synchronous wp_mail() notification to admin on each new submission, with Reply-To set to the visitor so you can answer straight from your inbox.
* **Admin submissions list** — 50 per page, filter by form, with a detail view per submission.
* **Audit log** — every form creation, update, and deletion is logged to a dedicated table.
* **WP-CLI** — `wp bluefairy-forms list-forms` and `wp bluefairy-forms doctor`.
* **Multisite ready** — tables created per-site on new site creation; uninstall cleans every site that asked for its data to be removed.
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

Not in the free plugin. CSV export is available in BlueFairy Forms Pro.

= How does the rate limiter work? =

Submissions are limited to 5 per IP per form per minute by default. The limit is DB-backed using an atomic `INSERT … ON DUPLICATE KEY UPDATE` to avoid race conditions. You can adjust the limit in **Tools > BlueFairy Forms > Settings**.

= Which IP address does the rate limit use? =

The address that connected to your server. Forwarding headers are written by whoever sends the request, so they are believed only from a proxy in front of your site: Cloudflare's `CF-Connecting-IP` when the request comes from one of Cloudflare's published IP ranges (built in, no setup), and `X-Forwarded-For` / `X-Real-IP` only from the proxies you list under **Settings > Trusted Proxies** (IP addresses or CIDR ranges; the right-most forwarded address that is not one of your proxies is used). Developers can adjust both lists with the `bffrm_cloudflare_ranges` and `bffrm_trusted_proxies` filters.

== Screenshots ==

1. The BlueFairy Form Gutenberg block with form ID picker in the inspector.
2. The admin forms list (Tools > BlueFairy Forms).
3. The JSON form editor with CodeMirror syntax highlighting.
4. The submissions list table.
5. A rendered contact form on the front end.

== Changelog ==

= 1.1.9 =
* New: the admin notification e-mail has Reply-To set to the visitor (their name and address from the form), so replying reaches them. There was no Reply-To, so a reply went to your own site. Filter `bffrm_notification_headers` changes the headers.
* Fix: a form whose configuration has no field list logged two PHP warnings on every view.
* Fix: with BlueFairy Anti-Spam active, forms kept using their own honeypot and time trap instead of handing spam checking to it, and the settings page and admin notice still recommended installing it. Forms now detects Anti-Spam correctly.
* Fix: a value an add-on stores as a list or a record — a BlueFairy Forms Pro file upload — showed as "Array" in the admin notification e-mail, on the submission screen and in the personal-data export, and logged a PHP warning ("Array to string conversion") on every such submission. An upload now shows as its file name, several as a comma-separated list.
* Security: the rate limit believed the first X-Forwarded-For, X-Real-IP or CF-Connecting-IP header it found, so a sender that changed the header on every request was never limited. It now uses the connecting address, Cloudflare's header only from Cloudflare's own ranges, and forwarded headers only from proxies you list under the new **Trusted Proxies** setting.
* Fix: a form with a consent checkbox accepted submissions without consent (the check was only in the browser, and the form turns browser validation off). The server now refuses them with a message.
* Fix: **Enable Forms — Accept new form submissions** did nothing when unticked. Submissions are now refused with "This form is not accepting submissions at the moment." The **Debug Logging** setting, which also did nothing, is removed.
* Fix: saving this plugin's Settings page while BlueFairy Forms Pro was inactive erased Pro's own settings (CAPTCHA keys, upload size, retention, encryption switch) from the shared option. Saving now keeps them.
* Fix: on multisite, uninstall cleaned only the site the plugin was deleted from. It now removes the data of every site that ticked Remove Data on Uninstall.
* New: `bffrm_settings_fields` action and `bffrm_settings_input` filter, so an add-on's settings appear on and save with this page (Forms Pro 1.1.0 adds CAPTCHA keys, upload size and retention there); `bffrm_before_erase_submissions` action, so an add-on erases its own copy of a submission when you erase personal data (Forms Pro's encrypted payload); `bffrm_submission_detail_after` action on the submission screen (Forms Pro lists uploaded files there with download links).
* Docs: the readme no longer claims browser-side validation (the server validates) or a 200-row list (50 per page).
* New: `bffrm_submission_display_payload` filter (the decoded fields and the submission row), used by the submission screen and the personal-data export. BlueFairy Forms Pro 1.1.0 uses it to show encrypted submissions, which before showed no fields at all.

= 1.1.8 =
* New: an extension point above the submissions table, so BlueFairy Forms Pro can add its "Export CSV" button there. Pro's CSV export previously had nowhere in the admin to be triggered from.

= 1.1.7 =
* Fix: "Remove Data on Uninstall" left the `bffrm_db_version` option behind. The plugin creates three options and uninstall removed two of them, while the file's own documentation promised it removed all plugin data.
* Fix: the Forms Pro notice on the settings page listed features Pro does not have. It now names only what Pro ships, and explains that Pro installs alongside this plugin rather than replacing it.
* The Pro notice and the plugins-list link now hide themselves when Forms Pro is already installed.

= 1.1.6 =
* Renamed from BlueFairy Forms Lite to BlueFairy Forms (slug bluefairy-forms). The paid add-on is now BlueFairy Forms Pro. Nothing is called "Lite" any more.
* Fix: database schema changes now run when the plugin is updated, not only when it is activated. WordPress does not fire the activation hook on update, so new indexes and columns reached fresh installs and silently skipped every existing site.
* Fix: personal-data erasure could fail because ip_hash was not nullable. The column now accepts NULL, and a migration updates existing installs.
* Fix: file-field validation, plus a nonce-checked admin AJAX endpoint.
* Performance: indexes added on the rate-limit expiry and audit-log timestamp columns, and the submissions-by-date query rewritten as a range so it can use its index instead of scanning the table.
* New: audit log retention. Entries older than the configured number of days (90 by default) are removed by a daily scheduled task.
* New: `bffrm_submission_payload` filter, so add-ons can extend a submission payload before it is stored.
* Add: an upgrade notice for BlueFairy Forms Pro on the settings page and in the plugins list row.
* Compatibility: tested against WordPress 7.1.

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

= 1.1.9 =
If your site is behind a proxy or load balancer other than Cloudflare, list it under Settings > Trusted Proxies, or every visitor will share the proxy's rate limit. If you use BlueFairy Forms Pro: update this plugin with (or before) Forms Pro 1.1.0. File uploads then show by name in e-mails and on the submission screen, and encrypted submissions can be read in WP admin again.

= 1.1.7 =
If you use "Remove Data on Uninstall", this release makes it complete — the bffrm_db_version option was previously left behind.


= 1.1.6 =
The plugin is now BlueFairy Forms, not BlueFairy Forms Lite. Schema updates now apply on update as well as activation, so no deactivate/reactivate step is needed this time.

= 1.1.0 =
Bug fix release. Deactivate and reactivate the plugin after updating to stamp the new DB version option.

= 1.0.0 =
Initial release. No upgrade steps required.
