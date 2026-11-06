<?php

namespace WTHB\Tests;

use PHPUnit\Framework\TestCase as Base;

/**
 * Both directions of the plugin <-> metric HMAC must match wt-shared's
 * HmacService byte for byte. These vectors were produced by wt-shared
 * (dist/core/Hmac.service.js); if either side changes the message format,
 * this test and its twin in wt-shared stop agreeing.
 *
 * Message: CONTEXT \n METHOD \n PATH \n TIMESTAMP \n BODY
 */
class HmacContractTest extends Base
{
    public function test_metric_to_wp_vector(): void
    {
        $message = "METRIC_TO_WP\nPOST\n/wp-json/wt/v1/heartbeat\n1762430400\n{}";
        $this->assertSame(
            'b35fc5a06a9fe89c5c41abbf720fe10164af4dd6982689fec92c21b3f962bebd',
            hash_hmac('sha256', $message, 'test-secret')
        );
    }

    public function test_wp_to_metric_vector(): void
    {
        $message = "WP_TO_METRIC\nPOST\n/wp/heartbeat\n1762430400\n{\"instanceId\":\"i1\"}";
        $this->assertSame(
            'f8206464feec4727d1caaa78b73b189bcd868596114e262beecab644c7bab30c',
            hash_hmac('sha256', $message, 'test-secret')
        );
    }
}
