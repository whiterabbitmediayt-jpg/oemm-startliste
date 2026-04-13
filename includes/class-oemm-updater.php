<?php
defined( 'ABSPATH' ) || exit;

/**
 * GitHub Auto-Updater — robuste Version
 * Repo: whiterabbitmediayt-jpg/oemm-startliste
 */
class OEMM_Updater {

    private static string $github_user = 'whiterabbitmediayt-jpg';
    private static string $github_repo = 'oemm-startliste';
    private static string $plugin_slug = 'oemm-startliste/oemm-startliste.php';

    public static function init() {
        add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'check_update' ) );
        add_filter( 'plugins_api',                           array( __CLASS__, 'plugin_info' ), 20, 3 );
        add_filter( 'upgrader_post_install',                 array( __CLASS__, 'after_install' ), 10, 3 );
    }

    /**
     * GitHub Release holen (mit Cache)
     */
    private static function get_release(): ?object {
        $cache_key = 'oemm_github_release_v2';
        $cached    = get_transient( $cache_key );
        if ( $cached !== false ) {
            return $cached ?: null;
        }

        $token = get_option( 'oemm_github_token', '' );
        $args  = array(
            'timeout'    => 15,
            'user-agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url(),
        );
        if ( $token ) {
            $args['headers'] = array( 'Authorization' => 'token ' . $token );
        }

        $url      = 'https://api.github.com/repos/' . self::$github_user . '/' . self::$github_repo . '/releases/latest';
        $response = wp_remote_get( $url, $args );

        if ( is_wp_error( $response ) ) {
            set_transient( $cache_key, false, 5 * MINUTE_IN_SECONDS );
            return null;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            set_transient( $cache_key, false, 5 * MINUTE_IN_SECONDS );
            return null;
        }

        $release = json_decode( wp_remote_retrieve_body( $response ) );
        if ( ! $release || empty( $release->tag_name ) ) {
            set_transient( $cache_key, false, 5 * MINUTE_IN_SECONDS );
            return null;
        }

        set_transient( $cache_key, $release, HOUR_IN_SECONDS );
        return $release;
    }

    /**
     * Download-URL aus Release ermitteln
     */
    private static function get_download_url( object $release ): string {
        $token = get_option( 'oemm_github_token', '' );

        // Zuerst angehängtes Asset suchen
        foreach ( (array) $release->assets as $asset ) {
            if ( str_ends_with( (string) $asset->name, '.zip' ) ) {
                $url = (string) $asset->browser_download_url;
                // Bei privatem Repo: Token als Query-Parameter
                if ( $token ) {
                    $url = add_query_arg( 'access_token', $token, $url );
                }
                return $url;
            }
        }

        // Fallback: zipball
        $url = (string) ( $release->zipball_url ?? '' );
        if ( $url && $token ) {
            $url = add_query_arg( 'access_token', $token, $url );
        }
        return $url;
    }

    /**
     * WordPress Update-Check
     * Wird aufgerufen wenn WP den update_plugins Transient setzt
     */
    public static function check_update( $transient ) {
        if ( empty( $transient ) ) {
            $transient = new stdClass();
        }
        if ( ! isset( $transient->response ) ) {
            $transient->response = array();
        }
        if ( ! isset( $transient->checked ) ) {
            $transient->checked = array();
        }

        // Aktuelle Version aus Plugin-Header
        if ( ! function_exists( 'get_plugin_data' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $plugin_data     = get_plugin_data( OEMM_PLUGIN_FILE );
        $current_version = $plugin_data['Version'] ?? OEMM_VERSION;

        // Sicherstellen dass unser Plugin im checked Array ist
        $transient->checked[ self::$plugin_slug ] = $current_version;

        $release = self::get_release();
        if ( ! $release ) {
            return $transient;
        }

        $latest_version = ltrim( $release->tag_name, 'v' );

        if ( version_compare( $latest_version, $current_version, '>' ) ) {
            $download_url = self::get_download_url( $release );
            if ( $download_url ) {
                $transient->response[ self::$plugin_slug ] = (object) array(
                    'id'            => 'github.com/' . self::$github_user . '/' . self::$github_repo,
                    'slug'          => 'oemm-startliste',
                    'plugin'        => self::$plugin_slug,
                    'new_version'   => $latest_version,
                    'url'           => 'https://github.com/' . self::$github_user . '/' . self::$github_repo,
                    'package'       => $download_url,
                    'icons'         => array(),
                    'banners'       => array(),
                    'banners_rtl'   => array(),
                    'tested'        => get_bloginfo( 'version' ),
                    'requires_php'  => '8.0',
                    'compatibility' => new stdClass(),
                );
            }
        }

        return $transient;
    }

    /**
     * Plugin-Info Popup
     */
    public static function plugin_info( $result, $action, $args ) {
        if ( $action !== 'plugin_information' ) return $result;
        if ( ! isset( $args->slug ) || $args->slug !== 'oemm-startliste' ) return $result;

        $release = self::get_release();
        if ( ! $release ) return $result;

        return (object) array(
            'name'           => 'OEMM Startliste',
            'slug'           => 'oemm-startliste',
            'version'        => ltrim( $release->tag_name, 'v' ),
            'author'         => 'Manuel Ribis GmbH',
            'homepage'       => 'https://github.com/' . self::$github_user . '/' . self::$github_repo,
            'last_updated'   => $release->published_at ?? '',
            'short_description' => 'Startlisten-Verwaltung für den Ötztaler Moped Marathon.',
            'sections'       => array(
                'changelog' => '<pre>' . esc_html( $release->body ?? '' ) . '</pre>',
            ),
            'download_link'  => self::get_download_url( $release ),
            'requires_php'   => '8.0',
            'tested'         => get_bloginfo( 'version' ),
        );
    }

    /**
     * Nach dem Update: Plugin-Ordner korrekt umbenennen
     */
    public static function after_install( $response, $hook_extra, $result ) {
        if ( ! isset( $hook_extra['plugin'] ) || $hook_extra['plugin'] !== self::$plugin_slug ) {
            return $response;
        }
        global $wp_filesystem;
        $dest = WP_PLUGIN_DIR . '/oemm-startliste';
        if ( $result['destination'] !== $dest ) {
            $wp_filesystem->move( $result['destination'], $dest, true );
            $result['destination'] = $dest;
        }
        activate_plugin( self::$plugin_slug );
        return $result;
    }

    /**
     * Update-Cache komplett leeren
     */
    public static function clear_cache(): void {
        delete_transient( 'oemm_github_release_v2' );
        delete_transient( 'oemm_github_release' );
        delete_site_transient( 'update_plugins' );
        wp_update_plugins();
    }
}
