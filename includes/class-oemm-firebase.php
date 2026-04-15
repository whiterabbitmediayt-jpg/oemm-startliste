<?php
defined( 'ABSPATH' ) || exit;

/**
 * Firebase Sync
 *
 * Schickt Teilnehmerdaten an Urbans Besenwagen-App (Firebase REST API).
 *
 * Konfiguration (in ÖMM Einstellungen):
 *   oemm_firebase_url     z.B. https://PROJEKT-default-rtdb.europe-west1.firebasedatabase.app
 *   oemm_firebase_secret  Firebase Database Secret (Legacy Auth) ODER ID-Token
 *
 * Dokument-Pfad: /participants/{event_year}/{token_app}.json
 *   → Jeder Fahrer hat genau ein Dokument, identifiziert über seinen App-Token
 *   → Schreiben = PUT (idempotent, überschreibt immer)
 *
 * Felder die gesynct werden (nur was die Besenwagen-App braucht):
 *   startnumber, first_name, last_name, company, phone, qr_url, token_app, updated_at
 */
class OEMM_Firebase {

    /**
     * Einzelnen Teilnehmer zu Firebase pushen
     * Wird aufgerufen bei: neue Bestellung, Datenänderung, manueller Sync
     *
     * @param int $customer_id  WooCommerce Customer ID
     * @return bool             true bei Erfolg, false bei Fehler
     */
    public static function sync_participant( int $customer_id ): bool {
        $firebase_url    = get_option( 'oemm_firebase_url', '' );
        $firebase_secret = get_option( 'oemm_firebase_secret', '' );

        if ( ! $firebase_url || ! $firebase_secret ) {
            return false; // Firebase nicht konfiguriert — kein Fehler, einfach skip
        }

        $p = OEMM_Participant::get( $customer_id );
        if ( ! $p ) {
            return false;
        }

        $tokens = OEMM_Token::get_or_create( $customer_id );
        $token  = $tokens['app'] ?? '';

        if ( ! $token ) {
            return false;
        }

        $year = OEMM_Settings::get_event_year();

        // Nur die für Besenwagen-App relevanten Felder
        $payload = array(
            'startnumber' => $p['startnumber'],
            'first_name'  => $p['billing_first_name'],
            'last_name'   => $p['billing_last_name'],
            'company'     => $p['billing_company'],
            'phone'       => $p['billing_phone'],
            'qr_url'      => OEMM_QR::get_target_url( $token ),
            'token_app'   => $token,
            'updated_at'  => gmdate( 'c' ),
        );

        // PUT /participants/{year}/{token}.json?auth=SECRET
        $endpoint = rtrim( $firebase_url, '/' )
                  . '/participants/' . $year . '/' . $token . '.json'
                  . '?auth=' . rawurlencode( $firebase_secret );

        $response = wp_remote_request( $endpoint, array(
            'method'  => 'PUT',
            'headers' => array( 'Content-Type' => 'application/json' ),
            'body'    => wp_json_encode( $payload ),
            'timeout' => 10,
        ) );

        if ( is_wp_error( $response ) ) {
            error_log( '[OEMM Firebase] Sync-Fehler für customer_id=' . $customer_id . ': ' . $response->get_error_message() );
            return false;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code < 200 || $code >= 300 ) {
            error_log( '[OEMM Firebase] HTTP ' . $code . ' für customer_id=' . $customer_id );
            return false;
        }

        return true;
    }

    /**
     * Alle Teilnehmer zu Firebase pushen (Bulk-Sync)
     * Für den "Alle Tokens generieren"-Button und manuelle Synchronisation
     *
     * @return array  [ 'success' => int, 'failed' => int ]
     */
    public static function sync_all(): array {
        $participants = OEMM_Participant::get_all();
        $success = 0;
        $failed  = 0;

        foreach ( $participants as $p ) {
            $cid = (int) ( $p['customer_id'] ?? 0 );
            if ( ! $cid ) continue;

            if ( self::sync_participant( $cid ) ) {
                $success++;
            } else {
                $failed++;
            }
        }

        return array( 'success' => $success, 'failed' => $failed );
    }
}
