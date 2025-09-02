<?php
/**
 * Plugin Name: BB Post Slug Renamer
 * Plugin URI: https://github.com/biggan-barta/bb-post-slug-image-renamer
 * Description: Automatically renames uploaded images with post slug when uploading from post editor (featured image, post content), but leaves Media Library uploads unchanged.
 * Version: 1.0.9
 * Author: BigganBarta
 * Author URI: https://bigganbarta.org
 * License: GPL v2 or later
 * Text Domain: post-slug-image-renamer
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('PSIR_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PSIR_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('PSIR_VERSION', '1.0.9');

// Debug mode - set to true only for debugging (impacts performance)
if (!defined('PSIR_DEBUG')) {
    define('PSIR_DEBUG', false);
}

// Include required files
require_once PSIR_PLUGIN_PATH . 'includes/class-psir-core.php';
require_once PSIR_PLUGIN_PATH . 'includes/class-psir-settings.php';
require_once PSIR_PLUGIN_PATH . 'includes/class-psir-admin.php';
require_once PSIR_PLUGIN_PATH . 'includes/class-psir-admin-settings.php';
require_once PSIR_PLUGIN_PATH . 'includes/social-compatibility.php';

/**
 * Main plugin class
 */
class PostSlugImageRenamer {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // Load text domain only if needed
        if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
            load_plugin_textdomain('post-slug-image-renamer', false, dirname(plugin_basename(__FILE__)) . '/languages');
        }
        
        // Initialize core functionality (always needed)
        PSIR_Core::get_instance();
        
        // Initialize admin only when in admin area
        if (is_admin()) {
            PSIR_Admin::get_instance();
        }
    }
    
    public function activate() {
        // Set default options
        $default_options = array(
            'enabled' => true,
            'rename_timing' => 'publish',
            'separator' => '-',
            'max_length' => 50,
            'include_timestamp' => false,
            'preserve_original_extension' => true,
            'allowed_post_types' => array('post', 'page'),
            'update_image_title' => true,
            'filename_prefix' => '',
            'filename_suffix' => '',
            'title_prefix' => '',
            'title_suffix' => '',
            'transliterate_slug' => true,
            'fallback_option' => 'original',
            'custom_fallback' => ''
        );
        
        add_option('psir_settings', $default_options);
        
        // Create logs table if needed
        $this->create_logs_table();
    }
    
    public function deactivate() {
        // Clean up if needed
    }
    
    private function create_logs_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'psir_logs';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            post_id bigint(20) NOT NULL,
            original_filename varchar(255) NOT NULL,
            new_filename varchar(255) NOT NULL,
            file_size int(11) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY post_id (post_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}

// Initialize the plugin
PostSlugImageRenamer::get_instance();
