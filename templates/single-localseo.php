<?php
/**
 * Template for LocalSEO pages (fallback if no FSE template exists)
 */

get_header();

$data = \LocalSEO\Router::get_current_data();

if ( $data ) : ?>

<article class="localseo-page">
    <header class="entry-header">
        <h1 class="entry-title"><?php echo esc_html( $data->service_keyword . ' in ' . $data->city ); ?></h1>
    </header>

    <div class="entry-content">
        <?php if ( ! empty( $data->ai_generated_intro ) ) : ?>
            <div class="localseo-intro">
                <?php echo wp_kses_post( wpautop( $data->ai_generated_intro ) ); ?>
            </div>
        <?php endif; ?>

        <div class="localseo-details">
            <p><strong><?php _e( 'Service:', 'localseo-booster' ); ?></strong> <?php echo esc_html( $data->service_keyword ); ?></p>
            <p><strong><?php _e( 'City:', 'localseo-booster' ); ?></strong> <?php echo esc_html( $data->city ); ?></p>
            <?php if ( ! empty( $data->zip ) ) : ?>
                <p><strong><?php _e( 'ZIP:', 'localseo-booster' ); ?></strong> <?php echo esc_html( $data->zip ); ?></p>
            <?php endif; ?>
        </div>
    </div>
</article>

<?php else : ?>

<article class="localseo-page">
    <div class="entry-content">
        <p><?php _e( 'Page not found.', 'localseo-booster' ); ?></p>
    </div>
</article>

<?php endif;

get_footer();
