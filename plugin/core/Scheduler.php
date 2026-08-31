<?php

namespace WTHB\core;

use WTHB\models\Options;

/**
 * Handles WP-Cron based scheduling for heartbeats.
 * Optional fallback when JS-based timers are not active.
 */
class Scheduler
{
    const HOOK = 'wthb_cron_heartbeat';

    /**
     * Initialize scheduler hooks.
     *
     * @return void
     */
    public function init(): void
    {
        add_action(self::HOOK, [$this, 'run_heartbeat']);

        // Ensure the cron chain is bootstrapped once the site is connected.
        // Avoid rescheduling if an event already exists.
        // NOTE: Disabled. In WT-triggered mode, scheduling is handled externally.
        // self::ensure_scheduled();
    }

    /**
     * Ensure a heartbeat cron event is scheduled if the site is connected.
     *
     * Important: this must NOT continually reschedule on every request,
     * otherwise the event would be pushed forward forever.
     *
     * @param int|null $delay_sec Seconds from now (defaults to the configured interval)
     * @return void
     */
    public static function ensure_scheduled(?int $delay_sec = null): void
    {
        if (wp_next_scheduled(self::HOOK)) {
            return;
        }

        $opts = Options::get_all();

        if (!Options::is_connected($opts)) {
            return;
        }

        $delay = $delay_sec ?? (int) $opts['interval_sec'];

        if ($delay < 5) {
            $delay = 5;
        }

        wp_schedule_single_event(time() + $delay, self::HOOK);
    }

    /**
     * Schedule next heartbeat.
     *
     * @param int $delay_sec Seconds from now
     * @return void
     */
    public static function schedule_in(int $delay_sec = 300): void
    {
        $next = time() + $delay_sec;

        if (wp_next_scheduled(self::HOOK)) {
            wp_clear_scheduled_hook(self::HOOK);
        }

        wp_schedule_single_event($next, self::HOOK);
    }

    /**
     * Clear scheduled heartbeat.
     *
     * @return void
     */
    public static function clear(): void
    {
        wp_clear_scheduled_hook(self::HOOK);
    }

    /**
     * Run heartbeat via cron.
     *
     * @return void
     */
    public function run_heartbeat(): void
    {
        $opts = Options::get_all();

        if (!Options::is_connected($opts)) {
            return;
        }

        if ($opts['pause']) {
            // NOTE: Disabled. In WT-triggered mode, scheduling is handled externally.
            // self::schedule_in($opts['interval_sec']);
            return;
        }

        Heartbeat::with_lock(function () use ($opts) {
            Heartbeat::send('cron');
        });

        // NOTE: Disabled. In WT-triggered mode, scheduling is handled externally.
        // self::schedule_in($opts['interval_sec']);
    }
}
