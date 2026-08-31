<?php
/**
 * Existing account screen - enter register token.
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
        <h2><?php echo esc_html__('Connect Existing Account', 'watchman-tower'); ?></h2>
        <p><?php echo esc_html__('Enter your register token from Watchman Tower to connect this site.', 'watchman-tower'); ?></p>

        <div class="notice notice-warning inline">
            <p>
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: %s: this WordPress site's home URL */
                        __('You are connecting this WordPress install: %s. Only use a register token created for this exact site/domain.', 'watchman-tower'),
                        home_url()
                    )
                );
                ?>
            </p>
        </div>

        <div id="wthb-status"></div>

        <div class="wthb-form-group">
            <label for="wthb-token"><?php echo esc_html__('Register Token', 'watchman-tower'); ?></label>
            <input 
                type="password" 
                id="wthb-token" 
                class="regular-text" 
                placeholder="<?php echo esc_attr__('Paste your token here', 'watchman-tower'); ?>"
            />
            <p class="description">
                <?php echo esc_html__('You can find this token in your Watchman Tower dashboard. If the token was created for another domain, connection will be rejected.', 'watchman-tower'); ?>
            </p>
        </div>

        <div class="wthb-actions">
            <button type="button" id="wthb-connect-btn" class="button button-primary">
                <?php echo esc_html__('Connect', 'watchman-tower'); ?>
            </button>

            <a href="<?php echo esc_url(admin_url('options-general.php?page=wthb-settings')); ?>" class="button button-link">
                <?php echo esc_html__('Back', 'watchman-tower'); ?>
            </a>
        </div>
    </div>
</div>
