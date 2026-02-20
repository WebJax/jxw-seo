<?php
/**
 * Schema Class
 *
 * Outputs JSON-LD structured data (Schema.org) for LocalSEO virtual pages.
 * Supports LocalBusiness and Service schema types.
 */

namespace LocalSEO;

class Schema {
    public function __construct() {
        add_action( 'wp_head', [ $this, 'output_schema' ], 5 );
        add_action( 'wp_head', [ $this, 'output_post_schema' ], 5 );
    }

    /**
     * Output JSON-LD structured data for the current LocalSEO page.
     */
    public function output_schema() {
        $data = Router::get_current_data();
        if ( ! $data ) {
            return;
        }

        if ( empty( $data->service_keyword ) || empty( $data->city ) ) {
            return;
        }

        $enabled = get_option( 'localseo_schema_enabled', '1' );
        if ( '1' !== $enabled ) {
            return;
        }

        $schema_type    = get_option( 'localseo_schema_type', 'LocalBusiness' );
        $business_name  = get_option( 'localseo_business_name', get_bloginfo( 'name' ) );
        $business_phone = get_option( 'localseo_business_phone', '' );
        $og_image       = get_option( 'localseo_og_image', '' );
        $canonical      = Router::get_page_url( $data );
        $description    = ! empty( $data->meta_description ) ? $data->meta_description : '';
        $service        = ! empty( $data->service_keyword ) ? $data->service_keyword : '';
        $city           = ! empty( $data->city ) ? $data->city : '';

        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => $schema_type,
            'name'     => trim( $business_name . ( $service ? ' – ' . $service : '' ) ),
            'url'      => $canonical,
        ];

        if ( $description ) {
            $schema['description'] = $description;
        }

        if ( $og_image ) {
            $schema['image'] = $og_image;
        }

        // Address / location info
        $schema['address'] = [
            '@type'           => 'PostalAddress',
            'addressLocality' => $city,
        ];

        if ( ! empty( $data->zip ) ) {
            $schema['address']['postalCode'] = $data->zip;
        }

        if ( $business_phone ) {
            $schema['telephone'] = $business_phone;
        }

        // Add areaServed for LocalBusiness and its subtypes
        $local_business_types = [ 'LocalBusiness', 'Service', 'ProfessionalService', 'HomeAndConstructionBusiness' ];
        if ( $city && in_array( $schema_type, $local_business_types, true ) ) {
            $schema['areaServed'] = [
                '@type' => 'City',
                'name'  => $city,
            ];
        }

        // Add breadcrumb list
        $breadcrumb = $this->build_breadcrumb( $data, $service, $city );
        if ( $breadcrumb ) {
            $schema['breadcrumb'] = $breadcrumb;
        }

        echo '<script type="application/ld+json">' . "\n";
        echo wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
        echo "\n" . '</script>' . "\n";
    }

    /**
     * Output JSON-LD structured data for regular WordPress posts and pages.
     *
     * Only runs when a schema type has been explicitly chosen in the SEO metabox.
     */
    public function output_post_schema() {
        // Skip LocalSEO virtual pages — handled by output_schema()
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

        $schema_type = (string) get_post_meta( $post->ID, '_localseo_schema_type', true );
        if ( empty( $schema_type ) ) {
            return;
        }

        $meta_title         = (string) get_post_meta( $post->ID, '_localseo_meta_title', true );
        $meta_description   = (string) get_post_meta( $post->ID, '_localseo_meta_description', true );
        $post_og_image      = (string) get_post_meta( $post->ID, '_localseo_og_image', true );
        $canonical_override = (string) get_post_meta( $post->ID, '_localseo_canonical', true );

        $title    = $meta_title ?: get_the_title( $post );
        $url      = $canonical_override ?: (string) get_permalink( $post );
        $og_image = $post_og_image ?: get_option( 'localseo_og_image', '' );

        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => $schema_type,
            'name'     => $title,
            'url'      => $url,
        ];

        if ( $meta_description ) {
            $schema['description'] = $meta_description;
        }

        if ( $og_image ) {
            $schema['image'] = $og_image;
        }

        // Article-type extras: dates and author
        $article_types = [ 'Article', 'BlogPosting', 'NewsArticle' ];
        if ( in_array( $schema_type, $article_types, true ) ) {
            $schema['datePublished'] = (string) get_the_date( 'c', $post );
            $schema['dateModified']  = (string) get_the_modified_date( 'c', $post );
            $schema['author']        = [
                '@type' => 'Person',
                'name'  => get_the_author_meta( 'display_name', (int) $post->post_author ),
            ];
        }

        echo '<script type="application/ld+json">' . "\n";
        echo wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
        echo "\n" . '</script>' . "\n";
    }

    /**
     * Build a BreadcrumbList schema array.
     *
     * @param object $data    LocalSEO row data.
     * @param string $service Service keyword (pre-validated).
     * @param string $city    City name (pre-validated).
     * @return array
     */
    private function build_breadcrumb( $data, $service, $city ) {
        $label = trim( $service . ( $city ? ' in ' . $city : '' ) );
        return [
            '@type'           => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type'    => 'ListItem',
                    'position' => 1,
                    'name'     => get_bloginfo( 'name' ),
                    'item'     => home_url( '/' ),
                ],
                [
                    '@type'    => 'ListItem',
                    'position' => 2,
                    'name'     => $label ?: $data->custom_slug,
                    'item'     => Router::get_page_url( $data ),
                ],
            ],
        ];
    }
}
