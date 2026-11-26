<?php
/**
 * Uninstall script for Watchman Tower plugin.
 * Runs when plugin is deleted via WordPress admin.
 */

// Security check
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove main plugin options
delete_option('wthb_options');
delete_option('wthb_instance_id');

// For multisite compatibility
delete_site_option('wthb_options');
delete_site_option('wthb_instance_id');

// Clear scheduled cron job if exists
wp_clear_scheduled_hook('wthb_cron_heartbeat');

// Remove transient / cache keys (prefix based)
global $wpdb;
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-off cleanup on uninstall; no API deletes transients by prefix.
$wpdb->query(
    "DELETE FROM {$wpdb->options} 
     WHERE option_name LIKE '_transient_wthb_%'
        OR option_name LIKE '_transient_timeout_wthb_%'"
);

// Clear object cache
if (function_exists('wp_cache_flush')) {
    wp_cache_flush();
}