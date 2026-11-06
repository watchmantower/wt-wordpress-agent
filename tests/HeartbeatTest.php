<?php

namespace WTHB\Tests;

use Brain\Monkey\Functions;
use WTHB\core\Heartbeat;

class HeartbeatTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->stubPayloadEnvironment();
        $GLOBALS['wthb_test_now'] = 1762430400;
    }

    public function test_connected_site_signs_the_exact_body_it_sends(): void
    {
        $this->connect();
        $result = Heartbeat::send('cron');

        $this->assertTrue($result['ok']);
        $sent = $this->posted[0];
        $this->assertSame('https://metric.watchmantower.com/wp/heartbeat', $sent['url']);
        $headers = $sent['args']['headers'];
        $this->assertSame('site123', $headers['X-WT-Key']);
        $this->assertSame('1762430400', $headers['X-WT-Timestamp']);
        $this->assertArrayNotHasKey('Authorization', $headers);
        // Same formula as wt-shared HmacService (see HmacContractTest).
        $expected = hash_hmac('sha256', "WP_TO_METRIC\nPOST\n/wp/heartbeat\n1762430400\n" . $sent['args']['body'], 'test-secret');
        $this->assertSame($expected, $headers['X-WT-Signature']);
        $this->assertTrue($sent['args']['sslverify']);
    }

    public function test_unpaired_site_uses_the_registration_token(): void
    {
        $this->options['wthb_options'] = ['token' => 'reg-token'];
        Heartbeat::send('connect');
        $headers = $this->posted[0]['args']['headers'];
        $this->assertSame('Bearer reg-token', $headers['Authorization']);
        $this->assertArrayNotHasKey('X-WT-Signature', $headers);
    }

    public function test_connect_response_stores_the_pairing(): void
    {
        $this->options['wthb_options'] = ['token' => 'reg-token'];
        $this->remoteResponse = ['response' => ['code' => 200], 'body' => json_encode([
            'ok' => true, 'secretKey' => 'new-secret', 'siteId' => 'site9', 'desiredIntervalSec' => 120, 'pause' => false,
        ])];

        $this->assertTrue(Heartbeat::send('connect')['ok']);
        $opts = $this->options['wthb_options'];
        $this->assertSame('new-secret', $opts['hmac_secret']);
        $this->assertSame('site9', $opts['site_id']);
        $this->assertTrue($opts['connected']);
        $this->assertSame(120, $opts['interval_sec']);
        $this->assertSame(1762430400, $opts['last_success']);
    }

    public function test_no_token_records_an_error_without_calling_out(): void
    {
        $result = Heartbeat::send('manual');
        $this->assertFalse($result['ok']);
        $this->assertSame([], $this->posted);
        $this->assertNotEmpty($this->options['wthb_options']['last_error']);
    }

    public function test_http_errors_surface_the_backend_message(): void
    {
        $this->connect();
        $this->remoteResponse = ['response' => ['code' => 403], 'body' => '{"message":"Site limit reached"}'];
        $result = Heartbeat::send('manual');
        $this->assertSame(['ok' => false, 'message' => 'Site limit reached', 'code' => 403], $result);
        $this->assertSame('Site limit reached', $this->options['wthb_options']['last_error']);
    }

    public function test_transport_errors_are_recorded(): void
    {
        $this->connect();
        Functions\when('wp_remote_post')->justReturn(new \WP_Error('http_request_failed', 'cURL error 28'));
        $result = Heartbeat::send('manual');
        $this->assertSame(['ok' => false, 'message' => 'cURL error 28'], $result);
    }

    public function test_lock_prevents_overlapping_heartbeats(): void
    {
        $locked = false;
        Functions\when('get_transient')->alias(function () use (&$locked) {
            return $locked;
        });
        Functions\when('set_transient')->alias(function () use (&$locked) {
            $locked = true;
            return true;
        });
        Functions\when('delete_transient')->alias(function () use (&$locked) {
            $locked = false;
            return true;
        });

        $runs = 0;
        Heartbeat::with_lock(function () use (&$runs) {
            $runs++;
            Heartbeat::with_lock(function () use (&$runs) {
                $runs++; // nested call while locked: must not run
            });
        });
        $this->assertSame(1, $runs);
        $this->assertFalse($locked, 'lock is released afterwards');
    }
}
