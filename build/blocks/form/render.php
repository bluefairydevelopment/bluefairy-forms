<?php
declare(strict_types=1);

/**
 * Server-side render callback for the bluefairy/forms block.
 *
 * WordPress passes $attributes, $content, $block to this file.
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @var string               $content    Inner block content (unused).
 * @var \WP_Block            $block      Block instance.
 */

if (! defined('ABSPATH')) {
    exit;
}

$bffrm_form_id = isset($attributes['formId']) ? (int) $attributes['formId'] : 0;

if ($bffrm_form_id < 1) {
    if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) {
        echo '<p class="bffrm-placeholder">' . esc_html__('BlueFairy Form: select a form ID in the block settings panel.', 'bluefairy-forms') . '</p>';
    }
    return;
}

// Enqueue form styles only when this block is actually rendered on the page.
wp_enqueue_style('bffrm-form', BFFRM_URL . 'build/blocks/form/style.css', [], BFFRM_VERSION);

echo \BlueFairy\Forms\Renderer::render_form($bffrm_form_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_form() returns sanitized HTML
