<?php

namespace WTHB\admin;

use WTHB\includes\ajax\Signup;
use WTHB\includes\ajax\Token;
use WTHB\includes\ajax\HeartbeatAjax;
use WTHB\includes\ajax\Connection;
use WTHB\includes\ajax\Unlink;
use WTHB\includes\ajax\SaveSettings;
use WTHB\includes\ajax\SiteStatus;
use WTHB\models\Options;

/**
 * Admin area initialization and management.
 */
class Admin
{
    /**
     * Initialize admin hooks.
     *
     * @return void
     */
    public function init(): void
    {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);

        Signup::register();
        Token::register();
        HeartbeatAjax::register();
        Connection::register();
        Unlink::register();
        SaveSettings::register();
        SiteStatus::register();
    }

    /**
     * Register settings menu page.
     *
     * @return void
     */
    public function register_menu(): void
    {
        add_options_page(
            __('Watchman Tower', 'watchman-tower'),
            __('Watchman Tower', 'watchman-tower'),
            'manage_options',
            'wthb-settings',
            [$this, 'render_page']
        );
    }

    /**
     * Render settings page.
     *
     * @return void
     */
    public function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'watchman-tower'));
        }

        $controller = new SettingsController();
        $controller->handle();
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook
     * @return void
     */
    public function enqueue_assets(string $hook): void
    {
        if ($hook !== 'settings_page_wthb-settings') {
            return;
        }

        wp_enqueue_style(
            'wthb-admin',
            WTHB_PLUGIN_URL . 'assets/css/admin.css',
            [],
            WTHB_VERSION
        );

        $opts = Options::get_all();
        $mode = isset($_GET['mode']) ? sanitize_text_field(wp_unslash($_GET['mode'])) : '';

        $js_file = 'shared.js';

        if (Options::is_connected($opts)) {
            $js_file = 'connected.js';
        } elseif ($mode === 'existing') {
            $js_file = 'existing.js';
        } elseif ($mode === 'create') {
            $js_file = 'create.js';
        } elseif ($mode === 'connecting') {
            $js_file = 'connecting.js';
        } elseif ($mode === 'disconnected') {
            $js_file = 'disconnected.js';
        } else {
            $js_file = 'entry.js';
        }

        wp_enqueue_script(
            'wthb-shared',
            WTHB_PLUGIN_URL . 'assets/js/shared.js',
            ['jquery'],
            WTHB_VERSION,
            true
        );

        if ($js_file !== 'shared.js') {
            wp_enqueue_script(
                'wthb-screen',
                WTHB_PLUGIN_URL . 'assets/js/' . $js_file,
                ['jquery', 'wthb-shared'],
                WTHB_VERSION,
                true
            );
        }

        $localized_data = [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonces' => [
                'signup' => wp_create_nonce('wthb_signup_nonce'),
                'token' => wp_create_nonce('wthb_token_nonce'),
                'heartbeat' => wp_create_nonce('wthb_heartbeat_nonce'),
                'connection' => wp_create_nonce('wthb_connection_nonce'),
                'unlink' => wp_create_nonce('wthb_unlink_nonce'),
                'settings' => wp_create_nonce('wthb_settings_nonce'),
                'siteStatus' => wp_create_nonce('wthb_site_status_nonce'),
            ],
            'intervalSec' => (int) $opts['interval_sec'],
            'pause' => (bool) $opts['pause'],
            'settingsUrl' => admin_url('options-general.php?page=wthb-settings'),
        ];

        wp_localize_script('wthb-shared', 'wthbData', $localized_data);

        if (Options::is_connected($opts)) {
            wp_localize_script('wthb-screen', 'wthbConnected', [
                'intervalSec' => (int) $opts['interval_sec'],
                'pause' => (bool) $opts['pause'],
            ]);
        }
    }
}
