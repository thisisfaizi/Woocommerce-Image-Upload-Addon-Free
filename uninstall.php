<?php
/**
 * Uninstall script for Custom Product Image Upload plugin
 * 
 * This file handles the uninstallation process as required by Envato marketplace standards.
 * It provides user choice for data retention and follows WordPress best practices.
 * 
 * @package Custom_Product_Image_Upload
 * @since 1.1
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Only allow this to run during uninstall
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

/**
 * Enhanced uninstall function that respects user preferences and provides confirmation
 */
function cpiu_enhanced_uninstall() {
    // Get the uninstall preference, with null as default to detect if it's been explicitly set
    $keep_data_option = get_option('cpiu_keep_data_on_uninstall', null);
    
    // If no preference has been explicitly set, default to keeping data for safety
    if ($keep_data_option === null) {
        error_log('Custom Product Image Upload uninstalled: No preference set, defaulting to data preservation for safety.');
        return;
    }
    
    // Convert to boolean for explicit comparison
    $keep_data = (bool) $keep_data_option;
    
    // Explicitly check if user chose to keep data
    if ($keep_data === true) {
        // User explicitly chose to keep data, only remove the preference option itself
        delete_option('cpiu_keep_data_on_uninstall');
        error_log('Custom Product Image Upload uninstalled with data preservation (user choice).');
        return;
    }
    
    // User explicitly chose to delete data - proceed with cleanup
    error_log('Custom Product Image Upload: Proceeding with data deletion (user choice).');
    
    // Delete all plugin options
    $options_to_delete = array(
        'cpiu_multi_product_configs',
        'cpiu_default_settings',
        'cpiu_settings',
        'cpiu_keep_data_on_uninstall',
        'cpiu_installation_date',
        'cpiu_show_installation_notice',
        'cpiu_data_notice_dismissed'
    );
    
    foreach ($options_to_delete as $option) {
        delete_option($option);
    }
    
    // Clean up any transients
    delete_transient('cpiu_cdn_cache_last_refresh');
    
    // Clean up any user meta related to the plugin
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'cpiu_%'");
    
    // Clean up any post meta related to the plugin (including order item meta)
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE 'cpiu_%'");
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_cpiu_%'");
    
    // Clean up WooCommerce order item meta
    if (class_exists('WooCommerce')) {
        $wpdb->query("DELETE FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE meta_key LIKE 'cpiu_%'");
        $wpdb->query("DELETE FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE meta_key LIKE '_cpiu_%'");
    }
    
    // Clear any cached data
    wp_cache_flush();
    
    // Log uninstallation for debugging purposes
    error_log('Custom Product Image Upload uninstalled with data deletion.');
    
    // Note: We intentionally do NOT delete uploaded files as they might be referenced in orders
    // This is a safer approach for e-commerce plugins to maintain data integrity
}

// Run the enhanced uninstall function
cpiu_enhanced_uninstall();
