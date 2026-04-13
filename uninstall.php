<?php
/**
 * Wird von WordPress beim Plugin-Löschen aufgerufen
 * Löscht Daten NUR wenn der Schalter in den Einstellungen aktiv ist
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

require_once plugin_dir_path( __FILE__ ) . 'includes/class-oemm-install.php';
OEMM_Install::uninstall();
