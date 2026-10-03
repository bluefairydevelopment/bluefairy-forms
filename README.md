# BlueFairy Forms

Zero-asset-bloat WordPress forms plugin. Build forms with a Gutenberg block or shortcode, with GDPR-safe submissions and built-in spam protection — no jQuery, no bloat.

## Requirements

- WordPress 6.4+
- PHP 8.1+

## Installation

```bash
# From the WP plugins directory:
composer install --no-dev
wp plugin activate bluefairy-forms --path=/path/to/wordpress
```

## Usage

### Gutenberg Block

Add the **BlueFairy Form** block to any page or post, then enter the form ID in the Inspector Controls panel.

### Shortcode

```
[bluefairy_form id="1"]
```

### WP-CLI

```bash
wp bluefairy-forms list-forms
wp bluefairy-forms doctor
```

## Creating Forms

Go to **Tools > BlueFairy Forms > Add New**. Define your form as JSON:

```json
{
  "schema_version": "1.0",
  "submit_label": "Send Message",
  "success_message": "Thank you! Your message has been sent.",
  "fields": [
    { "id": "name",    "type": "text",     "label": "Your Name",      "required": true },
    { "id": "email",   "type": "email",    "label": "Email Address",  "required": true },
    { "id": "message", "type": "textarea", "label": "Message",        "required": true }
  ]
}
```

### Supported field types

`text`, `email`, `tel`, `url`, `textarea`, `select`, `radio`, `checkbox`, `number`, `date`

## Spam Protection

BlueFairy Forms includes a built-in honeypot field and timetrap token. For stronger protection (Stop Forum Spam lookup, shared block log), install the free **BlueFairy Anti-Spam** plugin — the two plugins detect each other automatically with no configuration needed.

## GDPR

IP addresses are HMAC-hashed before storage using a site-specific key generated on activation. Raw IPs are never stored. Submission data is exportable and erasable via WordPress's built-in Privacy tools.

## Anti-Spam Integration Hooks

Other plugins can hook into the spam-check system:

```php
// Render spam fields for a custom form:
do_action( 'bluefairy_antispam_render_fields', 'my_form_type' );

// Check a submission for spam:
$is_spam = apply_filters( 'bluefairy_antispam_check', false, $_POST, 'my_form_type', $ip, $email );
```

## Extension Hooks

```php
// Add or change fields before a submission is stored:
add_filter( 'bffrm_submission_payload', fn( array $payload, int $form_id, array $form ) => $payload, 10, 3 );

// The fields the submission screen and the personal-data export show for a stored row
// (an add-on that stores the payload elsewhere, e.g. encrypted, supplies it here):
add_filter( 'bffrm_submission_display_payload', fn( array $payload, array $submission ) => $payload, 10, 2 );
```

## Database Tables

- `{prefix}bffrm_forms` — form definitions
- `{prefix}bffrm_submissions` — submission records
- `{prefix}bffrm_rate_limits` — atomic rate-limit counters
- `{prefix}bffrm_audit_log` — admin action audit trail

## License

GPL-2.0-or-later — see [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).
