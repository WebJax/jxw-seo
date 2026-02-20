<?php
/**
 * Master Template for LocalSEO landing pages (fallback if no FSE template exists)
 *
 * Sections:
 *  1. Hero (H1)       – Service + City tagline with ZIP and nearby areas
 *  2. Local Relevance – Unique local content (landmarks + AI intro)
 *  3. Service List    – What we do (H2)
 *  4. Social Proof    – Trust signals (H2)
 *  5. Call-to-Action  – Phone / contact button (H2)
 */

get_header();

$data = \LocalSEO\Router::get_current_data();

if ( ! $data ) :
    status_header( 404 );
    ?>
    <article class="localseo-page localseo-not-found">
        <div class="entry-content">
            <p><?php _e( 'Page not found.', 'localseo-booster' ); ?></p>
        </div>
    </article>
    <?php
    get_footer();
    return;
endif;

// ── Row data ──────────────────────────────────────────────────────────────────
$service    = esc_html( $data->service_keyword );
$city       = esc_html( $data->city );
$zip        = esc_html( $data->zip ?? '' );
$intro_html = ! empty( $data->ai_generated_intro )
    ? wp_kses_post( wpautop( $data->ai_generated_intro ) )
    : '';
$landmarks  = esc_html( $data->local_landmarks ?? '' );

// Parse nearby cities from comma-separated string
$nearby_raw   = $data->nearby_cities ?? '';
$nearby_array = array_values( array_filter( array_map( 'trim', explode( ',', $nearby_raw ) ) ) );

// ── Site settings ─────────────────────────────────────────────────────────────
$phone         = esc_html( get_option( 'localseo_business_phone', '' ) );
$response_time = esc_html( get_option( 'localseo_response_time', '60' ) );
$customer_text = esc_html( get_option( 'localseo_customer_count_text', '' ) );
?>

