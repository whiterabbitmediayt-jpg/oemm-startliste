<?php
defined( 'ABSPATH' ) || exit;

/**
 * Teilnehmer-Datenzugriff
 * Liest Daten aus WooCommerce, kombiniert mit oemm_participants Tabelle
 */
class OEMM_Participant {

    /**
     * Stellt sicher dass ein Eintrag in oemm_participants existiert
     */
    public static function ensure_row( int $customer_id ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'oemm_participants';
        $year  = OEMM_Settings::get_event_year();

        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE customer_id = %d AND event_year = %d",
            $customer_id, $year
        ) );

        if ( ! $exists ) {
            $wpdb->insert( $table, array(
                'customer_id' => $customer_id,
                'event_year'  => $year,
            ), array( '%d', '%d' ) );
        }
    }

    /**
     * Gibt den vollstaendigen Teilnehmer-Datensatz zurück
     * Kombiniert WooCommerce-Kundendaten + Bestelldaten + Plugin-Daten
     *
     * @return array|null
     */
    public static function get( int $customer_id ): ?array {
        global $wpdb;
        $table    = $wpdb->prefix . 'oemm_participants';
        $year     = OEMM_Settings::get_event_year();
        $products = OEMM_Settings::get_product_ids();

        // 1. Plugin-Zeile
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE customer_id = %d AND event_year = %d",
            $customer_id, $year
        ), ARRAY_A );

        // 2. WooCommerce Kundendaten
        $wc_customer = new WC_Customer( $customer_id );

        // 3. Relevante Bestellung finden (neueste mit Vereinsmitgliedschaft)
        $orders = wc_get_orders( array(
            'customer_id' => $customer_id,
            'status'      => array( 'completed', 'processing' ),
            'limit'       => 50,
        ) );

        $main_order  = null;
        $shirt_size  = '';

        foreach ( $orders as $order ) {
            foreach ( $order->get_items() as $item ) {
                if ( in_array( (int) $item->get_product_id(), $products, true ) ) {
                    $main_order = $order;
                    // T-Shirt Groesse aus Variation-Meta
                    $size = $item->get_meta( 'pa_size' );
                    if ( $size ) {
                        $shirt_size = strtoupper( $size );
                    }
                    break 2;
                }
            }
        }

        // 4. Geburtsdatum aus Customer-Meta
        $geburtsdatum = get_user_meta( $customer_id, 'billing_geburtsdatum', true );

        // Attribution-Daten aus Order-Meta holen
        $attribution = array(
            'device'        => '',
            'source'        => '',
            'utm_source'    => '',
            'session_count' => '',
            'session_pages' => '',
        );
        if ( $main_order ) {
            $attribution['device']        = $main_order->get_meta( '_wc_order_attribution_device_type' );
            $attribution['source']        = $main_order->get_meta( '_wc_order_attribution_source_type' );
            $attribution['utm_source']    = $main_order->get_meta( '_wc_order_attribution_utm_source' );
            $attribution['session_count'] = $main_order->get_meta( '_wc_order_attribution_session_count' );
            $attribution['session_pages'] = $main_order->get_meta( '_wc_order_attribution_session_pages' );
        }

        // Geschlecht aus Order-Meta (title = 1 = Herr, 2 = Frau)
        $title_raw = $main_order ? $main_order->get_meta( '_billing_title' ) : '';
        $geschlecht = match( (string) $title_raw ) {
            '1'     => 'Herr',
            '2'     => 'Frau',
            default => '',
        };

        return array(
            // Basis-Identifikation
            'customer_id'             => $customer_id,
            'event_year'              => $year,

            // Persönliche Daten
            'billing_first_name'      => $wc_customer->get_billing_first_name(),
            'billing_last_name'       => $wc_customer->get_billing_last_name(),
            'geschlecht'              => $geschlecht,
            'geburtsdatum'            => $geburtsdatum,
            'billing_company'         => $wc_customer->get_billing_company(),

            // Kontakt
            'customer_email'          => $wc_customer->get_email(),
            'billing_phone'           => $wc_customer->get_billing_phone(),

            // Adresse
            'billing_address_1'       => $wc_customer->get_billing_address_1(),
            'billing_address_2'       => $wc_customer->get_billing_address_2(),
            'billing_postcode'        => $wc_customer->get_billing_postcode(),
            'billing_city'            => $wc_customer->get_billing_city(),
            'billing_country'         => $wc_customer->get_billing_country(),

            // Bestellung
            'order_id'                => $main_order ? $main_order->get_id()                              : null,
            'order_date'              => $main_order ? $main_order->get_date_created()?->date('d.m.Y')   : null,
            'order_status'            => $main_order ? $main_order->get_status()                          : null,
            'order_total'             => $main_order ? $main_order->get_total()                           : null,
            'order_subtotal'          => $main_order ? $main_order->get_subtotal()                        : null,
            'product_name'            => $main_order ? self::get_product_name( $main_order )              : null,
            'sku'                     => $main_order ? self::get_sku( $main_order )                       : null,
            'shirt_size'              => $shirt_size,
            'line_total'              => $main_order ? self::get_line_total( $main_order )                : null,
            'coupon_items'            => $main_order ? implode( ', ', $main_order->get_coupon_codes() )   : null,
            'customer_note'           => $main_order ? $main_order->get_customer_note()                   : null,

            // Attribution
            'attribution_device'      => $attribution['device'],
            'attribution_source'      => $attribution['source'],
            'attribution_utm_source'  => $attribution['utm_source'],
            'attribution_session_count' => $attribution['session_count'],
            'attribution_session_pages' => $attribution['session_pages'],

            // Plugin-Daten (Startnummer, Tokens, Scans)
            'startnumber'             => $row['startnumber']       ?? null,
            'token_app'               => $row['token_app']         ?? null,
            'token_paper'             => $row['token_paper']       ?? null,
            'scan_count_app'          => (int) ( $row['scan_count_app']   ?? 0 ),
            'scan_count_paper'        => (int) ( $row['scan_count_paper'] ?? 0 ),
            'notes'                   => $row['notes']             ?? '',
        );
    }

    /**
     * Gibt alle Teilnehmer dieses Jahres zurück die ein gültiges Produkt gekauft haben
     * Verwendet für die Admin-Startliste
     */
    public static function get_all( array $args = array() ): array {
        global $wpdb;
        $table    = $wpdb->prefix . 'oemm_participants';
        $year     = OEMM_Settings::get_event_year();
        $products = OEMM_Settings::get_product_ids();

        if ( empty( $products ) ) {
            return array();
        }

        $placeholders = implode( ',', array_fill( 0, count( $products ), '%d' ) );

        // Alle customer_ids die ein gültiges Produkt haben
        $order_items_table  = $wpdb->prefix . 'woocommerce_order_items';
        $order_itemmeta     = $wpdb->prefix . 'woocommerce_order_itemmeta';
        $posts_table        = $wpdb->prefix . 'posts';

        $query = $wpdb->prepare(
            "SELECT DISTINCT p.post_author as customer_id
             FROM {$posts_table} p
             INNER JOIN {$order_items_table} oi ON oi.order_id = p.ID
             INNER JOIN {$order_itemmeta} oim ON oim.order_item_id = oi.order_item_id
             WHERE p.post_type = 'shop_order'
               AND p.post_status IN ('wc-completed','wc-processing')
               AND oi.order_item_type = 'line_item'
               AND oim.meta_key = '_product_id'
               AND oim.meta_value IN ({$placeholders})
               AND p.post_author > 0",
            ...$products
        );

        // HPOS-kompatibel: neuere WC-Versionen nutzen eigene Orders-Tabelle
        // Fallback über wc_get_orders wenn der Join leer liefert
        $customer_ids = $wpdb->get_col( $query );

        if ( empty( $customer_ids ) ) {
            // HPOS-Variante
            $orders = wc_get_orders( array(
                'limit'  => -1,
                'status' => array( 'completed', 'processing' ),
                'return' => 'ids',
            ) );
            foreach ( $orders as $order_id ) {
                $order = wc_get_order( $order_id );
                if ( ! $order ) continue;
                foreach ( $order->get_items() as $item ) {
                    if ( in_array( (int) $item->get_product_id(), $products, true ) ) {
                        $cid = $order->get_customer_id();
                        if ( $cid ) {
                            $customer_ids[] = $cid;
                        }
                        break;
                    }
                }
            }
            $customer_ids = array_unique( $customer_ids );
        }

        // Plugin-Zeilen sicherstellen + Daten sammeln
        $participants = array();
        foreach ( $customer_ids as $cid ) {
            self::ensure_row( (int) $cid );
            $participants[] = self::get( (int) $cid );
        }

        // Sortierung: nach Startnummer (NULL ans Ende)
        usort( $participants, function( $a, $b ) {
            if ( $a['startnumber'] === null && $b['startnumber'] === null ) return 0;
            if ( $a['startnumber'] === null ) return 1;
            if ( $b['startnumber'] === null ) return -1;
            return $a['startnumber'] <=> $b['startnumber'];
        });

        return $participants;
    }

    /**
     * Hilfsfunktionen für Bestelldetails
     */
    private static function get_product_name( WC_Order $order ): string {
        foreach ( $order->get_items() as $item ) {
            if ( in_array( (int) $item->get_product_id(), OEMM_Settings::get_product_ids(), true ) ) {
                return $item->get_name();
            }
        }
        // Fallback: erstes Item
        foreach ( $order->get_items() as $item ) {
            return $item->get_name();
        }
        return '';
    }

    private static function get_sku( WC_Order $order ): string {
        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            if ( $product ) return $product->get_sku();
        }
        return '';
    }

    private static function get_line_total( WC_Order $order ): ?float {
        foreach ( $order->get_items() as $item ) {
            if ( in_array( (int) $item->get_product_id(), OEMM_Settings::get_product_ids(), true ) ) {
                return (float) $item->get_total();
            }
        }
        return null;
    }

    /**
     * Startnummer setzen
     */
    public static function set_startnumber( int $customer_id, ?int $number ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'oemm_participants';
        $year  = OEMM_Settings::get_event_year();

        self::ensure_row( $customer_id );

        $result = $wpdb->update(
            $table,
            array( 'startnumber' => $number ),
            array( 'customer_id' => $customer_id, 'event_year' => $year ),
            array( is_null( $number ) ? '%s' : '%d' ),
            array( '%d', '%d' )
        );

        return $result !== false;
    }

    /**
     * Nächste freie Startnummer automatisch auffüllen
     * Vergibt fortlaufend ab oemm_startnumber_start, ueberspringt bereits vergebene
     */
    public static function fill_startnumbers(): int {
        global $wpdb;
        $table = $wpdb->prefix . 'oemm_participants';
        $year  = OEMM_Settings::get_event_year();
        $start = OEMM_Settings::get_startnumber_start();

        // Alle bereits vergebenen Nummern dieses Jahres
        $taken = $wpdb->get_col( $wpdb->prepare(
            "SELECT startnumber FROM {$table} WHERE event_year = %d AND startnumber IS NOT NULL ORDER BY startnumber ASC",
            $year
        ) );
        $taken = array_map( 'intval', $taken );

        // Alle Teilnehmer ohne Startnummer
        $without = $wpdb->get_col( $wpdb->prepare(
            "SELECT customer_id FROM {$table} WHERE event_year = %d AND startnumber IS NULL ORDER BY id ASC",
            $year
        ) );

        if ( empty( $without ) ) {
            return 0;
        }

        $next    = $start;
        $counter = 0;

        foreach ( $without as $customer_id ) {
            // Nächste freie Nummer finden
            while ( in_array( $next, $taken, true ) ) {
                $next++;
            }
            $wpdb->update(
                $table,
                array( 'startnumber' => $next ),
                array( 'customer_id' => $customer_id, 'event_year' => $year ),
                array( '%d' ),
                array( '%d', '%d' )
            );
            $taken[] = $next;
            $next++;
            $counter++;
        }

        return $counter;
    }

    /**
     * Prüfen ob ein Kunde eine gültige Startnummer für dieses Jahr hat
     */
    public static function has_valid_startnumber( int $customer_id ): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'oemm_participants';
        $year  = OEMM_Settings::get_event_year();

        $sn = $wpdb->get_var( $wpdb->prepare(
            "SELECT startnumber FROM {$table} WHERE customer_id = %d AND event_year = %d",
            $customer_id, $year
        ) );

        return ! is_null( $sn );
    }
}
