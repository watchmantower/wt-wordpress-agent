<?php

namespace WTHB\Tests;

use Brain\Monkey\Functions;
use WTHB\core\PayloadBuilder;

class PayloadBuilderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->stubPayloadEnvironment();
        $GLOBALS['wthb_test_now'] = 1762430400;
    }

    public function test_payload_shape(): void
    {
        $this->connect(['interval_sec' => 120]);
        $this->options['users_can_register'] = '1';
        $p = PayloadBuilder::build('cron');

        $this->assertSame('instance-uuid', $p['instanceId']);
        $this->assertSame('2.1.2-test', $p['pluginVersion']);
        $this->assertSame(1762430400, $p['sentAt']);
        $this->assertSame(120, $p['interval']);
        $this->assertSame('https://example.test', $p['site']['homeUrl']);
        $this->assertSame('6.8', $p['wordpress']['wpVersion']);
        $this->assertTrue($p['health']['db']['ok']);
        $this->assertTrue($p['registration']['enabled']);
        $this->assertTrue($p['security']['xmlrpc']['enabled']);
        $this->assertSame(2048, $p['health']['ram']['wordpress']['autoloadedOptionsSize']);
    }

    public function test_manual_flag_is_only_true_for_manual_sends(): void
    {
        // <=2.1.1 cast the reason string to bool, so every send looked manual.
        $this->assertTrue(PayloadBuilder::build('manual')['manual']);
        $this->assertFalse(PayloadBuilder::build('remote')['manual']);
        $this->assertFalse(PayloadBuilder::build('cron')['manual']);
    }

    public function test_rest_health_is_measured_unless_the_send_came_through_rest(): void
    {
        Functions\when('wp_remote_get')->justReturn(['response' => ['code' => 500], 'body' => '']);
        $this->assertFalse(PayloadBuilder::build('manual')['health']['rest']['ok']);
        $this->assertTrue(PayloadBuilder::build('remote')['health']['rest']['ok']);

        Functions\when('wp_remote_get')->justReturn(['response' => ['code' => 401], 'body' => '']);
        $this->assertTrue(PayloadBuilder::build('manual')['health']['rest']['ok'], 'a 401 still proves REST is up');

        Functions\when('wp_remote_get')->justReturn(new \WP_Error('x', 'loopback blocked'));
        $this->assertFalse(PayloadBuilder::build('manual')['health']['rest']['ok']);
    }

    public function test_cron_overdue_counts(): void
    {
        Functions\when('_get_cron_array')->justReturn([
            1762430000 => ['a' => [], 'b' => []],
            1762430500 => ['c' => []],
            1762431000 => ['d' => []],
        ]);
        $this->assertSame(['overdue' => 2, 'nextDue' => 1762430500], PayloadBuilder::build('cron')['health']['cron']);
    }

    public function test_active_plugins_with_updates_and_stripped_tags(): void
    {
        $this->options['active_plugins'] = ['akismet/akismet.php', 'missing/missing.php'];
        Functions\when('get_plugins')->justReturn([
            'akismet/akismet.php' => ['Name' => 'Akismet', 'Version' => '5.0', 'Author' => '<a href="x">Automattic</a>', 'Description' => '<b>Spam</b>'],
        ]);
        Functions\when('get_plugin_updates')->justReturn([
            'akismet/akismet.php' => (object) ['update' => (object) ['new_version' => '5.1']],
        ]);

        $inv = PayloadBuilder::build('cron')['inventory']['activePlugins'];
        $this->assertSame(1, $inv['count']);
        $this->assertSame([
            'name' => 'Akismet', 'version' => '5.0', 'file' => 'akismet/akismet.php', 'slug' => 'akismet',
            'author' => 'Automattic', 'description' => 'Spam', 'hasUpdate' => true, 'newVersion' => '5.1',
        ], $inv['sample'][0]);
    }
}
