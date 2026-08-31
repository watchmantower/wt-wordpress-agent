<?php

namespace WTHB\models;

/**
 * Centralized options management for Watchman Tower plugin.
 */
class Options
{
    const OPT_KEY = 'wthb_options';
    const OPT_INSTANCE_ID = 'wthb_instance_id';

    const ENDPOINT_SIGNUP = 'https://api.watchmantower.com/api/auth/wp/create-account';
    const ENDPOINT_HEARTBEAT = 'https://metric.watchmantower.com/wp/heartbeat';
    const ENDPOINT_UNLINK = 'https://metric.watchmantower.com/wp/unlink';
    const ENDPOINT_SITE_STATUS = 'https://metric.watchmantower.com/wp/status';

    /**
     * Get all plugin options with defaults.
     *
     * @return array
     */
    public static function get_all(): array
    {
        $opts = get_option(self::OPT_KEY, []);

        $defaults = [
            'token' => null,
            'agent_jwt' => null,
            'agent_jwt_exp' => null,
            'connected' => false,
            'interval_sec' => 300,
            'pause' => false,
            'last_success' => null,
            'last_error' => null,
            'site_id' => null,
        ];

        $merged = array_merge($defaults, is_array($opts) ? $opts : []);

        if ($merged['interval_sec'] < 60) {
            $merged['interval_sec'] = 60;
        }
        if ($merged['interval_sec'] > 3600) {
            $merged['interval_sec'] = 3600;
        }

        return $merged;
    }

    /**
     * Update all plugin options.
     *
     * @param array $opts
     * @return void
     */
    public static function update_all(array $opts): void
    {
        update_option(self::OPT_KEY, $opts, false);

        if (function_exists('wp_cache_delete')) {
            wp_cache_delete(self::OPT_KEY, 'options');
            wp_cache_delete('alloptions', 'options');
        }
    }

    /**
     * Update a single option key.
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public static function update(string $key, $value): void
    {
        $opts = self::get_all();
        $opts[$key] = $value;
        self::update_all($opts);
    }

    /**
     * Check if site is connected to Watchman Tower.
     *
     * @param array|null $opts
     * @return bool
     */
    public static function is_connected(?array $opts = null): bool
    {
        $opts = $opts ?? self::get_all();
        return !empty($opts['hmac_secret']) && !empty($opts['connected']);
    }

    /**
     * Clear pairing data.
     *
     * @return void
     */
    public static function clear_pairing(): void
    {
        $opts = self::get_all();

        $opts['token'] = null;
        $opts['agent_jwt'] = null;
        $opts['agent_jwt_exp'] = null;
        $opts['hmac_secret'] = null;
        $opts['site_id'] = null;
        $opts['connected'] = false;
        $opts['last_error'] = null;
        $opts['last_success'] = null;

        self::update_all($opts);
    }

    /**
     * Record successful heartbeat.
     *
     * @return void
     */
    public static function record_success(): void
    {
        self::update('last_success', time());
        self::update('last_error', null);
    }

    /**
     * Record heartbeat error.
     *
     * @param string $msg
     * @return void
     */
    public static function record_error(string $msg): void
    {
        self::update('last_error', $msg);
    }

    /**
     * Get or generate instance ID.
     *
     * @return string
     */
    public static function get_instance_id(): string
    {
        $iid = get_option(self::OPT_INSTANCE_ID);

        if (!empty($iid) && is_string($iid)) {
            return $iid;
        }

        $iid = function_exists('wp_generate_uuid4')
            ? wp_generate_uuid4()
            : uniqid('wthb_', true);

        update_option(self::OPT_INSTANCE_ID, $iid, false);

        return $iid;
    }
}
