<?php
defined( 'ABSPATH' ) || exit;

/**
 * Token-Generierung (SHA-256, sicher, stabil pro Customer)
 * Zwei Tokens: 'app' und 'paper'
 */
class OEMM_Token {

    /**
     * Generiert einen stabilen Token fuer einen Kunden
     * Token aendert sich NIE nach Generierung - Daten dahinter werden aktuell gehalten
     *
     * @param int    $customer_id  WooCommerce Customer ID
     * @param string $channel      'app' oder 'paper'
     * @param int    $year         Event-Jahr
     * @return string 16-Zeichen Hex-Token (URL-sicher)
     */
    public static function generate( int $customer_id, string $channel, int $year ): string {
        $salt = OEMM_Settings::get_token_salt();
        $raw  = hash( 'sha256', $customer_id . '|' . $channel . '|' . $year . '|' . $salt );
        // Ersten 16 Bytes (32 Hex-Zeichen) nehmen -> kurz genug fuer QR, lang genug fuer Sicherheit
        return substr( $raw, 0, 32 );
    }

    /**
     * Erstellt (falls nicht vorhanden) und gibt beide Tokens fuer einen Kunden zurueck
     * Gibt array['app'] und array['paper'] zurueck
     */
    public static function get_or_create( int $customer_id ): array {
        global $wpdb;
        $table = $wpdb->prefix . 'oemm_participants';
        $year  = OEMM_Settings::get_event_year();

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT token_app, token_paper FROM {$table} WHERE customer_id = %d AND event_year = %d",
            $customer_id, $year
        ) );

        $token_app   = $row->token_app   ?? self::generate( $customer_id, 'app',   $year );
        $token_paper = $row->token_paper ?? self::generate( $customer_id, 'paper', $year );

        // Sicherstellen dass Zeile existiert
        OEMM_Participant::ensure_row( $customer_id );

        // Tokens speichern falls noch nicht vorhanden
        if ( ! $row || ! $row->token_app ) {
            $wpdb->update(
                $table,
                array( 'token_app' => $token_app, 'token_paper' => $token_paper ),
                array( 'customer_id' => $customer_id, 'event_year' => $year ),
                array( '%s', '%s' ),
                array( '%d', '%d' )
            );
        }

        return array(
            'app'   => $token_app,
            'paper' => $token_paper,
        );
    }

    /**
     * Token aufloesen -> gibt customer_id zurueck oder NULL wenn ungueltig
     */
    public static function resolve( string $token ): ?int {
        global $wpdb;
        $table = $wpdb->prefix . 'oemm_participants';

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT customer_id, token_app, token_paper FROM {$table} WHERE token_app = %s OR token_paper = %s",
            $token, $token
        ) );

        if ( ! $row ) {
            return null;
        }

        // Scan-Counter erhoehen
        if ( $row->token_app === $token ) {
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$table} SET scan_count_app = scan_count_app + 1 WHERE customer_id = %d",
                $row->customer_id
            ) );
        } else {
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$table} SET scan_count_paper = scan_count_paper + 1 WHERE customer_id = %d",
                $row->customer_id
            ) );
        }

        return (int) $row->customer_id;
    }
}
