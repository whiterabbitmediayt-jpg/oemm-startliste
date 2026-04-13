<?php
defined( 'ABSPATH' ) || exit;

/**
 * GitHub Auto-Updater via Plugin Update Checker Library (YahnisElsts/plugin-update-checker)
 */
class OEMM_Updater {

    private static string $github_user = 'whiterabbitmediayt-jpg';
    private static string $github_repo = 'oemm-startliste';

    public static function init() {
        require_once OEMM_PLUGIN_DIR . 'lib/plugin-update-checker/load-v5p5.php';

        $token = get_option( 'oemm_github_token', '' );

        // GitHubApi direkt mit Token instantiieren (privates Repo!)
        $api = new \YahnisElsts\PluginUpdateChecker\v5p5\Vcs\GitHubApi(
            'https://github.com/' . self::$github_user . '/' . self::$github_repo,
            $token ?: null
        );
        $api->enableReleaseAssets();

        $update_checker = new \YahnisElsts\PluginUpdateChecker\v5p5\Vcs\PluginUpdateChecker(
            $api,
            OEMM_PLUGIN_FILE,
            'oemm-startliste'
        );

        // Branch explizit auf main setzen
        $update_checker->setBranch( 'main' );
    }

    /**
     * Update-Cache komplett leeren
     */
    public static function clear_cache(): void {
        delete_option( 'external_updates-oemm-startliste' );
        delete_site_transient( 'update_plugins' );
        wp_update_plugins();
    }
}
