<?php
declare(strict_types=1);

/**
 * Plugin Name:       BlueFairy Forms
 * Plugin URI:        https://bluefairydevelopment.com/plugins/bluefairy-forms/
 * Description:       Zero-asset-bloat WordPress forms plugin. Gutenberg block, shortcode, GDPR-safe submissions, spam protection, and admin submissions list — no jQuery, no bloat.
 * Version:           1.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Blue Fairy Development
 * Author URI:        https://bluefairydevelopment.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bluefairy-forms
 * Domain Path:       /languages
 */

namespace BlueFairy\Forms;

if (! defined('ABSPATH')) {
    exit;
}

define('BFFRM_VERSION', '1.1.0');
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
 * Public helper — used by SpamChecker to detect the plugin without class_exists().
 */
function bffrm_plugin(): Plugin
{
    return Plugin::instance();
}

// Activation / deactivation hooks.
register_activation_hook(__FILE__, static function (): void {
    ( new Installer() )->activate();
});

register_deactivation_hook(__FILE__, static function (): void {
    ( new Installer() )->deactivate();
});

// Boot the plugin after plugins are loaded so other plugins are available.
add_action('plugins_loaded', static function (): void {
    Plugin::instance()->boot();
});
