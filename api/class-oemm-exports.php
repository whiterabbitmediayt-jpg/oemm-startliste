<?php
defined( 'ABSPATH' ) || exit;

/**
 * OEMM Victor-Exports
 * Namespace: oemm/v1
 *
 * Endpoints:
 *   GET /oemm/v1/victor-export      ?format=csv|xlsx  — Startnummern + Wunschnummern
 *   GET /oemm/v1/tshirt-export      ?format=csv|xlsx  — T-Shirt Produktion
 *   GET /oemm/v1/lettershop-export  ?format=csv|xlsx  — Lettershop & More (Versandzentrum)
 *
 * Alle Endpoints: Admin ODER manage_woocommerce
 * AJAX Actions: oemm_export_victor, oemm_export_tshirt, oemm_export_lettershop
 */
class OEMM_Exports {

    public static function init() {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
        add_action( 'wp_ajax_oemm_export_victor',     array( __CLASS__, 'ajax_victor' ) );
        add_action( 'wp_ajax_oemm_export_tshirt',     array( __CLASS__, 'ajax_tshirt' ) );
        add_action( 'wp_ajax_oemm_export_lettershop', array( __CLASS__, 'ajax_lettershop' ) );
    }

    public static function register_routes() {
        $ns   = 'oemm/v1';
        $perm = function() { return current_user_can( 'manage_woocommerce' ); };
        $args = array( 'format' => array( 'default' => 'csv', 'sanitize_callback' => 'sanitize_text_field' ) );

        register_rest_route( $ns, '/victor-export', array(
            'methods' => 'GET', 'permission_callback' => $perm, 'args' => $args,
            'callback' => function( $r ) { self::build_victor( $r->get_param('format') ?: 'csv' ); },
        ));
        register_rest_route( $ns, '/tshirt-export', array(
            'methods' => 'GET', 'permission_callback' => $perm, 'args' => $args,
            'callback' => function( $r ) { self::build_tshirt( $r->get_param('format') ?: 'csv' ); },
        ));
        register_rest_route( $ns, '/lettershop-export', array(
            'methods' => 'GET', 'permission_callback' => $perm, 'args' => $args,
            'callback' => function( $r ) { self::build_lettershop( $r->get_param('format') ?: 'csv' ); },
        ));
    }

    // -------------------------------------------------------------------------
    // AJAX Handlers
    // -------------------------------------------------------------------------

