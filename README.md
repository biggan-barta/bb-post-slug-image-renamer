# Post Slug Image Renamer

A WordPress plugin that automatically renames uploaded images with the post slug when uploading from the post editor, while leaving Media Library uploads unchanged.

## Description

Post Slug Image Renamer helps organize your WordPress media files by automatically renaming images based on the post slug when they are uploaded through the post editor (featured images, content images, etc.). This makes your media files more organized and SEO-friendly.

## Key Features

- ✅ **Smart Detection**: Only renames images uploaded from post editor
- ✅ **Media Library Safe**: Leaves direct Media Library uploads unchanged
- ✅ **SEO Friendly**: Uses post slug as filename base
- ✅ **Conflict Prevention**: Adds random suffixes to prevent filename conflicts
- ✅ **Configurable**: Multiple settings to customize behavior
- ✅ **Statistics**: Track renamed files and view statistics
- ✅ **Multi Post Type**: Support for posts, pages, and custom post types

## How It Works

1. **Post Editor Uploads**: When you upload images through the post editor (featured image, insert media, etc.), they get renamed with the post slug
2. **Media Library Uploads**: Direct uploads to the Media Library keep their original filenames
3. **Filename Format**: `post-slug-random-suffix.extension`
4. **Conflict Prevention**: Random suffixes prevent filename conflicts

## Installation

1. Upload the plugin files to `/wp-content/plugins/post-slug-image-renamer/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure settings in 'Image Renamer' menu in WordPress admin

## Settings

### General Settings

- **Enable Plugin**: Turn the plugin functionality on or off
- **Separator**: Character used to separate words in filename (default: hyphen)
- **Maximum Length**: Maximum length of the post slug part of the filename
- **Include Timestamp**: Add timestamp to make filenames even more unique
- **Allowed Post Types**: Select which post types should have their uploaded images renamed

## Examples

If your post slug is "my-awesome-blog-post" and you upload "IMG_1234.jpg":

- Without timestamp: `my-awesome-blog-post-abc123.jpg`
- With timestamp: `my-awesome-blog-post-1640995200-abc123.jpg`

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher

## Frequently Asked Questions

### Q: Will this plugin rename existing images?
A: No, this plugin only affects new uploads. Existing images in your Media Library remain unchanged.

### Q: What happens if I upload images directly to Media Library?
A: Images uploaded directly to the Media Library will keep their original filenames. Only uploads through the post editor are renamed.

### Q: Can I choose which post types use this feature?
A: Yes, you can select which post types should have their uploaded images renamed in the plugin settings.

### Q: Is there a way to see which files have been renamed?
A: Yes, the plugin includes a Statistics page showing all renamed files with details.

## Support

For support and feature requests, please create an issue in the plugin repository.

## License

This plugin is licensed under the GPL v2 or later.

## Changelog

### 1.0.0
- Initial release
- Automatic image renaming based on post slug
- Configurable settings
- Statistics tracking
- Multi post type support
