<?php
/**
 * External Next.js Admin Panel routing for minime plugin.
 * Handles URL rewriting and template loading for the admin interface.
 * No WordPress wp-admin integration - uses external Next.js app via rewrite rules.
 *
 * @package minime
 *
 * ARCHITECTURE CHANGES:
 * - Removed wp-admin menu page rendering and POST form handler
 * - Removed hardcoded /admin page assumption (minime_admin_page_id)
 * - Implemented rewrite rules for dynamic admin slug (minime_get_admin_slug())
 * - Added template_redirect hook to serve Next.js admin shell on admin route
 * - Added maybe_flush_rewrites_on_change() for slug change handling
 * - Optional: wp-admin menu item now redirects to external admin URL
 * - Per-site option aware: uses minime_get_admin_slug() which reads per-site option
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Minime_Admin {

    /**
     * Initialize admin routing.
     */
    public static function init() {
        // Register rewrite rules for admin URL routing
        add_action( 'init', array( __CLASS__, 'register_rewrite_rules' ), 10 );
        
        // Register query variable for admin detection
        add_filter( 'query_vars', array( __CLASS__, 'register_query_vars' ) );
        
        // Handle admin template on frontend via template_redirect
        add_action( 'template_redirect', array( __CLASS__, 'handle_admin_template_redirect' ), 1 );
        
        // Optional: add wp-admin menu redirect to external admin
        add_action( 'admin_menu', array( __CLASS__, 'register_admin_menu_redirect' ) );
    }

    /**
     * Register rewrite rules for the dynamic admin slug.
     * Converts /mm (or custom slug) requests to index.php?minime_admin=1
     */
    public static function register_rewrite_rules() {
        $slug = minime_get_admin_slug();
        
        // Build rewrite rule for admin slug
        add_rewrite_rule(
            '^' . $slug . '(/.*)?$',
            'index.php?minime_admin=1',
            'top'
        );
    }

    /**
     * Register minime_admin query variable.
     */
    public static function register_query_vars( $query_vars ) {
        $query_vars[] = 'minime_admin';
        return $query_vars;
    }

    /**
     * Handle template redirect for admin requests.
     * When minime_admin=1 query var is set, load the admin shell template.
     * Requires user to be logged in and sets proper headers.
     */
    public static function handle_admin_template_redirect() {
        if ( ! get_query_var( 'minime_admin' ) ) {
            return;
        }

        // Require user to be logged in
        if ( ! is_user_logged_in() ) {
            auth_redirect();
        }

        // Set response headers
        status_header( 200 );
        nocache_headers();

        // Locate and include the admin shell template
        $template_path = plugin_dir_path( dirname( __FILE__ ) ) . 'templates/admin-shell.php';
        
        if ( ! file_exists( $template_path ) ) {
            wp_die(
                wp_kses_post(
                    '<h1>Admin Panel Not Found</h1>' .
                    '<p>The admin panel template is missing.</p>' .
                    '<p>Please run <code>npm run deploy</code> from the admin-src directory to build and deploy the Next.js admin app.</p>'
                ),
                'Admin Panel Missing',
                array( 'response' => 500 )
            );
        }

        include $template_path;
        exit;
    }

    /**
     * Register wp-admin menu item that redirects to external admin URL.
     * Only visible to users with manage_options capability.
     * No UI rendered - just a redirect link in the menu.
     */
    public static function register_admin_menu_redirect() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        add_menu_page(
            'minime',
            'minime',
            'manage_options',
            'minime-admin',
            array( __CLASS__, 'redirect_to_external_admin' ),
            'dashicons-admin-links',
            25
        );
    }

    /**
     * Redirect wp-admin menu click to external admin URL.
     */
    public static function redirect_to_external_admin() {
        wp_safe_redirect( minime_get_admin_url() );
        exit;
    }

    /**
     * Flush rewrite rules when admin slug changes.
     * IMPORTANT: Call this only after minime_admin_slug option changes, not on every request.
     * Uses non-hard flush (false) for performance and to preserve custom rules.
     *
     * @return void
     */
    public static function maybe_flush_rewrites_on_change() {
        flush_rewrite_rules( false );
    }
}
