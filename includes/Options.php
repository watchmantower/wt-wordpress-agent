<?php
namespace WTHB;

if (!defined('ABSPATH')) exit;

class Options {
  const OPT_KEY       = 'wthb_options';
  const OPT_INSTANCE  = 'wthb_instance_id';
  const OPT_INSTANCE_OLD = 'wtm_instance_id'; // migration from old key
  // const DEFAULT_ENDPOINT = 'https://metric.watchmantower.com/wp/heartbeat';
  const DEFAULT_ENDPOINT = 'https://9c6152cf091e.ngrok-free.app/wp/heartbeat';
  const DEFAULT_INTERVAL = 600; // 10 min

  public static function init() {
    // nothing for now
  }

  public static function get_all(): array {
    $opts = get_option(self::OPT_KEY, []);
    if (empty($opts['endpoint']))     $opts['endpoint']     = self::DEFAULT_ENDPOINT;
    if (empty($opts['interval_sec'])) $opts['interval_sec'] = self::DEFAULT_INTERVAL;
    if (!isset($opts['pause']))       $opts['pause']        = false;
    return $opts;
  }

  public static function update_all(array $opts) {
    update_option(self::OPT_KEY, $opts, false);
  }

  /** Get or create instanceId (lazy) + migrate from old key if present */
  public static function get_instance_id(): string {
    $iid = get_option(self::OPT_INSTANCE);
    if (!empty($iid) && is_string($iid)) return $iid;

    // migrate from old key if exists
    $old = get_option(self::OPT_INSTANCE_OLD);
    if (!empty($old) && is_string($old)) {
      update_option(self::OPT_INSTANCE, $old, false);
      delete_option(self::OPT_INSTANCE_OLD);
      return $old;
    }

    // create new
    if (function_exists('wp_generate_uuid4')) {
      $iid = wp_generate_uuid4();
    } else {
      $iid = uniqid('wthb_', true);
    }
    update_option(self::OPT_INSTANCE, $iid, false);
    return $iid;
  }
}