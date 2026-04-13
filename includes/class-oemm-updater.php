<?php
defined( 'ABSPATH' ) || exit;

/**
 * GitHub Auto-Updater via Plugin Update Checker Library
 * Repo: https://github.com/whiterabbitmediayt-jpg/oemm-startliste (public)
 */
class OEMM_Updater {

    public static function init() {
        require_once OEMM_PLUGIN_DIR . 'lib/plugin-update-checker/load-v5p5.php';

        $checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
            'https://github.com/whiterabbitmediayt-jpg/oemm-startliste/',
            OEMM_PLUGIN_FILE,
            'oemm-startliste'
        );

        // Token aus Einstellungen verwenden (verhindert Rate Limit bei GitHub)
        $token = get_option( 'oemm_github_token', '' );
        if ( $token ) {
            $checker->setAuthentication( $token );
        }

        $checker->getVcsApi()->enableReleaseAssets();
        $checker->setBranch( 'main' );
    }

    public static function clear_cache(): void {
        delete_option( 'external_updates-oemm-startliste' );
        delete_site_transient( 'update_plugins' );
        wp_update_plugins();
    }
}
