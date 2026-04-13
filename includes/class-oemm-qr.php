<?php
defined( 'ABSPATH' ) || exit;

/**
 * QR-Code Generierung
 * Nutzt die Google Charts API (kein zusaetzliches Plugin noetig)
 * oder optional eine lokale PHP-Bibliothek
 */
class OEMM_QR {

    /**
     * Gibt die URL zu einem QR-Code-Bild zurueck
     * Ziel-URL: {app_url}{token}
     *
     * @param string $token
     * @param int    $size  Pixel (Standard: 200)
     * @return string URL zum QR-Code-Bild
     */
    public static function get_url( string $token, int $size = 200 ): string {
        $app_url  = OEMM_Settings::get_app_url();
        $target   = $app_url . $token;

        // QR via quickchart.io (DSGVO: nur wenn noetig - alternativ lokal einbinden)
        // Einfach + zuverlaessig, kein API-Key noetig
        return 'https://quickchart.io/qr?text=' . rawurlencode( $target )
             . '&size=' . $size
             . '&format=png'
             . '&margin=1';
    }

    /**
     * Gibt ein <img>-Tag fuer den QR-Code zurueck
     */
    public static function get_img( string $token, string $label = '', int $size = 200 ): string {
        $url = self::get_url( $token, $size );
        $alt = esc_attr( $label ?: 'QR Code' );
        return '<img src="' . esc_url( $url ) . '" alt="' . $alt . '" width="' . $size . '" height="' . $size . '" />';
    }

    /**
     * Gibt die vollstaendige Ziel-URL zurueck (nicht den QR-Code-Bild-URL)
     */
    public static function get_target_url( string $token ): string {
        return OEMM_Settings::get_app_url() . $token;
    }
}