    public static function ajax_victor() {
        check_ajax_referer( 'oemm_admin', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( 'Unauthorized', 403 );
        self::build_victor( sanitize_text_field( $_POST['format'] ?? 'csv' ) );
    }

    public static function ajax_tshirt() {
        check_ajax_referer( 'oemm_admin', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( 'Unauthorized', 403 );
        self::build_tshirt( sanitize_text_field( $_POST['format'] ?? 'csv' ) );
    }

    public static function ajax_lettershop() {
        check_ajax_referer( 'oemm_admin', 'nonce' );
        if ( ! current_user_can( 'manage_woocommerce' ) ) wp_die( 'Unauthorized', 403 );
        self::build_lettershop( sanitize_text_field( $_POST['format'] ?? 'csv' ) );
    }

    // -------------------------------------------------------------------------
    // Victor-Export: Startnummern + Wunschnummern
    // -------------------------------------------------------------------------

    public static function build_victor( string $format ): void {
        $participants = OEMM_Participant::get_all();

        global $wpdb;
        $wunsch_ids = $wpdb->get_col(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type = 'product' AND post_status = 'publish'
             AND post_title LIKE '%Wunschnummer%'"
        );

        $wunsch_map = array();
        if ( ! empty( $wunsch_ids ) ) {
            $ids_sql = implode( ',', array_map( 'intval', $wunsch_ids ) );
            $rows    = $wpdb->get_results(
                "SELECT oi.order_id, p.post_title AS product_name
                 FROM {$wpdb->prefix}woocommerce_order_items oi
                 JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim
                      ON oi.order_item_id = oim.order_item_id AND oim.meta_key = '_product_id'
                 JOIN {$wpdb->posts} p ON p.ID = oim.meta_value
                 WHERE oi.order_item_type = 'line_item' AND oim.meta_value IN ({$ids_sql})"
            );
            foreach ( $rows as $row ) {
                $order = wc_get_order( intval( $row->order_id ) );
                if ( ! $order ) continue;
                $d = $order->get_date_created();
                if ( ! $d || $d->getTimestamp() < strtotime( '2025-09-01' ) ) continue;
                $cid = $order->get_customer_id() ?: 'guest_' . $row->order_id;
                $wunsch_map[ $cid ] = array(
                    'wunschnummer' => trim( str_replace( 'OPTIONAL: Wunschnummer', '', $row->product_name ) ),
                    'order_id'     => $row->order_id,
                    'order_date'   => $d->date( 'Y-m-d' ),
                    'order_note'   => $order->get_customer_note(),
                );
            }
        }

        $header = array( 'Vorname', 'Nachname', 'Startnummer', 'Wunschnummer (Ja/Nein)', 'Wunschnummer', 'Bestelldatum', 'Bestellnummer', 'Kundennummer', 'Bestellnotiz' );
        $data   = array();

        foreach ( $participants as $p ) {
            $cid    = (int) $p['customer_id'];
            $wunsch = $wunsch_map[ $cid ] ?? null;
            $oid    = $p['order_id'] ?? '';
            $odate  = '';
            $onote  = '';
            if ( $wunsch ) {
                $oid = $wunsch['order_id']; $odate = $wunsch['order_date']; $onote = $wunsch['order_note'];
            } elseif ( $oid ) {
                $obj = wc_get_order( (int) $oid );
                if ( $obj ) { $d = $obj->get_date_created(); $odate = $d ? $d->date('Y-m-d') : ''; $onote = $obj->get_customer_note(); }
            }
            $data[] = array( $p['billing_first_name']??'', $p['billing_last_name']??'', $p['startnumber']??'', $wunsch?'Ja':'Nein', $wunsch?$wunsch['wunschnummer']:'', $odate, $oid, $cid, $onote );
        }

        $known = array_map( fn($p) => (int) $p['customer_id'], $participants );
        foreach ( $wunsch_map as $cid => $w ) {
            if ( strpos( (string)$cid, 'guest_' ) === 0 || in_array( (int)$cid, $known ) ) continue;
            $fn = $ln = '';
            $obj = wc_get_order( $w['order_id'] );
            if ( $obj ) { $fn = $obj->get_billing_first_name(); $ln = $obj->get_billing_last_name(); }
            $data[] = array( $fn, $ln, '', 'Ja', $w['wunschnummer'], $w['order_date'], $w['order_id'], $cid, $w['order_note'] );
        }

        usort( $data, fn($a,$b) => $a[3]!==$b[3] ? ($a[3]==='Ja'?-1:1) : strcmp($a[1],$b[1]) );
        self::output( 'victor-export-' . date('Y-m-d'), $header, $data, $format );
    }

    // -------------------------------------------------------------------------
    // T-Shirt Export
    // -------------------------------------------------------------------------

    public static function build_tshirt( string $format ): void {
        $participants = OEMM_Participant::get_all();
        $header       = array( 'Vorname', 'Nachname', 'Geschlecht', 'Startnummer', 'Bestelldatum', 'Bestellnummer', 'Kundennummer', 'T-Shirt Größe' );
        $data         = array();
        $size_counts  = array();

        foreach ( $participants as $p ) {
            $cid      = (int) $p['customer_id'];
            $oid      = (int) ( $p['order_id'] ?? 0 );
            $odate    = '';
            $size     = '';
            $gender   = '';

            if ( $oid ) {
                $order = wc_get_order( $oid );
                if ( $order ) {
                    $d     = $order->get_date_created();
                    $odate = $d ? $d->date( 'Y-m-d' ) : '';
                    foreach ( $order->get_items() as $item ) {
                        $s = $item->get_meta( 'pa_size' );
                        if ( $s ) { $size = strtoupper( $s ); break; }
                    }
                    $t = get_post_meta( $oid, '_billing_title', true );
                    if ( $t == '1' ) $gender = 'Herr';
                    elseif ( $t == '2' ) $gender = 'Frau';
                }
            }
            if ( ! $size ) $size = strtoupper( $p['shirt_size'] ?? '' );
            $size_counts[ $size ?: 'UNBEKANNT' ] = ( $size_counts[ $size ?: 'UNBEKANNT' ] ?? 0 ) + 1;
            $data[] = array( $p['billing_first_name']??'', $p['billing_last_name']??'', $gender, $p['startnumber']??'', $odate, $oid?:'', $cid, $size );
        }

        $so = array( 'XS'=>0,'S'=>1,'M'=>2,'L'=>3,'XL'=>4,'XXL'=>5,'2XL'=>5,'XXXL'=>6,'3XL'=>6,'ELEFANTENGROESSE'=>7,''=>98,'UNBEKANNT'=>99 );
        usort( $data, fn($a,$b) => ( ($so[$a[7]]??50) !== ($so[$b[7]]??50) ) ? ($so[$a[7]]??50)-($so[$b[7]]??50) : strcmp($a[1],$b[1]) );

        $total = count( $data );
        arsort( $size_counts );
        $data[] = array( '', '', '', '', '', '', '', '' );
        $data[] = array( '', '', '', '', '', '', 'ZUSAMMENFASSUNG', '' );
        $data[] = array( '', '', '', '', '', '', 'Größe', 'Anzahl | Prozent' );
        foreach ( $size_counts as $s => $c ) {
            $data[] = array( '', '', '', '', '', '', $s ?: 'UNBEKANNT', $c . ' (' . round($c/$total*100,1) . '%)' );
        }
        $data[] = array( '', '', '', '', '', '', 'GESAMT', $total );

        self::output( 'tshirt-export-' . date('Y-m-d'), $header, $data, $format );
    }

    // -------------------------------------------------------------------------
    // Lettershop-Export
    // -------------------------------------------------------------------------

    public static function build_lettershop( string $format ): void {
        $participants = OEMM_Participant::get_all();
        $header       = array( 'GANGNAME', 'VORNAME', 'NACHNAME', 'STRASSE', 'LÄNDERKZL', 'PLZ', 'STADT', 'LAND', 'KUNDENNUMMER', 'SHIRT_CODE' );
        $data         = array();

        foreach ( $participants as $p ) {
            $cid     = (int) $p['customer_id'];
            $oid     = (int) ( $p['order_id'] ?? 0 );
            $company = $p['billing_company']    ?? '';
            $fn      = $p['billing_first_name'] ?? '';
            $ln      = $p['billing_last_name']  ?? '';
            $addr    = $p['billing_address_1']  ?? '';
            $zip     = $p['billing_postcode']   ?? '';
            $city    = $p['billing_city']       ?? '';
            $country = $p['billing_country']    ?? '';
            $size    = '';

            if ( $oid ) {
                $order = wc_get_order( $oid );
                if ( $order ) {
                    if ( ! $addr )    $addr    = $order->get_billing_address_1();
                    if ( ! $zip )     $zip     = $order->get_billing_postcode();
                    if ( ! $city )    $city    = $order->get_billing_city();
                    if ( ! $country ) $country = $order->get_billing_country();
                    if ( ! $company ) $company = $order->get_billing_company();
                    if ( ! $fn )      $fn      = $order->get_billing_first_name();
                    if ( ! $ln )      $ln      = $order->get_billing_last_name();
                    foreach ( $order->get_items() as $item ) {
                        $s = $item->get_meta( 'pa_size' );
                        if ( $s ) { $size = strtoupper( $s ); break; }
                    }
                }
            }
            if ( ! $size ) $size = strtoupper( $p['shirt_size'] ?? '' );

            $data[] = array( $company, $fn, $ln, $addr, strtoupper($country), $zip, $city, self::country_name($country), $cid, $cid . $size );
        }

        usort( $data, fn($a,$b) => strcmp($a[2],$b[2]) );
        self::output( 'lettershop-export-' . date('Y-m-d'), $header, $data, $format );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private static function country_name( string $code ): string {
        $map = array(
            'DE'=>'Deutschland','AT'=>'Österreich','CH'=>'Schweiz','IT'=>'Italien',
            'FR'=>'Frankreich','NL'=>'Niederlande','BE'=>'Belgien','LU'=>'Luxemburg',
            'LI'=>'Liechtenstein','GB'=>'Großbritannien','US'=>'USA','ES'=>'Spanien',
            'PT'=>'Portugal','PL'=>'Polen','CZ'=>'Tschechien','SK'=>'Slowakei',
            'HU'=>'Ungarn','SI'=>'Slowenien','HR'=>'Kroatien','DK'=>'Dänemark',
            'SE'=>'Schweden','NO'=>'Norwegen','FI'=>'Finnland','RO'=>'Rumänien',
            'BG'=>'Bulgarien','GR'=>'Griechenland',
        );
        return $map[ strtoupper($code) ] ?? $code;
    }

    private static function col_letter( int $idx ): string {
        $l = '';
        while ( $idx >= 0 ) { $l = chr(65+($idx%26)).$l; $idx = intval($idx/26)-1; }
        return $l;
    }

    private static function output( string $filename, array $header, array $data, string $format ): void {
        if ( $format === 'xlsx' ) {
            self::output_xlsx( $filename, $header, $data );
        } else {
            self::output_csv( $filename, $header, $data );
        }
    }

    private static function output_csv( string $filename, array $header, array $rows ): void {
        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '.csv"' );
        header( 'Pragma: no-cache' );
        echo "\xEF\xBB\xBF";
        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, $header, ';' );
        foreach ( $rows as $row ) { fputcsv( $out, $row, ';' ); }
        fclose( $out );
        exit;
    }

    private static function output_xlsx( string $filename, array $header, array $rows ): void {
        $all      = array_merge( array( $header ), $rows );
        $xml_rows = '';
        $ri       = 1;
        foreach ( $all as $row ) {
            $bold     = ( $ri === 1 ) || ( isset($row[5]) && in_array($row[5], array('ZUSAMMENFASSUNG','Größe','GESAMT')) ) || ( isset($row[6]) && in_array($row[6], array('ZUSAMMENFASSUNG','Größe','GESAMT')) );
            $xml_rows .= '<row r="' . $ri . '">';
            $ci = 0;
            foreach ( $row as $cell ) {
                $ref      = self::col_letter($ci) . $ri;
                $val      = htmlspecialchars( (string)$cell, ENT_XML1 );
                $s        = $bold ? ' s="1"' : '';
                $xml_rows .= '<c r="'.$ref.'" t="inlineStr"'.$s.'><is><t>'.$val.'</t></is></c>';
                $ci++;
            }
            $xml_rows .= '</row>';
            $ri++;
        }

        $sheet   = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$xml_rows.'</sheetData></worksheet>';
        $styles  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/></font><font><b/><sz val="11"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0"/></cellXfs></styleSheet>';
        $rels    = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>';
        $wb      = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Export" sheetId="1" r:id="rId1"/></sheets></workbook>';
        $wbrels  = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
        $ct      = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>';

        $tmp = tempnam( sys_get_temp_dir(), 'oemm_export_' );
        $zip = new ZipArchive();
        $zip->open( $tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE );
        $zip->addFromString( '[Content_Types].xml',       $ct );
        $zip->addFromString( '_rels/.rels',                $rels );
        $zip->addFromString( 'xl/workbook.xml',            $wb );
        $zip->addFromString( 'xl/_rels/workbook.xml.rels', $wbrels );
        $zip->addFromString( 'xl/worksheets/sheet1.xml',   $sheet );
        $zip->addFromString( 'xl/styles.xml',              $styles );
        $zip->close();

        header( 'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '.xlsx"' );
        header( 'Content-Length: ' . filesize($tmp) );
        header( 'Pragma: no-cache' );
        readfile( $tmp );
        unlink( $tmp );
        exit;
    }
}
