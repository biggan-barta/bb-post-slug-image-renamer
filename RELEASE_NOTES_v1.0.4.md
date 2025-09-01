# Release Notes - v1.0.4

## Bug Fixes
- Fixed a critical issue where post content image URLs were not being properly updated after image renaming
- Added support for various URL formats in post content updates:
  - Standard HTTP/HTTPS URLs
  - Relative URLs
  - Protocol-relative URLs (//domain.com/...)
  - Responsive image srcset attributes
- Added support for updating image URLs stored in post meta fields

## Improvements
- Enhanced URL replacement logic to handle all variations of image URLs
- Added support for handling responsive image srcset attributes
- Improved handling of URLs in post meta data
- Added more comprehensive logging for URL updates

## Technical Details
- Implemented regex-based URL replacement for more accurate updates
- Added new function to handle post meta URL updates
- Improved path handling for more reliable URL replacements
- Enhanced debug logging for better troubleshooting

## Compatibility
- WordPress: 5.0 and above
- PHP: 7.0 and above

## Known Issues
- None

---

For more information, please visit the [plugin's GitHub repository](https://github.com/biggan-barta/bb-post-slug-image-renamer).

## Support
If you encounter any issues or need assistance, please [create an issue](https://github.com/biggan-barta/bb-post-slug-image-renamer/issues) on our GitHub repository.
