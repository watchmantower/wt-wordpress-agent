<?php

namespace WTHB\Tests;

use Brain\Monkey\Functions;
use WTHB\includes\rest\HeartbeatEndpoint;

class HeartbeatEndpointTest extends TestCase
{
    // The exact headers metric sends (wt-shared HmacService, see HmacContractTest).
    const TS = '1762430400';
    const SIGNATURE = 'b35fc5a06a9fe89c5c41abbf720fe10164af4dd6982689fec92c21b3f962bebd';

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['wthb_test_now'] = (int) self::TS + 30;
        Functions\when('wp_clear_scheduled_hook')->justReturn(0);
        Functions\when('get_transient')->justReturn(false);
        Functions\when('set_transient')->justReturn(true);
        Functions\when('delete_transient')->justReturn(true);
        $this->stubPayloadEnvironment();
    }

    private function request(array $overrides = []): \WP_REST_Request
    {
        $r = new \WP_REST_Request('POST', $overrides['route'] ?? '/wt/v1/heartbeat');
        $r->set_header('X-WT-Key', $overrides['key'] ?? 'site123');
        $r->set_header('X-WT-Timestamp', $overrides['ts'] ?? self::TS);
        $r->set_header('X-WT-Signature', $overrides['sig'] ?? self::SIGNATURE);
        $r->set_body($overrides['body'] ?? '{}');
        return $r;
    }

    private function assertUnauthorized($response): void
    {
        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertSame(401, $response->get_status());
        // Since 2.1.2 the 401 body says nothing about why: no secret prefix, no expected signature.
        $this->assertSame(['success' => false, 'error' => 'unauthorized'], $response->get_data());
        $this->assertSame([], $this->posted, 'no heartbeat may be sent');
    }

    public function test_accepts_the_signature_metric_produces_and_sends_a_heartbeat(): void
    {
        $this->connect();
        $result = HeartbeatEndpoint::handle($this->request());

        $this->assertTrue($result['ok']);
        $this->assertCount(1, $this->posted);
        $body = json_decode($this->posted[0]['args']['body'], true);
        // WT-triggered sends prove REST works and must not report as "manual".
        $this->assertTrue($body['health']['rest']['ok']);
        $this->assertFalse($body['manual']);
    }

    public function test_paused_site_answers_ok_without_sending(): void
    {
        $this->connect(['pause' => true]);
        $result = HeartbeatEndpoint::handle($this->request());
        $this->assertTrue($result['ok']);
        $this->assertSame([], $this->posted);
    }

    public function test_rejects_a_site_that_is_not_connected(): void
    {
        $this->assertUnauthorized(HeartbeatEndpoint::handle($this->request()));
    }

    public function test_rejects_when_the_secret_is_missing_instead_of_using_a_default(): void
    {
        $this->connect(['hmac_secret' => '']);
        $this->assertUnauthorized(HeartbeatEndpoint::handle($this->request()));
        // The pre-2.1.2 fallback key must not work either.
        $this->connect(['hmac_secret' => '']);
        $sig = hash_hmac('sha256', "METRIC_TO_WP\nPOST\n/wp-json/wt/v1/heartbeat\n" . self::TS . "\n{}", 'supersecretkey');
        $this->assertUnauthorized(HeartbeatEndpoint::handle($this->request(['sig' => $sig])));
    }

    public function test_rejects_another_sites_key(): void
    {
        $this->connect();
        $this->assertUnauthorized(HeartbeatEndpoint::handle($this->request(['key' => 'other-site'])));
    }

    public function test_rejects_missing_headers(): void
    {
        $this->connect();
        $this->assertUnauthorized(HeartbeatEndpoint::handle($this->request(['sig' => ''])));
    }

    public function test_rejects_a_timestamp_outside_the_15_minute_window(): void
    {
        $this->connect();
        $GLOBALS['wthb_test_now'] = (int) self::TS + 901;
        $this->assertUnauthorized(HeartbeatEndpoint::handle($this->request()));
        $GLOBALS['wthb_test_now'] = (int) self::TS - 901;
        $this->assertUnauthorized(HeartbeatEndpoint::handle($this->request()));
    }

    public function test_rejects_a_non_numeric_timestamp(): void
    {
        $this->connect();
        $this->assertUnauthorized(HeartbeatEndpoint::handle($this->request(['ts' => '1762430400.5'])));
    }

    public function test_rejects_a_tampered_body_or_route(): void
    {
        $this->connect();
        $this->assertUnauthorized(HeartbeatEndpoint::handle($this->request(['body' => '{"x":1}'])));
        $this->assertUnauthorized(HeartbeatEndpoint::handle($this->request(['route' => '/wt/v1/other'])));
    }

    public function test_rejects_a_wrong_signature(): void
    {
        $this->connect();
        $this->assertUnauthorized(HeartbeatEndpoint::handle($this->request(['sig' => str_repeat('0', 64)])));
    }
}
