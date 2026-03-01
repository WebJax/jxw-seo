<?php
/**
 * Meta Box Class
 *
 * Adds an SEO metabox (meta title, meta description, OG image, canonical URL,
 * robots directive, and JSON-LD schema type) to every public post type so that
 * existing WordPress pages and posts get full SEO coverage.
 */

namespace LocalSEO;

class Meta_Box {

    /** Allowed robots values (empty string = use site default). */
    private const ALLOWED_ROBOTS = [
        ''                   => 'Site default',
        'index, follow'      => 'index, follow',
        'noindex, follow'    => 'noindex, follow',
        'index, nofollow'    => 'index, nofollow',
        'noindex, nofollow'  => 'noindex, nofollow',
    ];

    /** Allowed schema types for regular posts/pages. */
    private const ALLOWED_SCHEMA_TYPES = [
        ''               => '— None (no schema output) —',
        'WebPage'        => 'WebPage',
        'Article'        => 'Article',
        'BlogPosting'    => 'BlogPosting',
        'FAQPage'        => 'FAQPage',
        'AboutPage'      => 'AboutPage',
        'ContactPage'    => 'ContactPage',
        'LocalBusiness'  => 'LocalBusiness',
        'Service'        => 'Service',
        'ProfessionalService'          => 'ProfessionalService',
        'HomeAndConstructionBusiness'  => 'HomeAndConstructionBusiness',
    ];

    public function __construct() {
        add_action( 'add_meta_boxes', [ $this, 'register_meta_boxes' ] );
        add_action( 'save_post', [ $this, 'save_meta' ], 10, 2 );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_metabox_script' ] );
    }

