<?php
/**
 * Create account screen - sign up for new Watchman Tower account.
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
        <h2><?php echo esc_html__('Create New Account', 'watchman-tower'); ?></h2>
        <p><?php echo esc_html__('Sign up for Watchman Tower and start monitoring your WordPress site.', 'watchman-tower'); ?></p>

        <div class="notice notice-info inline">
            <p>
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: %s: this WordPress site's home URL */
                        __('This account will be created for this WordPress install: %s. Keep the generated token tied to this same site/domain.', 'watchman-tower'),
                        home_url()
                    )
                );
                ?>
            </p>
        </div>

        <div id="wthb-status"></div>

        <div class="wthb-form-group">
            <label for="wthb-email"><?php echo esc_html__('Email Address', 'watchman-tower'); ?></label>
            <input 
                type="email" 
                id="wthb-email" 
                class="regular-text" 
                value="<?php echo esc_attr(get_option('admin_email')); ?>"
                placeholder="<?php echo esc_attr__('your@email.com', 'watchman-tower'); ?>"
                required
            />
        </div>

        <div class="wthb-form-group">
            <label for="wthb-password"><?php echo esc_html__('Password (Optional)', 'watchman-tower'); ?></label>
            <input 
                type="password" 
                id="wthb-password" 
                class="regular-text" 
                placeholder="<?php echo esc_attr__('Leave empty to auto-generate', 'watchman-tower'); ?>"
            />
            <p class="description">
                <?php echo esc_html__('If not provided, a password will be automatically generated and sent to your email.', 'watchman-tower'); ?>
            </p>
        </div>

        <div class="wthb-form-group">
            <label for="wthb-site-name"><?php echo esc_html__('Site Name', 'watchman-tower'); ?></label>
            <input 
                type="text" 
                id="wthb-site-name" 
                class="regular-text" 
                value="<?php echo esc_attr(get_bloginfo('name')); ?>"
            />
        </div>

        <div class="wthb-form-group">
            <label for="wthb-site-url"><?php echo esc_html__('Site URL', 'watchman-tower'); ?></label>
            <input 
                type="url" 
                id="wthb-site-url" 
                class="regular-text" 
                value="<?php echo esc_attr(home_url()); ?>"
                readonly
            />
        </div>

        <div class="wthb-actions">
            <button type="button" id="wthb-signup-btn" class="button button-primary">
                <?php echo esc_html__('Create Account', 'watchman-tower'); ?>
            </button>

            <a href="<?php echo esc_url(admin_url('options-general.php?page=wthb-settings')); ?>" class="button button-link">
                <?php echo esc_html__('Back', 'watchman-tower'); ?>
            </a>
        </div>

        <div id="wthb-token-display" style="display: none;">
            <hr />
            <h3><?php echo esc_html__('Your Register Token', 'watchman-tower'); ?></h3>
            <p><?php echo esc_html__('Copy this token and save it somewhere safe. You will need it to connect other sites.', 'watchman-tower'); ?></p>
            <div class="wthb-token-box">
                <code id="wthb-token-value"></code>
            </div>
            <button type="button" id="wthb-continue-btn" class="button button-primary">
                <?php echo esc_html__('Continue to Dashboard', 'watchman-tower'); ?>
            </button>
        </div>
    </div>
</div>