<article class="localseo-page localseo-mastertemplate" itemscope itemtype="https://schema.org/Service">

    <!-- ═══════════════════════════════════════════════════════════════════════
         1. HERO SECTION
         ══════════════════════════════════════════════════════════════════════ -->
    <section class="localseo-hero">
        <div class="localseo-hero__inner">

            <h1 class="localseo-hero__title" itemprop="name">
                <?php
                /* translators: 1: service keyword, 2: city name */
                printf( esc_html__( '%1$s i %2$s – Hurtig hjælp og lokale eksperter', 'localseo-booster' ), $service, $city );
                ?>
            </h1>

            <p class="localseo-hero__subtitle">
                <?php if ( $zip ) :
                    /* translators: 1: service keyword, 2: zip code, 3: city name */
                    printf(
                        esc_html__( 'Vi rykker ud til %1$s-opgaver i hele %2$s %3$s', 'localseo-booster' ),
                        $service, $zip, $city
                    );
                    if ( ! empty( $nearby_array ) ) :
                        echo ', ';
                        /* translators: %s: comma-separated list of nearby areas */
                        printf( esc_html__( 'samt lokalområder som %s', 'localseo-booster' ), esc_html( implode( ', ', $nearby_array ) ) );
                    endif;
                    echo '.';
                else :
                    /* translators: 1: service keyword, 2: city name */
                    printf( esc_html__( 'Vi rykker ud til %1$s-opgaver i %2$s og omegn.', 'localseo-booster' ), $service, $city );
                endif; ?>
            </p>

            <?php if ( $phone ) : ?>
            <a class="localseo-btn localseo-btn--primary" href="tel:<?php echo esc_attr( preg_replace( '/[^+\d]/', '', $phone ) ); ?>">
                <?php
                /* translators: 1: city name, 2: phone number */
                printf( esc_html__( 'Ring til din lokale ekspert i %1$s – %2$s', 'localseo-booster' ), $city, $phone );
                ?>
            </a>
            <?php endif; ?>

        </div>
    </section><!-- .localseo-hero -->

    <!-- ═══════════════════════════════════════════════════════════════════════
         2. LOCAL RELEVANCE
         ══════════════════════════════════════════════════════════════════════ -->
    <section class="localseo-local-relevance">
        <div class="localseo-section__inner">

            <h2>
                <?php
                /* translators: 1: service keyword, 2: city name */
                printf( esc_html__( 'Din lokale %1$s-ekspert i %2$s', 'localseo-booster' ), $service, $city );
                ?>
            </h2>

            <?php if ( $landmarks ) : ?>
            <p class="localseo-landmarks">
                <?php
                /* translators: 1: city name, 2: local landmarks description */
                printf( esc_html__( 'Uanset om du bor nær %2$s i %1$s, er vi klar til at hjælpe dig.', 'localseo-booster' ), $city, $landmarks );
                ?>
            </p>
            <?php endif; ?>

            <?php if ( $response_time ) : ?>
            <p class="localseo-response-time">
                <?php
                /* translators: 1: city name, 2: response time in minutes */
                printf(
                    esc_html__( 'Vi har ofte en vogn i nærheden og kan typisk være fremme i %1$s inden for %2$s minutter.', 'localseo-booster' ),
                    $city, $response_time
                );
                ?>
            </p>
            <?php endif; ?>

            <?php if ( $intro_html ) : ?>
            <div class="localseo-intro" itemprop="description">
                <?php echo $intro_html; // Already escaped via wp_kses_post ?>
            </div>
            <?php endif; ?>

            <?php if ( ! empty( $nearby_array ) ) : ?>
            <p class="localseo-nearby-links">
                <strong><?php _e( 'Vi kører også i:', 'localseo-booster' ); ?></strong>
                <?php
                $links = array_map( 'esc_html', $nearby_array );
                echo implode( ', ', $links );
                echo '.';
                ?>
            </p>
            <?php endif; ?>

        </div>
    </section><!-- .localseo-local-relevance -->

    <!-- ═══════════════════════════════════════════════════════════════════════
         3. SERVICE DESCRIPTION
         ══════════════════════════════════════════════════════════════════════ -->
    <section class="localseo-services">
        <div class="localseo-section__inner">

            <h2>
                <?php
                /* translators: 1: service keyword, 2: city name */
                printf( esc_html__( 'Vores %1$s-ydelser i %2$s', 'localseo-booster' ), $service, $city );
                ?>
            </h2>

            <ul class="localseo-services__list">
                <li>
                    <?php
                    /* translators: 1: service keyword, 2: city name */
                    printf( esc_html__( '%1$s i %2$s', 'localseo-booster' ), $service, $city );
                    ?>
                </li>
                <li><?php _e( 'TV-inspektion og fejlfinding', 'localseo-booster' ); ?></li>
                <li><?php _e( 'Akut hjælp – vi er klar døgnet rundt', 'localseo-booster' ); ?></li>
                <li>
                    <?php
                    /* translators: 1: service keyword */
                    printf( esc_html__( 'Forebyggende vedligehold og service (%1$s)', 'localseo-booster' ), $service );
                    ?>
                </li>
                <?php if ( ! empty( $nearby_array ) ) : ?>
                <li>
                    <?php
                    /* translators: 1: city name, 2: comma-separated list of nearby areas */
                    printf(
                        esc_html__( 'Dækkende %1$s og oplandsbyer: %2$s', 'localseo-booster' ),
                        $city,
                        esc_html( implode( ', ', $nearby_array ) )
                    );
                    ?>
                </li>
                <?php endif; ?>
            </ul>

        </div>
    </section><!-- .localseo-services -->

    <!-- ═══════════════════════════════════════════════════════════════════════
         4. SOCIAL PROOF & TRUST
         ══════════════════════════════════════════════════════════════════════ -->
    <section class="localseo-social-proof">
        <div class="localseo-section__inner">

            <h2>
                <?php
                /* translators: 1: city name */
                printf( esc_html__( 'Tillid og erfaring i %s', 'localseo-booster' ), $city );
                ?>
            </h2>

            <?php if ( $customer_text ) : ?>
            <p class="localseo-customer-count">
                <?php
                /* translators: 1: customer count text, 2: city name */
                printf(
                    esc_html__( 'Vi har hjulpet %1$s husstande i %2$s og omegn med deres opgaver.', 'localseo-booster' ),
                    $customer_text, $city
                );
                ?>
            </p>
            <?php endif; ?>

            <p class="localseo-trust-text">
                <?php
                /* translators: 1: service keyword */
                printf(
                    esc_html__( 'Som lokal %1$s-virksomhed sætter vi din tilfredshed øverst. Vi er certificerede, forsikrede og arbejder kun med kvalitetsmaterialer.', 'localseo-booster' ),
                    $service
                );
                ?>
            </p>

        </div>
    </section><!-- .localseo-social-proof -->

    <!-- ═══════════════════════════════════════════════════════════════════════
         5. CALL TO ACTION
         ══════════════════════════════════════════════════════════════════════ -->
    <section class="localseo-cta">
        <div class="localseo-section__inner">

            <h2>
                <?php
                /* translators: 1: service keyword, 2: city name */
                printf( esc_html__( 'Kontakt din lokale %1$s i %2$s', 'localseo-booster' ), $service, $city );
                ?>
            </h2>

            <?php if ( $phone ) : ?>
            <p>
                <?php
                /* translators: 1: city name */
                printf( esc_html__( 'Ring til os i dag – vi er klar til at hjælpe dig i %s.', 'localseo-booster' ), $city );
                ?>
            </p>
            <a class="localseo-btn localseo-btn--primary localseo-btn--large"
               href="tel:<?php echo esc_attr( preg_replace( '/[^+\d]/', '', $phone ) ); ?>">
                <?php
                /* translators: 1: service keyword, 2: city name, 3: phone number */
                printf( esc_html__( 'Ring til din lokale %1$s i %2$s på %3$s', 'localseo-booster' ), $service, $city, $phone );
                ?>
            </a>
            <?php else : ?>
            <p><?php _e( 'Kontakt os for et uforpligtende tilbud.', 'localseo-booster' ); ?></p>
            <?php endif; ?>

        </div>
    </section><!-- .localseo-cta -->

</article><!-- .localseo-mastertemplate -->

<?php get_footer();
