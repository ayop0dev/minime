<?php
/**
 * Minime REST API Handler
 *
 * Handles all REST API endpoints for the minime plugin.
 * Includes route registration, endpoint callbacks, and authentication helpers.
 *
 * @package Minime
 * @since 3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Minime_REST {

    /* ========================================================================= */
    /* INITIALIZATION & ROUTE REGISTRATION                                      */
    /* ========================================================================= */

    /**
     * Initialize REST API routes.
     * Call this method on rest_api_init hook.
     */
    public static function init() {
        self::register_routes();
    }

    /**
     * Register all REST API routes.
     *
     * ARCHITECTURE:
     * - Public endpoints (no auth):
     *   - GET /public: Card data for frontend display
     *   - POST /login: Legacy token-based login (optional, can be deprecated)
     *   - POST /request-password-reset: Password reset request
     *   - POST /reset-password: Password reset completion
     *
     * - Admin endpoints (cookie auth + nonce):
     *   - GET /admin: Full admin data for dashboard
     *   - POST /save: Save all settings
     *   - POST /upload-image: Upload image to media library
     *   - POST /admin-slug: Update admin slug (rewrite rules trigger)
     */
    private static function register_routes() {
        // DEBUG: Test endpoint to verify REST API is working
        $test_result = register_rest_route(
            'minime/v1',
            'test',
            array(
                'methods'             => 'GET',
                'callback'            => array( __CLASS__, 'test_endpoint' ),
                'permission_callback' => '__return_true',
            )
        );

        // Public endpoint: Get public card data (no auth required)
        register_rest_route(
            'minime/v1',
            'public',
            array(
                'methods'             => 'GET',
                'callback'            => array( __CLASS__, 'get_public_data' ),
                'permission_callback' => '__return_true',
            )
        );

        // Admin endpoint: Get full admin data (requires login + nonce)
        $admin_result = register_rest_route(
            'minime/v1',
            'admin',
            array(
                'methods'             => 'GET',
                'callback'            => array( __CLASS__, 'get_admin_data' ),
                'permission_callback' => array( __CLASS__, 'permission_admin' ),
            )
        );

        // Public endpoint: Login (legacy token-based, optional)
        register_rest_route(
            'minime/v1',
            'login',
            array(
                'methods'             => 'POST',
                'callback'            => array( __CLASS__, 'login' ),
                'permission_callback' => '__return_true',
            )
        );

        // Public endpoint: Request password reset
        register_rest_route(
            'minime/v1',
            'request-password-reset',
            array(
                'methods'             => 'POST',
                'callback'            => array( __CLASS__, 'request_password_reset' ),
                'permission_callback' => '__return_true',
            )
        );

        // Public endpoint: Handle password reset
        register_rest_route(
            'minime/v1',
            'reset-password',
            array(
                'methods'             => 'POST',
                'callback'            => array( __CLASS__, 'handle_reset_password' ),
                'permission_callback' => '__return_true',
            )
        );

        // Admin endpoint: Save all settings
        register_rest_route(
            'minime/v1',
            'save',
            array(
                'methods'             => 'POST',
                'callback'            => array( __CLASS__, 'save_all' ),
                'permission_callback' => array( __CLASS__, 'permission_admin' ),
            )
        );

        // Admin endpoint: Upload image
        register_rest_route(
            'minime/v1',
            'upload-image',
            array(
                'methods'             => 'POST',
                'callback'            => array( __CLASS__, 'upload_image' ),
                'permission_callback' => array( __CLASS__, 'permission_upload' ),
            )
        );

        // Admin endpoint: Update admin slug
        register_rest_route(
            'minime/v1',
            'admin-slug',
            array(
                'methods'             => 'POST',
                'callback'            => array( __CLASS__, 'update_admin_slug' ),
                'permission_callback' => array( __CLASS__, 'permission_admin' ),
            )
        );

        // Public endpoint: Logout (destroys session)
        register_rest_route(
            'minime/v1',
            'logout',
            array(
                'methods'             => 'POST',
                'callback'            => array( __CLASS__, 'logout' ),
                'permission_callback' => '__return_true',
            )
        );
    }

    /* ========================================================================= */
    /* PUBLIC ENDPOINTS                                                          */
    /* ========================================================================= */

    /**
     * DEBUG: Test endpoint to verify REST API is working
     * 
     * @return WP_REST_Response
     */
    public static function test_endpoint() {
        return rest_ensure_response( array(
            'success'       => true,
            'message'       => 'Minime REST API is working!',
            'current_user'  => get_current_user_id(),
            'is_logged_in'  => is_user_logged_in(),
            'timestamp'     => current_time( 'mysql' ),
        ) );
    }

    /**
     * GET /minime/v1/public
     * Returns ONLY public card data needed for frontend display.
     * No authentication required. Returns cleaned/minimal data.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response object.
     */
    public static function get_public_data( WP_REST_Request $request ) {
        $settings = minime_get_settings();

        // Core site meta: Name & Subtitle - get raw values without HTML encoding
        $site_title   = html_entity_decode( get_option( 'blogname' ), ENT_QUOTES, 'UTF-8' );
        $site_tagline = html_entity_decode( get_option( 'blogdescription' ), ENT_QUOTES, 'UTF-8' );

        // Site icon (WordPress core) – avatar for card + favicon
        $site_icon_url = get_site_icon_url();

        // Background: resolve URLs for image if we have IDs
        // IMPORTANT: Exclude custom_code entirely from public endpoint
        $bg          = $settings['background'];
        $bg_type     = isset( $bg['type'] ) ? $bg['type'] : 'image';
        $bg_image_id = isset( $bg['image_id'] ) ? (int) $bg['image_id'] : 0;
        $bg_image_url = $bg_image_id ? wp_get_attachment_url( $bg_image_id ) : '';
        $bg_color    = isset( $bg['color'] ) ? sanitize_hex_color( $bg['color'] ) ?: '#000000' : '#000000';
        $bg_gradient = isset( $bg['gradient'] ) && is_array( $bg['gradient'] ) ? $bg['gradient'] : array(
            'colors' => array(),
            'angle'  => 180,
        );

        // Socials – build normalized URLs
        $socials_raw = $settings['socials'];
        $socials     = array();

        if ( is_array( $socials_raw ) ) {
            foreach ( $socials_raw as $row ) {
                $type  = isset( $row['type'] ) ? $row['type'] : '';
                $value = isset( $row['value'] ) ? $row['value'] : '';

                if ( $type === '' && $value === '' ) {
                    continue;
                }

                $url = self::normalize_contact_url( $type, $value );
                if ( $url === '' ) {
                    continue;
                }

                $socials[] = array(
                    'type'   => $type,
                    'value'  => $value,
                    'url'    => $url,
                    'icon'   => $type,
                );
            }
        }

        // Buttons – also normalized to URLs
        $buttons_raw = $settings['buttons'];
        $buttons     = array();

        if ( is_array( $buttons_raw ) ) {
            foreach ( $buttons_raw as $row ) {
                $label = isset( $row['label'] ) ? $row['label'] : '';
                $value = isset( $row['value'] ) ? $row['value'] : '';

                if ( $label === '' && $value === '' ) {
                    continue;
                }

                $url = self::normalize_contact_url( 'button', $value );
                if ( $url === '' ) {
                    $url = $value;
                }

                $buttons[] = array(
                    'label' => $label,
                    'value' => $value,
                    'url'   => $url,
                );
            }
        }

        // Public URL for "View my minime" button
        $minime_page_id = (int) get_option( 'minime_front_page_id', 0 );
        $public_url = '';

        if ( $minime_page_id && get_post_status( $minime_page_id ) ) {
            $public_url = get_permalink( $minime_page_id );
        } else {
            $public_url = home_url( '/' );
        }

        // Card Background - SOLID COLOR ONLY (no gradient/type for public)
        $card_bg = isset( $settings['card_background'] ) ? $settings['card_background'] : array();
        $card_bg_color = isset( $card_bg['color'] ) ? sanitize_hex_color( $card_bg['color'] ) ?: '#ffffff' : '#ffffff';

        return rest_ensure_response( array(
            // Identity
            'site_title'   => $site_title,
            'site_tagline' => $site_tagline,
            'bio'          => $settings['bio'],

            // Avatar
            'site_icon_url' => $site_icon_url,

            // Background (no custom_code)
            'background' => array(
                'type'     => $bg_type,
                'image_url'    => $bg_image_url,
                'color'    => $bg_color,
                'gradient' => array(
                    'colors' => isset( $bg_gradient['colors'] ) ? $bg_gradient['colors'] : array(),
                    'angle'  => isset( $bg_gradient['angle'] ) ? (int) $bg_gradient['angle'] : 180,
                ),
            ),

            // Card Background - COLOR ONLY
            'card_background' => array(
                'color' => $card_bg_color,
            ),

            // Social links & buttons
            'socials' => $socials,
            'buttons' => $buttons,

            // URLs
            'public_url' => $public_url,
        ) );
    }

    /**
     * GET /minime/v1/admin
     * Returns FULL admin data for dashboard (authenticated + nonce verified).
     * Includes IDs, custom_code, internal settings.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response object.
     */
    public static function get_admin_data( WP_REST_Request $request ) {
        $settings = minime_get_settings();

        // Core site meta
        $site_title   = html_entity_decode( get_option( 'blogname' ), ENT_QUOTES, 'UTF-8' );
        $site_tagline = html_entity_decode( get_option( 'blogdescription' ), ENT_QUOTES, 'UTF-8' );

        // Site icon with ID
        $site_icon_url = get_site_icon_url();
        $site_icon_id  = (int) get_option( 'site_icon', 0 );

        // Background: FULL data including custom_code and image_id
        $bg          = $settings['background'];
        $bg_type     = isset( $bg['type'] ) ? $bg['type'] : 'image';
        $bg_image_id = isset( $bg['image_id'] ) ? (int) $bg['image_id'] : 0;
        $bg_image_url = $bg_image_id ? wp_get_attachment_url( $bg_image_id ) : '';
        $bg_color    = isset( $bg['color'] ) ? sanitize_hex_color( $bg['color'] ) ?: '#000000' : '#000000';
        $bg_gradient = isset( $bg['gradient'] ) && is_array( $bg['gradient'] ) ? $bg['gradient'] : array(
            'colors' => array(),
            'angle'  => 180,
        );

        // Encode custom_code to Base64 for frontend (stored as decoded HTML internally)
        $bg_custom_raw = isset( $bg['custom_code'] ) ? $bg['custom_code'] : '';
        $bg_custom     = base64_encode( $bg_custom_raw );

        // Socials – normalized URLs
        $socials_raw = $settings['socials'];
        $socials     = array();

        if ( is_array( $socials_raw ) ) {
            foreach ( $socials_raw as $row ) {
                $type  = isset( $row['type'] ) ? $row['type'] : '';
                $value = isset( $row['value'] ) ? $row['value'] : '';

                if ( $type === '' && $value === '' ) {
                    continue;
                }

                $url = self::normalize_contact_url( $type, $value );
                if ( $url === '' ) {
                    continue;
                }

                $socials[] = array(
                    'type'   => $type,
                    'value'  => $value,
                    'url'    => $url,
                    'icon'   => $type,
                );
            }
        }

        // Buttons – normalized URLs
        $buttons_raw = $settings['buttons'];
        $buttons     = array();

        if ( is_array( $buttons_raw ) ) {
            foreach ( $buttons_raw as $row ) {
                $label = isset( $row['label'] ) ? $row['label'] : '';
                $value = isset( $row['value'] ) ? $row['value'] : '';

                if ( $label === '' && $value === '' ) {
                    continue;
                }

                $url = self::normalize_contact_url( 'button', $value );
                if ( $url === '' ) {
                    $url = $value;
                }

                $buttons[] = array(
                    'label' => $label,
                    'value' => $value,
                    'url'   => $url,
                );
            }
        }

        // Public URL
        $minime_page_id = (int) get_option( 'minime_front_page_id', 0 );
        $public_url = '';

        if ( $minime_page_id && get_post_status( $minime_page_id ) ) {
            $public_url = get_permalink( $minime_page_id );
        } else {
            $public_url = home_url( '/' );
        }

        // Card Background - FULL data for editing
        $card_bg = isset( $settings['card_background'] ) ? $settings['card_background'] : array();
        $card_bg_color = isset( $card_bg['color'] ) ? sanitize_hex_color( $card_bg['color'] ) ?: '#ffffff' : '#ffffff';

        // Admin slug for dashboard context
        $admin_slug = minime_get_admin_slug();

        // Sandbox code: limit to 100KB before encoding
        $raw_sandbox = isset( $bg['sandbox']['code'] ) ? (string) $bg['sandbox']['code'] : '';
        if ( strlen( $raw_sandbox ) > 102400 ) {
            $raw_sandbox = substr( $raw_sandbox, 0, 102400 );
        }

        // Custom CSS: sanitize and encode for transport
        $raw_custom_css = isset( $bg['custom_css'] ) ? (string) $bg['custom_css'] : '';
        $custom_css_b64 = $raw_custom_css !== '' ? base64_encode( $raw_custom_css ) : '';

        return rest_ensure_response( array(
            // Identity
            'site_title'   => $site_title,
            'site_tagline' => $site_tagline,
            'bio'          => $settings['bio'],

            // Avatar with ID
            'site_icon_id'  => $site_icon_id,
            'site_icon_url' => $site_icon_url,

            // Background - FULL data
            'background' => array(
                'type'        => $bg_type,
                'image_id'    => $bg_image_id,
                'image_url'   => $bg_image_url,
                'color'       => $bg_color,
                'gradient'    => array(
                    'colors' => isset( $bg_gradient['colors'] ) ? $bg_gradient['colors'] : array(),
                    'angle'  => isset( $bg_gradient['angle'] ) ? (int) $bg_gradient['angle'] : 180,
                ),
                'custom_code' => $bg_custom,
                'sandbox'     => array(
                    'code' => $raw_sandbox !== '' ? base64_encode( $raw_sandbox ) : '',
                ),
                'custom_css'  => $custom_css_b64,
            ),

            // Card Background - COLOR ONLY (solid only for cards)
            'card_background' => array(
                'color' => $card_bg_color,
            ),

            // Social links & buttons
            'socials' => $socials,
            'buttons' => $buttons,

            // URLs & settings
            'public_url' => $public_url,
            'keep_homepage' => isset( $settings['keep_homepage'] ) ? $settings['keep_homepage'] : false,
            'branding_footer_text' => isset( $settings['branding_footer_text'] ) ? $settings['branding_footer_text'] : 'Powered by · Ayop · Headless WP · REST API',

            // Admin-specific
            'adminBasePath' => $admin_slug,
        ) );
    }

    /**
     * POST /minime/v1/login
     * Handle user login with WordPress credentials.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public static function login( WP_REST_Request $request ) {
        $params   = $request->get_json_params();
        $username = isset( $params['username'] ) ? sanitize_text_field( $params['username'] ) : '';
        $password = isset( $params['password'] ) ? (string) $params['password'] : '';

        // remember: default true to keep behavior, but can be overridden
        $remember = array_key_exists( 'remember', $params ) ? (bool) $params['remember'] : true;

        if ( $username === '' || $password === '' ) {
            return new WP_Error(
                'missing_credentials',
                __( 'Username (or email) and password are required.', 'minime' ),
                array( 'status' => 400 )
            );
        }

        // Support login via email (convert to username)
        if ( strpos( $username, '@' ) !== false ) {
            $user_obj = get_user_by( 'email', $username );
            if ( $user_obj ) {
                $username = $user_obj->user_login;
            }
        }

        $creds = array(
            'user_login'    => $username,
            'user_password' => $password,
            'remember'      => $remember,
        );

        $user = wp_signon( $creds, is_ssl() );

        if ( is_wp_error( $user ) ) {
            return new WP_Error(
                'invalid_credentials',
                __( 'Invalid username/email or password.', 'minime' ),
                array( 'status' => 401 )
            );
        }

        wp_set_current_user( $user->ID );

        // Generate token – if remember is true, make it longer
        $token = wp_generate_password( 32, false );
        $ttl   = $remember ? 7 * DAY_IN_SECONDS : DAY_IN_SECONDS;

        set_transient(
            'minime_api_token_' . $token,
            (int) $user->ID,
            $ttl
        );

        return rest_ensure_response( array(
            'ok'    => true,
            'token' => $token,
            'user'  => array(
                'id'    => $user->ID,
                'email' => $user->user_email,
                'name'  => $user->display_name,
            ),
        ) );
    }

    /**
     * POST /minime/v1/logout
     * Logout endpoint - destroys WordPress session.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response object.
     */
    public static function logout( WP_REST_Request $request ) {
        wp_logout();

        return rest_ensure_response( array(
            'success' => true,
        ) );
    }

    /**
     * POST /minime/v1/request-password-reset
     * Request password reset endpoint - uses WordPress core retrieve_password().
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public static function request_password_reset( WP_REST_Request $request ) {
        $params = $request->get_json_params();
        $email_or_login = isset( $params['email'] ) ? sanitize_text_field( $params['email'] ) : '';

        if ( empty( $email_or_login ) ) {
            return new WP_Error(
                'missing_email',
                __( 'Email address or username is required.', 'minime' ),
                array( 'status' => 400 )
            );
        }

        // Try to find user by email first, then by login
        $user = get_user_by( 'email', $email_or_login );
        
        if ( ! $user ) {
            $user = get_user_by( 'login', $email_or_login );
        }
        
        if ( ! $user ) {
            // Don't reveal if email/username exists or not for security
            return rest_ensure_response( array(
                'ok'      => true,
                'message' => __( 'If this email is registered, you will receive a password reset link shortly.', 'minime' ),
            ) );
        }

        // Use WordPress core retrieve_password() function
        $result = retrieve_password( $user->user_login );
        
        if ( is_wp_error( $result ) ) {
            // Check for specific error codes
            $error_code = $result->get_error_code();
            
            // Don't expose sensitive error details to frontend
            if ( in_array( $error_code, array( 'invalidcombo', 'invalid_email', 'invalid_username' ) ) ) {
                // Generic success message for security
                return rest_ensure_response( array(
                    'ok'      => true,
                    'message' => 'If this email is registered, you will receive a password reset link shortly.',
                ) );
            }
            
            // For other errors (like email sending failures), return error
            return new WP_Error(
                'reset_failed',
                __( 'Unable to send password reset email. Please try again.', 'minime' ),
                array( 'status' => 500 )
            );
        }

        return rest_ensure_response( array(
            'ok'      => true,
            'message' => 'If this email is registered, you will receive a password reset link shortly.',
        ) );
    }

    /**
     * POST /minime/v1/reset-password
     * Handle password reset with new password.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public static function handle_reset_password( WP_REST_Request $request ) {
        $params = $request->get_json_params();
        
        $key      = isset( $params['key'] ) ? sanitize_text_field( $params['key'] ) : '';
        $login    = isset( $params['login'] ) ? sanitize_text_field( $params['login'] ) : '';
        $password = isset( $params['password'] ) ? $params['password'] : '';
        
        // Validate required fields
        if ( empty( $key ) || empty( $login ) || empty( $password ) ) {
            return new WP_Error(
                'missing_fields',
                __( 'Reset key, login, and new password are all required.', 'minime' ),
                array( 'status' => 400 )
            );
        }
        
        // Validate the reset key
        $user = check_password_reset_key( $key, $login );
        
        if ( is_wp_error( $user ) ) {
            $error_message = $user->get_error_message();
            
            return new WP_Error(
                'invalid_key',
                $error_message ? $error_message : __( 'Invalid or expired reset key.', 'minime' ),
                array( 'status' => 403 )
            );
        }
        
        // Key is valid, user object returned - reset the password
        reset_password( $user, $password );
        
        return rest_ensure_response( array(
            'ok'      => true,
            'message' => __( 'Password reset successfully. You can now log in with your new password.', 'minime' ),
        ) );
    }

    /* ========================================================================= */
    /* ADMIN ENDPOINTS                                                           */
    /* ========================================================================= */

    /**
     * POST /minime/v1/save
     * Save all settings in one request.
     * Requires: current_user_can('manage_options') + valid X-WP-Nonce header
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response object.
     */
    public static function save_all( WP_REST_Request $request ) {
        $params   = $request->get_json_params();
        $settings = minime_get_settings();

        // --- Site Title / Tagline ---
        // Preserve all characters including symbols (< > @ etc) but remove actual HTML/script tags
        $site_title   = isset( $params['site_title'] ) ? sanitize_option( 'blogname', $params['site_title'] ) : '';
        $site_tagline = isset( $params['site_tagline'] ) ? sanitize_option( 'blogdescription', $params['site_tagline'] ) : '';

        if ( $site_title !== '' ) {
            update_option( 'blogname', $site_title );
        }
        if ( $site_tagline !== '' ) {
            update_option( 'blogdescription', $site_tagline );
        }

        // --- Site Icon (avatar) – attachment ID ---
        $site_icon_id = 0;
        if ( isset( $params['site_icon_id'] ) ) {
            $site_icon_id = (int) $params['site_icon_id'];
            if ( $site_icon_id > 0 ) {
                update_option( 'site_icon', $site_icon_id );
            }
        }

        // --- Bio ---
        // Allow basic post HTML but strip dangerous tags/attributes
        if ( isset( $params['bio'] ) ) {
            $settings['bio'] = wp_kses_post( $params['bio'] );
        }

        // --- Background ---
        $bg = $settings['background'];

        if ( isset( $params['background'] ) && is_array( $params['background'] ) ) {
            $bg_input = $params['background'];

            if ( isset( $bg_input['type'] ) ) {
                $bg['type'] = sanitize_text_field( $bg_input['type'] );
            }

            if ( isset( $bg_input['image_id'] ) ) {
                $image_id = (int) $bg_input['image_id'];
                // Validate attachment exists and is actually an attachment
                if ( $image_id > 0 ) {
                    $attachment = get_post( $image_id );
                    if ( $attachment && 'attachment' === get_post_type( $attachment ) ) {
                        $bg['image_id'] = $image_id;
                    } else {
                        // Invalid attachment ID, clear it
                        $bg['image_id'] = 0;
                    }
                } else {
                    // Explicitly set to 0 if cleared
                    $bg['image_id'] = 0;
                }
            }

            if ( isset( $bg_input['color'] ) ) {
                // sanitize color value (fallback to safe default)
                $bg['color'] = sanitize_hex_color( $bg_input['color'] ) ?: '#000000';
            }

            if ( isset( $bg_input['gradient'] ) && is_array( $bg_input['gradient'] ) ) {
                $g = $bg_input['gradient'];

                $colors = array();
                if ( isset( $g['colors'] ) && is_array( $g['colors'] ) ) {
                    // sanitize each gradient color
                    foreach ( $g['colors'] as $c ) {
                        $c = sanitize_hex_color( $c );
                        if ( $c ) {
                            $colors[] = $c;
                        }
                    }
                }
                $angle = isset( $g['angle'] ) ? (int) $g['angle'] : 180;
                if ( $angle < 0 )   $angle = 0;
                if ( $angle > 360 ) $angle = 360;

                $bg['gradient'] = array(
                    'colors' => $colors,
                    'angle'  => $angle,
                );
            }

            if ( isset( $bg_input['custom_code'] ) ) {
                // --- Decode Base64 and Sanitize `custom_code` before storing ---
                // Admin.js sends Base64-encoded to prevent JSON corruption.
                // We allow <style> tags for custom backgrounds but strip scripts/PHP.
                $encoded = (string) $bg_input['custom_code'];
                $decoded = base64_decode( $encoded, true );
                
                if ( $decoded !== false ) {
                    // Remove PHP open/close tags completely
                    $decoded = str_replace( array('<?php', '<?', '?>'), '', $decoded );
                    
                    // Strip any <script>...</script> blocks to avoid storing active scripts
                    $decoded = preg_replace( '#<script\b[^>]*>(.*?)</script>#is', '', $decoded );
                    
                    // Allow style tags + common HTML for custom backgrounds
                    // wp_kses_post strips <style>, so we use wp_kses with custom allowed tags
                    $allowed_tags = wp_kses_allowed_html( 'post' );
                    $allowed_tags['style'] = array('type' => true);  // ← NOW ALLOWS <style> TAGS!
                    $allowed_tags['div'] = array('class' => true, 'id' => true, 'style' => true);
                    $allowed_tags['iframe'] = array(
                        'src' => true,
                        'width' => true,
                        'height' => true,
                        'frameborder' => true,
                        'allowfullscreen' => true,
                        'style' => true,
                        'class' => true,
                    );
                    $allowed_tags['canvas'] = array(
                        'id' => true,
                        'class' => true,
                        'style' => true,
                        'width' => true,
                        'height' => true,
                    );
                    
                    $bg['custom_code'] = wp_kses( $decoded, $allowed_tags );
                } else {
                    // Base64 decode failed, store empty string
                    $bg['custom_code'] = '';
                }
            }

            // --- Sandbox Code (HTML/CSS/JS for sandboxed iframe) ---
            if ( isset( $bg_input['sandbox'] ) && is_array( $bg_input['sandbox'] ) && isset( $bg_input['sandbox']['code'] ) ) {
                $encoded = (string) $bg_input['sandbox']['code'];
                $decoded = base64_decode( $encoded, true );

                if ( $decoded !== false ) {
                    // Limit to 100KB decoded
                    if ( strlen( $decoded ) > 102400 ) {
                        $decoded = substr( $decoded, 0, 102400 );
                    }

                    // Remove PHP tags
                    $decoded = str_replace( array('<?php', '<?', '?>'), '', $decoded );

                    // Strip external scripts: <script src="...">...</script> but keep inline <script>...</script>
                    // Pattern 1: <script ... src="...">any content</script>
                    $decoded = preg_replace( '#<script\b(?=[^>]*\ssrc\s*=)[^>]*>.*?</script>#is', '', $decoded );
                    // Pattern 2: self-closing <script ... src="..." />
                    $decoded = preg_replace( '#<script\b(?=[^>]*\ssrc\s*=)[^>]*/>#is', '', $decoded );

                    // Strip external stylesheets: <link rel="stylesheet" href="...">
                    $decoded = preg_replace( '#<link\b[^>]*\srel\s*=\s*["\']?stylesheet["\']?[^>]*/?>#is', '', $decoded );

                    // Strip <base> tags (can redirect relative URLs)
                    $decoded = preg_replace( '#<base\b[^>]*/?>#is', '', $decoded );

                    if ( ! isset( $bg['sandbox'] ) ) {
                        $bg['sandbox'] = array();
                    }
                    $bg['sandbox']['code'] = $decoded;
                } else {
                    $bg['sandbox']['code'] = '';
                }
            }

            // --- Custom CSS (CSS only, no HTML) ---
            if ( isset( $bg_input['custom_css'] ) ) {
                $encoded = (string) $bg_input['custom_css'];
                $decoded = base64_decode( $encoded, true );

                if ( $decoded !== false ) {
                    // Strip < and > to prevent any HTML/script injection
                    $decoded = str_replace( array( '<', '>' ), '', $decoded );

                    // Block dangerous CSS patterns: @import, expression(), javascript:
                    $decoded = preg_replace( '/@import\b/i', '', $decoded );
                    $decoded = preg_replace( '/expression\s*\(/i', '', $decoded );
                    $decoded = preg_replace( '/javascript\s*:/i', '', $decoded );

                    $bg['custom_css'] = $decoded;
                } else {
                    $bg['custom_css'] = '';
                }
            }
        }

        $settings['background'] = $bg;

        // --- Card Background (solid color ONLY) ---
        // Accept only the 'color' field; ignore type and gradient
        $card_bg = isset( $settings['card_background'] ) ? $settings['card_background'] : array();

        if ( isset( $params['card_background'] ) && is_array( $params['card_background'] ) ) {
            $card_bg_input = $params['card_background'];

            // Solid color only
            if ( isset( $card_bg_input['color'] ) ) {
                $card_bg['color'] = sanitize_hex_color( $card_bg_input['color'] ) ?: '#ffffff';
            }
        }

        $settings['card_background'] = $card_bg;

        // --- Socials ---
        if ( isset( $params['socials'] ) && is_array( $params['socials'] ) ) {
            $socials_clean = array();
            foreach ( $params['socials'] as $row ) {
                if ( ! is_array( $row ) ) {
                    continue;
                }

                // Short text fields: sanitize_text_field
                $type_raw  = isset( $row['type'] ) ? $row['type'] : '';
                $value_raw = isset( $row['value'] ) ? $row['value'] : '';

                $type  = sanitize_text_field( $type_raw ); // sanitized type
                $value = sanitize_text_field( $value_raw ); // sanitized short value

                if ( $type === '' && $value === '' ) {
                    continue;
                }

                // If the sanitized value looks like a URL, make sure to store a safe URL
                if ( preg_match( '~^https?://~i', $value ) || strpos( $value, '//' ) === 0 ) {
                    $value = esc_url_raw( $value ); // store safe URL
                }

                $socials_clean[] = array(
                    'type'  => $type,
                    'value' => $value,
                );
            }
            $settings['socials'] = $socials_clean;
        }

        // --- Buttons ---
        if ( isset( $params['buttons'] ) && is_array( $params['buttons'] ) ) {
            $buttons_clean = array();
            foreach ( $params['buttons'] as $row ) {
                if ( ! is_array( $row ) ) {
                    continue;
                }

                // Short text fields: sanitize_text_field
                $label_raw = isset( $row['label'] ) ? $row['label'] : '';
                $value_raw = isset( $row['value'] ) ? $row['value'] : '';

                $label = sanitize_text_field( $label_raw );
                $value = sanitize_text_field( $value_raw );

                if ( $label === '' && $value === '' ) {
                    continue;
                }

                // If value looks like a URL, ensure it's safely stored
                if ( preg_match( '~^https?://~i', $value ) || strpos( $value, '//' ) === 0 ) {
                    $value = esc_url_raw( $value );
                }

                $buttons_clean[] = array(
                    'label' => $label,
                    'value' => $value,
                );
            }
            $settings['buttons'] = $buttons_clean;
        }

        // --- Keep Homepage Setting ---
        $keep_homepage = ! empty( $params['keep_homepage'] );
        $settings['keep_homepage'] = $keep_homepage;

        // --- Branding Footer Text ---
        if ( isset( $params['branding_footer_text'] ) ) {
            $settings['branding_footer_text'] = sanitize_text_field( $params['branding_footer_text'] );
        }

        minime_update_settings( $settings );

        // Apply front page logic based on keep_homepage setting
        $front_page_id = (int) get_option( 'minime_front_page_id', 0 );
        if ( $front_page_id && current_user_can( 'manage_options' ) ) {
            minime_maybe_set_front_page( $front_page_id, $settings );
        }

        return rest_ensure_response( array(
            'ok'      => true,
            'message' => __( 'Settings saved successfully.', 'minime' ),
        ) );
    }

    /**
     * POST /minime/v1/upload-image
     * Upload an image to WordPress Media Library.
     * Hardened with file size limit (5MB) and image mime type validation.
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public static function upload_image( WP_REST_Request $request ) {
        $files = $request->get_file_params();

        if ( empty( $files ) || ! isset( $files['file'] ) ) {
            return new WP_Error(
                'no_file',
                __( 'No file uploaded.', 'minime' ),
                array( 'status' => 400 )
            );
        }

        $file = $files['file'];

        // --- File size check: limit to 5MB ---
        $max_size = 5 * 1024 * 1024; // 5MB in bytes
        if ( isset( $file['size'] ) && $file['size'] > $max_size ) {
            return new WP_Error(
                'file_too_large',
                __( 'File size exceeds 5MB limit.', 'minime' ),
                array( 'status' => 400 )
            );
        }

        // --- Mime type check: verify real file type, don't trust $file['type'] ---
        $file_check = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );
        $file_type = isset( $file_check['type'] ) ? $file_check['type'] : '';
        $file_ext = isset( $file_check['ext'] ) ? $file_check['ext'] : '';

        $allowed_extensions = array( 'jpg', 'jpeg', 'png', 'gif', 'webp' );
        $allowed_mimes = array( 'image/jpeg', 'image/png', 'image/gif', 'image/webp' );

        if ( ! in_array( $file_ext, $allowed_extensions, true ) || ! in_array( $file_type, $allowed_mimes, true ) ) {
            // Reject SVG with clear message
            if ( $file_ext === 'svg' || strpos( $file_type, 'svg' ) !== false ) {
                return new WP_Error(
                    'svg_not_allowed',
                    __( 'SVG files are not currently supported.', 'minime' ),
                    array( 'status' => 400 )
                );
            }

            return new WP_Error(
                'invalid_mime_type',
                __( 'Only image files are allowed (JPEG, PNG, GIF, WebP).', 'minime' ),
                array( 'status' => 400 )
            );
        }

        if ( ! function_exists( 'media_handle_sideload' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $attachment_id = media_handle_sideload(
            array(
                'name'     => $file['name'],
                'type'     => $file['type'],
                'tmp_name' => $file['tmp_name'],
                'error'    => $file['error'],
                'size'     => $file['size'],
            ),
            0
        );

        if ( is_wp_error( $attachment_id ) ) {
            return new WP_Error(
                'upload_error',
                $attachment_id->get_error_message(),
                array( 'status' => 500 )
            );
        }

        $url = wp_get_attachment_url( $attachment_id );

        return rest_ensure_response( array(
            'ok'  => true,
            'id'  => $attachment_id,
            'url' => $url,
        ) );
    }

    /**
     * POST /minime/v1/admin-slug
     * Update the admin slug (rewrite rules base).
     * Requires: current_user_can('manage_options') + valid X-WP-Nonce header
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public static function update_admin_slug( WP_REST_Request $request ) {
        $params = $request->get_json_params();
        $slug   = isset( $params['slug'] ) ? sanitize_text_field( $params['slug'] ) : '';

        if ( $slug === '' ) {
            return new WP_Error(
                'empty_slug',
                __( 'Admin slug cannot be empty.', 'minime' ),
                array( 'status' => 400 )
            );
        }

        // Additional sanitization: slugify and lowercase
        $slug = sanitize_title( $slug );
        $slug = strtolower( $slug );
        $slug = trim( $slug );

        if ( $slug === '' ) {
            return new WP_Error(
                'invalid_slug',
                __( 'Admin slug must contain at least one alphanumeric character.', 'minime' ),
                array( 'status' => 400 )
            );
        }

        // Reserved slugs that conflict with WordPress
        $reserved_slugs = array(
            'wp-admin',
            'wp-login',
            'wp-json',
            'minime',
            'admin',
            'login',
            'assets',
        );

        if ( in_array( $slug, $reserved_slugs, true ) ) {
            return new WP_Error(
                'reserved_slug',
                sprintf(
                    __( 'The slug "%s" is reserved and cannot be used.', 'minime' ),
                    $slug
                ),
                array( 'status' => 400 )
            );
        }

        // Update the option
        update_option( 'minime_admin_slug', $slug );

        // Flush rewrite rules to apply new slug-based routing
        Minime_Admin::maybe_flush_rewrites_on_change();

        // Build the new admin URL (match the saved slug)
        $admin_url = home_url( '/' . $slug . '/' );

        return rest_ensure_response( array(
            'ok'   => true,
            'slug' => $slug,
            'url'  => $admin_url,
            'message' => __( 'Admin slug updated successfully.', 'minime' ),
        ) );
    }

    /* ========================================================================= */
    /* AUTHENTICATION HELPERS                                                    */
    /* ========================================================================= */

    /**
     * Verify admin access via nonce + cookie auth.
     * MANDATORY: X-WP-Nonce header required for all admin requests.
     * Nonce is verified against 'wp_rest' action.
     *
     * @param WP_REST_Request $request Request object.
     * @param string $capability Required capability (e.g., 'manage_options').
     * @return bool|WP_Error True if authorized, WP_Error otherwise.
     */
    private static function verify_auth( WP_REST_Request $request, $capability ) {
        // --- MANDATORY: Check for X-WP-Nonce header ---
        $nonce = $request->get_header( 'x-wp-nonce' );
        if ( ! $nonce ) {
            return new WP_Error(
                'missing_nonce',
                __( 'X-WP-Nonce header is required for admin endpoints.', 'minime' ),
                array( 'status' => 403 )
            );
        }

        // Verify nonce
        if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
            return new WP_Error(
                'invalid_nonce',
                __( 'Invalid or expired nonce header.', 'minime' ),
                array( 'status' => 403 )
            );
        }

        // Check capability
        if ( ! current_user_can( $capability ) ) {
            return new WP_Error(
                'insufficient_permission',
                __( 'You do not have permission to access this resource.', 'minime' ),
                array( 'status' => 403 )
            );
        }

        return true;
    }

    /**
     * Permission callback for admin endpoints.
     * Requires 'manage_options' capability + valid nonce/auth.
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error True if authorized, WP_Error otherwise.
     */
    public static function permission_admin( WP_REST_Request $request ) {
        return self::verify_auth( $request, 'manage_options' );
    }

    /**
     * Permission callback for upload endpoints.
     * Requires 'upload_files' capability + valid nonce/auth.
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error True if authorized, WP_Error otherwise.
     */
    public static function permission_upload( WP_REST_Request $request ) {
        return self::verify_auth( $request, 'upload_files' );
    }

    /* ========================================================================= */
    /* HELPER METHODS                                                            */
    /* ========================================================================= */

    /**
     * Normalize URLs for socials / buttons based on type/value.
     *
     * @param string $type Contact type (instagram, facebook, email, etc).
     * @param string $value Contact value (username, URL, email, etc).
     * @return string Normalized URL.
     */
    private static function normalize_contact_url( $type, $value ) {
        $type  = strtolower( trim( (string) $type ) );
        $value = trim( (string) $value );

        if ( $value === '' ) {
            return '';
        }

        // Already a full URL with scheme
        if ( preg_match( '~^https?://~i', $value ) ) {
            return esc_url_raw( $value );
        }

        // Basic patterns
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

        // Generic normalization:
        // "example.com" → "https://example.com"
        if ( preg_match( '~^[\w.-]+\.[a-z]{2,}(/.*)?$~i', $value ) ) {
            return 'https://' . $value;
        }

        return esc_url_raw( $value );
    }
}
