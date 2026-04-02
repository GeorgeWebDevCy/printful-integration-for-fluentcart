<?php
/**
 * Plugin Name:       Printful Integration for FluentCart
 * Plugin URI:        https://github.com/GeorgeWebDevCy/printful-integration-for-fluentcart
 * Description:       Connects Printful print-on-demand fulfillment with FluentCart - automatic order fulfillment, product sync, live shipping rates, and shipment tracking.
 * Version:           1.0.11
 * Author:            George Nicolaou
 * Author URI:        https://georgewebdev.cy
 * Update URI:        https://github.com/GeorgeWebDevCy/printful-integration-for-fluentcart
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       printful-for-fluentcart
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Requires PHP:      7.4
 */

defined('ABSPATH') || exit;

define('PIFC_VERSION', '1.0.11');
define('PIFC_PLUGIN_FILE', __FILE__);
define('PIFC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PIFC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PIFC_PLUGIN_SLUG', 'printful-for-fluentcart');

if (file_exists(PIFC_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once PIFC_PLUGIN_DIR . 'vendor/autoload.php';
}

if (class_exists('\YahnisElsts\PluginUpdateChecker\v5\PucFactory')) {
    $pifcUpdateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/GeorgeWebDevCy/printful-integration-for-fluentcart/',
        __FILE__,
        'printful-integration-for-fluentcart'
    );
    $pifcUpdateChecker->setBranch('main');
}

spl_autoload_register(function ($class) {
    $prefix   = 'PrintfulForFluentCart\\';
    $base_dir = PIFC_PLUGIN_DIR . 'src/';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file     = $base_dir . str_replace('\\', '/', $relative) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

register_activation_hook(__FILE__, ['PrintfulForFluentCart\\Activator', 'activate']);
register_deactivation_hook(__FILE__, ['PrintfulForFluentCart\\Deactivator', 'deactivate']);

add_action('plugins_loaded', function () {
    $hasFluentCart = defined('FLUENTCART_PLUGIN_PATH')
        || defined('FLUENT_CART_DIR_FILE')
        || function_exists('fluentCart')
        || class_exists('\FluentCart\App\App');

    if (!$hasFluentCart) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__(
                'Printful for FluentCart requires FluentCart to be installed and active.',
                'printful-for-fluentcart'
            );
            echo '</p></div>';
        });
        return;
    }

    \PrintfulForFluentCart\Plugin::getInstance()->boot();
}, 20);
