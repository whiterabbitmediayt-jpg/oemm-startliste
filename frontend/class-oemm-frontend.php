<?php
defined( 'ABSPATH' ) || exit;

/**
 * Frontend: WooCommerce "Mein Konto" Dashboard-Button
 */
class OEMM_Frontend {

    public static function init() {
        add_action( 'woocommerce_account_dashboard', array( __CLASS__, 'render_dashboard_button' ) );
        add_action( 'wp_enqueue_scripts',            array( __CLASS__, 'enqueue_assets' ) );
    }

    public static function enqueue_assets() {
        if ( ! is_account_page() ) return;

        wp_enqueue_style(
            'oemm-frontend',
            OEMM_PLUGIN_URL . 'frontend/frontend.css',
            array(),
            OEMM_VERSION
        );
    }

    /**
     * Zeigt den App-Button im WooCommerce Dashboard
     * Nur wenn:
     *   1. App-Button global AN (Einstellungs-Schalter)
     *   2. Kunde eingeloggt
     *   3. Kunde hat eine gueltige Startnummer fuer dieses Jahr
     */
    public static function render_dashboard_button() {
        if ( ! is_user_logged_in() ) return;
        if ( ! OEMM_Settings::is_active() ) return;

        $customer_id = get_current_user_id();

        if ( ! OEMM_Participant::has_valid_startnumber( $customer_id ) ) return;

        $participant = OEMM_Participant::get( $customer_id );
        $tokens      = OEMM_Token::get_or_create( $customer_id );
        $app_url     = OEMM_QR::get_target_url( $tokens['app'] );
        $year        = OEMM_Settings::get_event_year();

        ?>
        <div class="oemm-dashboard-section">
            <div class="oemm-app-card">
                <div class="oemm-app-card-header">
                    <span class="oemm-logo">🏍</span>
                    <h3>Ötztaler Moped Marathon <?php echo esc_html( $year ); ?></h3>
                </div>

                <div class="oemm-app-card-body">
                    <div class="oemm-startnumber-display">
                        <span class="oemm-sn-label">Deine Startnummer</span>
                        <span class="oemm-sn-number"><?php echo esc_html( $participant['startnumber'] ); ?></span>
                    </div>

                    <a href="<?php echo esc_url( $app_url ); ?>"
                       class="oemm-app-button"
                       target="_blank"
                       rel="noopener">
                        📱 Zur ÖMM App
                    </a>

                    <p class="oemm-app-hint">
                        Öffne die App um dein persönliches Event-Profil zu sehen.
                    </p>
                </div>
            </div>
        </div>
        <?php
    }
}
