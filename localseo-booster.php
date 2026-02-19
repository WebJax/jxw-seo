<?php
/**
 * Plugin Name: LocalSEO Booster
 * Plugin URI: https://github.com/WebJax/jxw-seo
 * Description: AI-Powered Programmatic SEO Plugin for FSE that generates virtual landing pages based on City + Service data
 * Version: 1.0.0
 * Author: WebJax
 * Author URI: https://github.com/WebJax
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: localseo-booster
 * Domain Path: /languages
 * Requires at least: 6.5
 * Requires PHP: 8.1
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'LOCALSEO_VERSION', '1.0.0' );
define( 'LOCALSEO_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'LOCALSEO_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'LOCALSEO_PLUGIN_FILE', __FILE__ );

// Autoload classes
spl_autoload_register( function( $class ) {
    $prefix = 'LocalSEO\\';
    $base_dir = LOCALSEO_PLUGIN_DIR . 'includes/';

    $len = strlen( $prefix );
    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        return;
    }

    $relative_class = substr( $class, $len );
    $file = $base_dir . 'class-' . strtolower( str_replace( '\\', '-', $relative_class ) ) . '.php';

    if ( file_exists( $file ) ) {
        require $file;
    }
});

// Activation hook
register_activation_hook( __FILE__, 'localseo_activate_plugin' );
function localseo_activate_plugin() {
    require_once LOCALSEO_PLUGIN_DIR . 'includes/class-activator.php';
    LocalSEO\Activator::activate();
}

// Deactivation hook
register_deactivation_hook( __FILE__, 'localseo_deactivate_plugin' );
function localseo_deactivate_plugin() {
    require_once LOCALSEO_PLUGIN_DIR . 'includes/class-deactivator.php';
    LocalSEO\Deactivator::deactivate();
}

// Initialize the plugin
add_action( 'plugins_loaded', 'localseo_init' );
function localseo_init() {
    // Load includes
    require_once LOCALSEO_PLUGIN_DIR . 'includes/class-database.php';
    require_once LOCALSEO_PLUGIN_DIR . 'includes/class-admin.php';
    require_once LOCALSEO_PLUGIN_DIR . 'includes/class-router.php';
    require_once LOCALSEO_PLUGIN_DIR . 'includes/class-block-bindings.php';
    require_once LOCALSEO_PLUGIN_DIR . 'includes/class-ai-engine.php';
    require_once LOCALSEO_PLUGIN_DIR . 'includes/class-rest-api.php';
    require_once LOCALSEO_PLUGIN_DIR . 'includes/class-seo-tags.php';
    require_once LOCALSEO_PLUGIN_DIR . 'includes/class-sitemap.php';
    require_once LOCALSEO_PLUGIN_DIR . 'includes/class-schema.php';

    // Initialize components
    new LocalSEO\Admin();
    new LocalSEO\Router();
    new LocalSEO\Block_Bindings();
    new LocalSEO\REST_API();
    new LocalSEO\SEO_Tags();
    new LocalSEO\Sitemap();
    new LocalSEO\Schema();
}
