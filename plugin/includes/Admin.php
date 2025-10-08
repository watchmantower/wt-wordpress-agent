<?php
namespace WTHB;

if (!defined('ABSPATH')) exit;

class Admin {
  private static $heartbeat_in_progress = false;
  
  public static function init(): void {
    add_action('admin_menu',               [__CLASS__, 'admin_menu']);
    add_action('admin_init',               [__CLASS__, 'register_settings']);
    add_action('admin_post_wthb_send_now', [__CLASS__, 'send_now_handler']);
    add_action('admin_post_wthb_unlink',   [__CLASS__, 'unlink_handler']);
    add_action('admin_notices',            [__CLASS__, 'notices']);
    add_action('wthb_immediate_heartbeat', [__CLASS__, 'immediate_heartbeat_handler']);
  }

  public static function admin_menu(): void {
    add_options_page(
      'Watchman Tower',
      'Watchman Tower',
      'manage_options',
      'wthb-settings',
      [__CLASS__, 'render_settings']
    );
  }

  public static function register_settings(): void {
    register_setting('wthb_settings', Options::OPT_KEY, [
      'sanitize_callback' => function ($new) {
        $old     = get_option(Options::OPT_KEY, []);
        $allowed = ['token','endpoint','interval_sec','pause'];
        $clean   = [];

        foreach ($allowed as $k) {
          if ($k === 'pause') {
            $clean['pause'] = !empty($new['pause']);
          } elseif ($k === 'interval_sec' && isset($new[$k])) {
            // Interval'i 60-3600 arasında sınırla
            $interval = max(60, min(3600, (int)$new[$k]));
            $clean['interval_sec'] = $interval;
          } elseif (isset($new[$k])) {
            $clean[$k] = $new[$k];
          }
        }
        
        return array_merge($old ?? [], $clean);
      }
    ]);

    add_action('update_option_' . Options::OPT_KEY, ['WTHB\Scheduler', 'reschedule_after_save'], 10, 2);
    add_action('update_option_' . Options::OPT_KEY, [__CLASS__, 'trigger_heartbeat_after_save'], 20, 2);
  }

