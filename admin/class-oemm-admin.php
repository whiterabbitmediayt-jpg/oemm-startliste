<?php
defined( 'ABSPATH' ) || exit;

/**
 * Admin-Bereich: Startliste, Einstellungen, AJAX-Handler
 */
class OEMM_Admin {

    public static function init() {
        add_action( 'admin_menu',        array( __CLASS__, 'add_menu' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

        // AJAX Handler
        add_action( 'wp_ajax_oemm_set_startnumber',   array( __CLASS__, 'ajax_set_startnumber' ) );
        add_action( 'wp_ajax_oemm_fill_startnumbers', array( __CLASS__, 'ajax_fill_startnumbers' ) );
        add_action( 'wp_ajax_oemm_generate_tokens',   array( __CLASS__, 'ajax_generate_tokens' ) );
        add_action( 'wp_ajax_oemm_import_excel',      array( __CLASS__, 'ajax_import_excel' ) );
        add_action( 'wp_ajax_oemm_save_settings',     array( __CLASS__, 'ajax_save_settings' ) );
        add_action( 'wp_ajax_oemm_export_csv',         array( __CLASS__, 'ajax_export_csv' ) );
        add_action( 'wp_ajax_oemm_export_qr_zip',        array( __CLASS__, 'ajax_export_qr_zip' ) );
        add_action( 'wp_ajax_oemm_clear_update_cache',   array( __CLASS__, 'ajax_clear_update_cache' ) );
    }

    // -------------------------------------------------------------------------
    // Menu
    // -------------------------------------------------------------------------

    public static function add_menu() {
        add_menu_page(
            'ÖMM Startliste',
            'ÖMM Startliste',
            'manage_woocommerce',
            'oemm-startliste',
            array( __CLASS__, 'page_startliste' ),
            'dashicons-groups',
            58
        );
        add_submenu_page(
            'oemm-startliste',
            'Startliste',
            'Startliste',
            'manage_woocommerce',
            'oemm-startliste',
            array( __CLASS__, 'page_startliste' )
        );
        add_submenu_page(
            'oemm-startliste',
            'Einstellungen',
            'Einstellungen',
            'manage_woocommerce',
            'oemm-settings',
            array( __CLASS__, 'page_settings' )
        );
        add_submenu_page(
            'oemm-startliste',
            'Import',
            'Import (Einmalig)',
            'manage_options',
            'oemm-import',
            array( __CLASS__, 'page_import' )
        );
        add_submenu_page(
            'oemm-startliste',
            'Export',
            'Export',
            'manage_woocommerce',
            'oemm-export',
            array( __CLASS__, 'page_export' )
        );
        add_submenu_page(
            'oemm-startliste',
            'Statistik',
            'App vs. Papier',
            'manage_woocommerce',
            'oemm-stats',
            array( __CLASS__, 'page_stats' )
        );
    }

    // -------------------------------------------------------------------------
    // Assets
    // -------------------------------------------------------------------------

    public static function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'oemm' ) === false ) return;

