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
        add_action( 'wp_head', [ $this, 'output_post_tags' ], 2 );
        add_filter( 'pre_get_document_title', [ $this, 'document_title' ], 20 );
    }

    /**
     * Override document title for LocalSEO pages and regular posts/pages.
     *
     * @param string $title
     * @return string
     */
    public function document_title( $title ) {
        // LocalSEO virtual page
        $data = Router::get_current_data();
        if ( $data ) {
            if ( ! empty( $data->meta_title ) ) {
                return $data->meta_title;
            }

            $service = ! empty( $data->service_keyword ) ? $data->service_keyword : '';
            $city    = ! empty( $data->city ) ? $data->city : '';

            return trim( $service . ' in ' . $city, ' in' );
        }

        // Regular singular post/page with a custom meta title
        if ( is_singular() ) {
            global $post;
            if ( $post ) {
                $meta_title = get_post_meta( $post->ID, '_localseo_meta_title', true );
                if ( $meta_title ) {
                    return $meta_title;
                }
            }
        }

        return $title;
    }

    /**
     * Output all SEO head tags for LocalSEO pages.
     */
    public function output_tags() {
        $data = Router::get_current_data();
        if ( ! $data ) {
            return;
        }

        if ( empty( $data->custom_slug ) ) {
            return;
        }

        $service = ! empty( $data->service_keyword ) ? $data->service_keyword : '';
        $city    = ! empty( $data->city ) ? $data->city : '';
        $title   = ! empty( $data->meta_title ) ? $data->meta_title : trim( $service . ' in ' . $city, ' in' );
        $description = ! empty( $data->meta_description ) ? $data->meta_description : '';
        $canonical   = Router::get_page_url( $data );
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

    /**
     * Output SEO head tags for regular WordPress posts and pages.
     *
     * Runs only on singular posts/pages that are not LocalSEO virtual pages.
     * Only outputs a tag when a value is explicitly set in the SEO metabox so
     * that WordPress' own canonical and other defaults are not overridden
     * unnecessarily.
     */
    public function output_post_tags() {
        // Skip LocalSEO virtual pages — handled by output_tags()
        if ( Router::get_current_data() ) {
            return;
        }

        if ( ! is_singular() ) {
            return;
        }

        global $post;
        if ( ! $post ) {
            return;
        }

        $meta_title       = (string) get_post_meta( $post->ID, '_localseo_meta_title', true );
        $meta_description = (string) get_post_meta( $post->ID, '_localseo_meta_description', true );
        $post_robots      = (string) get_post_meta( $post->ID, '_localseo_robots', true );
        $post_og_image    = (string) get_post_meta( $post->ID, '_localseo_og_image', true );
        $canonical_override = (string) get_post_meta( $post->ID, '_localseo_canonical', true );

        // Nothing configured for this post – nothing to output
        if ( ! $meta_title && ! $meta_description && ! $post_robots &&
             ! $post_og_image && ! $canonical_override ) {
            return;
        }

        $title     = $meta_title ?: get_the_title( $post );
        $canonical = $canonical_override ?: (string) get_permalink( $post );
        $og_image  = $post_og_image ?: get_option( 'localseo_og_image', '' );
        $site_name = get_bloginfo( 'name' );

        // Robots: post-level value first, then fall back to site default if configured
        $robots = $post_robots ?: get_option( 'localseo_robots', '' );

        if ( $meta_description ) {
            echo '<meta name="description" content="' . esc_attr( $meta_description ) . '">' . "\n";
        }

        if ( $canonical_override ) {
            echo '<link rel="canonical" href="' . esc_url( $canonical ) . '">' . "\n";
        }

        if ( $robots ) {
            echo '<meta name="robots" content="' . esc_attr( $robots ) . '">' . "\n";
        }

        // Open Graph and Twitter: output only when content-specific SEO data is set
        if ( $meta_title || $meta_description || $post_og_image ) {
            // Use 'article' for posts, 'website' for pages and other post types
            $og_type = ( 'post' === $post->post_type ) ? 'article' : 'website';

            echo '<meta property="og:type" content="' . esc_attr( $og_type ) . '">' . "\n";
            echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
            echo '<meta property="og:url" content="' . esc_url( $canonical ) . '">' . "\n";
            echo '<meta property="og:site_name" content="' . esc_attr( $site_name ) . '">' . "\n";

            if ( $meta_description ) {
                echo '<meta property="og:description" content="' . esc_attr( $meta_description ) . '">' . "\n";
            }

            if ( $og_image ) {
                echo '<meta property="og:image" content="' . esc_url( $og_image ) . '">' . "\n";
            }

            // Twitter Card
            $twitter_card = $og_image ? 'summary_large_image' : 'summary';
            echo '<meta name="twitter:card" content="' . esc_attr( $twitter_card ) . '">' . "\n";
            echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '">' . "\n";

            if ( $meta_description ) {
                echo '<meta name="twitter:description" content="' . esc_attr( $meta_description ) . '">' . "\n";
            }

            if ( $og_image ) {
                echo '<meta name="twitter:image" content="' . esc_url( $og_image ) . '">' . "\n";
            }
        }
    }
}