  public static function render_settings(): void {
    if (!current_user_can('manage_options')) return;

    $opts     = Options::get_all();
    $iid      = Options::get_instance_id();
    $last_ok  = isset($opts['last_success_at']) ? date_i18n(get_option('date_format').' '.get_option('time_format'), (int)$opts['last_success_at']) : '—';
    $last_err = !empty($opts['last_error']) ? esc_html($opts['last_error']) : '—';

    $hasAgentActive   = !empty($opts['agent_jwt']) && !empty($opts['connected']);
    $hasAgentInactive = empty($opts['agent_jwt']) && !empty($opts['agent_jwt_inactive']);
    $field = Options::OPT_KEY;
    
    // Debug bilgileri (geliştirme aşamasında)
    if (isset($_GET['debug']) || isset($_GET['msg'])) {
      echo '<pre style="background:#f1f1f1;padding:10px;margin:10px 0;font-size:11px;">';
      echo 'Debug Info:' . "\n";
      echo 'agent_jwt: ' . (isset($opts['agent_jwt']) ? 'SET ('.strlen($opts['agent_jwt']).' chars)' : 'NOT SET') . "\n";
      echo 'connected: ' . (isset($opts['connected']) ? ($opts['connected'] ? 'TRUE' : 'FALSE') : 'NOT SET') . "\n";
      echo 'hasAgentActive: ' . ($hasAgentActive ? 'TRUE' : 'FALSE') . "\n";
      echo 'hasAgentInactive: ' . ($hasAgentInactive ? 'TRUE' : 'FALSE') . "\n";
      echo 'token: ' . (isset($opts['token']) ? 'SET ('.strlen($opts['token']).' chars)' : 'NOT SET') . "\n";
      if (isset($opts['last_unlinked_at'])) {
        echo 'last_unlinked_at: ' . date('Y-m-d H:i:s', $opts['last_unlinked_at']) . "\n";
      }
      echo 'All options: ' . json_encode($opts, JSON_PRETTY_PRINT) . "\n";
      
      // Log dosyasının son 20 satırını da göster
      $log_file = ini_get('error_log');
      if (!$log_file) {
        $log_file = ABSPATH . 'wp-content/debug.log';
      }
      if (file_exists($log_file)) {
        $lines = file($log_file);
        $recent_lines = array_slice($lines, -20);
        echo "\nRecent debug.log entries:\n";
        foreach ($recent_lines as $line) {
          if (strpos($line, 'WTHB') !== false || strpos($line, 'trigger_heartbeat') !== false || strpos($line, 'agentJwt') !== false) {
            echo htmlspecialchars($line);
          }
        }
      }
      echo '</pre>';
    }
    ?>

    <div class="wrap">
      <h1>Watchman Tower</h1>

      <?php if ($hasAgentActive): ?>
        <p><span class="dashicons dashicons-yes-alt" style="color:#46b450"></span>
          <strong>Connected</strong> — agent is paired and sending heartbeats.
        </p>
      <?php elseif ($hasAgentInactive): ?>
        <div class="notice notice-warning">
          <p><strong>Disconnected.</strong> A previous agent token exists but is inactive. Paste a new register token to reconnect.</p>
        </div>
      <?php else: ?>
        <div class="notice notice-success">
          <p><strong>Disconnected.</strong> Please paste a new register token to reconnect.</p>
        </div>
      <?php endif; ?>

      <form method="post" action="options.php">
        <?php settings_fields('wthb_settings'); ?>
        <table class="form-table" role="presentation">
          <?php if (!$hasAgentActive): ?>
            <tr>
              <th scope="row"><label>Integration Token (JWT)</label></th>
              <td>
                <input type="password"
                       name="<?php echo esc_attr($field); ?>[token]"
                       value="<?php echo esc_attr($opts['token'] ?? ''); ?>"
                       class="regular-text" placeholder="Paste your install token"/>
                <p class="description">Single-use token from Watchman Tower → Install flow.</p>
              </td>
            </tr>
          <?php endif; ?>

          <tr>
            <th scope="row"><label>Interval (seconds)</label></th>
            <td>
              <input type="number" name="<?php echo esc_attr($field); ?>[interval_sec]"
                     value="<?php echo esc_attr($opts['interval_sec']); ?>" min="60" max="3600" step="60"/>
              <p class="description">Min 60s, Max 3600s (1 hour). Server may override.</p>
            </td>
          </tr>

          <tr>
            <th scope="row">Pause</th>
            <td>
              <label><input type="checkbox" name="<?php echo esc_attr($field); ?>[pause]" <?php checked(!empty($opts['pause'])); ?> /> Pause sending</label>
            </td>
          </tr>

          <tr><th scope="row">Instance ID</th><td><code><?php echo esc_html($iid); ?></code></td></tr>
          <tr><th scope="row">Last success</th><td><?php echo $last_ok; ?></td></tr>
          <tr><th scope="row">Last error</th><td><code><?php echo $last_err; ?></code></td></tr>
        </table>
        <?php submit_button('Save'); ?>
      </form>

      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-top:12px;">
        <?php wp_nonce_field('wthb_send_now'); ?>
        <input type="hidden" name="action" value="wthb_send_now"/>
        <?php submit_button('Send now', 'secondary'); ?>
      </form>

      <?php if ($hasAgentActive): ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline-block;margin-left:8px;">
          <?php wp_nonce_field('wthb_unlink'); ?>
          <input type="hidden" name="action" value="wthb_unlink"/>
          <?php submit_button('Unlink / Reconnect', 'delete'); ?>
        </form>
      <?php endif; ?>
    </div>
    <?php
  }

