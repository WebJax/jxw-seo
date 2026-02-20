<?php
/**
 * Sitemap Class
 *
 * Registers LocalSEO virtual pages with the WordPress core Sitemaps API (WP 5.5+).
 * Includes the provider class used by the registry.
 */

namespace LocalSEO;

/**
 * Provides sitemap entries for LocalSEO virtual pages.
 */
class Sitemap_Provider extends \WP_Sitemaps_Provider {
    public function __construct() {
        $this->name        = 'localseo-pages';
        $this->object_type = 'localseo_page';
    }

    /**
     * Returns all URL entries for the sitemap.
     *
     * @param int    $page_num  Sitemap page number (1-based).
     * @param string $object_subtype Unused.
     * @return array
     */
    public function get_url_list( $page_num, $object_subtype = '' ) {
        $per_page = $this->get_sitemap_entries_per_page();
        $offset   = ( $page_num - 1 ) * $per_page;

        global $wpdb;
        $table = $wpdb->prefix . 'localseo_data';

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT service_keyword, city, updated_at FROM {$table} ORDER BY id ASC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ) );

        $urls = [];
        foreach ( $rows as $row ) {
            if ( empty( $row->service_keyword ) || empty( $row->city ) ) {
                continue;
            }
            $entry = [
                'loc' => home_url( '/service/' . sanitize_title( $row->service_keyword ) . '/' . sanitize_title( $row->city ) . '/' ),
            ];
            if ( ! empty( $row->updated_at ) ) {
                $entry['lastmod'] = gmdate( 'c', strtotime( $row->updated_at ) );
            }
            $urls[] = $entry;
        }

        return $urls;
    }

    /**
     * Returns the maximum number of pages for the sitemap.
     *
     * @param string $object_subtype Unused.
     * @return int
     */
    public function get_max_num_pages( $object_subtype = '' ) {
        global $wpdb;
        $table = $wpdb->prefix . 'localseo_data';
        $count = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$table}" );

        return (int) ceil( $count / $this->get_sitemap_entries_per_page() );
    }
}

/**
 * Main Sitemap integration class.
 */
class Sitemap {
    public function __construct() {
        add_action( 'init', [ $this, 'register_provider' ] );
    }

    /**
     * Register the custom sitemap provider with the WordPress sitemaps server.
     */
    public function register_provider() {
        if ( ! function_exists( 'wp_sitemaps_get_server' ) ) {
            return;
        }

        $enabled = get_option( 'localseo_sitemap_enabled', '1' );
        if ( '1' !== $enabled ) {
            return;
        }

        $server = wp_sitemaps_get_server();
        if ( $server && isset( $server->registry ) ) {
            $server->registry->add_provider( 'localseo-pages', new Sitemap_Provider() );
        }
    }
}
