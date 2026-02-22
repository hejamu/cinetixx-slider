<?php
/**
 * Plugin Name: Cinetixx Movie Poster Slider
 * Plugin URI:  https://example.com
 * Description: Fetches movie data from the Cinetixx API and displays a poster slider via shortcode [cinetixx_slider].
 * Version:     1.1.0
 * Author:      Murrlichtspiele
 * Text Domain: cinetixx-slider
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ─── Constants ────────────────────────────────────────────────────────────────
define( 'CTX_SLIDER_VERSION', '1.1.0' );
define( 'CTX_SLIDER_PATH',    plugin_dir_path( __FILE__ ) );
define( 'CTX_SLIDER_URL',     plugin_dir_url( __FILE__ ) );

// ─── Default values ──────────────────────────────────────────────────────────
function ctx_slider_defaults() {
    return [
        // API & Data
        'ctx_slider_api_url'          => 'https://api.cinetixx.de/Services/CinetixxService.asmx/GetShowInfo?mandatorID=2208234164&cinemaid=2209519384',
        'ctx_slider_api_timeout'      => 15,
        'ctx_slider_cache_hours'      => 2,
        'ctx_slider_days_ahead'       => 14,
        // Display
        'ctx_slider_title'            => 'Aktuelles Programm',
        'ctx_slider_slides_per_view'  => 4,
        'ctx_slider_no_movies_msg'    => 'Derzeit keine Vorstellungen geplant.',
        'ctx_slider_cta_text'         => 'Tickets kaufen →',
        'ctx_slider_link_new_tab'     => '1',
        'ctx_slider_loop'             => '0',
        'ctx_slider_show_titles'      => '1',
        'ctx_slider_show_3d_badge'    => '1',
        'ctx_slider_show_genre'       => '1',
        'ctx_slider_show_meta'        => '1',
        // Style
        'ctx_slider_max_width'        => 1200,
        'ctx_slider_border_radius'    => 10,
        'ctx_slider_accent_color'     => '#e63946',
        'ctx_slider_nav_color'        => '#333333',
    ];
}

// ─── Sanitize helpers ────────────────────────────────────────────────────────
function ctx_slider_sanitize_checkbox( $value ) {
    return $value ? '1' : '0';
}

function ctx_slider_sanitize_hex_color( $color ) {
    $color = trim( $color );
    if ( preg_match( '/^#([0-9a-fA-F]{3}){1,2}$/', $color ) ) {
        return $color;
    }
    return '';
}

function ctx_slider_sanitize_int_range( $value, $min, $max, $default ) {
    $value = (int) $value;
    if ( $value < $min || $value > $max ) {
        return $default;
    }
    return $value;
}

// ─── Settings registration ───────────────────────────────────────────────────
add_action( 'admin_menu', 'ctx_slider_admin_menu' );
add_action( 'admin_init', 'ctx_slider_register_settings' );
add_action( 'admin_enqueue_scripts', 'ctx_slider_admin_assets' );

function ctx_slider_admin_menu() {
    add_options_page(
        'Cinetixx Slider',
        'Cinetixx Slider',
        'manage_options',
        'cinetixx-slider',
        'ctx_slider_settings_page'
    );
}

function ctx_slider_admin_assets( $hook ) {
    if ( $hook !== 'settings_page_cinetixx-slider' ) {
        return;
    }
    wp_enqueue_style( 'wp-color-picker' );
    wp_enqueue_script( 'wp-color-picker' );
}

function ctx_slider_register_settings() {
    $defaults = ctx_slider_defaults();

    // ── API & Data ──
    register_setting( 'ctx_slider_options', 'ctx_slider_api_url', [
        'type'              => 'string',
        'sanitize_callback' => 'esc_url_raw',
        'default'           => $defaults['ctx_slider_api_url'],
    ] );
    register_setting( 'ctx_slider_options', 'ctx_slider_api_timeout', [
        'type'              => 'integer',
        'sanitize_callback' => function( $val ) {
            return ctx_slider_sanitize_int_range( $val, 5, 60, 15 );
        },
        'default'           => $defaults['ctx_slider_api_timeout'],
    ] );
    register_setting( 'ctx_slider_options', 'ctx_slider_cache_hours', [
        'type'              => 'integer',
        'sanitize_callback' => function( $val ) {
            return ctx_slider_sanitize_int_range( $val, 1, 48, 2 );
        },
        'default'           => $defaults['ctx_slider_cache_hours'],
    ] );
    register_setting( 'ctx_slider_options', 'ctx_slider_days_ahead', [
        'type'              => 'integer',
        'sanitize_callback' => function( $val ) {
            return ctx_slider_sanitize_int_range( $val, 1, 60, 14 );
        },
        'default'           => $defaults['ctx_slider_days_ahead'],
    ] );

    // ── Display ──
    register_setting( 'ctx_slider_options', 'ctx_slider_title', [
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => $defaults['ctx_slider_title'],
    ] );
    register_setting( 'ctx_slider_options', 'ctx_slider_slides_per_view', [
        'type'              => 'integer',
        'sanitize_callback' => function( $val ) {
            return ctx_slider_sanitize_int_range( $val, 1, 8, 4 );
        },
        'default'           => $defaults['ctx_slider_slides_per_view'],
    ] );
    register_setting( 'ctx_slider_options', 'ctx_slider_no_movies_msg', [
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => $defaults['ctx_slider_no_movies_msg'],
    ] );
    register_setting( 'ctx_slider_options', 'ctx_slider_cta_text', [
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => $defaults['ctx_slider_cta_text'],
    ] );
    register_setting( 'ctx_slider_options', 'ctx_slider_link_new_tab', [
        'type'              => 'string',
        'sanitize_callback' => 'ctx_slider_sanitize_checkbox',
        'default'           => $defaults['ctx_slider_link_new_tab'],
    ] );
    register_setting( 'ctx_slider_options', 'ctx_slider_loop', [
        'type'              => 'string',
        'sanitize_callback' => 'ctx_slider_sanitize_checkbox',
        'default'           => $defaults['ctx_slider_loop'],
    ] );
    register_setting( 'ctx_slider_options', 'ctx_slider_show_titles', [
        'type'              => 'string',
        'sanitize_callback' => 'ctx_slider_sanitize_checkbox',
        'default'           => $defaults['ctx_slider_show_titles'],
    ] );
    register_setting( 'ctx_slider_options', 'ctx_slider_show_3d_badge', [
        'type'              => 'string',
        'sanitize_callback' => 'ctx_slider_sanitize_checkbox',
        'default'           => $defaults['ctx_slider_show_3d_badge'],
    ] );
    register_setting( 'ctx_slider_options', 'ctx_slider_show_genre', [
        'type'              => 'string',
        'sanitize_callback' => 'ctx_slider_sanitize_checkbox',
        'default'           => $defaults['ctx_slider_show_genre'],
    ] );
    register_setting( 'ctx_slider_options', 'ctx_slider_show_meta', [
        'type'              => 'string',
        'sanitize_callback' => 'ctx_slider_sanitize_checkbox',
        'default'           => $defaults['ctx_slider_show_meta'],
    ] );

    // ── Style ──
    register_setting( 'ctx_slider_options', 'ctx_slider_max_width', [
        'type'              => 'integer',
        'sanitize_callback' => function( $val ) {
            return ctx_slider_sanitize_int_range( $val, 400, 2400, 1200 );
        },
        'default'           => $defaults['ctx_slider_max_width'],
    ] );
    register_setting( 'ctx_slider_options', 'ctx_slider_border_radius', [
        'type'              => 'integer',
        'sanitize_callback' => function( $val ) {
            return ctx_slider_sanitize_int_range( $val, 0, 30, 10 );
        },
        'default'           => $defaults['ctx_slider_border_radius'],
    ] );
    register_setting( 'ctx_slider_options', 'ctx_slider_accent_color', [
        'type'              => 'string',
        'sanitize_callback' => 'ctx_slider_sanitize_hex_color',
        'default'           => $defaults['ctx_slider_accent_color'],
    ] );
    register_setting( 'ctx_slider_options', 'ctx_slider_nav_color', [
        'type'              => 'string',
        'sanitize_callback' => 'ctx_slider_sanitize_hex_color',
        'default'           => $defaults['ctx_slider_nav_color'],
    ] );
}

// ─── Settings page ───────────────────────────────────────────────────────────
function ctx_slider_settings_page() {
    $defaults = ctx_slider_defaults();

    // Load all current values
    $api_url          = get_option( 'ctx_slider_api_url',          $defaults['ctx_slider_api_url'] );
    $api_timeout      = get_option( 'ctx_slider_api_timeout',      $defaults['ctx_slider_api_timeout'] );
    $cache_hours      = get_option( 'ctx_slider_cache_hours',      $defaults['ctx_slider_cache_hours'] );
    $days_ahead       = get_option( 'ctx_slider_days_ahead',       $defaults['ctx_slider_days_ahead'] );
    $title            = get_option( 'ctx_slider_title',            $defaults['ctx_slider_title'] );
    $slides_per_view  = get_option( 'ctx_slider_slides_per_view',  $defaults['ctx_slider_slides_per_view'] );
    $no_movies_msg    = get_option( 'ctx_slider_no_movies_msg',    $defaults['ctx_slider_no_movies_msg'] );
    $cta_text         = get_option( 'ctx_slider_cta_text',         $defaults['ctx_slider_cta_text'] );
    $link_new_tab     = get_option( 'ctx_slider_link_new_tab',     $defaults['ctx_slider_link_new_tab'] );
    $loop             = get_option( 'ctx_slider_loop',             $defaults['ctx_slider_loop'] );
    $show_titles      = get_option( 'ctx_slider_show_titles',      $defaults['ctx_slider_show_titles'] );
    $show_3d_badge    = get_option( 'ctx_slider_show_3d_badge',    $defaults['ctx_slider_show_3d_badge'] );
    $show_genre       = get_option( 'ctx_slider_show_genre',       $defaults['ctx_slider_show_genre'] );
    $show_meta        = get_option( 'ctx_slider_show_meta',        $defaults['ctx_slider_show_meta'] );
    $max_width        = get_option( 'ctx_slider_max_width',        $defaults['ctx_slider_max_width'] );
    $border_radius    = get_option( 'ctx_slider_border_radius',    $defaults['ctx_slider_border_radius'] );
    $accent_color     = get_option( 'ctx_slider_accent_color',     $defaults['ctx_slider_accent_color'] );
    $nav_color        = get_option( 'ctx_slider_nav_color',        $defaults['ctx_slider_nav_color'] );
    ?>
    <style>
        .ctx-tab-panel { display: none; }
        .ctx-tab-panel.ctx-active { display: block; }
        .ctx-settings-wrap .nav-tab-wrapper { margin-bottom: 1.5em; }
        .ctx-settings-wrap .form-table th { width: 220px; }
        .ctx-settings-wrap h2.ctx-section-title { margin-top: 0; }
        .ctx-checkbox-row label { font-weight: normal; }
        .ctx-checkbox-row .description { margin-top: 4px; }
    </style>

    <div class="wrap ctx-settings-wrap">
        <h1>Cinetixx Slider Einstellungen</h1>

        <nav class="nav-tab-wrapper">
            <a href="#tab-api" class="nav-tab nav-tab-active" data-tab="tab-api">API &amp; Daten</a>
            <a href="#tab-display" class="nav-tab" data-tab="tab-display">Darstellung</a>
            <a href="#tab-style" class="nav-tab" data-tab="tab-style">Stil</a>
            <a href="#tab-info" class="nav-tab" data-tab="tab-info">Cache &amp; Shortcode</a>
        </nav>

        <form method="post" action="options.php">
            <?php settings_fields( 'ctx_slider_options' ); ?>

            <!-- ── Tab 1: API & Data ── -->
            <div id="tab-api" class="ctx-tab-panel ctx-active">
                <h2 class="ctx-section-title">API &amp; Dateneinstellungen</h2>
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
                        <th scope="row"><label for="ctx_slider_api_timeout">API-Timeout (Sekunden)</label></th>
                        <td>
                            <input type="number" id="ctx_slider_api_timeout" name="ctx_slider_api_timeout"
                                   value="<?php echo esc_attr( $api_timeout ); ?>" min="5" max="60" class="small-text">
                            <p class="description">Maximale Wartezeit auf eine API-Antwort (5&ndash;60 Sek.).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ctx_slider_cache_hours">Cache (Stunden)</label></th>
                        <td>
                            <input type="number" id="ctx_slider_cache_hours" name="ctx_slider_cache_hours"
                                   value="<?php echo esc_attr( $cache_hours ); ?>" min="1" max="48" class="small-text">
                            <p class="description">Wie lange die API-Antwort zwischengespeichert wird (1&ndash;48 Std.).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ctx_slider_days_ahead">Vorschau-Zeitraum (Tage)</label></th>
                        <td>
                            <input type="number" id="ctx_slider_days_ahead" name="ctx_slider_days_ahead"
                                   value="<?php echo esc_attr( $days_ahead ); ?>" min="1" max="60" class="small-text">
                            <p class="description">Vorstellungen der n&auml;chsten X Tage anzeigen (1&ndash;60).</p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- ── Tab 2: Display ── -->
            <div id="tab-display" class="ctx-tab-panel">
                <h2 class="ctx-section-title">Darstellungsoptionen</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="ctx_slider_title">&Uuml;berschrift</label></th>
                        <td>
                            <input type="text" id="ctx_slider_title" name="ctx_slider_title"
                                   value="<?php echo esc_attr( $title ); ?>" class="regular-text">
                            <p class="description">Titel &uuml;ber dem Slider. Leer lassen um die &Uuml;berschrift auszublenden.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ctx_slider_slides_per_view">Poster pro Ansicht</label></th>
                        <td>
                            <input type="number" id="ctx_slider_slides_per_view" name="ctx_slider_slides_per_view"
                                   value="<?php echo esc_attr( $slides_per_view ); ?>" min="1" max="8" class="small-text">
                            <p class="description">Anzahl sichtbarer Poster auf gro&szlig;en Bildschirmen (1&ndash;8). Kann per Shortcode &uuml;berschrieben werden.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ctx_slider_no_movies_msg">Keine-Filme-Hinweis</label></th>
                        <td>
                            <input type="text" id="ctx_slider_no_movies_msg" name="ctx_slider_no_movies_msg"
                                   value="<?php echo esc_attr( $no_movies_msg ); ?>" class="regular-text">
                            <p class="description">Text, wenn keine Vorstellungen vorhanden sind.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ctx_slider_cta_text">CTA-Button Text</label></th>
                        <td>
                            <input type="text" id="ctx_slider_cta_text" name="ctx_slider_cta_text"
                                   value="<?php echo esc_attr( $cta_text ); ?>" class="regular-text">
                            <p class="description">Text des Buttons im Hover-Overlay (z.B. &bdquo;Tickets kaufen &rarr;&ldquo;).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Sichtbare Elemente</th>
                        <td>
                            <fieldset>
                                <label class="ctx-checkbox-row">
                                    <input type="hidden" name="ctx_slider_show_titles" value="0">
                                    <input type="checkbox" name="ctx_slider_show_titles" value="1"
                                        <?php checked( $show_titles, '1' ); ?>>
                                    Filmtitel unter Postern anzeigen
                                </label><br>
                                <label class="ctx-checkbox-row">
                                    <input type="hidden" name="ctx_slider_show_3d_badge" value="0">
                                    <input type="checkbox" name="ctx_slider_show_3d_badge" value="1"
                                        <?php checked( $show_3d_badge, '1' ); ?>>
                                    3D-Badge anzeigen
                                </label><br>
                                <label class="ctx-checkbox-row">
                                    <input type="hidden" name="ctx_slider_show_genre" value="0">
                                    <input type="checkbox" name="ctx_slider_show_genre" value="1"
                                        <?php checked( $show_genre, '1' ); ?>>
                                    Genre im Overlay anzeigen
                                </label><br>
                                <label class="ctx-checkbox-row">
                                    <input type="hidden" name="ctx_slider_show_meta" value="0">
                                    <input type="checkbox" name="ctx_slider_show_meta" value="1"
                                        <?php checked( $show_meta, '1' ); ?>>
                                    Laufzeit &amp; Altersfreigabe im Overlay anzeigen
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Verhalten</th>
                        <td>
                            <fieldset>
                                <label class="ctx-checkbox-row">
                                    <input type="hidden" name="ctx_slider_link_new_tab" value="0">
                                    <input type="checkbox" name="ctx_slider_link_new_tab" value="1"
                                        <?php checked( $link_new_tab, '1' ); ?>>
                                    Links in neuem Tab &ouml;ffnen
                                </label><br>
                                <label class="ctx-checkbox-row">
                                    <input type="hidden" name="ctx_slider_loop" value="0">
                                    <input type="checkbox" name="ctx_slider_loop" value="1"
                                        <?php checked( $loop, '1' ); ?>>
                                    Endlos-Schleife (Loop) aktivieren
                                </label>
                            </fieldset>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- ── Tab 3: Style ── -->
            <div id="tab-style" class="ctx-tab-panel">
                <h2 class="ctx-section-title">Stil-Einstellungen</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="ctx_slider_max_width">Maximale Breite (px)</label></th>
                        <td>
                            <input type="number" id="ctx_slider_max_width" name="ctx_slider_max_width"
                                   value="<?php echo esc_attr( $max_width ); ?>" min="400" max="2400" step="10" class="small-text">
                            <p class="description">Maximale Breite des Sliders in Pixeln (400&ndash;2400).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ctx_slider_border_radius">Poster-Eckenradius (px)</label></th>
                        <td>
                            <input type="number" id="ctx_slider_border_radius" name="ctx_slider_border_radius"
                                   value="<?php echo esc_attr( $border_radius ); ?>" min="0" max="30" class="small-text">
                            <p class="description">Abrundung der Poster-Ecken in Pixeln (0&ndash;30).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ctx_slider_accent_color">Akzentfarbe</label></th>
                        <td>
                            <input type="text" id="ctx_slider_accent_color" name="ctx_slider_accent_color"
                                   value="<?php echo esc_attr( $accent_color ); ?>" class="ctx-color-picker"
                                   data-default-color="<?php echo esc_attr( $defaults['ctx_slider_accent_color'] ); ?>">
                            <p class="description">Farbe f&uuml;r 3D-Badge und Hover-Akzente.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ctx_slider_nav_color">Navigationsfarbe</label></th>
                        <td>
                            <input type="text" id="ctx_slider_nav_color" name="ctx_slider_nav_color"
                                   value="<?php echo esc_attr( $nav_color ); ?>" class="ctx-color-picker"
                                   data-default-color="<?php echo esc_attr( $defaults['ctx_slider_nav_color'] ); ?>">
                            <p class="description">Farbe f&uuml;r die Vor/Zur&uuml;ck-Buttons.</p>
                        </td>
                    </tr>
                </table>
            </div>

            <?php submit_button( 'Speichern' ); ?>
        </form>

        <!-- ── Tab 4: Cache & Shortcode (separate from settings form) ── -->
        <div id="tab-info" class="ctx-tab-panel">
            <h2 class="ctx-section-title">Cache leeren</h2>
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

            <hr>
            <h2>Shortcode-Verwendung</h2>
            <p>F&uuml;ge den Shortcode in eine Seite oder einen Beitrag ein:</p>
            <p><code>[cinetixx_slider]</code></p>
            <p>Optionale Parameter (zum &Uuml;berschreiben der Einstellungen oben):</p>
            <p><code>[cinetixx_slider days="7" slides="3"]</code></p>
            <table class="widefat striped" style="max-width: 500px;">
                <thead><tr><th>Parameter</th><th>Beschreibung</th><th>Standard</th></tr></thead>
                <tbody>
                    <tr>
                        <td><code>days</code></td>
                        <td>Vorschau-Zeitraum in Tagen</td>
                        <td><?php echo esc_html( $days_ahead ); ?></td>
                    </tr>
                    <tr>
                        <td><code>slides</code></td>
                        <td>Poster pro Ansicht (Desktop)</td>
                        <td><?php echo esc_html( $slides_per_view ); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Color pickers
        $('.ctx-color-picker').wpColorPicker();

        // Tab switching
        var $tabs = $('.ctx-settings-wrap .nav-tab');
        var $panels = $('.ctx-tab-panel');

        $tabs.on('click', function(e) {
            e.preventDefault();
            var target = $(this).data('tab');

            $tabs.removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');

            $panels.removeClass('ctx-active');
            $('#' + target).addClass('ctx-active');
        });

        // Restore tab from URL hash
        var hash = window.location.hash.replace('#', '');
        if (hash && $('#' + hash).length) {
            $tabs.removeClass('nav-tab-active');
            $panels.removeClass('ctx-active');
            $tabs.filter('[data-tab="' + hash + '"]').addClass('nav-tab-active');
            $('#' + hash).addClass('ctx-active');
        }
    });
    </script>
    <?php
}

// ─── Fetch & parse Cinetixx data ─────────────────────────────────────────────
function ctx_slider_get_movies( $days_ahead = null ) {
    $defaults = ctx_slider_defaults();

    if ( $days_ahead === null ) {
        $days_ahead = (int) get_option( 'ctx_slider_days_ahead', $defaults['ctx_slider_days_ahead'] );
    }

    $transient_key = 'ctx_slider_movies';
    $cached        = get_transient( $transient_key );

    if ( $cached !== false ) {
        return ctx_slider_filter_by_days( $cached, $days_ahead );
    }

    $api_url     = get_option( 'ctx_slider_api_url', $defaults['ctx_slider_api_url'] );
    $api_timeout = (int) get_option( 'ctx_slider_api_timeout', $defaults['ctx_slider_api_timeout'] );

    $response = wp_remote_get( $api_url, [
        'timeout' => $api_timeout,
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

    $movies_map = [];

    foreach ( $xml->Show as $show ) {
        $status = (string) $show['status'];
        if ( $status !== 'SHOW_ENABLED' ) {
            continue;
        }

        $movie_id   = (string) $show->MOVIE_ID;
        $show_begin = (string) $show->SHOW_BEGINNING;

        $showtime_data = [
            'datetime'     => $show_begin,
            'booking_link' => (string) $show->BOOKING_LINK,
        ];

        if ( isset( $movies_map[ $movie_id ] ) ) {
            $movies_map[ $movie_id ]['showtimes'][] = $showtime_data;

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

    $cache_hours = (int) get_option( 'ctx_slider_cache_hours', $defaults['ctx_slider_cache_hours'] );
    set_transient( $transient_key, $movies, $cache_hours * HOUR_IN_SECONDS );

    return ctx_slider_filter_by_days( $movies, $days_ahead );
}

function ctx_slider_filter_by_days( $movies, $days_ahead ) {
    $now     = new DateTime( 'now', new DateTimeZone( 'Europe/Berlin' ) );
    $cutoff  = ( clone $now )->modify( "+{$days_ahead} days" );

    return array_filter( $movies, function( $movie ) use ( $now, $cutoff ) {
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
    $defaults = ctx_slider_defaults();

    $default_slides = (int) get_option( 'ctx_slider_slides_per_view', $defaults['ctx_slider_slides_per_view'] );

    $atts = shortcode_atts( [
        'days'   => null,
        'slides' => $default_slides,
    ], $atts, 'cinetixx_slider' );

    $days_ahead = $atts['days'] ? (int) $atts['days'] : null;
    $movies     = ctx_slider_get_movies( $days_ahead );

    if ( empty( $movies ) ) {
        $no_movies_msg = get_option( 'ctx_slider_no_movies_msg', $defaults['ctx_slider_no_movies_msg'] );
        return '<p class="ctx-no-movies">' . esc_html( $no_movies_msg ) . '</p>';
    }

    // Enqueue assets
    wp_enqueue_style( 'cinetixx-slider-css' );
    wp_enqueue_script( 'cinetixx-slider-js' );

    // Dynamic inline CSS from settings
    $max_width     = (int) get_option( 'ctx_slider_max_width',     $defaults['ctx_slider_max_width'] );
    $border_radius = (int) get_option( 'ctx_slider_border_radius', $defaults['ctx_slider_border_radius'] );
    $accent_color  = get_option( 'ctx_slider_accent_color',        $defaults['ctx_slider_accent_color'] );
    $nav_color     = get_option( 'ctx_slider_nav_color',           $defaults['ctx_slider_nav_color'] );

    if ( ! $accent_color ) {
        $accent_color = $defaults['ctx_slider_accent_color'];
    }
    if ( ! $nav_color ) {
        $nav_color = $defaults['ctx_slider_nav_color'];
    }

    $inline_css = sprintf(
        '.ctx-slider-wrapper { --ctx-max-width: %dpx; --ctx-border-radius: %dpx; --ctx-accent-color: %s; --ctx-nav-color: %s; }',
        $max_width,
        $border_radius,
        $accent_color,
        $nav_color
    );
    wp_add_inline_style( 'cinetixx-slider-css', $inline_css );

    $slides_per_view = (int) $atts['slides'];
    $loop            = get_option( 'ctx_slider_loop', $defaults['ctx_slider_loop'] );

    // Pass config to JS
    wp_localize_script( 'cinetixx-slider-js', 'ctxSliderConfig', [
        'slidesPerView' => $slides_per_view,
        'loop'          => (bool) $loop,
    ] );

    // Load display toggles
    $slider_title  = get_option( 'ctx_slider_title',       $defaults['ctx_slider_title'] );
    $cta_text      = get_option( 'ctx_slider_cta_text',    $defaults['ctx_slider_cta_text'] );
    $link_new_tab  = get_option( 'ctx_slider_link_new_tab', $defaults['ctx_slider_link_new_tab'] );
    $show_titles   = get_option( 'ctx_slider_show_titles',  $defaults['ctx_slider_show_titles'] );
    $show_3d_badge = get_option( 'ctx_slider_show_3d_badge', $defaults['ctx_slider_show_3d_badge'] );
    $show_genre    = get_option( 'ctx_slider_show_genre',   $defaults['ctx_slider_show_genre'] );
    $show_meta     = get_option( 'ctx_slider_show_meta',    $defaults['ctx_slider_show_meta'] );

    $target_attr = $link_new_tab ? 'target="_blank" rel="noopener"' : '';

    ob_start();
    ?>
    <div class="ctx-slider-wrapper">
        <div class="ctx-slider-header">
            <?php if ( $slider_title ) : ?>
                <h2 class="ctx-slider-title"><?php echo esc_html( $slider_title ); ?></h2>
            <?php else : ?>
                <div></div>
            <?php endif; ?>
            <div class="ctx-slider-nav">
                <button class="ctx-prev" aria-label="Zur&uuml;ck">&#10094;</button>
                <button class="ctx-next" aria-label="Weiter">&#10095;</button>
            </div>
        </div>

        <div class="swiper ctx-swiper">
            <div class="swiper-wrapper">
                <?php foreach ( $movies as $movie ) : ?>
                    <?php
                    $upcoming = [];
                    $now_ts   = time();
                    foreach ( $movie['showtimes'] as $st ) {
                        $ts = strtotime( $st['datetime'] );
                        if ( $ts >= $now_ts ) {
                            $upcoming[] = $st;
                        }
                    }
                    $first_link = ! empty( $upcoming ) ? $upcoming[0]['booking_link'] : '#';
                    ?>
                    <div class="swiper-slide ctx-slide">
                        <a href="<?php echo esc_url( $first_link ); ?>" <?php echo $target_attr; ?>
                           class="ctx-poster-link">
                            <div class="ctx-poster-container">
                                <img src="<?php echo esc_url( $movie['poster'] ); ?>"
                                     alt="<?php echo esc_attr( $movie['title'] ); ?>"
                                     class="ctx-poster-img" loading="lazy">

                                <?php if ( $show_3d_badge && $movie['is_3d'] ) : ?>
                                    <span class="ctx-badge ctx-badge-3d">3D</span>
                                <?php endif; ?>

                                <div class="ctx-overlay">
                                    <?php if ( $show_genre && $movie['genre'] ) : ?>
                                        <span class="ctx-genre"><?php echo esc_html( $movie['genre'] ); ?></span>
                                    <?php endif; ?>

                                    <?php if ( $show_meta ) : ?>
                                        <span class="ctx-meta">
                                            <?php echo esc_html( $movie['runtime'] ); ?> Min.
                                            &middot;
                                            <?php echo esc_html( $movie['age_rating'] ); ?>
                                        </span>
                                    <?php endif; ?>

                                    <div class="ctx-showtimes">
                                        <?php
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
                                                       class="ctx-time-link" <?php echo $target_attr; ?>
                                                       onclick="event.stopPropagation();">
                                                        <?php echo esc_html( $t['time'] ); ?>
                                                    </a>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <span class="ctx-cta"><?php echo esc_html( $cta_text ); ?></span>
                                </div>
                            </div>

                            <?php if ( $show_titles ) : ?>
                                <h3 class="ctx-movie-title"><?php echo esc_html( $movie['title'] ); ?></h3>
                            <?php endif; ?>
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
