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
        
        // Only add hooks that are actually needed
        if (in_array($rename_timing, array('upload', 'both'))) {
            add_filter('wp_handle_upload_prefilter', array($this, 'rename_uploaded_file'), 10, 1);
        }
        
        if (in_array($rename_timing, array('publish', 'both'))) {
            add_action('transition_post_status', array($this, 'rename_images_on_publish'), 10, 3);
        }
        
        // Only add title update hook if enabled
        if (!empty($this->settings['update_image_title'])) {
            add_action('add_attachment', array($this, 'update_attachment_title_on_upload'), 10, 1);
        }
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
        
        // Generate new filename with directory path for uniqueness check
        $path_info = pathinfo($current_file);
        $new_filename = $this->generate_new_filename($path_info['basename'], $post_slug, $path_info['dirname']);
        $new_file_path = $path_info['dirname'] . '/' . $new_filename;
        
        // Skip if filename would be the same
        if ($current_file === $new_file_path) {
            return;
        }
        
        // Rename the file
        if (rename($current_file, $new_file_path)) {
            $old_url = wp_get_attachment_url($attachment->ID);
            
            // Update WordPress records
            update_attached_file($attachment->ID, $new_file_path);
            
            // Generate new URL
            $upload_dir = wp_upload_dir();
            $new_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $new_file_path);
            
            // Clear minimal cache
            wp_cache_delete($attachment->ID, 'posts');
            
            // Update post content (optimized)
            $this->update_post_content_urls_optimized($attachment->post_parent, $old_url, $new_url);
            
            // Handle thumbnails
            $this->rename_thumbnail_sizes($attachment->ID, $path_info['dirname'], $path_info['filename'], pathinfo($new_filename, PATHINFO_FILENAME));
            
            // Log if needed
            if (defined('PSIR_DEBUG') && PSIR_DEBUG) {
                $this->log_published_rename($attachment->post_parent, $path_info['basename'], $new_filename);
            }
        }
    }
    
    /**
     * Optimized URL update in post content - uses simple string replacement
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
     * Simple logging for renames - only if debug enabled
     */
    private function log_published_rename($post_id, $original_filename, $new_filename) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'psir_logs';
        
        $wpdb->insert(
            $table_name,
            array(
                'post_id' => $post_id,
                'original_filename' => $original_filename,
                'new_filename' => $new_filename,
                'file_size' => 0,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%d', '%s')
        );
    }
}
