<?php
/**
 * Database Class
 *
 * Handles all database operations for LocalSEO data
 */

namespace LocalSEO;

class Database {
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'localseo_data';
    }

    /**
     * Get all rows
     *
     * @return array
     */
    public function get_all() {
        global $wpdb;
        // Table name is safe - it's set in constructor from wpdb->prefix which is controlled by WordPress core
        return $wpdb->get_results( "SELECT * FROM {$this->table_name} ORDER BY id DESC" );
    }

    /**
     * Get row by ID
     *
     * @param int $id
     * @return object|null
     */
    public function get_by_id( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $id
        ));
    }

    /**
     * Get row by slug
     *
     * @param string $slug
     * @return object|null
     */
    public function get_by_slug( $slug ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE custom_slug = %s",
            $slug
        ));
    }

    /**
     * Get row by city and service
     *
     * @param string $city
     * @param string $service
     * @return object|null
     */
    public function get_by_city_service( $city, $service ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE city = %s AND service_keyword = %s",
            $city,
            $service
        ));
    }

    /**
     * Insert new row
     *
     * @param array $data
     * @return int|false
     */
    public function insert( $data ) {
        global $wpdb;

        // Generate slug if not provided
        if ( empty( $data['custom_slug'] ) && ! empty( $data['service_keyword'] ) && ! empty( $data['city'] ) ) {
            $base_slug = sanitize_title( $data['service_keyword'] . '-' . $data['city'] );
            $slug = $base_slug;
            $counter = 1;
            
            // Check for duplicate slugs and append number if needed (max 100 attempts)
            while ( $this->get_by_slug( $slug ) && $counter < 100 ) {
                $slug = $base_slug . '-' . $counter;
                $counter++;
            }
            
            $data['custom_slug'] = $slug;
        }

        $inserted = $wpdb->insert(
            $this->table_name,
            $data,
            [ '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
        );

        return $inserted ? $wpdb->insert_id : false;
    }

    /**
     * Update row
     *
     * @param int $id
     * @param array $data
     * @return bool
     */
    public function update( $id, $data ) {
        global $wpdb;

        return $wpdb->update(
            $this->table_name,
            $data,
            [ 'id' => $id ],
            null,
            [ '%d' ]
        ) !== false;
    }

    /**
     * Delete row
     *
     * @param int $id
     * @return bool
     */
    public function delete( $id ) {
        global $wpdb;

        return $wpdb->delete(
            $this->table_name,
            [ 'id' => $id ],
            [ '%d' ]
        ) !== false;
    }

    /**
     * Get rows with missing AI fields
     *
     * @return array
     */
    public function get_rows_missing_ai() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM {$this->table_name} 
             WHERE (ai_generated_intro = '' OR ai_generated_intro IS NULL) 
             OR (meta_title = '' OR meta_title IS NULL)
             OR (meta_description = '' OR meta_description IS NULL)"
        );
    }
}
