<?php
/**
 * Minime Admin Shell Template
 *
 * Renders the compiled Next.js admin dashboard by loading the exported
 * static HTML from assets/admin-app/index.html and injecting WordPress
 * configuration and asset URL rewrites.
 *
 * Why read index.html instead of scanning for hashed bundles?
 * - Stability: Next.js hashes output filenames with content hashes (e.g., main-abc123.js)
 *   These change on every build. Reading index.html is the single source of truth.
 * - Correctness: The exported index.html already has correct URLs and asset references.
 * - Simplicity: No fragile glob patterns or filename assumptions.
 * - Performance: Single file read instead of directory scans.
 * - Maintainability: Works across Next.js versions without changes.
 *
 * @package Minime
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// =========================================================================
// DETERMINE PLUGIN ROOT PATH
// =========================================================================

// Get plugin root reliably using MINIME_PLUGIN_FILE if available
$plugin_file = defined( 'MINIME_PLUGIN_FILE' ) ? MINIME_PLUGIN_FILE : dirname( dirname( __FILE__ ) ) . '/minime.php';
$plugin_root = dirname( $plugin_file );
$html_file   = $plugin_root . '/assets/admin-app/index.html';

// =========================================================================
// CHECK IF BUILD EXISTS
// =========================================================================

if ( ! file_exists( $html_file ) ) {
    status_header( 404 );
    nocache_headers();
    header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );
    
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Minime Admin - Build Required</title>
        <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                margin: 0;
                background: #f5f5f5;
            }
            .error-container {
                background: white;
                padding: 40px;
                border-radius: 8px;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
                max-width: 600px;
                text-align: center;
            }
            h1 {
                color: #d32f2f;
                margin-top: 0;
            }
            p {
                color: #666;
                line-height: 1.6;
            }
            code {
                background: #f5f5f5;
                padding: 2px 6px;
                border-radius: 3px;
                font-family: 'Courier New', monospace;
                display: inline-block;
                margin: 20px 0;
                padding: 12px;
                border-left: 3px solid #d32f2f;
            }
            .path {
                color: #999;
                font-size: 12px;
                margin-top: 20px;
                padding-top: 20px;
                border-top: 1px solid #eee;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <h1>⚠️ Admin Build Not Found</h1>
            <p>The Next.js admin dashboard has not been built yet.</p>
            <p>To build and deploy, run:</p>
            <code>npm run deploy</code>
            <p>From the <code>admin-src/</code> directory in your plugin folder.</p>
            <div class="path">
                Expected file: <code><?php echo esc_html( $html_file ); ?></code>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// =========================================================================
// READ EXPORTED HTML
// =========================================================================

$html_content = file_get_contents( $html_file );

if ( false === $html_content ) {
    status_header( 500 );
    wp_die( 'Unable to read admin dashboard file.', 'Minime Admin Error' );
}

// =========================================================================
// COMPUTE PLUGIN BASE URL
// =========================================================================

// Use MINIME_PLUGIN_FILE if available for reliable URL computation
if ( defined( 'MINIME_PLUGIN_FILE' ) ) {
    $plugin_base_url = plugin_dir_url( MINIME_PLUGIN_FILE ) . 'assets/admin-app/';
} else {
    // Fallback: compute from template file path (dirname(__DIR__) goes up to plugin root)
    $plugin_base_url = plugin_dir_url( dirname( __DIR__ ) ) . 'assets/admin-app/';
}

// =========================================================================
// PREPARE CONFIGURATION
// =========================================================================

// Send proper response headers before output
status_header( 200 );
nocache_headers();
header( 'Content-Type: text/html; charset=' . get_bloginfo( 'charset' ) );

// Get REST root URL (full URL, not just prefix)
$rest_root = esc_url_raw( rest_url() );

// Get site configuration
$site_id = absint( function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 1 );
$public_url = esc_attr( home_url() );

// Get dynamic admin slug with fallback
$admin_slug = function_exists( 'minime_get_admin_slug' ) ? minime_get_admin_slug() : 'mm';
$admin_base_path = esc_attr( $admin_slug );

// Create nonce for REST API security
$nonce = wp_create_nonce( 'wp_rest' );

// Build admin config object
$admin_config = array(
    'restRoot'      => $rest_root,
    'nonce'         => $nonce,
    'siteId'        => $site_id,
    'publicUrl'     => $public_url,
    'adminBasePath' => $admin_base_path,
    'assetBase'     => $plugin_base_url,  // For runtime URL rewriting
);

// Generate the config script that will be injected
$config_script = sprintf(
    '<script>window.MINIME_ADMIN_CONFIG = %s;</script>',
    wp_json_encode( $admin_config )
);

// Add runtime asset URL rewriter
$asset_rewriter = <<<'SCRIPT'
<script>
// Intercept fetch requests to rewrite asset URLs
const originalFetch = window.fetch;
window.fetch = function(resource, config) {
  if (typeof resource === 'string') {
    // Rewrite /_next/ paths
    if (resource.startsWith('/_next/')) {
      resource = window.MINIME_ADMIN_CONFIG.assetBase + resource.substring(1);
    }
    // Rewrite /fonts/ paths
    else if (resource.startsWith('/fonts/')) {
      resource = window.MINIME_ADMIN_CONFIG.assetBase + 'fonts/' + resource.substring(7);
    }
  }
  return originalFetch.call(this, resource, config);
};

// Patch XMLHttpRequest too
const XHROpen = XMLHttpRequest.prototype.open;
XMLHttpRequest.prototype.open = function(method, url, ...args) {
  if (typeof url === 'string') {
    if (url.startsWith('/_next/')) {
      url = window.MINIME_ADMIN_CONFIG.assetBase + url.substring(1);
    } else if (url.startsWith('/fonts/')) {
      url = window.MINIME_ADMIN_CONFIG.assetBase + 'fonts/' + url.substring(7);
    }
  }
  return XHROpen.call(this, method, url, ...args);
};
</script>
SCRIPT;

// =========================================================================
// REWRITE ASSET URLS
// =========================================================================

// Strategy: Replace ALL instances of /_next/ and /fonts/ with the full plugin asset base URL
// This works because these paths only appear in the exported Next.js HTML

// 1. Replace /_next/... with plugin_base_url/_next/...
$html_content = str_replace( '/_next/', $plugin_base_url . '_next/', $html_content );

// 2. Replace /fonts/... with plugin_base_url/fonts/...
$html_content = str_replace( '/fonts/', $plugin_base_url . 'fonts/', $html_content );

// =========================================================================
// OUTPUT HTML WITH INJECTED CONFIG
// =========================================================================

// Inject config and asset rewriter before closing </head> (SINGLE replacement to avoid duplicates)
$html_content = str_replace(
    '</head>',
    $config_script . "\n" . $asset_rewriter . "\n</head>",
    $html_content
);

echo $html_content; // phpcs:ignore WordPress.Security.EscapedOutput.OutputNotEscaped -- HTML from Next.js build is trusted
