<?php
namespace WTHB;

if (!defined('ABSPATH')) exit;

class Heartbeat {
  const LOCK_KEY = 'wthb_hb_lock';

  public static function init(): void {}

  public static function send(bool $manual = false): void {
    $opts     = Options::get_all();
    $endpoint = $opts['endpoint'] ?? Options::DEFAULT_ENDPOINT;

    // Debug: Hangi ayarlarla çalışıyoruz?
    error_log('Heartbeat::send called with opts: ' . json_encode($opts));

    // Eğer yakın zamanda unlink yapılmışsa (son 30 saniye) ve manual değilse, heartbeat gönderme
    if (!$manual && isset($opts['last_unlinked_at'])) {
      $timeSinceUnlink = time() - (int)$opts['last_unlinked_at'];
      if ($timeSinceUnlink < 30) {
        Options::record_error('Recently unlinked, skipping heartbeat');
        return;
      }
    }

    // --- TOKEN SEÇİMİ ---
    $authToken = '';
    // Eğer agent_jwt varsa onu kullan (connected olsun ya da olmasın)
    if (!empty($opts['agent_jwt'])) {
      $authToken = $opts['agent_jwt'];
      error_log('Using agent_jwt token');
    } elseif (!empty($opts['token'])) {
      $authToken = $opts['token']; // register flow
      error_log('Using register token');
    }

    if (empty($endpoint) || empty($authToken)) {
      Options::record_error('Missing endpoint or token');
      return;
    }

    // Cache bypass için timestamp ekle
    $endpoint_with_cache_buster = $endpoint . (strpos($endpoint, '?') !== false ? '&' : '?') . '_t=' . time();
    error_log('Sending heartbeat to: ' . $endpoint_with_cache_buster);

    $iid           = Options::get_instance_id();
    $theme         = wp_get_theme();
    $comment_status = self::get_comment_settings();
    global $wp_version;

    // -------- Health Data --------
    $payload = [
      'instanceId'    => $iid,
      'pluginVersion' => defined('WTHB_VERSION') ? WTHB_VERSION : '0.0.0',
      'sentAt'        => time(),
      'manual'        => $manual,
      'interval'      => (int)$opts['interval_sec'],
      'site' => [
        'homeUrl'   => home_url(),
        'siteUrl'   => site_url(),
        'adminUrl'  => admin_url(),
        'multisite' => is_multisite(),
      ],
      'wordpress' => [
        'wpVersion'  => $wp_version,
        'phpVersion' => PHP_VERSION,
        'theme'      => [
          'name'    => $theme ? $theme->get('Name')    : null,
          'version' => $theme ? $theme->get('Version') : null,
        ],
        'comments' => $comment_status,
      ],
      'health' => [
        'db'      => ['ok' => self::check_db()],
        'cron'    => self::cron_overdue_stats(),
        'rest'    => ['ok' => self::rest_ok()],
        'updates' => self::update_counts(),
        'ram'     => self::get_ram_health(),
      ],
      'registration' => ['enabled' => (bool) get_option('users_can_register')],
      'security'     => ['xmlrpc' => ['enabled' => self::xmlrpc_enabled()]],
      'inventory'    => self::active_plugins_summary(10),
    ];

    $res = wp_remote_post($endpoint_with_cache_buster, [
      'headers' => [
        'Content-Type'  => 'application/json; charset=UTF-8',
        'Authorization' => 'Bearer '.$authToken,
        'Accept'        => 'application/json',
        'Cache-Control' => 'no-cache, no-store, must-revalidate',
        'Pragma'        => 'no-cache',
        'Expires'       => '0',
      ],
      'timeout' => 12,
      'body'    => wp_json_encode($payload),
    ]);

    if (is_wp_error($res)) {
      Options::record_error($res->get_error_message());
      return;
    }

    $code = wp_remote_retrieve_response_code($res);
    $body = wp_remote_retrieve_body($res);

    if ($code >= 200 && $code < 300) {
      $server = json_decode(is_string($body) ? trim($body) : '', true) ?: [];

      if (!empty($server['agentJwt']) && is_string($server['agentJwt'])) {
        $opts['agent_jwt'] = $server['agentJwt'];
        $opts['connected'] = true;
        if (isset($server['agentJwtExpiresAt'])) {
          $opts['agent_jwt_exp'] = (int)$server['agentJwtExpiresAt'];
        }
        // Eski register token'ı kesin olarak etkisizleştir
        $opts['token'] = '';
        error_log('agentJwt received, will persist and clear register token.');
        error_log('Options to save: ' . json_encode($opts));
        if (class_exists('\WTHB\Scheduler')) \WTHB\Scheduler::schedule_in(5);
      }

      if (isset($server['desiredIntervalSec'])) $opts['interval_sec'] = max(60, (int)$server['desiredIntervalSec']);
      if (isset($server['pause']))              $opts['pause']        = (bool)$server['pause'];

      // AGRESIF KAYDETME: Direkt veritabanına yaz, tüm cache ve hook'ları bypass et
      global $wpdb;
      
      // Önce tüm cache'leri temizle
      wp_cache_flush();
      if (function_exists('wp_cache_delete')) {
        wp_cache_delete(Options::OPT_KEY, 'options');
        wp_cache_delete('alloptions', 'options');
      }
      
      // WordPress'in kendi option cache'ini de bypass et
      global $wp_object_cache;
      if (isset($wp_object_cache)) {
        $wp_object_cache->delete(Options::OPT_KEY, 'options');
        $wp_object_cache->delete('alloptions', 'options');
      }
      
      // Veritabanına direkt yaz (hooks bypass)
      $option_name = Options::OPT_KEY;
      $option_value = maybe_serialize($opts);
      
      $wpdb->query($wpdb->prepare(
        "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no') ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)",
        $option_name,
        $option_value
      ));
      
      error_log('Direct DB write completed for agentJwt persistence');
      
      // Cache'leri tekrar temizle
      wp_cache_flush();
      if (function_exists('wp_cache_delete')) {
        wp_cache_delete(Options::OPT_KEY, 'options');
        wp_cache_delete('alloptions', 'options');
      }
      
      // Doğrulama için hemen geri oku ve logla
      $saved = get_option(Options::OPT_KEY, []);
      error_log('Raw options after direct DB save: ' . json_encode($saved));
      
      $saved_via_class = Options::get_all();
      error_log('Options via get_all after saving agentJwt: ' . json_encode([
        'has_agent_jwt' => !empty($saved_via_class['agent_jwt']),
        'token_len'     => isset($saved_via_class['token']) ? strlen((string)$saved_via_class['token']) : null,
        'connected'     => !empty($saved_via_class['connected'])
      ]));
      Options::record_success();
      
      return;
    }

    if ($code === 401 || $code === 403) {
      Options::set_connected(false);
    }

    // Interval'i sadece otomatik heartbeat'lerde artır, manual'da değil
    if (!$manual) {
      $prev = (int)($opts['interval_sec'] ?? Options::DEFAULT_INTERVAL);
      $opts['interval_sec'] = min(3600, max(60, $prev * 2));
      Options::update_all($opts);
    }
    Options::record_error('HTTP '.$code.' '.substr((string)$body, 0, 200));
  }

