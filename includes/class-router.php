<?php
/**
 * Router Class
 *
 * Handles virtual routing for LocalSEO pages
 */

namespace LocalSEO;

class Router {
    private $db;

    public function __construct() {
        $this->db = new Database();
        add_action( 'init', [ $this, 'add_rewrite_rules' ] );
        add_action( 'query_vars', [ $this, 'add_query_vars' ] );
        add_action( 'template_include', [ $this, 'template_loader' ] );
    }

    /**
     * Add rewrite rules for virtual pages
     */
    public function add_rewrite_rules() {
        // Pattern: /service-city/ or custom slug
        add_rewrite_rule(
            '^localseo/([^/]+)/?$',
            'index.php?localseo_page=1&localseo_slug=$matches[1]',
            'top'
        );

        // Alternative pattern: /service/city/
        add_rewrite_rule(
            '^service/([^/]+)/([^/]+)/?$',
            'index.php?localseo_page=1&localseo_service=$matches[1]&localseo_city=$matches[2]',
            'top'
        );
    }

    /**
     * Add custom query vars
     */
    public function add_query_vars( $vars ) {
        $vars[] = 'localseo_page';
        $vars[] = 'localseo_slug';
        $vars[] = 'localseo_service';
        $vars[] = 'localseo_city';
        return $vars;
    }

    /**
     * Load custom template for LocalSEO pages
     */
    public function template_loader( $template ) {
        if ( ! get_query_var( 'localseo_page' ) ) {
            return $template;
        }

        // Get the current page data
        $data = $this->get_current_page_data();

        if ( ! $data ) {
            // No matching LocalSEO data found; mark the request as 404
            status_header( 404 );
            global $wp_query;
            if ( isset( $wp_query ) && is_object( $wp_query ) ) {
                $wp_query->set_404();
            }
            nocache_headers();
            return $template;
        }

        // Store data in global for block bindings
        global $localseo_current_data;
        $localseo_current_data = $data;

        // Set document title
        add_filter( 'pre_get_document_title', function() use ( $data ) {
            return $data->meta_title ?: ( $data->service_keyword . ' in ' . $data->city );
        });

        // Set meta description
        add_action( 'wp_head', function() use ( $data ) {
            if ( $data->meta_description ) {
                echo '<meta name="description" content="' . esc_attr( $data->meta_description ) . '">' . "\n";
            }
        });

        // Look for block template
        $block_template = locate_template( 'templates/single-localseo.html' );
        
        if ( $block_template ) {
            return $block_template;
        }

        // Look for PHP template fallback
        $php_template = LOCALSEO_PLUGIN_DIR . 'templates/single-localseo.php';
        
        if ( file_exists( $php_template ) ) {
            return $php_template;
        }

        // Use default template with block bindings
        return $template;
    }

    /**
     * Get current page data from database
     */
    private function get_current_page_data() {
        $slug = get_query_var( 'localseo_slug' );
        $service = get_query_var( 'localseo_service' );
        $city = get_query_var( 'localseo_city' );

        if ( $slug ) {
            return $this->db->get_by_slug( $slug );
        }

        if ( $service && $city ) {
            return $this->db->get_by_city_service( $city, $service );
        }

        return null;
    }

    /**
     * Get current LocalSEO data (public helper function)
     */
    public static function get_current_data() {
        global $localseo_current_data;
        return $localseo_current_data;
    }
}
