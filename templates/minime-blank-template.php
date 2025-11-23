<?php
/**
 * Template Name: Minime Link in Bio
 *
 * A minimal WordPress page template for displaying the minime link-in-bio card.
 * This template provides a fullscreen layout without theme header/footer interference.
 *
 * NOTE: This template is not auto-used by the plugin.
 * It is optional for custom theme integration.
 *
 * @package minime
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$site_icon_url = get_site_icon_url();
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo esc_html( get_bloginfo( 'name' ) ); ?></title>
    
    <?php if ( $site_icon_url ) : ?>
    <link rel="icon" type="image/png" href="<?php echo esc_url( $site_icon_url ); ?>">
    <link rel="apple-touch-icon" href="<?php echo esc_url( $site_icon_url ); ?>">
    <?php endif; ?>
    
    <?php wp_head(); ?>
</head>
<body <?php body_class( 'minime-template minime-link-in-bio' ); ?>>

<div id="minime-root">
    <?php echo do_shortcode( '[minime_link_in_bio]' ); ?>
</div>

<?php wp_footer(); ?>

</body>
</html>
