<?php
/**
 * Block Bindings Class
 *
 * Handles Block Bindings API for dynamic content injection
 */

namespace LocalSEO;

class Block_Bindings {
    public function __construct() {
        add_action( 'init', [ $this, 'register_bindings_source' ] );
    }

    /**
     * Register block bindings source
     */
    public function register_bindings_source() {
        if ( ! function_exists( 'register_block_bindings_source' ) ) {
            return; // Block Bindings API not available (WP < 6.5)
        }

        register_block_bindings_source( 'localseo/data', [
            'label' => __( 'Local SEO Data', 'localseo-booster' ),
            'get_value_callback' => [ $this, 'get_value_callback' ],
            'uses_context' => [],
        ]);
    }

    /**
     * Get value callback for block bindings
     *
     * @param array $source_args Arguments passed from the block binding
     * @param WP_Block $block_instance The block instance
     * @return string The value to bind
     */
    public function get_value_callback( $source_args, $block_instance ) {
        // Get current LocalSEO data
        $data = Router::get_current_data();

        if ( ! $data ) {
            return '';
        }

        // Get the field key from source args
        $key = $source_args['key'] ?? '';

        if ( empty( $key ) ) {
            return '';
        }

        // Map binding keys to database fields
        $field_map = [
            'city' => 'city',
            'zip' => 'zip',
            'service' => 'service_keyword',
            'service_keyword' => 'service_keyword',
            'intro' => 'ai_generated_intro',
            'intro_text' => 'ai_generated_intro',
            'ai_generated_intro' => 'ai_generated_intro',
            'meta_title' => 'meta_title',
            'meta_description' => 'meta_description',
            'slug' => 'custom_slug',
            'nearby_cities' => 'nearby_cities',
            'local_landmarks' => 'local_landmarks',
        ];

        $field = $field_map[ $key ] ?? $key;

        // Computed / virtual keys
        if ( $key === 'phone_url' ) {
            $phone = get_option( 'localseo_business_phone', '' );
            return $phone ? 'tel:' . preg_replace( '/[^+\d]/', '', $phone ) : '';
        }

        if ( $key === 'cta_label' ) {
            $phone = get_option( 'localseo_business_phone', '' );
            $city  = $data->city ?? '';
            if ( $phone ) {
                return sprintf(
                    /* translators: 1: city name, 2: phone number */
                    __( 'Ring til din lokale ekspert i %1$s – %2$s', 'localseo-booster' ),
                    $city,
                    $phone
                );
            }
            return __( 'Kontakt os i dag', 'localseo-booster' );
        }

        // Return the field value
        if ( isset( $data->$field ) ) {
            return $data->$field;
        }

        return '';
    }
}
