<?php
/**
 * Unit test bootstrap (WT-214).
 *
 * WordPress itself is not loaded: WP functions are stubbed per test with
 * Brain Monkey, and the few WP classes the plugin touches are minimal fakes
 * below. This keeps the suite fast and runnable on any PHP from 7.4 up.
 */

define('ABSPATH', __DIR__ . '/fake-wp/');
define('WTHB_VERSION', '2.1.2-test');

require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/WpFakes.php';
require __DIR__ . '/Clock.php';
require __DIR__ . '/TestCase.php';
