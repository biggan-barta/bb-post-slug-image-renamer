<?php
/**
 * Admin interface for Post Slug Image Renamer
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class PSIR_Admin {
    
    private static $instance = null;
    private $settings;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->settings = PSIR_Settings::get_instance();
        $this->init_hooks();
    }
    
    private function init_hooks() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_filter('plugin_action_links_' . plugin_basename(PSIR_PLUGIN_PATH . 'post-slug-image-renamer.php'), array($this, 'add_settings_link'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Post Slug Image Renamer', 'post-slug-image-renamer'),
            __('Image Renamer', 'post-slug-image-renamer'),
            'manage_options',
            'post-slug-image-renamer',
            array($this, 'admin_page'),
            'dashicons-format-image',
            30
        );
        
        add_submenu_page(
            'post-slug-image-renamer',
            __('General Settings', 'post-slug-image-renamer'),
            __('General', 'post-slug-image-renamer'),
            'manage_options',
            'post-slug-image-renamer',
            array($this, 'admin_page')
        );
        
        add_submenu_page(
            'post-slug-image-renamer',
            __('Statistics', 'post-slug-image-renamer'),
            __('Statistics', 'post-slug-image-renamer'),
            'manage_options',
            'psir-statistics',
            array($this, 'statistics_page')
        );
        
        add_submenu_page(
            'post-slug-image-renamer',
            __('About', 'post-slug-image-renamer'),
            __('About', 'post-slug-image-renamer'),
            'manage_options',
            'psir-about',
            array($this, 'about_page')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'post-slug-image-renamer') === false && strpos($hook, 'psir-') === false) {
            return;
        }
        
        wp_enqueue_style(
            'psir-admin-style',
            PSIR_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            PSIR_VERSION
        );
        
        wp_enqueue_script(
            'psir-admin-script',
            PSIR_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            PSIR_VERSION,
            true
        );
    }
    
    /**
     * Add settings link to plugin actions
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=post-slug-image-renamer') . '">' . __('Settings', 'post-slug-image-renamer') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * Main admin page
     */
    public function admin_page() {
        if (isset($_GET['settings-updated'])) {
            add_settings_error('psir_messages', 'psir_message', __('Settings saved successfully.', 'post-slug-image-renamer'), 'updated');
        }
        
        settings_errors('psir_messages');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="psir-admin-container">
                <div class="psir-main-content">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('psir_settings_group');
                        do_settings_sections('psir_settings');
                        submit_button();
                        ?>
                    </form>
                </div>
                
                <div class="psir-sidebar">
                    <div class="psir-widget">
                        <h3><?php _e('Quick Info', 'post-slug-image-renamer'); ?></h3>
                        <ul>
                            <li><?php _e('✓ Renames images uploaded from post editor', 'post-slug-image-renamer'); ?></li>
                            <li><?php _e('✓ Leaves Media Library uploads unchanged', 'post-slug-image-renamer'); ?></li>
                            <li><?php _e('✓ Uses post slug as filename base', 'post-slug-image-renamer'); ?></li>
                            <li><?php _e('✓ Configurable settings', 'post-slug-image-renamer'); ?></li>
                        </ul>
                    </div>
                    
                    <div class="psir-widget">
                        <h3><?php _e('Need Help?', 'post-slug-image-renamer'); ?></h3>
                        <p><?php _e('Check the About page for more information about how this plugin works.', 'post-slug-image-renamer'); ?></p>
                        <a href="<?php echo admin_url('admin.php?page=psir-about'); ?>" class="button"><?php _e('View About Page', 'post-slug-image-renamer'); ?></a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Statistics page
     */
    public function statistics_page() {
        $core = PSIR_Core::get_instance();
        $stats = $core->get_stats();
        ?>
        <div class="wrap">
            <h1><?php _e('Statistics', 'post-slug-image-renamer'); ?></h1>
            
            <div class="psir-admin-container">
                <div class="psir-stats-grid">
                    <div class="psir-stat-card">
                        <h3><?php _e('Total Images Renamed', 'post-slug-image-renamer'); ?></h3>
                        <div class="psir-stat-number"><?php echo number_format($stats['total_renamed']); ?></div>
                    </div>
                    
                    <div class="psir-stat-card">
                        <h3><?php _e('Total File Size', 'post-slug-image-renamer'); ?></h3>
                        <div class="psir-stat-number"><?php echo $this->format_bytes($stats['total_size']); ?></div>
                    </div>
                </div>
                
                <div class="psir-recent-renames">
                    <h3><?php _e('Recent Renames', 'post-slug-image-renamer'); ?></h3>
                    
                    <?php if (empty($stats['recent_renames'])): ?>
                        <p><?php _e('No image renames recorded yet.', 'post-slug-image-renamer'); ?></p>
                    <?php else: ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Post', 'post-slug-image-renamer'); ?></th>
                                    <th><?php _e('Original Filename', 'post-slug-image-renamer'); ?></th>
                                    <th><?php _e('New Filename', 'post-slug-image-renamer'); ?></th>
                                    <th><?php _e('File Size', 'post-slug-image-renamer'); ?></th>
                                    <th><?php _e('Date', 'post-slug-image-renamer'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats['recent_renames'] as $rename): ?>
                                    <tr>
                                        <td>
                                            <?php 
                                            $post = get_post($rename['post_id']);
                                            if ($post) {
                                                echo '<a href="' . get_edit_post_link($post->ID) . '">' . esc_html($post->post_title) . '</a>';
                                            } else {
                                                echo __('Post not found', 'post-slug-image-renamer');
                                            }
                                            ?>
                                        </td>
                                        <td><?php echo esc_html($rename['original_filename']); ?></td>
                                        <td><?php echo esc_html($rename['new_filename']); ?></td>
                                        <td><?php echo $this->format_bytes($rename['file_size']); ?></td>
                                        <td><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($rename['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * About page
     */
    public function about_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('About Post Slug Image Renamer', 'post-slug-image-renamer'); ?></h1>
            
            <div class="psir-about-content">
                <div class="psir-about-section">
                    <h2><?php _e('What does this plugin do?', 'post-slug-image-renamer'); ?></h2>
                    <p><?php _e('Post Slug Image Renamer automatically renames image files when they are uploaded through the post editor (like featured images or images inserted into post content). The new filename is based on the post slug, making your media files more organized and SEO-friendly.', 'post-slug-image-renamer'); ?></p>
                </div>
                
                <div class="psir-about-section">
                    <h2><?php _e('How it works', 'post-slug-image-renamer'); ?></h2>
                    <ul>
                        <li><?php _e('<strong>Post Editor Uploads:</strong> When you upload images through the post editor (featured image, insert media, etc.), they get renamed with the post slug.', 'post-slug-image-renamer'); ?></li>
                        <li><?php _e('<strong>Media Library Uploads:</strong> Direct uploads to the Media Library keep their original filenames.', 'post-slug-image-renamer'); ?></li>
                        <li><?php _e('<strong>Filename Format:</strong> post-slug-random-suffix.extension', 'post-slug-image-renamer'); ?></li>
                        <li><?php _e('<strong>Conflict Prevention:</strong> Random suffixes prevent filename conflicts.', 'post-slug-image-renamer'); ?></li>
                    </ul>
                </div>
                
                <div class="psir-about-section">
                    <h2><?php _e('Settings Explained', 'post-slug-image-renamer'); ?></h2>
                    <dl>
                        <dt><strong><?php _e('Enable Plugin', 'post-slug-image-renamer'); ?></strong></dt>
                        <dd><?php _e('Turn the plugin functionality on or off.', 'post-slug-image-renamer'); ?></dd>
                        
                        <dt><strong><?php _e('Separator', 'post-slug-image-renamer'); ?></strong></dt>
                        <dd><?php _e('Character used to separate words in the filename (default: hyphen).', 'post-slug-image-renamer'); ?></dd>
                        
                        <dt><strong><?php _e('Maximum Length', 'post-slug-image-renamer'); ?></strong></dt>
                        <dd><?php _e('Maximum length of the post slug part of the filename.', 'post-slug-image-renamer'); ?></dd>
                        
                        <dt><strong><?php _e('Include Timestamp', 'post-slug-image-renamer'); ?></strong></dt>
                        <dd><?php _e('Add timestamp to make filenames even more unique.', 'post-slug-image-renamer'); ?></dd>
                        
                        <dt><strong><?php _e('Allowed Post Types', 'post-slug-image-renamer'); ?></strong></dt>
                        <dd><?php _e('Select which post types should have their uploaded images renamed.', 'post-slug-image-renamer'); ?></dd>
                    </dl>
                </div>
                
                <div class="psir-about-section">
                    <h2><?php _e('Examples', 'post-slug-image-renamer'); ?></h2>
                    <p><?php _e('If your post slug is "my-awesome-blog-post" and you upload "IMG_1234.jpg":', 'post-slug-image-renamer'); ?></p>
                    <ul>
                        <li><?php _e('Without timestamp: my-awesome-blog-post-abc123.jpg', 'post-slug-image-renamer'); ?></li>
                        <li><?php _e('With timestamp: my-awesome-blog-post-1640995200-abc123.jpg', 'post-slug-image-renamer'); ?></li>
                    </ul>
                </div>
                
                <div class="psir-about-section">
                    <h2><?php _e('Plugin Information', 'post-slug-image-renamer'); ?></h2>
                    <table class="psir-info-table">
                        <tr>
                            <td><strong><?php _e('Version:', 'post-slug-image-renamer'); ?></strong></td>
                            <td><?php echo PSIR_VERSION; ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('WordPress Version:', 'post-slug-image-renamer'); ?></strong></td>
                            <td><?php echo get_bloginfo('version'); ?></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('PHP Version:', 'post-slug-image-renamer'); ?></strong></td>
                            <td><?php echo PHP_VERSION; ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Format bytes into human readable format
     */
    private function format_bytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
