<?php
/**
 * Plugin Name: Cinetixx Movie Poster Slider
 * Plugin URI:  https://example.com
 * Description: Fetches movie data from the Cinetixx API and displays a poster slider via shortcode [cinetixx_slider].
 * Version:     1.0.0
 * Author:      Murrlichtspiele
 * Text Domain: cinetixx-slider
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ─── Constants ────────────────────────────────────────────────────────────────
define( 'CTX_SLIDER_VERSION', '1.0.0' );
define( 'CTX_SLIDER_PATH',    plugin_dir_path( __FILE__ ) );
define( 'CTX_SLIDER_URL',     plugin_dir_url( __FILE__ ) );

// ─── Settings page ───────────────────────────────────────────────────────────
add_action( 'admin_menu', 'ctx_slider_admin_menu' );
add_action( 'admin_init', 'ctx_slider_register_settings' );

function ctx_slider_admin_menu() {
    add_options_page(
        'Cinetixx Slider',
        'Cinetixx Slider',
        'manage_options',
        'cinetixx-slider',
        'ctx_slider_settings_page'
    );
}

function ctx_slider_register_settings() {
    register_setting( 'ctx_slider_options', 'ctx_slider_api_url', [
        'type'              => 'string',
        'sanitize_callback' => 'esc_url_raw',
        'default'           => 'https://api.cinetixx.de/Services/CinetixxService.asmx/GetShowInfo?mandatorID=2208234164&cinemaid=2209519384',
    ] );
    register_setting( 'ctx_slider_options', 'ctx_slider_cache_hours', [
        'type'              => 'integer',
        'sanitize_callback' => 'absint',
        'default'           => 2,
    ] );
    register_setting( 'ctx_slider_options', 'ctx_slider_days_ahead', [
        'type'              => 'integer',
        'sanitize_callback' => 'absint',
        'default'           => 14,
    ] );
}

function ctx_slider_settings_page() {
    $api_url     = get_option( 'ctx_slider_api_url', 'https://api.cinetixx.de/Services/CinetixxService.asmx/GetShowInfo?mandatorID=2208234164&cinemaid=2209519384' );
    $cache_hours = get_option( 'ctx_slider_cache_hours', 2 );
    $days_ahead  = get_option( 'ctx_slider_days_ahead', 14 );
    ?>
    <div class="wrap">
        <h1>Cinetixx Slider Einstellungen</h1>
        <form method="post" action="options.php">
            <?php settings_fields( 'ctx_slider_options' ); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="ctx_slider_api_url">API-URL</label></th>
                    <td>
                        <input type="url" id="ctx_slider_api_url" name="ctx_slider_api_url"
                               value="<?php echo esc_attr( $api_url ); ?>" class="regular-text" style="width:100%;max-width:700px;">
                        <p class="description">Cinetixx GetShowInfo URL inkl. mandatorID &amp; cinemaid.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="ctx_slider_cache_hours">Cache (Stunden)</label></th>
                    <td>
                        <input type="number" id="ctx_slider_cache_hours" name="ctx_slider_cache_hours"
                               value="<?php echo esc_attr( $cache_hours ); ?>" min="1" max="48" class="small-text">
                        <p class="description">Wie lange die API-Antwort zwischengespeichert wird.</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="ctx_slider_days_ahead">Vorschau-Zeitraum (Tage)</label></th>
                    <td>
                        <input type="number" id="ctx_slider_days_ahead" name="ctx_slider_days_ahead"
                               value="<?php echo esc_attr( $days_ahead ); ?>" min="1" max="60" class="small-text">
                        <p class="description">Vorstellungen der nächsten X Tage anzeigen (Standard: 14).</p>
                    </td>
                </tr>
            </table>
            <?php submit_button( 'Speichern' ); ?>
        </form>

        <hr>
        <h2>Shortcode</h2>
        <p>Füge <code>[cinetixx_slider]</code> in eine Seite oder einen Beitrag ein.</p>
        <p>Optional: <code>[cinetixx_slider days="14" slides="4"]</code></p>

        <hr>
        <h2>Cache leeren</h2>
        <?php
        if ( isset( $_POST['ctx_clear_cache'] ) && check_admin_referer( 'ctx_clear_cache_nonce' ) ) {
            delete_transient( 'ctx_slider_movies' );
            echo '<div class="notice notice-success"><p>Cache geleert!</p></div>';
        }
        ?>
        <form method="post">
            <?php wp_nonce_field( 'ctx_clear_cache_nonce' ); ?>
            <input type="hidden" name="ctx_clear_cache" value="1">
            <?php submit_button( 'Cache jetzt leeren', 'secondary' ); ?>
        </form>
    </div>
    <?php
}

// ─── Fetch & parse Cinetixx data ─────────────────────────────────────────────
function ctx_slider_get_movies( $days_ahead = null ) {
    if ( $days_ahead === null ) {
        $days_ahead = (int) get_option( 'ctx_slider_days_ahead', 14 );
    }

    $transient_key = 'ctx_slider_movies';
    $cached        = get_transient( $transient_key );

    if ( $cached !== false ) {
        // Filter cached data by the requested days_ahead (may differ from when cached)
        return ctx_slider_filter_by_days( $cached, $days_ahead );
    }

    $api_url = get_option(
        'ctx_slider_api_url',
        'https://api.cinetixx.de/Services/CinetixxService.asmx/GetShowInfo?mandatorID=2208234164&cinemaid=2209519384'
    );

    $response = wp_remote_get( $api_url, [
        'timeout' => 15,
        'headers' => [ 'Accept' => 'application/xml' ],
    ] );

    if ( is_wp_error( $response ) ) {
        return [];
    }

    $body = wp_remote_retrieve_body( $response );
    if ( empty( $body ) ) {
        return [];
    }

    // Suppress XML warnings for malformed data
    libxml_use_internal_errors( true );
    $xml = simplexml_load_string( $body );
    libxml_clear_errors();

    if ( ! $xml ) {
        return [];
    }

    $movies_map = []; // keyed by MOVIE_ID to deduplicate

    foreach ( $xml->Show as $show ) {
        $status = (string) $show['status'];
        if ( $status !== 'SHOW_ENABLED' ) {
            continue;
        }

        $movie_id   = (string) $show->MOVIE_ID;
        $show_begin = (string) $show->SHOW_BEGINNING;

        // Collect showtime for this screening
        $showtime_data = [
            'datetime'     => $show_begin,
            'booking_link' => (string) $show->BOOKING_LINK,
        ];

        if ( isset( $movies_map[ $movie_id ] ) ) {
            // Movie already seen — just add this showtime
            $movies_map[ $movie_id ]['showtimes'][] = $showtime_data;

            // Track earliest showtime
            if ( $show_begin < $movies_map[ $movie_id ]['earliest'] ) {
                $movies_map[ $movie_id ]['earliest'] = $show_begin;
            }
        } else {
            $movies_map[ $movie_id ] = [
                'movie_id'     => $movie_id,
                'title'        => (string) $show->VERANSTALTUNGSTITEL,
                'poster'       => (string) $show->ARTWORK_BIG,
                'genre'        => (string) $show->GENRE,
                'age_rating'   => (string) $show->ALTERSFREIGABE,
                'runtime'      => (int) $show->SPIELDAUER_EVENT,
                'director'     => (string) $show->DIRECTOR,
                'actors'       => (string) $show->ACTOR,
                'description'  => (string) $show->TEXT,
                'trailer'      => (string) $show->EVENT_TRAILER,
                'language'     => (string) $show->SPRACHVERSION,
                'is_3d'        => ( (string) $show->FLAG_3D === 'true' ),
                'earliest'     => $show_begin,
                'showtimes'    => [ $showtime_data ],
            ];
        }
    }

    // Sort showtimes within each movie and sort movies by earliest showtime
    foreach ( $movies_map as &$movie ) {
        usort( $movie['showtimes'], function( $a, $b ) {
            return strcmp( $a['datetime'], $b['datetime'] );
        } );
    }
    unset( $movie );

    $movies = array_values( $movies_map );
    usort( $movies, function( $a, $b ) {
        return strcmp( $a['earliest'], $b['earliest'] );
    } );

    // Cache the full dataset
    $cache_hours = (int) get_option( 'ctx_slider_cache_hours', 2 );
    set_transient( $transient_key, $movies, $cache_hours * HOUR_IN_SECONDS );

    return ctx_slider_filter_by_days( $movies, $days_ahead );
}

function ctx_slider_filter_by_days( $movies, $days_ahead ) {
    $now     = new DateTime( 'now', new DateTimeZone( 'Europe/Berlin' ) );
    $cutoff  = ( clone $now )->modify( "+{$days_ahead} days" );

    return array_filter( $movies, function( $movie ) use ( $now, $cutoff ) {
        // Check if any showtime is between now and cutoff
        foreach ( $movie['showtimes'] as $st ) {
            $dt = new DateTime( $st['datetime'] );
            if ( $dt >= $now && $dt <= $cutoff ) {
                return true;
            }
        }
        return false;
    } );
}

// ─── Enqueue assets only when shortcode is used ──────────────────────────────
function ctx_slider_enqueue_assets() {
    // Swiper.js from CDN
    wp_register_style(
        'swiper-css',
        'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css',
        [],
        '11.0.0'
    );
    wp_register_script(
        'swiper-js',
        'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js',
        [],
        '11.0.0',
        true
    );

    // Plugin styles
    wp_register_style(
        'cinetixx-slider-css',
        CTX_SLIDER_URL . 'assets/cinetixx-slider.css',
        [ 'swiper-css' ],
        CTX_SLIDER_VERSION
    );

    // Plugin script
    wp_register_script(
        'cinetixx-slider-js',
        CTX_SLIDER_URL . 'assets/cinetixx-slider.js',
        [ 'swiper-js' ],
        CTX_SLIDER_VERSION,
        true
    );
}
add_action( 'wp_enqueue_scripts', 'ctx_slider_enqueue_assets' );

// ─── Shortcode [cinetixx_slider] ─────────────────────────────────────────────
function ctx_slider_shortcode( $atts ) {
    $atts = shortcode_atts( [
        'days'   => null,
        'slides' => 4,
    ], $atts, 'cinetixx_slider' );

    $days_ahead = $atts['days'] ? (int) $atts['days'] : null;
    $movies     = ctx_slider_get_movies( $days_ahead );

    if ( empty( $movies ) ) {
        return '<p class="ctx-no-movies">Derzeit keine Vorstellungen geplant.</p>';
    }

    // Enqueue assets
    wp_enqueue_style( 'cinetixx-slider-css' );
    wp_enqueue_script( 'cinetixx-slider-js' );

    $slides_per_view = (int) $atts['slides'];

    // Pass config to JS
    wp_localize_script( 'cinetixx-slider-js', 'ctxSliderConfig', [
        'slidesPerView' => $slides_per_view,
    ] );

    ob_start();
    ?>
    <div class="ctx-slider-wrapper">
        <div class="ctx-slider-header">
            <h2 class="ctx-slider-title">Aktuelles Programm</h2>
            <div class="ctx-slider-nav">
                <button class="ctx-prev" aria-label="Zurück">&#10094;</button>
                <button class="ctx-next" aria-label="Weiter">&#10095;</button>
            </div>
        </div>

        <div class="swiper ctx-swiper">
            <div class="swiper-wrapper">
                <?php foreach ( $movies as $movie ) : ?>
                    <?php
                    // Collect upcoming showtimes for tooltip / overlay
                    $upcoming = [];
                    $now_ts   = time();
                    foreach ( $movie['showtimes'] as $st ) {
                        $ts = strtotime( $st['datetime'] );
                        if ( $ts >= $now_ts ) {
                            $upcoming[] = $st;
                        }
                    }
                    // First upcoming booking link
                    $first_link = ! empty( $upcoming ) ? $upcoming[0]['booking_link'] : '#';
                    ?>
                    <div class="swiper-slide ctx-slide">
                        <a href="<?php echo esc_url( $first_link ); ?>" target="_blank" rel="noopener"
                           class="ctx-poster-link">
                            <div class="ctx-poster-container">
                                <img src="<?php echo esc_url( $movie['poster'] ); ?>"
                                     alt="<?php echo esc_attr( $movie['title'] ); ?>"
                                     class="ctx-poster-img" loading="lazy">

                                <?php if ( $movie['is_3d'] ) : ?>
                                    <span class="ctx-badge ctx-badge-3d">3D</span>
                                <?php endif; ?>

                                <div class="ctx-overlay">
                                    <span class="ctx-genre"><?php echo esc_html( $movie['genre'] ); ?></span>
                                    <span class="ctx-meta">
                                        <?php echo esc_html( $movie['runtime'] ); ?> Min.
                                        &middot;
                                        <?php echo esc_html( $movie['age_rating'] ); ?>
                                    </span>
                                    <div class="ctx-showtimes">
                                        <?php
                                        // Group by date
                                        $by_date = [];
                                        foreach ( $upcoming as $st ) {
                                            $dt   = new DateTime( $st['datetime'] );
                                            $key  = $dt->format( 'D, d.m.' );
                                            $by_date[ $key ][] = [
                                                'time' => $dt->format( 'H:i' ),
                                                'link' => $st['booking_link'],
                                            ];
                                        }
                                        foreach ( $by_date as $date_label => $times ) :
                                        ?>
                                            <div class="ctx-showdate">
                                                <strong><?php echo esc_html( $date_label ); ?></strong>
                                                <?php foreach ( $times as $t ) : ?>
                                                    <a href="<?php echo esc_url( $t['link'] ); ?>"
                                                       class="ctx-time-link" target="_blank"
                                                       rel="noopener"
                                                       onclick="event.stopPropagation();">
                                                        <?php echo esc_html( $t['time'] ); ?>
                                                    </a>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <span class="ctx-cta">Tickets kaufen &rarr;</span>
                                </div>
                            </div>

                            <h3 class="ctx-movie-title"><?php echo esc_html( $movie['title'] ); ?></h3>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'cinetixx_slider', 'ctx_slider_shortcode' );
