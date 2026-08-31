<?php

namespace WTHB\includes\rest;

use WP_REST_Request;
use WP_REST_Response;
use WTHB\core\Heartbeat;
use WTHB\core\Scheduler;
use WTHB\models\Options;

class HeartbeatEndpoint
{
    /**
     * Register hooks.
     *
     * @return void
     */
    public static function register(): void
    {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    /**
     * Register REST routes.
     *
     * @return void
     */
    public static function register_routes(): void
    {
        register_rest_route('wt/v1', '/heartbeat', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'handle'],
            // Auth is handled inside the handler; keep route publicly reachable.
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * REST handler for inbound heartbeat trigger.
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|array
     */
    public static function handle(WP_REST_Request $request)
    {
        // 1) Auth kontrolü
        //
        // 401 cevabi BILEREK kuru: onceki surum (<=2.1.1) buraya `debug`
        // nesnesini koyuyordu ve o nesne HMAC secret'inin ilk 6 karakterini,
        // beklenen imzanin ilk 16 karakterini, site_id'yi ve istegin ham
        // govdesini ANONIM istemciye donduruyordu. Kimlik dogrulamasi
        // BASARISIZ olan birine dogrulamanin ic detaylarini anlatmak, kapiyi
        // acamayana kilidin semasini vermek demek. Teshis bilgisi artik
        // yalnizca sunucu icinde (get_last_hmac_debug) yasiyor.
       if ( ! self::verify_request( $request ) ) {
        return new WP_REST_Response(
            [
                'success' => false,
                'error'   => 'unauthorized',
            ],
            401
        );
    }

        // 2) Eski cron’un yaptığı işi çağır
        self::run_heartbeat_job();

        // 3) WT’ye cevap
        return [
            'ok'   => true,
            'time' => time(),
        ];
    }

    /**
     * Verify an inbound request from Watchman Tower.
     *
     * For now, WT can authenticate with the current stored agent JWT:
     * `Authorization: Bearer <agentJwt>`
     *
     * @param WP_REST_Request $request
     * @return bool
     */
    // Sınıf içinde ek bir property:
private static $last_hmac_debug = null;

public static function get_last_hmac_debug(): ?array {
    return self::$last_hmac_debug;
}

private static function set_hmac_debug(array $debug): void {
    self::$last_hmac_debug = $debug;
}

private static function verify_request( WP_REST_Request $request ): bool {
    $opts = Options::get_all();

    $debug = [
        'reason'   => null,
        'site_id'  => $opts['site_id'] ?? null,
        'headers'  => [
            'X-WT-Key'       => (string) $request->get_header('X-WT-Key'),
            'X-WT-Timestamp' => (string) $request->get_header('X-WT-Timestamp'),
            'X-WT-Signature' => substr((string) $request->get_header('X-WT-Signature'), 0, 16) . '...',
        ],
    ];

    // Site WT ile bağlanmamışsa zaten devam etmeyelim
    if ( ! Options::is_connected( $opts ) ) {
        $debug['reason'] = 'not_connected';
        self::set_hmac_debug($debug);
        return false;
    }

    // Secret yoksa dogrulama YOK. Onceki surum (<=2.1.1) burada
    // 'supersecretkey' sabitine dusuyordu -- yapilandirmasi bozuk bir site,
    // herkesin kaynak kodda gorebilecegi bir anahtarla imzalanmis istegi
    // kabul ederdi. Bos birakiyoruz; asagidaki bos-kontrol reddediyor.
    $site_id     = isset( $opts['site_id'] ) ? (string) $opts['site_id'] : '';
    $hmac_secret = isset( $opts['hmac_secret'] ) ? (string) $opts['hmac_secret'] : '';

    $debug['site_id'] = $site_id;

    if ( $site_id === '' || $hmac_secret === '' ) {
        $debug['reason'] = 'missing_site_or_secret';
        self::set_hmac_debug($debug);
        return false;
    }

    // 1) Header'lardan değerleri çek
    $header_key       = (string) $request->get_header( 'X-WT-Key' );
    $header_ts        = (string) $request->get_header( 'X-WT-Timestamp' );
    $header_signature = (string) $request->get_header( 'X-WT-Signature' );

    $debug['headers']['X-WT-Key']       = $header_key;
    $debug['headers']['X-WT-Timestamp'] = $header_ts;
    $debug['headers']['X-WT-Signature'] = substr($header_signature, 0, 16) . '...';

    if ( $header_key === '' || $header_ts === '' || $header_signature === '' ) {
        $debug['reason'] = 'missing_headers';
        self::set_hmac_debug($debug);
        return false;
    }

    // keyId = siteId mi? (ekstra güvenlik / doğru site mi?)
    if ( $header_key !== $site_id ) {
        $debug['reason'] = 'key_mismatch';
        self::set_hmac_debug($debug);
        return false;
    }

    // 2) Timestamp kontrolü (replay / saat sapması)
    if ( ! ctype_digit( $header_ts ) ) {
        $debug['reason'] = 'invalid_timestamp_format';
        self::set_hmac_debug($debug);
        return false;
    }

    $ts       = (int) $header_ts;
    $now      = time();          // WP sunucusunun unix zamanı
    $max_skew = 900;             // 15 dakikalık pencere (gerektiğinde ayarlanabilir)

    $debug['time'] = [
        'now'       => $now,
        'ts'        => $ts,
        'delta_sec' => $now - $ts,
        'max_skew'  => $max_skew,
    ];

    if ( abs( $now - $ts ) > $max_skew ) {
        $debug['reason'] = 'timestamp_skew';
        self::set_hmac_debug($debug);
        return false;
    }

    // 3) HMAC mesajını bizim TS tarafıyla aynı formatta kur:
    // METHOD \n PATH \n TIMESTAMP \n BODY
    $method = strtoupper( $request->get_method() );
    $route  = $request->get_route(); 

    if ( $route === '' ) {
        $debug['reason'] = 'empty_route';
        self::set_hmac_debug($debug);
        return false;
    }

    if ( $route[0] !== '/' ) {
        $route = '/' . $route;
    }
    $path_with_query = '/wp-json' . $route;

    $body_raw = $request->get_body();
    if ( $body_raw === null ) {
        $body_raw = '';
    }

    $context = 'METRIC_TO_WP';

    $message = $context . "\n" . $method . "\n" . $path_with_query . "\n" . $header_ts . "\n" . $body_raw;

    $debug['computed'] = [
        'method'          => $method,
        'path_with_query' => $path_with_query,
        'body_raw'        => $body_raw,
        'message_preview' => substr($message, 0, 200) . '...',
    ];

    // 4) Beklenen imzayı üret
    $expected_signature = hash_hmac( 'sha256', $message, $hmac_secret );

    // Beklenen imzanin hicbir parcasi debug'a bile yazilmiyor: istemciye
    // sizmasa da, dogru imzanin herhangi bir onekini herhangi bir yerde
    // tutmak icin sebep yok. Gelen imza zaten istemcinin kendi verisi.
    $debug['signatures'] = [
        'provided_prefix' => substr($header_signature, 0, 16) . '...',
    ];

    // 5) Timing-safe karşılaştırma
    if ( ! hash_equals( $expected_signature, $header_signature ) ) {
        $debug['reason'] = 'signature_mismatch';
        self::set_hmac_debug($debug);
        return false;
    }

    // Başarılı durum
    $debug['reason'] = 'ok';
    self::set_hmac_debug($debug);

    return true;
}

    /**
     * Run a heartbeat job (mirrors cron behavior).
     *
     * Note: This path is meant for WT-driven scheduling. We clear the local
     * WP-Cron chain to avoid double-sending when WT is already triggering.
     *
     * @return void
     */
    private static function run_heartbeat_job(): void
    {
        $opts = Options::get_all();

        if (!Options::is_connected($opts)) {
            return;
        }

        // Avoid duplicates: WT is the scheduler in this mode.
        Scheduler::clear();

        if (!empty($opts['pause'])) {
            return;
        }

        Heartbeat::with_lock(function () {
            Heartbeat::send('remote');
        });
    }
}
