<?php
/**
 * Activator Class
 *
 * Handles plugin activation tasks
 */

namespace LocalSEO;

class Activator {
    /**
     * Activate plugin
     */
    public static function activate() {
        self::create_database_table();
        self::create_redirects_table();
        self::add_rewrite_rules();
        flush_rewrite_rules();
    }

    /**
     * Create custom database table
     */
    private static function create_database_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'localseo_data';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            city varchar(100) NOT NULL,
            zip varchar(20) DEFAULT '',
            service_keyword varchar(100) NOT NULL,
            custom_slug varchar(200) DEFAULT NULL,
            ai_generated_intro text DEFAULT '',
            meta_title varchar(255) DEFAULT '',
            meta_description varchar(500) DEFAULT '',
            nearby_cities varchar(500) DEFAULT '',
            local_landmarks text DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug_index (custom_slug),
            KEY city_service (city, service_keyword)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // Store the database version
        update_option( 'localseo_db_version', '1.1' );
    }

    /**
     * Run database migrations for existing installations.
     * Adds columns introduced after the initial release.
     */
    public static function maybe_upgrade_database() {
        global $wpdb;

        $table = $wpdb->prefix . 'localseo_data';

        // Guard: table must exist before we can ALTER it
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $table_exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s",
            DB_NAME,
            $table
        ) );

        if ( ! $table_exists ) {
            // Table doesn't exist yet – full activation will create it with all columns.
            return;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- %i identifier escaping requires WP 6.2+ (plugin requires WP 6.5+)
        $columns = $wpdb->get_col( $wpdb->prepare( 'DESCRIBE %i', $table ), 0 );

        if ( ! in_array( 'nearby_cities', $columns, true ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN nearby_cities varchar(500) DEFAULT '' AFTER meta_description" );
        }

        if ( ! in_array( 'local_landmarks', $columns, true ) ) {
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN local_landmarks text DEFAULT '' AFTER nearby_cities" );
        }

        update_option( 'localseo_db_version', '1.1' );

        // Ensure the redirects table exists for installations that pre-date v1.2.
        self::create_redirects_table();
    }

    /**
     * Add rewrite rules (they'll be flushed in activate)
     */
    private static function add_rewrite_rules() {
        // Router class will handle the actual rewrite rules
        // This is just a placeholder to ensure flush happens
    }

    /**
     * Create the redirects table (IF NOT EXISTS – safe to call on every activation).
     */
    public static function create_redirects_table() {
        global $wpdb;

        $table_name      = $wpdb->prefix . 'localseo_redirects';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint unsigned NOT NULL AUTO_INCREMENT,
            source_url varchar(500) NOT NULL,
            target_url varchar(500) NOT NULL,
            redirect_type smallint unsigned NOT NULL DEFAULT 301,
            hits bigint unsigned NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY source_url (source_url(191))
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
}
