<?php

namespace WTHB\core;

use WTHB\models\Options;

/**
 * Handles sending heartbeat data to Watchman Tower API.
 */
class Heartbeat
{
    /**
     * Send heartbeat to Watchman Tower API.
     *
     * @param string $reason 'connect', 'cron', 'manual', or other
     * @return array Response data
     */
    public static function send(string $reason = 'manual'): array
    {
        $opts = Options::get_all();

        $token = !empty($opts['agent_jwt']) ? $opts['agent_jwt'] : $opts['token'];

        if (empty($token)) {
            $error_msg = __('No token available for heartbeat.', 'watchman-tower');
            Options::record_error($error_msg);
            return ['ok' => false, 'message' => $error_msg];
        }

        $payload = PayloadBuilder::build($reason);
        
        // Convert payload to JSON string first
        $body_json = wp_json_encode($payload, JSON_UNESCAPED_SLASHES);
        
        if ($body_json === false) {
            $error_msg = __('Failed to encode payload as JSON.', 'watchman-tower');
            Options::record_error($error_msg);
            return ['ok' => false, 'message' => $error_msg];
        }

        $endpoint = apply_filters('wthb_endpoint_heartbeat', Options::ENDPOINT_HEARTBEAT);

        $hmac_secret = $opts['hmac_secret'] ?? '';
        $site_id     = $opts['site_id'] ?? '';

        $headers = [
            'User-Agent'   => 'WatchmanTower-WP-Agent/1.0',
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'Cache-Control' => 'no-cache',
            'X-Client-Platform' => 'wordpress',
            'x-wt-site-id' => $site_id,
        ];

        // Use HMAC if available, otherwise Bearer token
        if ( ! empty( $hmac_secret ) && ! empty( $site_id ) ) {
            $timestamp = time();
            $method    = 'POST';

            // URL'den path + query parçalama
            $parts = wp_parse_url($endpoint);
            $path  = $parts['path'] ?? '/';
            $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
            $context = 'WP_TO_METRIC';

            $path_with_query = $path . $query;

            // HMAC mesajı: CONTEXT \n METHOD \n PATH \n TIMESTAMP \n BODY
            $message = $context . "\n" . $method . "\n" . $path_with_query . "\n" . $timestamp . "\n" . $body_json;

            $signature = hash_hmac( 'sha256', $message, $hmac_secret );

            // Header'lara ekle
            $headers['X-WT-Key']       = $site_id;
            $headers['X-WT-Timestamp'] = (string) $timestamp;
            $headers['X-WT-Signature'] = $signature;
        } else {
            // Fallback: Bearer token authentication
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        $response = wp_remote_post($endpoint, [
            'headers' => $headers,
            'body' => $body_json,
            'timeout' => 15,
            'sslverify' => true,
        ]);

        if (is_wp_error($response)) {
            $error_msg = $response->get_error_message();
            Options::record_error($error_msg);
            return ['ok' => false, 'message' => $error_msg];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code < 200 || $code >= 300) {
            if (isset($data['message'])) {
                $error_msg = $data['message'];
            } elseif (isset($data['error'])) {
                $error_msg = $data['error'];
            } else {
                /* translators: %d: HTTP response code */
                $error_msg = sprintf(__('HTTP %d response', 'watchman-tower'), $code);
            }
            Options::record_error($error_msg);
            return ['ok' => false, 'message' => $error_msg, 'code' => $code];
        }

        if (!is_array($data)) {
            $data = ['ok' => true];
        }

        if (isset($data['ok']) && $data['ok'] === false) {
            $error_msg = isset($data['message']) ? $data['message'] : __('API returned ok: false', 'watchman-tower');
            Options::record_error($error_msg);
            return $data;
        }

        if (!empty($data['secretKey'])) {
            $opts['hmac_secret'] = $data['secretKey'];
            $opts['connected'] = true;

            if (isset($data['siteId'])) {
                $opts['site_id'] = $data['siteId'];
            }

            if (isset($data['desiredIntervalSec'])) {
                $opts['interval_sec'] = (int) $data['desiredIntervalSec'];
            }



            if (isset($data['pause'])) {
                $opts['pause'] = (bool) $data['pause'];
            }

            Options::update_all($opts);
        } elseif (!empty($data['hmacSecret'])) {
            // Try hmacSecret (camelCase) as fallback
            $opts['hmac_secret'] = $data['hmacSecret'];
            $opts['connected'] = true;

            if (isset($data['siteId'])) {
                $opts['site_id'] = $data['siteId'];
            }

            if (isset($data['desiredIntervalSec'])) {
                $opts['interval_sec'] = (int) $data['desiredIntervalSec'];
            }

            if (isset($data['pause'])) {
                $opts['pause'] = (bool) $data['pause'];
            }

            Options::update_all($opts);
        }

        Options::record_success();

        return array_merge(['ok' => true], $data);
    }

    /**
     * Execute callback with transient lock to prevent concurrent heartbeats.
     *
     * @param callable $fn
     * @return void
     */
    public static function with_lock(callable $fn): void
    {
        $lock_key = 'wthb_hb_lock';

        if (get_transient($lock_key)) {
            return;
        }

        set_transient($lock_key, true, 60);

        try {
            call_user_func($fn);
        } finally {
            delete_transient($lock_key);
        }
    }
}
