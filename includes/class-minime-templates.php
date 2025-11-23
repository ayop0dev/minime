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
     * Override page template for minime pages to use blank template.
     * 
     * This ensures minime pages render without theme interference,
     * providing a clean fullscreen experience.
     */
    public static function override_page_template( $template ) {
        $front_page_id = (int) get_option( 'minime_front_page_id', 0 );
        $admin_page_id = (int) get_option( 'minime_admin_page_id', 0 );
        
        // Check if current page is a minime page (front or admin)
        if ( is_page( $front_page_id ) || is_page( $admin_page_id ) ) {
            $plugin_template = plugin_dir_path( dirname( __FILE__ ) ) . 'templates/minime-blank.php';
            if ( file_exists( $plugin_template ) ) {
                return $plugin_template;
            }
        }

        return $template;
    }

    /**
     * Disable admin toolbar on minime pages.
     * 
     * Provides a clean, distraction-free interface for the link-in-bio
     * and admin panel without WordPress admin bar overlay.
     */
    public static function disable_admin_bar() {
        $front_page_id = (int) get_option( 'minime_front_page_id', 0 );
        $admin_page_id = (int) get_option( 'minime_admin_page_id', 0 );
        
        if ( is_page( $front_page_id ) || is_page( $admin_page_id ) ) {
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
        // Only strip styles on minime pages (by slug)
        if ( ! ( is_page( MINIME_FRONT_SLUG ) || is_page( MINIME_ADMIN_SLUG ) ) ) {
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
     * Enqueues frontend assets and renders the app container.
     * Assets are enqueued here (not in template) to ensure they only
     * load when the shortcode is actually rendered.
     */
    public static function render_card_shortcode() {
        // Enqueue public CSS
        wp_enqueue_style(
            'minime-style',
            minime_asset_url( 'assets/style.css' ),
            array(),
            MINIME_PLUGIN_VERSION
        );

        // Enqueue public JS
        wp_enqueue_script(
            'minime-app',
            minime_asset_url( 'assets/app.js' ),
            array(),
            MINIME_PLUGIN_VERSION,
            true // Load in footer
        );

        // Pass configuration to JavaScript
        $config = array(
            'baseUrl'   => trailingslashit( home_url() ),
            'frontSlug' => MINIME_FRONT_SLUG,
            'adminSlug' => MINIME_ADMIN_SLUG,
            'restRoot'  => esc_url_raw( get_rest_url( null, 'minime/v1' ) ),
        );

        wp_localize_script(
            'minime-app',
            'MINIME_CONFIG',
            $config
        );

        // Return app mount point (JavaScript will render content here)
        ob_start();
        ?>
        <div id="app"></div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render the admin panel SPA.
     * Shortcode: [minime_admin_panel]
     * 
     * Enqueues admin assets and renders the admin panel structure.
     * Assets are enqueued here (not in template) to ensure they only
     * load when the shortcode is actually rendered.
     */
    public static function render_admin_shortcode() {
        // Enqueue admin CSS
        wp_enqueue_style(
            'minime-admin-style',
            minime_asset_url( 'assets/admin/admin.css' ),
            array(),
            MINIME_PLUGIN_VERSION
        );

        // Enqueue admin JS
        wp_enqueue_script(
            'minime-admin-app',
            minime_asset_url( 'assets/admin/admin.js' ),
            array(),
            MINIME_PLUGIN_VERSION,
            true // Load in footer
        );

        // Pass configuration to JavaScript
        $config = array(
            'baseUrl'   => trailingslashit( home_url() ),
            'frontSlug' => MINIME_FRONT_SLUG,
            'adminSlug' => MINIME_ADMIN_SLUG,
            'restRoot'  => esc_url_raw( get_rest_url( null, 'minime/v1' ) ),
        );

        wp_localize_script(
            'minime-admin-app',
            'MINIME_CONFIG',
            $config
        );

        ob_start();
        ?>
        <div class="shell">
            <div class="card">

                <!-- LOGIN VIEW -->
                <div id="lb-login-view">
                    <div class="headline">minime dashboard</div>
                    <div class="title">Sign in to minime</div>

                    <form id="lb-login-form" class="stack" autocomplete="on">
                        <div class="field">
                            <label for="lb-login-email">Email or username</label>
                            <input id="lb-login-email" type="text" autocomplete="username" placeholder="you@example.com" required />
                        </div>
                        <div class="field">
                            <label for="lb-login-password">Password</label>
                            <input id="lb-login-password" type="password" autocomplete="current-password" placeholder="••••••••" required />
                        </div>

                        <div class="checkbox-row">
                            <input id="lb-remember-me" type="checkbox" />
                            <label for="lb-remember-me">Remember me on this browser</label>
                        </div>

                        <div class="login-help">
                            <a href="#" id="lb-forgot-password">Forgot your password?</a>
                        </div>

                        <button type="submit" class="btn btn-primary">Log in</button>

                        <div class="status" id="lb-login-status"></div>
                    </form>
                    
                    <!-- Password Reset Form -->
                    <form id="lb-reset-form" class="stack" style="display:none;">
                        <div class="field">
                            <label for="lb-reset-email">Email Address</label>
                            <input id="lb-reset-email" type="email" autocomplete="email" placeholder="you@example.com" required />
                        </div>

                        <button type="submit" class="btn btn-primary">Send Reset Link</button>
                        <button type="button" id="lb-back-to-login" class="btn btn-ghost">Back to Login</button>

                        <div class="status" id="lb-reset-status"></div>
                    </form>
                </div>

                <!-- SETTINGS VIEW -->
                <div id="lb-settings-view" hidden>
                    <div class="topbar">
                        <div>
                            <div class="headline">minime settings</div>
                            <div class="title">Edit your public minime page</div>
                        </div>
                        <div class="stack-sm" style="align-items:flex-end;">
                            <button id="lb-logout-btn" class="btn btn-ghost btn-small">Log out</button>
                            <div class="status" id="lb-settings-status">Connected to WordPress.</div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- LEFT COLUMN: PROFILE + BACKGROUND -->
                        <div class="col stack">

                            <div class="section">
                                <div class="section-header">
                                    <div class="section-title">Profile (from WordPress)</div>
                                    <div class="pill">Identity</div>
                                </div>

                                <div class="field">
                                    <label for="lb-site-title">Site Title (Name)</label>
                                    <input id="lb-site-title" type="text" placeholder="Site title" />
                                </div>

                                <div class="field">
                                    <label for="lb-site-tagline">Tagline (Subtitle)</label>
                                    <input id="lb-site-tagline" type="text" placeholder="Short tagline" />
                                </div>

                                <div class="field">
                                    <label for="lb-bio">Bio (Card description)</label>
                                    <textarea id="lb-bio" placeholder="Write a short bio for the card"></textarea>
                                </div>

                                <div class="field">
                                    <label for="lb-footer-text">Footer Branding Text</label>
                                    <input id="lb-footer-text" type="text" placeholder="Powered by · Ayop · Headless WP · REST API" />
                                    <div class="bg-hint">This text appears in the footer of your link-in-bio card.</div>
                                </div>

                                <div class="field">
                                    <label>Avatar / Site Icon</label>
                                    <div class="list-row">
                                        <img id="lb-avatar-preview" class="avatar-preview" src="" alt="Avatar preview" />
                                        <div class="stack-sm">
                                            <input id="lb-avatar-file" type="file" accept="image/*" />
                                            <div class="bg-hint">This image will be used as both the card avatar and the site favicon.</div>
                                        </div>
                                    </div>
                                    <input id="lb-site-icon-id" type="hidden" />
                                </div>
                            </div>

                            <div class="section">
                                <div class="section-header">
                                    <div class="section-title">Background</div>
                                    <div class="pill">Visuals</div>
                                </div>

                                <div class="field">
                                    <label for="lb-bg-type">Background Type</label>
                                    <select id="lb-bg-type">
                                        <option value="image">Image</option>
                                        <option value="color">Solid Color</option>
                                        <option value="gradient">Gradient</option>
                                        <option value="custom">Custom HTML / CSS / JS</option>
                                    </select>
                                </div>

                                <!-- IMAGE -->
                                <div id="lb-bg-image-block" class="stack-sm">
                                    <div class="field">
                                        <label>Background Image</label>
                                        <div class="list-row">
                                            <input id="lb-bg-image-file" type="file" accept="image/*" />
                                            <button type="button" id="lb-bg-image-upload-btn" class="btn btn-small">Upload</button>
                                        </div>
                                        <input id="lb-bg-image-id" type="hidden" />
                                        <div class="bg-hint">Recommended large, high-quality image.</div>
                                    </div>
                                </div>

                                <!-- COLOR -->
                                <div id="lb-bg-color-block" class="stack-sm" hidden>
                                    <div class="field">
                                        <label for="lb-bg-color">Background Color</label>
                                        <input id="lb-bg-color" type="color" value="#000000" />
                                    </div>
                                </div>

                                <!-- GRADIENT -->
                                <div id="lb-bg-gradient-block" class="stack-sm" hidden>
                                    <div class="field">
                                        <label>Gradient Colors (2–3 colors)</label>
                                        <div class="list-row">
                                            <input id="lb-bg-grad-color-1" type="color" value="#111827" />
                                            <input id="lb-bg-grad-color-2" type="color" value="#1f2937" />
                                            <input id="lb-bg-grad-color-3" type="color" />
                                        </div>
                                    </div>
                                    <div class="field">
                                        <label for="lb-bg-gradient-angle">Gradient Angle (0–360)</label>
                                        <input id="lb-bg-gradient-angle" type="number" min="0" max="360" step="1" value="180" />
                                    </div>
                                </div>

                                <!-- CUSTOM CODE -->
                                <div id="lb-bg-custom-block" class="stack-sm" hidden>
                                    <div class="field">
                                        <label for="lb-bg-custom-code">Custom HTML / CSS / JS</label>
                                        <textarea id="lb-bg-custom-code" placeholder="Paste custom HTML/CSS/JS to render the background. PHP is not allowed."></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="section">
                                <div class="section-header">
                                    <div class="section-title">Card Background</div>
                                    <div class="pill">Visuals</div>
                                </div>

                                <div class="field">
                                    <label for="lb-card-bg-type">Card Background Type</label>
                                    <select id="lb-card-bg-type">
                                        <option value="solid">Solid Color</option>
                                        <option value="gradient">Gradient</option>
                                    </select>
                                </div>

                                <!-- SOLID COLOR -->
                                <div id="lb-card-bg-solid-block" class="stack-sm">
                                    <div class="field">
                                        <label for="lb-card-bg-color">Card Background Color</label>
                                        <input id="lb-card-bg-color" type="color" value="#ffffff" />
                                    </div>
                                </div>

                                <!-- GRADIENT -->
                                <div id="lb-card-bg-gradient-block" class="stack-sm" hidden>
                                    <div class="field">
                                        <label>Gradient Colors (2–3 colors)</label>
                                        <div class="list-row">
                                            <input id="lb-card-bg-grad-color-1" type="color" value="#ffffff" />
                                            <input id="lb-card-bg-grad-color-2" type="color" value="#eeeeee" />
                                            <input id="lb-card-bg-grad-color-3" type="color" />
                                        </div>
                                    </div>
                                    <div class="field">
                                        <label for="lb-card-bg-gradient-angle">Gradient Angle (0–360)</label>
                                        <input id="lb-card-bg-gradient-angle" type="number" min="0" max="360" step="1" value="135" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- RIGHT COLUMN: SOCIALS + BUTTONS + SAVE -->
                        <div class="col stack">

                            <div class="section">
                                <div class="section-header">
                                    <div class="section-title">Social Links</div>
                                    <div class="pill">Channels</div>
                                </div>

                                <div id="lb-socials-list" class="list"></div>

                                <button type="button" id="lb-add-social" class="btn btn-small btn-ghost">+ Add social link</button>
                            </div>

                            <div class="section">
                                <div class="section-header">
                                    <div class="section-title">Buttons</div>
                                    <div class="pill">Call to Action</div>
                                </div>

                                <div id="lb-buttons-list" class="list"></div>

                                <button type="button" id="lb-add-button" class="btn btn-small btn-ghost">+ Add button</button>
                            </div>

                            <div class="section">
                                <div class="section-header">
                                    <div class="section-title">Save</div>
                                </div>

                                <p class="bg-hint" style="margin-bottom:12px;">
                                    One single <strong>Save</strong> button to apply all changes:
                                    profile, background, socials and buttons.  
                                    Changes will update WordPress Site Title, Tagline and Site Icon automatically.
                                </p>

                                <label class="mm-toggle" style="display:flex;align-items:center;gap:8px;margin-bottom:16px;cursor:pointer;font-size:12px;">
                                    <input type="checkbox" id="mm-keep-homepage" style="cursor:pointer;">
                                    <span style="user-select:none;">Keep my current homepage (don't set minime as front page)</span>
                                </label>

                                <button type="button" id="lb-save-all" class="btn btn-primary" style="width:100%;margin-bottom:8px;">Save all changes</button>
                                <button type="button" id="mm-view-public" class="btn btn-secondary" style="width:100%;" disabled>View my minime</button>
                            </div>

                        </div>
                    </div>
                </div>

            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
