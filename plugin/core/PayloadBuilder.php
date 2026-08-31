<?php

namespace WTHB\core;

use WTHB\models\Options;

/**
 * Builds heartbeat/metric payload for Watchman Tower API.
 */
class PayloadBuilder
{
    /**
     * Build complete payload.
     *
     * @param mixed $reason 'remote' (WT tetigi), 'connect', 'cron', 'manual', veya false
     * @return array
     */
    public static function build($reason = false): array
    {
        global $wp_version;

        $opts = Options::get_all();
        $iid = Options::get_instance_id();

        $theme = wp_get_theme();

        $payload = [
            'instanceId' => $iid,
            'pluginVersion' => defined('WTHB_VERSION') ? WTHB_VERSION : '0.0.0',
            'sentAt' => time(),
            // <=2.1.1'de bu satir `(bool) $manual` idi ve parametre 'remote'
            // gibi bir REASON STRING'i tasiyordu -- bos olmayan her string
            // true'ya cast oldugu icin HER gonderim "manual" gorunuyordu.
            'manual' => $reason === 'manual',
            'interval' => (int) $opts['interval_sec'],
            'pause' => (bool) $opts['pause'],
            'site' => [
                'homeUrl' => home_url(),
                'siteUrl' => site_url(),
                'adminUrl' => admin_url(),
                'multisite' => is_multisite(),
            ],
            'wordpress' => [
                'wpVersion' => $wp_version,
                'phpVersion' => PHP_VERSION,
                'theme' => [
                    'name' => $theme ? $theme->get('Name') : '',
                    'version' => $theme ? $theme->get('Version') : '',
                ],
                'comments' => self::comment_stats(),
            ],
            'health' => [
                'db' => ['ok' => self::check_db()],
                'cron' => self::cron_overdue_stats(),
                'rest' => ['ok' => self::rest_ok($reason)],
                'updates' => self::update_counts(),
                'ram' => self::get_ram_health(),
            ],
            'registration' => ['enabled' => (bool) get_option('users_can_register')],
            'security' => ['xmlrpc' => ['enabled' => self::xmlrpc_enabled()]],
            'inventory' => self::active_plugins_summary(50),
        ];

        return $payload;
    }