        wp_enqueue_style(
            'oemm-admin',
            OEMM_PLUGIN_URL . 'admin/admin.css',
            array(),
            OEMM_VERSION
        );
        wp_enqueue_script(
            'oemm-admin',
            OEMM_PLUGIN_URL . 'admin/admin.js',
            array( 'jquery' ),
            OEMM_VERSION,
            true
        );
        wp_localize_script( 'oemm-admin', 'oemm_ajax', array(
            'url'         => admin_url( 'admin-ajax.php' ),
            'nonce'       => wp_create_nonce( 'oemm_admin' ),
            'plugins_url' => admin_url( 'plugins.php' ),
            'app_url'     => OEMM_Settings::get_app_url(),
        ) );
    }

    // -------------------------------------------------------------------------
    // Seiten
    // -------------------------------------------------------------------------

    public static function page_startliste() {
        $participants = OEMM_Participant::get_all();
        $fields       = OEMM_Settings::get_fields();
        $year         = OEMM_Settings::get_event_year();
        $is_active    = OEMM_Settings::is_active();
        include OEMM_PLUGIN_DIR . 'admin/views/startliste.php';
    }

    public static function page_settings() {
        include OEMM_PLUGIN_DIR . 'admin/views/settings.php';
    }

    public static function page_import() {
        include OEMM_PLUGIN_DIR . 'admin/views/import.php';
    }

    public static function page_export() {
        include OEMM_PLUGIN_DIR . 'admin/views/export.php';
    }

    public static function page_stats() {
        global $wpdb;
        $table = $wpdb->prefix . 'oemm_participants';
        $year  = OEMM_Settings::get_event_year();

        $stats = $wpdb->get_row( $wpdb->prepare(
            "SELECT
                COUNT(*) as total,
                SUM(scan_count_app > 0) as app_users,
                SUM(scan_count_paper > 0) as paper_users,
                SUM(scan_count_app) as total_app_scans,
                SUM(scan_count_paper) as total_paper_scans
             FROM {$table} WHERE event_year = %d",
            $year
        ) );

        include OEMM_PLUGIN_DIR . 'admin/views/stats.php';
    }

    // -------------------------------------------------------------------------
    // AJAX Handler
    // -------------------------------------------------------------------------

    public static function ajax_set_startnumber() {
        check_ajax_referer( 'oemm_admin', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( 'Unauthorized', 403 );

        $customer_id = intval( $_POST['customer_id'] ?? 0 );
        $number      = $_POST['startnumber'] === '' ? null : intval( $_POST['startnumber'] );

        if ( ! $customer_id ) {
            wp_send_json_error( 'Ungültige Customer ID' );
        }

        $ok = OEMM_Participant::set_startnumber( $customer_id, $number );
        $ok ? wp_send_json_success() : wp_send_json_error( 'Fehler beim Speichern' );
    }

    public static function ajax_fill_startnumbers() {
        check_ajax_referer( 'oemm_admin', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( 'Unauthorized', 403 );

        $count = OEMM_Participant::fill_startnumbers();
        wp_send_json_success( array( 'count' => $count, 'message' => "{$count} Startnummern vergeben." ) );
    }

    public static function ajax_generate_tokens() {
        check_ajax_referer( 'oemm_admin', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( 'Unauthorized', 403 );

        $participants = OEMM_Participant::get_all();
        $count = 0;
        foreach ( $participants as $p ) {
            $tokens = OEMM_Token::get_or_create( (int) $p['customer_id'] );
            if ( $tokens ) $count++;
        }
        wp_send_json_success( array( 'count' => $count, 'message' => "{$count} Token-Paare generiert/geprueft." ) );
    }

    public static function ajax_import_excel() {
        check_ajax_referer( 'oemm_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized', 403 );

        // Erwartet JSON-Array: [{ "customer_id": 123, "startnumber": 456 }, ...]
        $raw  = stripslashes( $_POST['data'] ?? '' );
        $data = json_decode( $raw, true );

        if ( ! is_array( $data ) ) {
            wp_send_json_error( 'Ungültige Daten' );
        }

        $count = 0;
        foreach ( $data as $row ) {
            $cid = intval( $row['customer_id'] ?? 0 );
            $sn  = intval( $row['startnumber'] ?? 0 );
            if ( $cid && $sn ) {
                OEMM_Participant::ensure_row( $cid );
                OEMM_Participant::set_startnumber( $cid, $sn );
                OEMM_Token::get_or_create( $cid );
                $count++;
            }
        }
        wp_send_json_success( array( 'count' => $count, 'message' => "{$count} Eintraege importiert." ) );
    }

    /**
     * CSV Export
     * Gibt CSV direkt aus (kein AJAX-JSON, direkter Download)
     */
    public static function ajax_export_csv() {
        check_ajax_referer( 'oemm_admin', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( 'Unauthorized', 403 );

        $fields_raw  = isset( $_POST['fields'] ) ? (array) $_POST['fields'] : array();
        $export_name = sanitize_text_field( $_POST['export_name'] ?? 'startliste' );

        // Nur erlaubte Felder
        $all_field_keys = array_keys( OEMM_Settings::all_fields() );
        $fields = array_intersect( $fields_raw, $all_field_keys );
        if ( empty( $fields ) ) {
            $fields = array_keys( OEMM_Settings::get_fields() );
        }

        $participants = OEMM_Participant::get_all();
        $all_labels   = OEMM_Settings::all_fields();

        // Tokens sicherstellen
        foreach ( $participants as &$p ) {
            if ( empty( $p['token_app'] ) || empty( $p['token_paper'] ) ) {
                $tokens = OEMM_Token::get_or_create( (int) $p['customer_id'] );
                $p['token_app']   = $tokens['app'];
                $p['token_paper'] = $tokens['paper'];
            }
        }
        unset( $p );

        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $export_name . '-' . date('Y-m-d') . '.csv"' );
        header( 'Pragma: no-cache' );
        echo "\xEF\xBB\xBF"; // UTF-8 BOM für Excel

        $out = fopen( 'php://output', 'w' );

        // Header-Zeile
        $header_row = array_map( fn($f) => $all_labels[$f] ?? $f, $fields );
        fputcsv( $out, $header_row, ';' );

        // Datenzeilen
        foreach ( $participants as $p ) {
            $row = array();
            foreach ( $fields as $f ) {
                $val = $p[ $f ] ?? '';
                if ( is_null( $val ) ) $val = '';
                $row[] = $val;
            }
            fputcsv( $out, $row, ';' );
        }

        fclose( $out );
        exit;
    }

    /**
     * QR-Code ZIP Export für Druckerei
     * Generiert alle QR-Code Bild-URLs als Liste (Download der Bilder selbst
     * muss serverseitig via cURL erfolgen - wir liefern eine CSV mit URLs + ZIP-Logik)
     */
    public static function ajax_export_qr_zip() {
        check_ajax_referer( 'oemm_admin', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( 'Unauthorized', 403 );

        $channel = sanitize_text_field( $_POST['channel'] ?? 'paper' ); // 'app' oder 'paper'

        $participants = OEMM_Participant::get_all();
        $app_base     = OEMM_Settings::get_app_url();

        // CSV mit allen QR-Daten für Druckerei (Serienbrief-kompatibel)
        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="qr-druckerei-' . $channel . '-' . date('Y-m-d') . '.csv"' );
        echo "\xEF\xBB\xBF";

        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, array(
            'Startnummer', 'Vorname', 'Nachname', 'Adresse', 'PLZ', 'Ort', 'Land',
            'Token', 'QR_Ziel_URL', 'QR_Bild_URL'
        ), ';' );

        foreach ( $participants as $p ) {
            if ( ! $p['startnumber'] ) continue;

            $tokens = OEMM_Token::get_or_create( (int) $p['customer_id'] );
            $token  = $channel === 'app' ? $tokens['app'] : $tokens['paper'];
            if ( ! $token ) continue;

            $target_url = $app_base . $token;
            $qr_img_url = OEMM_QR::get_url( $token, 400 );

            fputcsv( $out, array(
                $p['startnumber'],
                $p['billing_first_name'],
                $p['billing_last_name'],
                $p['billing_address_1'],
                $p['billing_postcode'],
                $p['billing_city'],
                $p['billing_country'],
                $token,
                $target_url,
                $qr_img_url,
            ), ';' );
        }

        fclose( $out );
        exit;
    }

    public static function ajax_clear_update_cache() {
        check_ajax_referer( 'oemm_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized', 403 );

        delete_transient( 'oemm_github_release' );
        delete_site_transient( 'update_plugins' );
        wp_update_plugins();

        wp_send_json_success( 'Update-Cache geleert.' );
    }

    public static function ajax_save_settings() {
        check_ajax_referer( 'oemm_admin', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized', 403 );

        OEMM_Settings::save( $_POST );
        wp_send_json_success( 'Einstellungen gespeichert.' );
    }
}
