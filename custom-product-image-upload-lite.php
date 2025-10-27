<?php
namespace CPIU\Lite;

/**
 * Plugin Name:	   Custom Product Image Upload Lite
 * Plugin URI:		https://nowdigiverse.com/products/
 * Description:	   Allows users to upload images for a single designated WooCommerce product before adding to cart. Upgrade to Pro for multi-product support, cropping, drag & drop, and more advanced features.
 * Version:		   1.1-lite
 * Author:			Nowdigiverse
 * Author URI:		https://nowdigiverse.com/
 * License:		   GPL v2 or later
 * License URI:	   https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:	   custom-product-image-upload-lite
 * Domain Path:	   /i18n/languages
 * Requires at least: 5.2
 * Requires PHP:	  7.2
 * WC requires at least: 3.5
 * WC tested up to:   9.0
 * Network:		   false
 */

// SECURITY: Prevent direct file access and unauthorized execution
if ( ! defined( 'WPINC' ) ) {
    // Log potential security breach attempt
    if (function_exists('error_log')) {
        error_log('CPIU_SECURITY_BREACH: Direct file access attempt to ' . __FILE__ . ' from IP: ' . (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'Unknown'));
    }
    
    // Send security headers
    if (!headers_sent()) {
        header('HTTP/1.1 403 Forbidden');
        header('X-Frame-Options: DENY');
        header('X-Content-Type-Options: nosniff'); 
        header('X-XSS-Protection: 1; mode=block');
    }
    
    exit('Access denied. This file cannot be accessed directly for security reasons.');
}

// --- SECURITY: Lite Version Flag with Tamper Detection ---
define( 'CPIU_IS_LITE', true );

// SECURITY: File integrity check to detect tampering
if (!function_exists('cpiu_verify_file_integrity')) {
    function cpiu_verify_file_integrity() {
        // Check if critical files have been modified
        $critical_files = array(
            __FILE__,
            CPIU_PLUGIN_PATH . 'includes/class-cpiu-data-manager.php',
            CPIU_PLUGIN_PATH . 'includes/class-cpiu-ajax-handler.php'
        );
        
        foreach ($critical_files as $file) {
            if (file_exists($file)) {
                $content = file_get_contents($file);
                
                // Check for suspicious modifications that might bypass security
                $suspicious_patterns = array(
                    '/define\s*\(\s*[\'"]CPIU_IS_LITE[\'"]\s*,\s*false\s*\)/',  // Attempt to set CPIU_IS_LITE to false
                    '/\$this->is_lite_version\(\)\s*===?\s*false/',              // Bypassing lite version checks
                    '/return\s+false;\s*\/\/.*lite.*bypass/',                    // Bypass comments
                    '/cpiu_is_lite_version\(\)\s*&&\s*false/',                   // Disabling lite checks
                );
                
                foreach ($suspicious_patterns as $pattern) {
                    if (preg_match($pattern, $content)) {
                        cpiu_log_security_event('file_tampering_detected', array(
                            'file' => basename($file),
                            'pattern' => $pattern,
                            'severity' => 'critical'
                        ));
                        
                        // In production, you might want to disable the plugin or take other action
                        if (WP_DEBUG) {
                            wp_die('SECURITY: File tampering detected in ' . basename($file) . '. Plugin disabled for security.');
                        }
                    }
                }
            }
        }
    }
}

// Run integrity check during plugin initialization
\add_action('init', __NAMESPACE__ . '\\cpiu_verify_file_integrity', 1);

// --- Define Plugin Constants (Available immediately) ---
if ( ! \defined( 'CPIU_PLUGIN_PATH' ) ) {
    \define( 'CPIU_PLUGIN_PATH', \plugin_dir_path( __FILE__ ) );
}
if ( ! \defined( 'CPIU_PLUGIN_URL' ) ) {
    \define( 'CPIU_PLUGIN_URL', \plugin_dir_url( __FILE__ ) );
}
if ( ! \defined( 'CPIU_VERSION' ) ) {
    \define( 'CPIU_VERSION', '1.1-lite' );
}
if ( ! \defined( 'CPIU_OPTIONS_GROUP' ) ) {
    \define( 'CPIU_OPTIONS_GROUP', 'cpiu_options_group' );
}
if ( ! \defined( 'CPIU_OPTIONS_NAME' ) ) {
    \define( 'CPIU_OPTIONS_NAME', 'cpiu_settings' );
}
if ( ! \defined( 'CPIU_UPLOAD_DIR_NAME' ) ) {
    \define( 'CPIU_UPLOAD_DIR_NAME', 'custom_product_images' );
}

// --- Include Required Files ---
require_once CPIU_PLUGIN_PATH . 'includes/class-cpiu-data-manager.php';
require_once CPIU_PLUGIN_PATH . 'includes/class-cpiu-secure-upload.php';
require_once CPIU_PLUGIN_PATH . 'includes/class-cpiu-ajax-handler.php';
require_once CPIU_PLUGIN_PATH . 'includes/class-cpiu-admin-interface.php';
require_once CPIU_PLUGIN_PATH . 'includes/class-cpiu-frontend-manager.php';
require_once CPIU_PLUGIN_PATH . 'includes/class-cpiu-cdn-cache.php';

/**
 * Check if this is the lite version
 * 
 * @return bool True if lite version
 */
function cpiu_is_lite_version() {
    // SECURITY: Multi-layer validation to prevent bypass attempts
    
    // Layer 1: Constant check (basic)
    $constant_check = defined('CPIU_IS_LITE') && CPIU_IS_LITE === true;
    
    // Layer 2: File-based validation (harder to bypass)
    $file_check = basename(__FILE__) === 'custom-product-image-upload-lite.php';
    
    // Layer 3: Version string validation  
    $version_check = defined('CPIU_VERSION') && strpos(CPIU_VERSION, 'lite') !== false;
    
    // Layer 4: Plugin header validation (most secure)
    $plugin_data = get_file_data(__FILE__, array('Name' => 'Plugin Name'), 'plugin');
    $header_check = isset($plugin_data['Name']) && strpos($plugin_data['Name'], 'Lite') !== false;
    
    // Require all checks to pass for maximum security
    return $constant_check && $file_check && $version_check && $header_check;
}

/**
 * Check if a feature is enabled in the current version
 * 
 * @param string $feature Feature name (cropping, multi_product, drag_drop, modal_upload, blocks_integration)
 * @return bool True if feature is enabled
 */
function cpiu_is_feature_enabled($feature) {
    // In LITE version, ALL premium features are disabled (return false)
    // This keeps the code simple - only basic single product upload is available
    if (cpiu_is_lite_version()) {
        // Only basic features enabled in lite
        $lite_enabled_features = array(
            'basic_upload' => true,
            'single_product' => true
        );
        
        return isset($lite_enabled_features[$feature]) ? $lite_enabled_features[$feature] : false;
    }
    
    // Pro version: All features enabled
    return true;
}

/**
 * SECURITY: Get client IP address with proper proxy handling
 * 
 * @return string Client IP address
 */
