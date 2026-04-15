<?php
defined( 'ABSPATH' ) || exit;

/**
 * Firestore Sync via Service Account (JWT OAuth2)
 *
 * Schreibt Fahrerdaten in Cloud Firestore (nicht Realtime Database).
 * Authentifizierung: Service Account Key (JSON-Datei) → JWT → OAuth2 Access Token
 *
 * Firestore-Pfad:
 *   artifacts/{app_id}/public/data/registered_participants/{order_id}
 *
 * Felder:
 *   orderId, startNumber, nickname (Vorname Nachname), phoneNumber
 *
 * Konfiguration (ÖMM Einstellungen):
 *   oemm_firebase_credentials_path  Absoluter Pfad zur Service Account JSON-Datei
 *                                   (am besten AUSSERHALB des Webroot ablegen!)
 *   oemm_firebase_project_id        Firebase Project ID (moped-tracker)
 *   oemm_firebase_app_id            Firebase App ID (1:xxx:web:xxx)
 */
class OEMM_Firebase {

    const PROJECT_ID = 'moped-tracker';
    const APP_ID     = '1:1087816067648:web:ddca938996fc936cfccda5';

    // -------------------------------------------------------------------------
    // Öffentliche API
    // -------------------------------------------------------------------------

    /**
     * Einzelnen Teilnehmer zu Firestore pushen
     *
     * @param int $customer_id  WooCommerce Customer ID
     * @return bool
     */
    public static function sync_participant( int $customer_id ): bool {
        $creds_path = get_option( 'oemm_firebase_credentials_path', '' );
        if ( ! $creds_path || ! file_exists( $creds_path ) ) {
            error_log( '[OEMM Firebase] Credentials-Datei nicht gefunden: ' . $creds_path );
            return false;
        }

        $p = OEMM_Participant::get( $customer_id );
        if ( ! $p || is_null( $p['startnumber'] ) ) {
            return false;
        }

        $token = self::get_oauth_token( $creds_path );
        if ( ! $token ) {
            error_log( '[OEMM Firebase] OAuth Token konnte nicht geholt werden.' );
            return false;
        }

        return self::write_to_firestore( $token, $p );
    }

    /**
     * Alle Teilnehmer zu Firestore pushen (Bulk-Sync)
     *
     * @return array [ 'success' => int, 'failed' => int ]
     */
    public static function sync_all(): array {
        $creds_path = get_option( 'oemm_firebase_credentials_path', '' );
        if ( ! $creds_path || ! file_exists( $creds_path ) ) {
            error_log( '[OEMM Firebase] Credentials-Datei nicht gefunden für Bulk-Sync.' );
            return array( 'success' => 0, 'failed' => 0 );
        }

        // Token einmal holen für alle Requests (gültig 1h)
        $token = self::get_oauth_token( $creds_path );
        if ( ! $token ) {
            return array( 'success' => 0, 'failed' => 0 );
        }

        $participants = OEMM_Participant::get_all();
        $success = 0;
        $failed  = 0;

        foreach ( $participants as $p ) {
            if ( is_null( $p['startnumber'] ) ) continue;
            if ( self::write_to_firestore( $token, $p ) ) {
                $success++;
            } else {
                $failed++;
            }
        }

        return array( 'success' => $success, 'failed' => $failed );
    }

    // -------------------------------------------------------------------------
    // OAuth2 JWT Token
    // -------------------------------------------------------------------------

