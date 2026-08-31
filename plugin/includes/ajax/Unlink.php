<?php

namespace WTHB\includes\ajax;

use WTHB\core\Scheduler;
use WTHB\models\Options;

/**
 * Handles unlinking site from Watchman Tower via AJAX.
 */
class Unlink
{
    /**
     * Register AJAX hooks.
     *
     * @return void
     */
    public static function register(): void
    {
        add_action('wp_ajax_wthb_unlink', [__CLASS__, 'handle']);
    }

    /**
     * Handle unlink request.
     *
     * @return void
     */
    public static function handle(): void
    {
        check_ajax_referer('wthb_unlink_nonce', '_ajax_nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'watchman-tower')]);
        }

        $opts = Options::get_all();
        $agent_jwt = $opts['agent_jwt'];
        $token = $opts['token'];
        $hmac_secret = $opts['hmac_secret'] ?? '';
        $site_id = $opts['site_id'] ?? '';
        $iid = Options::get_instance_id();

        $server_ok = false;

        // Use agent_jwt, token, or hmac_secret - whichever is available
        $auth_token = !empty($agent_jwt) ? $agent_jwt : $token;

        if (!empty($auth_token) || !empty($hmac_secret)) {
            $endpoint = apply_filters('wthb_endpoint_unlink', Options::ENDPOINT_UNLINK);

            $request_body = [
                'siteId' => $site_id,
                'instanceId' => $iid,
            ];

            $headers = [
                'Content-Type' => 'application/json; charset=UTF-8',
                'Accept' => 'application/json',
                'X-Client-Platform' => 'wordpress',
            ];

            // Use HMAC if available, otherwise Bearer token
            if (!empty($hmac_secret) && !empty($site_id)) {
                $timestamp = time();
                $method = 'POST';
                $parts = wp_parse_url($endpoint);
                $path = isset($parts['path']) ? $parts['path'] : '/';
                $query = isset($parts['query']) ? '?' . $parts['query'] : '';
                $path_with_query = $path . $query;
                $body_json = wp_json_encode($request_body);
                $context = 'WP_TO_METRIC';
                
                $message = $context . "\n" . $method . "\n" . $path_with_query . "\n" . $timestamp . "\n" . $body_json;
                $signature = hash_hmac('sha256', $message, $hmac_secret);
                
                $headers['X-WT-Key'] = $site_id;
                $headers['X-WT-Timestamp'] = (string) $timestamp;
                $headers['X-WT-Signature'] = $signature;
            } else {
                $headers['Authorization'] = 'Bearer ' . $auth_token;
            }

            $response = wp_remote_post($endpoint, [
                'headers' => $headers,
                'body' => wp_json_encode($request_body),
                'timeout' => 15,
                'sslverify' => true,
            ]);

            if (is_wp_error($response)) {
                // Request failed
            } else {
                $code = wp_remote_retrieve_response_code($response);
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);

                if ($code >= 200 && $code < 300 && !empty($data['ok'])) {
                    $server_ok = true;
                }
            }
        }

        // Only proceed with unlink if server responded successfully
        if (!$server_ok) {
            wp_send_json_error([
                'message' => __('Failed to unlink from Watchman Tower. Please try again later.', 'watchman-tower'),
            ]);
            return;
        }

        // Server confirmed unlink, proceed with local cleanup
        Options::clear_pairing();

        // Stop background heartbeats after unlink.
        Scheduler::clear();

        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }

        wp_send_json_success([
            'message' => __('Site unlinked successfully from Watchman Tower.', 'watchman-tower'),
            'server' => true,
        ]);
    }
}
