<?php
/**
 * Plugin Name: Watchman Tower Heartbeat
 * Plugin URI: https://watchmantower.com
 * Description: Sends lightweight WordPress heartbeat metrics to Watchman Tower on a fixed schedule.
 * Version: 0.0.1
 * Author: Watchman Tower
 * Author URI: https://watchmantower.com
 */

namespace WTHB;

if (!defined('ABSPATH')) exit;

define('WTHB_PLUGIN_FILE', __FILE__);
define('WTHB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WTHB_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WTHB_VERSION', '0.1.1');

require_once WTHB_PLUGIN_DIR . 'includes/Options.php';
require_once WTHB_PLUGIN_DIR . 'includes/Admin.php';
require_once WTHB_PLUGIN_DIR . 'includes/Heartbeat.php';
require_once WTHB_PLUGIN_DIR . 'includes/Scheduler.php';

use WTHB\Options;
use WTHB\Admin;
use WTHB\Heartbeat;
use WTHB\Scheduler;

add_action('plugins_loaded', function () {
  Options::init();
  Admin::init();
  Heartbeat::init();
  Scheduler::init();

  add_filter('plugin_action_links_' . plugin_basename(WTHB_PLUGIN_FILE), function ($links) {
    $url = admin_url('options-general.php?page=wthb-settings');
    $links[] = '<a href="'.esc_url($url).'">Settings</a>';
    return $links;
  });
});