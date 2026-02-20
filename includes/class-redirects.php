<?php
/**
 * Redirects Class
 *
 * Manages 301/302 URL redirects for SEO purposes.
 * Stores redirect rules in a custom DB table and processes
 * them on incoming frontend requests.
 */

namespace LocalSEO;

class Redirects {

    /** @var string DB table name. */
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'localseo_redirects';

        add_action( 'template_redirect', [ $this, 'process_redirect' ], 1 );
        add_action( 'admin_menu', [ $this, 'add_submenu' ], 20 );
    }

    /**
     * Register the admin submenu page.
     */
    public function add_submenu() {
        add_submenu_page(
            'localseo-booster',
            __( 'Redirects', 'localseo-booster' ),
            __( 'Redirects', 'localseo-booster' ),
            'manage_options',
            'localseo-redirects',
            [ $this, 'render_page' ]
        );
    }

    /**
     * Process an incoming request against stored redirect rules.
     *
     * Fires on template_redirect (priority 1) so it runs before any other
     * template logic. Skips admin and REST API requests.
     */
    public function process_redirect() {
        if ( is_admin() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
            return;
        }

        $request_uri  = $_SERVER['REQUEST_URI'] ?? '';
        $request_path = (string) parse_url( $request_uri, PHP_URL_PATH );
        if ( '' === $request_path ) {
            return;
        }

        // Normalize: ensure a leading slash.
        $request_path = '/' . ltrim( $request_path, '/' );

        $redirects = $this->get_all_cached();

        foreach ( $redirects as $redirect ) {
            $source = '/' . ltrim( (string) $redirect->source_url, '/' );

            // Match with and without trailing slash.
            if ( rtrim( $source, '/' ) === rtrim( $request_path, '/' ) ) {
                $this->increment_hits( (int) $redirect->id );

                $code = in_array( (int) $redirect->redirect_type, [ 301, 302 ], true )
                    ? (int) $redirect->redirect_type
                    : 301;

                wp_redirect( esc_url_raw( $redirect->target_url ), $code );
                exit;
            }
        }
    }

    // -------------------------------------------------------------------------
    // DB helpers
    // -------------------------------------------------------------------------

    /**
     * Return all redirect rules, using a one-hour transient cache.
     *
     * @return array
     */
    private function get_all_cached() {
        $cached = get_transient( 'localseo_redirects_cache' );
        if ( false !== $cached ) {
            return $cached;
        }

        global $wpdb;
        // Select only the columns needed for redirect processing.
        // Table name is safe — set from wpdb->prefix in constructor.
        $rows = $wpdb->get_results( "SELECT id, source_url, target_url, redirect_type FROM {$this->table_name} ORDER BY id ASC" );
        set_transient( 'localseo_redirects_cache', $rows, HOUR_IN_SECONDS );
        return $rows;
    }

    /** Invalidate the redirects transient cache. */
    private function clear_cache() {
        delete_transient( 'localseo_redirects_cache' );
    }

    /**
     * Increment the hit counter for a redirect rule.
     *
     * @param int $id Redirect ID.
     */
    private function increment_hits( $id ) {
        global $wpdb;
        $wpdb->query( $wpdb->prepare(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            "UPDATE {$this->table_name} SET hits = hits + 1 WHERE id = %d",
            $id
        ) );
    }

    /**
     * Insert a new redirect rule.
     *
     * @param string $source Source path (e.g. /old-page/).
     * @param string $target Target URL.
     * @param int    $type   301 or 302.
     * @return int|false Inserted ID or false on failure.
     */
    public function insert( $source, $target, $type = 301 ) {
        global $wpdb;

        $inserted = $wpdb->insert(
            $this->table_name,
            [
                'source_url'    => $source,
                'target_url'    => $target,
                'redirect_type' => $type,
                'hits'          => 0,
            ],
            [ '%s', '%s', '%d', '%d' ]
        );

        $this->clear_cache();
        return $inserted ? $wpdb->insert_id : false;
    }

    /**
     * Delete a redirect rule by ID.
     *
     * @param int $id
     * @return bool
     */
    public function delete( $id ) {
        global $wpdb;

        $result = $wpdb->delete(
            $this->table_name,
            [ 'id' => $id ],
            [ '%d' ]
        ) !== false;

        $this->clear_cache();
        return $result;
    }

    /**
     * Return all redirect rules ordered by most recently created.
     *
     * @return array
     */
    public function get_all() {
        global $wpdb;
        // Table name is safe — set from wpdb->prefix in constructor.
        return $wpdb->get_results( "SELECT id, source_url, target_url, redirect_type, hits, created_at FROM {$this->table_name} ORDER BY id DESC" );
    }

    // -------------------------------------------------------------------------
    // Admin page
    // -------------------------------------------------------------------------

    /**
     * Render the Redirect Manager admin page.
     */
    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'localseo-booster' ) );
        }

        $message      = '';
        $message_type = 'success';

        if ( isset( $_POST['localseo_redirect_nonce'] ) ) {
            if ( ! wp_verify_nonce(
                (string) ( $_POST['localseo_redirect_nonce'] ?? '' ),
                'localseo_redirect_action'
            ) ) {
                wp_die( __( 'Security check failed.', 'localseo-booster' ) );
            }

            $action = sanitize_text_field( $_POST['redirect_action'] ?? '' );

            if ( 'add' === $action ) {
                $source = sanitize_text_field( $_POST['source_url'] ?? '' );
                $target = esc_url_raw( $_POST['target_url'] ?? '' );
                $type   = in_array( (int) ( $_POST['redirect_type'] ?? 301 ), [ 301, 302 ], true )
                    ? (int) $_POST['redirect_type']
                    : 301;

                if ( $source && $target ) {
                    $this->insert( $source, $target, $type );
                    $message = __( 'Redirect added successfully.', 'localseo-booster' );
                } else {
                    $message      = __( 'Please enter both a source path and a target URL.', 'localseo-booster' );
                    $message_type = 'error';
                }
            } elseif ( 'delete' === $action ) {
                $id = (int) ( $_POST['redirect_id'] ?? 0 );
                if ( $id ) {
                    $this->delete( $id );
                    $message = __( 'Redirect deleted.', 'localseo-booster' );
                }
            }
        }

        $redirects = $this->get_all();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Redirect Manager', 'localseo-booster' ); ?></h1>
            <p><?php esc_html_e( 'Manage 301/302 URL redirects. Enter paths relative to your site root (e.g. /old-page/).', 'localseo-booster' ); ?></p>

            <?php if ( $message ) : ?>
                <div class="notice notice-<?php echo esc_attr( $message_type ); ?> is-dismissible">
                    <p><?php echo esc_html( $message ); ?></p>
                </div>
            <?php endif; ?>

            <h2><?php esc_html_e( 'Add New Redirect', 'localseo-booster' ); ?></h2>
            <form method="post" action="">
                <?php wp_nonce_field( 'localseo_redirect_action', 'localseo_redirect_nonce' ); ?>
                <input type="hidden" name="redirect_action" value="add" />
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="source_url"><?php esc_html_e( 'Source Path', 'localseo-booster' ); ?></label>
                        </th>
                        <td>
                            <input type="text" id="source_url" name="source_url" class="regular-text" placeholder="/old-page/" />
                            <p class="description">
                                <?php esc_html_e( 'Relative path to redirect FROM, e.g. /old-page/', 'localseo-booster' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="target_url"><?php esc_html_e( 'Target URL', 'localseo-booster' ); ?></label>
                        </th>
                        <td>
                            <input type="url" id="target_url" name="target_url" class="regular-text" placeholder="https://example.com/new-page/" />
                            <p class="description">
                                <?php esc_html_e( 'Full URL to redirect TO.', 'localseo-booster' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="redirect_type"><?php esc_html_e( 'Redirect Type', 'localseo-booster' ); ?></label>
                        </th>
                        <td>
                            <select name="redirect_type" id="redirect_type">
                                <option value="301"><?php esc_html_e( '301 – Permanent (recommended for SEO)', 'localseo-booster' ); ?></option>
                                <option value="302"><?php esc_html_e( '302 – Temporary', 'localseo-booster' ); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>
                <?php submit_button( __( 'Add Redirect', 'localseo-booster' ) ); ?>
            </form>

            <h2>
                <?php esc_html_e( 'Existing Redirects', 'localseo-booster' ); ?>
                <span class="title-count">(<?php echo count( $redirects ); ?>)</span>
            </h2>

            <?php if ( $redirects ) : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Source Path', 'localseo-booster' ); ?></th>
                        <th><?php esc_html_e( 'Target URL', 'localseo-booster' ); ?></th>
                        <th style="width:80px"><?php esc_html_e( 'Type', 'localseo-booster' ); ?></th>
                        <th style="width:60px"><?php esc_html_e( 'Hits', 'localseo-booster' ); ?></th>
                        <th style="width:90px"><?php esc_html_e( 'Actions', 'localseo-booster' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $redirects as $redirect ) : ?>
                    <tr>
                        <td><code><?php echo esc_html( $redirect->source_url ); ?></code></td>
                        <td>
                            <a href="<?php echo esc_url( $redirect->target_url ); ?>" target="_blank" rel="noopener noreferrer">
                                <?php echo esc_html( $redirect->target_url ); ?>
                            </a>
                        </td>
                        <td><?php echo (int) $redirect->redirect_type; ?></td>
                        <td><?php echo (int) $redirect->hits; ?></td>
                        <td>
                            <form method="post" action="" style="display:inline">
                                <?php wp_nonce_field( 'localseo_redirect_action', 'localseo_redirect_nonce' ); ?>
                                <input type="hidden" name="redirect_action" value="delete" />
                                <input type="hidden" name="redirect_id" value="<?php echo (int) $redirect->id; ?>" />
                                <button
                                    type="submit"
                                    class="button-link button-link-delete"
                                    onclick="return confirm('<?php echo esc_js( __( 'Delete this redirect?', 'localseo-booster' ) ); ?>')"
                                ><?php esc_html_e( 'Delete', 'localseo-booster' ); ?></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else : ?>
                <p><?php esc_html_e( 'No redirects configured yet.', 'localseo-booster' ); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }
}