  public static function notify_unlink(string $reason = 'manual'): bool {
    $opts     = Options::get_all();
    $endpoint = $opts['endpoint'] ?? Options::DEFAULT_ENDPOINT;
    $agent    = !empty($opts['agent_jwt']) ? $opts['agent_jwt'] : '';
    if (empty($endpoint) || empty($agent)) return false;

    $iid = Options::get_instance_id();
    $url = preg_replace('#/heartbeat/?$#', '/unlink', rtrim($endpoint, '/'));

    $res = wp_remote_post($url, [
      'headers' => [
        'Content-Type'  => 'application/json; charset=UTF-8',
        'Authorization' => 'Bearer '.$agent,
        'Accept'        => 'application/json',
      ],
      'timeout' => 8,
      'body'    => wp_json_encode(['instanceId' => $iid, 'reason' => $reason, 'at' => time()]),
    ]);

    if (is_wp_error($res)) return false;
    $code = wp_remote_retrieve_response_code($res);
    return ($code >= 200 && $code < 300);
  }

  public static function with_lock(callable $fn): void {
    if (get_transient(self::LOCK_KEY)) return;
    set_transient(self::LOCK_KEY, 1, 60);
    try { $fn(); } finally { delete_transient(self::LOCK_KEY); }
  }

  private static function check_db(): bool {
    global $wpdb;
    
    try {
      // Basit bir sorgu ile veritabanı bağlantısını test et
      $result = $wpdb->get_var("SELECT 1");
      return ($result === '1');
    } catch (Exception $e) {
      return false;
    }
  }
  private static function cron_overdue_stats(): array {
    $crons = _get_cron_array();
    if (empty($crons)) {
      return ['overdue' => 0, 'nextDueInSec' => null];
    }
    
    $now = time();
    $overdue_count = 0;
    $next_due = null;
    
    foreach ($crons as $timestamp => $cron) {
      if ($timestamp < $now) {
        $overdue_count++;
      } elseif ($next_due === null || $timestamp < $next_due) {
        $next_due = $timestamp;
      }
    }
    
    $next_due_in_sec = $next_due ? ($next_due - $now) : null;
    
    return [
      'overdue' => $overdue_count,
      'nextDueInSec' => $next_due_in_sec
    ];
  }
  private static function rest_ok(): bool { return true; }
  
