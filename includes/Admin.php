<?php
namespace WTHB;

if (!defined('ABSPATH')) exit;

class Admin {
  public static function init() {
    add_action('admin_menu', [__CLASS__, 'admin_menu']);
    add_action('admin_init', [__CLASS__, 'register_settings']);
    add_action('admin_post_wthb_send_now', [__CLASS__, 'send_now_handler']);
  }

  public static function admin_menu() {
    add_options_page('Watchman Tower', 'Watchman Tower', 'manage_options', 'wthb-settings', [__CLASS__, 'render_settings']);
  }

  public static function register_settings() {
    register_setting('wthb_settings', Options::OPT_KEY);
    // reschedule strictly after settings save
    add_action('update_option_' . Options::OPT_KEY, ['WTHB\Scheduler', 'reschedule_after_save'], 10, 2);
  }

  public static function render_settings() {
    if (!current_user_can('manage_options')) return;

    $opts = Options::get_all();
    $iid  = Options::get_instance_id();
    $last_ok  = isset($opts['last_success_at']) ? date_i18n(get_option('date_format').' '.get_option('time_format'), intval($opts['last_success_at'])) : '—';
    $last_err = !empty($opts['last_error']) ? esc_html($opts['last_error']) : '—';

    ?>
    <div class="wrap">
      <h1>Watchman Tower</h1>
      <form method="post" action="options.php">
        <?php settings_fields('wthb_settings'); ?>
        <?php $field = Options::OPT_KEY; ?>
        <table class="form-table" role="presentation">
          <tr>
            <th scope="row"><label>Integration Token (JWT)</label></th>
            <td>
              <input type="password" name="<?php echo esc_attr($field); ?>[token]" value="<?php echo esc_attr($opts['token'] ?? ''); ?>" class="regular-text" placeholder="Paste your token"/>
              <p class="description">Single-line token from Watchman Tower → Install flow.</p>
            </td>
          </tr>
          <tr>
            <th scope="row"><label>Endpoint</label></th>
            <td>
              <input type="text" name="<?php echo esc_attr($field); ?>[endpoint]" value="<?php echo esc_attr($opts['endpoint']); ?>" class="regular-text code"/>
              <p class="description">Default: <?php echo esc_html(Options::DEFAULT_ENDPOINT); ?></p>
            </td>
          </tr>
          <tr>
            <th scope="row"><label>Interval (seconds)</label></th>
            <td>
              <input type="number" name="<?php echo esc_attr($field); ?>[interval_sec]" value="<?php echo esc_attr($opts['interval_sec']); ?>" min="60" step="60"/>
              <p class="description">Default 600s (10 min). Server may override with a different desired interval.</p>
            </td>
          </tr>
          <tr>
            <th scope="row">Pause</th>
            <td>
              <label><input type="checkbox" name="<?php echo esc_attr($field); ?>[pause]" <?php checked(!empty($opts['pause'])); ?> /> Pause sending</label>
              <p class="description">When checked, no data is sent.</p>
            </td>
          </tr>
          <tr>
            <th scope="row">Instance ID</th>
            <td><code><?php echo esc_html($iid); ?></code></td>
          </tr>
          <tr>
            <th scope="row">Last success</th>
            <td><?php echo $last_ok; ?></td>
          </tr>
          <tr>
            <th scope="row">Last error</th>
            <td><code><?php echo $last_err; ?></code></td>
          </tr>
        </table>
        <?php submit_button('Save'); ?>
      </form>

      <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:12px;">
        <?php wp_nonce_field('wthb_send_now'); ?>
        <input type="hidden" name="action" value="wthb_send_now"/>
        <?php submit_button('Send now', 'secondary'); ?>
      </form>
    </div>
    <?php
  }

  public static function send_now_handler() {
    if (!current_user_can('manage_options')) wp_die();
    check_admin_referer('wthb_send_now');

    Heartbeat::send(true); // manual
    Scheduler::schedule_in(10); // ensure next run exists in 10s

    wp_safe_redirect(admin_url('options-general.php?page=wthb-settings'));
    exit;
  }
}