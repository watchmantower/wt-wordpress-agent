<?php
namespace WTHB;

if (!defined('ABSPATH')) exit;

class Options {
  const OPT_KEY          = 'wthb_options';
  const OPT_INSTANCE     = 'wthb_instance_id';
  const OPT_INSTANCE_OLD = 'wtm_instance_id';

  const DEFAULT_ENDPOINT = 'https://metric.watchmantower.com/wp/heartbeat';
  const DEFAULT_INTERVAL = 600;

  public static function init(): void {}

  public static function get_all(): array {
    $opts = get_option(self::OPT_KEY, []);
    
    // Sadece default değerleri set et, mevcut değerleri override etme
    $defaults = [
      'endpoint' => self::DEFAULT_ENDPOINT,
      'interval_sec' => self::DEFAULT_INTERVAL,
      'pause' => false,
      'connected' => false
    ];
    
    // Mevcut değerleri koru, sadece eksik olanları ekle
    $opts = array_merge($defaults, $opts);
    
    // Sadece interval çok küçükse düzelt
    if ($opts['interval_sec'] < 60) {
      $opts['interval_sec'] = self::DEFAULT_INTERVAL;
    }
    
    return $opts;
  }

  public static function update_all(array $opts): void {
    update_option(self::OPT_KEY, $opts, false);
    if (function_exists('wp_cache_delete')) {
      wp_cache_delete(self::OPT_KEY, 'options');
      wp_cache_delete('alloptions', 'options');
    }
  }

  public static function is_connected(?array $opts = null): bool {
    $opts = $opts ?? self::get_all();
    return !empty($opts['connected']) && !empty($opts['agent_jwt']);
  }

  public static function set_connected(bool $val): void {
    $opts = self::get_all();
    $opts['connected'] = $val;
    self::update_all($opts);
  }

  public static function clear_pairing(): void {
    $opts = self::get_all();

    // Agent JWT'yi inactive olarak sakla ve sil
    if (!empty($opts['agent_jwt'])) {
      $opts['agent_jwt_inactive'] = $opts['agent_jwt'];
    }
    
    // Tüm bağlantı ile ilgili alanları temizle
    unset($opts['agent_jwt']);
    unset($opts['agent_jwt_exp']);
    
    $opts['connected'] = false;
    $opts['last_unlinked_at'] = time();
    $opts['token'] = ''; // Token alanını temizle
    
    // Önce güncelle
    self::update_all($opts);
    
    // Sonra cache'i agresif şekilde temizle
    wp_cache_flush();
    if (function_exists('wp_cache_delete')) {
      wp_cache_delete(self::OPT_KEY, 'options');
      wp_cache_delete('alloptions', 'options');
    }
    
    // Veritabanından da direkt olarak kontrol et
    delete_option(self::OPT_KEY);
    update_option(self::OPT_KEY, $opts, false);
  }

  public static function record_success(): void {
    $opts = self::get_all();
    $opts['last_success_at'] = time();
    $opts['last_error']      = '';
    self::update_all($opts);
  }

  public static function record_error(string $msg): void {
    $opts = self::get_all();
    $opts['last_error'] = $msg;
    self::update_all($opts);
  }

  public static function get_instance_id(): string {
    $iid = get_option(self::OPT_INSTANCE);
    if (!empty($iid) && is_string($iid)) return $iid;

    $old = get_option(self::OPT_INSTANCE_OLD);
    if (!empty($old) && is_string($old)) {
      update_option(self::OPT_INSTANCE, $old, false);
      delete_option(self::OPT_INSTANCE_OLD);
      return $old;
    }

    $iid = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('wthb_', true);
    update_option(self::OPT_INSTANCE, $iid, false);
    return $iid;
  }
}