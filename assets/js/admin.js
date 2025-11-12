/**
 * Post Slug Image Renamer Admin JavaScript
 */

jQuery(document).ready(function($) {
    
    // Settings form validation
    $('#submit').on('click', function(e) {
        var maxLength = $('#max_length').val();
        var separator = $('#separator').val();
        
        // Validate max length
        if (maxLength && (maxLength < 10 || maxLength > 100)) {
            alert('Maximum length must be between 10 and 100 characters.');
            e.preventDefault();
            return false;
        }
        
        // Validate separator
        if (separator && separator.length > 1) {
            alert('Separator must be a single character.');
            e.preventDefault();
            return false;
        }
        
        // Check if at least one post type is selected
        var postTypesSelected = $('input[name="psir_settings[allowed_post_types][]"]:checked').length;
        if (postTypesSelected === 0) {
            alert('Please select at least one post type.');
            e.preventDefault();
            return false;
        }
    });
    
    // Real-time filename preview
    function updateFilenamePreview() {
        var prefix = $('#filename_prefix').val() || '';
        var suffix = $('#filename_suffix').val() || '';
        var separator = $('#separator').val() || '-';
        var includeTimestamp = $('#include_timestamp').is(':checked');
        var maxLength = parseInt($('#max_length').val()) || 50;
        
        var sampleSlug = 'my-awesome-blog-post';
        if (sampleSlug.length > maxLength) {
            sampleSlug = sampleSlug.substring(0, maxLength);
        }
        
        // Build filename with proper separators
        var filename = '';
        
        // Add prefix with separator
        if (prefix) {
            var prefixClean = prefix.replace(/[-_]+$/, ''); // Remove trailing separators
            filename += prefixClean + separator;
        }
        
        // Add main slug
        filename += sampleSlug;
        
        // Add suffix with separator  
        if (suffix) {
            var suffixClean = suffix.replace(/^[-_]+/, ''); // Remove leading separators
            filename += separator + suffixClean;
        }
        
        // Add timestamp if enabled
        if (includeTimestamp) {
            filename += separator + '1640995200';
        }
        
        // Add random suffix
        filename += separator + 'abc123.jpg';
        
        // Image title preview
        var titlePrefix = $('#title_prefix').val() || '';
        var titleSuffix = $('#title_suffix').val() || '';
        var sampleTitle = 'My Awesome Blog Post';
        var imageTitle = titlePrefix + (titlePrefix ? ' ' : '') + sampleTitle + (titleSuffix ? ' ' : '') + titleSuffix;
        
        // Create or update preview
        var previewId = 'filename-preview';
        var $preview = $('#' + previewId);
        
        if ($preview.length === 0) {
            $preview = $('<div id="' + previewId + '" class="filename-preview"></div>');
            $('.form-table').after($preview);
        }
        
        var previewHtml = '<div style="margin-bottom: 10px;"><strong>Filename Preview:</strong> <code>' + filename + '</code></div>';
        if ($('#update_image_title').is(':checked')) {
            previewHtml += '<div><strong>Image Title Preview:</strong> <code>' + imageTitle + '</code></div>';
        }
        
        $preview.html(previewHtml);
    }
    
    // Update preview when relevant fields change
    $('#separator, #max_length, #filename_prefix, #filename_suffix, #title_prefix, #title_suffix').on('input', updateFilenamePreview);
    $('#include_timestamp, #update_image_title').on('change', updateFilenamePreview);
    
    // Initial preview
    if ($('#separator').length > 0) {
        updateFilenamePreview();
    }
    
    // Add CSS for preview
    if ($('.filename-preview').length === 0) {
        $('<style>.filename-preview { margin: 15px 0; padding: 10px; background: #f0f0f1; border-left: 4px solid #0073aa; } .filename-preview code { background: #fff; padding: 2px 6px; border-radius: 3px; }</style>').appendTo('head');
    }
    
    // Statistics page: Auto-refresh
    if ($('.psir-stat-number').length > 0) {
        // Add refresh button
        var refreshButton = '<button type="button" class="button button-secondary" id="psir-refresh-stats" style="margin-bottom: 20px;">Refresh Statistics</button>';
        $('.psir-stats-grid').before(refreshButton);
        
        $('#psir-refresh-stats').on('click', function() {
            location.reload();
        });
    }
    
    // Tooltips for settings
    $('.form-table th label').each(function() {
        var $this = $(this);
        var text = $this.text();
        
        switch (text) {
            case 'Enable Plugin':
                $this.attr('title', 'Turn on/off automatic image renaming functionality');
                break;
            case 'Separator':
                $this.attr('title', 'Character used to separate words in filenames (e.g., - or _)');
                break;
            case 'Maximum Length':
                $this.attr('title', 'Prevents very long filenames by limiting the post slug portion');
                break;
            case 'Include Timestamp':
                $this.attr('title', 'Adds Unix timestamp to filename for additional uniqueness');
                break;
            case 'Allowed Post Types':
                $this.attr('title', 'Only uploads from these post types will be renamed');
                break;
        }
    });
    
    // Add help text toggle
    $('.form-table').before('<p><a href="#" id="toggle-help">Show/Hide Help Text</a></p>');
    
    $('#toggle-help').on('click', function(e) {
        e.preventDefault();
        $('.description').slideToggle();
    });
    
    // Confirm before disabling plugin
    $('#enabled').on('change', function() {
        if (!$(this).is(':checked')) {
            if (!confirm('Are you sure you want to disable the plugin? New uploads will not be renamed.')) {
                $(this).prop('checked', true);
            }
        }
    });
});
