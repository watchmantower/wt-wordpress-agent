<?php

namespace WTHB\includes\ajax;

use WTHB\models\Options;

/**
 * Handles connection status check via AJAX.
 */
class Connection
{
    /**
     * Register AJAX hooks.
     *
     * @return void
     */
    public static function register(): void
    {
        add_action('wp_ajax_wthb_check_connection', [__CLASS__, 'handle']);
    }

    /**
     * Handle connection check request.
     *
     * @return void
     */
    public static function handle(): void
    {
        check_ajax_referer('wthb_connection_nonce', '_ajax_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'watchman-tower')]);
        }

        $opts = Options::get_all();
        $connected = Options::is_connected($opts);

        wp_send_json_success([
            'connected' => $connected,
            'last_success' => $opts['last_success'],
            'last_error' => $opts['last_error'],
        ]);
    }
}