  private static function get_comment_settings(): array {
    return [
      'enabled' => get_option('default_comment_status') === 'open',
      'moderation' => (bool) get_option('comment_moderation'),
      'requireRegistration' => (bool) get_option('comment_registration'),
      'pingbacks' => get_option('default_ping_status') === 'open',
      'showAvatars' => (bool) get_option('show_avatars'),
      'closeAfterDays' => (int) get_option('close_comments_days_old'),
      'autoCloseEnabled' => (bool) get_option('close_comments_for_old_posts')
    ];
  }
  
  private static function xmlrpc_enabled(): bool {
    // XML-RPC'nin devre dışı bırakılıp bırakılmadığını kontrol et
    return apply_filters('xmlrpc_enabled', true);
  }
  
  private static function get_ram_health(): array {
    $result = [];
    
    // PHP Memory Usage
    $current_usage = memory_get_usage(true);
    $peak_usage = memory_get_peak_usage(true);
    $memory_limit = self::parse_memory_limit(ini_get('memory_limit'));
    
    $result['php'] = [
      'currentUsage' => $current_usage,
      'peakUsage' => $peak_usage,
      'memoryLimit' => $memory_limit,
      'peakUsagePercent' => $memory_limit > 0 ? round(($peak_usage / $memory_limit) * 100, 2) : null,
      'currentUsagePercent' => $memory_limit > 0 ? round(($current_usage / $memory_limit) * 100, 2) : null,
    ];
    
    // WordPress Autoloaded Options Size
    $autoload_size = self::get_autoloaded_options_size();
    $result['wordpress'] = [
      'autoloadedOptionsSize' => $autoload_size,
      'autoloadedOptionsSizeMB' => round($autoload_size / 1024 / 1024, 2),
    ];
    
    // OPcache Status
    if (function_exists('opcache_get_status')) {
      $opcache_status = opcache_get_status();
      if ($opcache_status !== false) {
        $memory = $opcache_status['memory_usage'] ?? [];
        $result['opcache'] = [
          'enabled' => true,
          'usedMemory' => $memory['used_memory'] ?? 0,
          'freeMemory' => $memory['free_memory'] ?? 0,
          'wastedMemory' => $memory['wasted_memory'] ?? 0,
          'wastedPercent' => isset($memory['used_memory'], $memory['wasted_memory']) && $memory['used_memory'] > 0 
            ? round(($memory['wasted_memory'] / ($memory['used_memory'] + $memory['wasted_memory'])) * 100, 2) 
            : 0,
          'hitRate' => isset($opcache_status['opcache_statistics']['opcache_hit_rate']) 
            ? round($opcache_status['opcache_statistics']['opcache_hit_rate'], 2) 
            : null,
        ];
      } else {
        $result['opcache'] = ['enabled' => false];
      }
    } else {
      $result['opcache'] = ['enabled' => false];
    }
    
    // Object Cache (Redis/Memcached) - WP Object Cache
    $result['objectCache'] = self::get_object_cache_info();
    
    // System Memory (Linux only - güvenli şekilde)
    $result['system'] = self::get_system_memory_info();
    
    return $result;
  }
  
  private static function parse_memory_limit(string $limit): int {
    if ($limit === '-1') return -1;
    
    $unit = strtolower(substr($limit, -1));
    $value = (int) $limit;
    
    switch ($unit) {
      case 'g': return $value * 1024 * 1024 * 1024;
      case 'm': return $value * 1024 * 1024;
      case 'k': return $value * 1024;
      default: return $value;
    }
  }
  
  private static function get_autoloaded_options_size(): int {
    global $wpdb;
    
    try {
      $result = $wpdb->get_var(
        "SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload = 'yes'"
      );
      return (int) ($result ?: 0);
    } catch (Exception $e) {
      return 0;
    }
  }
  
  private static function get_object_cache_info(): array {
    global $wp_object_cache;
    
    $info = ['type' => 'default'];
    
    if (isset($wp_object_cache) && is_object($wp_object_cache)) {
      $class = get_class($wp_object_cache);
      
      // Redis Object Cache
      if (strpos($class, 'Redis') !== false) {
        $info['type'] = 'redis';
        if (method_exists($wp_object_cache, 'redis_instance')) {
          try {
            $redis = $wp_object_cache->redis_instance();
            if ($redis && method_exists($redis, 'info')) {
              $redis_info = $redis->info('memory');
              $info['usedMemory'] = isset($redis_info['used_memory']) ? (int) $redis_info['used_memory'] : null;
              $info['maxMemory'] = isset($redis_info['maxmemory']) ? (int) $redis_info['maxmemory'] : null;
              $info['evictedKeys'] = isset($redis_info['evicted_keys']) ? (int) $redis_info['evicted_keys'] : null;
            }
          } catch (Exception $e) {
            // Redis bağlantı hatası - sessizce devam et
          }
        }
      }
      // Memcached
      elseif (strpos($class, 'Memcached') !== false || strpos($class, 'Memcache') !== false) {
        $info['type'] = 'memcached';
        // Memcached stats alımı daha karmaşık, şimdilik tip bilgisi yeterli
      }
    }
    
    return $info;
  }
  
