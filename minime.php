<?php
/**
 * Plugin Name: minime
 * Description: REST API for minime frontend + external admin panel. No ACF required. Stores everything in core options.
 * Version: 1.0.0
 * Author: Ayop
 * Text Domain: minime
 * Domain Path: /languages
 *
 * @package minime
 *
 * ARCHITECTURE CHANGES:
 * - Added MINIME_PLUGIN_FILE constant for consistent file reference
 * - Removed hardcoded MINIME_ADMIN_SLUG; now uses dynamic minime_get_admin_slug()
 * - Introduced minime_get_admin_slug() helper to read option 'minime_admin_slug' (default: 'mm')
 * - Updated minime_get_admin_url() to use dynamic admin slug
 * - Removed unused minime_init(); init now wired directly on hooks
 * - Activation no longer creates WordPress /admin page (no [minime_admin_panel] shortcode)
 * - Activation now stores default option 'minime_admin_slug' = 'mm' on first run
 * - Simplified card_background defaults (removed gradient, keeps only color)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* =========================================================================
 * CONSTANTS & CONFIGURATION
 * ========================================================================= */

if ( ! defined( 'MINIME_PLUGIN_FILE' ) ) {
    define( 'MINIME_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'MINIME_PLUGIN_VERSION' ) ) {
    define( 'MINIME_PLUGIN_VERSION', '1.0.0' );
}

if ( ! defined( 'MINIME_FRONT_SLUG' ) ) {
    define( 'MINIME_FRONT_SLUG', 'minime' );
}

if ( ! defined( 'MINIME_OPTION_KEY' ) ) {
    define( 'MINIME_OPTION_KEY', 'minime_settings' );
}

/* =========================================================================
 * INCLUDES - Load plugin components
 * ========================================================================= */

require_once plugin_dir_path( __FILE__ ) . 'includes/class-minime-rest.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-minime-templates.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-minime-emails.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-minime-admin.php';

/* =========================================================================
 * INITIALIZATION
 * ========================================================================= */

/**
 * Load plugin text domain for translations.
 */
function minime_load_textdomain() {
    load_plugin_textdomain( 'minime', false, dirname( plugin_basename( MINIME_PLUGIN_FILE ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'minime_load_textdomain' );

/**
 * Initialize plugin components on rest_api_init.
 */
add_action( 'rest_api_init', array( 'Minime_REST', 'init' ) );

/**
 * Initialize plugin components on init.
 */
add_action( 'init', function() {
    Minime_Templates::init();
    Minime_Emails::init();
    Minime_Admin::init();
} );

/**
 * Auto-flush rewrite rules if minime admin rules are missing.
 * This handles cases where activation hook didn't properly register rules.
 */
add_action( 'wp_loaded', function() {
    // Only check on non-AJAX, non-REST requests from admin users
    if ( is_admin() || defined( 'DOING_AJAX' ) || defined( 'REST_REQUEST' ) ) {
        return;
    }
    
    global $wp_rewrite;
    
    // Check if minime_admin rule exists
    $has_minime_rule = false;
    if ( is_array( $wp_rewrite->rules ) && ! empty( $wp_rewrite->rules ) ) {
        foreach ( $wp_rewrite->rules as $rule => $rewrite ) {
            if ( strpos( $rewrite, 'minime_admin' ) !== false ) {
                $has_minime_rule = true;
                break;
            }
        }
    }
    
    // If rule missing, flush immediately
    if ( ! $has_minime_rule ) {
        // Register the rule
        Minime_Admin::register_rewrite_rules();
        // Flush the database
        flush_rewrite_rules( false );
    }
}, 99 );

/* =========================================================================
 * UTILITY FUNCTIONS
 * ========================================================================= */

/**
 * Helper to get plugin asset URL.
 *
 * @param string $path Relative path to asset.
 * @return string Full URL to asset.
 */
function minime_asset_url( $path = '' ) {
    return plugins_url( ltrim( $path, '/' ), MINIME_PLUGIN_FILE );
}

/**
 * Get the admin slug from options (per-site configuration).
 * Reads option 'minime_admin_slug', defaults to 'mm'.
 * Sanitizes to lowercase alphanumeric + dashes.
 *
 * @return string Sanitized admin slug.
 */
function minime_get_admin_slug() {
    $slug = get_option( 'minime_admin_slug', 'mm' );
    $slug = sanitize_title( $slug );
    return ! empty( $slug ) ? $slug : 'mm';
}

/**
 * Get the minime admin page URL based on dynamic admin slug.
 *
 * @return string Admin page URL.
 */
function minime_get_admin_url() {
    return home_url( '/' . minime_get_admin_slug() );
}

/* =========================================================================
 * POLYFILLS for older PHP versions
 * ========================================================================= */

if ( ! function_exists( 'str_contains' ) ) {
    /**
     * Polyfill for str_contains (PHP 8+).
     */
    function str_contains( $haystack, $needle ) {
        return $needle !== '' && mb_strpos( $haystack, $needle ) !== false;
    }
}

if ( ! function_exists( 'str_starts_with' ) ) {
    /**
     * Polyfill for str_starts_with (PHP 8+).
     */
    function str_starts_with( $haystack, $needle ) {
        return mb_substr( $haystack, 0, mb_strlen( $needle ) ) === $needle;
    }
}

/* =========================================================================
 * SETTINGS MANAGEMENT
 * ========================================================================= */

/**
 * Load settings array from options with sane defaults.
 *
 * @return array Settings array.
 */
function minime_get_settings() {
    $settings = get_option( MINIME_OPTION_KEY, array() );

    // Bio
    if ( ! isset( $settings['bio'] ) ) {
        $settings['bio'] = '';
    }

    // Background
    if ( ! isset( $settings['background'] ) || ! is_array( $settings['background'] ) ) {
        $settings['background'] = array();
    }

    $bg = &$settings['background'];
    if ( ! isset( $bg['type'] ) ) {
        $bg['type'] = 'image';
    }
    if ( ! isset( $bg['image_id'] ) ) {
        $bg['image_id'] = 0;
    }
    if ( ! isset( $bg['color'] ) ) {
        $bg['color'] = '#000000';
    }
    if ( ! isset( $bg['gradient'] ) || ! is_array( $bg['gradient'] ) ) {
        $bg['gradient'] = array(
            'colors' => array(),
            'angle'  => 180,
        );
    }
    if ( ! isset( $bg['custom_code'] ) ) {
        $bg['custom_code'] = '';
    }

    // Card Background
    if ( ! isset( $settings['card_background'] ) || ! is_array( $settings['card_background'] ) ) {
        $settings['card_background'] = array(
            'type'  => 'solid',
            'color' => '#ffffff',
        );
    }

    // Socials
    if ( ! isset( $settings['socials'] ) || ! is_array( $settings['socials'] ) ) {
        $settings['socials'] = array();
    }

    // Buttons
    if ( ! isset( $settings['buttons'] ) || ! is_array( $settings['buttons'] ) ) {
        $settings['buttons'] = array();
    }

    // Keep homepage setting
    if ( ! isset( $settings['keep_homepage'] ) ) {
        $settings['keep_homepage'] = false;
    }

    // Branding footer text
    if ( ! isset( $settings['branding_footer_text'] ) ) {
        $settings['branding_footer_text'] = 'Powered by · Ayop · Headless WP · REST API';
    }

    return $settings;
}

/**
 * Update settings in database.
 *
 * @param array $settings Settings array to save.
 * @return bool True if successful, false otherwise.
 */
function minime_update_settings( array $settings ) {
    return update_option( MINIME_OPTION_KEY, $settings );
}

/**
 * Maybe set minime page as front page based on keep_homepage setting.
 *
 * @param int   $page_id  The minime page ID.
 * @param array $settings Settings array.
 */
function minime_maybe_set_front_page( $page_id, $settings ) {
    if ( ! empty( $settings['keep_homepage'] ) ) {
        // User wants to keep their existing homepage
        return;
    }

    // Set minime page as front page
    update_option( 'show_on_front', 'page' );
    update_option( 'page_on_front', $page_id );
}

/**
 * Normalize URLs for socials/buttons based on type and value.
 * This function is used by the REST API class.
 *
 * @param string $type  Contact type (instagram, email, etc).
 * @param string $value Contact value (username, email, etc).
 * @return string Normalized URL.
 */
function minime_normalize_contact_url( $type, $value ) {
    $type  = strtolower( trim( (string) $type ) );
    $value = trim( (string) $value ) ;

    if ( $value === '' ) {
        return '';
    }

    // Already a full URL
    if ( preg_match( '~^https?://~i', $value ) ) {
        return esc_url_raw( $value );
    }

    // Platform-specific normalization
    switch ( $type ) {
        case 'instagram':
            return 'https://instagram.com/' . ltrim( $value, '@/ ' );
        case 'facebook':
            return 'https://facebook.com/' . ltrim( $value, '@/ ' );
        case 'linkedin':
            return 'https://linkedin.com/in/' . ltrim( $value, '@/ ' );
        case 'youtube':
            return 'https://youtube.com/' . ltrim( $value, '@/ ' );
        case 'x':
        case 'twitter':
            return 'https://x.com/' . ltrim( $value, '@/ ' );
        case 'tiktok':
            return 'https://www.tiktok.com/@' . ltrim( $value, '@/ ' );
        case 'snapchat':
            return 'https://www.snapchat.com/add/' . ltrim( $value, '@/ ' );
        case 'telegram':
            return 'https://t.me/' . ltrim( $value, '@/ ' );
        case 'github':
            return 'https://github.com/' . ltrim( $value, '@/ ' );
        case 'dribbble':
            return 'https://dribbble.com/' . ltrim( $value, '@/ ' );
        case 'behance':
            return 'https://www.behance.net/' . ltrim( $value, '@/ ' );
        case 'whatsapp':
            $digits = preg_replace( '~\D+~', '', $value );
            if ( strlen( $digits ) >= 8 ) {
                return 'https://wa.me/' . $digits;
            }
            return esc_url_raw( $value );
        case 'email':
        case 'mail':
            if ( str_contains( $value, '@' ) && ! str_starts_with( $value, 'mailto:' ) ) {
                return 'mailto:' . $value;
            }
            return esc_url_raw( $value );
        case 'phone':
        case 'call':
            $digits = preg_replace( '~\D+~', '', $value );
            if ( $digits !== '' ) {
                return 'tel:' . $digits;
            }
            return esc_url_raw( $value );
    }

    // Generic: "example.com" → "https://example.com"
    if ( preg_match( '~^[\w.-]+\.[a-z]{2,}(/.*)?$~i', $value ) ) {
        return 'https://' . $value;
    }

    return esc_url_raw( $value );
}

/* =========================================================================
 * PLUGIN ACTIVATION
 * ========================================================================= */

register_activation_hook( __FILE__, 'minime_activate_plugin' );

/**
 * Plugin activation callback.
 * Creates necessary pages and sets up initial configuration.
 */
function minime_activate_plugin() {
    // Create /minime page for public card
    $front_page_id = minime_ensure_page(
        'minime',
        MINIME_FRONT_SLUG,
        '[minime_link_in_bio]'
    );
    update_option( 'minime_front_page_id', $front_page_id );

    // Set minime page as front page by default
    $settings = minime_get_settings();
    minime_maybe_set_front_page( $front_page_id, $settings );

    // Store default admin slug if not already set
    if ( ! get_option( 'minime_admin_slug' ) ) {
        update_option( 'minime_admin_slug', 'mm' );
    }

    // Register rewrite rules before flushing
    // This ensures the rules are written to the WordPress rewrite database
    Minime_Admin::register_rewrite_rules();

    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * Ensure a page exists with given title, slug, and content.
 * Creates or restores the page if needed.
 *
 * @param string $title    Page title.
 * @param string $slug     Page slug.
 * @param string $shortcode_content Shortcode to insert in page content.
 * @return int Page ID.
 */
function minime_ensure_page( $title, $slug, $shortcode_content ) {
    // Check if page already exists
    $existing = get_page_by_path( $slug );

    if ( $existing && $existing->post_status === 'publish' ) {
        return $existing->ID;
    }

    // Check for trashed/draft page with same slug
    $trashed = get_posts( array(
        'name'           => $slug,
        'post_type'      => 'page',
        'post_status'    => array( 'trash', 'draft', 'pending', 'private' ),
        'posts_per_page' => 1,
    ) );

    if ( ! empty( $trashed ) ) {
        $page_id = $trashed[0]->ID;
        wp_update_post( array(
            'ID'           => $page_id,
            'post_status'  => 'publish',
            'post_title'   => $title,
            'post_content' => $shortcode_content,
        ) );
        return $page_id;
    }

    // Create new page
    $page_id = wp_insert_post( array(
        'post_title'   => $title,
        'post_name'    => $slug,
        'post_content' => $shortcode_content,
        'post_status'  => 'publish',
        'post_type'    => 'page',
        'post_author'  => get_current_user_id(),
    ) );

    return $page_id;
}


