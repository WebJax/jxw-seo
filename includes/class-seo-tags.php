<?php
/**
 * SEO Tags Class
 *
 * Outputs Open Graph, Twitter Card, canonical, and robots meta tags
 * for LocalSEO virtual pages.
 */

namespace LocalSEO;

class SEO_Tags {
    public function __construct() {
        add_action( 'wp_head', [ $this, 'output_tags' ], 2 );
        add_filter( 'pre_get_document_title', [ $this, 'document_title' ], 20 );
    }

    /**
     * Override document title for LocalSEO pages.
     *
     * @param string $title
     * @return string
     */
    public function document_title( $title ) {
        $data = Router::get_current_data();
        if ( ! $data ) {
            return $title;
        }

        if ( ! empty( $data->meta_title ) ) {
            return $data->meta_title;
        }

        return $data->service_keyword . ' in ' . $data->city;
    }

    /**
     * Output all SEO head tags for LocalSEO pages.
     */
    public function output_tags() {
        $data = Router::get_current_data();
        if ( ! $data ) {
            return;
        }

        $title       = ! empty( $data->meta_title ) ? $data->meta_title : ( $data->service_keyword . ' in ' . $data->city );
        $description = ! empty( $data->meta_description ) ? $data->meta_description : '';
        $canonical   = home_url( '/localseo/' . $data->custom_slug . '/' );
        $og_image    = get_option( 'localseo_og_image', '' );
        $site_name   = get_bloginfo( 'name' );

        // Meta description
        if ( $description ) {
            echo '<meta name="description" content="' . esc_attr( $description ) . '">' . "\n";
        }

        // Canonical URL
        echo '<link rel="canonical" href="' . esc_url( $canonical ) . '">' . "\n";

        // Robots meta
        $robots = get_option( 'localseo_robots', 'index, follow' );
        echo '<meta name="robots" content="' . esc_attr( $robots ) . '">' . "\n";

        // Open Graph
        echo '<meta property="og:type" content="website">' . "\n";
        echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
        echo '<meta property="og:url" content="' . esc_url( $canonical ) . '">' . "\n";
        echo '<meta property="og:site_name" content="' . esc_attr( $site_name ) . '">' . "\n";

        if ( $description ) {
            echo '<meta property="og:description" content="' . esc_attr( $description ) . '">' . "\n";
        }

        if ( $og_image ) {
            echo '<meta property="og:image" content="' . esc_url( $og_image ) . '">' . "\n";
        }

        // Twitter Card
        $twitter_card = $og_image ? 'summary_large_image' : 'summary';
        echo '<meta name="twitter:card" content="' . esc_attr( $twitter_card ) . '">' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '">' . "\n";

        if ( $description ) {
            echo '<meta name="twitter:description" content="' . esc_attr( $description ) . '">' . "\n";
        }

        if ( $og_image ) {
            echo '<meta name="twitter:image" content="' . esc_url( $og_image ) . '">' . "\n";
        }
    }
}
