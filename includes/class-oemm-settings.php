<?php
defined( 'ABSPATH' ) || exit;

/**
 * Einstellungen lesen / schreiben (zentrale Helfer-Klasse)
 */
class OEMM_Settings {

    /**
     * Alle verfügbaren Felder mit Labels (Reihenfolge = Anzeigereihenfolge)
     */
    public static function all_fields(): array {
        return array(
            // Startnummer & Tokens
            'startnumber'                    => 'Startnummer',
            'token_app'                      => 'Token App',
            'token_paper'                    => 'Token Zettel',

            // Persönliche Daten
            'billing_first_name'             => 'Vorname',
            'billing_last_name'              => 'Nachname',
            'geschlecht'                     => 'Geschlecht',
            'geburtsdatum'                   => 'Geburtsdatum',
            'billing_company'                => 'Firma',

            // Kontakt
            'customer_email'                 => 'E-Mail',
            'billing_phone'                  => 'Telefon',

            // Adresse
            'billing_address_1'              => 'Adresse',
            'billing_address_2'              => 'Adresse 2',
            'billing_postcode'               => 'PLZ',
            'billing_city'                   => 'Ort',
            'billing_country'                => 'Land',

            // Bestellung
            'order_id'                       => 'Bestellnummer',
            'order_date'                     => 'Bestelldatum',
            'order_status'                   => 'Bestellstatus',
            'order_total'                    => 'Bestellsumme',
            'order_subtotal'                 => 'Zwischensumme',
            'product_name'                   => 'Produkt',
            'sku'                            => 'SKU',
            'shirt_size'                     => 'T-Shirt Größe',
            'line_total'                     => 'Positionspreis',
            'coupon_items'                   => 'Gutschein',
            'customer_note'                  => 'Bestellnotiz',

            // Attribution
            'attribution_device'             => 'Gerät',
            'attribution_source'             => 'Quelle',
            'attribution_utm_source'         => 'UTM Source',
            'attribution_session_count'      => 'Sessions',
            'attribution_session_pages'      => 'Seitenaufrufe',
        );
    }

    public static function init() {
        // nichts zu laden - wird on-demand abgefragt
    }

    // -------------------------------------------------------------------------
    // Getter
    // -------------------------------------------------------------------------

    public static function get_event_year(): int {
        return (int) get_option( 'oemm_event_year', 2026 );
    }

    /** ON/OFF Schalter - gibt true zurück wenn App-Button aktiv */
    public static function is_active(): bool {
        return (bool) get_option( 'oemm_event_active', false );
    }

    /** Produkt-IDs als Array */
    public static function get_product_ids(): array {
        $raw = get_option( 'oemm_product_ids', '' );
        return array_filter( array_map( 'intval', explode( ',', $raw ) ) );
    }

    public static function get_token_salt(): string {
        return (string) get_option( 'oemm_token_salt', '' );
    }

    public static function get_app_url(): string {
        return (string) get_option( 'oemm_app_url', 'https://moped-tracker.web.app/t/' );
    }

    public static function get_startnumber_start(): int {
        return (int) get_option( 'oemm_startnumber_start', 1 );
    }

    /** Aktivierte Felder als Array */
    public static function get_fields(): array {
        $saved = get_option( 'oemm_fields', null );
        if ( $saved !== null ) {
            return (array) $saved;
        }
        // Standard: die wichtigsten Felder vorausgewählt
        $defaults = array(
            'startnumber', 'billing_first_name', 'billing_last_name',
            'geschlecht', 'customer_email', 'billing_phone',
            'billing_address_1', 'billing_postcode', 'billing_city', 'billing_country',
            'shirt_size', 'product_name',
        );
        return array_fill_keys( $defaults, 1 );
    }

    // -------------------------------------------------------------------------
    // Setter
    // -------------------------------------------------------------------------

    public static function save( array $data ): void {
        if ( isset( $data['event_year'] ) ) {
            update_option( 'oemm_event_year', absint( $data['event_year'] ) );
        }
        if ( isset( $data['event_active'] ) ) {
            update_option( 'oemm_event_active', '1' );
        } else {
            update_option( 'oemm_event_active', '0' );
        }
        if ( isset( $data['product_ids'] ) ) {
            $clean = implode( ',', array_filter( array_map( 'intval', explode( ',', $data['product_ids'] ) ) ) );
            update_option( 'oemm_product_ids', $clean );
        }
        if ( isset( $data['startnumber_start'] ) ) {
            update_option( 'oemm_startnumber_start', absint( $data['startnumber_start'] ) );
        }
        if ( isset( $data['app_url'] ) ) {
            update_option( 'oemm_app_url', esc_url_raw( $data['app_url'] ) );
        }
        if ( isset( $data['github_token'] ) && ! empty( $data['github_token'] ) ) {
            // Token nur speichern wenn er sich geändert hat (nicht bei leerem Submit)
            $token = sanitize_text_field( $data['github_token'] );
            if ( str_starts_with( $token, 'ghp_' ) ) {
                update_option( 'oemm_github_token', $token );
                // Update-Cache leeren damit sofort geprüft wird
                delete_transient( 'oemm_github_release' );
            }
        }
        if ( isset( $data['api_key'] ) ) {
            $key = preg_replace( '/[^a-zA-Z0-9\-_]/', '', $data['api_key'] );
            update_option( 'oemm_api_key', $key );
        }
        // Daten-Lösch-Schalter (bewusst explizit)
        update_option( 'oemm_delete_data_on_uninstall', isset( $data['delete_data_on_uninstall'] ) ? '1' : '0' );

        if ( isset( $data['fields'] ) && is_array( $data['fields'] ) ) {
            $allowed = array_keys( self::all_fields() );
            $fields  = array();
            foreach ( $allowed as $f ) {
                $fields[ $f ] = isset( $data['fields'][ $f ] ) ? 1 : 0;
            }
            update_option( 'oemm_fields', $fields );
        }
    }
}
