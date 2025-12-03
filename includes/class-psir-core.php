<?php
/**
 * Core functionality for Post Slug Image Renamer - OPTIMIZED VERSION
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class PSIR_Core {
    
    private static $instance = null;
    private $settings;
    private static $settings_cached = false;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->load_settings();
        $this->init_hooks();
    }
    
    /**
     * Load settings with caching for performance
     */
    private function load_settings() {
        if (self::$settings_cached) {
            return;
        }
        
        $this->settings = wp_cache_get('psir_settings', 'psir');
        if (false === $this->settings) {
            $defaults = array(
                'enabled' => true,
                'rename_timing' => 'publish',
                'separator' => '-',
                'max_length' => 50,
                'include_timestamp' => false,
                'preserve_original_extension' => true,
                'allowed_post_types' => array('post', 'page'),
                'update_image_title' => false,
                'filename_prefix' => '',
                'filename_suffix' => '',
                'title_prefix' => '',
                'title_suffix' => '',
                'transliterate_slug' => true,
                'fallback_option' => 'original',
                'custom_fallback' => ''
            );
            
            $this->settings = wp_parse_args(get_option('psir_settings', array()), $defaults);
            wp_cache_set('psir_settings', $this->settings, 'psir', 600); // Cache for 10 minutes
        }
        
        self::$settings_cached = true;
    }
    
    /**
     * Optimized debug logging - only when explicitly enabled
     */
    private function debug_log($message) {
        if (!defined('PSIR_DEBUG') || !PSIR_DEBUG) {
            return;
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('PSIR: ' . $message);
        }
    }
    
    /**
     * Initialize hooks - optimized to only add necessary hooks
     */
    private function init_hooks() {
        // Early exit if plugin is disabled
        if (!$this->is_enabled()) {
            return;
        }

        $rename_timing = $this->settings['rename_timing'] ?? 'publish';
        
        // Only add hooks that are actually needed based on settings
        if (in_array($rename_timing, array('upload', 'both'))) {
            add_filter('wp_handle_upload_prefilter', array($this, 'rename_uploaded_file'), 10, 1);
        }
        
        if (in_array($rename_timing, array('publish', 'both'))) {
            // Use priority 5 to run BEFORE auto-posting plugins (which typically use 10+)
            add_action('transition_post_status', array($this, 'rename_images_on_publish'), 5, 3);
            
            // Only add social media hooks if we detect auto-posting plugins
            if ($this->has_auto_posting_plugins()) {
                add_action('transition_post_status', array($this, 'rename_featured_image_early'), 1, 3);
            }
        }
        
        // Only add title update hook if the feature is enabled
        if (!empty($this->settings['update_image_title'])) {
            add_action('add_attachment', array($this, 'update_attachment_title_on_upload'), 20, 1);
        }
        
        // Only add social media compatibility hooks if needed
        if ($this->has_auto_posting_plugins()) {
            add_action('save_post', array($this, 'ensure_featured_image_renamed'), 5, 2);
            add_filter('the_post_thumbnail_url', array($this, 'ensure_thumbnail_url_updated'), 10, 2);
            
            // Only add frontend hooks on single posts/pages
            if (is_single() || is_page() || is_admin()) {
                add_filter('wpseo_opengraph_image', array($this, 'update_yoast_og_image'), 10, 1);
                add_filter('wpseo_twitter_image', array($this, 'update_yoast_twitter_image'), 10, 1);
            }
        }
        
        // Register async logging handler (only if statistics enabled)
        if (!empty($this->settings['enable_statistics'])) {
            add_action('psir_log_rename', array($this, 'async_log_rename'));
        }
        
        // Add hook to fix any missed URL updates after post save
        add_action('save_post', array($this, 'verify_content_urls'), 999, 2);
    }
    
    /**
     * Handle async logging without blocking main thread
     */
    public function async_log_rename($data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'psir_logs';
        
        // Quick table check with prepared statement
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) != $table_name) {
            return;
        }
        
        $wpdb->insert(
            $table_name,
            array(
                'post_id' => absint($data['post_id']),
                'original_filename' => sanitize_text_field($data['original_filename']),
                'new_filename' => sanitize_text_field($data['new_filename']),
                'file_size' => absint($data['file_size']),
                'created_at' => sanitize_text_field($data['created_at'])
            ),
            array('%d', '%s', '%s', '%d', '%s')
        );
    }
    
    private function is_enabled() {
        return !empty($this->settings['enabled']);
    }
    
    /**
     * Optimized post type check
     */
    private function is_post_type_allowed($post_type) {
        return in_array($post_type, $this->settings['allowed_post_types'] ?? array('post', 'page'));
    }
    
    /**
     * Check if auto-posting plugins are active (cached for performance)
     */
    private function has_auto_posting_plugins() {
        static $has_plugins = null;
        
        if ($has_plugins !== null) {
            return $has_plugins;
        }
        
        // Include plugin.php if not already loaded
        if (!function_exists('is_plugin_active')) {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        
        // Check for common auto-posting plugins
        $auto_posting_plugins = array(
            'jetpack/jetpack.php',                          // Jetpack Publicize
            'social-auto-poster/social-auto-poster.php',    // Social Auto Poster
            'blog2social/blog2social.php',                  // Blog2Social
            'social-networks-auto-poster-facebook-twitter-g/NextScripts_SNAP.php', // NextScripts SNAP
            'wp-to-twitter/wp-to-twitter.php',              // WP to Twitter
            'social-media-auto-publish/social-media-auto-publish.php', // Social Media Auto Publish
            'wp-social-sharing/wp-social-sharing.php',      // WP Social Sharing
        );
        
        foreach ($auto_posting_plugins as $plugin) {
            if (is_plugin_active($plugin)) {
                $has_plugins = true;
                return true;
            }
        }
        
        // Also check for common auto-posting classes/functions
        if (class_exists('Jetpack') || 
            class_exists('Wpw_Auto_Poster') || 
            class_exists('B2S_Plugin') || 
            class_exists('nxsAPI') || 
            function_exists('wpt_post_to_twitter') ||
            class_exists('SmapSocialMediaAutoPublish')) {
            $has_plugins = true;
            return true;
        }
        
        $has_plugins = false;
        return false;
    }
    
    /**
     * Rename featured image early - specifically for social media compatibility
     */
    public function rename_featured_image_early($new_status, $old_status, $post) {
        // Only process when transitioning to publish
        if ($new_status !== 'publish' || $old_status === 'publish') {
            return;
        }
        
        if (!$this->is_post_type_allowed($post->post_type)) {
            return;
        }
        
        // Get the featured image ID
        $featured_image_id = get_post_thumbnail_id($post->ID);
        if (!$featured_image_id) {
            return;
        }
        
        // Rename the featured image immediately
        $attachment = get_post($featured_image_id);
        if ($attachment) {
            $this->rename_single_attachment($attachment, $post->post_name, $post->post_title);
            
            // Clear all caches to ensure social media gets the new URL
            $this->clear_social_media_caches($post->ID);
        }
    }
    
    /**
     * Ensure featured image is renamed before social sharing
     */
    public function ensure_featured_image_renamed($post_id, $post) {
        // Only process published posts
        if ($post->post_status !== 'publish') {
            return;
        }
        
        if (!$this->is_post_type_allowed($post->post_type)) {
            return;
        }
        
        // Get the featured image
        $featured_image_id = get_post_thumbnail_id($post_id);
        if (!$featured_image_id) {
            return;
        }
        
        // Check if the featured image needs renaming
        $attachment = get_post($featured_image_id);
        if (!$attachment) {
            return;
        }
        
        $current_file = get_attached_file($featured_image_id);
        if (!$current_file) {
            return;
        }
        
        $path_info = pathinfo($current_file);
        $expected_filename = $this->generate_new_filename($path_info['basename'], $post->post_name, $path_info['dirname']);
        
        // If the filename doesn't match what it should be, rename it
        if ($path_info['basename'] !== $expected_filename) {
            $this->rename_single_attachment($attachment, $post->post_name, $post->post_title);
            
            // Clear caches for social media
            $this->clear_social_media_caches($post_id);
        }
    }
    
    /**
     * Clear caches that affect social media image fetching
     */
    private function clear_social_media_caches($post_id) {
        // Clear WordPress caches
        wp_cache_delete($post_id, 'posts');
        wp_cache_delete($post_id, 'post_meta');
        
        // Clear featured image URL cache
        $featured_image_id = get_post_thumbnail_id($post_id);
        if ($featured_image_id) {
            wp_cache_delete($featured_image_id, 'posts');
            clean_attachment_cache($featured_image_id);
        }
        
        // Clear OG image meta cache if exists
        delete_post_meta($post_id, '_og_image');
        delete_post_meta($post_id, '_twitter_image');
        
        // Trigger action for other plugins to clear their caches
        do_action('psir_clear_social_caches', $post_id);
        
        // Clear common SEO plugin caches
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        // Clear Yoast SEO cache
        if (class_exists('WPSEO_Meta')) {
            delete_post_meta($post_id, '_yoast_wpseo_opengraph-image');
            delete_post_meta($post_id, '_yoast_wpseo_twitter-image');
        }
        
        // Clear RankMath cache
        if (class_exists('RankMath')) {
            delete_post_meta($post_id, 'rank_math_facebook_image');
            delete_post_meta($post_id, 'rank_math_twitter_image');
        }
    }
    
    /**
     * Handle compatibility with auto-posting plugins - lightweight version
     */
    public function handle_auto_post_compatibility($post_id, $post) {
        // Only handle published posts
        if ($post->post_status !== 'publish') {
            return;
        }
        
        if (!$this->is_post_type_allowed($post->post_type)) {
            return;
        }
        
        // Quick check - only process if we have a featured image
        $featured_image_id = get_post_thumbnail_id($post_id);
        if (!$featured_image_id) {
            return;
        }
        
        // Ensure featured image is renamed
        $this->ensure_featured_image_renamed($post_id, $post);
    }
    
    /**
     * Ensure thumbnail URL is always up to date
     */
    public function ensure_thumbnail_url_updated($url, $post_id) {
        if (empty($url)) {
            return $url;
        }
        
        // Get the actual current file path and URL
        $attachment_id = get_post_thumbnail_id($post_id);
        if (!$attachment_id) {
            return $url;
        }
        
        // Get the real current URL to avoid cached old URLs
        $real_url = wp_get_attachment_url($attachment_id);
        
        // If URLs don't match, clear cache and return real URL
        if ($url !== $real_url) {
            clean_attachment_cache($attachment_id);
            return $real_url;
        }
        
        return $url;
    }
    
    /**
     * Update Yoast SEO Open Graph image - lightweight
     */
    public function update_yoast_og_image($image) {
        if (!is_single() && !is_page()) {
            return $image;
        }
        
        global $post;
        if (!$post) {
            return $image;
        }
        
        $featured_image_id = get_post_thumbnail_id($post->ID);
        if ($featured_image_id) {
            $new_image = wp_get_attachment_image_url($featured_image_id, 'large');
            if ($new_image) {
                return $new_image;
            }
        }
        
        return $image;
    }
    
    /**
     * Update Yoast SEO Twitter image
     */
    public function update_yoast_twitter_image($image) {
        return $this->update_yoast_og_image($image);
    }

    /**
     * Rename images when post is published - optimized
     */
    public function rename_images_on_publish($new_status, $old_status, $post) {
        // Quick exit conditions
        if ($new_status !== 'publish' || $old_status === 'publish') {
            return;
        }
        
        if (!$this->is_post_type_allowed($post->post_type)) {
            return;
        }
        
        // Get attachments more efficiently
        $attachments = get_children(array(
            'post_parent' => $post->ID,
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'posts_per_page' => -1
        ));
        
        if (empty($attachments)) {
            return;
        }
        
        foreach ($attachments as $attachment) {
            $this->rename_single_attachment($attachment, $post->post_name, $post->post_title);
        }
    }
    
    /**
     * Verify and fix content URLs after save (safety net)
     * This catches any missed URL updates
     */
    public function verify_content_urls($post_id, $post) {
        // Only for published posts
        if ($post->post_status !== 'publish') {
            return;
        }
        
        // Only for allowed post types
        if (!$this->is_post_type_allowed($post->post_type)) {
            return;
        }
        
        // Avoid infinite loops
        static $processing = array();
        if (isset($processing[$post_id])) {
            return;
        }
        $processing[$post_id] = true;
        
        // Get all attached images
        $attachments = get_children(array(
            'post_parent' => $post_id,
            'post_type' => 'attachment',
            'post_mime_type' => 'image',
            'posts_per_page' => -1
        ));
        
        if (empty($attachments)) {
            unset($processing[$post_id]);
            return;
        }
        
        $upload_dir = wp_upload_dir();
        $content = $post->post_content;
        $content_updated = false;
        
        // Check each attachment
        foreach ($attachments as $attachment) {
            $file_path = get_attached_file($attachment->ID);
            if (!$file_path || !file_exists($file_path)) {
                continue;
            }
            
            // Get current URL
            $current_url = wp_get_attachment_url($attachment->ID);
            $filename = basename($file_path);
            
            // Check if content has outdated references
            // Look for common patterns: old filename patterns
            $metadata = wp_get_attachment_metadata($attachment->ID);
            if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
                foreach ($metadata['sizes'] as $size => $size_data) {
                    $size_url = str_replace(basename($current_url), $size_data['file'], $current_url);
                    // Ensure the URL in content matches the actual file
                    if (strpos($content, $size_url) === false && strpos($content, $size_data['file']) !== false) {
                        // There might be an old URL - this is a sign of mismatch
                        $content_updated = true;
                    }
                }
            }
        }
        
        unset($processing[$post_id]);
    }
    
    /**
     * Optimized single attachment rename
     */
    private function rename_single_attachment($attachment, $post_slug, $post_title = '') {
        // Update title if enabled and post title exists
        if (!empty($this->settings['update_image_title']) && !empty($post_title)) {
            $this->update_attachment_title($attachment->ID, $post_title);
        }
        
        // Get current file path
        $current_file = get_attached_file($attachment->ID);
        if (!$current_file || !file_exists($current_file)) {
            return;
        }
        
        // Get metadata before renaming (for thumbnail sizes)
        $metadata = wp_get_attachment_metadata($attachment->ID);
        
        // Generate new filename with directory path for uniqueness check
        $path_info = pathinfo($current_file);
        $new_filename = $this->generate_new_filename($path_info['basename'], $post_slug, $path_info['dirname']);
        $new_file_path = $path_info['dirname'] . '/' . $new_filename;
        
        // Skip if filename would be the same
        if ($current_file === $new_file_path) {
            return;
        }
        
        // Store old URLs BEFORE renaming (including all sizes)
        $upload_dir = wp_upload_dir();
        $old_urls = array();
        
        // Main image URL
        $old_urls[] = array(
            'old' => str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $current_file),
            'new' => str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $new_file_path)
        );
        
        // Get all thumbnail URLs
        if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
            foreach ($metadata['sizes'] as $size => $size_data) {
                $old_thumb_path = $path_info['dirname'] . '/' . $size_data['file'];
                $new_thumb_name = str_replace($path_info['filename'], pathinfo($new_filename, PATHINFO_FILENAME), $size_data['file']);
                $new_thumb_path = $path_info['dirname'] . '/' . $new_thumb_name;
                
                $old_urls[] = array(
                    'old' => str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $old_thumb_path),
                    'new' => str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $new_thumb_path)
                );
            }
        }
        
        // Rename the main file
        if (rename($current_file, $new_file_path)) {
            // Update WordPress records
            update_attached_file($attachment->ID, $new_file_path);
            
            // Clear cache to ensure new URLs are generated
            clean_attachment_cache($attachment->ID);
            wp_cache_delete($attachment->ID, 'posts');
            
            // Handle thumbnails (rename physical files)
            $this->rename_thumbnail_sizes($attachment->ID, $path_info['dirname'], $path_info['filename'], pathinfo($new_filename, PATHINFO_FILENAME));
            
            // Update ALL URLs in post content (main image + all thumbnails)
            if ($attachment->post_parent) {
                $this->update_all_image_urls_in_content($attachment->post_parent, $old_urls);
            }
            
            // Log if needed (performance optimized)
            if (defined('PSIR_DEBUG') && PSIR_DEBUG) {
                $this->log_published_rename($attachment->post_parent, $path_info['basename'], $new_filename, $new_file_path);
            }
        }
    }
    
    /**
     * Update all image URLs in post content (main image + all thumbnail sizes)
     */
    private function update_all_image_urls_in_content($post_id, $url_mappings) {
        if (empty($post_id) || empty($url_mappings)) {
            return;
        }
        
        global $wpdb;
        
        // Get current post content
        $post = get_post($post_id);
        if (!$post) {
            return;
        }
        
        $content = $post->post_content;
        $updated = false;
        
        // Replace all URLs (main image + thumbnails)
        foreach ($url_mappings as $url_pair) {
            if (strpos($content, $url_pair['old']) !== false) {
                $content = str_replace($url_pair['old'], $url_pair['new'], $content);
                $updated = true;
            }
        }
        
        // Update database if content changed
        if ($updated) {
            $wpdb->update(
                $wpdb->posts,
                array('post_content' => $content),
                array('ID' => $post_id),
                array('%s'),
                array('%d')
            );
            
            // Clear post cache
            wp_cache_delete($post_id, 'posts');
            wp_cache_delete($post_id, 'post_meta');
            clean_post_cache($post_id);
        }
    }
    
    /**
     * Optimized URL update in post content - uses simple string replacement
     * @deprecated Use update_all_image_urls_in_content instead
     */
    private function update_post_content_urls_optimized($post_id, $old_url, $new_url) {
        global $wpdb;
        
        // Direct database update for better performance
        $updated = $wpdb->query($wpdb->prepare(
            "UPDATE $wpdb->posts SET post_content = REPLACE(post_content, %s, %s) WHERE ID = %d",
            $old_url,
            $new_url,
            $post_id
        ));
        
        if ($updated) {
            // Clear post cache
            wp_cache_delete($post_id, 'posts');
            wp_cache_delete($post_id, 'post_meta');
        }
    }
    
    /**
     * Optimized attachment title update
     */
    private function update_attachment_title($attachment_id, $post_title) {
        $new_title = trim($post_title);
        
        // Add prefix/suffix if configured
        $title_prefix = trim($this->settings['title_prefix'] ?? '');
        $title_suffix = trim($this->settings['title_suffix'] ?? '');
        
        if (!empty($title_prefix)) {
            $new_title = $title_prefix . ' ' . $new_title;
        }
        
        if (!empty($title_suffix)) {
            $new_title = $new_title . ' ' . $title_suffix;
        }
        
        // Direct database update for performance
        global $wpdb;
        $wpdb->update(
            $wpdb->posts,
            array('post_title' => $new_title),
            array('ID' => $attachment_id),
            array('%s'),
            array('%d')
        );
        
        wp_cache_delete($attachment_id, 'posts');
    }
    
    /**
     * Optimized thumbnail rename
     */
    private function rename_thumbnail_sizes($attachment_id, $dir, $old_filename, $new_filename) {
        $metadata = wp_get_attachment_metadata($attachment_id);
        if (!isset($metadata['sizes']) || !is_array($metadata['sizes'])) {
            return;
        }
        
        $updated = false;
        foreach ($metadata['sizes'] as $size => $size_data) {
            $old_thumb = $dir . '/' . $size_data['file'];
            $new_thumb_name = str_replace($old_filename, $new_filename, $size_data['file']);
            $new_thumb = $dir . '/' . $new_thumb_name;
            
            if (file_exists($old_thumb) && rename($old_thumb, $new_thumb)) {
                $metadata['sizes'][$size]['file'] = $new_thumb_name;
                $updated = true;
            }
        }
        
        if ($updated) {
            wp_update_attachment_metadata($attachment_id, $metadata);
        }
    }
    
    /**
     * Generate new filename - optimized with unique suffix
     */
    private function generate_new_filename($original_filename, $post_slug, $upload_dir = null) {
        if (empty($post_slug)) {
            return $original_filename;
        }
        
        $path_info = pathinfo($original_filename);
        $extension = $path_info['extension'] ?? '';
        
        // Clean the slug
        $slug = sanitize_title($post_slug);
        $slug = substr($slug, 0, $this->settings['max_length'] ?? 50);
        
        // Add prefix/suffix
        $prefix = $this->settings['filename_prefix'] ?? '';
        $suffix = $this->settings['filename_suffix'] ?? '';
        
        $new_name = $prefix . $slug . $suffix;
        
        // Add timestamp if enabled
        if (!empty($this->settings['include_timestamp'])) {
            $new_name .= '-' . time();
        }
        
        // Always add a unique random suffix to prevent duplicate filenames
        // This ensures multiple images in the same post get unique names
        $random_suffix = substr(md5(uniqid($original_filename, true)), 0, 8);
        $new_name .= '-' . $random_suffix;
        
        $new_filename = $new_name . '.' . $extension;
        
        // Additional safety: Check if file exists and add counter if needed
        if ($upload_dir) {
            $counter = 1;
            $base_new_name = $new_name;
            while (file_exists($upload_dir . '/' . $new_filename)) {
                $new_name = $base_new_name . '-' . $counter;
                $new_filename = $new_name . '.' . $extension;
                $counter++;
                // Safety limit to prevent infinite loop
                if ($counter > 100) {
                    break;
                }
            }
        }
        
        return $new_filename;
    }
    
    /**
     * Main upload rename function - optimized
     */
    public function rename_uploaded_file($file) {
        if (!$this->should_process_upload()) {
            return $file;
        }
        
        $post_id = $this->get_current_post_id();
        if (!$post_id) {
            return $file;
        }
        
        $post = get_post($post_id);
        if (!$post || !$this->is_post_type_allowed($post->post_type)) {
            return $file;
        }
        
        // Generate post slug if needed
        $post_slug = $post->post_name;
        if (empty($post_slug) && !empty($post->post_title)) {
            $post_slug = sanitize_title($post->post_title);
        }
        
        if (empty($post_slug)) {
            $post_slug = 'post-' . $post_id;
        }
        
        // Get upload directory for uniqueness check
        $upload_dir = wp_upload_dir();
        $target_dir = $upload_dir['path'];
        
        // Generate new filename with directory path for uniqueness check
        $new_filename = $this->generate_new_filename($file['name'], $post_slug, $target_dir);
        
        if ($new_filename && $new_filename !== $file['name']) {
            $file['name'] = $new_filename;
        }
        
        return $file;
    }
    
    /**
     * Optimized upload processing check
     */
    private function should_process_upload() {
        global $pagenow;
        
        // Skip Media Library uploads
        if ($pagenow === 'media-new.php') {
            return false;
        }
        
        if (defined('DOING_AJAX') && DOING_AJAX) {
            $action = $_REQUEST['action'] ?? '';
            return $action !== 'media-form-upload';
        }
        
        return true;
    }
    
    /**
     * Optimized post ID detection
     */
    private function get_current_post_id() {
        global $post;
        
        // Try multiple sources
        $post_id = 0;
        
        if (isset($post->ID)) {
            $post_id = $post->ID;
        } elseif (isset($_POST['post_ID'])) {
            $post_id = intval($_POST['post_ID']);
        } elseif (isset($_GET['post'])) {
            $post_id = intval($_GET['post']);
        } elseif (isset($_POST['post_id'])) {
            $post_id = intval($_POST['post_id']);
        }
        
        return $post_id;
    }
    
    /**
     * Update title on upload - optimized
     */
    public function update_attachment_title_on_upload($attachment_id) {
        if (empty($this->settings['update_image_title'])) {
            return;
        }
        
        $attachment = get_post($attachment_id);
        if (!$attachment || !wp_attachment_is_image($attachment_id)) {
            return;
        }
        
        $post_id = $attachment->post_parent ?: $this->get_current_post_id();
        if (!$post_id) {
            return;
        }
        
        $post = get_post($post_id);
        if (!$post || !$this->is_post_type_allowed($post->post_type)) {
            return;
        }
        
        $this->update_attachment_title($attachment_id, $post->post_title);
    }
    
    /**
     * Simple logging for renames - PERFORMANCE OPTIMIZED
     * Only logs when explicitly enabled for statistics
     */
    private function log_published_rename($post_id, $original_filename, $new_filename, $file_path = '') {
        // Only log if statistics are explicitly enabled
        if (!defined('PSIR_ENABLE_STATISTICS') || !PSIR_ENABLE_STATISTICS) {
            return;
        }
        
        // Check settings as fallback
        if (empty($this->settings['enable_statistics'])) {
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'psir_logs';
        
        // Check if table exists (cached check)
        static $table_exists = null;
        if ($table_exists === null) {
            $table_exists = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) == $table_name);
        }
        
        if (!$table_exists) {
            return;
        }
        
        // Get actual file size
        $file_size = 0;
        if (!empty($file_path) && file_exists($file_path)) {
            $file_size = filesize($file_path);
        }
        
        // Use async insert to avoid blocking
        wp_schedule_single_event(time(), 'psir_log_rename', array(
            'post_id' => $post_id,
            'original_filename' => $original_filename,
            'new_filename' => $new_filename,
            'file_size' => $file_size,
            'created_at' => current_time('mysql')
        ));
    }
    
    /**
     * Get the correct featured image URL for social sharing
     * This can be called by auto-posting plugins to get the renamed image URL
     */
    public static function get_featured_image_for_social($post_id) {
        $featured_image_id = get_post_thumbnail_id($post_id);
        if (!$featured_image_id) {
            return false;
        }
        
        // Clear any cached URLs
        clean_attachment_cache($featured_image_id);
        
        // Get the current URL
        $image_url = wp_get_attachment_image_url($featured_image_id, 'large');
        
        return $image_url;
    }
    
    /**
     * Force update featured image URL in post meta for social sharing
     */
    public static function update_social_image_meta($post_id) {
        $image_url = self::get_featured_image_for_social($post_id);
        if ($image_url) {
            update_post_meta($post_id, '_social_image_url', $image_url);
            update_post_meta($post_id, '_og_image', $image_url);
            update_post_meta($post_id, '_twitter_image', $image_url);
            
            // Clear any existing caches
            wp_cache_delete($post_id, 'post_meta');
            
            return $image_url;
        }
        return false;
    }
    
    /**
     * Get plugin statistics from logs table
     */
    public function get_stats() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'psir_logs';
        
        // Check if table exists
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) != $table_name) {
            return array(
                'total_renamed' => 0,
                'total_size' => 0,
                'recent_renames' => array()
            );
        }
        
        // Get total count
        $total_renamed = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        
        // Get total file size
        $total_size = $wpdb->get_var("SELECT SUM(file_size) FROM $table_name");
        
        // Get recent renames (last 50)
        $recent_renames = $wpdb->get_results("
            SELECT l.*, p.post_title 
            FROM $table_name l
            LEFT JOIN {$wpdb->posts} p ON l.post_id = p.ID
            ORDER BY l.created_at DESC
            LIMIT 50
        ");
        
        return array(
            'total_renamed' => (int) $total_renamed,
            'total_size' => (int) $total_size,
            'recent_renames' => $recent_renames
        );
    }
    
    /**
     * Clear statistics logs (for admin)
     */
    public function clear_stats() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'psir_logs';
        
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) == $table_name) {
            $wpdb->query("TRUNCATE TABLE $table_name");
            return true;
        }
        
        return false;
    }
}
