<?php
declare(strict_types=1);

/**
 * Plugin Name:       BlueFairy Forms Lite
 * Plugin URI:        https://bluefairydevelopment.com/plugins/bluefairy-forms-lite/
 * Description:       Zero-asset-bloat WordPress forms plugin. Gutenberg block, shortcode, GDPR-safe submissions, spam protection, and admin submissions list — no jQuery, no bloat.
 * Version:           1.1.5
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Blue Fairy Development
 * Author URI:        https://bluefairydevelopment.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bluefairy-forms-lite
 * Domain Path:       /languages
 */

if (! defined('ABSPATH')) {
    exit;
}

define('BFFRM_VERSION', '1.1.5');
define('BFFRM_FILE', __FILE__);
define('BFFRM_DIR', plugin_dir_path(__FILE__));
define('BFFRM_URL', plugin_dir_url(__FILE__));

// Autoloader.
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'BlueFairy\\Forms\\')) {
        $relative = str_replace('BlueFairy\\Forms\\', '', $class);
        $path     = BFFRM_DIR . 'includes/' . str_replace('\\', '/', $relative) . '.php';
        if (file_exists($path)) {
            require_once $path;
        }
    }
});

/**
 * Global helper — allows Pro and third-party code to detect Lite without class_exists().
 */
function bffrm_plugin(): BlueFairy\Forms\Plugin
{
    return BlueFairy\Forms\Plugin::instance();
}

// Activation / deactivation hooks.
register_activation_hook(__FILE__, static function (): void {
    ( new BlueFairy\Forms\Installer() )->activate();
});

register_deactivation_hook(__FILE__, static function (): void {
    ( new BlueFairy\Forms\Installer() )->deactivate();
});

// Boot the plugin after plugins are loaded so other plugins are available.
add_action('plugins_loaded', static function (): void {
    BlueFairy\Forms\Plugin::instance()->boot();
});
