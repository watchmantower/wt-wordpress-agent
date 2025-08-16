<?php
namespace WTHB;

if (!defined('ABSPATH')) exit;

class Heartbeat {
  const LOCK_KEY = 'wthb_hb_lock';

  public static function init() {
    // nothing for now
  }

  public static function send(bool $manual = false) {
    $opts     = Options::get_all();
    $token    = $opts['token'] ?? '';
    $endpoint = $opts['endpoint'] ?? Options::DEFAULT_ENDPOINT;

    if (empty($endpoint) || empty($token)) {
      self::save_error('Missing endpoint or token');
      return;
    }

    // --- Collect base ---
    $iid           = Options::get_instance_id();
    $theme         = wp_get_theme();
    $comments_open = get_option('default_comment_status') === 'open';
    global $wp_version;

    // --- Health: DB ---
    $db_ok = self::check_db();

    // --- Health: Cron overdue ---
    $cron_stats = self::cron_overdue_stats();

    // --- Health: REST loopback (/wp-json/) ---
    $rest_ok = self::rest_ok();

    // --- Security: XML-RPC enabled? ---
    $xmlrpc_enabled = self::xmlrpc_enabled();

    // --- Updates (core/plugins/themes) ---
    $updates = self::update_counts();

    // --- Inventory: active plugins (summary) ---
    $inventory = self::active_plugins_summary(10); // first 10 only

    $payload = [
      // identity
      'instanceId'    => $iid,
      'pluginVersion' => WTHB_VERSION,
      'sentAt'        => time(),
      'manual'        => $manual,

      // site / env
      'site' => [
        'homeUrl'   => home_url(),
        'siteUrl'   => site_url(),
        'multisite' => is_multisite(),
      ],

      // versions / basic status
      'wordpress' => [
        'wpVersion'   => $wp_version,
        'phpVersion'  => PHP_VERSION,
        'theme'       => [
          'name'    => $theme ? $theme->get('Name') : null,
          'version' => $theme ? $theme->get('Version') : null,
        ],
        'comments'    => [ 'enabled' => $comments_open ],
      ],

      // deep health
      'health' => [
        'db'      => [ 'ok' => $db_ok ],
        'cron'    => $cron_stats,      // { overdue, nextDueInSec }
        'rest'    => [ 'ok' => $rest_ok ],
        'updates' => $updates,         // { core, plugins, themes }
      ],

      'registration' => [
        'enabled' => (bool) get_option('users_can_register')
      ],

      // security posture bits
      'security' => [
        'xmlrpc' => [ 'enabled' => $xmlrpc_enabled ],
      ],

      // inventory (trimmed)
      'inventory' => $inventory,       // { activePlugins: { count, sample:[{name,version}] } }
    ];

    $args = [
      'headers' => [
        'Content-Type'  => 'application/json',
        'Authorization' => 'Bearer ' . $token,
      ],
      'timeout' => 12,
      'body'    => wp_json_encode($payload),
    ];

    $res = wp_remote_post($endpoint, $args);

    if (is_wp_error($res)) {
      self::save_error($res->get_error_message());
      return;
    }

    $code = wp_remote_retrieve_response_code($res);
    $body = wp_remote_retrieve_body($res);

    if ($code >= 200 && $code < 300) {
      $server = json_decode($body, true);
      if (isset($server['desiredIntervalSec'])) {
        $opts['interval_sec'] = max(60, intval($server['desiredIntervalSec']));
      }
      if (isset($server['pause'])) {
        $opts['pause'] = (bool)$server['pause'];
      }
      $opts['last_success_at'] = time();
      $opts['last_error']      = '';
      Options::update_all($opts);
    } else {
      $prev = intval($opts['interval_sec'] ?? Options::DEFAULT_INTERVAL);
      $back = min(3600, max(60, $prev * 2));
      $opts['interval_sec'] = $back;
      $opts['last_error']   = 'HTTP ' . $code . ' ' . substr($body, 0, 200);
      Options::update_all($opts);
    }
  }

