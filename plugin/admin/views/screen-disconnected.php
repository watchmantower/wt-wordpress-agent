<?php
/**
 * Disconnected screen - shown after unlink or failed connection.
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
        <div class="wthb-status-badge wthb-status-disconnected">
            <?php echo esc_html__('Disconnected', 'watchman-tower'); ?>
        </div>

        <h2><?php echo esc_html__('Site Not Connected', 'watchman-tower'); ?></h2>
        <p><?php echo esc_html__('This site is not currently connected to Watchman Tower.', 'watchman-tower'); ?></p>

        <?php if (!empty($opts['last_error'])): ?>
        <div class="notice notice-error inline">
            <p><strong><?php echo esc_html__('Last Error:', 'watchman-tower'); ?></strong> <?php echo esc_html($opts['last_error']); ?></p>
        </div>
        <?php endif; ?>

        <div class="wthb-actions">
            <a href="<?php echo esc_url(admin_url('options-general.php?page=wthb-settings&mode=existing')); ?>" class="button button-primary">
                <?php echo esc_html__('Reconnect with Token', 'watchman-tower'); ?>
            </a>

            <a href="<?php echo esc_url(admin_url('options-general.php?page=wthb-settings&mode=create')); ?>" class="button button-secondary">
                <?php echo esc_html__('Create New Account', 'watchman-tower'); ?>
            </a>

            <a href="<?php echo esc_url(admin_url('options-general.php?page=wthb-settings')); ?>" class="button button-link">
                <?php echo esc_html__('Back to Start', 'watchman-tower'); ?>
            </a>
        </div>
    </div>
</div>
