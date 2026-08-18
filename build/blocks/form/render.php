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

$form_id = isset($attributes['formId']) ? (int) $attributes['formId'] : 0;

if ($form_id < 1) {
    if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) {
        echo '<p class="bffrm-placeholder">' . esc_html__('BlueFairy Form: select a form ID in the block settings panel.', 'bluefairy-forms-lite') . '</p>';
    }
    return;
}

echo \BlueFairy\Forms\Renderer::render_form($form_id);