  public static function with_lock(callable $fn) {
    if (get_transient(self::LOCK_KEY)) return;
    set_transient(self::LOCK_KEY, 1, 60);
    try { $fn(); } finally { delete_transient(self::LOCK_KEY); }
  }

  private static function save_error(string $msg) {
    $opts = Options::get_all();
    $opts['last_error'] = $msg;
    Options::update_all($opts);
  }

  /* ---------------- helpers ---------------- */

  private static function check_db(): bool {
    global $wpdb;
    if (!isset($wpdb)) return false;
    // WordPress 6.1+: check_connection() var; yoksa hızlı bir sorgu dene
    if (method_exists($wpdb, 'check_connection')) {
      return (bool) $wpdb->check_connection(false);
    }
    // Fallback: ping
    @ $ok = $wpdb->query('SELECT 1');
    return $ok !== false;
  }

  private static function cron_overdue_stats(): array {
    $cron = _get_cron_array();
    if (!is_array($cron) || empty($cron)) {
      return ['overdue' => 0, 'nextDueInSec' => null];
    }
    $now = time();
    $overdue = 0;
    $nextTs = null;
    foreach ($cron as $ts => $hooks) {
      if ($nextTs === null || $ts < $nextTs) $nextTs = $ts;
      if ($ts + 30 < $now) { // 30 sn tolerans
        $overdue += count($hooks);
      }
    }
    $nextDue = $nextTs ? max(0, $nextTs - $now) : null;
    return ['overdue' => $overdue, 'nextDueInSec' => $nextDue];
  }

  private static function rest_ok(): bool {
    // loopback GET to /wp-json/ (timeout small)
    $url = home_url('/wp-json/');
    $res = wp_remote_get($url, ['timeout' => 5, 'headers' => ['Accept' => 'application/json']]);
    if (is_wp_error($res)) return false;
    $code = wp_remote_retrieve_response_code($res);
    return $code >= 200 && $code < 300;
  }

  private static function xmlrpc_enabled(): bool {
    // WP core default filter; many hosts disable via filter
    $enabled = apply_filters('xmlrpc_enabled', true);
    return (bool) $enabled;
  }

  private static function active_plugins_summary(int $limit = 10): array {
    // Need plugin functions
    if (!function_exists('get_plugins')) {
      require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $active = get_option('active_plugins', []);
    $all    = function_exists('get_plugins') ? get_plugins() : [];
    $sample = [];
    foreach ($active as $file) {
      if (isset($all[$file])) {
        $sample[] = [
          'name'    => $all[$file]['Name'] ?? $file,
          'version' => $all[$file]['Version'] ?? null,
        ];
      } else {
        $sample[] = ['name' => $file, 'version' => null];
      }
      if (count($sample) >= $limit) break;
    }
    return [
      'activePlugins' => [
        'count'  => is_array($active) ? count($active) : 0,
        'sample' => $sample,
      ],
    ];
  }

  private static function update_counts(): array {
    // Load update API
    require_once ABSPATH . 'wp-admin/includes/update.php';
    wp_version_check();         // refresh core
    wp_update_plugins();        // refresh plugins
    wp_update_themes();         // refresh themes

    $core_updates = get_core_updates();
    $core_has = 0;
    if (is_array($core_updates)) {
      foreach ($core_updates as $u) {
        if (!empty($u->response) && $u->response !== 'latest') {
          $core_has = 1; break;
        }
      }
    }

    $plugin_updates = get_site_transient('update_plugins');
    $plugins_cnt = is_object($plugin_updates) && !empty($plugin_updates->response)
      ? count($plugin_updates->response) : 0;

    $theme_updates = get_site_transient('update_themes');
    $themes_cnt = is_object($theme_updates) && !empty($theme_updates->response)
      ? count($theme_updates->response) : 0;

    return [
      'core'    => $core_has,
      'plugins' => $plugins_cnt,
      'themes'  => $themes_cnt,
    ];
  }
}