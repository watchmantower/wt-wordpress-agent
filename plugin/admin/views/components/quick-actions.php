<?php
/**
 * Quick Actions sidebar component for the connected screen.
 *
 * @var array $opts Current plugin options
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wthb-card wthb-card-sidebar wthb-quick-actions">
    <h2><?php echo esc_html__('Quick Actions', 'watchman-tower'); ?></h2>

    <div id="wthb-site-status-container" data-site-id="<?php echo esc_attr($opts['site_id']); ?>">
        <p class="wthb-loading-status">
            <span class="spinner is-active" style="float: none; margin: 0;"></span>
            <?php echo esc_html__('Loading status...', 'watchman-tower'); ?>
        </p>
    </div>

    <p style="margin-top: 20px;">
        <a href="<?php echo esc_url('https://app.watchmantower.com/monitoring/' . sanitize_text_field($opts['site_id'])); ?>"
           target="_blank"
           rel="noopener noreferrer"
           class="button button-primary button-large">
            <?php echo esc_html__('View Dashboard', 'watchman-tower'); ?>
            <span class="dashicons dashicons-external" style="margin-top: 3px;"></span>
        </a>
    </p>
</div>
