<?php
/**
 * Connected screen - main dashboard when site is connected.
 *
 * @var array $opts Current plugin options
 */

if (!defined('ABSPATH')) {
    exit;
}

use WTHB\models\Options;

$instance_id = Options::get_instance_id();
$last_success_formatted = '';
$last_success_relative = '';

if (!empty($opts['last_success'])) {
    $last_success_formatted = date_i18n('Y-m-d H:i:s', $opts['last_success']);
    $last_success_relative = human_time_diff($opts['last_success'], current_time('timestamp')) . ' ' . __('ago', 'watchman-tower');
}
?>

<div class="wrap wthb-settings">
    <h1><?php echo esc_html__('Watchman Tower', 'watchman-tower'); ?></h1>

    <div class="wthb-cards-row">
        <div class="wthb-card wthb-card-main">
        <div class="wthb-status-badge wthb-status-connected">
            <?php echo esc_html__('Connected', 'watchman-tower'); ?>
        </div>

        <h2><?php echo esc_html__('Site Monitoring Active', 'watchman-tower'); ?></h2>
        <p><?php echo esc_html__('Your WordPress site is connected to Watchman Tower and sending heartbeat data.', 'watchman-tower'); ?></p>

        <div id="wthb-status"></div>

        <div class="wthb-info-grid">
            <div class="wthb-info-item">
                <label><?php echo esc_html__('Instance ID', 'watchman-tower'); ?></label>
                <code><?php echo esc_html($instance_id); ?></code>
            </div>

            <?php if (!empty($opts['site_id'])): ?>
            <div class="wthb-info-item">
                <label><?php echo esc_html__('Site ID', 'watchman-tower'); ?></label>
                <code><?php echo esc_html($opts['site_id']); ?></code>
            </div>
            <?php endif; ?>

            <div class="wthb-info-item">
                <label><?php echo esc_html__('Last Successful Heartbeat', 'watchman-tower'); ?></label>
                <?php if (!empty($last_success_formatted)): ?>
                    <span><?php echo esc_html($last_success_formatted); ?></span>
                    <small>(<?php echo esc_html($last_success_relative); ?>)</small>
                <?php else: ?>
                    <span><?php echo esc_html__('No heartbeat sent yet', 'watchman-tower'); ?></span>
                <?php endif; ?>
            </div>

            <?php if (!empty($opts['last_error'])): ?>
            <div class="wthb-info-item wthb-error">
                <label><?php echo esc_html__('Last Error', 'watchman-tower'); ?></label>
                <span><?php echo esc_html($opts['last_error']); ?></span>
            </div>
            <?php endif; ?>
        </div>

        <hr />

        <h3><?php echo esc_html__('Settings', 'watchman-tower'); ?></h3>

        <div class="wthb-form-group">
            <label for="wthb-interval"><?php echo esc_html__('Heartbeat Interval (seconds)', 'watchman-tower'); ?></label>
            <input 
                type="number" 
                id="wthb-interval" 
                class="small-text" 
                value="<?php echo esc_attr($opts['interval_sec']); ?>"
                min="60"
                max="3600"
            />
            <p class="description">
                <?php echo esc_html__('How often to send heartbeat data (60-3600 seconds).', 'watchman-tower'); ?>
            </p>
        </div>

        <div class="wthb-form-group">
            <label>
                <input 
                    type="checkbox" 
                    id="wthb-pause" 
                    <?php checked($opts['pause'], true); ?>
                />
                <?php echo esc_html__('Pause heartbeat monitoring', 'watchman-tower'); ?>
            </label>
        </div>

        <div class="wthb-actions">
            <button type="button" id="wthb-save-settings" class="button button-primary">
                <?php echo esc_html__('Save Settings', 'watchman-tower'); ?>
            </button>

            <?php // 2.0.0'dan beri readme'de vaat ediliyordu ama arayuze hic
                  // konmamisti: ajax islegi (wthb_trigger_heartbeat) ve nonce
                  // bastan beri hazirdi, tek cagirani ise WT-tetikli moda
                  // gecerken kapatilan JS oto-dongusuydu. ?>
            <button type="button" id="wthb-send-heartbeat-btn" class="button button-secondary">
                <?php echo esc_html__('Send Heartbeat Now', 'watchman-tower'); ?>
            </button>

            <button type="button" id="wthb-unlink-btn" class="button button-secondary">
                <?php echo esc_html__('Unlink Site', 'watchman-tower'); ?>
            </button>
        </div>
    </div>

    <?php
    if (!empty($opts['site_id'])) {
        require WTHB_PLUGIN_PATH . 'admin/views/components/quick-actions.php';
    }
    ?>
    </div>
</div>
