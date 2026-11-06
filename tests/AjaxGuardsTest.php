<?php

namespace WTHB\Tests;

use Brain\Monkey\Functions;
use WTHB\includes\ajax\SaveSettings;
use WTHB\includes\ajax\Token;

/**
 * Every admin AJAX action must check its nonce and manage_options before
 * touching settings.
 */
class AjaxGuardsTest extends TestCase
{
    private $nonceChecked = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->stubPayloadEnvironment();
        Functions\when('check_ajax_referer')->alias(function ($action) {
            $this->nonceChecked[] = $action;
            return 1;
        });
        Functions\when('sanitize_text_field')->alias('trim');
        Functions\when('wp_unslash')->returnArg();
    }

    private function invoke(callable $handler): \WthbJsonSent
    {
        try {
            $handler();
        } catch (\WthbJsonSent $sent) {
            return $sent;
        }
        $this->fail('handler did not answer');
    }

    public function test_save_settings_needs_manage_options(): void
    {
        Functions\when('current_user_can')->justReturn(false);
        $_POST = ['interval_sec' => '120'];
        $sent = $this->invoke([SaveSettings::class, 'handle']);
        $this->assertFalse($sent->success);
        $this->assertSame(['wthb_settings_nonce'], $this->nonceChecked);
        $this->assertArrayNotHasKey('wthb_options', $this->options);
    }

    public function test_save_settings_clamps_the_interval(): void
    {
        Functions\when('current_user_can')->justReturn(true);
        $this->connect();
        $_POST = ['interval_sec' => '10', 'pause' => '1'];
        $sent = $this->invoke([SaveSettings::class, 'handle']);
        $this->assertTrue($sent->success);
        $this->assertSame(60, $sent->data['interval_sec']);
        $this->assertTrue($this->options['wthb_options']['pause']);
    }

    public function test_token_needs_manage_options_and_a_value(): void
    {
        Functions\when('current_user_can')->justReturn(false);
        $_POST = ['token' => 'abc'];
        $this->assertFalse($this->invoke([Token::class, 'handle'])->success);
        $this->assertArrayNotHasKey('wthb_options', $this->options);

        Functions\when('current_user_can')->justReturn(true);
        $_POST = ['token' => '   '];
        $this->assertFalse($this->invoke([Token::class, 'handle'])->success);
        $this->assertSame([], $this->posted);
        $this->assertSame(['wthb_token_nonce', 'wthb_token_nonce'], $this->nonceChecked);
    }

    public function test_token_connects_with_the_backend_answer(): void
    {
        Functions\when('current_user_can')->justReturn(true);
        $this->remoteResponse = ['response' => ['code' => 200], 'body' => '{"ok":true,"secretKey":"s","siteId":"site1"}'];
        $_POST = ['token' => ' tok '];
        $sent = $this->invoke([Token::class, 'handle']);
        $this->assertTrue($sent->success);
        $this->assertSame('Bearer tok', $this->posted[0]['args']['headers']['Authorization']);
        $this->assertTrue($this->options['wthb_options']['connected']);
    }
}
