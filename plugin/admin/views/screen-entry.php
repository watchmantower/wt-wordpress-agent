<?php
/**
 * Entry screen - shown when not connected and no mode selected.
 *
 * @var array $opts Current plugin options
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap wthb-settings">
    <h1><?php echo esc_html__('Watchman Tower', 'watchman-tower'); ?></h1>

    <div class="wthb-card">
        <h2><?php echo esc_html__('Connect Your WordPress Site', 'watchman-tower'); ?></h2>
        <p><?php echo esc_html__('Get started with Watchman Tower monitoring in just a few steps.', 'watchman-tower'); ?></p>

        <div class="wthb-actions">
            <a href="<?php echo esc_url(admin_url('options-general.php?page=wthb-settings&mode=existing')); ?>" class="button button-primary button-large">
                <?php echo esc_html__('I Already Have an Account', 'watchman-tower'); ?>
            </a>

            <a href="<?php echo esc_url(admin_url('options-general.php?page=wthb-settings&mode=create')); ?>" class="button button-secondary button-large">
                <?php echo esc_html__('Create New Account', 'watchman-tower'); ?>
            </a>
        </div>

        <div class="wthb-info">
            <h3><?php echo esc_html__('Why Watchman Tower?', 'watchman-tower'); ?></h3>
            <ul>
                <li><?php echo esc_html__('Monitor uptime and performance', 'watchman-tower'); ?></li>
                <li><?php echo esc_html__('Get instant alerts when issues occur', 'watchman-tower'); ?></li>
                <li><?php echo esc_html__('Track WordPress core, theme, and plugin updates', 'watchman-tower'); ?></li>
                <li><?php echo esc_html__('View comprehensive site health metrics', 'watchman-tower'); ?></li>
            </ul>
            
            <p style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #dcdcde;">
                <a href="https://docs.watchmantower.com/wordpress-agent/installation" target="_blank" rel="noopener noreferrer" class="wthb-help-link">
                    <span class="dashicons dashicons-book" style="font-size: 16px; vertical-align: middle;"></span>
                    <?php echo esc_html__('Setup Guide & Documentation', 'watchman-tower'); ?>
                    <span class="dashicons dashicons-external" style="font-size: 14px; vertical-align: middle;"></span>
                </a>
            </p>
        </div>
    </div>
</div>
