<?php

namespace WTHB\includes\ajax;

use WTHB\models\Options;

/**
 * Handles fetching site status from Watchman Tower API.
 */
class SiteStatus
{
    /**
     * Register AJAX hooks.
     *
     * @return void
     */
    public static function register(): void
    {
        add_action('wp_ajax_wthb_get_site_status', [__CLASS__, 'handle']);
    }

    /**
     * Handle site status request.
     *
     * @return void
     */
    public static function handle(): void
    {
        check_ajax_referer('wthb_site_status_nonce', '_ajax_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'watchman-tower')]);
        }

        $opts = Options::get_all();
        $agent_jwt = $opts['agent_jwt'];
        $token = $opts['token'];
        $hmac_secret = $opts['hmac_secret'] ?? '';
        $site_id = $opts['site_id'] ?? '';

        if (empty($site_id)) {
            wp_send_json_error(['message' => __('Site not connected.', 'watchman-tower')]);
        }

        $endpoint = apply_filters('wthb_endpoint_site_status', Options::ENDPOINT_SITE_STATUS);
        $url = add_query_arg('siteId', $site_id, $endpoint);

        $headers = [
            'Accept' => 'application/json',
            'X-Client-Platform' => 'wordpress',
        ];

        // Use HMAC if available, otherwise Bearer token
        if (!empty($hmac_secret) && !empty($site_id)) {
            $timestamp = time();
            $method = 'GET';

            // Parse URL for path and query
            $parts = wp_parse_url($url);
            $path = $parts['path'] ?? '/';
            $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
            $path_with_query = $path . $query;
            $context = 'WP_TO_METRIC';

            // HMAC message for GET: CONTEXT \n METHOD \n PATH \n TIMESTAMP \n BODY (empty for GET)
            $message = $context . "\n" . $method . "\n" . $path_with_query . "\n" . $timestamp . "\n";

            $signature = hash_hmac('sha256', $message, $hmac_secret);

            $headers['X-WT-Key'] = $site_id;
            $headers['X-WT-Timestamp'] = (string) $timestamp;
            $headers['X-WT-Signature'] = $signature;
        } else {
            // Fallback: Bearer token authentication
            $auth_token = !empty($agent_jwt) ? $agent_jwt : $token;
            $headers['Authorization'] = 'Bearer ' . $auth_token;
        }

        $response = wp_remote_get($url, [
            'headers' => $headers,
            'timeout' => 15,
            'sslverify' => true,
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message()]);
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code < 200 || $code >= 300) {
            /* translators: %d: HTTP response code */
            $msg = isset($data['message']) ? $data['message'] : sprintf(__('HTTP %d response', 'watchman-tower'), $code);
            wp_send_json_error(['message' => $msg]);
        }

        if (!is_array($data)) {
            wp_send_json_error(['message' => __('Invalid response from server.', 'watchman-tower')]);
        }

        wp_send_json_success($data);
    }
}
