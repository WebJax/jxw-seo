<?php
/**
 * REST API Class
 *
 * Handles REST API endpoints for React admin interface
 */

namespace LocalSEO;

class REST_API {
    private $namespace = 'localseo/v1';
    private $db;

    public function __construct() {
        $this->db = new Database();
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Get all rows
        register_rest_route( $this->namespace, '/data', [
            'methods' => 'GET',
            'callback' => [ $this, 'get_all_data' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ]);

        // Create new row
        register_rest_route( $this->namespace, '/data', [
            'methods' => 'POST',
            'callback' => [ $this, 'create_row' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ]);

        // Update row
        register_rest_route( $this->namespace, '/data/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [ $this, 'update_row' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ]);

        // Delete row
        register_rest_route( $this->namespace, '/data/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [ $this, 'delete_row' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ]);

        // Generate AI content for a row
        register_rest_route( $this->namespace, '/generate-ai/(?P<id>\d+)', [
            'methods' => 'POST',
            'callback' => [ $this, 'generate_ai_content' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ]);

        // Bulk generate AI content
        register_rest_route( $this->namespace, '/generate-ai-bulk', [
            'methods' => 'POST',
            'callback' => [ $this, 'generate_ai_bulk' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ]);
    }

    /**
     * Permission callback
     */
    public function check_permission() {
        return current_user_can( 'manage_options' );
    }

    /**
     * Get all data
     */
    public function get_all_data( $request ) {
        $data = $this->db->get_all();
        return rest_ensure_response( $data );
    }

    /**
     * Create new row
     */
    public function create_row( $request ) {
        $params = $request->get_json_params();

        $data = [
            'city' => sanitize_text_field( $params['city'] ?? '' ),
            'zip' => sanitize_text_field( $params['zip'] ?? '' ),
            'service_keyword' => sanitize_text_field( $params['service_keyword'] ?? '' ),
            'custom_slug' => sanitize_title( $params['custom_slug'] ?? '' ),
            'ai_generated_intro' => sanitize_textarea_field( $params['ai_generated_intro'] ?? '' ),
            'meta_title' => sanitize_text_field( $params['meta_title'] ?? '' ),
            'meta_description' => sanitize_textarea_field( $params['meta_description'] ?? '' ),
        ];

        $id = $this->db->insert( $data );

        if ( ! $id ) {
            return new \WP_Error( 'create_failed', __( 'Failed to create row', 'localseo-booster' ), [ 'status' => 500 ] );
        }

        $row = $this->db->get_by_id( $id );
        return rest_ensure_response( $row );
    }

    /**
     * Update row
     */
    public function update_row( $request ) {
        $id = $request->get_param( 'id' );
        $params = $request->get_json_params();

        $data = [];
        $allowed_fields = [ 'city', 'zip', 'service_keyword', 'custom_slug', 'ai_generated_intro', 'meta_title', 'meta_description' ];

        foreach ( $allowed_fields as $field ) {
            if ( isset( $params[ $field ] ) ) {
                if ( in_array( $field, [ 'ai_generated_intro', 'meta_description' ] ) ) {
                    $data[ $field ] = sanitize_textarea_field( $params[ $field ] );
                } elseif ( $field === 'custom_slug' ) {
                    $data[ $field ] = sanitize_title( $params[ $field ] );
                } else {
                    $data[ $field ] = sanitize_text_field( $params[ $field ] );
                }
            }
        }

        $success = $this->db->update( $id, $data );

        if ( ! $success ) {
            return new \WP_Error( 'update_failed', __( 'Failed to update row', 'localseo-booster' ), [ 'status' => 500 ] );
        }

        $row = $this->db->get_by_id( $id );
        return rest_ensure_response( $row );
    }

    /**
     * Delete row
     */
    public function delete_row( $request ) {
        $id = $request->get_param( 'id' );
        $success = $this->db->delete( $id );

        if ( ! $success ) {
            return new \WP_Error( 'delete_failed', __( 'Failed to delete row', 'localseo-booster' ), [ 'status' => 500 ] );
        }

        return rest_ensure_response( [ 'success' => true ] );
    }

    /**
     * Generate AI content for a single row
     */
    public function generate_ai_content( $request ) {
        $id = $request->get_param( 'id' );
        $row = $this->db->get_by_id( $id );

        if ( ! $row ) {
            return new \WP_Error( 'not_found', __( 'Row not found', 'localseo-booster' ), [ 'status' => 404 ] );
        }

        $row_data = [
            'city' => $row->city,
            'zip' => $row->zip,
            'service_keyword' => $row->service_keyword,
        ];

        $ai_content = AI_Engine::generate_content( $row_data );

        if ( is_wp_error( $ai_content ) ) {
            return $ai_content;
        }

        $this->db->update( $id, $ai_content );
        $updated_row = $this->db->get_by_id( $id );

        return rest_ensure_response( $updated_row );
    }

    /**
     * Bulk generate AI content for all rows with missing data
     */
    public function generate_ai_bulk( $request ) {
        $rows = $this->db->get_rows_missing_ai();
        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ( $rows as $row ) {
            $row_data = [
                'city' => $row->city,
                'zip' => $row->zip,
                'service_keyword' => $row->service_keyword,
            ];

            $ai_content = AI_Engine::generate_content( $row_data );

            if ( is_wp_error( $ai_content ) ) {
                $results['failed']++;
                $results['errors'][] = [
                    'id' => $row->id,
                    'message' => $ai_content->get_error_message(),
                ];
            } else {
                $this->db->update( $row->id, $ai_content );
                $results['success']++;
            }

            // Add a small delay to avoid rate limiting
            usleep( 500000 ); // 0.5 second
        }

        return rest_ensure_response( $results );
    }
}
