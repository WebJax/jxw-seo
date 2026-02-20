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
    }

    /**
     * Output JSON-LD structured data for the current LocalSEO page.
     */
    public function output_schema() {
        $data = Router::get_current_data();
        if ( ! $data ) {
            return;
        }

        if ( empty( $data->custom_slug ) ) {
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
        $canonical      = home_url( '/localseo/' . $data->custom_slug . '/' );
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
                    'item'     => home_url( '/localseo/' . $data->custom_slug . '/' ),
                ],
            ],
        ];
    }
}
