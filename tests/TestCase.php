<?php

namespace WTHB\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase as Base;

abstract class TestCase extends Base
{
    /** In-memory wp_options. */
    protected $options = [];

    /** Requests the plugin sent with wp_remote_post. */
    protected $posted = [];

    /** What the next wp_remote_post answers. */
    protected $remoteResponse = ['response' => ['code' => 200], 'body' => '{"ok":true}'];

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        $GLOBALS['wthb_test_now'] = null;
        $this->options = [];
        $this->posted = [];

        Functions\stubTranslationFunctions();
        Functions\stubEscapeFunctions();
        Functions\when('get_option')->alias(function ($key, $default = false) {
            return array_key_exists($key, $this->options) ? $this->options[$key] : $default;
        });
        Functions\when('update_option')->alias(function ($key, $value) {
            $this->options[$key] = $value;
            return true;
        });
        Functions\when('wp_cache_delete')->justReturn(true);
        Functions\when('apply_filters')->returnArg(2);
        Functions\when('wp_json_encode')->alias(function ($data, $flags = 0) {
            return json_encode($data, $flags);
        });
        Functions\when('wp_parse_url')->alias('parse_url');
        Functions\when('is_wp_error')->alias(function ($thing) {
            return $thing instanceof \WP_Error;
        });
        Functions\when('wp_remote_post')->alias(function ($url, $args) {
            $this->posted[] = ['url' => $url, 'args' => $args];
            return $this->remoteResponse;
        });
        Functions\when('wp_remote_retrieve_response_code')->alias(function ($r) {
            return $r['response']['code'];
        });
        Functions\when('wp_remote_retrieve_body')->alias(function ($r) {
            return $r['body'];
        });
        Functions\when('wp_send_json_success')->alias(function ($data = null) {
            throw new \WthbJsonSent(true, $data);
        });
        Functions\when('wp_send_json_error')->alias(function ($data = null) {
            throw new \WthbJsonSent(false, $data);
        });
    }

    protected function tearDown(): void
    {
        $GLOBALS['wthb_test_now'] = null;
        $_POST = [];
        Monkey\tearDown();
        parent::tearDown();
    }

    protected function connect(array $extra = []): void
    {
        $this->options['wthb_options'] = array_merge([
            'connected' => true,
            'hmac_secret' => 'test-secret',
            'site_id' => 'site123',
            'token' => 'reg-token',
        ], $extra);
    }

    /** Stubs everything PayloadBuilder::build() reads from WordPress. */
    protected function stubPayloadEnvironment(): void
    {
        $GLOBALS['wp_version'] = '6.8';
        $GLOBALS['wpdb'] = new class {
            public $options = 'wp_options';

            public function get_var($sql)
            {
                return strpos($sql, 'SELECT 1') === 0 ? '1' : '2048';
            }
        };
        Functions\when('wp_get_theme')->justReturn(new class {
            public function get($key)
            {
                return ['Name' => 'Twenty', 'Version' => '1.0'][$key] ?? '';
            }
        });
        Functions\when('home_url')->justReturn('https://example.test');
        Functions\when('site_url')->justReturn('https://example.test/wp');
        Functions\when('admin_url')->justReturn('https://example.test/wp-admin/');
        Functions\when('rest_url')->justReturn('https://example.test/wp-json/');
        Functions\when('is_multisite')->justReturn(false);
        Functions\when('wp_count_comments')->justReturn((object) ['total_comments' => 3, 'approved' => 2, 'moderated' => 1, 'spam' => 0]);
        Functions\when('_get_cron_array')->justReturn([]);
        Functions\when('wp_remote_get')->justReturn(['response' => ['code' => 200], 'body' => '']);
        Functions\when('wp_using_ext_object_cache')->justReturn(false);
        Functions\when('get_core_updates')->justReturn([]);
        Functions\when('get_plugin_updates')->justReturn([]);
        Functions\when('get_theme_updates')->justReturn([]);
        Functions\when('get_plugins')->justReturn([]);
        Functions\when('wp_strip_all_tags')->alias('strip_tags');
        Functions\when('wp_generate_uuid4')->justReturn('instance-uuid');
    }
}
