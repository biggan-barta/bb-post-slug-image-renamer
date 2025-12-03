<?php
/**
 * Social Media Compatibility Functions
 * 
 * These functions can be used by auto-posting plugins to ensure they get
 * the correct renamed image URLs for social media sharing.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get the correct featured image URL for a post after renaming
 * 
 * @param int $post_id The post ID
 * @return string|false The image URL or false if no image
 */
function psir_get_social_image_url($post_id) {
    return PSIR_Core::get_featured_image_for_social($post_id);
}

/**
 * Update social media meta tags with correct image URL
 * Call this before auto-posting to ensure correct image URLs
 * 
 * @param int $post_id The post ID
 * @return string|false The updated image URL or false
 */
function psir_update_social_meta($post_id) {
    return PSIR_Core::update_social_image_meta($post_id);
}

/**
 * Hook for auto-posting plugins to get the correct image URL - OPTIMIZED
 * Only runs if auto-posting plugins are detected
 */
if (class_exists('PSIR_Core')) {
    add_filter('psir_get_social_image', function($default_url, $post_id) {
        $renamed_url = psir_get_social_image_url($post_id);
        return $renamed_url ? $renamed_url : $default_url;
    }, 10, 2);
    
    add_action('psir_prepare_social_image', function($post_id) {
        psir_update_social_meta($post_id);
    });
}

/**
 * Lightweight filter for featured image URL - only when needed
 */
add_filter('get_the_post_thumbnail_url', function($url, $post_id) {
    // Only run during publishing actions to avoid performance impact on frontend
    if (!doing_action('transition_post_status') && !doing_action('publish_post') && !doing_action('save_post')) {
        return $url;
    }
    
    if (empty($url)) {
        return $url;
    }
    
    $updated_url = psir_get_social_image_url($post_id);
    return $updated_url ? $updated_url : $url;
}, 10, 2);

/**
 * Compatibility with common auto-posting plugins - LIGHTWEIGHT VERSION
 */
class PSIR_Social_Compatibility {
    
    private static $plugins_checked = false;
    private static $has_auto_plugins = false;
    
    public function __construct() {
        // Only initialize if we detect auto-posting plugins
        add_action('init', array($this, 'init_compatibility'), 20); // Lower priority
    }
    
    public function init_compatibility() {
        // Early exit if no auto-posting plugins detected
        if (!$this->has_auto_posting_plugins()) {
            return;
        }
        
        // Only add hooks for detected plugins
        $this->add_plugin_specific_hooks();
    }
    
    /**
     * Check for auto-posting plugins (cached)
     */
    private function has_auto_posting_plugins() {
        if (self::$plugins_checked) {
            return self::$has_auto_plugins;
        }
        
        // Include plugin.php if not already loaded
        if (!function_exists('is_plugin_active')) {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        
        // Check for specific plugin classes/functions only
        self::$has_auto_plugins = (
            class_exists('Jetpack') ||
            class_exists('Wpw_Auto_Poster') ||
            class_exists('B2S_Plugin') ||
            class_exists('nxsAPI') ||
            function_exists('wpt_post_to_twitter') ||
            class_exists('SmapSocialMediaAutoPublish')
        );
        
        self::$plugins_checked = true;
        return self::$has_auto_plugins;
    }
    
    /**
     * Add hooks only for detected plugins
     */
    private function add_plugin_specific_hooks() {
        // Jetpack Publicize
        if (class_exists('Jetpack')) {
            add_filter('jetpack_publicize_post_thumbnail', array($this, 'get_renamed_thumbnail'), 10, 2);
        }
        
        // Social Auto Poster
        if (class_exists('Wpw_Auto_Poster')) {
            add_filter('wpw_auto_poster_get_image', array($this, 'get_renamed_thumbnail'), 10, 2);
        }
        
        // Blog2Social
        if (class_exists('B2S_Plugin')) {
            add_filter('b2s_post_image', array($this, 'get_renamed_thumbnail'), 10, 2);
        }
        
        // NextScripts SNAP
        if (class_exists('nxsAPI')) {
            add_filter('nxs_snap_post_image', array($this, 'get_renamed_thumbnail'), 10, 2);
        }
        
        // WP to Twitter
        if (function_exists('wpt_post_to_twitter')) {
            add_filter('wpt_tweet_image', array($this, 'get_renamed_thumbnail'), 10, 2);
        }
        
        // Social Media Auto Publish
        if (class_exists('SmapSocialMediaAutoPublish')) {
            add_filter('smap_post_image', array($this, 'get_renamed_thumbnail'), 10, 2);
        }
    }
    
    /**
     * Get the renamed thumbnail URL for social sharing
     */
    public function get_renamed_thumbnail($image_url, $post_id) {
        $renamed_url = psir_get_social_image_url($post_id);
        return $renamed_url ? $renamed_url : $image_url;
    }
}

// Initialize compatibility
new PSIR_Social_Compatibility();
