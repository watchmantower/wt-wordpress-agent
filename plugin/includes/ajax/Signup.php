<?php

namespace WTHB\includes\ajax;

use WTHB\models\Options;

/**
 * Handles user signup AJAX action.
 */
class Signup
{
    /**
     * Register AJAX hooks.
     *
     * @return void
     */
    public static function register(): void
    {
        add_action('wp_ajax_wthb_signup', [__CLASS__, 'handle']);
    }

    /**
     * Handle signup request.
     *
     * @return void
     */
    public static function handle(): void
    {
        check_ajax_referer('wthb_signup_nonce', '_ajax_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'watchman-tower')]);
        }

        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $password = isset($_POST['password']) ? sanitize_text_field(wp_unslash($_POST['password'])) : '';
        $site_url = isset($_POST['site_url']) ? esc_url_raw(wp_unslash($_POST['site_url'])) : '';
        $site_name = isset($_POST['site_name']) ? sanitize_text_field(wp_unslash($_POST['site_name'])) : '';

        if (empty($email) || !is_email($email)) {
            wp_send_json_error(['message' => __('Valid email is required.', 'watchman-tower')]);
        }

        $endpoint = apply_filters('wthb_endpoint_signup', Options::ENDPOINT_SIGNUP);

        $payload = [
            'email' => $email,
            'siteUrl' => $site_url,
            'siteName' => $site_name,
        ];

        if (!empty($password)) {
            $payload['password'] = $password;
        }

        $response = wp_remote_post($endpoint, [
            'headers' => [
                'Content-Type' => 'application/json; charset=UTF-8',
                'Accept' => 'application/json',
                'X-Client-Platform' => 'wordpress',
            ],
            'body' => wp_json_encode($payload),
            'timeout' => 15,
            'sslverify' => true,
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message()]);
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!is_array($data)) {
            wp_send_json_error(['message' => __('Invalid response from server.', 'watchman-tower')]);
        }

        if (empty($data['success']) || $data['success'] === false) {
            $msg = isset($data['message']) ? $data['message'] : __('Account creation failed.', 'watchman-tower');
            wp_send_json_error(['message' => $msg]);
        }

        $token = null;
        if (isset($data['data']['token'])) {
            $token = $data['data']['token'];
        }

        wp_send_json_success([
            'token' => $token,
            'message' => isset($data['message']) ? $data['message'] : __('Account created successfully.', 'watchman-tower'),
        ]);
    }
}
