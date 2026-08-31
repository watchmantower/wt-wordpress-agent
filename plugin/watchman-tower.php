<?php
/**
 * Plugin Name: Watchman Tower
 * Plugin URI: https://docs.watchmantower.com/wordpress-agent/installation
 * Description: Watchman Tower WordPress Agent plugin to connect your site to Watchman Tower service for monitoring and management.
 * Version: 2.1.2
 * Author: Watchman Tower
 * Author URI: https://www.watchmantower.com
 * Requires PHP: 7.4
 * Requires at least: 5.8
 * Text Domain: watchman-tower
 * Domain Path: /languages
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

define('WTHB_VERSION', '2.1.2');
define('WTHB_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WTHB_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Activation hook: bootstrap background heartbeat scheduling if already connected.
 */
register_activation_hook(__FILE__, function () {
    if (class_exists('WTHB\\core\\Scheduler')) {
        // NOTE: Disabled. In WT-triggered mode, scheduling is handled externally.
        // WTHB\core\Scheduler::ensure_scheduled();
    }
});

/**
 * PSR-4 style autoloader for WTHB namespace.
 */
spl_autoload_register(function ($class) {
    $prefix = 'WTHB\\';
    $base_dir = WTHB_PLUGIN_PATH;

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

/**
 * Initialize plugin components.
 */
add_action('plugins_loaded', function () {

    if (is_admin() && class_exists('WTHB\\admin\\Admin')) {
        $admin = new WTHB\admin\Admin();
        $admin->init();
    }

    if (class_exists('WTHB\\core\\Scheduler')) {
        $scheduler = new WTHB\core\Scheduler();
        $scheduler->init();
    }

    if (class_exists('WTHB\\includes\\rest\\HeartbeatEndpoint')) {
        WTHB\includes\rest\HeartbeatEndpoint::register();
    }
});

/**
 * Add Settings link to plugins page.
 */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
    $settings_link = sprintf(
        '<a href="%s">%s</a>',
        esc_url(admin_url('options-general.php?page=wthb-settings')),
        esc_html__('Settings', 'watchman-tower')
    );
    array_unshift($links, $settings_link);
    return $links;
});
