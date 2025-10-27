<?php
namespace CPIU\Lite;

/**
 * CPIU Admin Interface Class
 * 
 * Handles the admin interface for multi-product configuration
 * 
 * @package Custom_Product_Image_Upload
 * @since 1.2.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class CPIU_Admin_Interface {
    
    /**
     * Data manager instance
     */
    private $data_manager;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->data_manager = new CPIU_Data_Manager();
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        \add_action('admin_menu', array($this, 'add_admin_menu'));
        \add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        \add_action('wp_ajax_cpiu_get_configurations', array($this, 'get_configurations_ajax'));
        \add_action('wp_ajax_cpiu_get_configuration', array($this, 'get_configuration_ajax'));
        \add_action('wp_ajax_cpiu_export_settings', array($this, 'export_settings_ajax'));
        \add_action('wp_ajax_cpiu_import_settings', array($this, 'import_settings_ajax'));
        \add_action('wp_ajax_cpiu_clear_security_logs', array($this, 'clear_security_logs_ajax'));
        \add_action('wp_ajax_cpiu_refresh_cdn_cache', array($this, 'refresh_cdn_cache_ajax'));
        \add_action('wp_ajax_cpiu_clear_cdn_cache', array($this, 'clear_cdn_cache_ajax'));
        \add_action('wp_ajax_cpiu_set_uninstall_preference', array($this, 'set_uninstall_preference_ajax'));
        \add_action('wp_ajax_cpiu_export_for_uninstall', array($this, 'export_for_uninstall_ajax'));
    }
    
    /**
     * Tab slug mappings
     */
    private $tab_slugs = array(
        'default-settings' => 'default-settings',
        'add-configuration' => 'add-configuration', 
        'bulk-operations' => 'bulk-operations',
        'existing-configurations' => 'existing-configurations',
        'import-export' => 'import-export',
        'security-logs' => 'security-logs',
        'cdn-cache' => 'cdn-cache',
        'uninstall-preferences' => 'uninstall-preferences',
        'upgrade-to-pro' => 'upgrade-to-pro'
    );

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        // Change menu title for lite version
        $menu_title = $this->data_manager->is_lite_version() 
            ? esc_html__('Image Upload Lite', 'custom-product-image-upload-lite')
            : esc_html__('Custom Image Upload', 'custom-product-image-upload');
            
        $page_title = $this->data_manager->is_lite_version()
            ? esc_html__('Custom Image Upload Lite Settings', 'custom-product-image-upload-lite')
            : esc_html__('Custom Image Upload Settings', 'custom-product-image-upload');
        
        \add_menu_page(
            $page_title,
            $menu_title,
            'manage_options',
            'custom-image-upload-addon',
            array($this, 'render_admin_page'),
            'dashicons-camera-alt',
            58
        );
    }

    /**
     * Get current tab slug from URL
     */
    private function get_current_tab_slug() {
        $current_tab = 'default-settings'; // Default tab
        
        if (isset($_GET['tab']) && array_key_exists($_GET['tab'], $this->tab_slugs)) {
            $current_tab = sanitize_text_field($_GET['tab']);
        }
        
        return $current_tab;
    }

    /**
     * Generate tab URL
     */
    private function get_tab_url($tab_slug) {
        return \admin_url('admin.php?page=custom-image-upload-addon&tab=' . $tab_slug);
    }
    
    /**
     * Render upgrade prompt banner for lite version
     * 
     * @param string $feature Feature name being restricted
     * @return void
     */
    private function render_upgrade_prompt($feature = '') {
        if (!$this->data_manager->is_lite_version()) {
            return; // Don't show for pro version
        }
        
        $upgrade_url = 'https://wpbay.com/product/custom-image-upload-addon-for-woocommerce/';
        $features_url = 'https://www.nowdigiverse.com/product/image-upload-addon-for-woocommerce/';
        
        // Feature-specific content
        $features = array(
            'bulk_operations' => array(
                'title' => esc_html__('🚀 Bulk Operations - Pro Feature', 'custom-product-image-upload-lite'),
                'description' => esc_html__('Configure multiple products simultaneously with advanced bulk operations.', 'custom-product-image-upload-lite'),
                'benefits' => array(
                    esc_html__('Apply settings to unlimited products at once', 'custom-product-image-upload-lite'),
                    esc_html__('Save hours of configuration time', 'custom-product-image-upload-lite'),
                    esc_html__('Category-based product selection', 'custom-product-image-upload-lite'),
                    esc_html__('Advanced filtering options', 'custom-product-image-upload-lite')
                )
            ),
            'import_export' => array(
                'title' => esc_html__('💾 Import/Export - Pro Feature', 'custom-product-image-upload-lite'),
                'description' => esc_html__('Backup and migrate your settings effortlessly across sites.', 'custom-product-image-upload-lite'),
                'benefits' => array(
                    esc_html__('Export all settings as JSON backup', 'custom-product-image-upload-lite'),
                    esc_html__('Import settings to new sites instantly', 'custom-product-image-upload-lite'),
                    esc_html__('Version control for configurations', 'custom-product-image-upload-lite'),
                    esc_html__('Disaster recovery ready', 'custom-product-image-upload-lite')
                )
            ),
            'security_logs' => array(
                'title' => esc_html__('🔒 Security Logs - Pro Feature', 'custom-product-image-upload-lite'),
                'description' => esc_html__('Advanced security monitoring and audit trails for your uploads.', 'custom-product-image-upload-lite'),
                'benefits' => array(
                    esc_html__('Track all upload attempts', 'custom-product-image-upload-lite'),
                    esc_html__('Detect suspicious activity', 'custom-product-image-upload-lite'),
                    esc_html__('IP blocking and rate limiting', 'custom-product-image-upload-lite'),
                    esc_html__('Detailed audit reports', 'custom-product-image-upload-lite')
                )
            ),
            'cdn_cache' => array(
                'title' => esc_html__('⚡ CDN Cache - Pro Feature', 'custom-product-image-upload-lite'),
                'description' => esc_html__('Supercharge performance with CDN resource caching.', 'custom-product-image-upload-lite'),
                'benefits' => array(
                    esc_html__('Cache external libraries locally', 'custom-product-image-upload-lite'),
                    esc_html__('Reduce external dependencies', 'custom-product-image-upload-lite'),
                    esc_html__('Faster page load times', 'custom-product-image-upload-lite'),
                    esc_html__('Offline fallback support', 'custom-product-image-upload-lite')
                )
            )
        );
        
        $feature_data = isset($features[$feature]) ? $features[$feature] : array(
            'title' => esc_html__('🌟 Pro Feature Unlocked Ahead', 'custom-product-image-upload-lite'),
            'description' => esc_html__('This powerful feature is available in the Pro version.', 'custom-product-image-upload-lite'),
            'benefits' => array(
                esc_html__('Unlock unlimited potential', 'custom-product-image-upload-lite'),
                esc_html__('Priority support included', 'custom-product-image-upload-lite'),
                esc_html__('Regular updates and new features', 'custom-product-image-upload-lite')
            )
        );
        ?>
        <div class="cpiu-upgrade-overlay-wrapper">
            <div class="cpiu-upgrade-overlay">
                <div class="cpiu-upgrade-card">
                    <span class="cpiu-lock-icon">🔒</span>
                    <h3><?php echo wp_kses_post($feature_data['title']); ?></h3>
                    <p><?php echo esc_html($feature_data['description']); ?></p>
                    
                    <ul>
                        <?php foreach ($feature_data['benefits'] as $benefit): ?>
                            <li><?php echo esc_html($benefit); ?></li>
                        <?php endforeach; ?>
                    </ul>
                    
                    <div class="cpiu-upgrade-buttons">
                        <a href="<?php echo esc_url($upgrade_url); ?>" class="cpiu-upgrade-btn" target="_blank" rel="noopener noreferrer">
                            <span class="dashicons dashicons-cart"></span>
                            <?php esc_html_e('Upgrade to Pro Now', 'custom-product-image-upload-lite'); ?>
                        </a>
                        <a href="<?php echo esc_url($features_url); ?>" class="cpiu-upgrade-btn secondary" target="_blank" rel="noopener noreferrer">
                            <span class="dashicons dashicons-info"></span>
                            <?php esc_html_e('See All Features', 'custom-product-image-upload-lite'); ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if ('toplevel_page_custom-image-upload-addon' !== $hook) {
            return;
        }
        
        // Enqueue Select2 for searchable dropdowns
        // Use cached CDN resources or fallback to original URLs
        $cdn_cache = new CPIU_CDN_Cache();
        $select2_css_url = $cdn_cache->get_cached_url('select2', 'css');
        $select2_js_url = $cdn_cache->get_cached_url('select2', 'js');
        
        // Ensure fallback URLs use HTTPS
        $select2_css_fallback = 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/css/select2.min.css';
        $select2_js_fallback = 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/js/select2.min.js';
        
        wp_enqueue_style('select2', $select2_css_url ?: $select2_css_fallback);
        wp_enqueue_script('select2', $select2_js_url ?: $select2_js_fallback, array('jquery'));
        
        // Enqueue color picker
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        
        // Enqueue custom admin scripts
        wp_enqueue_script(
            'cpiu-admin-multi-product',
            CPIU_PLUGIN_URL . 'assets/js/cpiu-admin-multi-product.js',
            array('jquery', 'select2', 'wp-color-picker'),
            CPIU_VERSION,
            true
        );
        
        // Enqueue custom admin styles
        wp_enqueue_style(
            'cpiu-admin-styles',
            CPIU_PLUGIN_URL . 'assets/css/cpiu-admin-styles.css',
            array(),
            CPIU_VERSION
        );
        
        // Localize script
        $nonce = wp_create_nonce('cpiu_admin_nonce');
        error_log('CPIU Admin - Generated nonce: ' . $nonce);
        
        $current_tab = $this->get_current_tab_slug();
        
        wp_localize_script('cpiu-admin-multi-product', 'cpiu_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => $nonce,
            'current_tab' => $current_tab,
            'base_url' => admin_url('admin.php?page=custom-image-upload-addon'),
            'tab_slugs' => $this->tab_slugs,
            'is_lite' => $this->data_manager->is_lite_version(),
            'can_add_product' => $this->data_manager->can_add_product(),
            'configured_count' => $this->data_manager->get_configured_products_count(),
            'strings' => array(
                'search_placeholder' => esc_html__('Search products by name, ID, or SKU...', 'custom-product-image-upload'),
                'no_products_found' => esc_html__('No products found.', 'custom-product-image-upload'),
                'loading' => esc_html__('Loading...', 'custom-product-image-upload'),
                'save_success' => esc_html__('Configuration saved successfully!', 'custom-product-image-upload'),
                'delete_success' => esc_html__('Configuration deleted successfully!', 'custom-product-image-upload'),
                'error' => esc_html__('An error occurred. Please try again.', 'custom-product-image-upload'),
                'confirm_delete' => esc_html__('Are you sure you want to delete this configuration?', 'custom-product-image-upload'),
                'confirm_bulk_delete' => esc_html__('Are you sure you want to delete the selected configurations?', 'custom-product-image-upload'),
                'select_products' => esc_html__('Please select at least one product.', 'custom-product-image-upload'),
                'invalid_config' => esc_html__('Please fill in all required fields correctly.', 'custom-product-image-upload'),
                'export_success' => esc_html__('Settings exported successfully!', 'custom-product-image-upload'),
                'import_success' => esc_html__('Settings imported successfully!', 'custom-product-image-upload'),
                'invalid_file' => esc_html__('Please select a valid JSON file.', 'custom-product-image-upload'),
                'cache_cleared' => esc_html__('CDN cache cleared successfully!', 'custom-product-image-upload'),
                'cache_refreshed' => esc_html__('CDN cache refreshed successfully!', 'custom-product-image-upload'),
                'uninstall_preference_saved' => esc_html__('Uninstall preference saved successfully!', 'custom-product-image-upload'),
                'network_error' => esc_html__('Network error occurred. Please try again.', 'custom-product-image-upload'),
                'select_preference' => esc_html__('Please select a preference before saving.', 'custom-product-image-upload'),
                'failed_clear_cache' => esc_html__('Failed to clear cache.', 'custom-product-image-upload'),
                'network_error_cache' => esc_html__('Network error while clearing cache.', 'custom-product-image-upload'),
                'lite_limit_reached' => esc_html__('Lite version allows only 1 product configuration. Upgrade to Pro for unlimited products!', 'custom-product-image-upload-lite'),
                'lite_bulk_disabled' => esc_html__('Bulk operations are not available in Lite version. Upgrade to Pro!', 'custom-product-image-upload-lite')
            )
        ));
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $configurations = $this->data_manager->get_all_configurations();
        $default_settings = $this->data_manager->get_default_settings();
        $allowed_types = array('jpg', 'jpeg', 'png', 'gif', 'webp');
        $current_tab = $this->get_current_tab_slug();
        ?>
        <div class="wrap cpiu-admin-page">
            
            <!-- Tab Navigation -->
            <nav class="nav-tab-wrapper cpiu-tab-nav">
                <a href="<?php echo esc_url($this->get_tab_url('default-settings')); ?>" class="nav-tab <?php echo esc_attr($current_tab === 'default-settings' ? 'nav-tab-active' : ''); ?>" data-tab="default-settings">
                    <span class="dashicons dashicons-admin-generic"></span>
                    <?php esc_html_e('Default Settings', 'custom-product-image-upload'); ?>
                </a>
                <a href="<?php echo esc_url($this->get_tab_url('add-configuration')); ?>" class="nav-tab <?php echo esc_attr($current_tab === 'add-configuration' ? 'nav-tab-active' : ''); ?>" data-tab="add-configuration">
                    <span class="dashicons dashicons-plus-alt"></span>
                    <?php esc_html_e('Add Configuration', 'custom-product-image-upload'); ?>
                </a>
                <a href="<?php echo esc_url($this->get_tab_url('bulk-operations')); ?>" class="nav-tab <?php echo esc_attr($current_tab === 'bulk-operations' ? 'nav-tab-active' : ''); ?>" data-tab="bulk-operations">
                    <span class="dashicons dashicons-groups"></span>
                    <?php esc_html_e('Bulk Operations', 'custom-product-image-upload'); ?>
                </a>
                <a href="<?php echo esc_url($this->get_tab_url('existing-configurations')); ?>" class="nav-tab <?php echo esc_attr($current_tab === 'existing-configurations' ? 'nav-tab-active' : ''); ?>" data-tab="existing-configurations">
                    <span class="dashicons dashicons-list-view"></span>
                    <?php esc_html_e('Existing Configurations', 'custom-product-image-upload'); ?>
                </a>
                <a href="<?php echo esc_url($this->get_tab_url('import-export')); ?>" class="nav-tab <?php echo esc_attr($current_tab === 'import-export' ? 'nav-tab-active' : ''); ?>" data-tab="import-export">
                    <span class="dashicons dashicons-download"></span>
                    <?php esc_html_e('Import/Export', 'custom-product-image-upload'); ?>
                </a>
                <a href="<?php echo esc_url($this->get_tab_url('security-logs')); ?>" class="nav-tab <?php echo esc_attr($current_tab === 'security-logs' ? 'nav-tab-active' : ''); ?>" data-tab="security-logs">
                    <span class="dashicons dashicons-shield"></span>
                    <?php esc_html_e('Security Logs', 'custom-product-image-upload'); ?>
                </a>
                <a href="<?php echo esc_url($this->get_tab_url('cdn-cache')); ?>" class="nav-tab <?php echo esc_attr($current_tab === 'cdn-cache' ? 'nav-tab-active' : ''); ?>" data-tab="cdn-cache">
                    <span class="dashicons dashicons-performance"></span>
                    <?php esc_html_e('CDN Cache', 'custom-product-image-upload'); ?>
                </a>
                <a href="<?php echo esc_url($this->get_tab_url('uninstall-preferences')); ?>" class="nav-tab <?php echo esc_attr($current_tab === 'uninstall-preferences' ? 'nav-tab-active' : ''); ?>" data-tab="uninstall-preferences">
                    <span class="dashicons dashicons-trash"></span>
                    <?php esc_html_e('Uninstall Preferences', 'custom-product-image-upload'); ?>
                </a>
                <?php if ($this->data_manager->is_lite_version()): ?>
                <a href="<?php echo esc_url($this->get_tab_url('upgrade-to-pro')); ?>" class="nav-tab cpiu-upgrade-tab <?php echo esc_attr($current_tab === 'upgrade-to-pro' ? 'nav-tab-active' : ''); ?>" data-tab="upgrade-to-pro">
                    <span class="dashicons dashicons-star-filled"></span>
                    <?php esc_html_e('Upgrade to Pro', 'custom-product-image-upload-lite'); ?>
                </a>
                <?php endif; ?>
            </nav>
            
            <!-- Tab Content -->
            <div class="cpiu-tab-content">
                
                <!-- Default Settings Tab -->
                <div id="default-settings" class="cpiu-tab-pane <?php echo esc_attr($current_tab === 'default-settings' ? 'active' : ''); ?>">
                    <div class="cpiu-section">
                        <h2><?php esc_html_e('Default Settings', 'custom-product-image-upload'); ?></h2>
                        <p><?php esc_html_e('Configure default settings that will be used as fallback for products without specific configurations.', 'custom-product-image-upload'); ?></p>
                        
                        <form id="cpiu-default-settings-form" class="cpiu-form">
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="default_image_count"><?php esc_html_e('Default Required Images', 'custom-product-image-upload'); ?></label>
                                    </th>
                                    <td>
                                        <input type="number" id="default_image_count" name="image_count" value="<?php echo esc_attr($default_settings['image_count']); ?>" min="1" max="50" class="regular-text" required>
                                        <p class="description"><?php esc_html_e('Default number of images required for products.', 'custom-product-image-upload'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="default_max_file_size"><?php esc_html_e('Default Max File Size (MB)', 'custom-product-image-upload'); ?></label>
                                    </th>
                                    <td>
                                        <input type="number" id="default_max_file_size" name="max_file_size" value="<?php echo esc_attr($default_settings['max_file_size'] / 1024 / 1024); ?>" min="1" step="0.1" class="regular-text" required>
                                        <p class="description"><?php esc_html_e('Default maximum file size in megabytes.', 'custom-product-image-upload'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="default_button_text"><?php esc_html_e('Default Button Text', 'custom-product-image-upload'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="default_button_text" name="button_text" value="<?php echo esc_attr($default_settings['button_text']); ?>" class="regular-text">
                                        <p class="description"><?php esc_html_e('Default text for the upload button.', 'custom-product-image-upload'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="default_button_color"><?php esc_html_e('Default Button Color', 'custom-product-image-upload'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="default_button_color" name="button_color" value="<?php echo esc_attr($default_settings['button_color']); ?>" class="cpiu-color-field">
                                        <p class="description"><?php esc_html_e('Default color for progress bars and buttons.', 'custom-product-image-upload'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label><?php esc_html_e('Default Allowed File Types', 'custom-product-image-upload'); ?></label>
                                    </th>
                                    <td>
                                        <?php
                                        foreach ($allowed_types as $type) {
                                            $checked = in_array($type, $default_settings['allowed_types']) ? 'checked' : '';
                                            ?>
                                            <label class="cpiu-admin-checkbox-label">
                                                <input type="checkbox" class="cpiu-admin-checkbox" name="allowed_types[]" value="<?php echo esc_attr($type); ?>" <?php echo esc_attr($checked); ?>>
                                                <?php echo esc_html(strtoupper($type)); ?>
                                            </label>
                                            <?php
                                        }
                                        ?>
                                        <p class="description"><?php esc_html_e('Default allowed file types for products.', 'custom-product-image-upload'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label><?php esc_html_e('Resolution Validation', 'custom-product-image-upload'); ?></label>
                                    </th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="resolution_validation" value="1" <?php checked(!empty($default_settings['resolution_validation'])); ?>>
                                            <?php esc_html_e('Enable resolution validation for uploaded images', 'custom-product-image-upload'); ?>
                                        </label>
                                        <p class="description"><?php esc_html_e('When enabled, uploaded images must meet minimum and maximum dimension requirements.', 'custom-product-image-upload'); ?></p>
                                    </td>
                                </tr>
                                <tr class="resolution-settings cpiu-admin-form-row" data-show-when="resolution_validation">
                                    <th scope="row">
                                        <label><?php esc_html_e('Minimum Dimensions', 'custom-product-image-upload'); ?></label>
                                    </th>
                                    <td>
                                        <label>
                                            <?php esc_html_e('Width:', 'custom-product-image-upload'); ?>
                                            <input type="number" class="cpiu-admin-input cpiu-dimension-input" name="min_width" value="<?php echo esc_attr($default_settings['min_width']); ?>" min="0" max="10000">
                                            px
                                        </label>
                                        <label class="cpiu-dimension-label-spacing">
                                            <?php esc_html_e('Height:', 'custom-product-image-upload'); ?>
                                            <input type="number" class="cpiu-admin-input cpiu-dimension-input" name="min_height" value="<?php echo esc_attr($default_settings['min_height']); ?>" min="0" max="10000">
                                            px
                                        </label>
                                        <p class="description"><?php esc_html_e('Minimum required dimensions (0 = no minimum).', 'custom-product-image-upload'); ?></p>
                                    </td>
                                </tr>
                                <tr class="resolution-settings cpiu-admin-form-row" data-show-when="resolution_validation">
                                    <th scope="row">
                                        <label><?php esc_html_e('Maximum Dimensions', 'custom-product-image-upload'); ?></label>
                                    </th>
                                    <td>
                                        <label class="cpiu-dimension-label">
                                            <?php esc_html_e('Width:', 'custom-product-image-upload'); ?>
                                            <input type="number" name="max_width" value="<?php echo esc_attr($default_settings['max_width']); ?>" min="0" max="10000" class="cpiu-admin-input cpiu-dimension-input">
                                            px
                                        </label>
                                        <label class="cpiu-dimension-label cpiu-dimension-label-spacing">
                                            <?php esc_html_e('Height:', 'custom-product-image-upload'); ?>
                                            <input type="number" name="max_height" value="<?php echo esc_attr($default_settings['max_height']); ?>" min="0" max="10000" class="cpiu-admin-input cpiu-dimension-input">
                                            px
                                        </label>
                                        <p class="description"><?php esc_html_e('Maximum allowed dimensions (0 = no maximum).', 'custom-product-image-upload'); ?></p>
                                    </td>
                                </tr>
                            </table>
                            <p class="submit">
                                <button type="submit" class="button button-primary"><?php esc_html_e('Save Default Settings', 'custom-product-image-upload'); ?></button>
                            </p>
                        </form>
                    </div>
                </div>
                
                <!-- Add Configuration Tab -->
                <div id="add-configuration" class="cpiu-tab-pane <?php echo esc_attr($current_tab === 'add-configuration' ? 'active' : ''); ?>">
                    <?php 
                    // Add Configuration tab is now fully enabled in lite version
                    // Individual product configurations are allowed
                    ?>
                    <div class="cpiu-section">
                        <h2><?php esc_html_e('Add Product Configuration', 'custom-product-image-upload'); ?></h2>
                        <p><?php esc_html_e('Configure image upload settings for individual products.', 'custom-product-image-upload'); ?></p>
                        
                        <form id="cpiu-add-config-form" class="cpiu-form">
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="product_search"><?php esc_html_e('Select Product', 'custom-product-image-upload'); ?></label>
                                    </th>
                                    <td>
                                        <select id="product_search" name="product_id" class="cpiu-product-select cpiu-admin-select" required>
                                            <option value=""><?php esc_html_e('Search for a product...', 'custom-product-image-upload'); ?></option>
                                        </select>
                                        <p class="description"><?php esc_html_e('Start typing to search for products by name or ID.', 'custom-product-image-upload'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="image_count"><?php esc_html_e('Required Images', 'custom-product-image-upload'); ?></label>
                                    </th>
                                    <td>
                                        <input type="number" id="image_count" name="image_count" value="<?php echo esc_attr($default_settings['image_count']); ?>" min="1" max="50" class="regular-text" required>
                                        <p class="description"><?php esc_html_e('Number of images required for this product.', 'custom-product-image-upload'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="max_file_size"><?php esc_html_e('Max File Size (MB)', 'custom-product-image-upload'); ?></label>
                                    </th>
                                    <td>
                                        <input type="number" id="max_file_size" name="max_file_size" value="<?php echo esc_attr($default_settings['max_file_size'] / 1024 / 1024); ?>" min="1" step="0.1" class="regular-text" required>
                                        <p class="description"><?php esc_html_e('Maximum file size in megabytes.', 'custom-product-image-upload'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="button_text"><?php esc_html_e('Button Text', 'custom-product-image-upload'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="button_text" name="button_text" value="<?php echo esc_attr($default_settings['button_text']); ?>" class="regular-text">
                                        <p class="description"><?php esc_html_e('Text for the upload button (leave empty to use default).', 'custom-product-image-upload'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="button_color"><?php esc_html_e('Button Color', 'custom-product-image-upload'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="button_color" name="button_color" value="<?php echo esc_attr($default_settings['button_color']); ?>" class="cpiu-color-field">
                                        <p class="description"><?php esc_html_e('Color for progress bars and buttons (leave empty to use default).', 'custom-product-image-upload'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label><?php esc_html_e('Allowed File Types', 'custom-product-image-upload'); ?></label>
                                    </th>
                                    <td>
                                        <?php
                                        foreach ($allowed_types as $type) {
                                            $checked = in_array($type, $default_settings['allowed_types']) ? 'checked' : '';
                                            ?>
                                            <label class="cpiu-admin-checkbox-label">
                                                <input type="checkbox" name="allowed_types[]" value="<?php echo esc_attr($type); ?>" <?php echo esc_attr($checked); ?> class="cpiu-admin-checkbox">
                                                <?php echo esc_html(strtoupper($type)); ?>
                                            </label>
                                            <?php
                                        }
                                        ?>
                                        <p class="description"><?php esc_html_e('Allowed file types for this product.', 'custom-product-image-upload'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label><?php esc_html_e('Resolution Validation', 'custom-product-image-upload'); ?></label>
                                    </th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="resolution_validation" value="1" <?php checked(!empty($default_settings['resolution_validation'])); ?>>
                                            <?php esc_html_e('Enable resolution validation for uploaded images', 'custom-product-image-upload'); ?>
                                        </label>
                                        <p class="description"><?php esc_html_e('When enabled, uploaded images must meet minimum and maximum dimension requirements.', 'custom-product-image-upload'); ?></p>
                                    </td>
                                </tr>
                                <tr class="resolution-settings cpiu-admin-form-row" data-show-when="resolution_validation">
                                    <th scope="row">
                                        <label><?php esc_html_e('Minimum Dimensions', 'custom-product-image-upload'); ?></label>
                                    </th>
                                    <td>
                                        <label class="cpiu-dimension-label">
                                            <?php esc_html_e('Width:', 'custom-product-image-upload'); ?>
                                            <input type="number" name="min_width" value="<?php echo esc_attr($default_settings['min_width']); ?>" min="0" max="10000" class="cpiu-admin-input cpiu-dimension-input">
                                            px
                                        </label>
                                        <label class="cpiu-dimension-label cpiu-dimension-label-spacing">
                                            <?php esc_html_e('Height:', 'custom-product-image-upload'); ?>
                                            <input type="number" name="min_height" value="<?php echo esc_attr($default_settings['min_height']); ?>" min="0" max="10000" class="cpiu-admin-input cpiu-dimension-input">
                                            px
                                        </label>
                                        <p class="description"><?php esc_html_e('Minimum required dimensions (0 = no minimum).', 'custom-product-image-upload'); ?></p>
                                    </td>
                                </tr>
                                <tr class="resolution-settings cpiu-conditional-row" data-condition="resolution_validation">
                                    <th scope="row">
                                        <label><?php esc_html_e('Maximum Dimensions', 'custom-product-image-upload'); ?></label>
                                    </th>
                                    <td>
                                        <label class="cpiu-dimension-label">
                                            <?php esc_html_e('Width:', 'custom-product-image-upload'); ?>
                                            <input type="number" name="max_width" value="<?php echo esc_attr($default_settings['max_width']); ?>" min="0" max="10000" class="cpiu-dimension-input">
                                            px
                                        </label>
                                        <label class="cpiu-dimension-label cpiu-dimension-label-spaced">
                                            <?php esc_html_e('Height:', 'custom-product-image-upload'); ?>
                                            <input type="number" name="max_height" value="<?php echo esc_attr($default_settings['max_height']); ?>" min="0" max="10000" class="cpiu-dimension-input">
                                            px
                                        </label>
                                        <p class="description"><?php esc_html_e('Maximum allowed dimensions (0 = no maximum).', 'custom-product-image-upload'); ?></p>
                                    </td>
                                </tr>
                            </table>
                            <p class="submit">
                                <button type="submit" class="button button-primary"><?php esc_html_e('Add Configuration', 'custom-product-image-upload'); ?></button>
                            </p>
                        </form>
                    </div>
                </div>
                
                <!-- Bulk Operations Tab -->
                <div id="bulk-operations" class="cpiu-tab-pane <?php echo esc_attr($current_tab === 'bulk-operations' ? 'active' : ''); ?>">
                    <?php $this->render_upgrade_prompt('bulk_operations'); ?>
                    <div class="cpiu-section<?php echo $this->data_manager->is_lite_version() ? ' cpiu-disabled-section' : ''; ?>">
                        <h2><?php esc_html_e('Bulk Operations', 'custom-product-image-upload'); ?></h2>
                        <p><?php esc_html_e('Apply the same settings to multiple products at once.', 'custom-product-image-upload'); ?></p>
                        
                        <form id="cpiu-bulk-config-form" class="cpiu-form">
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="bulk_product_search"><?php esc_html_e('Select Products', 'custom-product-image-upload'); ?></label>
                                    </th>
                                    <td>
                                        <select id="bulk_product_search" name="product_ids[]" class="cpiu-product-select cpiu-admin-select" multiple required>
                                        </select>
                                        <p class="description"><?php esc_html_e('Select multiple products to apply the same settings.', 'custom-product-image-upload'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="bulk_image_count"><?php esc_html_e('Required Images', 'custom-product-image-upload'); ?></label>
                                    </th>
                                    <td>
                                        <input type="number" id="bulk_image_count" name="image_count" value="<?php echo esc_attr($default_settings['image_count']); ?>" min="1" max="50" class="regular-text" required>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="bulk_max_file_size"><?php esc_html_e('Max File Size (MB)', 'custom-product-image-upload'); ?></label>
                                    </th>
                                    <td>
                                        <input type="number" id="bulk_max_file_size" name="max_file_size" value="<?php echo esc_attr($default_settings['max_file_size'] / 1024 / 1024); ?>" min="1" step="0.1" class="regular-text" required>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="bulk_button_text"><?php esc_html_e('Button Text', 'custom-product-image-upload'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="bulk_button_text" name="button_text" value="<?php echo esc_attr($default_settings['button_text']); ?>" class="regular-text">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label for="bulk_button_color"><?php esc_html_e('Button Color', 'custom-product-image-upload'); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="bulk_button_color" name="button_color" value="<?php echo esc_attr($default_settings['button_color']); ?>" class="cpiu-color-field">
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label><?php esc_html_e('Allowed File Types', 'custom-product-image-upload'); ?></label>
                                    </th>
                                    <td>
                                        <?php
                                        foreach ($allowed_types as $type) {
                                            $checked = in_array($type, $default_settings['allowed_types']) ? 'checked' : '';
                                            ?>
                                            <label class="cpiu-admin-checkbox-label">
                                                <input type="checkbox" name="allowed_types[]" value="<?php echo esc_attr($type); ?>" <?php echo esc_attr($checked); ?> class="cpiu-admin-checkbox">
                                                <?php echo esc_html(strtoupper($type)); ?>
                                            </label>
                                            <?php
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row">
                                        <label><?php esc_html_e('Resolution Validation', 'custom-product-image-upload'); ?></label>
                                    </th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="resolution_validation" value="1" <?php checked(!empty($default_settings['resolution_validation'])); ?>>
                                            <?php esc_html_e('Enable resolution validation for uploaded images', 'custom-product-image-upload'); ?>
                                        </label>
                                        <p class="description"><?php esc_html_e('When enabled, uploaded images must meet minimum and maximum dimension requirements.', 'custom-product-image-upload'); ?></p>
                                    </td>
                                </tr>
                                <tr class="resolution-settings cpiu-conditional-row" data-condition="resolution_validation">
                                    <th scope="row">
                                        <label><?php esc_html_e('Minimum Dimensions', 'custom-product-image-upload'); ?></label>
                                    </th>
                                    <td>
                                        <label class="cpiu-dimension-label">
                                            <?php esc_html_e('Width:', 'custom-product-image-upload'); ?>
                                            <input type="number" name="min_width" value="<?php echo esc_attr($default_settings['min_width']); ?>" min="0" max="10000" class="cpiu-dimension-input">
                                            px
                                        </label>
                                        <label class="cpiu-dimension-label cpiu-dimension-label-spaced">
                                            <?php esc_html_e('Height:', 'custom-product-image-upload'); ?>
                                            <input type="number" name="min_height" value="<?php echo esc_attr($default_settings['min_height']); ?>" min="0" max="10000" class="cpiu-dimension-input">
                                            px
                                        </label>
                                        <p class="description"><?php esc_html_e('Minimum required dimensions (0 = no minimum).', 'custom-product-image-upload'); ?></p>
                                    </td>
                                </tr>
                                <tr class="resolution-settings cpiu-conditional-row" data-condition="resolution_validation">
                                    <th scope="row">
                                        <label><?php esc_html_e('Maximum Dimensions', 'custom-product-image-upload'); ?></label>
                                    </th>
                                    <td>
                                        <label class="cpiu-dimension-label">
                                            <?php esc_html_e('Width:', 'custom-product-image-upload'); ?>
                                            <input type="number" name="max_width" value="<?php echo esc_attr($default_settings['max_width']); ?>" min="0" max="10000" class="cpiu-dimension-input">
                                            px
                                        </label>
                                        <label class="cpiu-dimension-label cpiu-dimension-label-spaced">
                                            <?php esc_html_e('Height:', 'custom-product-image-upload'); ?>
                                            <input type="number" name="max_height" value="<?php echo esc_attr($default_settings['max_height']); ?>" min="0" max="10000" class="cpiu-dimension-input">
                                            px
                                        </label>
                                        <p class="description"><?php esc_html_e('Maximum allowed dimensions (0 = no maximum).', 'custom-product-image-upload'); ?></p>
                                    </td>
                                </tr>
                            </table>
                            <p class="submit">
                                <button type="submit" class="button button-primary"><?php esc_html_e('Apply to Selected Products', 'custom-product-image-upload'); ?></button>
                            </p>
                        </form>
                    </div>
                </div>
                
                <!-- Existing Configurations Tab -->
                <div id="existing-configurations" class="cpiu-tab-pane <?php echo esc_attr($current_tab === 'existing-configurations' ? 'active' : ''); ?>">
                    <div class="cpiu-section">
                        <h2><?php esc_html_e('Existing Configurations', 'custom-product-image-upload'); ?></h2>
                        <p><?php esc_html_e('Manage existing product configurations.', 'custom-product-image-upload'); ?></p>
                        
                        <div id="cpiu-configurations-table">
                            <?php $this->render_configurations_table($configurations); ?>
                        </div>
                    </div>
                </div>
                
                <!-- Import/Export Tab -->
                <div id="import-export" class="cpiu-tab-pane <?php echo esc_attr($current_tab === 'import-export' ? 'active' : ''); ?>">
                    <?php $this->render_upgrade_prompt('import_export'); ?>
                    <div class="cpiu-section<?php echo $this->data_manager->is_lite_version() ? ' cpiu-disabled-section' : ''; ?>">
                        <h2><?php esc_html_e('Import/Export Settings', 'custom-product-image-upload'); ?></h2>
                        <p><?php esc_html_e('Export your current settings or import previously exported settings.', 'custom-product-image-upload'); ?></p>
                        
                        <div class="cpiu-import-export-container">
                            <!-- Export Section -->
                            <div class="cpiu-export-section">
                                <h3><?php esc_html_e('Export Settings', 'custom-product-image-upload'); ?></h3>
                                <p><?php esc_html_e('Download your current plugin settings as a JSON file for backup or migration purposes.', 'custom-product-image-upload'); ?></p>
                                
                                <button type="button" id="cpiu-export-settings" class="button button-primary">
                                    <span class="dashicons dashicons-download cpiu-icon-spacing"></span>
                                    <?php esc_html_e('Export Settings', 'custom-product-image-upload'); ?>
                                </button>
                                
                                <div id="cpiu-export-result" class="cpiu-result-message"></div>
                            </div>
                            
                            <!-- Import Section -->
                            <div class="cpiu-import-section">
                                <h3><?php esc_html_e('Import Settings', 'custom-product-image-upload'); ?></h3>
                                <p><?php esc_html_e('Upload a previously exported settings file to restore your configurations.', 'custom-product-image-upload'); ?></p>
                                
                                <form id="cpiu-import-form" enctype="multipart/form-data">
                                    <input type="file" id="cpiu-import-file" accept=".json" class="cpiu-file-input">
                                    <br>
                                    <button type="submit" id="cpiu-import-settings" class="button button-secondary">
                                        <span class="dashicons dashicons-upload cpiu-icon-spacing"></span>
                                        <?php esc_html_e('Import Settings', 'custom-product-image-upload'); ?>
                                    </button>
                                </form>
                                
                                <div id="cpiu-import-result" class="cpiu-result-message"></div>
                            </div>
                        </div>
                        
                        <div class="cpiu-import-export-notice">
                            <strong><?php esc_html_e('Important Notes:', 'custom-product-image-upload'); ?></strong>
                            <ul class="cpiu-notice-list">
                                <li><?php esc_html_e('Export includes all product configurations, default settings, and legacy settings.', 'custom-product-image-upload'); ?></li>
                                <li><?php esc_html_e('Import will overwrite existing settings. Make sure to export current settings first if you want to keep them.', 'custom-product-image-upload'); ?></li>
                                <li><?php esc_html_e('Uploaded images are not included in the export/import process.', 'custom-product-image-upload'); ?></li>
                                <li><?php esc_html_e('Settings exported from one site may not work perfectly on another site due to different product IDs.', 'custom-product-image-upload'); ?></li>
                            </ul>
                        </div>
                        
                        <div class="cpiu-data-protection-notice">
                            <strong><?php esc_html_e('🔒 Data Protection Notice:', 'custom-product-image-upload'); ?></strong>
                            <p class="cpiu-notice-text">
                                <?php esc_html_e('This plugin follows Envato marketplace standards for data protection:', 'custom-product-image-upload'); ?>
                            </p>
                            <ul class="cpiu-notice-list">
                                <li><?php esc_html_e('Plugin does NOT delete any data when deactivated', 'custom-product-image-upload'); ?></li>
                                <li><?php esc_html_e('All settings and uploaded images are preserved during deactivation', 'custom-product-image-upload'); ?></li>
                                <li><?php esc_html_e('Uninstallation requires explicit user confirmation', 'custom-product-image-upload'); ?></li>
                                <li><?php esc_html_e('Users can choose to keep data even during uninstallation', 'custom-product-image-upload'); ?></li>
                                <li><?php esc_html_e('Export functionality allows users to backup settings before uninstalling', 'custom-product-image-upload'); ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Security Logs Tab -->
                <div id="security-logs" class="cpiu-tab-pane <?php echo esc_attr($current_tab === 'security-logs' ? 'active' : ''); ?>">
                    <?php $this->render_upgrade_prompt('security_logs'); ?>
                    <div class="cpiu-section<?php echo $this->data_manager->is_lite_version() ? ' cpiu-disabled-section' : ''; ?>">
                        <h2><?php esc_html_e('Security Logs', 'custom-product-image-upload'); ?></h2>
                        <p><?php esc_html_e('Monitor all file upload attempts and security events.', 'custom-product-image-upload'); ?></p>
                        
                        <div class="cpiu-security-stats">
                            <?php
                            $logs = CPIU_Secure_Upload::get_upload_logs(1000);
                            $total_attempts = count($logs);
                            $successful_uploads = count(array_filter($logs, function($log) { return $log['success']; }));
                            $failed_uploads = $total_attempts - $successful_uploads;
                            $unique_users = count(array_unique(array_column($logs, 'user_id')));
                            ?>
                            <div class="cpiu-stat-card cpiu-stat-total">
                                <h3 class="cpiu-stat-number"><?php echo esc_html($total_attempts); ?></h3>
                                <p class="cpiu-stat-label"><?php esc_html_e('Total Upload Attempts', 'custom-product-image-upload'); ?></p>
                            </div>
                            <div class="cpiu-stat-card cpiu-stat-success">
                                <h3 class="cpiu-stat-number cpiu-stat-success-number"><?php echo esc_html($successful_uploads); ?></h3>
                                <p class="cpiu-stat-label"><?php esc_html_e('Successful Uploads', 'custom-product-image-upload'); ?></p>
                            </div>
                            <div class="cpiu-stat-card cpiu-stat-failed">
                                <h3 class="cpiu-stat-number cpiu-stat-failed-number"><?php echo esc_html($failed_uploads); ?></h3>
                                <p class="cpiu-stat-label"><?php esc_html_e('Failed Attempts', 'custom-product-image-upload'); ?></p>
                            </div>
                            <div class="cpiu-stat-card cpiu-stat-users">
                                <h3 class="cpiu-stat-number cpiu-stat-users-number"><?php echo esc_html($unique_users); ?></h3>
                                <p class="cpiu-stat-label"><?php esc_html_e('Unique Users', 'custom-product-image-upload'); ?></p>
                            </div>
                        </div>
                        
                        <div class="cpiu-logs-actions">
                            <button type="button" id="cpiu-clear-logs" class="button button-secondary">
                                <span class="dashicons dashicons-trash cpiu-icon-spacing"></span>
                                <?php esc_html_e('Clear All Logs', 'custom-product-image-upload'); ?>
                            </button>
                            <button type="button" id="cpiu-refresh-logs" class="button">
                                <span class="dashicons dashicons-update cpiu-icon-spacing"></span>
                                <?php esc_html_e('Refresh Logs', 'custom-product-image-upload'); ?>
                            </button>
                        </div>
                        
                        <div id="cpiu-logs-table">
                            <?php $this->render_security_logs_table($logs); ?>
                        </div>
                    </div>
                </div>
                
                <!-- CDN Cache Tab -->
                <div id="cdn-cache" class="cpiu-tab-pane <?php echo esc_attr($current_tab === 'cdn-cache' ? 'active' : ''); ?>">
                    <?php $this->render_upgrade_prompt('cdn_cache'); ?>
                    <div class="cpiu-section<?php echo $this->data_manager->is_lite_version() ? ' cpiu-disabled-section' : ''; ?>">
                        <h2><?php esc_html_e('CDN Cache Management', 'custom-product-image-upload'); ?></h2>
                        <p><?php esc_html_e('Manage cached third-party CDN resources to improve performance and prevent throttling.', 'custom-product-image-upload'); ?></p>
                        
                        <?php
                        $cdn_cache = new CPIU_CDN_Cache();
                        $cache_status = $cdn_cache->get_cache_status();
                        ?>
                        
        <div class="cpiu-cache-status">
                            <h3><?php esc_html_e('Cache Status', 'custom-product-image-upload'); ?></h3>
                            
                            <?php foreach ($cache_status as $resource_name => $status): ?>
                                <div class="cpiu-cache-resource">
                                    <h4 class="cpiu-cache-resource-title"><?php echo esc_html($resource_name); ?></h4>
                                    <p class="cpiu-cache-version"><strong><?php esc_html_e('Version:', 'custom-product-image-upload'); ?></strong> <?php echo esc_html($status['version']); ?></p>
                                    
                                    <div class="cpiu-cache-details">
                                        <div class="cpiu-cache-item">
                                            <strong><?php esc_html_e('JavaScript:', 'custom-product-image-upload'); ?></strong>
                                            <?php if ($status['js']): ?>
                                                <span class="cpiu-cache-status-cached">✓ <?php esc_html_e('Cached', 'custom-product-image-upload'); ?></span>
                                                <small class="cpiu-cache-timestamp">
                                                    <?php echo esc_html(sprintf(esc_html__('Cached on %s', 'custom-product-image-upload'), date('Y-m-d H:i:s', $status['js']))); ?>
                                                </small>
                                            <?php else: ?>
                                                <span class="cpiu-cache-status-not-cached">✗ <?php esc_html_e('Not cached', 'custom-product-image-upload'); ?></span>
                                            <?php endif; ?>
            </div>
                                        
                                        <div class="cpiu-cache-item">
                                            <strong><?php esc_html_e('CSS:', 'custom-product-image-upload'); ?></strong>
                                            <?php if ($status['css']): ?>
                                                <span class="cpiu-cache-status-cached">✓ <?php esc_html_e('Cached', 'custom-product-image-upload'); ?></span>
                                                <small class="cpiu-cache-timestamp">
                                                    <?php echo esc_html(sprintf(esc_html__('Cached on %s', 'custom-product-image-upload'), date('Y-m-d H:i:s', $status['css']))); ?>
                                                </small>
                                            <?php else: ?>
                                                <span class="cpiu-cache-status-not-cached">✗ <?php esc_html_e('Not cached', 'custom-product-image-upload'); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="cpiu-cache-actions">
                            <button type="button" id="cpiu-refresh-cdn-cache" class="button button-primary">
                                <span class="dashicons dashicons-update cpiu-icon-spacing"></span>
                                <?php esc_html_e('Refresh All Cache', 'custom-product-image-upload'); ?>
                            </button>
                            <button type="button" id="cpiu-clear-cdn-cache" class="button button-secondary">
                                <span class="dashicons dashicons-trash cpiu-icon-spacing"></span>
                                <?php esc_html_e('Clear All Cache', 'custom-product-image-upload'); ?>
                            </button>
                        </div>
                        
                        <div class="cpiu-cache-info">
                            <h4 class="cpiu-cache-info-title"><?php esc_html_e('About CDN Caching', 'custom-product-image-upload'); ?></h4>
                            <ul class="cpiu-cache-info-list">
                                <li><?php esc_html_e('External CDN resources are automatically cached locally to improve performance', 'custom-product-image-upload'); ?></li>
                                <li><?php esc_html_e('Cache is automatically refreshed every 24 hours', 'custom-product-image-upload'); ?></li>
                                <li><?php esc_html_e('Cached files are stored in your WordPress uploads directory', 'custom-product-image-upload'); ?></li>
                                <li><?php esc_html_e('This prevents throttling from external CDN services', 'custom-product-image-upload'); ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Upgrade to Pro Tab -->
                <div id="upgrade-to-pro" class="cpiu-tab-pane <?php echo esc_attr($current_tab === 'upgrade-to-pro' ? 'active' : ''); ?>">
                    <?php if ($this->data_manager->is_lite_version()): ?>
                    <div class="cpiu-section">
                        <h2><?php esc_html_e('⬆️ Planning to Upgrade to Pro?', 'custom-product-image-upload-lite'); ?></h2>
                        <p class="cpiu-upgrade-intro"><?php esc_html_e('Great news! Your settings and configurations will automatically transfer to the Pro version.', 'custom-product-image-upload-lite'); ?></p>
                        
                        <div class="cpiu-upgrade-status-card">
                            <h3><?php esc_html_e('📊 Current Status:', 'custom-product-image-upload-lite'); ?></h3>
                            <?php 
                            $configured_count = $this->data_manager->get_configured_products_count();
                            ?>
                            <p class="cpiu-status-text">
                                <?php 
                                echo esc_html(sprintf(
                                    _n(
                                        'You have %d product configuration that will be preserved during upgrade.',
                                        'You have %d product configurations that will be preserved during upgrade.',
                                        $configured_count,
                                        'custom-product-image-upload-lite'
                                    ),
                                    $configured_count
                                ));
                                ?>
                            </p>
                        </div>
                        
                        <div class="cpiu-migration-steps">
                            <h3><?php esc_html_e('🔄 Automatic Migration Process:', 'custom-product-image-upload-lite'); ?></h3>
                            <ol class="cpiu-migration-list">
                                <li><?php esc_html_e('Install Custom Product Image Upload Pro', 'custom-product-image-upload-lite'); ?></li>
                                <li><?php esc_html_e('Click "Activate" - that\'s it!', 'custom-product-image-upload-lite'); ?></li>
                                <li><?php esc_html_e('Pro automatically exports your settings', 'custom-product-image-upload-lite'); ?></li>
                                <li><?php esc_html_e('Pro automatically deactivates Lite', 'custom-product-image-upload-lite'); ?></li>
                                <li><?php esc_html_e('All your data is preserved and ready to use!', 'custom-product-image-upload-lite'); ?></li>
                            </ol>
                        </div>
                        
                        <div class="cpiu-migration-features">
                            <h3><?php esc_html_e('✅ What Gets Transferred:', 'custom-product-image-upload-lite'); ?></h3>
                            <ul class="cpiu-feature-list">
                                <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('All product configurations', 'custom-product-image-upload-lite'); ?></li>
                                <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Default settings', 'custom-product-image-upload-lite'); ?></li>
                                <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Uploaded images (remain in same location)', 'custom-product-image-upload-lite'); ?></li>
                                <li><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Automatic backup created for safety', 'custom-product-image-upload-lite'); ?></li>
                            </ul>
                        </div>
                        
                        <div class="cpiu-upgrade-actions">
                            <a href="https://wpbay.com/product/custom-image-upload-addon-for-woocommerce/" class="button button-primary button-hero" target="_blank" rel="noopener noreferrer">
                                <span class="dashicons dashicons-cart"></span>
                                <?php esc_html_e('Upgrade to Pro Now', 'custom-product-image-upload-lite'); ?>
                            </a>
                            <a href="https://www.nowdigiverse.com/product/image-upload-addon-for-woocommerce/" class="button button-secondary button-hero" target="_blank" rel="noopener noreferrer">
                                <span class="dashicons dashicons-info"></span>
                                <?php esc_html_e('See All Pro Features', 'custom-product-image-upload-lite'); ?>
                            </a>
                        </div>
                        
                        <div class="cpiu-upgrade-note">
                            <p><span class="dashicons dashicons-info"></span> <strong><?php esc_html_e('Note:', 'custom-product-image-upload-lite'); ?></strong> <?php esc_html_e('You can upgrade at any time. Your data will always be preserved automatically.', 'custom-product-image-upload-lite'); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Uninstall Preferences Tab -->
                <div id="uninstall-preferences" class="cpiu-tab-pane <?php echo esc_attr($current_tab === 'uninstall-preferences' ? 'active' : ''); ?>">
                    <div class="cpiu-section">
                        <h2><?php esc_html_e('Uninstall Preferences', 'custom-product-image-upload'); ?></h2>
                        <p><?php esc_html_e('Configure what happens to your data when the plugin is uninstalled.', 'custom-product-image-upload'); ?></p>
                        
                        <?php
                        // Get the uninstall preference, with null as default to detect if it's been set
                        $keep_data_option = get_option('cpiu_keep_data_on_uninstall', null);
                        
                        // If no preference has been set, default to 'keep' for safety (recommended option)
                        if ($keep_data_option === null) {
                            $keep_data = true; // Default to keep data (recommended)
                        } else {
                            $keep_data = (bool) $keep_data_option;
                        }
                        ?>
                        
                        <div class="cpiu-uninstall-warning">
                            <h3 class="cpiu-warning-title">
                                <span class="dashicons dashicons-warning cpiu-warning-icon"></span>
                                <?php esc_html_e('Important: Data Protection Notice', 'custom-product-image-upload'); ?>
                            </h3>
                            <p class="cpiu-warning-text"><?php esc_html_e('This plugin follows WordPress and Envato marketplace standards for data handling:', 'custom-product-image-upload'); ?></p>
                            <ul class="cpiu-warning-list">
                                <li><strong><?php esc_html_e('Deactivation:', 'custom-product-image-upload'); ?></strong> <?php esc_html_e('No data is deleted when you deactivate the plugin. All settings and uploaded images are preserved.', 'custom-product-image-upload'); ?></li>
                                <li><strong><?php esc_html_e('Uninstallation:', 'custom-product-image-upload'); ?></strong> <?php esc_html_e('You can choose whether to keep or delete your data during uninstallation.', 'custom-product-image-upload'); ?></li>
                            </ul>
                        </div>
                        
                        <div class="cpiu-uninstall-options">
                            <h3><?php esc_html_e('Uninstall Data Preference', 'custom-product-image-upload'); ?></h3>
                            <p><?php esc_html_e('Choose what should happen to your plugin data when you uninstall the plugin:', 'custom-product-image-upload'); ?></p>
                            
                            <div class="cpiu-preference-options">
                                <label class="cpiu-preference-option">
                                    <input type="radio" name="cpiu_uninstall_preference" value="keep" <?php checked($keep_data, true); ?> class="cpiu-radio-input">
                                    <strong><?php esc_html_e('Keep all data (Recommended)', 'custom-product-image-upload'); ?></strong>
                                    <p class="cpiu-preference-description">
                                        <?php esc_html_e('Preserve all settings, configurations, and uploaded images. You can reinstall the plugin later without losing any data.', 'custom-product-image-upload'); ?>
                                    </p>
                                </label>
                                
                                <label class="cpiu-preference-option">
                                    <input type="radio" name="cpiu_uninstall_preference" value="delete" <?php checked($keep_data, false); ?> class="cpiu-radio-input">
                                    <strong><?php esc_html_e('Delete all data', 'custom-product-image-upload'); ?></strong>
                                    <p class="cpiu-preference-description">
                                        <?php esc_html_e('Remove all plugin settings and configurations. Note: Uploaded images referenced in orders will be preserved for data integrity.', 'custom-product-image-upload'); ?>
                                    </p>
                                </label>
                            </div>
                            
                            <div class="cpiu-save-preference">
                                <button type="button" id="cpiu-save-uninstall-preference" class="button button-primary">
                                    <span class="dashicons dashicons-saved cpiu-icon-spacing"></span>
                                    <?php esc_html_e('Save Preference', 'custom-product-image-upload'); ?>
                                </button>
                            </div>
                        </div>
                        
                        <div class="cpiu-export-before-uninstall">
                            <h3><?php esc_html_e('Export Settings Before Uninstall', 'custom-product-image-upload'); ?></h3>
                            <p><?php esc_html_e('Before uninstalling, you can export your settings to a backup file. This allows you to restore your configuration if you reinstall the plugin later.', 'custom-product-image-upload'); ?></p>
                            
                            <div class="cpiu-export-action">
                                <button type="button" id="cpiu-export-for-uninstall" class="button button-secondary">
                                    <span class="dashicons dashicons-download cpiu-icon-spacing"></span>
                                    <?php esc_html_e('Export Settings Backup', 'custom-product-image-upload'); ?>
                                </button>
                            </div>
                            
                            <div class="cpiu-export-info">
                                <h4 class="cpiu-export-info-title"><?php esc_html_e('What gets exported:', 'custom-product-image-upload'); ?></h4>
                                <ul class="cpiu-export-list">
                                    <li><?php esc_html_e('All plugin settings and configurations', 'custom-product-image-upload'); ?></li>
                                    <li><?php esc_html_e('Product-specific image upload configurations', 'custom-product-image-upload'); ?></li>
                                    <li><?php esc_html_e('Default settings and preferences', 'custom-product-image-upload'); ?></li>
                                    <li><?php esc_html_e('Export timestamp and plugin version', 'custom-product-image-upload'); ?></li>
                                </ul>
                                <p class="cpiu-export-note"><?php esc_html_e('Note: Uploaded images are not included in the export. Only configuration settings are backed up.', 'custom-product-image-upload'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
        <?php
    }
    
    /**
     * Render configurations table
     */
    private function render_configurations_table($configurations) {
        if (empty($configurations)) {
            echo '<p>' . esc_html__('No product configurations found.', 'custom-product-image-upload') . '</p>';
            return;
        }
        ?>
        <div class="cpiu-bulk-actions">
            <button type="button" id="cpiu-select-all" class="button"><?php esc_html_e('Select All', 'custom-product-image-upload'); ?></button>
            <button type="button" id="cpiu-deselect-all" class="button"><?php esc_html_e('Deselect All', 'custom-product-image-upload'); ?></button>
            <button type="button" id="cpiu-bulk-delete" class="button button-link-delete"><?php esc_html_e('Delete Selected', 'custom-product-image-upload'); ?></button>
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <td class="manage-column column-cb check-column">
                        <input type="checkbox" id="cpiu-select-all-checkbox">
                    </td>
                    <th scope="col" class="manage-column"><?php esc_html_e('Product ID', 'custom-product-image-upload'); ?></th>
                    <th scope="col" class="manage-column"><?php esc_html_e('Product', 'custom-product-image-upload'); ?></th>
                    <th scope="col" class="manage-column"><?php esc_html_e('Images Required', 'custom-product-image-upload'); ?></th>
                    <th scope="col" class="manage-column"><?php esc_html_e('Max File Size', 'custom-product-image-upload'); ?></th>
                    <th scope="col" class="manage-column"><?php esc_html_e('Allowed Types', 'custom-product-image-upload'); ?></th>
                    <th scope="col" class="manage-column"><?php esc_html_e('Status', 'custom-product-image-upload'); ?></th>
                    <th scope="col" class="manage-column"><?php esc_html_e('Actions', 'custom-product-image-upload'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($configurations as $product_id => $config): ?>
                    <?php
                    $product = wc_get_product($product_id);
                    $product_name = $product ? $product->get_name() : sprintf(esc_html__('Product #%d (deleted)', 'custom-product-image-upload'), $product_id);
                    $product_exists = $product !== false;
                    ?>
                    <tr data-product-id="<?php echo esc_attr($product_id); ?>">
                        <th scope="row" class="check-column">
                            <input type="checkbox" name="selected_products[]" value="<?php echo esc_attr($product_id); ?>">
                        </th>
                        <td>
                            <strong><?php echo esc_html($product_id); ?></strong>
                        </td>
                        <td>
                            <strong><?php echo esc_html($product_name); ?></strong>
                            <?php if (!$product_exists): ?>
                                <span class="cpiu-deleted-product"><?php esc_html_e('(Product deleted)', 'custom-product-image-upload'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($config['image_count']); ?></td>
                        <td><?php echo esc_html(size_format($config['max_file_size'])); ?></td>
                        <td><?php echo esc_html(implode(', ', array_map('strtoupper', $config['allowed_types']))); ?></td>
                        <td>
                            <?php if ($config['enabled']): ?>
                                <span class="cpiu-status-enabled"><?php esc_html_e('Enabled', 'custom-product-image-upload'); ?></span>
                            <?php else: ?>
                                <span class="cpiu-status-disabled"><?php esc_html_e('Disabled', 'custom-product-image-upload'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button type="button" class="button cpiu-edit-config<?php echo $this->data_manager->is_lite_version() ? ' cpiu-lite-disabled-btn' : ''; ?>" data-product-id="<?php echo esc_attr($product_id); ?>">
                                <?php esc_html_e('Edit', 'custom-product-image-upload'); ?>
                                <?php if ($this->data_manager->is_lite_version()): ?>
                                    <span class="cpiu-pro-badge"><?php esc_html_e('PRO', 'custom-product-image-upload-lite'); ?></span>
                                <?php endif; ?>
                            </button>
                            <button type="button" class="button button-link-delete cpiu-delete-config" data-product-id="<?php echo esc_attr($product_id); ?>">
                                <?php esc_html_e('Delete', 'custom-product-image-upload'); ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Edit Configuration Modal -->
        <div id="cpiu-edit-modal" class="cpiu-modal cpiu-modal-hidden<?php echo $this->data_manager->is_lite_version() ? ' cpiu-lite-modal' : ''; ?>">
            <?php if ($this->data_manager->is_lite_version()): ?>
            <!-- Lite Version: Overlay on Modal -->
            <div class="cpiu-modal-upgrade-overlay">
                <div class="cpiu-upgrade-card cpiu-modal-upgrade-card">
                    <span class="cpiu-lock-icon">🔒</span>
                    <h3><?php esc_html_e('✏️ Edit Configuration - Pro Feature', 'custom-product-image-upload-lite'); ?></h3>
                    <p><?php esc_html_e('Editing existing configurations is a Pro feature. Upgrade to unlock full control!', 'custom-product-image-upload-lite'); ?></p>
                    
                    <ul>
                        <li><?php esc_html_e('Edit unlimited product configurations', 'custom-product-image-upload-lite'); ?></li>
                        <li><?php esc_html_e('Update settings anytime', 'custom-product-image-upload-lite'); ?></li>
                        <li><?php esc_html_e('Advanced configuration options', 'custom-product-image-upload-lite'); ?></li>
                        <li><?php esc_html_e('Priority support included', 'custom-product-image-upload-lite'); ?></li>
                    </ul>
                    
                    <div class="cpiu-upgrade-buttons">
                        <a href="https://wpbay.com/product/custom-image-upload-addon-for-woocommerce/" class="cpiu-upgrade-btn" target="_blank" rel="noopener noreferrer">
                            <span class="dashicons dashicons-cart"></span>
                            <?php esc_html_e('Upgrade to Pro Now', 'custom-product-image-upload-lite'); ?>
                        </a>
                        <button type="button" class="cpiu-upgrade-btn secondary cpiu-modal-close-alt">
                            <span class="dashicons dashicons-no-alt"></span>
                            <?php esc_html_e('Close', 'custom-product-image-upload-lite'); ?>
                        </button>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="cpiu-modal-content<?php echo $this->data_manager->is_lite_version() ? ' cpiu-modal-content-faded' : ''; ?>">
                <div class="cpiu-modal-header">
                    <h3><?php esc_html_e('Edit Configuration', 'custom-product-image-upload'); ?></h3>
                    <button type="button" class="cpiu-modal-close">&times;</button>
                </div>
                <div class="cpiu-modal-body">
                    <form id="cpiu-edit-config-form" class="cpiu-form">
                        <input type="hidden" id="edit_product_id" name="product_id">
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label><?php esc_html_e('Product', 'custom-product-image-upload'); ?></label>
                                </th>
                                <td>
                                    <strong id="edit_product_name"></strong>
                                    <p class="description"><?php esc_html_e('Product ID:', 'custom-product-image-upload'); ?> <span id="edit_product_id_display"></span></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="edit_image_count"><?php esc_html_e('Required Images', 'custom-product-image-upload'); ?></label>
                                </th>
                                <td>
                                    <input type="number" id="edit_image_count" name="image_count" min="1" max="50" class="regular-text" required>
                                    <p class="description"><?php esc_html_e('Number of images required for this product.', 'custom-product-image-upload'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="edit_max_file_size"><?php esc_html_e('Max File Size (MB)', 'custom-product-image-upload'); ?></label>
                                </th>
                                <td>
                                    <input type="number" id="edit_max_file_size" name="max_file_size" min="1" step="0.1" class="regular-text" required>
                                    <p class="description"><?php esc_html_e('Maximum file size in megabytes.', 'custom-product-image-upload'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="edit_button_text"><?php esc_html_e('Button Text', 'custom-product-image-upload'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="edit_button_text" name="button_text" class="regular-text">
                                    <p class="description"><?php esc_html_e('Text for the upload button (leave empty to use default).', 'custom-product-image-upload'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="edit_button_color"><?php esc_html_e('Button Color', 'custom-product-image-upload'); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="edit_button_color" name="button_color" class="cpiu-color-field">
                                    <p class="description"><?php esc_html_e('Color for progress bars and buttons (leave empty to use default).', 'custom-product-image-upload'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label><?php esc_html_e('Allowed File Types', 'custom-product-image-upload'); ?></label>
                                </th>
                                <td>
                                    <div id="edit_allowed_types">
                                        <?php
                                        $allowed_types = array('jpg', 'jpeg', 'png', 'gif', 'webp');
                                        foreach ($allowed_types as $type) {
                                            ?>
                                            <label class="cpiu-admin-checkbox-label">
                                                <input type="checkbox" name="allowed_types[]" value="<?php echo esc_attr($type); ?>" class="cpiu-admin-checkbox">
                                                <?php echo esc_html(strtoupper($type)); ?>
                                            </label>
                                            <?php
                                        }
                                        ?>
                                    </div>
                                    <p class="description"><?php esc_html_e('Allowed file types for this product.', 'custom-product-image-upload'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label><?php esc_html_e('Resolution Validation', 'custom-product-image-upload'); ?></label>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox" id="edit_resolution_validation" name="resolution_validation" value="1">
                                        <?php esc_html_e('Enable resolution validation for uploaded images', 'custom-product-image-upload'); ?>
                                    </label>
                                    <p class="description"><?php esc_html_e('When enabled, uploaded images must meet minimum and maximum dimension requirements.', 'custom-product-image-upload'); ?></p>
                                </td>
                            </tr>
                            <tr class="edit-resolution-settings cpiu-conditional-row" data-condition="edit-resolution">
                                <th scope="row">
                                    <label><?php esc_html_e('Minimum Dimensions', 'custom-product-image-upload'); ?></label>
                                </th>
                                <td>
                                    <label class="cpiu-dimension-label">
                                        <?php esc_html_e('Width:', 'custom-product-image-upload'); ?>
                                        <input type="number" id="edit_min_width" name="min_width" min="0" max="10000" class="cpiu-dimension-input">
                                        px
                                    </label>
                                    <label class="cpiu-dimension-label cpiu-dimension-label-spaced">
                                        <?php esc_html_e('Height:', 'custom-product-image-upload'); ?>
                                        <input type="number" id="edit_min_height" name="min_height" min="0" max="10000" class="cpiu-dimension-input">
                                        px
                                    </label>
                                    <p class="description"><?php esc_html_e('Minimum required dimensions (0 = no minimum).', 'custom-product-image-upload'); ?></p>
                                </td>
                            </tr>
                            <tr class="edit-resolution-settings cpiu-conditional-row" data-condition="edit-resolution">
                                <th scope="row">
                                    <label><?php esc_html_e('Maximum Dimensions', 'custom-product-image-upload'); ?></label>
                                </th>
                                <td>
                                    <label class="cpiu-dimension-label">
                                        <?php esc_html_e('Width:', 'custom-product-image-upload'); ?>
                                        <input type="number" id="edit_max_width" name="max_width" min="0" max="10000" class="cpiu-dimension-input">
                                        px
                                    </label>
                                    <label class="cpiu-dimension-label cpiu-dimension-label-spaced">
                                        <?php esc_html_e('Height:', 'custom-product-image-upload'); ?>
                                        <input type="number" id="edit_max_height" name="max_height" min="0" max="10000" class="cpiu-dimension-input">
                                        px
                                    </label>
                                    <p class="description"><?php esc_html_e('Maximum allowed dimensions (0 = no maximum).', 'custom-product-image-upload'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="edit_enabled"><?php esc_html_e('Status', 'custom-product-image-upload'); ?></label>
                                </th>
                                <td>
                                    <label>
                                        <input type="checkbox" id="edit_enabled" name="enabled" value="1">
                                        <?php esc_html_e('Enable image upload for this product', 'custom-product-image-upload'); ?>
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </form>
                </div>
                <div class="cpiu-modal-footer">
                    <button type="button" id="cpiu-save-edit" class="button button-primary"><?php esc_html_e('Save Changes', 'custom-product-image-upload'); ?></button>
                    <button type="button" id="cpiu-cancel-edit" class="button"><?php esc_html_e('Cancel', 'custom-product-image-upload'); ?></button>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX handler for getting configurations
     */
    public function get_configurations_ajax() {
        if (!wp_verify_nonce($_POST['nonce'], 'cpiu_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'custom-product-image-upload')));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'custom-product-image-upload')));
        }
        
        $configurations = $this->data_manager->get_all_configurations();
        wp_send_json_success(array('configurations' => $configurations));
    }
    
    /**
     * AJAX handler for getting a single configuration
     */
    public function get_configuration_ajax() {
        if (!wp_verify_nonce($_POST['nonce'], 'cpiu_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'custom-product-image-upload')));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'custom-product-image-upload')));
        }
        
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        
        if (!$product_id) {
            wp_send_json_error(array('message' => __('Invalid product ID.', 'custom-product-image-upload')));
        }
        
        $configuration = $this->data_manager->get_product_configuration($product_id);
        
        if (!$configuration) {
            wp_send_json_error(array('message' => __('Configuration not found.', 'custom-product-image-upload')));
        }
        
        // Get product information
        $product = wc_get_product($product_id);
        $product_name = $product ? $product->get_name() : sprintf(esc_html__('Product #%d (deleted)', 'custom-product-image-upload'), $product_id);
        
        wp_send_json_success(array(
            'configuration' => $configuration,
            'product_name' => $product_name,
            'product_id' => $product_id
        ));
    }
    
    /**
     * AJAX handler for exporting settings
     */
    public function export_settings_ajax() {
        if (!wp_verify_nonce($_POST['nonce'], 'cpiu_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'custom-product-image-upload')));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'custom-product-image-upload')));
        }
        
        // SECURITY: Block in Lite version
        if ($this->data_manager->is_lite_version()) {
            wp_send_json_error(array(
                'message' => esc_html__('Import/Export is not available in the Lite version. Please upgrade to Pro.', 'custom-product-image-upload-lite'),
                'upgrade_required' => true,
                'feature' => 'import_export'
            ));
        }
        
        $export_data = array(
            'plugin_info' => array(
                'name' => 'Custom Product Image Upload',
                'version' => CPIU_VERSION,
                'export_date' => current_time('mysql'),
                'site_url' => get_site_url()
            ),
            'multi_product_configs' => get_option('cpiu_multi_product_configs', array()),
            'default_settings' => get_option('cpiu_default_settings', array()),
            'legacy_settings' => get_option('cpiu_settings', array())
        );
        
        wp_send_json_success(array('export_data' => $export_data));
    }
    
    /**
     * AJAX handler for importing settings
     */
    public function import_settings_ajax() {
        if (!wp_verify_nonce($_POST['nonce'], 'cpiu_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'custom-product-image-upload')));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'custom-product-image-upload')));
        }
        
        // SECURITY: Block in Lite version
        if ($this->data_manager->is_lite_version()) {
            wp_send_json_error(array(
                'message' => esc_html__('Import/Export is not available in the Lite version. Please upgrade to Pro.', 'custom-product-image-upload-lite'),
                'upgrade_required' => true,
                'feature' => 'import_export'
            ));
        }
        
        if (!isset($_POST['import_data']) || empty($_POST['import_data'])) {
            wp_send_json_error(array('message' => __('No import data provided.', 'custom-product-image-upload')));
        }
        
        $import_data = json_decode(stripslashes($_POST['import_data']), true);
        
        if (!$import_data || !is_array($import_data)) {
            wp_send_json_error(array('message' => __('Invalid import data format.', 'custom-product-image-upload')));
        }
        
        $imported_count = 0;
        $errors = array();
        
        // Import multi-product configurations
        if (isset($import_data['multi_product_configs']) && is_array($import_data['multi_product_configs'])) {
            $result = update_option('cpiu_multi_product_configs', $import_data['multi_product_configs']);
            if ($result) {
                $imported_count += count($import_data['multi_product_configs']);
            } else {
                $errors[] = esc_html__('Failed to import multi-product configurations.', 'custom-product-image-upload');
            }
        }
        
        // Import default settings
        if (isset($import_data['default_settings']) && is_array($import_data['default_settings'])) {
            $result = update_option('cpiu_default_settings', $import_data['default_settings']);
            if ($result) {
                $imported_count++;
            } else {
                $errors[] = esc_html__('Failed to import default settings.', 'custom-product-image-upload');
            }
        }
        
        // Import legacy settings
        if (isset($import_data['legacy_settings']) && is_array($import_data['legacy_settings'])) {
            $result = update_option('cpiu_settings', $import_data['legacy_settings']);
            if ($result) {
                $imported_count++;
            } else {
                $errors[] = esc_html__('Failed to import legacy settings.', 'custom-product-image-upload');
            }
        }
        
        if ($imported_count > 0) {
            wp_send_json_success(array(
                'message' => sprintf(esc_html__('Successfully imported %d settings.', 'custom-product-image-upload'), $imported_count),
                'imported_count' => $imported_count,
                'errors' => $errors
            ));
        } else {
            wp_send_json_error(array('message' => __('No settings were imported.', 'custom-product-image-upload')));
        }
    }
    
    /**
     * Render security logs table
     */
    private function render_security_logs_table($logs) {
        if (empty($logs)) {
            echo '<p>' . esc_html__('No security logs found.', 'custom-product-image-upload') . '</p>';
            return;
        }
        
        // Sort logs by timestamp (newest first)
        usort($logs, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col" class="manage-column"><?php esc_html_e('Timestamp', 'custom-product-image-upload'); ?></th>
                    <th scope="col" class="manage-column"><?php esc_html_e('User', 'custom-product-image-upload'); ?></th>
                    <th scope="col" class="manage-column"><?php esc_html_e('IP Address', 'custom-product-image-upload'); ?></th>
                    <th scope="col" class="manage-column"><?php esc_html_e('Product ID', 'custom-product-image-upload'); ?></th>
                    <th scope="col" class="manage-column"><?php esc_html_e('Status', 'custom-product-image-upload'); ?></th>
                    <th scope="col" class="manage-column"><?php esc_html_e('Details', 'custom-product-image-upload'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <?php
                    $user = get_user_by('id', $log['user_id']);
                    $user_name = $user ? $user->display_name : sprintf(esc_html__('User #%d', 'custom-product-image-upload'), $log['user_id']);
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html(date('Y-m-d H:i:s', strtotime($log['timestamp']))); ?></strong>
                        </td>
                        <td>
                            <strong><?php echo esc_html($user_name); ?></strong>
                            <br><small><?php echo esc_html($log['user_id']); ?></small>
                        </td>
                        <td><?php echo esc_html($log['ip_address']); ?></td>
                        <td><?php echo esc_html($log['product_id']); ?></td>
                        <td>
                            <?php if ($log['success']): ?>
                                <span class="cpiu-log-status-success"><?php esc_html_e('SUCCESS', 'custom-product-image-upload'); ?></span>
                            <?php else: ?>
                                <span class="cpiu-log-status-failed"><?php esc_html_e('FAILED', 'custom-product-image-upload'); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty($log['error_message'])): ?>
                                <span class="cpiu-log-error-message"><?php echo esc_html($log['error_message']); ?></span>
                            <?php else: ?>
                                <span class="cpiu-log-success-message"><?php esc_html_e('Upload completed successfully', 'custom-product-image-upload'); ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * AJAX handler for clearing security logs
     */
    public function clear_security_logs_ajax() {
        if (!wp_verify_nonce($_POST['nonce'], 'cpiu_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'custom-product-image-upload')));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'custom-product-image-upload')));
        }
        
        // SECURITY: Block in Lite version
        if ($this->data_manager->is_lite_version()) {
            wp_send_json_error(array(
                'message' => esc_html__('Security Logs are not available in the Lite version. Please upgrade to Pro.', 'custom-product-image-upload-lite'),
                'upgrade_required' => true,
                'feature' => 'security_logs'
            ));
        }
        
        CPIU_Secure_Upload::clear_upload_logs();
        
        wp_send_json_success(array('message' => __('Security logs cleared successfully.', 'custom-product-image-upload')));
    }
    
    /**
     * AJAX handler for refreshing CDN cache
     */
    public function refresh_cdn_cache_ajax() {
        if (!wp_verify_nonce($_POST['nonce'], 'cpiu_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'custom-product-image-upload')));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'custom-product-image-upload')));
        }
        
        // SECURITY: Block in Lite version
        if ($this->data_manager->is_lite_version()) {
            wp_send_json_error(array(
                'message' => esc_html__('CDN Cache is not available in the Lite version. Please upgrade to Pro.', 'custom-product-image-upload-lite'),
                'upgrade_required' => true,
                'feature' => 'cdn_cache'
            ));
        }
        
        $cdn_cache = new CPIU_CDN_Cache();
        $cdn_cache->refresh_all_cdn_cache();
        set_transient('cpiu_cdn_cache_last_refresh', time(), CPIU_CDN_Cache::CACHE_DURATION);
        
        wp_send_json_success(array('message' => __('CDN cache refreshed successfully.', 'custom-product-image-upload')));
    }
    
    /**
     * AJAX handler for clearing CDN cache
     */
    public function clear_cdn_cache_ajax() {
        if (!wp_verify_nonce($_POST['nonce'], 'cpiu_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'custom-product-image-upload')));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'custom-product-image-upload')));
        }
        
        // SECURITY: Block in Lite version
        if ($this->data_manager->is_lite_version()) {
            wp_send_json_error(array(
                'message' => esc_html__('CDN Cache is not available in the Lite version. Please upgrade to Pro.', 'custom-product-image-upload-lite'),
                'upgrade_required' => true,
                'feature' => 'cdn_cache'
            ));
        }
        
        $cdn_cache = new CPIU_CDN_Cache();
        $cdn_cache->clear_cache();
        
        wp_send_json_success(array('message' => __('CDN cache cleared successfully.', 'custom-product-image-upload')));
    }
    
    /**
     * AJAX handler for setting uninstall preference
     */
    public function set_uninstall_preference_ajax() {
        if (!wp_verify_nonce($_POST['nonce'], 'cpiu_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'custom-product-image-upload')));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'custom-product-image-upload')));
        }
        
        $preference = sanitize_text_field($_POST['preference']);
        
        if ($preference === 'keep') {
            update_option('cpiu_keep_data_on_uninstall', true);
            wp_send_json_success(array('message' => __('Data will be preserved during uninstallation.', 'custom-product-image-upload')));
        } elseif ($preference === 'delete') {
            update_option('cpiu_keep_data_on_uninstall', false);
            wp_send_json_success(array('message' => __('Data will be deleted during uninstallation.', 'custom-product-image-upload')));
        } else {
            wp_send_json_error(array('message' => __('Invalid preference.', 'custom-product-image-upload')));
        }
    }
    
    /**
     * AJAX handler for exporting settings before uninstall
     */
    public function export_for_uninstall_ajax() {
        if (!wp_verify_nonce($_POST['nonce'], 'cpiu_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'custom-product-image-upload')));
        }
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions.', 'custom-product-image-upload')));
        }
        
        // Get all plugin settings
        $settings = array(
            'cpiu_settings' => get_option('cpiu_settings', array()),
            'cpiu_default_settings' => get_option('cpiu_default_settings', array()),
            'cpiu_multi_product_configs' => get_option('cpiu_multi_product_configs', array()),
            'export_date' => current_time('mysql'),
            'plugin_version' => CPIU_VERSION
        );
        
        $filename = 'cpiu-settings-backup-' . date('Y-m-d-H-i-s') . '.json';
        
        wp_send_json_success(array(
            'data' => json_encode($settings, JSON_PRETTY_PRINT),
            'filename' => $filename,
            'message' => esc_html__('Settings exported successfully for backup before uninstall.', 'custom-product-image-upload')
        ));
    }
}