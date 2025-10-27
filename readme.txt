=== Custom Product Image Upload ===
Contributors: nowdigiverse
Tags: woocommerce, product, image, upload, cropping, custom, ecommerce, guest, session
Requires at least: 5.2
Tested up to: 6.4
Requires PHP: 7.2
Stable tag: 1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Allow customers (including guests) to upload and crop custom images for WooCommerce products before adding them to cart.

== Description ==

Custom Product Image Upload is a powerful WooCommerce extension that enables both logged-in customers and guest users to upload and crop custom images for specific products before adding them to their cart. Perfect for personalized products, custom designs, or any scenario where customers need to provide their own images.

= Key Features =

* **Guest Upload Support**: Allow guest users to upload images without requiring account registration
* **Session-Based Tracking**: Secure guest session management for temporary file storage
* **Multi-Product Support**: Configure image upload settings for individual WooCommerce products
* **Advanced Image Cropping**: Built-in cropper with real-time preview using Cropper.js
* **Flexible Configuration**: Set allowed file types, image count, file size limits, and resolution requirements
* **Resolution Validation**: Optional minimum/maximum width and height validation
* **Custom Styling**: Customizable button text, colors, and styling options
* **Bulk Management**: Configure multiple products at once with bulk settings
* **Enhanced Security**: Advanced security with rate limiting, behavior analysis, and guest upload protection
* **Smart File Management**: Automatic cleanup of abandoned uploads with intelligent preservation
* **Admin Dashboard**: Comprehensive admin interface for managing all product configurations
* **Data Management**: Import/export configurations and comprehensive data management tools
* **Performance Optimized**: CDN caching and optimized asset loading

= Use Cases =

* **Custom T-Shirts**: Customers upload their designs
* **Photo Products**: Personalized photo books, mugs, or prints
* **Custom Artwork**: Customers provide their artwork for printing
* **Logo Products**: Businesses upload logos for branded merchandise
* **Wedding/Event Products**: Custom invitations or decorations

= Technical Features =

* **WooCommerce Integration**: Seamlessly integrates with WooCommerce cart and checkout
* **Guest User Support**: Full functionality for non-registered users with session tracking
* **Responsive Design**: Works perfectly on desktop, tablet, and mobile devices
* **Advanced File Security**: Multi-layer security with validation, sanitization, and behavior analysis
* **Rate Limiting**: Intelligent rate limiting to prevent abuse (10/hour for guests, 50/hour for logged-in users)
* **Temporary File Management**: Smart cleanup system with 48-hour grace period and order protection
* **Performance**: Optimized for speed with conditional asset loading and CDN support
* **Standards Compliant**: Follows WordPress and WooCommerce coding standards

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/custom-product-image-upload` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Ensure WooCommerce is installed and activated
4. Go to WooCommerce > Custom Product Image Upload to configure your first product
5. Set up image upload settings for your products and start accepting custom images!

== Screenshots ==

1. Admin interface showing product configuration
2. Frontend image upload interface with cropping tool
3. Bulk configuration settings for multiple products

== Changelog ==

= 1.1 =
* NEW: Guest Upload Support - Allow non-registered users to upload images without account creation
* NEW: Session-Based Tracking - Secure guest session management for temporary file storage
* NEW: Smart File Management - Automatic cleanup of abandoned uploads with intelligent preservation
* NEW: Enhanced Security - Advanced rate limiting and behavior analysis for guest uploads
* NEW: Temporary File System - Automatic cleanup of abandoned uploads with smart preservation logic
* IMPROVED: User Experience - Seamless upload experience for both logged-in and guest users
* IMPROVED: Security - Enhanced security measures specifically designed for guest uploads
* IMPROVED: Performance - Optimized file management with automatic cleanup reducing server storage
* IMPROVED: Monitoring - Enhanced logging and statistics for guest activity tracking
* FIXED: Authentication Barriers - Removed authentication requirements for guest uploads while maintaining security
* Enhanced multi-product support with per-product configurations
* Improved admin interface with bulk management and 6-tab interface
* Added resolution validation options and comprehensive file validation
* Performance optimizations with CDN caching and optimized file management
* Added comprehensive data management tools with Import/Export functionality
* Improved mobile responsiveness and seamless user experience
* Enterprise Security - Multi-layer security implementation with real-time monitoring
* Security Monitoring - Statistics dashboard, audit trails, and detailed logging
* Secure Upload Class - Enterprise-grade file validation with content security scanning
* Enhanced File Validation - Double MIME type validation with finfo_file() and exif_imagetype()
* Path Traversal Protection - Comprehensive filename and path security
* Execution Prevention - .htaccess protection and non-executable permissions
* User Activity Tracking - Monitor unique users and upload patterns with guest activity
* Failed Attempt Analysis - Security threat detection and monitoring
* Fixed various bugs and security issues

= 1.0 =
* Initial release
* Basic image upload and cropping functionality
* WooCommerce integration
* Admin configuration interface

== Upgrade Notice ==

= 1.1 =
Major update with guest upload support, enhanced security, and smart file management. Significantly improves user experience by removing authentication barriers while maintaining security. Recommended for all users.

= 1.1 =
Major update with enhanced multi-product support, improved security, and performance optimizations. Recommended for all users.

== FAQ ==

= Does this plugin work with WooCommerce? =

Yes, this plugin is specifically designed for WooCommerce and requires WooCommerce to be installed and activated.

= Can guest users upload images without creating an account? =

Yes! Version 1.2 introduces full guest upload support. Guests can upload images using secure session tracking without needing to register or log in.

= How are guest uploads managed and secured? =

Guest uploads use session-based tracking with advanced security measures including rate limiting (10 uploads/hour for guests), behavior analysis, and automatic cleanup of abandoned files after 48 hours.

= What happens to uploaded files if a guest doesn't complete their order? =

Files are automatically cleaned up after 48 hours if they're not associated with a completed order or active cart session. This prevents server storage bloat while protecting customer files.

= What file types are supported? =

By default, the plugin supports JPEG, PNG, GIF, and WebP images. File types can be configured per product in the admin settings.

= Can I set different image requirements for different products? =

Yes, you can configure individual settings for each product including file types, image count, file size limits, and resolution requirements.

= Is there a limit on file size? =

Yes, file size limits can be configured per product. The default is 10MB, but this can be adjusted based on your needs.

= Are there upload rate limits? =

Yes, to prevent abuse: guests are limited to 10 uploads per hour, while logged-in users can upload up to 50 files per hour.

= Does the plugin work on mobile devices? =

Yes, the plugin is fully responsive and works on desktop, tablet, and mobile devices.

= Can customers crop their images before uploading? =

Yes, the plugin includes a built-in cropper that allows customers to crop their images with a real-time preview.

= Is the plugin secure? =

Yes, the plugin follows WordPress security best practices with multi-layer validation, sanitization, nonce verification, rate limiting, and behavior analysis for both guest and logged-in users.

== Support ==

For support, please visit our [support page](https://nowdigiverse.com/contact/) or contact us at [support@nowdigiverse.com](mailto:hello@nowdigiverse.com).

== Privacy Policy ==

This plugin does not collect or store any personal data beyond what is necessary for its functionality. Uploaded images are stored securely on your server and are not transmitted to third parties. 

For guest users, the plugin creates temporary session identifiers to manage uploads, but no personal information is collected or stored. Guest session data is automatically cleaned up when sessions expire or orders are completed.

== Credits ==

* Cropper.js for the image cropping functionality
* WooCommerce for the e-commerce framework
* WordPress for the content management system
