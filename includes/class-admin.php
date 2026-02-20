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
                    'hasApiKey' => ! empty( get_option( 'localseo_api_key', '' ) ),
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
            
            // Verify user has capability to manage options
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( __( 'You do not have sufficient permissions to access this page.', 'localseo-booster' ) );
            }
            
            update_option( 'localseo_api_key', sanitize_text_field( $_POST['api_key'] ?? '' ) );
            update_option( 'localseo_api_provider', sanitize_text_field( $_POST['api_provider'] ?? 'openai' ) );
            update_option( 'localseo_system_prompt', wp_kses_post( $_POST['system_prompt'] ?? '' ) );

            // SEO settings
            update_option( 'localseo_business_name', sanitize_text_field( $_POST['business_name'] ?? '' ) );
            update_option( 'localseo_business_phone', sanitize_text_field( $_POST['business_phone'] ?? '' ) );
            update_option( 'localseo_og_image', esc_url_raw( $_POST['og_image'] ?? '' ) );
            update_option( 'localseo_response_time', sanitize_text_field( $_POST['response_time'] ?? '60' ) );
            update_option( 'localseo_customer_count_text', sanitize_text_field( $_POST['customer_count_text'] ?? '' ) );

            $allowed_schema_types = [ 'LocalBusiness', 'Service', 'ProfessionalService', 'HomeAndConstructionBusiness' ];
            $schema_type_raw = sanitize_text_field( $_POST['schema_type'] ?? 'LocalBusiness' );
            update_option( 'localseo_schema_type', in_array( $schema_type_raw, $allowed_schema_types, true ) ? $schema_type_raw : 'LocalBusiness' );

            $allowed_robots = [ 'index, follow', 'noindex, follow', 'index, nofollow', 'noindex, nofollow' ];
            $robots_raw = sanitize_text_field( $_POST['robots'] ?? 'index, follow' );
            update_option( 'localseo_robots', in_array( $robots_raw, $allowed_robots, true ) ? $robots_raw : 'index, follow' );
            update_option( 'localseo_schema_enabled', ! empty( $_POST['schema_enabled'] ) ? '1' : '0' );
            update_option( 'localseo_sitemap_enabled', ! empty( $_POST['sitemap_enabled'] ) ? '1' : '0' );

            echo '<div class="notice notice-success"><p>' . __( 'Settings saved successfully!', 'localseo-booster' ) . '</p></div>';
        }

        $api_key = get_option( 'localseo_api_key', '' );
        $api_provider = get_option( 'localseo_api_provider', 'openai' );
        $system_prompt = get_option( 'localseo_system_prompt', 'You are an SEO expert for a local service company. Write a 50-word intro for {service} in {city} ({zip}). Focus on local expertise and trust.' );

        $business_name   = get_option( 'localseo_business_name', '' );
        $business_phone  = get_option( 'localseo_business_phone', '' );
        $og_image        = get_option( 'localseo_og_image', '' );
        $schema_type     = get_option( 'localseo_schema_type', 'LocalBusiness' );
        $robots          = get_option( 'localseo_robots', 'index, follow' );
        $schema_enabled  = get_option( 'localseo_schema_enabled', '1' );
        $sitemap_enabled = get_option( 'localseo_sitemap_enabled', '1' );
        $response_time   = get_option( 'localseo_response_time', '60' );
        $customer_count_text = get_option( 'localseo_customer_count_text', '' );

        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <form method="post" action="">
                <?php wp_nonce_field( 'localseo_settings', 'localseo_settings_nonce' ); ?>
                
                <h2><?php _e( 'AI Settings', 'localseo-booster' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="api_provider"><?php _e( 'AI Provider', 'localseo-booster' ); ?></label>
                        </th>
                        <td>
                            <select name="api_provider" id="api_provider" class="regular-text">
                                <option value="openai" <?php selected( $api_provider, 'openai' ); ?>>OpenAI (GPT-4o-mini)</option>
                                <option value="anthropic" <?php selected( $api_provider, 'anthropic' ); ?>>Anthropic (Claude)</option>
                                <option value="gemini" <?php selected( $api_provider, 'gemini' ); ?>>Google Gemini</option>
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

                <h2><?php _e( 'SEO Settings', 'localseo-booster' ); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="business_name"><?php _e( 'Business Name', 'localseo-booster' ); ?></label>
                        </th>
                        <td>
                            <input type="text" name="business_name" id="business_name" value="<?php echo esc_attr( $business_name ); ?>" class="regular-text" />
                            <p class="description"><?php _e( 'Used in Schema.org structured data. Defaults to site name if empty.', 'localseo-booster' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="business_phone"><?php _e( 'Business Phone', 'localseo-booster' ); ?></label>
                        </th>
                        <td>
                            <input type="text" name="business_phone" id="business_phone" value="<?php echo esc_attr( $business_phone ); ?>" class="regular-text" />
                            <p class="description"><?php _e( 'Added to Schema.org LocalBusiness/Service markup.', 'localseo-booster' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="response_time"><?php _e( 'Response Time (minutes)', 'localseo-booster' ); ?></label>
                        </th>
                        <td>
                            <input type="text" name="response_time" id="response_time" value="<?php echo esc_attr( $response_time ); ?>" class="small-text" />
                            <p class="description"><?php _e( 'Typical response time shown on landing pages, e.g. "60". Used in the mastertemplate CTA section.', 'localseo-booster' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="customer_count_text"><?php _e( 'Customers Served (text)', 'localseo-booster' ); ?></label>
                        </th>
                        <td>
                            <input type="text" name="customer_count_text" id="customer_count_text" value="<?php echo esc_attr( $customer_count_text ); ?>" class="regular-text" />
                            <p class="description"><?php _e( 'Number shown in social-proof section, e.g. "200+" or "over 500". Leave blank to hide the sentence.', 'localseo-booster' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="og_image"><?php _e( 'Default OG Image URL', 'localseo-booster' ); ?></label>
                        </th>
                        <td>
                            <input type="url" name="og_image" id="og_image" value="<?php echo esc_url( $og_image ); ?>" class="regular-text" />
                            <p class="description"><?php _e( 'Fallback image for Open Graph and Twitter Card meta tags.', 'localseo-booster' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="schema_type"><?php _e( 'Schema Type', 'localseo-booster' ); ?></label>
                        </th>
                        <td>
                            <select name="schema_type" id="schema_type" class="regular-text">
                                <option value="LocalBusiness" <?php selected( $schema_type, 'LocalBusiness' ); ?>><?php _e( 'LocalBusiness', 'localseo-booster' ); ?></option>
                                <option value="Service" <?php selected( $schema_type, 'Service' ); ?>><?php _e( 'Service', 'localseo-booster' ); ?></option>
                                <option value="ProfessionalService" <?php selected( $schema_type, 'ProfessionalService' ); ?>><?php _e( 'ProfessionalService', 'localseo-booster' ); ?></option>
                                <option value="HomeAndConstructionBusiness" <?php selected( $schema_type, 'HomeAndConstructionBusiness' ); ?>><?php _e( 'HomeAndConstructionBusiness', 'localseo-booster' ); ?></option>
                            </select>
                            <p class="description"><?php _e( 'Schema.org type used for JSON-LD structured data.', 'localseo-booster' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="robots"><?php _e( 'Robots Meta', 'localseo-booster' ); ?></label>
                        </th>
                        <td>
                            <select name="robots" id="robots" class="regular-text">
                                <option value="index, follow" <?php selected( $robots, 'index, follow' ); ?>><?php _e( 'index, follow (default)', 'localseo-booster' ); ?></option>
                                <option value="noindex, follow" <?php selected( $robots, 'noindex, follow' ); ?>><?php _e( 'noindex, follow', 'localseo-booster' ); ?></option>
                                <option value="index, nofollow" <?php selected( $robots, 'index, nofollow' ); ?>><?php _e( 'index, nofollow', 'localseo-booster' ); ?></option>
                                <option value="noindex, nofollow" <?php selected( $robots, 'noindex, nofollow' ); ?>><?php _e( 'noindex, nofollow', 'localseo-booster' ); ?></option>
                            </select>
                            <p class="description"><?php _e( 'Robots meta tag applied to all LocalSEO virtual pages.', 'localseo-booster' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e( 'Structured Data', 'localseo-booster' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="schema_enabled" id="schema_enabled" value="1" <?php checked( $schema_enabled, '1' ); ?> />
                                <?php _e( 'Enable JSON-LD Schema.org markup on virtual pages', 'localseo-booster' ); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e( 'XML Sitemap', 'localseo-booster' ); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="sitemap_enabled" id="sitemap_enabled" value="1" <?php checked( $sitemap_enabled, '1' ); ?> />
                                <?php _e( 'Include LocalSEO pages in the WordPress XML sitemap', 'localseo-booster' ); ?>
                            </label>
                            <p class="description"><?php printf( __( 'Sitemap index: <a href="%s" target="_blank">%s</a>', 'localseo-booster' ), esc_url( home_url( '/wp-sitemap.xml' ) ), esc_html( home_url( '/wp-sitemap.xml' ) ) ); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button( __( 'Save Settings', 'localseo-booster' ) ); ?>
            </form>
        </div>
        <?php
    }
}