  public static function trigger_heartbeat_after_save($old_value, $new_value): void {
    // Debug: Hangi değerlerle çalışıyoruz?
    error_log('trigger_heartbeat_after_save called');
    error_log('old_value: ' . json_encode($old_value));
    error_log('new_value: ' . json_encode($new_value));
    error_log('heartbeat_in_progress flag: ' . (self::$heartbeat_in_progress ? 'true' : 'false'));
    
    // Eğer heartbeat süreci içindeyse sonsuz döngüyü önle
    if (self::$heartbeat_in_progress) {
      error_log('trigger_heartbeat_after_save: Skipping due to heartbeat in progress');
      return;
    }
    
    // Token varsa ve agent_jwt yoksa heartbeat gönder (daha gevşek kontrol)
    if (!empty($new_value['token']) && empty($new_value['agent_jwt'])) {
      error_log('Token present but no agent_jwt, sending heartbeat');
      
      // Cache'i temizle
      wp_cache_delete(Options::OPT_KEY, 'options');
      wp_cache_delete('alloptions', 'options');
      
      // Flag set et ve try-finally ile kesin temizlik garantisi
      self::$heartbeat_in_progress = true;
      
      try {
        // Heartbeat'i çalıştır
        if (class_exists('\WTHB\Heartbeat')) {
          \WTHB\Heartbeat::send(false);
        }
      } finally {
        // Flag'i kesinlikle temizle
        self::$heartbeat_in_progress = false;
        error_log('heartbeat_in_progress flag cleared');
      }
    } else {
      error_log('Not sending heartbeat - token: ' . (!empty($new_value['token']) ? 'YES' : 'NO') . 
                ', agent_jwt: ' . (!empty($new_value['agent_jwt']) ? 'YES' : 'NO'));
    }
  }

  public static function send_now_handler(): void {
    if (!current_user_can('manage_options')) wp_die();
    check_admin_referer('wthb_send_now');
    Heartbeat::send(true);
    if (class_exists('\WTHB\Scheduler')) \WTHB\Scheduler::schedule_in(10);
    wp_safe_redirect(admin_url('options-general.php?page=wthb-settings'));
    exit;
  }

  public static function unlink_handler(): void {
    if (!current_user_can('manage_options')) wp_die();
    check_admin_referer('wthb_unlink');

    // Önce server'a bildir
    $ok = Heartbeat::notify_unlink('manual');
    
    // Pairing'i temizle
    Options::clear_pairing();
    
    // Scheduler'ı durdurmak için tüm cron job'ları temizle
    wp_clear_scheduled_hook('wthb_heartbeat');
    wp_clear_scheduled_hook('wthb_immediate_heartbeat');
    
    // Cache'i zorla temizle
    wp_cache_flush();
    
    $msg = $ok ? 'unlinked' : 'unlink_failed';
    wp_safe_redirect(admin_url('options-general.php?page=wthb-settings&msg=' . $msg));
    exit;
  }

  public static function immediate_heartbeat_handler(): void {
    if (class_exists('\WTHB\Heartbeat')) {
      \WTHB\Heartbeat::send(false);
    }
  }

  public static function notices(): void {
    if (!current_user_can('manage_options')) return;
    if (!isset($_GET['page']) || $_GET['page'] !== 'wthb-settings') return;

    $opts = Options::get_all();
    $hasAgentActive = !empty($opts['agent_jwt']) && !empty($opts['connected']);
    if ($hasAgentActive) return;

    $msg = isset($_GET['msg']) ? sanitize_text_field($_GET['msg']) : '';
    if ($msg === 'unlinked') {
      echo '<div class="notice notice-success is-dismissible"><p><strong>Disconnected.</strong> Please paste a new register token to reconnect.</p></div>';
    } elseif ($msg === 'unlink_failed') {
      echo '<div class="notice notice-warning is-dismissible"><p><strong>Disconnected locally.</strong> Server could not be notified. You may reconnect safely.</p></div>';
    }
  }
}