<?php
/**
 * Uninstall script for Post Slug Image Renamer
 * 
 * This file is executed when the plugin is deleted through the WordPress admin
 */

// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('psir_settings');

// Drop custom table
global $wpdb;
$table_name = $wpdb->prefix . 'psir_logs';
$wpdb->query("DROP TABLE IF EXISTS $table_name");

// Clear any cached data
wp_cache_flush();
