<?php
/**
 * Advanced Admin Settings for Post Slug Image Renamer
 * Handles debug mode and statistics collection settings
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class PSIR_Admin_Settings {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
        
        // Handle form submissions
        if (isset($_POST['psir_advanced_settings_nonce']) && wp_verify_nonce($_POST['psir_advanced_settings_nonce'], 'psir_save_advanced_settings')) {
            add_action('admin_init', array($this, 'save_advanced_settings'));
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'post-slug-image-renamer',
            __('Advanced Settings', 'post-slug-image-renamer'),
            __('Advanced', 'post-slug-image-renamer'),
            'manage_options',
            'psir-advanced',
            array($this, 'advanced_settings_page')
        );
    }
    
    /**
     * Initialize settings
     */
    public function init_settings() {
        register_setting('psir_advanced_settings', 'psir_advanced_options');
    }
    
    /**
     * Save advanced settings
     */
    public function save_advanced_settings() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $settings = get_option('psir_settings', array());
        
        // Debug mode
        $settings['debug_mode'] = isset($_POST['debug_mode']) ? 'on' : 'off';
        
        // Statistics collection
        $settings['enable_statistics'] = isset($_POST['enable_statistics']) ? 'on' : 'off';
        
        update_option('psir_settings', $settings);
        
        add_action('admin_notices', array($this, 'settings_saved_notice'));
    }
    
    /**
     * Settings saved notice
     */
    public function settings_saved_notice() {
        echo '<div class="notice notice-success is-dismissible"><p>' . __('Advanced settings saved successfully!', 'post-slug-image-renamer') . '</p></div>';
    }
    
    /**
     * Advanced settings page
     */
    public function advanced_settings_page() {
        $settings = get_option('psir_settings', array());
        $debug_mode = $settings['debug_mode'] ?? 'off';
        $enable_statistics = $settings['enable_statistics'] ?? 'off';
        ?>
        <div class="wrap">
            <h1><?php _e('Advanced Settings', 'post-slug-image-renamer'); ?></h1>
            
            <div class="notice notice-info">
                <p><strong><?php _e('Performance Note:', 'post-slug-image-renamer'); ?></strong> <?php _e('These settings can impact site performance. Only enable what you need.', 'post-slug-image-renamer'); ?></p>
            </div>
            
            <form method="post" action="">
                <?php wp_nonce_field('psir_save_advanced_settings', 'psir_advanced_settings_nonce'); ?>
                
                <div class="card">
                    <h2><?php _e('Debug & Troubleshooting', 'post-slug-image-renamer'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Debug Mode', 'post-slug-image-renamer'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="debug_mode" value="on" <?php checked($debug_mode, 'on'); ?> />
                                    <?php _e('Enable debug mode', 'post-slug-image-renamer'); ?>
                                </label>
                                <p class="description">
                                    <?php _e('Logs detailed information about image renaming. Useful for troubleshooting but may slow down your site.', 'post-slug-image-renamer'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="card">
                    <h2><?php _e('Statistics Collection', 'post-slug-image-renamer'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Statistics Tracking', 'post-slug-image-renamer'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="enable_statistics" value="on" <?php checked($enable_statistics, 'on'); ?> />
                                    <?php _e('Collect statistics about renamed images', 'post-slug-image-renamer'); ?>
                                </label>
                                <p class="description">
                                    <?php _e('Tracks information about renamed images in the database. Adds minimal overhead but can be disabled for maximum performance.', 'post-slug-image-renamer'); ?>
                                </p>
                                
                                <?php if ($enable_statistics === 'on'): ?>
                                    <div class="notice notice-warning inline">
                                        <p><strong><?php _e('Statistics Enabled:', 'post-slug-image-renamer'); ?></strong> <?php _e('Statistics are being collected. You can view them on the Statistics page.', 'post-slug-image-renamer'); ?></p>
                                    </div>
                                <?php else: ?>
                                    <div class="notice notice-success inline">
                                        <p><strong><?php _e('Performance Mode:', 'post-slug-image-renamer'); ?></strong> <?php _e('Statistics are disabled for maximum performance.', 'post-slug-image-renamer'); ?></p>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="card">
                    <h2><?php _e('Current Status', 'post-slug-image-renamer'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Performance Impact', 'post-slug-image-renamer'); ?></th>
                            <td>
                                <?php
                                $impact_level = 'minimal';
                                $impact_class = 'notice-success';
                                $impact_message = __('Minimal - Plugin is optimized for performance', 'post-slug-image-renamer');
                                
                                if ($debug_mode === 'on' && $enable_statistics === 'on') {
                                    $impact_level = 'moderate';
                                    $impact_class = 'notice-warning';
                                    $impact_message = __('Moderate - Both debug and statistics enabled', 'post-slug-image-renamer');
                                } elseif ($debug_mode === 'on' || $enable_statistics === 'on') {
                                    $impact_level = 'low';
                                    $impact_class = 'notice-info';
                                    $impact_message = __('Low - One feature enabled', 'post-slug-image-renamer');
                                }
                                ?>
                                <div class="notice <?php echo $impact_class; ?> inline">
                                    <p><strong><?php echo esc_html($impact_message); ?></strong></p>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <?php submit_button(__('Save Advanced Settings', 'post-slug-image-renamer')); ?>
            </form>
            
            <div class="card">
                <h2><?php _e('Performance Tips', 'post-slug-image-renamer'); ?></h2>
                <ul>
                    <li><?php _e('For maximum performance, keep both debug mode and statistics disabled', 'post-slug-image-renamer'); ?></li>
                    <li><?php _e('Only enable debug mode when troubleshooting issues', 'post-slug-image-renamer'); ?></li>
                    <li><?php _e('Statistics collection uses async logging to minimize impact', 'post-slug-image-renamer'); ?></li>
                    <li><?php _e('You can clear statistics data anytime from the Statistics page', 'post-slug-image-renamer'); ?></li>
                </ul>
            </div>
        </div>
        
        <style>
            .card {
                background: #fff;
                border: 1px solid #ccd0d4;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
                padding: 20px;
                margin: 20px 0;
            }
            .notice.inline {
                margin: 10px 0 0 0;
                padding: 8px 12px;
            }
        </style>
        <?php
    }
}

// Initialize the class
PSIR_Admin_Settings::get_instance();
