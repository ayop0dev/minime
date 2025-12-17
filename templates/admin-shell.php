<?php
/**
 * Minime Admin Shell (Next.js static export loader)
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$plugin_file = dirname(__DIR__) . '/minime.php';
$assets_dir  = dirname(__DIR__) . '/assets/admin-app/';
$index_file  = $assets_dir . 'index.html';

$assets_url = plugins_url( 'assets/admin-app/', $plugin_file );

if ( ! file_exists( $index_file ) ) :
	?>
	<!doctype html>
	<html lang="en">
	<head>
		<meta charset="utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<title>Minime Admin - Build Required</title>
		<style>
			body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;margin:40px;max-width:900px;line-height:1.6}
			code,pre{background:#f5f5f5;padding:2px 6px;border-radius:6px}
			pre{padding:12px;overflow:auto}
		</style>
	</head>
	<body>
		<h1>Admin Build Not Found</h1>
		<p>The Next.js admin dashboard build is missing.</p>
		<p>Run this from <code>admin-src/</code>:</p>
		<pre><code>npm run deploy</code></pre>
	</body>
	</html>
	<?php
	exit;
endif;

$html = file_get_contents( $index_file );
if ( ! is_string( $html ) || $html === '' ) {
	wp_die( esc_html__( 'Minime admin build exists but could not be read.', 'minime' ) );
}

$nonce           = wp_create_nonce( 'wp_rest' );
$rest_root       = esc_url_raw( rest_url() );
$site_id         = get_current_blog_id();
$admin_base_path = home_url( '/' . minime_get_admin_slug() . '/' );

$front_id   = (int) get_option( 'minime_front_page_id' );
$public_url = $front_id ? get_permalink( $front_id ) : home_url( '/minime/' );

$config = array(
	'nonce'         => $nonce,
	'restRoot'      => trailingslashit( $rest_root ),
	'siteId'        => $site_id,
	'publicUrl'     => $public_url,
	'adminBasePath' => trailingslashit( $admin_base_path ),
	'assetBase'     => trailingslashit( $assets_url ),
);

$config_script = '<script>window.MINIME_ADMIN_CONFIG=' . wp_json_encode( $config ) . ';</script>';

$html = str_replace( '/_next/', trailingslashit( $assets_url ) . '_next/', $html );
$html = str_replace( '/fonts/', trailingslashit( $assets_url ) . 'fonts/', $html );

if ( strpos( $html, '</head>' ) !== false ) {
	$html = str_replace( '</head>', $config_script . "\n</head>", $html );
} else {
	$html = $config_script . "\n" . $html;
}

echo $html; // phpcs:ignore WordPress.Security.EscapedOutput.OutputNotEscaped
