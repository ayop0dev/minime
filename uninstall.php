<?php
/**
 * Uninstall script for minime plugin.
 * 
 * Executed when the plugin is deleted via WordPress admin.
 * Cleans up all plugin data from the database.
 *
 * @package minime
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * Clean up minime data for a single site.
 */
function minime_uninstall_site() {
    // Get page IDs before deleting options
    $front_page_id = (int) get_option( 'minime_front_page_id', 0 );
    $admin_page_id = (int) get_option( 'minime_admin_page_id', 0 );
    
    // Delete plugin options
    delete_option( 'minime_settings' );
    delete_option( 'minime_front_page_id' );
    delete_option( 'minime_admin_page_id' );
    
    // Force delete pages (bypass trash)
    if ( $front_page_id > 0 ) {
        wp_delete_post( $front_page_id, true );
    }
    
    if ( $admin_page_id > 0 ) {
        wp_delete_post( $admin_page_id, true );
    }
    
    // Reset front page if it was set to minime page
    $current_front_page = (int) get_option( 'page_on_front', 0 );
    if ( $current_front_page === $front_page_id ) {
        delete_option( 'page_on_front' );
        update_option( 'show_on_front', 'posts' );
    }
    
    // Clean up API token transients
    global $wpdb;
    $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_minime_api_token_%'" );
    $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_minime_api_token_%'" );
}

// Single site cleanup
minime_uninstall_site();

// Multisite cleanup
if ( is_multisite() ) {
    global $wpdb;
    
    $blog_ids = $wpdb->get_col( "SELECT blog_id FROM {$wpdb->blogs}" );
    
    foreach ( $blog_ids as $blog_id ) {
        switch_to_blog( $blog_id );
        minime_uninstall_site();
        restore_current_blog();
    }
}
