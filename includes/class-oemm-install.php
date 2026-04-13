<?php
defined( 'ABSPATH' ) || exit;

/**
 * Installation, DB-Tabellen anlegen, Updates
 */
class OEMM_Install {

    /**
     * Wird bei Plugin-Aktivierung aufgerufen
     */
    public static function activate() {
        self::create_tables();
        self::set_default_options();
        flush_rewrite_rules();
    }

    /**
     * Wird bei Deaktivierung aufgerufen
     * Daten bleiben standardmäßig erhalten (für Neu-Installation)
     */
    public static function deactivate() {
        flush_rewrite_rules();
        // Tabelle und Einstellungen bleiben erhalten — bewusste Entscheidung
        // Nur löschen wenn "Daten löschen bei Deinstallation" in den Einstellungen aktiv ist
    }

    /**
     * Wird bei Plugin-Löschung aufgerufen (uninstall.php)
     * Löscht Daten nur wenn der Schalter explizit gesetzt ist
     */
    public static function uninstall() {
        $delete = get_option( 'oemm_delete_data_on_uninstall', '0' );
        if ( $delete === '1' ) {
            self::drop_tables();
            self::delete_options();
        }
        // Sonst: Daten bleiben — bei Neu-Installation werden sie wiederverwendet
    }

    /**
     * Custom DB-Tabelle: oemm_participants
     */
    public static function create_tables() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $table   = $wpdb->prefix . 'oemm_participants';

        $sql = "CREATE TABLE IF NOT EXISTS {$table} (
            id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_id     BIGINT UNSIGNED NOT NULL,
            event_year      SMALLINT UNSIGNED NOT NULL DEFAULT 2026,
            startnumber     VARCHAR(20) DEFAULT NULL COMMENT 'Startnummer (z.B. 1, 01, 007a — als Text gespeichert)',
            token_app       VARCHAR(64)  DEFAULT NULL COMMENT 'Token für App-QR-Code',
            token_paper     VARCHAR(64)  DEFAULT NULL COMMENT 'Token für Zettel-QR-Code',
            scan_count_app  INT UNSIGNED NOT NULL DEFAULT 0,
            scan_count_paper INT UNSIGNED NOT NULL DEFAULT 0,
            notes           TEXT DEFAULT NULL,
            created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uq_customer_year (customer_id, event_year),
            KEY idx_startnumber (startnumber),
            KEY idx_token_app (token_app),
            KEY idx_token_paper (token_paper)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // Migration: SMALLINT -> VARCHAR(20) falls alte Spalte noch existiert
        $col_type = $wpdb->get_var( "SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = '{$table}'
            AND COLUMN_NAME = 'startnumber'" );
        if ( $col_type && strtolower( $col_type ) !== 'varchar' ) {
            $wpdb->query( "ALTER TABLE {$table} MODIFY COLUMN startnumber VARCHAR(20) DEFAULT NULL" );
        }

        update_option( 'oemm_db_version', OEMM_DB_VERSION );
    }

    private static function drop_tables() {
        global $wpdb;
        $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}oemm_participants" );
        delete_option( 'oemm_db_version' );
    }

    private static function delete_options() {
        $keys = array(
            'oemm_event_year', 'oemm_event_active', 'oemm_product_ids',
            'oemm_startnumber_start', 'oemm_token_salt', 'oemm_app_url',
            'oemm_fields', 'oemm_api_key', 'oemm_delete_data_on_uninstall',
        );
        foreach ( $keys as $key ) {
            delete_option( $key );
        }
    }

    /**
     * Standard-Einstellungen beim ersten Aktivieren
     */
    private static function set_default_options() {
        $defaults = array(
            'oemm_event_year'                => 2026,
            'oemm_event_active'              => '0',
            'oemm_product_ids'               => '7537,8457,8566',
            'oemm_startnumber_start'         => 1,
            'oemm_token_salt'                => bin2hex( random_bytes( 32 ) ),
            'oemm_app_url'                   => 'https://moped-tracker.web.app/t/',
            'oemm_delete_data_on_uninstall'  => '0', // Standard: Daten behalten!
        );

        foreach ( $defaults as $key => $value ) {
            if ( false === get_option( $key ) ) {
                add_option( $key, $value );
            }
        }
        // Token-Salt NIEMALS überschreiben wenn er schon existiert
        // (würde alle bestehenden Tokens ungültig machen)
    }
}
