<?php
/**
 * Core functionality for Post Slug Image Renamer
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class PSIR_Core {
    
    private static $instance = null;
    private $settings;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Get settings with proper defaults
        $defaults = array(
            'enabled' => true,
            'rename_timing' => 'publish',
            'separator' => '-',
            'max_length' => 50,
            'include_timestamp' => false,
            'preserve_original_extension' => true,
            'allowed_post_types' => array('post', 'page'),
            'update_image_title' => false, // Default to false for safety
            'filename_prefix' => '',
            'filename_suffix' => '',
            'title_prefix' => '',
            'title_suffix' => '',
            'transliterate_slug' => true,
            'fallback_option' => 'original',
            'custom_fallback' => ''
        );
        
        $this->settings = wp_parse_args(get_option('psir_settings', array()), $defaults);
        
        // Debug log construction
        $this->debug_log('PSIR_Core constructed. Settings: ' . print_r($this->settings, true));
        
        $this->init_hooks();
    }
    
    /**
     * Debug logging function
     */
    private function debug_log($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('PSIR Debug: ' . $message);
        }
        
        // Also write to plugin debug file
        $log_file = PSIR_PLUGIN_PATH . 'debug.log';
        $timestamp = date('Y-m-d H:i:s');
        
        // Make sure we can write to the file
        $log_message = "[$timestamp] $message\n";
        
        // Try to write to the file
        $result = @file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
        
        // If writing failed, try creating the file first
        if ($result === false) {
            @file_put_contents($log_file, "[$timestamp] Debug log created\n", LOCK_EX);
            @file_put_contents($log_file, $log_message, FILE_APPEND | LOCK_EX);
        }
    }
    
    private function init_hooks() {
        // Always log this for debugging
        $this->debug_log('init_hooks called. Plugin enabled: ' . ($this->is_enabled() ? 'yes' : 'no'));
        
        // Add a test hook to make sure our logging works
        add_action('init', array($this, 'test_hook'), 10);
        
        // Only process if plugin is enabled
        if (!$this->is_enabled()) {
            $this->debug_log('Plugin is disabled - no hooks registered');
            return;
        }
        
        // Hook into wp_handle_upload_prefilter to rename files before upload
        $rename_timing = $this->settings['rename_timing'] ?? 'publish';
        
        if (in_array($rename_timing, array('upload', 'both'))) {
            add_filter('wp_handle_upload_prefilter', array($this, 'rename_uploaded_file'), 10, 1);
            $this->debug_log('wp_handle_upload_prefilter hook registered');
        }
        
        // Try additional upload hooks for debugging
        add_filter('wp_handle_upload', array($this, 'handle_upload_after'), 10, 2);
        add_action('wp_handle_upload_prefilter', array($this, 'debug_prefilter'), 5, 1);
        
        // Hook to rename images when post is published
        if (in_array($rename_timing, array('publish', 'both'))) {
            add_action('transition_post_status', array($this, 'rename_images_on_publish'), 10, 3);
            $this->debug_log('transition_post_status hook registered for publish rename');
        }
        
        // Hook to log renamed files
        add_action('add_attachment', array($this, 'log_renamed_file'), 10, 1);
        $this->debug_log('add_attachment hook registered');
        
        // Also hook into attachment upload to update title immediately if needed
        add_action('add_attachment', array($this, 'update_attachment_title_on_upload'), 10, 1);
        $this->debug_log('add_attachment title update hook registered');
    }
    
    /**
     * Test hook to verify logging is working
     */
    public function test_hook() {
        $this->debug_log('Test hook fired - logging is working!');
    }
    
    /**
     * Debug the prefilter hook
     */
    public function debug_prefilter($file) {
        $this->debug_log('wp_handle_upload_prefilter fired with file: ' . print_r($file, true));
        return $file;
    }
    
    /**
     * Handle upload after processing
     */
    public function handle_upload_after($upload, $context) {
        $this->debug_log('wp_handle_upload fired with upload: ' . print_r($upload, true));
        return $upload;
    }
    
    /**
     * Rename images when post status transitions to published
     */
    public function rename_images_on_publish($new_status, $old_status, $post) {
        $this->debug_log("Post status transition: $old_status -> $new_status for post ID: {$post->ID}");
        
        // Only process when publishing
        if ($new_status !== 'publish' || $old_status === 'publish') {
            $this->debug_log('Not a publish transition - skipping image rename');
            return;
        }
        
        // Check if post type is allowed
        if (!$this->is_post_type_allowed($post->post_type)) {
            $this->debug_log('Post type not allowed: ' . $post->post_type);
            return;
        }
        
        $this->debug_log("Post published: {$post->post_title} (Slug: {$post->post_name})");
        
        // Get all attachments for this post
        $attachments = get_posts(array(
            'post_type' => 'attachment',
            'post_parent' => $post->ID,
            'post_status' => 'inherit',
            'posts_per_page' => -1
        ));
        
        $this->debug_log('Found ' . count($attachments) . ' attachments for post ' . $post->ID);
        
        foreach ($attachments as $attachment) {
            $this->rename_single_attachment($attachment, $post->post_name, $post->post_title);
        }
    }
    
    /**
     * Rename a single attachment file
     */
    private function rename_single_attachment($attachment, $post_slug, $post_title = '') {
        $this->debug_log("Processing attachment ID: {$attachment->ID} (Title: {$attachment->post_title})");
        
        // Update image title if enabled
        $this->debug_log('Checking title update - Setting enabled: ' . ($this->settings['update_image_title'] ? 'yes' : 'no') . ', Post title: "' . $post_title . '"');
        
        if (!empty($this->settings['update_image_title'])) {
            if (!empty($post_title)) {
                $this->update_attachment_title($attachment->ID, $post_title);
            } else {
                $this->debug_log('Post title is empty - skipping title update');
            }
        } else {
            $this->debug_log('Title update is disabled in settings');
        }
        
        // Get current file path
        $current_file = get_attached_file($attachment->ID);
        if (!$current_file || !file_exists($current_file)) {
            $this->debug_log('Attachment file not found: ' . $current_file);
            return;
        }
        
        $this->debug_log('Current file path: ' . $current_file);
        
        // Check if it's an image
        if (!wp_attachment_is_image($attachment->ID)) {
            $this->debug_log('Attachment is not an image - skipping file rename');
            return;
        }
        
        // Generate new filename
        $path_info = pathinfo($current_file);
        $new_filename = $this->generate_new_filename($path_info['basename'], $post_slug);
        $new_file_path = $path_info['dirname'] . '/' . $new_filename;
        
        $this->debug_log("New file path: $new_file_path");
        
        // Skip if filename would be the same
        if ($current_file === $new_file_path) {
            $this->debug_log('Filename already correct - skipping');
            return;
        }
        
        // Rename the main file
        if (rename($current_file, $new_file_path)) {
            $this->debug_log("Successfully renamed: $current_file -> $new_file_path");
            
            // Update attachment metadata
            update_attached_file($attachment->ID, $new_file_path);
            
            // Update attachment URL in database
            $upload_dir = wp_upload_dir();
            $new_url = str_replace($upload_dir['basedir'], $upload_dir['baseurl'], $new_file_path);
            $old_url = wp_get_attachment_url($attachment->ID);
            
            $this->debug_log("Updating URL: $old_url -> $new_url");
            
            // Update post content to replace old URLs with new ones
            $this->update_post_content_urls($attachment->post_parent, $old_url, $new_url);
            
            // Rename thumbnail sizes
            $this->rename_thumbnail_sizes($attachment->ID, $path_info['dirname'], $path_info['filename'], pathinfo($new_filename, PATHINFO_FILENAME));
            
            // Log the rename
            $this->log_published_rename($attachment->post_parent, $path_info['basename'], $new_filename);
            
        } else {
            $this->debug_log("Failed to rename file: $current_file");
        }
    }
    
    /**
     * Update attachment title with post title
     */
    private function update_attachment_title($attachment_id, $post_title) {
        $this->debug_log("Updating attachment title for ID: $attachment_id with post title: '$post_title'");
        
        // Get current attachment
        $attachment = get_post($attachment_id);
        if (!$attachment) {
            $this->debug_log('Attachment not found for title update');
            return;
        }
        
        $this->debug_log("Current attachment title: '{$attachment->post_title}'");
        
        // Prepare new title
        $new_title = trim($post_title);
        
        // Add prefix/suffix to title if desired
        $title_prefix = trim($this->settings['title_prefix'] ?? '');
        $title_suffix = trim($this->settings['title_suffix'] ?? '');
        
        if (!empty($title_prefix)) {
            $new_title = $title_prefix . ' ' . $new_title;
        }
        
        if (!empty($title_suffix)) {
            $new_title = $new_title . ' ' . $title_suffix;
        }
        
        $this->debug_log("New title will be: '$new_title'");
        
        // Skip if title would be the same
        if ($attachment->post_title === $new_title) {
            $this->debug_log('Title is already correct - skipping update');
            return;
        }
        
        // Update the attachment post - ONLY the title
        $update_data = array(
            'ID' => $attachment_id,
            'post_title' => $new_title
            // Note: We're NOT updating post_excerpt (caption) or alt text
        );
        
        $this->debug_log('Calling wp_update_post with data: ' . print_r($update_data, true));
        
        $updated = wp_update_post($update_data, true);
        
        if (is_wp_error($updated)) {
            $this->debug_log('Failed to update attachment title: ' . $updated->get_error_message());
        } else {
            $this->debug_log("Successfully updated attachment title from '{$attachment->post_title}' to '$new_title'");
            $this->debug_log("Caption and alt text were left unchanged");
            
            // Clear any caches
            clean_post_cache($attachment_id);
            $this->debug_log("Cleared post cache for attachment $attachment_id");
        }
    }
    
    /**
     * Rename thumbnail sizes
     */
    private function rename_thumbnail_sizes($attachment_id, $dir, $old_filename, $new_filename) {
        $metadata = wp_get_attachment_metadata($attachment_id);
        if (!isset($metadata['sizes']) || !is_array($metadata['sizes'])) {
            return;
        }
        
        foreach ($metadata['sizes'] as $size => $size_data) {
            $old_thumb = $dir . '/' . $size_data['file'];
            $new_thumb_name = str_replace($old_filename, $new_filename, $size_data['file']);
            $new_thumb = $dir . '/' . $new_thumb_name;
            
            if (file_exists($old_thumb)) {
                if (rename($old_thumb, $new_thumb)) {
                    $metadata['sizes'][$size]['file'] = $new_thumb_name;
                    $this->debug_log("Renamed thumbnail $size: $old_thumb -> $new_thumb");
                }
            }
        }
        
        wp_update_attachment_metadata($attachment_id, $metadata);
    }
    
    /**
     * Update post content URLs
     */
    private function update_post_content_urls($post_id, $old_url, $new_url) {
        $post = get_post($post_id);
        if (!$post) return;
        
        $updated_content = str_replace($old_url, $new_url, $post->post_content);
        
        if ($updated_content !== $post->post_content) {
            wp_update_post(array(
                'ID' => $post_id,
                'post_content' => $updated_content
            ));
            $this->debug_log("Updated post content URLs for post $post_id");
        }
    }
    
    /**
     * Log renamed file on publish
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
                'file_size' => 0, // We could get this but it's not critical
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%d', '%s')
        );
        
        $this->debug_log("Logged rename: $original_filename -> $new_filename");
    }
    
    /**
     * Update attachment title when uploaded (immediate update)
     */
    public function update_attachment_title_on_upload($attachment_id) {
        // Only do this if title update is enabled and we're in the right context
        if (empty($this->settings['update_image_title'])) {
            return;
        }
        
        $this->debug_log("Title update on upload for attachment: $attachment_id");
        
        // Get the attachment
        $attachment = get_post($attachment_id);
        if (!$attachment || !wp_attachment_is_image($attachment_id)) {
            return;
        }
        
        // Try to get the post ID this attachment belongs to
        $post_id = $attachment->post_parent;
        if (!$post_id) {
            // Try to get post ID from upload context
            $post_id = $this->get_current_post_id();
        }
        
        if (!$post_id) {
            $this->debug_log("No post ID found for attachment $attachment_id during upload");
            return;
        }
        
        $post = get_post($post_id);
        if (!$post) {
            $this->debug_log("Post not found for ID: $post_id");
            return;
        }
        
        // Only update if post type is allowed
        if (!$this->is_post_type_allowed($post->post_type)) {
            return;
        }
        
        $this->debug_log("Updating title on upload for attachment $attachment_id with post title: '{$post->post_title}'");
        
        // Update the title
        $this->update_attachment_title($attachment_id, $post->post_title);
    }
    private function is_enabled() {
        return !empty($this->settings['enabled']);
    }
    
    /**
     * Main function to rename uploaded files
     */
    public function rename_uploaded_file($file) {
        // Debug logging
        $this->debug_log('rename_uploaded_file called with file: ' . print_r($file, true));
        
        // Check if we should process this upload
        if (!$this->should_process_upload()) {
            $this->debug_log('Upload processing skipped');
            return $file;
        }
        
        // Get current post context
        $post_id = $this->get_current_post_id();
        $this->debug_log('Post ID detected: ' . $post_id);
        
        if (!$post_id) {
            $this->debug_log('No post ID found');
            return $file;
        }
        
        // Get post slug
        $post = get_post($post_id);
        if (!$post) {
            $this->debug_log('Post not found for ID: ' . $post_id);
            return $file;
        }
        
        $this->debug_log('Post found - Title: ' . $post->post_title . ', Status: ' . $post->post_status . ', Type: ' . $post->post_type);
        $this->debug_log('Post slug (post_name): "' . $post->post_name . '"');
        $this->debug_log('Post slug length: ' . strlen($post->post_name));
        
        // If post slug is empty, generate one from the title
        $post_slug = $post->post_name;
        if (empty($post_slug) && !empty($post->post_title)) {
            $post_slug = sanitize_title($post->post_title);
            $this->debug_log('Generated slug from title: ' . $post_slug);
        }
        
        // If still empty, use a fallback
        if (empty($post_slug)) {
            $post_slug = 'post-' . $post_id;
            $this->debug_log('Using fallback slug: ' . $post_slug);
        }
        
        // Check if post type is allowed
        if (!$this->is_post_type_allowed($post->post_type)) {
            $this->debug_log('Post type not allowed: ' . $post->post_type);
            return $file;
        }
        
        // Generate new filename
        $new_filename = $this->generate_new_filename($file['name'], $post_slug);
        $this->debug_log('Generated filename: ' . $new_filename);
        
        if ($new_filename && $new_filename !== $file['name']) {
            // Store original filename for logging
            $file['psir_original_name'] = $file['name'];
            $file['name'] = $new_filename;
            $this->debug_log('File renamed from ' . $file['psir_original_name'] . ' to ' . $file['name']);
        }
        
        return $file;
    }
    
    /**
     * Check if we should process this upload
     */
    private function should_process_upload() {
        $this->debug_log('should_process_upload called');
        
        // Check current page and context
        global $pagenow;
        $this->debug_log('Current page: ' . ($pagenow ?? 'unknown'));
        
        // For now, let's be less restrictive and process most uploads
        // We'll rely on post ID detection to determine if it's from a post context
        
        // Only skip if we're absolutely sure it's a direct Media Library upload
        if ($pagenow === 'media-new.php') {
            $this->debug_log('Direct Media Library upload page - skipping');
            return false;
        }
        
        // Handle AJAX uploads - be more permissive
        if (defined('DOING_AJAX') && DOING_AJAX) {
            $action = $_REQUEST['action'] ?? '';
            $this->debug_log('AJAX upload detected. Action: ' . $action);
            
            // Only skip very specific Media Library actions
            if ($action === 'media-form-upload') {
                $this->debug_log('Direct Media Library AJAX action - skipping');
                return false;
            }
            
            // Allow all other AJAX uploads (including upload-attachment which can be from post editor)
            $this->debug_log('AJAX action allowed - processing');
            return true;
        }
        
        $this->debug_log('Regular upload - processing');
        return true;
    }
    
    /**
     * Check if upload is from Media Library
     */
    private function is_media_library_upload() {
        global $pagenow;
        $this->debug_log('Checking if Media Library upload. Current page: ' . ($pagenow ?? 'unknown'));
        
        // Check if we're on upload.php or media-new.php
        if (in_array($pagenow, array('upload.php', 'media-new.php', 'async-upload.php'))) {
            $this->debug_log('On Media Library page - returning true');
            return true;
        }
        
        // Check referer
        $referer = wp_get_referer();
        $this->debug_log('Referer: ' . ($referer ?? 'none'));
        
        if ($referer && (strpos($referer, 'upload.php') !== false || strpos($referer, 'media-new.php') !== false)) {
            $this->debug_log('Referer indicates Media Library - returning true');
            return true;
        }
        
        $this->debug_log('Not a Media Library upload - returning false');
        return false;
    }
    
    /**
     * Get current post ID from various sources
     */
    private function get_current_post_id() {
        global $post;
        
        $this->debug_log('Getting current post ID');
        
        // Try to get post ID from various sources
        $post_id = 0;
        
        // From global $post
        if (isset($post->ID)) {
            $post_id = $post->ID;
            $this->debug_log('Post ID from global $post: ' . $post_id);
        }
        
        // From $_POST
        if (!$post_id && isset($_POST['post_ID'])) {
            $post_id = intval($_POST['post_ID']);
            $this->debug_log('Post ID from $_POST: ' . $post_id);
        }
        
        // From $_GET
        if (!$post_id && isset($_GET['post'])) {
            $post_id = intval($_GET['post']);
            $this->debug_log('Post ID from $_GET: ' . $post_id);
        }
        
        // From $_POST (alternative)
        if (!$post_id && isset($_POST['post_id'])) {
            $post_id = intval($_POST['post_id']);
            $this->debug_log('Post ID from $_POST[post_id]: ' . $post_id);
        }
        
        // From HTTP_REFERER parameter
        if (!$post_id && isset($_POST['_wp_http_referer'])) {
            $referer = $_POST['_wp_http_referer'];
            if (preg_match('/post=(\d+)/', $referer, $matches)) {
                $post_id = intval($matches[1]);
                $this->debug_log('Post ID from HTTP_REFERER: ' . $post_id);
            }
        }
        
        // From referer
        if (!$post_id) {
            $referer = wp_get_referer();
            if ($referer && preg_match('/post=(\d+)/', $referer, $matches)) {
                $post_id = intval($matches[1]);
                $this->debug_log('Post ID from wp_get_referer: ' . $post_id);
            }
        }
        
        // Debug all available data
        $this->debug_log('$_POST data: ' . print_r($_POST, true));
        $this->debug_log('$_GET data: ' . print_r($_GET, true));
        
        $this->debug_log('Final post ID: ' . $post_id);
        return $post_id;
    }
    
    /**
     * Check if post type is allowed for renaming
     */
    private function is_post_type_allowed($post_type) {
        $allowed_types = $this->settings['allowed_post_types'] ?? array('post', 'page');
        return in_array($post_type, $allowed_types);
    }
    
    /**
     * Generate new filename based on post slug
     */
    private function generate_new_filename($original_filename, $post_slug) {
        $this->debug_log('generate_new_filename called with original: ' . $original_filename . ', slug: "' . $post_slug . '"');
        
        // Get file extension
        $file_info = pathinfo($original_filename);
        $extension = isset($file_info['extension']) ? $file_info['extension'] : '';
        
        // Process the slug with multi-language support and fallback
        $slug = $this->process_slug_with_fallback($post_slug, $original_filename);
        
        $this->debug_log('Processed slug: "' . $slug . '"');
        
        // Apply max length
        $max_length = $this->settings['max_length'] ?? 50;
        if (strlen($slug) > $max_length) {
            $slug = substr($slug, 0, $max_length);
        }
        
        // Get prefix and suffix
        $prefix = $this->settings['filename_prefix'] ?? '';
        $suffix = $this->settings['filename_suffix'] ?? '';
        $separator = $this->settings['separator'] ?? '-';
        
        // Build filename with prefix/suffix
        $filename = '';
        
        // Add prefix with separator
        if (!empty($prefix)) {
            $prefix_clean = sanitize_file_name($prefix);
            // Remove trailing separator if exists
            $prefix_clean = rtrim($prefix_clean, '-_');
            $filename .= $prefix_clean . $separator;
            $this->debug_log('Added prefix with separator: ' . $prefix_clean . $separator);
        }
        
        // Add main slug
        $filename .= $slug;
        
        // Add suffix with separator
        if (!empty($suffix)) {
            $suffix_clean = sanitize_file_name($suffix);
            // Remove leading separator if exists
            $suffix_clean = ltrim($suffix_clean, '-_');
            $filename .= $separator . $suffix_clean;
            $this->debug_log('Added suffix with separator: ' . $separator . $suffix_clean);
        }
        
        // Add timestamp if needed
        if (!empty($this->settings['include_timestamp'])) {
            $filename .= $separator . time();
        }
        
        // Add random suffix to avoid conflicts
        $filename .= $separator . wp_generate_password(6, false);
        
        // Add extension
        if ($extension) {
            $filename .= '.' . $extension;
        }
        
        $this->debug_log('Final generated filename: ' . $filename);
        return $filename;
    }
    
    /**
     * Process slug with multi-language support and fallback options
     */
    private function process_slug_with_fallback($post_slug, $original_filename) {
        $this->debug_log('Processing slug with fallback. Original slug: "' . $post_slug . '"');
        
        // Step 1: Handle empty or invalid slug with fallback
        if (empty($post_slug) || trim($post_slug) === '') {
            $this->debug_log('Slug is empty, applying fallback strategy');
            return $this->apply_fallback_strategy($original_filename);
        }
        
        // Step 2: Apply transliteration if enabled
        if (!empty($this->settings['transliterate_slug'])) {
            $transliterated_slug = $this->transliterate_text($post_slug);
            $this->debug_log('Transliterated slug: "' . $post_slug . '" to "' . $transliterated_slug . '"');
            $post_slug = $transliterated_slug;
        }
        
        // Step 3: Sanitize the slug
        $sanitized_slug = sanitize_file_name($post_slug);
        $this->debug_log('Sanitized slug: "' . $sanitized_slug . '"');
        
        // Step 4: Check if sanitization left us with nothing usable
        if (empty($sanitized_slug) || strlen(trim($sanitized_slug, '-_')) < 2) {
            $this->debug_log('Sanitized slug is too short or empty, applying fallback strategy');
            return $this->apply_fallback_strategy($original_filename);
        }
        
        return $sanitized_slug;
    }
    
    /**
     * Apply fallback strategy when post slug is not usable
     */
    private function apply_fallback_strategy($original_filename) {
        $fallback_option = $this->settings['fallback_option'] ?? 'original';
        $this->debug_log('Applying fallback strategy: ' . $fallback_option);
        
        switch ($fallback_option) {
            case 'original':
                // Use original filename without extension
                $filename_base = pathinfo($original_filename, PATHINFO_FILENAME);
                $fallback = sanitize_file_name($filename_base);
                break;
                
            case 'date':
                // Use current date
                $fallback = date('Y-m-d');
                break;
                
            case 'datetime':
                // Use current datetime
                $fallback = date('Y-m-d-H-i-s');
                break;
                
            case 'random':
                // Use random string
                $fallback = wp_generate_password(8, false);
                break;
                
            case 'custom':
                // Use custom fallback text
                $custom_fallback = trim($this->settings['custom_fallback'] ?? '');
                if (!empty($custom_fallback)) {
                    $fallback = sanitize_file_name($custom_fallback);
                } else {
                    $fallback = 'image'; // Default if custom is empty
                }
                break;
                
            default:
                $fallback = 'image';
                break;
        }
        
        // Ensure we have something usable
        if (empty($fallback)) {
            $fallback = 'image';
        }
        
        $this->debug_log('Fallback result: "' . $fallback . '"');
        return $fallback;
    }
    
    /**
     * Transliterate non-Latin characters to Latin characters
     */
    private function transliterate_text($text) {
        // First try WordPress's built-in function if available
        if (function_exists('remove_accents')) {
            $text = remove_accents($text);
        }
        
        // Extended transliteration map for various languages
        $transliteration_map = array(
            // Cyrillic to Latin
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'yo', 'ж' => 'zh',
            'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o',
            'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'kh', 'ц' => 'ts',
            'ч' => 'ch', 'ш' => 'sh', 'щ' => 'sch', 'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
            'А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Е' => 'E', 'Ё' => 'Yo', 'Ж' => 'Zh',
            'З' => 'Z', 'И' => 'I', 'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O',
            'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T', 'У' => 'U', 'Ф' => 'F', 'Х' => 'Kh', 'Ц' => 'Ts',
            'Ч' => 'Ch', 'Ш' => 'Sh', 'Щ' => 'Sch', 'Ъ' => '', 'Ы' => 'Y', 'Ь' => '', 'Э' => 'E', 'Ю' => 'Yu', 'Я' => 'Ya',
            
            // Arabic to Latin (basic)
            'ا' => 'a', 'ب' => 'b', 'ت' => 't', 'ث' => 'th', 'ج' => 'j', 'ح' => 'h', 'خ' => 'kh', 'د' => 'd',
            'ذ' => 'dh', 'ر' => 'r', 'ز' => 'z', 'س' => 's', 'ش' => 'sh', 'ص' => 's', 'ض' => 'd', 'ط' => 't',
            'ظ' => 'z', 'ع' => 'a', 'غ' => 'gh', 'ف' => 'f', 'ق' => 'q', 'ك' => 'k', 'ل' => 'l', 'م' => 'm',
            'ن' => 'n', 'ه' => 'h', 'و' => 'w', 'ي' => 'y',
            
            // Greek to Latin
            'α' => 'a', 'β' => 'b', 'γ' => 'g', 'δ' => 'd', 'ε' => 'e', 'ζ' => 'z', 'η' => 'h', 'θ' => 'th',
            'ι' => 'i', 'κ' => 'k', 'λ' => 'l', 'μ' => 'm', 'ν' => 'n', 'ξ' => 'x', 'ο' => 'o', 'π' => 'p',
            'ρ' => 'r', 'σ' => 's', 'τ' => 't', 'υ' => 'u', 'φ' => 'f', 'χ' => 'ch', 'ψ' => 'ps', 'ω' => 'o',
            'Α' => 'A', 'Β' => 'B', 'Γ' => 'G', 'Δ' => 'D', 'Ε' => 'E', 'Ζ' => 'Z', 'Η' => 'H', 'Θ' => 'Th',
            'Ι' => 'I', 'Κ' => 'K', 'Λ' => 'L', 'Μ' => 'M', 'Ν' => 'N', 'Ξ' => 'X', 'Ο' => 'O', 'Π' => 'P',
            'Ρ' => 'R', 'Σ' => 'S', 'Τ' => 'T', 'Υ' => 'U', 'Φ' => 'F', 'Χ' => 'Ch', 'Ψ' => 'Ps', 'Ω' => 'O',
            
            // Chinese/Japanese common characters (basic examples)
            '中' => 'zhong', '国' => 'guo', '文' => 'wen', '日' => 'ri', '本' => 'ben', '語' => 'yu'
        );
        
        // Apply transliteration
        $transliterated = strtr($text, $transliteration_map);
        
        $this->debug_log('Transliteration: "' . $text . '" -> "' . $transliterated . '"');
        
        return $transliterated;
    }
    
    /**
     * Log renamed file
     */
    public function log_renamed_file($attachment_id) {
        global $wpdb;
        
        $attachment = get_post($attachment_id);
        if (!$attachment) {
            return;
        }
        
        $file_path = get_attached_file($attachment_id);
        $file_size = filesize($file_path);
        
        $table_name = $wpdb->prefix . 'psir_logs';
        
        $wpdb->insert(
            $table_name,
            array(
                'post_id' => $attachment->post_parent,
                'original_filename' => $attachment->post_title,
                'new_filename' => basename($file_path),
                'file_size' => $file_size,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%d', '%s')
        );
    }
    
    /**
     * Get rename statistics
     */
    public function get_stats() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'psir_logs';
        
        $total_renamed = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $total_size = $wpdb->get_var("SELECT SUM(file_size) FROM $table_name");
        $recent_renames = $wpdb->get_results(
            "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 10",
            ARRAY_A
        );
        
        return array(
            'total_renamed' => intval($total_renamed),
            'total_size' => intval($total_size),
            'recent_renames' => $recent_renames
        );
    }
}
