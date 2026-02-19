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
            custom_slug varchar(200) DEFAULT '',
            ai_generated_intro text DEFAULT '',
            meta_title varchar(255) DEFAULT '',
            meta_description varchar(500) DEFAULT '',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug_index (custom_slug),
            KEY city_service (city, service_keyword)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        // Store the database version
        update_option( 'localseo_db_version', '1.0' );
    }

    /**
     * Add rewrite rules (they'll be flushed in activate)
     */
    private static function add_rewrite_rules() {
        // Router class will handle the actual rewrite rules
        // This is just a placeholder to ensure flush happens
    }
}
