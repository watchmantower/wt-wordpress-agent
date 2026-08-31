<?php

namespace WTHB\includes\ajax;

use WTHB\core\Heartbeat;

/**
 * Handles manual heartbeat trigger via AJAX.
 */
class HeartbeatAjax
{
    /**
     * Register AJAX hooks.
     *
     * @return void
     */
    public static function register(): void
    {
        add_action('wp_ajax_wthb_trigger_heartbeat', [__CLASS__, 'handle']);
    }

    /**
     * Handle manual heartbeat request.
     *
     * @return void
     */
    public static function handle(): void
    {
        check_ajax_referer('wthb_heartbeat_nonce', '_ajax_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'watchman-tower')]);
        }

        $result = Heartbeat::send('manual');

        if (!empty($result['ok'])) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
}
