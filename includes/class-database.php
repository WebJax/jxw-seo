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
     * Find a row by pre-slugified service and city values.
     *
     * URL parameters extracted from /service/{service}/{city}/ are already
     * sanitize_title'd (lowercased, special chars converted, spaces → hyphens).
     * This method uses a SQL pre-filter (LOWER + space→hyphen) and then performs
     * an exact sanitize_title() comparison in PHP so that e.g. "Kloakmester
     * Service" stored in the DB is found when the URL contains
     * "kloakmester-service".
     *
     * Note: the SQL pre-filter does not convert accented characters (e.g. 'ø').
     * If city/service names contain non-ASCII characters, store them in their
     * pre-slugified form (using sanitize_title) to ensure reliable lookups.
     *
     * @param string $service_slug  Slugified service_keyword from the URL.
     * @param string $city_slug     Slugified city from the URL.
     * @return object|null
     */
    public function get_by_service_city_slugs( $service_slug, $city_slug ) {
        global $wpdb;
        // Pre-filter using SQL normalisation (lowercase + spaces-to-hyphens) to
        // avoid loading the entire table. The PHP sanitize_title() check below
        // then handles any additional character conversions (e.g. underscores,
        // dots) that the SQL normalisation cannot cover.
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$this->table_name}
             WHERE LOWER(REPLACE(service_keyword, ' ', '-')) = %s
               AND LOWER(REPLACE(city, ' ', '-')) = %s",
            $service_slug,
            $city_slug
        ) );
        foreach ( $rows as $row ) {
            if ( sanitize_title( $row->service_keyword ) === $service_slug &&
                 sanitize_title( $row->city ) === $city_slug ) {
                return $row;
            }
        }
        return null;
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
