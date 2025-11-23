<?php
/**
 * Blank Template for Minime Pages
 * 
 * Provides a clean, minimal HTML document without theme interference.
 * Scripts and styles are enqueued through Minime_Templates class.
 *
 * @package minime
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get current post
global $post;

// Build page title
$page_title = get_bloginfo( 'name' );
if ( $post && $post->post_title ) {
    $page_title = $post->post_title . ' - ' . $page_title;
}

// Get site icon
$site_icon_url = get_site_icon_url();

// Get shortcode from post content (set during page creation)
$shortcode = $post ? $post->post_content : '[minime_link_in_bio]';
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo esc_html( $page_title ); ?></title>
    
    <?php if ( $site_icon_url ) : ?>
    <link rel="icon" type="image/png" href="<?php echo esc_url( $site_icon_url ); ?>">
    <link rel="apple-touch-icon" href="<?php echo esc_url( $site_icon_url ); ?>">
    <?php endif; ?>
    
    <!-- Font Awesome for social icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <?php wp_head(); ?>
</head>
<body <?php body_class( 'minime-page' ); ?>>

<div id="minime-root">
    <?php echo do_shortcode( $shortcode ); ?>
</div>

<?php wp_footer(); ?>

</body>
</html>
