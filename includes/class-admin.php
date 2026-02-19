<?php
/**
 * Admin Class
 *
 * Handles WordPress admin interface
 */

namespace LocalSEO;

class Admin {
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
    }

    /**
     * Add admin menu pages
     */
    public function add_admin_menu() {
        // Main menu page
        add_menu_page(
            __( 'LocalSEO Booster', 'localseo-booster' ),
            __( 'LocalSEO', 'localseo-booster' ),
            'manage_options',
            'localseo-booster',
            [ $this, 'render_data_center' ],
            'dashicons-location',
            30
        );

        // Data Center submenu (same as main)
        add_submenu_page(
            'localseo-booster',
            __( 'Data Center', 'localseo-booster' ),
            __( 'Data Center', 'localseo-booster' ),
            'manage_options',
            'localseo-booster',
            [ $this, 'render_data_center' ]
        );

        // Settings submenu
        add_submenu_page(
            'localseo-booster',
            __( 'Settings', 'localseo-booster' ),
            __( 'Settings', 'localseo-booster' ),
            'manage_options',
            'localseo-settings',
            [ $this, 'render_settings' ]
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets( $hook ) {
        // Only load on our admin pages
        if ( strpos( $hook, 'localseo' ) === false ) {
            return;
        }

        // Enqueue WordPress components styles
        wp_enqueue_style( 'wp-components' );

        // Enqueue our React app
        $asset_file = LOCALSEO_PLUGIN_DIR . 'build/index.asset.php';
        
        if ( file_exists( $asset_file ) ) {
            $asset = include $asset_file;

            wp_enqueue_script(
                'localseo-admin',
                LOCALSEO_PLUGIN_URL . 'build/index.js',
                $asset['dependencies'],
                $asset['version'],
                true
            );

            wp_enqueue_style(
                'localseo-admin',
                LOCALSEO_PLUGIN_URL . 'build/index.css',
                [ 'wp-components' ],
                $asset['version']
            );

            // Localize script with data
            wp_localize_script( 'localseo-admin', 'localSEOData', [
                'apiUrl' => rest_url( 'localseo/v1' ),
                'nonce' => wp_create_nonce( 'wp_rest' ),
                'settings' => [
                    'apiKey' => get_option( 'localseo_api_key', '' ),
                    'apiProvider' => get_option( 'localseo_api_provider', 'openai' ),
                    'systemPrompt' => get_option( 'localseo_system_prompt', '' ),
                ]
            ]);
        }
    }

    /**
     * Render Data Center page
     */
    public function render_data_center() {
        echo '<div id="localseo-data-center" class="wrap"></div>';
    }

    /**
     * Render Settings page
     */
    public function render_settings() {
        // Handle form submission
        if ( isset( $_POST['localseo_settings_nonce'] ) && 
             wp_verify_nonce( $_POST['localseo_settings_nonce'], 'localseo_settings' ) ) {
            
            update_option( 'localseo_api_key', sanitize_text_field( $_POST['api_key'] ?? '' ) );
            update_option( 'localseo_api_provider', sanitize_text_field( $_POST['api_provider'] ?? 'openai' ) );
            update_option( 'localseo_system_prompt', wp_kses_post( $_POST['system_prompt'] ?? '' ) );

            echo '<div class="notice notice-success"><p>' . __( 'Settings saved successfully!', 'localseo-booster' ) . '</p></div>';
        }

        $api_key = get_option( 'localseo_api_key', '' );
        $api_provider = get_option( 'localseo_api_provider', 'openai' );
        $system_prompt = get_option( 'localseo_system_prompt', 'You are an SEO expert for a local service company. Write a 50-word intro for {service} in {city} ({zip}). Focus on local expertise and trust.' );

        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form method="post" action="">
                <?php wp_nonce_field( 'localseo_settings', 'localseo_settings_nonce' ); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="api_provider"><?php _e( 'AI Provider', 'localseo-booster' ); ?></label>
                        </th>
                        <td>
                            <select name="api_provider" id="api_provider" class="regular-text">
                                <option value="openai" <?php selected( $api_provider, 'openai' ); ?>>OpenAI (GPT-4)</option>
                                <option value="anthropic" <?php selected( $api_provider, 'anthropic' ); ?>>Anthropic (Claude)</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="api_key"><?php _e( 'API Key', 'localseo-booster' ); ?></label>
                        </th>
                        <td>
                            <input type="password" name="api_key" id="api_key" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text" />
                            <p class="description"><?php _e( 'Enter your OpenAI or Anthropic API key', 'localseo-booster' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="system_prompt"><?php _e( 'System Prompt Template', 'localseo-booster' ); ?></label>
                        </th>
                        <td>
                            <textarea name="system_prompt" id="system_prompt" rows="5" class="large-text code"><?php echo esc_textarea( $system_prompt ); ?></textarea>
                            <p class="description">
                                <?php _e( 'Available placeholders: {service}, {city}, {zip}', 'localseo-booster' ); ?>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button( __( 'Save Settings', 'localseo-booster' ) ); ?>
            </form>
        </div>
        <?php
    }
}
