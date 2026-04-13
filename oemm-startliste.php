<?php
/**
 * Plugin Name:       OEMM Startliste
 * Plugin URI:        https://mopedmarathon.at
 * Description:       Verwaltung der Startliste fuer den Oetztaler Moped Marathon. Startnummern, QR-Codes, App-Anbindung.
 * Version:           1.2.0
 * Author:            Manuel Ribis GmbH
 * Author URI:        https://mopedmarathon.at
 * License:           GPL-2.0+
 * Text Domain:       oemm-startliste
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * WC requires at least: 8.0
 */

defined( 'ABSPATH' ) || exit;

// Plugin-Konstanten
define( 'OEMM_VERSION',     '1.2.0' );
define( 'OEMM_PLUGIN_FILE', __FILE__ );
define( 'OEMM_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'OEMM_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'OEMM_DB_VERSION',  '1.0' );

// Autoload der Klassen
require_once OEMM_PLUGIN_DIR . 'includes/class-oemm-install.php';
require_once OEMM_PLUGIN_DIR . 'includes/class-oemm-settings.php';
require_once OEMM_PLUGIN_DIR . 'includes/class-oemm-participant.php';
require_once OEMM_PLUGIN_DIR . 'includes/class-oemm-token.php';
require_once OEMM_PLUGIN_DIR . 'includes/class-oemm-qr.php';
require_once OEMM_PLUGIN_DIR . 'includes/class-oemm-hooks.php';
require_once OEMM_PLUGIN_DIR . 'includes/class-oemm-updater.php';
require_once OEMM_PLUGIN_DIR . 'admin/class-oemm-admin.php';
require_once OEMM_PLUGIN_DIR . 'frontend/class-oemm-frontend.php';
require_once OEMM_PLUGIN_DIR . 'api/class-oemm-api.php';

// Aktivierung / Deaktivierung
register_activation_hook( __FILE__,  array( 'OEMM_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'OEMM_Install', 'deactivate' ) );

/**
 * Plugin booten
 */
function oemm_init() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function() {
            echo '<div class="error"><p><strong>OEMM Startliste:</strong> WooCommerce muss aktiv sein.</p></div>';
        });
        return;
    }

    OEMM_Settings::init();
    OEMM_Updater::init();
    OEMM_Hooks::init();
    OEMM_Admin::init();
    OEMM_Frontend::init();
    OEMM_API::init();
}
add_action( 'plugins_loaded', 'oemm_init' );