    /**
     * Check database connectivity.
     *
     * @return bool
     */
    private static function check_db(): bool
    {
        global $wpdb;

        try {
            $result = $wpdb->get_var("SELECT 1");
            return $result === '1';
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get cron overdue statistics.
     *
     * @return array
     */
    private static function cron_overdue_stats(): array
    {
        $crons = _get_cron_array();

        if (empty($crons) || !is_array($crons)) {
            return [
                'overdue' => 0,
                'nextDue' => 0,
            ];
        }

        $now = time();
        $overdue = 0;
        $next_due = 0;

        foreach ($crons as $timestamp => $cron) {
            if ($timestamp < $now) {
                $overdue += count($cron);
            } else {
                if ($next_due === 0 || $timestamp < $next_due) {
                    $next_due = $timestamp;
                }
            }
        }

        return [
            'overdue' => $overdue,
            'nextDue' => $next_due,
        ];
    }

    /**
     * Check REST API health.
     *
     * @return bool
     */
    /**
     * REST gercekten calisiyor mu?
     *
     * <=2.1.1'de bu metod `return true;` idi -- olcum yapmayan sahte bir
     * metrik. Ona bagli butun zincir (wp_rest_health sinyali, incident
     * cumlesi) hic tetiklenemeyecek veriye yaslaniyordu.
     *
     * Dogrulugu iki yoldan kuruyoruz:
     *
     * 1. Gonderim WT tetigiyle geldiyse ('remote') REST KANITLANMISTIR:
     *    tetik istegi /wp-json/wt/v1/heartbeat uzerinden, yani REST yiginin
     *    icinden gecerek geldi. Ek olcum, olani yeniden olcmek olurdu.
     *
     * 2. Diger yollarda (baglanti ani, manuel buton) REST kanitsiz --
     *    loopback ile /wp-json/ dizinine kisa bir istek atiyoruz. 500
     *    ALTINDAKI her cevap (200, 401, 404 dahil) REST yigininin ayakta
     *    oldugunu gosterir; olcmek istedigimiz sey icerik degil yol.
     *
     * Loopback'in paylasimli hosting'lerde guvenilmez oldugu biliniyor
     * (kendi kendine istek engellenebilir). Riski sinirli: manuel gonderimler
     * seyrek, WT tetikli gonderimler araya girer ve tuketici taraf iki ARDISIK
     * kotu olcum istemeden alarm uretmez.
     */
    private static function rest_ok($reason = false): bool
    {
        if ($reason === 'remote') {
            return true;
        }

        $response = wp_remote_get(rest_url(), [
            'timeout'   => 3,
            'redirection' => 2,
        ]);

        if (is_wp_error($response)) {
            return false;
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        return $code > 0 && $code < 500;
    }

    /**
     * Get available update counts.
     *
     * @return array
     */
    private static function update_counts(): array
    {
        $updates = [
            'core' => 0,
            'plugins' => 0,
            'themes' => 0,
        ];

        // Update helper functions live in wp-admin includes and are not guaranteed
        // to be loaded in REST/front-end contexts.
        if (!function_exists('get_core_updates') || !function_exists('get_plugin_updates') || !function_exists('get_theme_updates')) {
            if (defined('ABSPATH')) {
                $update_file = ABSPATH . 'wp-admin/includes/update.php';
                $plugin_file = ABSPATH . 'wp-admin/includes/plugin.php';
                $theme_file = ABSPATH . 'wp-admin/includes/theme.php';

                if (file_exists($update_file)) {
                    require_once $update_file;
                }
                if (file_exists($plugin_file)) {
                    require_once $plugin_file;
                }
                if (file_exists($theme_file)) {
                    require_once $theme_file;
                }
            }
        }

        if (!function_exists('get_core_updates') || !function_exists('get_plugin_updates') || !function_exists('get_theme_updates')) {
            return $updates;
        }

        $core_updates = \get_core_updates();
        if (!empty($core_updates) && is_array($core_updates)) {
            foreach ($core_updates as $update) {
                if (isset($update->response) && $update->response === 'upgrade') {
                    $updates['core']++;
                }
            }
        }

        $plugin_updates = \get_plugin_updates();
        if (!empty($plugin_updates) && is_array($plugin_updates)) {
            $updates['plugins'] = count($plugin_updates);
        }

        $theme_updates = \get_theme_updates();
        if (!empty($theme_updates) && is_array($theme_updates)) {
            $updates['themes'] = count($theme_updates);
        }

        return $updates;
    }

    /**
     * Get RAM/memory health stats.
     *
     * @return array
     */
    private static function get_ram_health(): array
    {
        $memory_limit = ini_get('memory_limit');

        $limit_bytes = 0;
        if (preg_match('/^(\d+)(.)$/', $memory_limit, $matches)) {
            $limit_value = (int) $matches[1];
            $unit = strtoupper($matches[2]);

            switch ($unit) {
                case 'G':
                    $limit_bytes = $limit_value * 1024 * 1024 * 1024;
                    break;
                case 'M':
                    $limit_bytes = $limit_value * 1024 * 1024;
                    break;
                case 'K':
                    $limit_bytes = $limit_value * 1024;
                    break;
                default:
                    $limit_bytes = $limit_value;
            }
        }

        $current_usage = memory_get_usage(true);
        $peak_usage = memory_get_peak_usage(true);
        
        $current_percent = $limit_bytes > 0 ? ($current_usage / $limit_bytes) * 100 : 0;
        $peak_percent = $limit_bytes > 0 ? ($peak_usage / $limit_bytes) * 100 : 0;

        // Get autoloaded options size
        global $wpdb;
        $autoload_size = 0;
        $result = $wpdb->get_var(
            "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload = 'yes'"
        );
        if ($result) {
            $autoload_size = (int) $result;
        }

        // Check OPcache
        $opcache_enabled = function_exists('opcache_get_status') && opcache_get_status() !== false;

        // Detect object cache type
        $object_cache_type = 'none';
        if (wp_using_ext_object_cache()) {
            global $wp_object_cache;
            if (isset($wp_object_cache)) {
                $class_name = get_class($wp_object_cache);
                if (strpos($class_name, 'Redis') !== false) {
                    $object_cache_type = 'redis';
                } elseif (strpos($class_name, 'Memcached') !== false) {
                    $object_cache_type = 'memcached';
                } else {
                    $object_cache_type = 'external';
                }
            }
        }

        return [
            'php' => [
                'currentUsage' => $current_usage,
                'peakUsage' => $peak_usage,
                'memoryLimit' => $limit_bytes,
                'peakUsagePercent' => round($peak_percent, 2),
                'currentUsagePercent' => round($current_percent, 2),
            ],
            'wordpress' => [
                'autoloadedOptionsSize' => $autoload_size,
                'autoloadedOptionsSizeMB' => round($autoload_size / 1024 / 1024, 2),
            ],
            'opcache' => [
                'enabled' => $opcache_enabled,
            ],
            'objectCache' => [
                'type' => $object_cache_type,
            ],
            'system' => [],
        ];
    }

    /**
     * Check if XML-RPC is enabled.
     *
     * @return bool
     */
    private static function xmlrpc_enabled(): bool
    {
        return apply_filters('xmlrpc_enabled', true);
    }

    /**
     * Get active plugins summary.
     *
     * @param int $limit
     * @return array
     */
    private static function active_plugins_summary(int $limit = 50): array
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();
        $active_plugins = get_option('active_plugins', []);

        if (is_multisite()) {
            $network_active = array_keys(get_site_option('active_sitewide_plugins', []));
            $active_plugins = array_merge($active_plugins, $network_active);
        }

        // Get update information
        $plugin_updates = get_plugin_updates();

        $active_list = [];

        foreach ($active_plugins as $plugin_path) {
            if (isset($all_plugins[$plugin_path])) {
                $plugin_data = $all_plugins[$plugin_path];
                $path_parts = explode('/', $plugin_path);
                $slug = isset($path_parts[0]) ? $path_parts[0] : '';
                
                $plugin_info = [
                    'name' => $plugin_data['Name'],
                    'version' => $plugin_data['Version'],
                    'file' => $plugin_path,
                    'slug' => $slug,
                    'author' => isset($plugin_data['Author']) ? wp_strip_all_tags($plugin_data['Author']) : '',
                    'description' => isset($plugin_data['Description']) ? wp_strip_all_tags($plugin_data['Description']) : '',
                ];

                // Add update information if available
                if (isset($plugin_updates[$plugin_path])) {
                    $update_info = $plugin_updates[$plugin_path]->update;
                    $plugin_info['hasUpdate'] = true;
                    $plugin_info['newVersion'] = isset($update_info->new_version) ? $update_info->new_version : '';
                } else {
                    $plugin_info['hasUpdate'] = false;
                    $plugin_info['newVersion'] = '';
                }
                
                $active_list[] = $plugin_info;
            }
        }

        $sample = array_slice($active_list, 0, $limit);

        return [
            'activePlugins' => [
                'count' => count($active_list),
                'sample' => $sample,
            ],
        ];
    }

    /**
     * Get comment statistics.
     *
     * @return array
     */
    private static function comment_stats(): array
    {
        $counts = wp_count_comments();
        $comments_open = get_option('default_comment_status') === 'open';
        $require_registration = (bool) get_option('comment_registration');
        $comment_moderation = (bool) get_option('comment_moderation');
        $default_pingback = get_option('default_pingback_flag');
        $show_avatars = (bool) get_option('show_avatars');
        $close_after_days = (int) get_option('close_comments_days_old');
        $auto_close = (bool) get_option('close_comments_for_old_posts');

        return [
            'total' => isset($counts->total_comments) ? (int) $counts->total_comments : 0,
            'approved' => isset($counts->approved) ? (int) $counts->approved : 0,
            'pending' => isset($counts->moderated) ? (int) $counts->moderated : 0,
            'spam' => isset($counts->spam) ? (int) $counts->spam : 0,
            'enabled' => $comments_open,
            'moderation' => $comment_moderation,
            'requireRegistration' => $require_registration,
            'pingbacks' => (bool) $default_pingback,
            'showAvatars' => $show_avatars,
            'closeAfterDays' => $close_after_days,
            'autoCloseEnabled' => $auto_close,
        ];
    }
}