  private static function get_system_memory_info(): array {
    $info = [];
    
    // Sadece Linux sistemlerde /proc/meminfo okumaya çalış
    if (PHP_OS_FAMILY === 'Linux' && is_readable('/proc/meminfo')) {
      try {
        $meminfo = file_get_contents('/proc/meminfo');
        if ($meminfo !== false) {
          // MemTotal ve MemAvailable'ı parse et
          if (preg_match('/MemTotal:\s+(\d+)\s+kB/', $meminfo, $matches)) {
            $info['totalRAM'] = (int) $matches[1] * 1024; // bytes
          }
          if (preg_match('/MemAvailable:\s+(\d+)\s+kB/', $meminfo, $matches)) {
            $info['availableRAM'] = (int) $matches[1] * 1024; // bytes
          }
          if (preg_match('/SwapTotal:\s+(\d+)\s+kB/', $meminfo, $matches)) {
            $info['totalSwap'] = (int) $matches[1] * 1024; // bytes
          }
          if (preg_match('/SwapFree:\s+(\d+)\s+kB/', $meminfo, $matches)) {
            $info['freeSwap'] = (int) $matches[1] * 1024; // bytes
          }
        }
      } catch (Exception $e) {
        // Sessizce devam et
      }
    }
    
    return $info;
  }
  
  private static function active_plugins_summary(int $limit = 10): array {
    if (!function_exists('get_plugins')) {
      require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    
    $all_plugins = get_plugins();
    $active_plugins = get_option('active_plugins', []);
    
    $active_list = [];
    foreach ($active_plugins as $plugin_file) {
      if (isset($all_plugins[$plugin_file])) {
        $plugin_data = $all_plugins[$plugin_file];
        
        // Plugin slug'ını plugin file'dan çıkar (folder/file.php -> folder)
        $plugin_slug = dirname($plugin_file);
        if ($plugin_slug === '.') {
          // Eğer tek dosyalık plugin ise (hello.php gibi)
          $plugin_slug = pathinfo($plugin_file, PATHINFO_FILENAME);
        }
        
        $active_list[] = [
          'name' => $plugin_data['Name'],
          'version' => $plugin_data['Version'],
          'file' => $plugin_file,
          'slug' => $plugin_slug,
          'author' => $plugin_data['Author'] ?? '',
          'description' => wp_trim_words($plugin_data['Description'] ?? '', 20),
        ];
      }
    }
    
    // Network aktif pluginları da ekle (multisite)
    if (is_multisite()) {
      $network_active = get_site_option('active_sitewide_plugins', []);
      foreach ($network_active as $plugin_file => $time) {
        if (isset($all_plugins[$plugin_file])) {
          $plugin_data = $all_plugins[$plugin_file];
          
          // Plugin slug'ını plugin file'dan çıkar
          $plugin_slug = dirname($plugin_file);
          if ($plugin_slug === '.') {
            $plugin_slug = pathinfo($plugin_file, PATHINFO_FILENAME);
          }
          
          $active_list[] = [
            'name' => $plugin_data['Name'] . ' (Network)',
            'version' => $plugin_data['Version'],
            'file' => $plugin_file,
            'slug' => $plugin_slug,
            'author' => $plugin_data['Author'] ?? '',
            'description' => wp_trim_words($plugin_data['Description'] ?? '', 20),
          ];
        }
      }
    }
    
    // Limitle ve sample al
    $sample = array_slice($active_list, 0, $limit);
    
    return [
      'activePlugins' => [
        'count' => count($active_list),
        'sample' => $sample
      ]
    ];
  }
  private static function update_counts(): array {
    if (!function_exists('get_core_updates')) {
      require_once ABSPATH . 'wp-admin/includes/update.php';
    }
    
    // WordPress core güncellemeleri
    $core_updates = get_core_updates();
    $core_count = 0;
    foreach ($core_updates as $update) {
      if ($update->response === 'upgrade') {
        $core_count++;
      }
    }
    
    // Plugin güncellemeleri
    $plugin_updates = get_site_transient('update_plugins');
    $plugin_count = isset($plugin_updates->response) ? count($plugin_updates->response) : 0;
    
    // Theme güncellemeleri
    $theme_updates = get_site_transient('update_themes');
    $theme_count = isset($theme_updates->response) ? count($theme_updates->response) : 0;
    
    return [
      'core' => $core_count,
      'plugins' => $plugin_count,
      'themes' => $theme_count
    ];
  }
}