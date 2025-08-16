<?php
namespace WTHB;

if (!defined('ABSPATH')) exit;

class Scheduler {
  const CRON_HOOK = 'wthb_heartbeat_event';

  public static function init() {
    add_action(self::CRON_HOOK, [__CLASS__, 'heartbeat_job']);
    register_activation_hook(WTHB_PLUGIN_FILE, [__CLASS__, 'on_activate']);
    register_deactivation_hook(WTHB_PLUGIN_FILE, [__CLASS__, 'on_deactivate']);
  }

  public static function on_activate() {
    // ensure instanceId exists on activation too
    Options::get_instance_id();
    if (!wp_next_scheduled(self::CRON_HOOK)) {
      wp_schedule_single_event(time() + 10, self::CRON_HOOK);
    }
  }

  public static function on_deactivate() {
    while ($ts = wp_next_scheduled(self::CRON_HOOK)) {
      wp_unschedule_event($ts, self::CRON_HOOK);
    }
  }

  /** Called only after settings save */
  public static function reschedule_after_save($old, $new) {
    while ($ts = wp_next_scheduled(self::CRON_HOOK)) {
      wp_unschedule_event($ts, self::CRON_HOOK);
    }
    wp_schedule_single_event(time() + 5, self::CRON_HOOK);
  }

  public static function heartbeat_job() {
    $opts = Options::get_all();

    // schedule next run now (fixed schedule + jitter)
    $jitter = rand(30, 90);
    $next   = time() + intval($opts['interval_sec']) + $jitter;
    wp_schedule_single_event($next, self::CRON_HOOK);

    if (!empty($opts['pause'])) return;

    Heartbeat::with_lock(function () {
      Heartbeat::send(false);
    });
  }

  public static function schedule_in(int $sec) {
    while ($ts = wp_next_scheduled(self::CRON_HOOK)) {
      wp_unschedule_event($ts, self::CRON_HOOK);
    }
    wp_schedule_single_event(time() + max(1, $sec), self::CRON_HOOK);
  }
}