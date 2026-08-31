<?php

namespace WTHB\includes\ajax;

use WTHB\core\Heartbeat;
use WTHB\models\Options;

/**
 * Handles saving settings via AJAX.
 */
class SaveSettings
{
    /**
     * Register AJAX hooks.
     *
     * @return void
     */
    public static function register(): void
    {
        add_action('wp_ajax_wthb_save_settings', [__CLASS__, 'handle']);
    }

    /**
     * Handle save settings request.
     *
     * @return void
     */
    public static function handle(): void
    {
        check_ajax_referer('wthb_settings_nonce', '_ajax_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'watchman-tower')]);
        }

        $interval_sec = isset($_POST['interval_sec']) ? intval($_POST['interval_sec']) : 300;
        $pause = isset($_POST['pause']) ? (bool) $_POST['pause'] : false;

        if ($interval_sec < 60) {
            $interval_sec = 60;
        }
        if ($interval_sec > 3600) {
            $interval_sec = 3600;
        }

        $opts = Options::get_all();
        $opts['interval_sec'] = $interval_sec;
        $opts['pause'] = $pause;

        Options::update_all($opts);

        // Immediately send updated settings/metrics to Watchman Tower.
        // Scheduling is handled externally (WT-triggered), so no WP-Cron planning here.
        $hb = Heartbeat::send('settings');

        // NOTE: Disabled. In WT-triggered mode, scheduling is handled externally.
        // Scheduler::schedule_in(5);

        wp_send_json_success([
            'saved' => true,
            'interval_sec' => $interval_sec,
            'pause' => $pause,
            'heartbeat' => $hb,
        ]);
    }
}