function cpiu_get_client_ip() {
    $ip_keys = array('HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
    
    foreach ($ip_keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ips = explode(',', $_SERVER[$key]);
            $ip = trim($ips[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
}

/**
 * SECURITY: Log security events for audit trail
 * 
 * @param string $event_type Type of security event
 * @param array $data Additional event data
 */
function cpiu_log_security_event($event_type, $data = array()) {
    if (!WP_DEBUG) {
        return; // Only log in debug mode to avoid filling logs in production
    }
    
    $log_entry = array(
        'timestamp' => current_time('mysql'),
        'event_type' => $event_type,
        'data' => $data,
        'request_uri' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '',
        'referer' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : ''
    );
    
    error_log('CPIU_SECURITY: ' . wp_json_encode($log_entry));
}

/**
 * Load plugin text domain for internationalization.
 */
function cpiu_load_textdomain() {
	load_plugin_textdomain( 'custom-product-image-upload-lite', false, dirname( plugin_basename( __FILE__ ) ) . '/i18n/languages' );
}
add_action( 'init', __NAMESPACE__ . '\\cpiu_load_textdomain' );

/**
 * Schedule cleanup of guest uploads
 */
function cpiu_schedule_guest_cleanup() {
    if (!wp_next_scheduled('cpiu_cleanup_guest_uploads')) {
        wp_schedule_event(time(), 'daily', 'cpiu_cleanup_guest_uploads');
    }
}
add_action('wp', __NAMESPACE__ . '\\cpiu_schedule_guest_cleanup');

/**
 * Cleanup abandoned guest uploads (older than 48 hours)
 */
function cpiu_cleanup_abandoned_guest_uploads() {
    $upload_dir_info = wp_upload_dir();
    $upload_dir = $upload_dir_info['basedir'] . '/' . CPIU_UPLOAD_DIR_NAME;
    
    if (!is_dir($upload_dir)) {
        return;
    }
    
    $files = glob($upload_dir . '/prod-*');
    $cutoff_time = time() - (48 * 60 * 60); // 48 hours ago
    
    foreach ($files as $file) {
        if (is_file($file) && filemtime($file) < $cutoff_time) {
            // Check if this file is associated with any active cart or completed order
            $filename = basename($file);
            
            // Skip if file is associated with completed orders
            if (cpiu_is_file_in_completed_order($filename)) {
                continue;
            }
            
            // Skip if file is in active cart sessions
            if (cpiu_is_file_in_active_cart($filename)) {
                continue;
            }
            
            // Safe to delete
            unlink($file);
            error_log('CPIU: Cleaned up abandoned guest upload: ' . $filename);
        }
    }
}
add_action('cpiu_cleanup_guest_uploads', __NAMESPACE__ . '\\cpiu_cleanup_abandoned_guest_uploads');

/**
 * Check if file is associated with completed orders
 */
function cpiu_is_file_in_completed_order($filename) {
    global $wpdb;
    
    $orders = $wpdb->get_results($wpdb->prepare("
        SELECT om.order_id 
        FROM {$wpdb->prefix}woocommerce_order_itemmeta om
        INNER JOIN {$wpdb->prefix}woocommerce_order_items oi ON om.order_item_id = oi.order_item_id
        WHERE om.meta_key = 'cpiu_uploaded_images' 
        AND om.meta_value LIKE %s
    ", '%' . $filename . '%'));
    
    return !empty($orders);
}

/**
 * Check if file is in active cart sessions
 */
function cpiu_is_file_in_active_cart($filename) {
    global $wpdb;
    
    // Check WooCommerce sessions for cart data containing this filename
    $sessions = $wpdb->get_results($wpdb->prepare("
        SELECT session_value 
        FROM {$wpdb->prefix}woocommerce_sessions 
        WHERE session_expiry > %d
    ", time()));
    
    foreach ($sessions as $session) {
        $cart_data = maybe_unserialize($session->session_value);
        if (isset($cart_data['cart']) && is_array($cart_data['cart'])) {
            foreach ($cart_data['cart'] as $cart_item) {
                if (isset($cart_item['cpiu_uploaded_images']) && is_array($cart_item['cpiu_uploaded_images'])) {
                    foreach ($cart_item['cpiu_uploaded_images'] as $image_url) {
                        if (strpos($image_url, $filename) !== false) {
                            return true;
                        }
                    }
                }
            }
        }
    }
    
    return false;
}

/**
 * Initialize the plugin after all other plugins are loaded.
 * This ensures checks for other plugins like WooCommerce are reliable.
 */
function cpiu_initialize_plugin() {

    // --- WooCommerce Dependency Check ---
    // Check if WooCommerce is active *after* all plugins are loaded.
    if ( ! \class_exists( 'WooCommerce' ) ) {
        \add_action('admin_notices', __NAMESPACE__ . '\\cpiu_woocommerce_inactive_notice');
        return; // Stop the plugin initialization if WC is not active
    }

    // --- Initialize Multi-Product Classes ---
    // Initialize the new multi-product functionality
    // Debug: Check if classes exist
    if (! \class_exists('CPIU\\Lite\\CPIU_Data_Manager')) {
        \error_log('CPIU ERROR: CPIU_Data_Manager class not found in namespace CPIU\\Lite');
        return;
    }
    
    new CPIU_Data_Manager();
    new CPIU_Ajax_Handler();
    
    // CDN Cache: Pro feature only
    if (!cpiu_is_lite_version()) {
        new CPIU_CDN_Cache();
    }
	
	// --- WooCommerce HPOS Compatibility ---
	// Declare compatibility with WooCommerce High-Performance Order Storage
	// This must be called in before_woocommerce_init action
	\add_action('before_woocommerce_init', function() {
		if (\class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
		}
	});
	
	// Initialize admin interface only in admin area
	if (\is_admin()) {
        new CPIU_Admin_Interface();
		
		// Enqueue admin notices script
		\add_action('admin_enqueue_scripts', __NAMESPACE__ . '\\cpiu_enqueue_admin_notices_script');
	}
	
	// Initialize frontend manager only on frontend
	if (!\is_admin()) {
        new CPIU_Frontend_Manager();
	}

    // --- Legacy Single-Product Support (for backward compatibility) ---
    // Keep existing functionality for backward compatibility
    // This will be removed in future versions after migration is complete

    // Frontend Display & Script Enqueueing (Legacy)
    \add_action('woocommerce_before_add_to_cart_button', __NAMESPACE__ . '\\cpiu_custom_product_image_upload_field', 20);
    \add_action('wp_enqueue_scripts', __NAMESPACE__ . '\\cpiu_enqueue_frontend_scripts');
    
    // Lite version: Add upgrade prompt styles
    if (cpiu_is_lite_version()) {
        \add_action('wp_enqueue_scripts', __NAMESPACE__ . '\\cpiu_enqueue_lite_styles');
    }

    // Cart & Order Integration (Legacy) - DISABLED (handled by CPIU_Frontend_Manager)
    // add_filter('woocommerce_get_item_data', 'cpiu_display_uploaded_images_in_cart', 10, 2);
    // add_action('woocommerce_checkout_create_order_line_item', 'cpiu_save_uploaded_images_to_order_meta', 10, 4);
    // add_action('woocommerce_order_item_meta_end', 'cpiu_display_uploaded_images_on_order_pages', 10, 3);
    // add_action('woocommerce_after_order_itemmeta', 'cpiu_display_uploaded_images_in_admin', 10, 3);
}

// Hook the initializer function to run after plugins are loaded
add_action( 'plugins_loaded', __NAMESPACE__ . '\\cpiu_initialize_plugin' );

/**
 * Enqueue admin notices JavaScript
 */
function cpiu_enqueue_admin_notices_script() {
	wp_enqueue_script(
		'cpiu-admin-notices',
		CPIU_PLUGIN_URL . 'assets/js/cpiu-admin-notices.js',
		array('jquery'),
		CPIU_VERSION,
		true
	);
	
	// Localize script with nonces
	wp_localize_script('cpiu-admin-notices', 'cpiu_admin_notices', array(
		'dismiss_nonce' => wp_create_nonce('cpiu_dismiss_notice'),
		'data_preference_nonce' => wp_create_nonce('cpiu_data_preference'),
		'dismiss_data_nonce' => wp_create_nonce('cpiu_dismiss_data_notice')
	));
}

/**
 * Display the admin notice if WooCommerce is inactive.
 */
function cpiu_woocommerce_inactive_notice() {
    echo '<div class="notice notice-error is-dismissible">';
    echo '<p>' . esc_html__( 'Custom Product Image Upload Lite requires WooCommerce to be installed and active.', 'custom-product-image-upload-lite' ) . '</p>';
    echo '</div>';
}

// Function Definitions (These are now called by hooks added in cpiu_initialize_plugin)

/**
 * Add the admin menu item.
 */
function cpiu_add_admin_menu() { /* Legacy UI disabled in favor of new tabbed admin */ }

/**
 * Register plugin settings.
 */
function cpiu_register_settings() { /* Legacy settings registration disabled */ }

/**
 * Sanitize settings input.
 */
function cpiu_sanitize_settings( $input ) {
    $sanitized_input = array();
    $defaults = cpiu_get_default_options();

    $sanitized_input['product_id'] = isset( $input['product_id'] ) ? absint( $input['product_id'] ) : $defaults['product_id'];
    $sanitized_input['image_count'] = isset( $input['image_count'] ) ? max( 1, absint( $input['image_count'] ) ) : $defaults['image_count'];
    $sanitized_input['button_text'] = isset( $input['button_text'] ) ? sanitize_text_field( $input['button_text'] ) : $defaults['button_text'];

    if ( isset( $input['button_color'] ) && preg_match( '/^#([a-fA-F0-9]{6}|[a-fA-F0-9]{3})$/', $input['button_color'] ) ) {
        $sanitized_input['button_color'] = $input['button_color'];
    } else {
        $sanitized_input['button_color'] = $defaults['button_color'];
    }

    return $sanitized_input;
}

/**
 * Get default plugin options.
 */
function cpiu_get_default_options() {
    return array(
        'product_id'   => 0,
        'image_count'  => 9,
        'button_text'  => esc_html__( 'Upload Images', 'custom-product-image-upload-lite' ),
        'button_color' => '#4CAF50', // Default green color
    );
}

/**
 * Get default multi-product options.
 */
function cpiu_get_default_multi_product_options() {
    return array(
        'enabled' => 1,
        'max_files' => 1,
        'max_size' => 5242880, // 5MB
        'allowed_types' => array('jpg', 'jpeg', 'png', 'gif', 'webp'),
        'upload_label' => esc_html__( 'Upload Images', 'custom-product-image-upload-lite' ),
        'primary_color' => '#4CAF50',
        'require_exact_count' => 1,
        'show_crop_tool' => cpiu_is_feature_enabled('cropping') ? 1 : 0, // Disabled in lite
        'crop_aspect_ratio' => '1:1',
        'auto_crop_area' => 0.8
    );
}

/**
 * Get saved plugin options, merging with defaults.
 */
function cpiu_get_options() {
    $defaults = cpiu_get_default_options();
    $saved_options = get_option( CPIU_OPTIONS_NAME, $defaults );
    return wp_parse_args( $saved_options, $defaults ); // Ensure all keys exist
}

/**
 * Callback for the settings section description.
 */
function cpiu_general_section_callback() {
    echo '<p>' . esc_html__( 'Configure the settings for the custom product image upload feature.', 'custom-product-image-upload-lite' ) . '</p>';
}

/**
 * Render the Product ID input field.
 */
function cpiu_product_id_render() {
    $options = cpiu_get_options();
    printf(
        '<input type="number" name="%s[product_id]" value="%d" min="0">',
        esc_attr( CPIU_OPTIONS_NAME ),
        esc_attr( $options['product_id'] )
    );
    echo '<p class="description">' . esc_html__( 'Enter the WooCommerce Product ID where the upload field should appear.', 'custom-product-image-upload-lite' ) . '</p>';
}

/**
 * Render the Image Count input field.
 */
function cpiu_image_count_render() {
    $options = cpiu_get_options();
    printf(
        '<input type="number" name="%s[image_count]" value="%d" min="1">',
        esc_attr( CPIU_OPTIONS_NAME ),
        esc_attr( $options['image_count'] )
    );
    echo '<p class="description">' . esc_html__( 'How many images must the user upload?', 'custom-product-image-upload-lite' ) . '</p>';
}

/**
 * Render the Button Text input field.
 */
function cpiu_button_text_render() {
    $options = cpiu_get_options();
    printf(
        '<input type="text" name="%s[button_text]" value="%s" class="regular-text">',
        esc_attr( CPIU_OPTIONS_NAME ),
        esc_attr( $options['button_text'] )
    );
    echo '<p class="description">' . esc_html__( 'The text label for the file upload input.', 'custom-product-image-upload-lite' ) . '</p>';
}

/**
 * Render the Button Color input field.
 */
function cpiu_button_color_render() {
    $options = cpiu_get_options();
    
    if ( defined('CPIU_PLUGIN_URL') ) {
        // Enqueue the color picker scripts specifically for the admin page
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'cpiu-color-picker', CPIU_PLUGIN_URL . 'assets/js/cpiu-color-picker.js', array( 'wp-color-picker' ), defined('CPIU_VERSION') ? CPIU_VERSION : '1.1.1', true );

        printf(
            '<input type="text" name="%s[button_color]" value="%s" class="cpiu-color-field">',
            esc_attr( CPIU_OPTIONS_NAME ),
            esc_attr( $options['button_color'] )
        );
        echo '<p class="description">' . esc_html__( 'Select the primary color used for elements like the progress bar.', 'custom-product-image-upload-lite' ) . '</p>';
    } else {
        echo '<p>' . esc_html__( 'Error: Plugin URL constant not defined.', 'custom-product-image-upload-lite' ) . '</p>';
    }
}

/**
 * Display the HTML for the settings page.
 */
function cpiu_settings_page_html() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields( CPIU_OPTIONS_GROUP );
            do_settings_sections( 'custom-image-upload-settings' );
            submit_button( esc_html__( 'Save Settings', 'custom-product-image-upload-lite' ) );
            ?>
        </form>
    </div>
    <?php
}

// --- Frontend Display & Script Functions ---

/**
 * Display the custom image upload field on the single product page.
 */
function cpiu_custom_product_image_upload_field() {
    global $post;
    $options = cpiu_get_options();
    $specific_product_id = $options['product_id'];
    $required_image_count = $options['image_count'];
    $button_label = $options['button_text'];
    $progress_bar_color = $options['button_color'];

    // Only show if a valid product ID is set and it matches the current product
    if ( $specific_product_id <= 0 || ! is_product() || ! isset($post) || $post->ID !== $specific_product_id ) {
        return;
    }
    ?>
    <div class="custom-image-upload cpiu-container">
        <label for="cpiu_custom_images"><?php echo esc_html($button_label); ?></label>
        <input type="file" id="cpiu_custom_images" name="cpiu_custom_images[]" multiple accept="image/*" style="display: block; margin-top: 5px;"/>
        <p class="cpiu-hint"><?php printf( esc_html__( 'Please upload exactly %d images.', 'custom-product-image-upload-lite' ), $required_image_count ); ?></p>

        <div id="cpiu-image-preview" style="margin-top: 15px; display: flex; gap: 10px; flex-wrap: wrap; border: 1px dashed #ccc; padding: 10px; min-height: 100px;">
        </div>

        <div id="cpiu-error-container" style="color: red; margin-top: 10px; font-weight: bold;"></div>

        <div id="cpiu-upload-progress" style="display: none; margin-top: 15px;">
            <div style="width: 100%; background-color: #f3f3f3; border-radius: 5px;">
                <div id="cpiu-progress-bar" style="height: 20px; width: 0%; background-color: <?php echo esc_attr($progress_bar_color); ?>; border-radius: 5px; text-align: center; line-height: 20px; color: white; transition: width 0.3s ease;"></div>
            </div>
            <p id="cpiu-progress-text" style="text-align: center; margin-top: 5px; font-size: 0.9em;"></p>
        </div>
    </div>

    <?php if (cpiu_is_feature_enabled('cropping')): ?>
    <div id="cpiu-cropper-modal" style="display:none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 100000; justify-content: center; align-items: center; padding: 20px;">
        <div style="position: relative; background: #fff; padding: 20px; border-radius: 8px; max-width: 90vw; max-height: 90vh; overflow: auto; display: flex; flex-direction: column;">
            <h3 style="margin-top: 0; margin-bottom: 15px; text-align: center;"><?php esc_html_e('Crop Image', 'custom-product-image-upload-lite'); ?></h3>
            <div id="cpiu-cropper-container" style="max-width: 500px; max-height: 50vh; margin-bottom: 15px;">
                <img id="cpiu-image-to-crop" src="" alt="<?php esc_attr_e('Image to crop', 'custom-product-image-upload-lite'); ?>" style="max-width: 100%; display: block;" />
            </div>
            <div style="text-align: center; margin-top: auto;">
                <button id="cpiu-save-cropped-image" class="button alt" style="margin-right: 10px;"><?php esc_html_e('Save Crop', 'custom-product-image-upload-lite'); ?></button>
                <button id="cpiu-close-cropper-modal" class="button"><?php esc_html_e('Cancel', 'custom-product-image-upload-lite'); ?></button>
            </div>
        </div>
    </div>
    <?php else: ?>
    <!-- Cropper feature disabled in lite version - show upgrade prompt -->
    <?php endif; ?>

    <div id="cpiu-upload-loading" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.75); z-index: 100001; justify-content: center; align-items: center;">
        <div style="background: white; padding: 30px 40px; border-radius: 8px; text-align: center; box-shadow: 0 5px 15px rgba(0,0,0,0.2);">
            <h3 style="margin-top:0; margin-bottom: 20px;"><?php esc_html_e('Uploading Your Images', 'custom-product-image-upload-lite'); ?></h3>
            <div class="cpiu-spinner" style="border: 5px solid #f3f3f3; border-top: 5px solid <?php echo esc_attr($progress_bar_color); ?>; border-radius: 50%; width: 50px; height: 50px; animation: cpiu_spin 1s linear infinite; margin: 0 auto 20px auto;"></div>
            <div id="cpiu-upload-status" style="margin-bottom: 15px;">
                <p><?php esc_html_e('Please wait...', 'custom-product-image-upload-lite'); ?></p>
            </div>
            <style>
                @keyframes cpiu_spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
            </style>
        </div>
    </div>
    <?php
}

/**
 * Enqueue Cropper.js library only on the specific product page.
 * LITE VERSION: Cropper is disabled, function kept for code structure
 */
function cpiu_enqueue_cropper_js() {
    // Cropper disabled in lite version
    return;
}

/**
 * Enqueue lite-specific styles for upgrade prompts and disabled features
 */
function cpiu_enqueue_lite_styles() {
    global $post;
    $options = cpiu_get_options();
    $specific_product_id = $options['product_id'];

    if ( $specific_product_id > 0 && is_product() && isset($post) && $post->ID === $specific_product_id ) {
        wp_enqueue_style('cpiu-frontend-lite', CPIU_PLUGIN_URL . 'assets/css/cpiu-frontend-lite.css', array(), CPIU_VERSION);
    }
}

/**
 * Enqueue frontend JavaScript and localize data.
 */
function cpiu_enqueue_frontend_scripts() {
    global $post;
    $options = cpiu_get_options();
    $specific_product_id = $options['product_id'];

    // Only enqueue and localize if we are on the target product page
    if ( $specific_product_id > 0 && is_product() && isset($post) && $post->ID === $specific_product_id ) {

        $ajax_nonce = wp_create_nonce("cpiu_image_upload_nonce");
        $version = defined('CPIU_VERSION') ? CPIU_VERSION : '1.1.1';

        $script_data = array(
            'ajax_url'			 => admin_url( 'admin-ajax.php' ),
            'nonce'				=> $ajax_nonce,
            'required_image_count' => $options['image_count'],
            'progress_bar_color'   => $options['button_color'],
            'is_lite_version'      => cpiu_is_lite_version(),
            'features_enabled'     => array(
                'cropping' => cpiu_is_feature_enabled('cropping'),
                'drag_drop' => cpiu_is_feature_enabled('drag_drop'),
                'modal_upload' => cpiu_is_feature_enabled('modal_upload')
            ),
            'error_less_images'	=> sprintf( esc_html__( 'Please upload exactly %d images to proceed.', 'custom-product-image-upload-lite' ), $options['image_count'] ),
            'error_more_images'	=> sprintf( esc_html__( 'You can only upload %d images. Please remove the extra images.', 'custom-product-image-upload-lite' ), $options['image_count'] ),
            'text_loading'		 => esc_html__('Reading files...', 'custom-product-image-upload-lite'),
            'text_loading_file'	=> esc_html__('Reading {filename}...', 'custom-product-image-upload-lite'),
            'text_loaded_progress' => esc_html__('Read {loaded} of {total} files ({percent}%)', 'custom-product-image-upload-lite'),
            'text_error_loading'   => esc_html__('Error reading file: {filename}', 'custom-product-image-upload-lite'),
            'text_skipped_file'	=> esc_html__('Skipped non-image file: {filename}', 'custom-product-image-upload-lite'),
            'text_ajax_starting'   => esc_html__('Uploading Images...', 'custom-product-image-upload-lite'),
            'text_ajax_preparing'  => esc_html__('Preparing images...', 'custom-product-image-upload-lite'),
            'text_ajax_uploading'  => esc_html__('Processing images... {percent}%', 'custom-product-image-upload-lite'),
            'text_ajax_complete'   => esc_html__('Images Uploaded! Adding to cart...', 'custom-product-image-upload-lite'),
            'text_ajax_error'	  => esc_html__('Error Uploading images: {message}', 'custom-product-image-upload-lite'),
            'text_ajax_status_error' => esc_html__('Server error during Uploading. Status: {status}', 'custom-product-image-upload-lite'),
            'text_ajax_network_error' => esc_html__('Network error during Uploading.', 'custom-product-image-upload-lite'),
            'cart_url'			 => wc_get_cart_url()
        );

        wp_register_script( 'cpiu-frontend-js-data', '', [], $version, true );
        wp_enqueue_script( 'cpiu-frontend-js-data' );
        wp_localize_script( 'cpiu-frontend-js-data', 'cpiu_params', $script_data );

        // Hook the function to output the inline script into the footer
        add_action('wp_footer', __NAMESPACE__ . '\\cpiu_output_inline_script', 99);
    }
}

/**
 * Outputs the inline JavaScript in the footer.
 * This function is hooked by cpiu_enqueue_frontend_scripts.
 */
function cpiu_output_inline_script() {
    // Check if our localized data script has been enqueued and printed
    if (!wp_script_is('cpiu-frontend-js-data', 'done')) {
        return; // Don't output if dependencies aren't loaded
    }
    
    // Check if cropper is required and loaded (only for pro version)
    if (cpiu_is_feature_enabled('cropping') && !wp_script_is('cropper-js', 'done')) {
        return; // Don't output if cropper is required but not loaded
    }
    ?>
    <script id="cpiu-inline-frontend-script">
    // --- Start JavaScript ---
    document.addEventListener('DOMContentLoaded', function () {
        if (typeof cpiu_params === 'undefined') {
            console.error('CPIU Error: cpiu_params not loaded.');
            return;
        }

        // Check if cropper is required but not available
        if (cpiu_params.features_enabled.cropping && typeof Cropper === 'undefined') {
            console.error('CPIU Error: Cropper.js not loaded but cropping is enabled.');
            return;
        }

        const fileInput = document.getElementById('cpiu_custom_images');
        const previewContainer = document.getElementById('cpiu-image-preview');
        const errorContainer = document.getElementById('cpiu-error-container');
        const cropperModal = document.getElementById('cpiu-cropper-modal');
        const imageToCrop = document.getElementById('cpiu-image-to-crop');
        const saveCropButton = document.getElementById('cpiu-save-cropped-image');
        const closeCropButton = document.getElementById('cpiu-close-cropper-modal');
        const addToCartButton = document.querySelector('form.cart .single_add_to_cart_button');
        const uploadProgressContainer = document.getElementById('cpiu-upload-progress');
        const progressBar = document.getElementById('cpiu-progress-bar');
        const progressText = document.getElementById('cpiu-progress-text');
        const uploadLoadingModal = document.getElementById('cpiu-upload-loading');
        const uploadStatusText = document.getElementById('cpiu-upload-status');

        if (!fileInput || !previewContainer || !errorContainer || !addToCartButton || !uploadProgressContainer || !progressBar || !progressText || !uploadLoadingModal || !uploadStatusText) {
            console.error('CPIU Error: One or more essential DOM elements are missing.');
            if(errorContainer) {
                errorContainer.textContent = 'Initialization failed (DOM). Please contact support.';
            }
            return;
        }

        let cropperInstance = null;
        let currentImageElement = null;
        let uploadedImageData = []; // Array of { originalSrc, currentSrc, element, wrapper }

        const requiredCount = parseInt(cpiu_params.required_image_count, 10);
        addToCartButton.disabled = true;

        fileInput.addEventListener('change', handleFileSelection);
        
        // Only add cropper event listeners if cropping is enabled
        if (cpiu_params.features_enabled.cropping && saveCropButton && closeCropButton) {
            saveCropButton.addEventListener('click', handleSaveCrop);
            closeCropButton.addEventListener('click', handleCloseCropper);
        }

        function handleFileSelection(event) {
            const files = event.target.files;
            if (!files || files.length === 0) {
                return;
            }

            if (uploadProgressContainer) {
                uploadProgressContainer.style.display = 'block';
            }
            if (progressBar) {
                progressBar.style.width = '0%';
            }
            if (progressText) {
                progressText.textContent = cpiu_params.text_loading;
            }

            let filesProcessed = 0;
            const totalFiles = files.length;
            const newFilesArray = Array.from(files);

            newFilesArray.forEach(file => {
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onloadstart = () => { if (progressText) progressText.textContent = cpiu_params.text_loading_file.replace('{filename}', file.name); };
                    reader.onload = (e) => {
                        if (uploadedImageData.length < requiredCount) { addImagePreview(e.target.result, file.name); }
                        else { updateValidationError(cpiu_params.error_more_images); console.warn(`CPIU: Max ${requiredCount} images. Skipped: ${file.name}`); }
                        filesProcessed++; updateFileReadProgress(filesProcessed, totalFiles); validateImageCount();
                    };
                    reader.onerror = () => { console.error(`CPIU: Error reading ${file.name}`); if (progressText) progressText.textContent = cpiu_params.text_error_loading.replace('{filename}', file.name); filesProcessed++; updateFileReadProgress(filesProcessed, totalFiles); };
                    reader.readAsDataURL(file);
                } else { if (progressText) progressText.textContent = cpiu_params.text_skipped_file.replace('{filename}', file.name); filesProcessed++; updateFileReadProgress(filesProcessed, totalFiles); }
            });
            fileInput.value = ''; // Clear input
        }

        function updateFileReadProgress(loaded, total) {
            if (!progressBar || !progressText) return;
            const percent = total > 0 ? Math.round((loaded / total) * 100) : 0;
            progressBar.style.width = percent + '%';
            progressText.textContent = cpiu_params.text_loaded_progress.replace('{loaded}', loaded).replace('{total}', total).replace('{percent}', percent);
            if (loaded >= total) { setTimeout(() => { if (uploadProgressContainer) uploadProgressContainer.style.display = 'none'; }, 1500); }
        }

        function addImagePreview(imageSrc, imageName) {
            if (!previewContainer) return;
            const wrapper = document.createElement('div');
            wrapper.style.cssText = 'position:relative; display:inline-block; margin:5px; vertical-align:top; border:1px solid #eee; padding:2px; width:105px; height:105px; overflow:hidden;';
            const imgElement = document.createElement('img');
            imgElement.src = imageSrc; imgElement.alt = imageName || 'Preview';
            imgElement.style.cssText = 'display:block; width:100%; height:100%; object-fit:cover; cursor:pointer;';
            
            // Only add crop functionality if enabled
            if (cpiu_params.features_enabled.cropping) {
                imgElement.title = 'Click to crop'; 
                imgElement.classList.add('cpiu-preview-image');
            } else {
                imgElement.title = 'Image preview';
                imgElement.style.cursor = 'default';
            }
            
            const deleteBtn = document.createElement('button');
            deleteBtn.innerHTML = '&times;'; deleteBtn.type = 'button'; deleteBtn.ariaLabel = 'Delete image'; deleteBtn.title = 'Delete image';
            deleteBtn.style.cssText = 'position:absolute; top:3px; right:3px; background:rgba(0,0,0,0.7); color:white; border:none; border-radius:50%; width:22px; height:22px; line-height:20px; text-align:center; cursor:pointer; font-size:16px; font-weight:bold; padding:0; z-index:10;';
            wrapper.appendChild(imgElement); wrapper.appendChild(deleteBtn); previewContainer.appendChild(wrapper);
            const imageData = { originalSrc: imageSrc, currentSrc: imageSrc, element: imgElement, wrapper: wrapper };
            uploadedImageData.push(imageData);
            
            // Only add crop functionality if enabled
            if (cpiu_params.features_enabled.cropping) {
                imgElement.addEventListener('click', () => handleOpenCropper(imageData));
            }
            deleteBtn.addEventListener('click', () => handleDeleteImage(imageData));
        }

        function handleOpenCropper(imageData) {
            if (!cpiu_params.features_enabled.cropping || !cropperModal || !imageToCrop || typeof Cropper === 'undefined') return;
            currentImageElement = imageData.element; imageToCrop.src = imageData.currentSrc; cropperModal.style.display = 'flex';
            if (cropperInstance) { cropperInstance.destroy(); }
            cropperInstance = new Cropper(imageToCrop, { aspectRatio: 1 / 1, viewMode: 1, autoCropArea: 0.8, responsive: true, background: false, modal: false, guides: true, center: true, highlight: false, cropBoxMovable: true, cropBoxResizable: true, toggleDragModeOnDblclick: false });
            if (addToCartButton) addToCartButton.disabled = true; updateValidationError('');
        }

        function handleSaveCrop(event) {
            if (!cpiu_params.features_enabled.cropping) return;
            event.preventDefault(); event.stopPropagation();
            if (!cropperInstance || !currentImageElement) { console.error("CPIU Error: Cropper/image ref missing."); return; }
            const canvas = cropperInstance.getCroppedCanvas({ width: 500, height: 500, fillColor: '#fff', imageSmoothingEnabled: true, imageSmoothingQuality: 'medium' });
            if (canvas) {
                const croppedImageDataUrl = canvas.toDataURL('image/jpeg', 0.9);
                const imageDataIndex = uploadedImageData.findIndex(data => data.element === currentImageElement);
                if (imageDataIndex > -1) { uploadedImageData[imageDataIndex].element.src = croppedImageDataUrl; uploadedImageData[imageDataIndex].currentSrc = croppedImageDataUrl; }
                else { console.error("CPIU Error: Could not find image data to update."); }
                handleCloseCropper();
            } else { console.error("CPIU Error: Could not get cropped canvas."); alert('Could not crop image.'); }
        }

        function handleCloseCropper() {
            if (!cpiu_params.features_enabled.cropping) return;
            if (cropperModal) cropperModal.style.display = 'none';
            if (cropperInstance) { cropperInstance.destroy(); cropperInstance = null; }
            currentImageElement = null; validateImageCount();
        }

        function handleDeleteImage(imageData) {
            if (!imageData || !imageData.wrapper) return;
            imageData.wrapper.remove(); uploadedImageData = uploadedImageData.filter(data => data !== imageData); validateImageCount();
        }

        function validateImageCount() {
            const currentCount = uploadedImageData.length; let errorMessage = ''; let isButtonDisabled = true;
            if (currentCount < requiredCount) { errorMessage = cpiu_params.error_less_images; isButtonDisabled = true; }
            else if (currentCount > requiredCount) { errorMessage = cpiu_params.error_more_images; isButtonDisabled = true; }
            else { errorMessage = ''; isButtonDisabled = false; }
            updateValidationError(errorMessage);
            if (addToCartButton) {
                addToCartButton.disabled = isButtonDisabled;
                addToCartButton.removeEventListener('click', handleAddToCartSubmission); // Remove first
                if (!isButtonDisabled) { addToCartButton.addEventListener('click', handleAddToCartSubmission); } // Add if enabled
            }
        }

        function updateValidationError(message) { if (errorContainer) { errorContainer.textContent = message; errorContainer.style.display = message ? 'block' : 'none'; } }

        function handleAddToCartSubmission(event) {
            event.preventDefault(); event.stopPropagation();
            if (uploadedImageData.length !== requiredCount) { validateImageCount(); alert(cpiu_params.error_less_images); return; }
            if (uploadLoadingModal) uploadLoadingModal.style.display = 'flex'; if (uploadStatusText) uploadStatusText.innerHTML = `<p>${cpiu_params.text_ajax_starting}</p>`; if (addToCartButton) addToCartButton.disabled = true;
            const imagesToSend = uploadedImageData.map(data => data.currentSrc); const formData = new FormData();
            formData.append('action', 'cpiu_upload_cropped_images'); formData.append('images_data', JSON.stringify(imagesToSend)); formData.append('security', cpiu_params.nonce);
            fetch(cpiu_params.ajax_url, { method: 'POST', body: formData })
            .then(response => { if (!response.ok) { return response.text().then(text => { throw new Error(`HTTP error! Status: ${response.status}, Response: ${text}`); }); } return response.json(); })
            .then(data => {
                if (data.success) { if (uploadStatusText) uploadStatusText.innerHTML = `<p style="color:green;">${cpiu_params.text_ajax_complete}</p>`; setTimeout(() => { window.location.href = cpiu_params.cart_url; }, 1200); }
                else { const serverMessage = data.data?.message || 'Unknown server error.'; console.error('CPIU AJAX Error:', serverMessage, data); alert(cpiu_params.text_ajax_error.replace('{message}', serverMessage)); if (uploadLoadingModal) uploadLoadingModal.style.display = 'none'; if (addToCartButton) addToCartButton.disabled = false; }
            })
            .catch(error => { console.error('CPIU Fetch Error:', error); alert(cpiu_params.text_ajax_network_error + ` (${error.message})`); if (uploadLoadingModal) uploadLoadingModal.style.display = 'none'; if (addToCartButton) addToCartButton.disabled = false; });
        }
        validateImageCount(); // Initial validation
    });
    // --- End JavaScript ---
    </script>
    <?php
}

// --- Cart & Order Functions (Keep all existing functionality) ---

/**
 * Display uploaded image thumbnails in the cart.
 */
function cpiu_display_uploaded_images_in_cart( $item_data, $cart_item ) {
    if ( isset( $cart_item['cpiu_uploaded_images'] ) && is_array( $cart_item['cpiu_uploaded_images'] ) ) {
        $image_html = '<div class="cpiu-cart-thumbnails">';
        foreach ( $cart_item['cpiu_uploaded_images'] as $image_url ) { 
            if ( filter_var( trim($image_url), FILTER_VALIDATE_URL ) ) { 
                $image_html .= '<div class="cpiu-order-image-container"><img class="cpiu-order-image" src="' . esc_url( $image_url ) . '" alt="' . esc_attr__( 'Uploaded Thumb', 'custom-product-image-upload-lite' ) . '"></div>'; 
            } 
        } 
        $image_html .= '</div>';
        $item_data[] = array( 'key' => esc_html__( 'Uploaded Images', 'custom-product-image-upload-lite' ), 'display' => $image_html, 'hidden' => false );
    } 
    return $item_data;
}

/**
 * Save uploaded image URLs to order item meta when order is created.
 */
function cpiu_save_uploaded_images_to_order_meta( $item, $cart_item_key, $values, $order ) {
    if ( isset( $values['cpiu_uploaded_images'] ) && is_array( $values['cpiu_uploaded_images'] ) ) { 
        $item->add_meta_data( '_cpiu_uploaded_images', implode( ', ', $values['cpiu_uploaded_images'] ), true ); 
    }
}

/**
 * Display uploaded image thumbnails on customer-facing order details pages.
 */
function cpiu_display_uploaded_images_on_order_pages( $item_id, $item, $order ) {
    if ( ! $item instanceof WC_Order_Item_Product ) { return; } 
    $uploaded_images_str = $item->get_meta( '_cpiu_uploaded_images' );
    if ( $uploaded_images_str ) {
        echo '<div class="cpiu-order-item-images" style="margin-top: 8px;"><strong style="display: block; margin-bottom: 3px;">' . esc_html__( 'Uploaded Images:', 'custom-product-image-upload-lite' ) . '</strong>';
        $images = explode( ', ', $uploaded_images_str );
        foreach ( $images as $image_url ) { 
            $trimmed_url = trim( $image_url ); 
            if ( filter_var( $trimmed_url, FILTER_VALIDATE_URL ) ) { 
                echo '<a href="' . esc_url( $trimmed_url ) . '" target="_blank" rel="noopener noreferrer" style="display: inline-block; margin: 3px 3px 3px 0;"><img src="' . esc_url( $trimmed_url ) . '" alt="' . esc_attr__( 'Uploaded Image', 'custom-product-image-upload-lite' ) . '" style="width: 50px; height: 50px; border: 1px solid #ddd; object-fit: cover; vertical-align: middle;"></a>'; 
            } 
        } 
        echo '</div>';
    }
}

/**
 * Display uploaded image thumbnails in the admin order edit screen.
 */
function cpiu_display_uploaded_images_in_admin( $item_id, $item, $product ) {
    if ( ! $item instanceof WC_Order_Item_Product ) { return; } 
    $uploaded_images_str = $item->get_meta( '_cpiu_uploaded_images' );
    if ( $uploaded_images_str ) {
        echo '<div class="cpiu-admin-order-item-images" style="margin-top: 5px;"><strong style="display: block; margin-bottom: 3px;">' . esc_html__( 'Uploaded Images:', 'custom-product-image-upload-lite' ) . '</strong>';
        $images = explode( ', ', $uploaded_images_str );
        foreach ( $images as $image_url ) { 
            $trimmed_url = trim( $image_url ); 
            if ( filter_var( $trimmed_url, FILTER_VALIDATE_URL ) ) { 
                echo '<a href="' . esc_url( $trimmed_url ) . '" target="_blank" rel="noopener noreferrer" style="display: inline-block; margin: 2px 2px 2px 0;"><img src="' . esc_url( $trimmed_url ) . '" alt="' . esc_attr__( 'Uploaded Image', 'custom-product-image-upload-lite' ) . '" style="width: 45px; height: 45px; border: 1px solid #ccc; object-fit: cover; vertical-align: middle;"></a>'; 
            } 
        } 
        echo '</div>';
    }
}

// --- Activation Hook (Remains outside the initializer function) ---

/**
 * Actions on plugin activation.
 * Shows installation warnings and data management options.
 */
function cpiu_activate() {
    // Check if this is a fresh installation or reactivation
    $is_fresh_install = false === get_option( CPIU_OPTIONS_NAME );
    
    // Create upload directory
    $upload_dir_info = wp_upload_dir();
    $custom_upload_path = $upload_dir_info['basedir'] . '/' . CPIU_UPLOAD_DIR_NAME;

    if ( ! file_exists( $custom_upload_path ) ) {
        wp_mkdir_p( $custom_upload_path );
        @file_put_contents( $custom_upload_path . '/index.php', '<?php // Silence is golden.' );
        @file_put_contents( $custom_upload_path . '/.htaccess', 'Options -Indexes' );
    }
    
    // Set default options for fresh installations
    if ( $is_fresh_install ) {
        add_option( CPIU_OPTIONS_NAME, cpiu_get_default_options() );
        add_option( 'cpiu_default_settings', cpiu_get_default_multi_product_options() );
        add_option( 'cpiu_multi_product_configs', array() );
        
        // Set installation timestamp
        add_option( 'cpiu_installation_date', current_time( 'mysql' ) );
        
        // Set version flag for lite
        add_option( 'cpiu_plugin_version', 'lite' );
    }
    
    // Show installation notice
    add_option( 'cpiu_show_installation_notice', true );
}
register_activation_hook( __FILE__, 'cpiu_activate' );

// Register uninstall hook - handled by uninstall.php
register_uninstall_hook( __FILE__, 'cpiu_uninstall_plugin' );

// Register deactivation hook - NO DATA DELETION on deactivation
register_deactivation_hook( __FILE__, 'cpiu_deactivate' );

/**
 * Actions on plugin deactivation.
 * IMPORTANT: This function does NOT delete any data as per Envato requirements.
 */
function cpiu_deactivate() {
    // Clear any cached data but keep all options and uploaded files
    wp_cache_flush();
    
    // Remove installation notice if it exists
    delete_option( 'cpiu_show_installation_notice' );
    
    // Log deactivation for debugging purposes
    error_log( 'Custom Product Image Upload Lite plugin deactivated. Data preserved.' );
}

/**
 * Show installation notice after plugin activation.
 */
function cpiu_show_installation_notice() {
    if ( get_option( 'cpiu_show_installation_notice' ) ) {
        ?>
        <div class="notice notice-success is-dismissible" id="cpiu-installation-notice">
            <h3><?php esc_html_e( 'Custom Product Image Upload Lite - Installation Complete!', 'custom-product-image-upload-lite' ); ?></h3>
            <p>
                <?php esc_html_e( 'Thank you for installing Custom Product Image Upload Lite. The plugin has been successfully activated.', 'custom-product-image-upload-lite' ); ?>
            </p>
            <p>
                <strong><?php esc_html_e( '✨ Lite Version Features:', 'custom-product-image-upload-lite' ); ?></strong><br>
                <?php esc_html_e( '• Individual product configuration (no limit) • Image cropping • Drag & drop • Modal upload', 'custom-product-image-upload-lite' ); ?>
            </p>
            <p>
                <strong><?php esc_html_e( '🚀 Upgrade to Pro for:', 'custom-product-image-upload-lite' ); ?></strong><br>
                <?php esc_html_e( '• Bulk operations • Edit existing configurations • Import & export settings • CDN caching • Advanced settings & logs', 'custom-product-image-upload-lite' ); ?>
            </p>
            <p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=custom-image-upload-addon' ) ); ?>" class="button button-primary">
                    <?php esc_html_e( 'Configure Plugin', 'custom-product-image-upload-lite' ); ?>
                </a>
                <a href="https://wpbay.com/product/custom-image-upload-addon-for-woocommerce/" class="button button-secondary" target="_blank">
                    <?php esc_html_e( 'Upgrade to Pro', 'custom-product-image-upload-lite' ); ?>
                </a>
                <button type="button" class="button" data-cpiu-action="dismiss-installation">
                    <?php esc_html_e( 'Dismiss', 'custom-product-image-upload-lite' ); ?>
                </button>
            </p>
        </div>
        <?php
    }
}
add_action( 'admin_notices', __NAMESPACE__ . '\\cpiu_show_installation_notice' );

/**
 * Show upgrade migration notice (informs users about seamless migration to Pro)
 */
/**
 * Handle AJAX request to dismiss installation notice.
 */
function cpiu_dismiss_installation_notice_ajax() {
    if ( ! wp_verify_nonce( $_POST['nonce'], 'cpiu_dismiss_notice' ) ) {
        wp_die( 'Security check failed' );
    }
    
    delete_option( 'cpiu_show_installation_notice' );
    wp_send_json_success();
}
add_action( 'wp_ajax_cpiu_dismiss_installation_notice', __NAMESPACE__ . '\\cpiu_dismiss_installation_notice_ajax' );

/**
 * Handle AJAX request to dismiss data notice
 */
function cpiu_dismiss_data_notice_ajax() {
    if (!wp_verify_nonce($_POST['nonce'], 'cpiu_dismiss_data_notice')) {
        wp_die('Security check failed');
    }
    
    // Set a temporary flag to not show the notice again for this session
    update_option('cpiu_data_notice_dismissed', true);
    
    wp_send_json_success();
}
add_action('wp_ajax_cpiu_dismiss_data_notice', __NAMESPACE__ . '\\cpiu_dismiss_data_notice_ajax');

/**
 * Add plugin action links
 */
function cpiu_add_plugin_action_links($links) {
    // Check if WooCommerce is active
    if (class_exists('WooCommerce')) {
        $settings_link = '<a href="' . admin_url('admin.php?page=custom-image-upload-addon') . '">' . esc_html__('Settings', 'custom-product-image-upload-lite') . '</a>';
        $upgrade_link = '<a href="https://wpbay.com/product/custom-image-upload-addon-for-woocommerce/" target="_blank" style="color: #39b54a; font-weight: bold;">' . esc_html__('Upgrade to Pro', 'custom-product-image-upload-lite') . '</a>';
        array_unshift($links, $settings_link, $upgrade_link);
    } else {
        // Show a disabled link with tooltip when WooCommerce is inactive
        $disabled_link = '<span style="color: #999; cursor: not-allowed;" title="' . esc_attr__('Activate WooCommerce plugin to configure settings', 'custom-product-image-upload-lite') . '">' . esc_html__('Settings', 'custom-product-image-upload-lite') . '</span>';
        array_unshift($links, $disabled_link);
    }
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), __NAMESPACE__ . '\\cpiu_add_plugin_action_links');

/**
 * Secured file serving endpoint
 */
function cpiu_serve_uploaded_file() {
    // Check if this is our file request
    if (!isset($_GET['cpiu_file']) || !isset($_GET['cpiu_nonce'])) {
        return;
    }
    
    // Verify nonce for security (with error logging for debugging)
    if (!wp_verify_nonce($_GET['cpiu_nonce'], 'cpiu_serve_file')) {
        error_log('CPIU: Nonce verification failed for file serving. Nonce: ' . sanitize_text_field($_GET['cpiu_nonce']));
        wp_die(esc_html__('Security check failed.', 'custom-product-image-upload-lite'));
    }
    
    // Check user permissions - allow both logged in users and guests with valid sessions
    $is_logged_in = is_user_logged_in();
    $has_valid_session = false;
    
    if (!$is_logged_in) {
        // For guest users, check if they have a valid session
        if (!session_id()) {
            session_start();
        }
        $has_valid_session = isset($_SESSION['cpiu_guest_id']) && !empty($_SESSION['cpiu_guest_id']);
    }
    
    if (!$is_logged_in && !$has_valid_session) {
        error_log('CPIU: Unauthenticated user without valid session attempted to access file: ' . sanitize_text_field($_GET['cpiu_file']));
        wp_die(esc_html__('Authentication required.', 'custom-product-image-upload-lite'));
    }
    
    $filename = sanitize_file_name($_GET['cpiu_file']);
    
    // Validate filename format (should be our secure format)
    if (!preg_match('/^prod-\d+-\d+-[a-zA-Z0-9]{8,32}-\d+\..*\.(jpg|jpeg|png|gif|webp)$/i', $filename) && 
        !preg_match('/^prod-\d+-\d+-[a-zA-Z0-9]{8,32}-\d+\.(jpg|jpeg|png|gif|webp)$/i', $filename)) {
        error_log('CPIU: Invalid filename pattern: ' . $filename);
        wp_die(esc_html__('Invalid file request.', 'custom-product-image-upload-lite'));
    }
    
    $upload_dir_info = wp_upload_dir();
    $file_path = $upload_dir_info['basedir'] . '/' . CPIU_UPLOAD_DIR_NAME . '/' . $filename;
    
    // Check if file exists
    if (!file_exists($file_path)) {
        error_log('CPIU: File not found: ' . $file_path);
        wp_die(esc_html__('File not found.', 'custom-product-image-upload-lite'));
    }
    
    // Get file info
    $file_info = pathinfo($file_path);
    $mime_type = wp_check_filetype($file_path)['type'];
    
    if (!$mime_type) {
        error_log('CPIU: Invalid file type for: ' . $file_path);
        wp_die(esc_html__('Invalid file type.', 'custom-product-image-upload-lite'));
    }
    
    // Set headers for file download/viewing
    header('Content-Type: ' . $mime_type);
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: private, max-age=3600');
    header('Pragma: cache');
    
    // Output file
    readfile($file_path);
    exit;
}
\add_action('init', __NAMESPACE__ . '\\cpiu_serve_uploaded_file');

/**
 * Actions on plugin uninstallation.
 * This function is called by the uninstall hook but the actual work is done in uninstall.php
 */
function cpiu_uninstall_plugin() {
    // This function is kept for compatibility but the actual uninstall logic
    // is handled in uninstall.php for better reliability
    error_log( 'Custom Product Image Upload Lite uninstall hook called.' );
}