    /**
     * Enqueue the metabox JavaScript on post edit screens.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_metabox_script( $hook ) {
        if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) ) {
            return;
        }

        wp_register_script( 'localseo-metabox', false, [ 'wp-api-fetch' ], LOCALSEO_VERSION, true );
        wp_enqueue_script( 'localseo-metabox' );
        wp_add_inline_script( 'localseo-metabox', $this->get_metabox_script() );
    }

    /**
     * Returns the inline JS for the metabox character-count bars and AI generation.
     *
     * @return string
     */
    private function get_metabox_script() {
        return '(function () {
    function lseoBar(inputId, barId, countId, recommended) {
        var input = document.getElementById(inputId);
        if (!input) return;
        input.addEventListener("input", function () {
            var len = this.value.length;
            var pct = Math.min(100, Math.round(len / recommended * 100));
            document.getElementById(barId).style.width = pct + "%";
            document.getElementById(countId).textContent =
                len + " / " + recommended + " recommended characters";
        });
    }
    lseoBar("localseo_meta_title",       "lseo-title-bar", "lseo-title-count", 60);
    lseoBar("localseo_meta_description", "lseo-desc-bar",  "lseo-desc-count",  155);

    var aiBtn = document.getElementById("lseo-generate-ai-btn");
    if (aiBtn) {
        aiBtn.addEventListener("click", function () {
            var btn = this;
            var postId = btn.getAttribute("data-post-id");
            var original = btn.textContent;
            btn.disabled = true;
            btn.textContent = "Generating\u2026";
            wp.apiFetch({
                path: "/localseo/v1/generate-ai-post/" + postId,
                method: "POST",
            }).then(function (data) {
                var titleEl = document.getElementById("localseo_meta_title");
                var descEl  = document.getElementById("localseo_meta_description");
                var selEl   = document.getElementById("localseo_schema_type");
                if (titleEl && data.meta_title) {
                    titleEl.value = data.meta_title;
                    titleEl.dispatchEvent(new Event("input"));
                }
                if (descEl && data.meta_description) {
                    descEl.value = data.meta_description;
                    descEl.dispatchEvent(new Event("input"));
                }
                if (selEl && data.schema_type) {
                    var found = false;
                    for (var i = 0; i < selEl.options.length; i++) {
                        if (selEl.options[i].value === data.schema_type) {
                            selEl.value = data.schema_type;
                            found = true;
                            break;
                        }
                    }
                    if (!found) {
                        console.warn("LocalSEO: AI suggested schema type \"" + data.schema_type + "\" is not in the dropdown.");
                    }
                }
                btn.textContent = "Generated \u2713";
                btn.disabled = false;
                setTimeout(function () { btn.textContent = original; }, 3000);
            }).catch(function (err) {
                btn.textContent = (err && err.message) ? err.message : "Error \u2013 retry";
                btn.disabled = false;
                setTimeout(function () { btn.textContent = original; }, 4000);
            });
        });
    }
})();';
    }

    /**
     * Register the SEO metabox on all public post types (excluding attachments).
     */
    public function register_meta_boxes() {
        $post_types = get_post_types( [ 'public' => true ], 'names' );
        unset( $post_types['attachment'] );

        foreach ( $post_types as $post_type ) {
            add_meta_box(
                'localseo-seo-metabox',
                __( 'SEO Settings (LocalSEO Booster)', 'localseo-booster' ),
                [ $this, 'render_meta_box' ],
                $post_type,
                'normal',
                'high'
            );
        }
    }

    /**
     * Render the SEO metabox HTML.
     *
     * @param \WP_Post $post The current post.
     */
    public function render_meta_box( $post ) {
        wp_nonce_field( 'localseo_save_meta_' . $post->ID, 'localseo_meta_nonce' );

        $meta_title       = (string) get_post_meta( $post->ID, '_localseo_meta_title', true );
        $meta_description = (string) get_post_meta( $post->ID, '_localseo_meta_description', true );
        $robots           = (string) get_post_meta( $post->ID, '_localseo_robots', true );
        $og_image         = (string) get_post_meta( $post->ID, '_localseo_og_image', true );
        $canonical        = (string) get_post_meta( $post->ID, '_localseo_canonical', true );
        $schema_type      = (string) get_post_meta( $post->ID, '_localseo_schema_type', true );

        $title_len = mb_strlen( $meta_title );
        $desc_len  = mb_strlen( $meta_description );
        ?>
        <style>
            .localseo-mb table { width: 100%; border-collapse: collapse; }
            .localseo-mb th { width: 170px; text-align: left; padding: 8px 12px 8px 0; vertical-align: top; font-weight: 600; }
            .localseo-mb td { padding: 6px 0 10px; }
            .localseo-mb input[type="text"],
            .localseo-mb input[type="url"],
            .localseo-mb textarea,
            .localseo-mb select { width: 100%; box-sizing: border-box; }
            .localseo-mb .lseo-bar-wrap { height: 6px; border-radius: 3px; background: #ddd; margin-top: 4px; }
            .localseo-mb .lseo-bar { display: block; height: 100%; border-radius: 3px; background: #2271b1; transition: width .15s; }
            .localseo-mb .lseo-count { font-size: 11px; color: #757575; margin-top: 3px; }
        </style>

        <div class="localseo-mb">
            <table>
                <tr>
                    <th><label for="localseo_meta_title"><?php esc_html_e( 'Meta Title', 'localseo-booster' ); ?></label></th>
                    <td>
                        <input type="text" id="localseo_meta_title" name="localseo_meta_title"
                               maxlength="60" value="<?php echo esc_attr( $meta_title ); ?>"
                               placeholder="<?php echo esc_attr( get_the_title( $post ) ); ?>" />
                        <div class="lseo-bar-wrap"><span class="lseo-bar" id="lseo-title-bar"
                             style="width:<?php echo esc_attr( min( 100, (int) round( $title_len / 60 * 100 ) ) ); ?>%"></span></div>
                        <p class="lseo-count" id="lseo-title-count">
                            <?php printf( esc_html__( '%d / 60 recommended characters', 'localseo-booster' ), $title_len ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th><label for="localseo_meta_description"><?php esc_html_e( 'Meta Description', 'localseo-booster' ); ?></label></th>
                    <td>
                        <textarea id="localseo_meta_description" name="localseo_meta_description"
                                  rows="3" maxlength="320"><?php echo esc_textarea( $meta_description ); ?></textarea>
                        <div class="lseo-bar-wrap"><span class="lseo-bar" id="lseo-desc-bar"
                             style="width:<?php echo esc_attr( min( 100, (int) round( $desc_len / 155 * 100 ) ) ); ?>%"></span></div>
                        <p class="lseo-count" id="lseo-desc-count">
                            <?php printf( esc_html__( '%d / 155 recommended characters', 'localseo-booster' ), $desc_len ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th><label for="localseo_og_image"><?php esc_html_e( 'OG Image URL', 'localseo-booster' ); ?></label></th>
                    <td>
                        <input type="url" id="localseo_og_image" name="localseo_og_image"
                               value="<?php echo esc_url( $og_image ); ?>" placeholder="https://" />
                        <p class="description"><?php esc_html_e( 'Overrides the default OG image for this post.', 'localseo-booster' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="localseo_canonical"><?php esc_html_e( 'Canonical URL', 'localseo-booster' ); ?></label></th>
                    <td>
                        <input type="url" id="localseo_canonical" name="localseo_canonical"
                               value="<?php echo esc_url( $canonical ); ?>"
                               placeholder="<?php echo esc_attr( (string) get_permalink( $post ) ); ?>" />
                        <p class="description"><?php esc_html_e( 'Leave blank to use the post permalink.', 'localseo-booster' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="localseo_robots"><?php esc_html_e( 'Robots Meta', 'localseo-booster' ); ?></label></th>
                    <td>
                        <select id="localseo_robots" name="localseo_robots">
                            <?php foreach ( self::ALLOWED_ROBOTS as $value => $label ) : ?>
                                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $robots, $value ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="localseo_schema_type"><?php esc_html_e( 'Schema Type', 'localseo-booster' ); ?></label></th>
                    <td>
                        <select id="localseo_schema_type" name="localseo_schema_type">
                            <?php foreach ( self::ALLOWED_SCHEMA_TYPES as $value => $label ) : ?>
                                <option value="<?php echo esc_attr( $value ); ?>" <?php selected( $schema_type, $value ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e( 'JSON-LD schema.org type output in the page head. Select "None" to disable.', 'localseo-booster' ); ?></p>
                    </td>
                </tr>
            </table>
            <?php if ( ! empty( get_option( 'localseo_api_key', '' ) ) ) : ?>
            <p style="margin-top:10px;">
                <button type="button" id="lseo-generate-ai-btn"
                        data-post-id="<?php echo esc_attr( $post->ID ); ?>"
                        class="button button-secondary">
                    <?php esc_html_e( 'Generate AI SEO', 'localseo-booster' ); ?>
                </button>
                <span class="description" style="margin-left:8px;">
                    <?php esc_html_e( 'Auto-fill meta title, description and schema type using AI.', 'localseo-booster' ); ?>
                </span>
            </p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Save SEO meta fields when a post is saved.
     *
     * @param int      $post_id Post ID.
     * @param \WP_Post $post    Post object.
     */
    public function save_meta( $post_id, $post ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! isset( $_POST['localseo_meta_nonce'] ) ||
             ! wp_verify_nonce( (string) ( $_POST['localseo_meta_nonce'] ?? '' ), 'localseo_save_meta_' . $post_id ) ) {
            return;
        }

        $post_type_obj = get_post_type_object( $post->post_type );
        if ( ! $post_type_obj || ! current_user_can( $post_type_obj->cap->edit_post, $post_id ) ) {
            return;
        }

        update_post_meta( $post_id, '_localseo_meta_title',
            sanitize_text_field( $_POST['localseo_meta_title'] ?? '' ) );

        update_post_meta( $post_id, '_localseo_meta_description',
            sanitize_textarea_field( $_POST['localseo_meta_description'] ?? '' ) );

        update_post_meta( $post_id, '_localseo_og_image',
            esc_url_raw( $_POST['localseo_og_image'] ?? '' ) );

        update_post_meta( $post_id, '_localseo_canonical',
            esc_url_raw( $_POST['localseo_canonical'] ?? '' ) );

        $allowed_robots = array_keys( self::ALLOWED_ROBOTS );
        $robots_raw     = sanitize_text_field( $_POST['localseo_robots'] ?? '' );
        update_post_meta( $post_id, '_localseo_robots',
            in_array( $robots_raw, $allowed_robots, true ) ? $robots_raw : '' );

        $allowed_schema = array_keys( self::ALLOWED_SCHEMA_TYPES );
        $schema_raw     = sanitize_text_field( $_POST['localseo_schema_type'] ?? '' );
        update_post_meta( $post_id, '_localseo_schema_type',
            in_array( $schema_raw, $allowed_schema, true ) ? $schema_raw : '' );
    }
}
