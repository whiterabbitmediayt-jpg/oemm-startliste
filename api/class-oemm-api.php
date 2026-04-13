<?php
defined( 'ABSPATH' ) || exit;

/**
 * REST API v1 — für Urban's App
 * Namespace: oemm/v1
 *
 * Endpoints:
 *   GET  /oemm/v1/participant          ?token=XYZ   -> Teilnehmerdaten anhand Token
 *   GET  /oemm/v1/status                            -> Plugin-Status (Version, aktiv, Jahr)
 *   POST /oemm/v1/checkpoint                        -> Zwischenzeit speichern (zukuenftig)
 *   POST /oemm/v1/photo                             -> Foto zuordnen (zukuenftig)
 *
 * Authentifizierung für sensible Endpoints:
 *   - GET /participant: Token = Authentication (kein Login noetig, Token = Identitaet)
 *   - GET /status: offen
 *   - POST /checkpoint, /photo: API-Key im Header X-OEMM-Key
 */
class OEMM_API {

    public static function init() {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    public static function register_routes() {
        $ns = 'oemm/v1';

        // Status - offen
        register_rest_route( $ns, '/status', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'status' ),
            'permission_callback' => '__return_true',
        ) );

        // Teilnehmer via Token abfragen - Token = Authentifizierung
        register_rest_route( $ns, '/participant', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'get_participant' ),
            'permission_callback' => '__return_true',
            'args'                => array(
                'token' => array(
                    'required' => true,
                    'type'     => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ),
            ),
        ) );

        // Checkpoint-Zeiten (Geofencing) - für zukuenftige Erweiterung
        register_rest_route( $ns, '/checkpoint', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'post_checkpoint' ),
            'permission_callback' => array( __CLASS__, 'check_api_key' ),
        ) );

        // Admin: Massen-Import (nur für Admins)
        register_rest_route( $ns, '/migrate', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'run_migration' ),
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
        ) );

        register_rest_route( $ns, '/import', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'import_participants' ),
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
        ) );

        // Admin: Update-Cache leeren (nur für Admins)
        register_rest_route( $ns, '/clear-update-cache', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'clear_update_cache' ),
            'permission_callback' => function() { return current_user_can( 'manage_options' ); },
        ) );

        // Foto-Zuordnung - für Christian & Marcel (Fotopoint)
        register_rest_route( $ns, '/photo', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'post_photo' ),
            'permission_callback' => array( __CLASS__, 'check_api_key' ),
        ) );
    }

    // -------------------------------------------------------------------------
    // Permission: API-Key Check
    // -------------------------------------------------------------------------

    public static function check_api_key( WP_REST_Request $request ): bool {
        $key     = $request->get_header( 'X-OEMM-Key' );
        $stored  = get_option( 'oemm_api_key', '' );
        if ( ! $stored ) return false;
        return hash_equals( $stored, (string) $key );
    }

    // -------------------------------------------------------------------------
    // Callbacks
    // -------------------------------------------------------------------------

    /**
     * GET /oemm/v1/status
     * Gibt Plugin-Version, Event-Jahr und Aktiv-Status zurück
     */
    public static function status( WP_REST_Request $request ): WP_REST_Response {
        return new WP_REST_Response( array(
            'plugin'  => 'ÖMM Startliste',
            'version' => OEMM_VERSION,
            'year'    => OEMM_Settings::get_event_year(),
            'active'  => OEMM_Settings::is_active(),
        ), 200 );
    }

    /**
     * GET /oemm/v1/participant?token=XYZ
     *
     * Gibt die Teilnehmerdaten für einen gültigen Token zurück.
     * Zaehlt Scan-Counter (App vs. Papier).
     *
     * Response (minimale Daten - nur was die App braucht):
     * {
     *   "startnumber": 42,
     *   "first_name": "Max",
     *   "phone": "+436601234567",
     *   "shirt_size": "L",
     *   "order_id": 8779,
     *   "channel": "app"   // oder "paper" - damit die App weiss welcher QR gescannt wurde
     * }
     */
    public static function get_participant( WP_REST_Request $request ): WP_REST_Response {
        $token = $request->get_param( 'token' );

        if ( ! $token || strlen( $token ) < 10 ) {
            return new WP_REST_Response( array( 'error' => 'Token ungueltig.' ), 400 );
        }

        if ( ! OEMM_Settings::is_active() ) {
            return new WP_REST_Response( array( 'error' => 'Event nicht aktiv.' ), 403 );
        }

        // Token aufloesen (erhoeht dabei auch den Scan-Counter)
        $customer_id = OEMM_Token::resolve( $token );

        if ( ! $customer_id ) {
            return new WP_REST_Response( array( 'error' => 'Token nicht gefunden.' ), 404 );
        }

        $p = OEMM_Participant::get( $customer_id );

        if ( ! $p || is_null( $p['startnumber'] ) ) {
            return new WP_REST_Response( array( 'error' => 'Keine Startnummer zugewiesen.' ), 404 );
        }

        // Ermitteln ob App oder Papier-Token
        $tokens  = OEMM_Token::get_or_create( $customer_id );
        $channel = ( $tokens['app'] === $token ) ? 'app' : 'paper';

        // Minimale Antwort - nur notwendige Daten (DSGVO!)
        return new WP_REST_Response( array(
            'startnumber' => $p['startnumber'], // String: '1', '01', '007a'
            'first_name'  => $p['first_name'],
            'phone'       => $p['phone'],
            'shirt_size'  => $p['shirt_size'],
            'order_id'    => $p['order_id'],
            'channel'     => $channel,
        ), 200 );
    }

    /**
     * POST /oemm/v1/checkpoint
     * Speichert eine Zwischenzeit für einen Teilnehmer
     *
     * Body: { "token": "XYZ", "checkpoint": "penserjoch", "timestamp": "2026-08-31T14:23:00Z" }
     */
    public static function post_checkpoint( WP_REST_Request $request ): WP_REST_Response {
        $token      = sanitize_text_field( $request->get_param( 'token' ) ?? '' );
        $checkpoint = sanitize_text_field( $request->get_param( 'checkpoint' ) ?? '' );
        $timestamp  = sanitize_text_field( $request->get_param( 'timestamp' ) ?? current_time( 'c' ) );

        $customer_id = OEMM_Token::resolve( $token );
        if ( ! $customer_id ) {
            return new WP_REST_Response( array( 'error' => 'Token nicht gefunden.' ), 404 );
        }

        // In User-Meta speichern (einfach, erweiterbar)
        $key  = 'oemm_checkpoint_' . OEMM_Settings::get_event_year();
        $data = get_user_meta( $customer_id, $key, true ) ?: array();
        $data[ $checkpoint ] = $timestamp;
        update_user_meta( $customer_id, $key, $data );

        return new WP_REST_Response( array( 'success' => true ), 200 );
    }

    /**
     * POST /oemm/v1/photo
     * Ordnet ein Foto einem Teilnehmer zu
     *
     * Body: { "token": "XYZ", "photo_url": "https://..." }
     */
    /**
     * POST /oemm/v1/import
     * Massen-Import: customer_id + optional startnumber
     * Body: { "data": [{"customer_id": 123, "startnumber": 1}, ...] }
     */
    public static function import_participants( WP_REST_Request $request ): WP_REST_Response {
        $data = $request->get_json_params();
        $rows = $data['data'] ?? array();

        if ( empty( $rows ) || ! is_array( $rows ) ) {
            return new WP_REST_Response( array( 'error' => 'Keine Daten.' ), 400 );
        }

        $count = 0;
        foreach ( $rows as $row ) {
            $cid = intval( $row['customer_id'] ?? 0 );
            if ( ! $cid ) continue;

            OEMM_Participant::ensure_row( $cid );
            OEMM_Token::get_or_create( $cid );

            if ( isset( $row['startnumber'] ) && $row['startnumber'] !== null && $row['startnumber'] !== '' ) {
                // Startnummer als String übergeben (z.B. '01', '007a')
                OEMM_Participant::set_startnumber( $cid, (string) $row['startnumber'] );
            }

            // billing_title in Order-Meta schreiben (1=Herr, 2=Frau)
            if ( isset( $row['billing_title'] ) && $row['billing_title'] !== '' ) {
                $p = OEMM_Participant::get( $cid );
                if ( ! empty( $p['order_id'] ) ) {
                    $order = wc_get_order( $p['order_id'] );
                    if ( $order ) {
                        $order->update_meta_data( '_billing_title', sanitize_text_field( $row['billing_title'] ) );
                        $order->save();
                    }
                }
            }

            $count++;
        }

        return new WP_REST_Response( array( 'success' => true, 'imported' => $count ), 200 );
    }

    public static function clear_update_cache( WP_REST_Request $request ): WP_REST_Response {
        OEMM_Updater::clear_cache();
        return new WP_REST_Response( array( 'success' => true, 'message' => 'Update-Cache geleert.' ), 200 );
    }

    public static function post_photo( WP_REST_Request $request ): WP_REST_Response {
        $token     = sanitize_text_field( $request->get_param( 'token' ) ?? '' );
        $photo_url = esc_url_raw( $request->get_param( 'photo_url' ) ?? '' );

        $customer_id = OEMM_Token::resolve( $token );
        if ( ! $customer_id ) {
            return new WP_REST_Response( array( 'error' => 'Token nicht gefunden.' ), 404 );
        }

        $key    = 'oemm_photos_' . OEMM_Settings::get_event_year();
        $photos = get_user_meta( $customer_id, $key, true ) ?: array();
        $photos[] = array(
            'url'  => $photo_url,
            'time' => current_time( 'c' ),
        );
        update_user_meta( $customer_id, $key, $photos );

        return new WP_REST_Response( array( 'success' => true, 'count' => count( $photos ) ), 200 );
    }

    /**
     * POST /oemm/v1/migrate — Einmalige DB-Migration (nur Admin)
     */
    public static function run_migration( WP_REST_Request $request ): WP_REST_Response {
        global $wpdb;
        $table = $wpdb->prefix . 'oemm_participants';

        // Spaltentyp prüfen
        $col_type = $wpdb->get_var( "SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}' AND COLUMN_NAME = 'startnumber'" );

        if ( strtolower( $col_type ) === 'varchar' ) {
            return new WP_REST_Response( array( 'status' => 'already_varchar', 'col_type' => $col_type ), 200 );
        }

        // Migration durchführen
        $result = $wpdb->query( "ALTER TABLE {$table} MODIFY COLUMN startnumber VARCHAR(20) DEFAULT NULL" );

        $col_type_after = $wpdb->get_var( "SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}' AND COLUMN_NAME = 'startnumber'" );

        return new WP_REST_Response( array(
            'status'         => 'migrated',
            'col_type_before' => $col_type,
            'col_type_after'  => $col_type_after,
        ), 200 );
    }

}
