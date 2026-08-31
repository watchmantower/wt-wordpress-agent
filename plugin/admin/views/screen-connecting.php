<?php
/**
 * Connecting screen - transitional state while connecting.
 *
 * @var array $opts Current plugin options
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap wthb-settings">
    <h1><?php echo esc_html__('Watchman Tower', 'watchman-tower'); ?></h1>

    <div class="wthb-card wthb-text-center">
        <div class="wthb-spinner"></div>
        <h2><?php echo esc_html__('Connecting...', 'watchman-tower'); ?></h2>
        <p><?php echo esc_html__('Please wait while we establish a connection to Watchman Tower.', 'watchman-tower'); ?></p>
    </div>
</div>
