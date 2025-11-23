<?php
/**
 * WordPress Admin Dashboard integration for minime plugin.
 * Handles the admin menu page and overview panel.
 *
 * @package minime
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Minime_Admin {

    /**
     * Initialize admin hooks.
     */
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_admin_page' ) );
    }

    /**
     * Register the admin menu page.
     */
    public static function register_admin_page() {
        add_menu_page(
            'minime',
            'minime',
            'manage_options',
            'minime-dashboard',
            array( __CLASS__, 'render_admin_page' ),
            'dashicons-admin-links',
            25
        );
    }

    /**
     * Render the admin dashboard page.
     */
    public static function render_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Handle form submission
        if ( isset( $_POST['minime_overview_form'] )
             && isset( $_POST['minime_overview_nonce'] )
             && wp_verify_nonce( $_POST['minime_overview_nonce'], 'minime_save_overview' )
             && current_user_can( 'manage_options' ) ) {
            
            self::handle_admin_save();
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'minime settings updated successfully.', 'minime' ) . '</p></div>';
        }

        // Include the admin page template
        $settings = minime_get_settings();
        $site_title = get_bloginfo( 'name' );
        $site_tagline = get_bloginfo( 'description' );
        $bio = isset( $settings['bio'] ) ? $settings['bio'] : '';
        $background = isset( $settings['background'] ) ? $settings['background'] : array();
        $site_icon_id = (int) get_option( 'site_icon', 0 );
        $site_icon_url = get_site_icon_url();
        $minime_page_id = (int) get_option( 'minime_front_page_id', 0 );
        $admin_page_id = (int) get_option( 'minime_admin_page_id', 0 );
        $public_url = $minime_page_id ? get_permalink( $minime_page_id ) : home_url( '/' );
        $admin_url = $admin_page_id ? get_permalink( $admin_page_id ) : home_url( '/admin' );

        include plugin_dir_path( dirname( __FILE__ ) ) . 'templates/admin-dashboard.php';
    }

    /**
     * Handle admin form submission.
     */
    private static function handle_admin_save() {
        $settings = minime_get_settings();
        
        // Site Title & Tagline - preserve all characters including symbols (< > @ etc)
        if ( isset( $_POST['site_title'] ) ) {
            update_option( 'blogname', sanitize_option( 'blogname', $_POST['site_title'] ) );
        }
        if ( isset( $_POST['site_tagline'] ) ) {
            update_option( 'blogdescription', sanitize_option( 'blogdescription', $_POST['site_tagline'] ) );
        }
        
        // Bio
        if ( isset( $_POST['bio'] ) ) {
            $settings['bio'] = wp_kses_post( $_POST['bio'] );
        }
        
        // Site Icon
        if ( isset( $_POST['site_icon_id'] ) ) {
            $icon_id = intval( $_POST['site_icon_id'] );
            if ( $icon_id > 0 ) {
                update_option( 'site_icon', $icon_id );
            }
        }
        
        // Background
        $bg = $settings['background'];
        
        if ( isset( $_POST['background_type'] ) ) {
            $bg['type'] = sanitize_text_field( $_POST['background_type'] );
        }
        
        if ( isset( $_POST['background_color'] ) ) {
            $color = sanitize_hex_color( $_POST['background_color'] );
            if ( $color ) {
                $bg['color'] = $color;
            }
        }
        
        if ( isset( $_POST['background_image_id'] ) ) {
            $bg['image_id'] = intval( $_POST['background_image_id'] );
        }
        
        if ( isset( $_POST['background_gradient_angle'] ) ) {
            $angle = intval( $_POST['background_gradient_angle'] );
            if ( $angle < 0 ) $angle = 0;
            if ( $angle > 360 ) $angle = 360;
            $bg['gradient']['angle'] = $angle;
        }
        
        if ( isset( $_POST['background_gradient_colors'] ) ) {
            $colors_input = sanitize_text_field( $_POST['background_gradient_colors'] );
            $colors = array_map( 'trim', explode( ',', $colors_input ) );
            $colors_clean = array();
            foreach ( $colors as $c ) {
                $sanitized = sanitize_hex_color( $c );
                if ( $sanitized ) {
                    $colors_clean[] = $sanitized;
                }
            }
            $bg['gradient']['colors'] = $colors_clean;
        }
        
        if ( isset( $_POST['background_custom_code'] ) ) {
            $custom = wp_kses_post( $_POST['background_custom_code'] );
            $bg['custom_code'] = $custom;
        }
        
        $settings['background'] = $bg;
        
        minime_update_settings( $settings );
    }
}
