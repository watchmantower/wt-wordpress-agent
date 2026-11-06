<?php

namespace WTHB\Tests;

use PHPUnit\Framework\TestCase as Base;

/**
 * A new AJAX handler that forgets its nonce or capability check would be an
 * open settings endpoint for any logged-in user. Checked statically for every
 * file, so it also covers handlers without a dedicated behaviour test.
 */
class AjaxInventoryTest extends Base
{
    public function test_every_ajax_handler_checks_nonce_and_capability_first(): void
    {
        $files = glob(dirname(__DIR__) . '/plugin/includes/ajax/*.php');
        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $src = file_get_contents($file);
            $name = basename($file);
            if (strpos($src, "add_action('wp_ajax_") === false) {
                continue;
            }
            $this->assertStringNotContainsString('wp_ajax_nopriv_', $src, "$name exposes an unauthenticated action");

            $handle = substr($src, strpos($src, 'function handle('));
            $nonce = strpos($handle, 'check_ajax_referer(');
            $cap = strpos($handle, "current_user_can('manage_options')");
            $this->assertNotFalse($nonce, "$name: no nonce check");
            $this->assertNotFalse($cap, "$name: no manage_options check");

            // Nothing that reads input or writes options may come before both checks.
            $guardEnd = max($nonce, $cap);
            foreach (['$_POST', '$_GET', '$_REQUEST', 'Options::', 'wp_remote_'] as $sensitive) {
                $at = strpos($handle, $sensitive);
                if ($at !== false) {
                    $this->assertGreaterThan($guardEnd, $at, "$name uses $sensitive before its auth checks");
                }
            }
        }
    }
}
