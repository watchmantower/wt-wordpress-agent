<?php

namespace WTHB\Tests;

use WTHB\models\Options;

class OptionsTest extends TestCase
{
    public function test_defaults_and_interval_clamp(): void
    {
        $this->assertSame(300, Options::get_all()['interval_sec']);
        $this->options['wthb_options'] = ['interval_sec' => 5];
        $this->assertSame(60, Options::get_all()['interval_sec']);
        $this->options['wthb_options'] = ['interval_sec' => 99999];
        $this->assertSame(3600, Options::get_all()['interval_sec']);
        $this->options['wthb_options'] = 'garbage';
        $this->assertFalse(Options::get_all()['connected']);
    }

    public function test_connected_needs_both_flag_and_secret(): void
    {
        $this->assertFalse(Options::is_connected(['connected' => true]));
        $this->assertFalse(Options::is_connected(['hmac_secret' => 's']));
        $this->assertTrue(Options::is_connected(['connected' => true, 'hmac_secret' => 's']));
    }

    public function test_clear_pairing_removes_every_credential(): void
    {
        $this->connect(['agent_jwt' => 'jwt', 'interval_sec' => 120]);
        Options::clear_pairing();
        $opts = $this->options['wthb_options'];
        foreach (['token', 'agent_jwt', 'hmac_secret', 'site_id'] as $key) {
            $this->assertNull($opts[$key], $key);
        }
        $this->assertFalse($opts['connected']);
        $this->assertSame(120, $opts['interval_sec'], 'settings survive');
    }

    public function test_instance_id_is_generated_once(): void
    {
        \Brain\Monkey\Functions\when('wp_generate_uuid4')->justReturn('uuid-1');
        $this->assertSame('uuid-1', Options::get_instance_id());
        \Brain\Monkey\Functions\when('wp_generate_uuid4')->justReturn('uuid-2');
        $this->assertSame('uuid-1', Options::get_instance_id());
    }
}
