<?php

namespace WTHB\includes\ajax;

use WTHB\core\Heartbeat;
use WTHB\core\Scheduler;
use WTHB\models\Options;

/**
 * Handles saving register token and connecting to Watchman Tower.
 */
class Token
{
    /**
     * Register AJAX hooks.
     *
     * @return void
     */
    public static function register(): void
    {
        add_action('wp_ajax_wthb_save_token', [__CLASS__, 'handle']);
    }

    /**
     * Handle save token request.
     *
     * @return void
     */
    public static function handle(): void
    {
        check_ajax_referer('wthb_token_nonce', '_ajax_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'watchman-tower')]);
        }

        $token = isset($_POST['token']) ? sanitize_text_field(wp_unslash($_POST['token'])) : '';

        if (empty($token)) {
            wp_send_json_error(['message' => __('Token is required.', 'watchman-tower')]);
        }

        $opts = Options::get_all();
        $opts['token'] = $token;
        $opts['connected'] = false;
        Options::update_all($opts);

        $result = Heartbeat::send('connect');

        if (empty($result['ok'])) {
            $msg = isset($result['message']) ? $result['message'] : __('Failed to connect to Watchman Tower.', 'watchman-tower');
            wp_send_json_error(['message' => $msg]);
        }

        // Debug: Check connection status
        $opts_after = Options::get_all();

        // NOTE: Disabled. In WT-triggered mode, scheduling is handled externally.
        // $opts = Options::get_all();
        // Scheduler::ensure_scheduled((int) $opts['interval_sec']);

        wp_send_json_success([
            'connected' => true,
            'redirect' => admin_url('options-general.php?page=wthb-settings'),
        ]);
    }
}
