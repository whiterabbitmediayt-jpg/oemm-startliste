<?php
defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce Hooks
 * Reagiert auf neue Bestellungen und Statusaenderungen
 */
class OEMM_Hooks {

    public static function init() {
        // Neue Bestellung angelegt / Status geaendert -> Teilnehmer-Zeile sicherstellen
        add_action( 'woocommerce_order_status_changed',       array( __CLASS__, 'on_order_status_change' ), 10, 3 );
        add_action( 'woocommerce_checkout_order_processed',   array( __CLASS__, 'on_new_order' ), 10, 1 );
        add_action( 'woocommerce_payment_complete',           array( __CLASS__, 'on_payment_complete' ), 10, 1 );
    }

    public static function on_new_order( $order_id ): void {
        self::process_order( (int) $order_id );
    }

    public static function on_payment_complete( $order_id ): void {
        self::process_order( (int) $order_id );
    }

    public static function on_order_status_change( $order_id, $old_status, $new_status ): void {
        if ( in_array( $new_status, array( 'completed', 'processing' ), true ) ) {
            self::process_order( (int) $order_id );
        }
    }

    /**
     * Prueft ob die Bestellung ein gueltiges Produkt enthaelt
     * und legt (falls noetig) den Teilnehmer-Eintrag an + generiert Tokens
     */
    private static function process_order( int $order_id ): void {
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        $customer_id = (int) $order->get_customer_id();
        if ( ! $customer_id ) return;

        $products = OEMM_Settings::get_product_ids();

        foreach ( $order->get_items() as $item ) {
            if ( in_array( (int) $item->get_product_id(), $products, true ) ) {
                // Teilnehmer anlegen + Tokens generieren
                OEMM_Participant::ensure_row( $customer_id );
                OEMM_Token::get_or_create( $customer_id );
                return;
            }
        }
    }
}
