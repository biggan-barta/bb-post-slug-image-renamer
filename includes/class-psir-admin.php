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
        
        // Check if logs table exists and show admin notice if not
        add_action('admin_notices', array($this, 'check_logs_table'));
    }
    
    private function init_hooks() {
        // Only add hooks when actually in admin
        if (!is_admin()) {
            return;
        }
        
        // Load admin scripts only on plugin pages
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_filter('plugin_action_links_' . plugin_basename(PSIR_PLUGIN_PATH . 'post-slug-image-renamer.php'), array($this, 'add_settings_link'));
        
        // Only enqueue scripts on our plugin pages
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts_conditionally'));
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
     * Conditionally enqueue admin scripts and styles - optimized
     */
    public function enqueue_admin_scripts_conditionally($hook) {
        // Only load on plugin pages
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
        // Handle clear statistics action
        if (isset($_POST['clear_stats']) && wp_verify_nonce($_POST['_wpnonce'], 'psir_clear_stats')) {
            $core = PSIR_Core::get_instance();
            if ($core->clear_stats()) {
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Statistics cleared successfully.', 'post-slug-image-renamer') . '</p></div>';
            } else {
                echo '<div class="notice notice-error is-dismissible"><p>' . __('Failed to clear statistics.', 'post-slug-image-renamer') . '</p></div>';
            }
        }
        
        // Check if statistics are enabled
        $settings = get_option('psir_settings', array());
        $statistics_enabled = ($settings['enable_statistics'] ?? 'off') === 'on';
        
        $core = PSIR_Core::get_instance();
        $stats = $core->get_stats();
        ?>
        <div class="wrap">
            <h1><?php _e('Statistics', 'post-slug-image-renamer'); ?></h1>
            
            <?php if (!$statistics_enabled): ?>
                <div class="notice notice-warning">
                    <h3><?php _e('⚡ Statistics Collection Disabled', 'post-slug-image-renamer'); ?></h3>
                    <p><?php _e('Statistics collection is currently disabled for optimal performance. No new data is being collected.', 'post-slug-image-renamer'); ?></p>
                    <p>
                        <a href="<?php echo admin_url('admin.php?page=psir-advanced'); ?>" class="button button-primary">
                            <?php _e('Enable Statistics Collection', 'post-slug-image-renamer'); ?>
                        </a>
                        <span style="margin-left: 15px; color: #666;">
                            <?php _e('Note: Existing historical data will still be shown below', 'post-slug-image-renamer'); ?>
                        </span>
                    </p>
                </div>
            <?php endif; ?>
            
            <?php if (!defined('PSIR_DEBUG') || !PSIR_DEBUG): ?>
                <div class="notice notice-info">
                    <p><strong><?php _e('Tip:', 'post-slug-image-renamer'); ?></strong> 
                    <?php _e('If statistics aren\'t showing, enable debug mode to troubleshoot. See the', 'post-slug-image-renamer'); ?> 
                    <a href="<?php echo admin_url('admin.php?page=psir-about'); ?>"><?php _e('About page', 'post-slug-image-renamer'); ?></a> 
                    <?php _e('for instructions.', 'post-slug-image-renamer'); ?>
                    </p>
                </div>
            <?php endif; ?>
            
            <div class="psir-admin-container">
                <div class="psir-main-content">
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
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h3 style="margin: 0;"><?php _e('Recent Renames', 'post-slug-image-renamer'); ?></h3>
                            
                            <?php if (!empty($stats['recent_renames'])): ?>
                                <form method="post" style="display: inline;">
                                    <?php wp_nonce_field('psir_clear_stats'); ?>
                                    <input type="submit" name="clear_stats" class="button button-secondary" 
                                           value="<?php _e('Clear Statistics', 'post-slug-image-renamer'); ?>"
                                           onclick="return confirm('<?php _e('Are you sure you want to clear all statistics? This action cannot be undone.', 'post-slug-image-renamer'); ?>');">
                                </form>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (empty($stats['recent_renames'])): ?>
                            <div class="psir-no-stats">
                                <p><?php _e('No image renames recorded yet.', 'post-slug-image-renamer'); ?></p>
                                
                                <?php 
                                global $wpdb;
                                $table_name = $wpdb->prefix . 'psir_logs';
                                if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name): ?>
                                    <div class="notice notice-warning inline">
                                        <p><strong><?php _e('Database table missing!', 'post-slug-image-renamer'); ?></strong></p>
                                        <p><?php _e('The statistics table wasn\'t created during plugin activation. Try deactivating and reactivating the plugin.', 'post-slug-image-renamer'); ?></p>
                                    </div>
                                <?php else: ?>
                                    <p><?php _e('Statistics will appear here after you:', 'post-slug-image-renamer'); ?></p>
                                    <ol>
                                        <li><?php _e('Create a post with a featured image', 'post-slug-image-renamer'); ?></li>
                                        <li><?php _e('Publish the post', 'post-slug-image-renamer'); ?></li>
                                        <li><?php _e('The plugin will rename the image and log it here', 'post-slug-image-renamer'); ?></li>
                                    </ol>
                                <?php endif; ?>
                            </div>
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
                        <tr>
                            <td><strong><?php _e('Debug Mode:', 'post-slug-image-renamer'); ?></strong></td>
                            <td>
                                <?php if (defined('PSIR_DEBUG') && PSIR_DEBUG): ?>
                                    <span style="color: green;">✓ <?php _e('Enabled', 'post-slug-image-renamer'); ?></span>
                                <?php else: ?>
                                    <span style="color: orange;">⚠ <?php _e('Disabled', 'post-slug-image-renamer'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Statistics Table:', 'post-slug-image-renamer'); ?></strong></td>
                            <td>
                                <?php 
                                global $wpdb;
                                $table_name = $wpdb->prefix . 'psir_logs';
                                if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name): ?>
                                    <span style="color: green;">✓ <?php _e('Created', 'post-slug-image-renamer'); ?></span>
                                <?php else: ?>
                                    <span style="color: red;">✗ <?php _e('Missing', 'post-slug-image-renamer'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="psir-about-section">
                    <h2><?php _e('🔧 Troubleshooting & Debug Mode', 'post-slug-image-renamer'); ?></h2>
                    
                    <h3><?php _e('Statistics Not Working?', 'post-slug-image-renamer'); ?></h3>
                    <p><?php _e('The statistics page tracks image renames in a database table. If statistics aren\'t showing:', 'post-slug-image-renamer'); ?></p>
                    <ol>
                        <li><?php _e('Make sure you\'ve published posts with featured images after installing the plugin', 'post-slug-image-renamer'); ?></li>
                        <li><?php _e('Check if the plugin is enabled in Settings', 'post-slug-image-renamer'); ?></li>
                        <li><?php _e('Enable Debug Mode (see below) to troubleshoot issues', 'post-slug-image-renamer'); ?></li>
                    </ol>
                    
                    <h3><?php _e('How to Enable Debug Mode', 'post-slug-image-renamer'); ?></h3>
                    <p><?php _e('Debug mode helps you see what the plugin is doing and troubleshoot issues:', 'post-slug-image-renamer'); ?></p>
                    
                    <div class="psir-code-box">
                        <h4><?php _e('Step 1: Add to wp-config.php', 'post-slug-image-renamer'); ?></h4>
                        <pre><code>// Add this line to your wp-config.php file (above "/* That's all, stop editing! */")
define('PSIR_DEBUG', true);</code></pre>
                        
                        <h4><?php _e('Step 2: Check Debug Logs', 'post-slug-image-renamer'); ?></h4>
                        <p><?php _e('Debug messages will appear in:', 'post-slug-image-renamer'); ?></p>
                        <ul>
                            <li><?php _e('WordPress debug.log (if WP_DEBUG is enabled)', 'post-slug-image-renamer'); ?></li>
                            <li><?php _e('Look for messages starting with "PSIR:"', 'post-slug-image-renamer'); ?></li>
                        </ul>
                        
                        <h4><?php _e('Step 3: Test Image Upload', 'post-slug-image-renamer'); ?></h4>
                        <ol>
                            <li><?php _e('Create a new post', 'post-slug-image-renamer'); ?></li>
                            <li><?php _e('Add a featured image', 'post-slug-image-renamer'); ?></li>
                            <li><?php _e('Publish the post', 'post-slug-image-renamer'); ?></li>
                            <li><?php _e('Check debug logs for activity', 'post-slug-image-renamer'); ?></li>
                        </ol>
                        
                        <h4><?php _e('Step 4: Disable When Done', 'post-slug-image-renamer'); ?></h4>
                        <pre><code>// Remove or comment out this line when debugging is complete
// define('PSIR_DEBUG', true);</code></pre>
                        <p><strong><?php _e('Important:', 'post-slug-image-renamer'); ?></strong> <?php _e('Debug mode can slow down your site slightly, so disable it when you\'re done troubleshooting.', 'post-slug-image-renamer'); ?></p>
                    </div>
                    
                    <?php if (defined('PSIR_DEBUG') && PSIR_DEBUG): ?>
                        <div class="notice notice-info">
                            <p><strong><?php _e('Debug Mode is Currently Active', 'post-slug-image-renamer'); ?></strong></p>
                            <p><?php _e('Debug information is being logged. Check your WordPress debug.log for messages starting with "PSIR:".', 'post-slug-image-renamer'); ?></p>
                        </div>
                    <?php else: ?>
                        <div class="notice notice-warning">
                            <p><strong><?php _e('Debug Mode is Disabled', 'post-slug-image-renamer'); ?></strong></p>
                            <p><?php _e('If you\'re experiencing issues, enable debug mode using the instructions above.', 'post-slug-image-renamer'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="psir-about-section">
                    <h2><?php _e('👨‍💻 Developer Information', 'post-slug-image-renamer'); ?></h2>
                    
                    <h3><?php _e('Plugin Details', 'post-slug-image-renamer'); ?></h3>
                    <table class="psir-info-table">
                        <tr>
                            <td><strong><?php _e('Plugin Name:', 'post-slug-image-renamer'); ?></strong></td>
                            <td>BB Post Slug Image Renamer</td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Plugin URI:', 'post-slug-image-renamer'); ?></strong></td>
                            <td><a href="https://github.com/biggan-barta/bb-post-slug-image-renamer" target="_blank">GitHub Repository</a></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Author:', 'post-slug-image-renamer'); ?></strong></td>
                            <td>BigganBarta</td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Developer:', 'post-slug-image-renamer'); ?></strong></td>
                            <td>Tanvir Rana Rabbi for BigganBarta</td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Author URI:', 'post-slug-image-renamer'); ?></strong></td>
                            <td><a href="https://bigganbarta.org" target="_blank">bigganbarta.org</a></td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('License:', 'post-slug-image-renamer'); ?></strong></td>
                            <td>GPL v2 or later</td>
                        </tr>
                        <tr>
                            <td><strong><?php _e('Text Domain:', 'post-slug-image-renamer'); ?></strong></td>
                            <td>post-slug-image-renamer</td>
                        </tr>
                    </table>
                    
                    <h3><?php _e('For Developers', 'post-slug-image-renamer'); ?></h3>
                    <p><?php _e('This plugin provides hooks and functions for developers:', 'post-slug-image-renamer'); ?></p>
                    
                    <div class="psir-code-box">
                        <h4><?php _e('Social Media Integration', 'post-slug-image-renamer'); ?></h4>
                        <pre><code>// Get renamed image URL for social sharing
$image_url = psir_get_social_image_url($post_id);

// Prepare social media meta tags
do_action('psir_prepare_social_image', $post_id);

// Filter hook for auto-posting plugins
$image_url = apply_filters('psir_get_social_image', $default_url, $post_id);</code></pre>
                        
                        <h4><?php _e('Available Actions', 'post-slug-image-renamer'); ?></h4>
                        <ul>
                            <li><code>psir_prepare_social_image</code> - Prepare image for social sharing</li>
                            <li><code>psir_clear_social_caches</code> - Clear social media caches</li>
                        </ul>
                        
                        <h4><?php _e('Available Filters', 'post-slug-image-renamer'); ?></h4>
                        <ul>
                            <li><code>psir_get_social_image</code> - Get correct image URL for social media</li>
                        </ul>
                        
                        <h4><?php _e('Core Functions', 'post-slug-image-renamer'); ?></h4>
                        <ul>
                            <li><code>psir_get_social_image_url($post_id)</code> - Get renamed featured image URL</li>
                            <li><code>psir_update_social_meta($post_id)</code> - Update social media meta tags</li>
                        </ul>
                    </div>
                    
                    <h3><?php _e('Support & Contributing', 'post-slug-image-renamer'); ?></h3>
                    <ul>
                        <li><strong><?php _e('Issues & Bug Reports:', 'post-slug-image-renamer'); ?></strong> <a href="https://github.com/biggan-barta/bb-post-slug-image-renamer/issues" target="_blank">GitHub Issues</a></li>
                        <li><strong><?php _e('Feature Requests:', 'post-slug-image-renamer'); ?></strong> <a href="https://github.com/biggan-barta/bb-post-slug-image-renamer/discussions" target="_blank">GitHub Discussions</a></li>
                        <li><strong><?php _e('Documentation:', 'post-slug-image-renamer'); ?></strong> <a href="https://github.com/biggan-barta/bb-post-slug-image-renamer/wiki" target="_blank">GitHub Wiki</a></li>
                        <li><strong><?php _e('Social Media Integration Guide:', 'post-slug-image-renamer'); ?></strong> See SOCIAL_MEDIA_INTEGRATION.md</li>
                    </ul>
                    
                    <h3><?php _e('Recent Updates', 'post-slug-image-renamer'); ?></h3>
                    <ul>
                        <li><strong>v1.1.0:</strong> <?php _e('Fixed duplicate filename issue for multiple images, added unique hash suffix to prevent collisions', 'post-slug-image-renamer'); ?></li>
                        <li><strong>v1.0.7:</strong> <?php _e('Performance optimized social media compatibility', 'post-slug-image-renamer'); ?></li>
                        <li><strong>v1.0.6:</strong> <?php _e('Added auto-posting plugin compatibility', 'post-slug-image-renamer'); ?></li>
                        <li><strong>v1.0.5:</strong> <?php _e('Major performance improvements', 'post-slug-image-renamer'); ?></li>
                        <li><strong>v1.0.4:</strong> <?php _e('Fixed post content URL updates', 'post-slug-image-renamer'); ?></li>
                    </ul>
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
    
    /**
     * Check if logs table exists and show notice if not
     */
    public function check_logs_table() {
        // Only show on plugin pages
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'post-slug-image-renamer') === false) {
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'psir_logs';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            ?>
            <div class="notice notice-warning">
                <p><strong><?php _e('Post Slug Image Renamer:', 'post-slug-image-renamer'); ?></strong> 
                <?php _e('Statistics table is missing. Try deactivating and reactivating the plugin to recreate it.', 'post-slug-image-renamer'); ?></p>
            </div>
            <?php
        }
    }
}
