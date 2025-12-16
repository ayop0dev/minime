<?php
/**
 * Template management for minime plugin.
 * Handles template overrides, shortcodes, and asset enqueuing.
 *
 * @package minime
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Minime_Templates {

    /**
     * Initialize template hooks.
     */
    public static function init() {
        add_filter( 'template_include', array( __CLASS__, 'override_page_template' ) );
        add_action( 'template_redirect', array( __CLASS__, 'disable_admin_bar' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'strip_theme_styles' ), 100 );
        
        // Register shortcodes immediately
        self::register_shortcodes();
    }

    /**
     * Check if current request is in minime context.
     * Returns true for:
     * - Minime front page (by option minime_front_page_id)
     * - Minime admin route (by query var minime_admin)
     *
     * @return bool True if in minime context.
     */
    private static function is_minime_context() {
        $front_page_id = (int) get_option( 'minime_front_page_id', 0 );
        
        // Check if current page is minime front page
        if ( $front_page_id > 0 && is_page( $front_page_id ) ) {
            return true;
        }
        
        // Check if current request is minime admin route
        if ( get_query_var( 'minime_admin' ) ) {
            return true;
        }
        
        return false;
    }

    /**
     * Override page template for minime front page.
     * 
     * This ensures minime front page renders without theme interference,
     * providing a clean fullscreen experience.
     */
    public static function override_page_template( $template ) {
        $front_page_id = (int) get_option( 'minime_front_page_id', 0 );
        
        // Only override for minime front page
        if ( $front_page_id > 0 && is_page( $front_page_id ) ) {
            $plugin_template = plugin_dir_path( dirname( __FILE__ ) ) . 'templates/minime-blank.php';
            if ( file_exists( $plugin_template ) ) {
                return $plugin_template;
            }
        }

        return $template;
    }

    /**
     * Disable admin toolbar on minime pages (front and admin route).
     * 
     * Provides a clean, distraction-free interface for the link-in-bio
     * and admin panel without WordPress admin bar overlay.
     */
    public static function disable_admin_bar() {
        if ( self::is_minime_context() ) {
            show_admin_bar( false );
        }
    }

    /**
     * Strip theme styles on minime pages to prevent conflicts.
     * 
     * Minime requires full control over styling to maintain consistent
     * branding and UX. Theme styles are removed to prevent:
     * - Layout conflicts from theme grid/flex systems
     * - Typography overrides that break minime design
     * - Color scheme interference
     * - Z-index and positioning issues
     * 
     * Only minime's own styles and essential WP styles are preserved.
     */
    public static function strip_theme_styles() {
        // Only strip styles in minime context
        if ( ! self::is_minime_context() ) {
            return;
        }

        global $wp_styles;

        if ( ! ( $wp_styles instanceof WP_Styles ) ) {
            return;
        }

        // Whitelist: Plugin styles that should be preserved
        $allowed = array(
            'minime-style',       // Public card styles
            'minime-admin-style', // Admin panel styles
            'dashicons',          // WordPress icon font (may be used by other plugins)
        );

        // Remove all non-whitelisted styles to ensure clean slate
        foreach ( $wp_styles->queue as $handle ) {
            if ( ! in_array( $handle, $allowed, true ) ) {
                wp_dequeue_style( $handle );
                wp_deregister_style( $handle );
            }
        }
    }

    /**
     * Register minime shortcodes.
     */
    public static function register_shortcodes() {
        add_shortcode( 'minime_link_in_bio', array( __CLASS__, 'render_card_shortcode' ) );
        add_shortcode( 'minime_admin_panel', array( __CLASS__, 'render_admin_shortcode' ) );
    }

    /**
     * Render the public card (Link in Bio) page.
     * Shortcode: [minime_link_in_bio]
     * 
     * Server-side rendered card with data from WordPress.
     * This is a fallback until a frontend React app is deployed.
     */
    public static function render_card_shortcode() {
        // Get settings
        $settings = function_exists( 'minime_get_settings' ) ? minime_get_settings() : array();
        
        // Get basic info
        $site_title = html_entity_decode( get_option( 'blogname' ), ENT_QUOTES, 'UTF-8' );
        $site_tagline = html_entity_decode( get_option( 'blogdescription' ), ENT_QUOTES, 'UTF-8' );
        $bio = isset( $settings['bio'] ) ? $settings['bio'] : '';
        $site_icon_url = get_site_icon_url();
        
        // Get socials and buttons
        $socials = isset( $settings['socials'] ) ? $settings['socials'] : array();
        $buttons = isset( $settings['buttons'] ) ? $settings['buttons'] : array();
        
        // Get background settings
        $bg = isset( $settings['background'] ) ? $settings['background'] : array();
        $bg_type = isset( $bg['type'] ) ? $bg['type'] : 'solid';
        $bg_color = isset( $bg['color'] ) ? $bg['color'] : '#f5f5f5';
        $bg_image_id = isset( $bg['image_id'] ) ? (int) $bg['image_id'] : 0;
        $bg_image_url = $bg_image_id ? wp_get_attachment_url( $bg_image_id ) : '';
        $bg_gradient = isset( $bg['gradient'] ) ? $bg['gradient'] : array();
        
        // Card background
        $card_bg = isset( $settings['card_background'] ) ? $settings['card_background'] : array();
        $card_color = isset( $card_bg['color'] ) ? $card_bg['color'] : '#ffffff';
        
        // Build background CSS
        $page_bg_css = '';
        if ( $bg_type === 'gradient' && ! empty( $bg_gradient['colors'] ) ) {
            $colors = implode( ', ', $bg_gradient['colors'] );
            $angle = isset( $bg_gradient['angle'] ) ? (int) $bg_gradient['angle'] : 180;
            $page_bg_css = "background: linear-gradient({$angle}deg, {$colors});";
        } elseif ( $bg_type === 'image' && $bg_image_url ) {
            $page_bg_css = "background: url('" . esc_url( $bg_image_url ) . "') center/cover no-repeat;";
        } else {
            $page_bg_css = "background-color: " . esc_attr( $bg_color ) . ";";
        }
        
        // Social icon mapping
        $social_icons = array(
            'instagram' => 'fab fa-instagram',
            'youtube' => 'fab fa-youtube',
            'facebook' => 'fab fa-facebook',
            'twitter' => 'fab fa-twitter',
            'tiktok' => 'fab fa-tiktok',
            'linkedin' => 'fab fa-linkedin',
            'email' => 'fas fa-envelope',
            'phone' => 'fas fa-phone',
            'whatsapp' => 'fab fa-whatsapp',
            'telegram' => 'fab fa-telegram',
            'snapchat' => 'fab fa-snapchat',
            'pinterest' => 'fab fa-pinterest',
            'github' => 'fab fa-github',
            'website' => 'fas fa-globe',
        );
        
        ob_start();
        ?>
        <style>
            .minime-card-wrapper {
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
                <?php echo $page_bg_css; ?>
            }
            .minime-card {
                background: <?php echo esc_attr( $card_color ); ?>;
                border-radius: 24px;
                padding: 40px 30px;
                max-width: 400px;
                width: 100%;
                text-align: center;
                box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            }
            .minime-avatar {
                width: 100px;
                height: 100px;
                border-radius: 50%;
                object-fit: cover;
                margin-bottom: 16px;
                border: 3px solid rgba(255,255,255,0.8);
            }
            .minime-title {
                font-size: 24px;
                font-weight: 700;
                margin: 0 0 4px 0;
                color: <?php echo self::get_contrast_color( $card_color ); ?>;
            }
            .minime-tagline {
                font-size: 14px;
                color: <?php echo self::get_contrast_color( $card_color ); ?>;
                opacity: 0.7;
                margin: 0 0 12px 0;
            }
            .minime-bio {
                font-size: 14px;
                color: <?php echo self::get_contrast_color( $card_color ); ?>;
                opacity: 0.8;
                margin: 0 0 24px 0;
                line-height: 1.5;
            }
            .minime-socials {
                display: flex;
                justify-content: center;
                gap: 16px;
                margin-bottom: 24px;
            }
            .minime-social-link {
                width: 40px;
                height: 40px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                background: rgba(0,0,0,0.1);
                color: <?php echo self::get_contrast_color( $card_color ); ?>;
                text-decoration: none;
                transition: transform 0.2s, background 0.2s;
            }
            .minime-social-link:hover {
                transform: scale(1.1);
                background: rgba(0,0,0,0.2);
            }
            .minime-buttons {
                display: flex;
                flex-direction: column;
                gap: 12px;
            }
            .minime-button {
                display: block;
                padding: 14px 24px;
                background: <?php echo self::get_contrast_color( $card_color ); ?>;
                color: <?php echo esc_attr( $card_color ); ?>;
                text-decoration: none;
                border-radius: 12px;
                font-weight: 600;
                transition: transform 0.2s, opacity 0.2s;
            }
            .minime-button:hover {
                transform: translateY(-2px);
                opacity: 0.9;
            }
        </style>
        <div class="minime-card-wrapper">
            <div class="minime-card">
                <?php if ( $site_icon_url ) : ?>
                    <img src="<?php echo esc_url( $site_icon_url ); ?>" alt="<?php echo esc_attr( $site_title ); ?>" class="minime-avatar">
                <?php endif; ?>
                
                <h1 class="minime-title"><?php echo esc_html( $site_title ); ?></h1>
                
                <?php if ( $site_tagline ) : ?>
                    <p class="minime-tagline"><?php echo esc_html( $site_tagline ); ?></p>
                <?php endif; ?>
                
                <?php if ( $bio ) : ?>
                    <p class="minime-bio"><?php echo wp_kses_post( $bio ); ?></p>
                <?php endif; ?>
                
                <?php if ( ! empty( $socials ) ) : ?>
                    <div class="minime-socials">
                        <?php foreach ( $socials as $social ) : 
                            $type = isset( $social['type'] ) ? $social['type'] : '';
                            $value = isset( $social['value'] ) ? $social['value'] : '';
                            $url = self::normalize_social_url( $type, $value );
                            $icon = isset( $social_icons[ $type ] ) ? $social_icons[ $type ] : 'fas fa-link';
                            if ( $url ) :
                        ?>
                            <a href="<?php echo esc_url( $url ); ?>" class="minime-social-link" target="_blank" rel="noopener">
                                <i class="<?php echo esc_attr( $icon ); ?>"></i>
                            </a>
                        <?php endif; endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ( ! empty( $buttons ) ) : ?>
                    <div class="minime-buttons">
                        <?php foreach ( $buttons as $button ) : 
                            $label = isset( $button['label'] ) ? $button['label'] : '';
                            $value = isset( $button['value'] ) ? $button['value'] : '';
                            if ( $label && $value ) :
                        ?>
                            <a href="<?php echo esc_url( $value ); ?>" class="minime-button" target="_blank" rel="noopener">
                                <?php echo esc_html( $label ); ?>
                            </a>
                        <?php endif; endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get contrasting text color (black or white) based on background.
     */
    private static function get_contrast_color( $hex ) {
        $hex = ltrim( $hex, '#' );
        if ( strlen( $hex ) === 3 ) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $r = hexdec( substr( $hex, 0, 2 ) );
        $g = hexdec( substr( $hex, 2, 2 ) );
        $b = hexdec( substr( $hex, 4, 2 ) );
        $luminance = ( 0.299 * $r + 0.587 * $g + 0.114 * $b ) / 255;
        return $luminance > 0.5 ? '#000000' : '#ffffff';
    }
    
    /**
     * Normalize social URLs.
     */
    private static function normalize_social_url( $type, $value ) {
        $value = trim( $value );
        if ( empty( $value ) ) return '';
        
        // Already a full URL
        if ( preg_match( '~^https?://~i', $value ) ) {
            return $value;
        }
        
        switch ( strtolower( $type ) ) {
            case 'instagram':
                return 'https://instagram.com/' . ltrim( $value, '@/ ' );
            case 'youtube':
                return 'https://youtube.com/' . ltrim( $value, '@/ ' );
            case 'facebook':
                return 'https://facebook.com/' . ltrim( $value, '@/ ' );
            case 'twitter':
                return 'https://twitter.com/' . ltrim( $value, '@/ ' );
            case 'tiktok':
                return 'https://tiktok.com/@' . ltrim( $value, '@/ ' );
            case 'linkedin':
                return 'https://linkedin.com/in/' . ltrim( $value, '@/ ' );
            case 'github':
                return 'https://github.com/' . ltrim( $value, '@/ ' );
            case 'email':
                return 'mailto:' . $value;
            case 'phone':
                return 'tel:' . preg_replace( '/[^0-9+]/', '', $value );
            case 'whatsapp':
                return 'https://wa.me/' . preg_replace( '/[^0-9]/', '', $value );
            default:
                return $value;
        }
    }

    /**
     * Render the admin panel link.
     * Shortcode: [minime_admin_panel]
     * 
     * Returns a button/link that directs to the admin panel served via rewrite rule.
     * The actual admin panel is a Next.js app served at /{minime_admin_slug}/.
     * Kept for backward compatibility.
     */
    public static function render_admin_shortcode() {
        // Build admin URL using dynamic slug
        $admin_url = function_exists( 'minime_get_admin_url' ) 
            ? minime_get_admin_url() 
            : home_url( '/' . ( function_exists( 'minime_get_admin_slug' ) ? minime_get_admin_slug() : 'mm' ) . '/' );

        ob_start();
        ?>
        <div style="text-align: center; padding: 40px 20px;">
            <a href="<?php echo esc_url( $admin_url ); ?>" class="btn btn-primary" style="display: inline-block; padding: 12px 24px; background: #0073aa; color: white; text-decoration: none; border-radius: 4px;">
                Go to minime Admin
            </a>
        </div>
        <?php
        return ob_get_clean();
    }
}