    /**
     * Generiert einen OAuth2 Access Token aus dem Service Account Key
     *
     * @param string $creds_path  Pfad zur Service Account JSON-Datei
     * @return string|false       Access Token oder false bei Fehler
     */
    private static function get_oauth_token( string $creds_path ) {
        // Token aus WP Transient Cache (1h gültig, 55min cachen)
        $cached = get_transient( 'oemm_firebase_oauth_token' );
        if ( $cached ) {
            return $cached;
        }

        $key_data = json_decode( file_get_contents( $creds_path ), true );
        if ( ! $key_data || empty( $key_data['private_key'] ) || empty( $key_data['client_email'] ) ) {
            error_log( '[OEMM Firebase] Ungültige Credentials-Datei.' );
            return false;
        }

        // JWT Header + Claims
        $now    = time();
        $header = self::base64url_encode( wp_json_encode( array(
            'alg' => 'RS256',
            'typ' => 'JWT',
        ) ) );
        $claims = self::base64url_encode( wp_json_encode( array(
            'iss'   => $key_data['client_email'],
            'scope' => 'https://www.googleapis.com/auth/datastore',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'exp'   => $now + 3600,
            'iat'   => $now,
        ) ) );

        $signing_input = $header . '.' . $claims;

        // Mit Private Key signieren (RSA SHA-256)
        $signature = '';
        if ( ! openssl_sign( $signing_input, $signature, $key_data['private_key'], 'sha256WithRSAEncryption' ) ) {
            error_log( '[OEMM Firebase] JWT Signierung fehlgeschlagen.' );
            return false;
        }

        $jwt = $signing_input . '.' . self::base64url_encode( $signature );

        // Token bei Google holen
        $response = wp_remote_post( 'https://oauth2.googleapis.com/token', array(
            'timeout' => 15,
            'body'    => array(
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ),
        ) );

        if ( is_wp_error( $response ) ) {
            error_log( '[OEMM Firebase] Token-Request Fehler: ' . $response->get_error_message() );
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $access_token = $body['access_token'] ?? false;

        if ( ! $access_token ) {
            error_log( '[OEMM Firebase] Kein Access Token in Response: ' . wp_remote_retrieve_body( $response ) );
            return false;
        }

        // 55 Minuten cachen (Token läuft nach 60min ab)
        set_transient( 'oemm_firebase_oauth_token', $access_token, 55 * MINUTE_IN_SECONDS );

        return $access_token;
    }

    // -------------------------------------------------------------------------
    // Firestore Write
    // -------------------------------------------------------------------------

    /**
     * Schreibt einen Teilnehmer in Firestore (PATCH = create or update)
     *
     * Pfad: artifacts/{app_id}/public/data/registered_participants/{order_id}
     *
     * @param string $token  OAuth2 Access Token
     * @param array  $p      Teilnehmer-Array von OEMM_Participant::get()
     * @return bool
     */
    private static function write_to_firestore( string $token, array $p ): bool {
        $project_id = self::PROJECT_ID;
        $app_id     = self::APP_ID;
        $order_id   = (string) ( $p['order_id'] ?? '' );

        if ( ! $order_id ) {
            return false;
        }

        // Firestore REST Endpoint
        $url = sprintf(
            'https://firestore.googleapis.com/v1/projects/%s/databases/(default)/documents/artifacts/%s/public/data/registered_participants/%s',
            rawurlencode( $project_id ),
            rawurlencode( $app_id ),
            rawurlencode( $order_id )
        );

        // Firestore erwartet { "fields": { "field": { "stringValue": "..." } } }
        $nickname = trim( ( $p['billing_first_name'] ?? '' ) . ' ' . ( $p['billing_last_name'] ?? '' ) );

        $payload = wp_json_encode( array(
            'fields' => array(
                'orderId'     => array( 'stringValue' => $order_id ),
                'startNumber' => array( 'stringValue' => (string) ( $p['startnumber'] ?? '' ) ),
                'nickname'    => array( 'stringValue' => $nickname ),
                'phoneNumber' => array( 'stringValue' => (string) ( $p['billing_phone'] ?? '' ) ),
            ),
        ) );

        $response = wp_remote_request( $url, array(
            'method'  => 'PATCH',
            'timeout' => 10,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ),
            'body'    => $payload,
        ) );

        if ( is_wp_error( $response ) ) {
            error_log( '[OEMM Firebase] Firestore Write Fehler order_id=' . $order_id . ': ' . $response->get_error_message() );
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code < 200 || $code >= 300 ) {
            error_log( '[OEMM Firebase] Firestore HTTP ' . $code . ' order_id=' . $order_id . ': ' . wp_remote_retrieve_body( $response ) );
            return false;
        }

        return true;
    }

    // -------------------------------------------------------------------------
    // Hilfsfunktionen
    // -------------------------------------------------------------------------

    private static function base64url_encode( string $data ): string {
        return str_replace( array( '+', '/', '=' ), array( '-', '_', '' ), base64_encode( $data ) );
    }
}
