<?php
defined( 'ABSPATH' ) || exit;

/**
 * GitHub Auto-Updater
 * Prüft auf neue Versionen im privaten GitHub Repo
 * und ermöglicht 1-Klick-Update direkt aus dem WP-Admin
 *
 * Repo: whiterabbitmediayt-jpg/oemm-startliste
 */
class OEMM_Updater {

    private static string $github_user  = 'whiterabbitmediayt-jpg';
    private static string $github_repo  = 'oemm-startliste';
    private static string $plugin_slug  = 'oemm-startliste/oemm-startliste.php';
    private static string $plugin_file  = '';

    public static function init() {
        self::$plugin_file = OEMM_PLUGIN_FILE;

        add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'check_update' ) );
        add_filter( 'plugins_api',                           array( __CLASS__, 'plugin_info' ), 20, 3 );
        add_filter( 'upgrader_post_install',                 array( __CLASS__, 'after_install' ), 10, 3 );
    }

    /**
     * Holt die neueste Release-Info von GitHub
     */
    private static function get_release(): ?object {
        $token    = get_option( 'oemm_github_token', '' );
        $cache_key = 'oemm_github_release';
        $cached   = get_transient( $cache_key );
        if ( $cached ) return $cached;

        $args = array( 'timeout' => 10 );
        if ( $token ) {
            $args['headers'] = array( 'Authorization' => 'token ' . $token );
        }

        $url = 'https://api.github.com/repos/' . self::$github_user . '/' . self::$github_repo . '/releases/latest';
        $response = wp_remote_get( $url, $args );

        if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
            return null;
        }

        $release = json_decode( wp_remote_retrieve_body( $response ) );
        if ( ! $release || ! isset( $release->tag_name ) ) return null;

        set_transient( $cache_key, $release, HOUR_IN_SECONDS );
        return $release;
    }

    /**
     * WordPress Update-Check Hook
     * Fügt das Plugin in die Update-Liste ein wenn eine neuere Version auf GitHub ist
     */
    public static function check_update( $transient ) {
        if ( empty( $transient->checked ) ) return $transient;

        $release = self::get_release();
        if ( ! $release ) return $transient;

        $latest_version = ltrim( $release->tag_name, 'v' );
        $current_version = OEMM_VERSION;

        if ( version_compare( $latest_version, $current_version, '>' ) ) {
            $zip_url = self::get_zip_url( $release );
            if ( $zip_url ) {
                $transient->response[ self::$plugin_slug ] = (object) array(
                    'slug'        => 'oemm-startliste',
                    'plugin'      => self::$plugin_slug,
                    'new_version' => $latest_version,
                    'url'         => "https://github.com/" . self::$github_user . "/" . self::$github_repo,
                    'package'     => $zip_url,
                    'icons'       => array(),
                    'banners'     => array(),
                    'tested'      => get_bloginfo( 'version' ),
                    'requires_php'=> '8.0',
                );
            }
        }

        return $transient;
    }

    /**
     * Plugin-Info Popup im WP-Admin (zeigt Changelog etc.)
     */
    public static function plugin_info( $result, $action, $args ) {
        if ( $action !== 'plugin_information' ) return $result;
        if ( ! isset( $args->slug ) || $args->slug !== 'oemm-startliste' ) return $result;

        $release = self::get_release();
        if ( ! $release ) return $result;

        return (object) array(
            'name'          => 'OEMM Startliste',
            'slug'          => 'oemm-startliste',
            'version'       => ltrim( $release->tag_name, 'v' ),
            'author'        => 'Manuel Ribis GmbH',
            'author_profile'=> 'https://mopedmarathon.at',
            'last_updated'  => $release->published_at ?? '',
            'homepage'      => 'https://github.com/' . self::$github_user . '/' . self::$github_repo,
            'short_description' => 'Startlisten-Verwaltung für den Ötztaler Moped Marathon.',
            'sections'      => array(
                'changelog' => nl2br( esc_html( $release->body ?? '' ) ),
            ),
            'download_link' => self::get_zip_url( $release ),
            'requires_php'  => '8.0',
            'tested'        => get_bloginfo( 'version' ),
        );
    }

    /**
     * Nach dem Update: Plugin-Ordner korrekt benennen
     * GitHub entpackt in einen Ordner mit Commit-Hash im Namen
     */
    public static function after_install( $response, $hook_extra, $result ) {
        if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== self::$plugin_slug ) {
            return $response;
        }

        global $wp_filesystem;
        $plugin_folder = WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . 'oemm-startliste';
        $wp_filesystem->move( $result['destination'], $plugin_folder );
        $result['destination'] = $plugin_folder;

        // Plugin nach Update wieder aktivieren
        activate_plugin( self::$plugin_slug );

        return $result;
    }

    /**
     * ZIP-Download-URL aus Release ermitteln
     * Bevorzugt einen angehängten Asset, fallback auf zipball
     */
    private static function get_zip_url( object $release ): ?string {
        // Zuerst in Assets suchen (wir hängen eine saubere ZIP an)
        if ( ! empty( $release->assets ) ) {
            foreach ( $release->assets as $asset ) {
                if ( str_ends_with( $asset->name, '.zip' ) ) {
                    $url   = $asset->browser_download_url;
                    $token = get_option( 'oemm_github_token', '' );
                    // Bei privatem Repo: Token in URL einbauen
                    if ( $token ) {
                        $url = add_query_arg( 'access_token', $token, $url );
                    }
                    return $url;
                }
            }
        }

        // Fallback: GitHub zipball (enthält Repo-Inhalt direkt)
        $token = get_option( 'oemm_github_token', '' );
        $url   = $release->zipball_url ?? null;
        if ( $url && $token ) {
            $url = add_query_arg( 'access_token', $token, $url );
        }
        return $url;
    }
}
