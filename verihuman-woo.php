<?php
/**
 * Plugin Name:       VeriHuman AI Copy Generator
 * Plugin URI:        https://verihuman.xyz
 * Description:       Generate AI-powered marketing & sales copy for WooCommerce products with a single click.
 * Version:           1.0.0
 * Author:            VeriHuman
 * Author URI:        https://verihuman.xyz
 * License:           GPL v2 or later
 * Text Domain:       verihuman-woo
 * Requires Plugins:  woocommerce
 */

defined('ABSPATH') || exit;

define('VERIHUMAN_VERSION', '1.0.0');
define('VERIHUMAN_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('VERIHUMAN_PLUGIN_URL', plugin_dir_url(__FILE__));
define('VERIHUMAN_API_BASE', 'http://localhost:3000/api/v1');

// Autoload classes
require_once VERIHUMAN_PLUGIN_DIR . 'includes/class-db.php';
require_once VERIHUMAN_PLUGIN_DIR . 'includes/class-settings.php';
require_once VERIHUMAN_PLUGIN_DIR . 'includes/class-meta-box.php';
require_once VERIHUMAN_PLUGIN_DIR . 'includes/class-api-handler.php';

// Bootstrap
add_action('plugins_loaded', function () {
    new Verihuman_Settings();
    new Verihuman_Meta_Box();
    new Verihuman_Api_Handler();
});

// Activation hook — create custom DB table
register_activation_hook(__FILE__, ['Verihuman_DB', 'install']);
