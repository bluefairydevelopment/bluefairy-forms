<?php
declare(strict_types=1);

namespace BlueFairy\Forms;

/**
 * A page that shows a form must not be served from a page cache (WPMAP-152).
 *
 * The form carries a nonce (valid 12-24 h) and a signed time token (valid a
 * day). W3 Total Cache, WP Super Cache, LiteSpeed Cache or a CDN serving the
 * cached page handed every visitor the tokens the page was cached with; after
 * a day each genuine enquiry was refused, and until 1.1.9 refused as spam
 * with nothing logged. DONOTCACHEPAGE is the constant those caches honour;
 * nocache_headers() tells browsers and proxies. A cache that ignores both is
 * covered by the form's own script, which fetches fresh tokens on load
 * (TokenEndpoint).
 */
final class PageCache
{
    public static function register(): void
    {
        add_action('template_redirect', [self::class, 'maybe_bypass'], 0);
    }

    /**
     * On template_redirect, before any output: the queried post shows a form
     * (the shortcode or the block) — mark the response uncacheable.
     */
    public static function maybe_bypass(): void
    {
        if (! is_singular()) {
            return;
        }

        $post = get_queried_object();
        if (! $post instanceof \WP_Post) {
            return;
        }

        if (self::has_form((string) $post->post_content)) {
            self::bypass();
        }
    }

    public static function has_form(string $content): bool
    {
        return has_shortcode($content, 'bluefairy_form') || has_block('bluefairy/forms', $content);
    }

    /**
     * Mark this response uncacheable. Also called when a form renders
     * (a widget, a template part, another plugin's output): the constant still
     * reaches the page caches, which read it when the page is finished; the
     * headers only if none have been sent yet.
     */
    public static function bypass(): void
    {
        if (! defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }

        if (! headers_sent()) {
            nocache_headers();
        }
    }
}
