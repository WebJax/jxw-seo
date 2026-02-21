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
        add_action( 'admin_post_localseo_export_csv', [ $this, 'export_csv' ] );
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

        // Import rows from CSV
        register_rest_route( $this->namespace, '/import-csv', [
            'methods' => 'POST',
            'callback' => [ $this, 'import_csv' ],
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
            'nearby_cities' => sanitize_text_field( $params['nearby_cities'] ?? '' ),
            'local_landmarks' => sanitize_textarea_field( $params['local_landmarks'] ?? '' ),
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
        $allowed_fields = [ 'city', 'zip', 'service_keyword', 'custom_slug', 'ai_generated_intro', 'meta_title', 'meta_description', 'nearby_cities', 'local_landmarks' ];

        foreach ( $allowed_fields as $field ) {
            if ( isset( $params[ $field ] ) ) {
                if ( in_array( $field, [ 'ai_generated_intro', 'meta_description', 'local_landmarks' ] ) ) {
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

    /**
     * Import rows from a CSV string.
     *
     * Expects a JSON body: { "csv": "<csv content>" }
     * Required CSV columns: city, service_keyword
     * Optional columns: zip, meta_title, meta_description, nearby_cities, local_landmarks
     *
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response|\WP_Error
     */
    public function import_csv( $request ) {
        $params      = $request->get_json_params();
        $csv_content = isset( $params['csv'] ) ? (string) $params['csv'] : '';

        if ( '' === trim( $csv_content ) ) {
            return new \WP_Error(
                'empty_csv',
                __( 'No CSV data provided.', 'localseo-booster' ),
                [ 'status' => 400 ]
            );
        }

        // Parse the CSV content correctly, handling quoted fields that may span
        // multiple lines (e.g. meta_description with embedded newlines).
        $temp = fopen( 'php://temp', 'r+' );
        fwrite( $temp, $csv_content );
        rewind( $temp );

        // Read the header row.
        $headers = fgetcsv( $temp );
        if ( false === $headers || null === $headers ) {
            fclose( $temp );
            return new \WP_Error(
                'invalid_csv',
                __( 'CSV must contain a header row and at least one data row.', 'localseo-booster' ),
                [ 'status' => 400 ]
            );
        }
        // Sanitize header names to alphanumeric + underscores only.
        $headers = array_map( function( $h ) {
            return preg_replace( '/[^a-z0-9_]/', '', strtolower( trim( (string) $h ) ) );
        }, $headers );

        $required = [ 'city', 'service_keyword' ];
        foreach ( $required as $field ) {
            if ( ! in_array( $field, $headers, true ) ) {
                fclose( $temp );
                return new \WP_Error(
                    'missing_columns',
                    /* translators: %s: comma-separated list of required column names */
                    sprintf(
                        __( 'CSV must include these columns: %s', 'localseo-booster' ),
                        implode( ', ', $required )
                    ),
                    [ 'status' => 400 ]
                );
            }
        }

        $imported = 0;
        $skipped  = 0;

        while ( ( $values = fgetcsv( $temp ) ) !== false ) {
            if ( null === $values ) {
                // Empty line in the stream — skip.
                continue;
            }

            // Pad to header count in case trailing empty columns are missing.
            while ( count( $values ) < count( $headers ) ) {
                $values[] = '';
            }

            $assoc = array_combine( $headers, array_slice( $values, 0, count( $headers ) ) );

            $city    = sanitize_text_field( $assoc['city'] ?? '' );
            $service = sanitize_text_field( $assoc['service_keyword'] ?? '' );

            if ( '' === $city || '' === $service ) {
                $skipped++;
                continue;
            }

            $row_data = [
                'city'             => $city,
                'zip'              => sanitize_text_field( $assoc['zip'] ?? '' ),
                'service_keyword'  => $service,
                'meta_title'       => sanitize_text_field( $assoc['meta_title'] ?? '' ),
                'meta_description' => sanitize_textarea_field( $assoc['meta_description'] ?? '' ),
                'nearby_cities'    => sanitize_text_field( $assoc['nearby_cities'] ?? '' ),
                'local_landmarks'  => sanitize_textarea_field( $assoc['local_landmarks'] ?? '' ),
            ];

            $id = $this->db->insert( $row_data );
            if ( $id ) {
                $imported++;
            } else {
                $skipped++;
            }
        }

        fclose( $temp );

        return rest_ensure_response( [
            'imported' => $imported,
            'skipped'  => $skipped,
        ] );
    }

    /**
     * Handle admin-post CSV export action.
     *
     * Outputs a UTF-8 CSV file for download and exits.
     * Secured by capability check and nonce verification.
     */
    public function export_csv() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'localseo-booster' ) );
        }

        check_admin_referer( 'localseo_export_csv' );

        $rows = $this->db->get_all();

        // Send CSV headers.
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="localseo-data.csv"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $output = fopen( 'php://output', 'w' );

        // BOM for Excel UTF-8 compatibility.
        fwrite( $output, "\xEF\xBB\xBF" );

        // Sanitize a value to prevent CSV formula injection.
        // Prefixes any value beginning with a formula-triggering character with
        // a single-quote so that spreadsheet apps treat it as plain text.
        $sanitize_csv = function ( $value ) {
            if ( null === $value ) {
                return '';
            }
            $value = (string) $value;
            if ( '' !== $value && preg_match( '/^[=+\-@\x09\x0D]/', $value ) ) {
                $value = "'" . $value;
            }
            return $value;
        };

        fputcsv( $output, [ 'city', 'zip', 'service_keyword', 'meta_title', 'meta_description', 'nearby_cities', 'local_landmarks' ] );

        foreach ( $rows as $row ) {
            fputcsv( $output, [
                $sanitize_csv( $row->city ),
                $sanitize_csv( $row->zip ),
                $sanitize_csv( $row->service_keyword ),
                $sanitize_csv( $row->meta_title ),
                $sanitize_csv( $row->meta_description ),
                $sanitize_csv( $row->nearby_cities ?? '' ),
                $sanitize_csv( $row->local_landmarks ?? '' ),
            ] );
        }

        fclose( $output );
        exit;
    }
}
