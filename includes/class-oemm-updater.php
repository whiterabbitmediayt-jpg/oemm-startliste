<?php
defined( 'ABSPATH' ) || exit;

/**
 * GitHub Auto-Updater via Plugin Update Checker Library (YahnisElsts/plugin-update-checker)
 * Zuverlässiger Standard für Custom WordPress Plugins mit GitHub-Hosting
 */
class OEMM_Updater {

    private static string $github_user = 'whiterabbitmediayt-jpg';
    private static string $github_repo = 'oemm-startliste';

    public static function init() {
        // Plugin Update Checker Library laden
        require_once OEMM_PLUGIN_DIR . 'lib/plugin-update-checker/load-v5p5.php';

        $token = get_option( 'oemm_github_token', '' );

        $update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
            'https://github.com/' . self::$github_user . '/' . self::$github_repo . '/',
            OEMM_PLUGIN_FILE,
            'oemm-startliste'
        );

        // Release-Asset als Download-Quelle (unsere ZIP statt zipball)
        $update_checker->getVcsApi()->enableReleaseAssets();

        // Authentifizierung für privates Repo
        if ( $token ) {
            $update_checker->setAuthentication( $token );
        }
    }

    /**
     * Update-Cache komplett leeren
     */
    public static function clear_cache(): void {
        // Plugin Update Checker Cache
        delete_option( 'external_updates-oemm-startliste' );
        // WordPress eigener Cache
        delete_site_transient( 'update_plugins' );
        wp_update_plugins();
    }
}
