<?php
/**
 * Settings management for Post Slug Image Renamer
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class PSIR_Settings {
    
    private static $instance = null;
    private $options_key = 'psir_settings';
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Only initialize settings if in admin area
        if (is_admin()) {
            add_action('admin_init', array($this, 'init_settings'));
        }
    }
    
    /**
     * Initialize settings
     */
    public function init_settings() {
        register_setting('psir_settings_group', $this->options_key, array($this, 'sanitize_settings'));
        
        // General Settings Section
        add_settings_section(
            'psir_general_section',
            __('General Settings', 'post-slug-image-renamer'),
            array($this, 'general_section_callback'),
            'psir_settings'
        );
        
        // Enable/Disable
        add_settings_field(
            'enabled',
            __('Enable Plugin', 'post-slug-image-renamer'),
            array($this, 'checkbox_field_callback'),
            'psir_settings',
            'psir_general_section',
            array(
                'id' => 'enabled',
                'description' => __('Enable automatic image renaming', 'post-slug-image-renamer')
            )
        );
        
        // Rename Timing
        add_settings_field(
            'rename_timing',
            __('When to Rename', 'post-slug-image-renamer'),
            array($this, 'select_field_callback'),
            'psir_settings',
            'psir_general_section',
            array(
                'id' => 'rename_timing',
                'description' => __('Choose when images should be renamed', 'post-slug-image-renamer'),
                'options' => array(
                    'upload' => __('During Upload (may use draft slug)', 'post-slug-image-renamer'),
                    'publish' => __('When Post is Published (uses final slug)', 'post-slug-image-renamer'),
                    'both' => __('Both (upload + publish rename)', 'post-slug-image-renamer')
                )
            )
        );
        
        // Separator
        add_settings_field(
            'separator',
            __('Separator', 'post-slug-image-renamer'),
            array($this, 'text_field_callback'),
            'psir_settings',
            'psir_general_section',
            array(
                'id' => 'separator',
                'description' => __('Character used to separate words in filename', 'post-slug-image-renamer'),
                'placeholder' => '-'
            )
        );
        
        // Max Length
        add_settings_field(
            'max_length',
            __('Maximum Length', 'post-slug-image-renamer'),
            array($this, 'number_field_callback'),
            'psir_settings',
            'psir_general_section',
            array(
                'id' => 'max_length',
                'description' => __('Maximum length of the filename (excluding extension)', 'post-slug-image-renamer'),
                'min' => 10,
                'max' => 100
            )
        );
        
        // Include Timestamp
        add_settings_field(
            'include_timestamp',
            __('Include Timestamp', 'post-slug-image-renamer'),
            array($this, 'checkbox_field_callback'),
            'psir_settings',
            'psir_general_section',
            array(
                'id' => 'include_timestamp',
                'description' => __('Add timestamp to filename to make it more unique', 'post-slug-image-renamer')
            )
        );
        
        // Preserve Original Extension
        add_settings_field(
            'preserve_original_extension',
            __('Preserve Original Extension', 'post-slug-image-renamer'),
            array($this, 'checkbox_field_callback'),
            'psir_settings',
            'psir_general_section',
            array(
                'id' => 'preserve_original_extension',
                'description' => __('Keep the original file extension', 'post-slug-image-renamer')
            )
        );
        
        // Allowed Post Types
        add_settings_field(
            'allowed_post_types',
            __('Allowed Post Types', 'post-slug-image-renamer'),
            array($this, 'post_types_field_callback'),
            'psir_settings',
            'psir_general_section',
            array(
                'id' => 'allowed_post_types',
                'description' => __('Select post types where image renaming should be active', 'post-slug-image-renamer')
            )
        );
        
        // Update Image Title
        add_settings_field(
            'update_image_title',
            __('Update Image Title', 'post-slug-image-renamer'),
            array($this, 'checkbox_field_callback'),
            'psir_settings',
            'psir_general_section',
            array(
                'id' => 'update_image_title',
                'description' => __('Update WordPress image title with post title', 'post-slug-image-renamer')
            )
        );
        
        // Filename Prefix
        add_settings_field(
            'filename_prefix',
            __('Filename Prefix', 'post-slug-image-renamer'),
            array($this, 'text_field_callback'),
            'psir_settings',
            'psir_general_section',
            array(
                'id' => 'filename_prefix',
                'description' => __('Text to add before filename (separator added automatically). Example: "site" becomes "site-post-slug-abc123.jpg"', 'post-slug-image-renamer'),
                'placeholder' => 'site'
            )
        );
        
        // Filename Suffix
        add_settings_field(
            'filename_suffix',
            __('Filename Suffix', 'post-slug-image-renamer'),
            array($this, 'text_field_callback'),
            'psir_settings',
            'psir_general_section',
            array(
                'id' => 'filename_suffix',
                'description' => __('Text to add after post slug (separator added automatically). Example: "img" becomes "post-slug-img-abc123.jpg"', 'post-slug-image-renamer'),
                'placeholder' => 'img'
            )
        );
        
        // Image Title Prefix
        add_settings_field(
            'title_prefix',
            __('Image Title Prefix', 'post-slug-image-renamer'),
            array($this, 'text_field_callback'),
            'psir_settings',
            'psir_general_section',
            array(
                'id' => 'title_prefix',
                'description' => __('Text to add before image title (e.g., "Photo:" results in "Photo: Post Title")', 'post-slug-image-renamer'),
                'placeholder' => 'Photo:'
            )
        );
        
        // Image Title Suffix
        add_settings_field(
            'title_suffix',
            __('Image Title Suffix', 'post-slug-image-renamer'),
            array($this, 'text_field_callback'),
            'psir_settings',
            'psir_general_section',
            array(
                'id' => 'title_suffix',
                'description' => __('Text to add after image title (e.g., "- Image" results in "Post Title - Image")', 'post-slug-image-renamer'),
                'placeholder' => '- Image'
            )
        );
        
        // Multi-language Support Section
        add_settings_section(
            'psir_multilang_section',
            __('Multi-language & Fallback Settings', 'post-slug-image-renamer'),
            array($this, 'multilang_section_callback'),
            'psir_settings'
        );
        
        // Transliterate Slug
        add_settings_field(
            'transliterate_slug',
            __('Transliterate Non-Latin Characters', 'post-slug-image-renamer'),
            array($this, 'checkbox_field_callback'),
            'psir_settings',
            'psir_multilang_section',
            array(
                'id' => 'transliterate_slug',
                'description' => __('Convert non-Latin characters (Cyrillic, Arabic, Chinese, etc.) to Latin characters', 'post-slug-image-renamer')
            )
        );
        
        // Fallback Option
        add_settings_field(
            'fallback_option',
            __('Fallback Strategy', 'post-slug-image-renamer'),
            array($this, 'select_field_callback'),
            'psir_settings',
            'psir_multilang_section',
            array(
                'id' => 'fallback_option',
                'description' => __('What to use for filename when post slug is empty or invalid', 'post-slug-image-renamer'),
                'options' => array(
                    'original' => __('Original filename', 'post-slug-image-renamer'),
                    'date' => __('Current date (YYYY-MM-DD)', 'post-slug-image-renamer'),
                    'datetime' => __('Current date and time', 'post-slug-image-renamer'),
                    'random' => __('Random string', 'post-slug-image-renamer'),
                    'custom' => __('Custom text (specified below)', 'post-slug-image-renamer')
                )
            )
        );
        
        // Custom Fallback
        add_settings_field(
            'custom_fallback',
            __('Custom Fallback Text', 'post-slug-image-renamer'),
            array($this, 'text_field_callback'),
            'psir_settings',
            'psir_multilang_section',
            array(
                'id' => 'custom_fallback',
                'description' => __('Custom text to use when "Custom text" fallback option is selected', 'post-slug-image-renamer'),
                'placeholder' => 'image'
            )
        );
    }
    
    /**
     * General section callback
     */
    public function general_section_callback() {
        echo '<p>' . __('Configure how images should be renamed when uploaded from post editor.', 'post-slug-image-renamer') . '</p>';
    }
    
    /**
     * Multi-language section callback
     */
    public function multilang_section_callback() {
        echo '<p>' . __('Configure multi-language support and fallback options for non-Latin characters and empty slugs.', 'post-slug-image-renamer') . '</p>';
    }
    
    /**
     * Checkbox field callback
     */
    public function checkbox_field_callback($args) {
        $options = get_option($this->options_key, array());
        $value = isset($options[$args['id']]) ? $options[$args['id']] : false;
        
        echo '<label>';
        echo '<input type="checkbox" id="' . esc_attr($args['id']) . '" name="' . $this->options_key . '[' . esc_attr($args['id']) . ']" value="1" ' . checked(1, $value, false) . ' />';
        echo ' ' . esc_html($args['description']);
        echo '</label>';
    }
    
    /**
     * Text field callback
     */
    public function text_field_callback($args) {
        $options = get_option($this->options_key, array());
        $value = isset($options[$args['id']]) ? $options[$args['id']] : '';
        
        echo '<input type="text" id="' . esc_attr($args['id']) . '" name="' . $this->options_key . '[' . esc_attr($args['id']) . ']" value="' . esc_attr($value) . '" placeholder="' . esc_attr($args['placeholder'] ?? '') . '" />';
        if (isset($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }
    
    /**
     * Select field callback
     */
    public function select_field_callback($args) {
        $options = get_option($this->options_key, array());
        $value = isset($options[$args['id']]) ? $options[$args['id']] : 'publish';
        
        echo '<select id="' . esc_attr($args['id']) . '" name="' . $this->options_key . '[' . esc_attr($args['id']) . ']">';
        foreach ($args['options'] as $option_value => $option_label) {
            echo '<option value="' . esc_attr($option_value) . '" ' . selected($option_value, $value, false) . '>';
            echo esc_html($option_label);
            echo '</option>';
        }
        echo '</select>';
        
        if (isset($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }
    public function number_field_callback($args) {
        $options = get_option($this->options_key, array());
        $value = isset($options[$args['id']]) ? $options[$args['id']] : '';
        
        echo '<input type="number" id="' . esc_attr($args['id']) . '" name="' . $this->options_key . '[' . esc_attr($args['id']) . ']" value="' . esc_attr($value) . '" min="' . esc_attr($args['min'] ?? '') . '" max="' . esc_attr($args['max'] ?? '') . '" />';
        if (isset($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }
    
    /**
     * Post types field callback
     */
    public function post_types_field_callback($args) {
        $options = get_option($this->options_key, array());
        $selected_types = isset($options[$args['id']]) ? $options[$args['id']] : array('post', 'page');
        
        $post_types = get_post_types(array('public' => true), 'objects');
        
        echo '<fieldset>';
        foreach ($post_types as $post_type) {
            $checked = in_array($post_type->name, $selected_types) ? 'checked="checked"' : '';
            echo '<label style="display: block; margin-bottom: 5px;">';
            echo '<input type="checkbox" name="' . $this->options_key . '[' . esc_attr($args['id']) . '][]" value="' . esc_attr($post_type->name) . '" ' . $checked . ' />';
            echo ' ' . esc_html($post_type->label) . ' (' . esc_html($post_type->name) . ')';
            echo '</label>';
        }
        echo '</fieldset>';
        
        if (isset($args['description'])) {
            echo '<p class="description">' . esc_html($args['description']) . '</p>';
        }
    }
    
    /**
     * Sanitize settings
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        // Enabled
        $sanitized['enabled'] = !empty($input['enabled']);
        
        // Rename Timing
        $valid_timings = array('upload', 'publish', 'both');
        $sanitized['rename_timing'] = in_array($input['rename_timing'] ?? '', $valid_timings) ? $input['rename_timing'] : 'publish';
        
        // Separator
        $sanitized['separator'] = sanitize_text_field($input['separator'] ?? '-');
        if (empty($sanitized['separator'])) {
            $sanitized['separator'] = '-';
        }
        
        // Max Length
        $sanitized['max_length'] = intval($input['max_length'] ?? 50);
        if ($sanitized['max_length'] < 10) {
            $sanitized['max_length'] = 10;
        }
        if ($sanitized['max_length'] > 100) {
            $sanitized['max_length'] = 100;
        }
        
        // Include Timestamp
        $sanitized['include_timestamp'] = !empty($input['include_timestamp']);
        
        // Preserve Original Extension
        $sanitized['preserve_original_extension'] = !empty($input['preserve_original_extension']);
        
        // Allowed Post Types
        if (isset($input['allowed_post_types']) && is_array($input['allowed_post_types'])) {
            $sanitized['allowed_post_types'] = array_map('sanitize_text_field', $input['allowed_post_types']);
        } else {
            $sanitized['allowed_post_types'] = array('post', 'page');
        }
        
        // Update Image Title
        $sanitized['update_image_title'] = !empty($input['update_image_title']);
        
        // Filename Prefix
        $sanitized['filename_prefix'] = sanitize_text_field($input['filename_prefix'] ?? '');
        
        // Filename Suffix
        $sanitized['filename_suffix'] = sanitize_text_field($input['filename_suffix'] ?? '');
        
        // Title Prefix
        $sanitized['title_prefix'] = sanitize_text_field($input['title_prefix'] ?? '');
        
        // Title Suffix
        $sanitized['title_suffix'] = sanitize_text_field($input['title_suffix'] ?? '');
        
        // Transliterate Slug
        $sanitized['transliterate_slug'] = !empty($input['transliterate_slug']);
        
        // Fallback Option
        $valid_fallbacks = array('original', 'date', 'datetime', 'random', 'custom');
        $sanitized['fallback_option'] = in_array($input['fallback_option'] ?? '', $valid_fallbacks) ? $input['fallback_option'] : 'original';
        
        // Custom Fallback
        $sanitized['custom_fallback'] = sanitize_text_field($input['custom_fallback'] ?? '');
        
        return $sanitized;
    }
    
    /**
     * Get option
     */
    public function get_option($key, $default = null) {
        $options = get_option($this->options_key, array());
        return isset($options[$key]) ? $options[$key] : $default;
    }
    
    /**
     * Get all options
     */
    public function get_all_options() {
        return get_option($this->options_key, array());
    }
}
