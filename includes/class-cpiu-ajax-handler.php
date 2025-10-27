<?php
namespace CPIU\Lite;

/**
 * CPIU AJAX Handler Class
 * 
 * Handles all AJAX operations for multi-product functionality
 * 
 * @package Custom_Product_Image_Upload
 * @since 1.2.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class CPIU_Ajax_Handler {
    
    /**
     * Data manager instance
     */
    private $data_manager;
    
    /**
     * Constructor
     */
    public function __construct() {
        error_log('CPIU AJAX Handler - Constructor called');
        $this->data_manager = new CPIU_Data_Manager();
        $this->init_hooks();
        $this->init_cache_groups();
        error_log('CPIU AJAX Handler - Hooks initialized');
    }
    
    /**
     * Initialize cache groups
     */
    private function init_cache_groups() {
        \wp_cache_add_non_persistent_groups('cpiu_product_search');
    }
    
    /**
     * Initialize AJAX hooks
     */
    private function init_hooks() {
        error_log('CPIU AJAX Handler - Initializing hooks');
        
        // Product search
        \add_action('wp_ajax_cpiu_search_products', array($this, 'search_products'));
        \add_action('wp_ajax_nopriv_cpiu_search_products', array($this, 'search_products'));
        error_log('CPIU AJAX Handler - Search products hooks added');
        
        // Configuration management
        \add_action('wp_ajax_cpiu_save_configuration', array($this, 'save_configuration'));
        \add_action('wp_ajax_cpiu_delete_configuration', array($this, 'delete_configuration'));
        
        // Bulk operations - PRO ONLY
        \add_action('wp_ajax_cpiu_bulk_save_configurations', array($this, 'bulk_save_configurations'));
        \add_action('wp_ajax_cpiu_bulk_delete_configurations', array($this, 'bulk_delete_configurations'));
        
        // Default settings
        \add_action('wp_ajax_cpiu_save_default_settings', array($this, 'save_default_settings'));
        
        // Enhanced image upload (existing functionality updated)
        \add_action('wp_ajax_cpiu_upload_cropped_images', array($this, 'handle_upload_cropped_images'));
        \add_action('wp_ajax_nopriv_cpiu_upload_cropped_images', array($this, 'handle_upload_cropped_images'));
    }
    
    /**
     * Search products for admin interface
     */
    public function search_products() {
        // Verify nonce (accept from POST or GET to match Select2 transport)
        $nonce = isset($_REQUEST['nonce']) ? sanitize_text_field(\wp_unslash($_REQUEST['nonce'])) : '';
        if (empty($nonce) || !\wp_verify_nonce($nonce, 'cpiu_admin_nonce')) {
            \wp_send_json_error(array('message' => esc_html__('Security check failed.', 'custom-product-image-upload')));
        }
        
        // Check permissions
        if (!\current_user_can('manage_options')) {
            \wp_send_json_error(array('message' => esc_html__('Insufficient permissions.', 'custom-product-image-upload')));
        }
        
        // Validate and sanitize search term (accept from POST or GET)
        $raw_search_term = isset($_REQUEST['search_term']) ? $_REQUEST['search_term'] : '';
        $search_term = sanitize_text_field(\wp_unslash($raw_search_term));
        $page = isset($_REQUEST['page']) ? max(1, absint($_REQUEST['page'])) : 1;
        $per_page = 20;
        
        if (empty($search_term)) {
            \wp_send_json_error(array('message' => esc_html__('Search term is required.', 'custom-product-image-upload')));
        }
        
        // Check if search term is a numeric ID
        $is_id_search = is_numeric($search_term);
        
        if ($is_id_search) {
            // Search by ID with caching
            $cache_key = 'cpiu_product_search_id_' . md5($search_term . '_' . $page . '_' . $per_page);
            $cached_result = \wp_cache_get($cache_key, 'cpiu_product_search');
            
            if ($cached_result !== false) {
                \wp_send_json_success($cached_result);
                return;
            }
            
            // Optimized ID search query
            $args = array(
                'post_type'      => 'product',
                'post_status'    => 'publish',
                'posts_per_page' => $per_page,
                'paged'          => $page,
                'p'              => intval($search_term),
                'orderby'        => 'ID',
                'order'          => 'ASC',
                'fields'         => 'ids', // Only get IDs for better performance
                'no_found_rows'  => false, // We need found_posts for pagination
                'update_post_meta_cache' => false, // Don't cache meta
                'update_post_term_cache' => false  // Don't cache terms
            );
        } else {
            // Use WordPress search with caching for better performance
            $cache_key = 'cpiu_product_search_' . md5($search_term . '_' . $page . '_' . $per_page);
            $cached_result = \wp_cache_get($cache_key, 'cpiu_product_search');
            
            if ($cached_result !== false) {
                \wp_send_json_success($cached_result);
                return;
            }
            
            // Optimized search query
            $args = array(
                'post_type'      => 'product',
                'post_status'    => 'publish',
                'posts_per_page' => $per_page,
                'paged'          => $page,
                's'              => $search_term,
                'orderby'        => 'relevance',
                'order'          => 'DESC',
                'fields'         => 'ids', // Only get IDs for better performance
                'no_found_rows'  => false, // We need found_posts for pagination
                'update_post_meta_cache' => false, // Don't cache meta
                'update_post_term_cache' => false  // Don't cache terms
            );
            
            $query = new \WP_Query($args);
            $products = array();
            
            if ($query->have_posts()) {
                foreach ($query->posts as $product_id) {
                    $product = \wc_get_product($product_id);
                    
                    if ($product) {
                        $products[] = array(
                            'id'    => $product_id,
                            'title' => $product->get_name(),
                            'type'  => $product->get_type(),
                            'sku'   => $product->get_sku()
                        );
                    }
                }
            }
            \wp_reset_postdata();
            
            $result = array(
                'products' => $products,
                'total'    => $query->found_posts,
                'pages'    => $query->max_num_pages,
                'current'  => $page
            );
            
            // Cache for 5 minutes
            \wp_cache_set($cache_key, $result, 'cpiu_product_search', 300);
            
            \wp_send_json_success($result);
            return;
        }
        
        $query = new \WP_Query($args);
        $products = array();
        
        if ($query->have_posts()) {
            foreach ($query->posts as $product_id) {
                $product = \wc_get_product($product_id);
                
                if ($product) {
                    $products[] = array(
                        'id'    => $product_id,
                        'title' => $product->get_name(),
                        'type'  => $product->get_type(),
                        'sku'   => $product->get_sku()
                    );
                }
            }
        }
        
        \wp_reset_postdata();
        
        $result = array(
            'products' => $products,
            'total'    => $query->found_posts,
            'pages'    => $query->max_num_pages,
            'current'  => $page
        );
        
        // Cache for 5 minutes
        \wp_cache_set($cache_key, $result, 'cpiu_product_search', 300);
        
        \wp_send_json_success($result);
    }
    
    /**
     * Fuzzy search products using multiple algorithms
     */
    private function fuzzy_search_products($products, $search_term) {
        $search_term = strtolower(trim($search_term));
        $results = array();
        
        foreach ($products as $product) {
            $score = 0;
            $title = strtolower($product['title']);
            $sku = strtolower($product['sku'] ?: '');
            
            // Exact match (highest priority)
            if ($title === $search_term || $sku === $search_term) {
                $score = 100;
            }
            // Starts with search term
            elseif (strpos($title, $search_term) === 0 || strpos($sku, $search_term) === 0) {
                $score = 90;
            }
            // Contains search term
            elseif (strpos($title, $search_term) !== false || strpos($sku, $search_term) !== false) {
                $score = 80;
            }
            // Levenshtein distance for fuzzy matching
            else {
                $title_distance = levenshtein($search_term, $title);
                $sku_distance = $sku ? levenshtein($search_term, $sku) : 999;
                
                // Calculate similarity percentage
                $title_similarity = $this->calculate_similarity($search_term, $title, $title_distance);
                $sku_similarity = $sku ? $this->calculate_similarity($search_term, $sku, $sku_distance) : 0;
                
                $max_similarity = max($title_similarity, $sku_similarity);
                
                // Lower threshold for fuzzy matching (40%)
                if ($max_similarity >= 40) {
                    $score = $max_similarity;
                }
            }
            
            // Add to results if score is above threshold (lowered to 40%)
            if ($score >= 40) {
                $product['fuzzy_score'] = $score;
                $results[] = $product;
            }
        }
        
        // Sort by score (highest first)
        usort($results, function($a, $b) {
            return $b['fuzzy_score'] - $a['fuzzy_score'];
        });
        
        return $results;
    }
    
    /**
     * Calculate similarity percentage based on Levenshtein distance
     */
    private function calculate_similarity($str1, $str2, $distance) {
        $max_length = max(strlen($str1), strlen($str2));
        if ($max_length === 0) return 100;
        
        // Prevent division by zero and handle very short strings
        if ($max_length < 3) {
            return $distance === 0 ? 100 : 0;
        }
        
        $similarity = (1 - ($distance / $max_length)) * 100;
        return max(0, $similarity); // Ensure non-negative
    }
    
    /**
     * Save individual product configuration
     */
    public function save_configuration() {
        // Verify nonce
        if (!\wp_verify_nonce($_POST['nonce'], 'cpiu_admin_nonce')) {
            \wp_send_json_error(array('message' => esc_html__('Security check failed.', 'custom-product-image-upload')));
        }
        
        // Check permissions
        if (!\current_user_can('manage_options')) {
            \wp_send_json_error(array('message' => esc_html__('Insufficient permissions.', 'custom-product-image-upload')));
        }
        
        // Sanitize and validate input data
        $product_id = absint($_POST['product_id']);
        $config_data = array();
        
        // Sanitize configuration data
        if (isset($_POST['config']) && is_array($_POST['config'])) {
            $raw_config = $_POST['config'];
            
            // Sanitize allowed types
            if (isset($raw_config['allowed_types'])) {
                if (is_string($raw_config['allowed_types'])) {
                    $decoded = json_decode(stripslashes($raw_config['allowed_types']), true);
                    if (is_array($decoded)) {
                        $config_data['allowed_types'] = array_map('sanitize_text_field', $decoded);
                    }
                } elseif (is_array($raw_config['allowed_types'])) {
                    $config_data['allowed_types'] = array_map('sanitize_text_field', $raw_config['allowed_types']);
                }
            }
            
            // Sanitize other fields
            $config_data['image_count'] = isset($raw_config['image_count']) ? absint($raw_config['image_count']) : 9;
            $config_data['max_file_size'] = isset($raw_config['max_file_size']) ? absint($raw_config['max_file_size']) : 5242880;
            $config_data['button_text'] = isset($raw_config['button_text']) ? sanitize_text_field($raw_config['button_text']) : '';
            $config_data['button_color'] = isset($raw_config['button_color']) ? sanitize_hex_color($raw_config['button_color']) : '#4CAF50';
            $config_data['enabled'] = isset($raw_config['enabled']) ? (bool) $raw_config['enabled'] : true;
            $config_data['resolution_validation'] = isset($raw_config['resolution_validation']) ? (bool) $raw_config['resolution_validation'] : false;
            $config_data['min_width'] = isset($raw_config['min_width']) ? absint($raw_config['min_width']) : 0;
            $config_data['min_height'] = isset($raw_config['min_height']) ? absint($raw_config['min_height']) : 0;
            $config_data['max_width'] = isset($raw_config['max_width']) ? absint($raw_config['max_width']) : 0;
            $config_data['max_height'] = isset($raw_config['max_height']) ? absint($raw_config['max_height']) : 0;
        }
        
        if ($product_id <= 0) {
            \wp_send_json_error(array('message' => esc_html__('Invalid product ID.', 'custom-product-image-upload')));
        }
        
        // Verify product exists
        $product = \wc_get_product($product_id);
        if (!$product) {
            \wp_send_json_error(array('message' => esc_html__('Product not found.', 'custom-product-image-upload')));
        }
        
        // Sanitize configuration data
        $sanitized_config = $this->data_manager->sanitize_configuration($config_data);
        $sanitized_config['product_id'] = $product_id;
        
        // Save configuration
        $result = $this->data_manager->save_product_configuration($product_id, $sanitized_config);
        
        if ($result) {
            \wp_send_json_success(array(
                'message' => esc_html__('Configuration saved successfully.', 'custom-product-image-upload'),
                'config'  => $this->data_manager->get_product_configuration($product_id)
            ));
        } else {
            \wp_send_json_error(array('message' => esc_html__('Failed to save configuration.', 'custom-product-image-upload')));
        }
    }
    
    /**
     * Delete product configuration
     */
    public function delete_configuration() {
        // Verify nonce
        if (!\wp_verify_nonce($_POST['nonce'], 'cpiu_admin_nonce')) {
            \wp_send_json_error(array('message' => esc_html__('Security check failed.', 'custom-product-image-upload')));
        }
        
        // Check permissions
        if (!\current_user_can('manage_options')) {
            \wp_send_json_error(array('message' => esc_html__('Insufficient permissions.', 'custom-product-image-upload')));
        }
        
        $product_id = absint($_POST['product_id']);
        
        if ($product_id <= 0) {
            \wp_send_json_error(array('message' => esc_html__('Invalid product ID.', 'custom-product-image-upload')));
        }
        
        $result = $this->data_manager->delete_product_configuration($product_id);
        
        if ($result) {
            \wp_send_json_success(array('message' => esc_html__('Configuration deleted successfully.', 'custom-product-image-upload')));
        } else {
            \wp_send_json_error(array('message' => esc_html__('Failed to delete configuration.', 'custom-product-image-upload')));
        }
    }
    
    /**
     * Bulk save configurations
     */
    public function bulk_save_configurations() {
        // Verify nonce
        if (!\wp_verify_nonce($_POST['nonce'], 'cpiu_admin_nonce')) {
            \wp_send_json_error(array('message' => esc_html__('Security check failed.', 'custom-product-image-upload')));
        }
        
        // Check permissions
        if (!\current_user_can('manage_options')) {
            \wp_send_json_error(array('message' => esc_html__('Insufficient permissions.', 'custom-product-image-upload')));
        }
        
        // SECURITY: Validate lite version restrictions at endpoint level
        if ($this->data_manager->is_lite_version()) {
            \wp_send_json_error(array(
                'message' => esc_html__('Bulk operations are not available in the Lite version. Please upgrade to Pro.', 'custom-product-image-upload-lite'),
                'upgrade_required' => true,
                'feature' => 'bulk_operations'
            ));
        }
        
        // Sanitize and validate product IDs
        $product_ids = array();
        $product_ids_raw = isset($_POST['product_ids']) ? $_POST['product_ids'] : array();
        
        // Handle both array format (product_ids[]) and JSON string format
        if (is_string($product_ids_raw)) {
            $decoded_ids = json_decode(stripslashes($product_ids_raw), true);
            if (is_array($decoded_ids)) {
                $product_ids_raw = $decoded_ids;
            }
        }
        
        if (is_array($product_ids_raw)) {
            foreach ($product_ids_raw as $id) {
                $sanitized_id = absint($id);
                if ($sanitized_id > 0) {
                    $product_ids[] = $sanitized_id;
                }
            }
        }
        
        // Sanitize configuration data
        $config_data = array();
        if (isset($_POST['config']) && is_array($_POST['config'])) {
            $raw_config = $_POST['config'];
            
            // Sanitize allowed types
            if (isset($raw_config['allowed_types'])) {
                if (is_string($raw_config['allowed_types'])) {
                    $decoded = json_decode(stripslashes($raw_config['allowed_types']), true);
                    if (is_array($decoded)) {
                        $config_data['allowed_types'] = array_map('sanitize_text_field', $decoded);
                    }
                } elseif (is_array($raw_config['allowed_types'])) {
                    $config_data['allowed_types'] = array_map('sanitize_text_field', $raw_config['allowed_types']);
                }
            }
            
            // Sanitize other fields
            $config_data['image_count'] = isset($raw_config['image_count']) ? absint($raw_config['image_count']) : 9;
            $config_data['max_file_size'] = isset($raw_config['max_file_size']) ? absint($raw_config['max_file_size']) : 5242880;
            $config_data['button_text'] = isset($raw_config['button_text']) ? sanitize_text_field($raw_config['button_text']) : '';
            $config_data['button_color'] = isset($raw_config['button_color']) ? sanitize_hex_color($raw_config['button_color']) : '#4CAF50';
            $config_data['enabled'] = isset($raw_config['enabled']) ? (bool) $raw_config['enabled'] : true;
            $config_data['resolution_validation'] = isset($raw_config['resolution_validation']) ? (bool) $raw_config['resolution_validation'] : false;
            $config_data['min_width'] = isset($raw_config['min_width']) ? absint($raw_config['min_width']) : 0;
            $config_data['min_height'] = isset($raw_config['min_height']) ? absint($raw_config['min_height']) : 0;
            $config_data['max_width'] = isset($raw_config['max_width']) ? absint($raw_config['max_width']) : 0;
            $config_data['max_height'] = isset($raw_config['max_height']) ? absint($raw_config['max_height']) : 0;
        }
        
        if (empty($product_ids)) {
            \wp_send_json_error(array('message' => esc_html__('No products selected.', 'custom-product-image-upload')));
        }
        
        // Sanitize configuration data
        $sanitized_config = $this->data_manager->sanitize_configuration($config_data);
        
        // Save configurations
        $results = $this->data_manager->save_bulk_configurations($product_ids, $sanitized_config);
        // Consider unchanged-but-valid saves as true
        $success_count = 0;
        foreach ($results as $pid => $ok) {
            if ($ok) { $success_count++; }
        }
        $total_count = count($product_ids);
        
        if ($success_count > 0) {
            \wp_send_json_success(array(
                'message' => sprintf(
                    esc_html__('%d of %d configurations saved successfully.', 'custom-product-image-upload'),
                    $success_count,
                    $total_count
                ),
                'results' => $results
            ));
        } else {
            \wp_send_json_error(array('message' => esc_html__('Failed to save any configurations.', 'custom-product-image-upload')));
        }
    }
    
    /**
     * Bulk delete configurations
     */
    public function bulk_delete_configurations() {
        // Verify nonce
        if (!\wp_verify_nonce($_POST['nonce'], 'cpiu_admin_nonce')) {
            \wp_send_json_error(array('message' => esc_html__('Security check failed.', 'custom-product-image-upload')));
        }
        
        // Check permissions
        if (!\current_user_can('manage_options')) {
            \wp_send_json_error(array('message' => esc_html__('Insufficient permissions.', 'custom-product-image-upload')));
        }
        
        // SECURITY: Validate lite version restrictions at endpoint level
        if ($this->data_manager->is_lite_version()) {
            \wp_send_json_error(array(
                'message' => esc_html__('Bulk operations are not available in the Lite version. Please upgrade to Pro.', 'custom-product-image-upload-lite'),
                'upgrade_required' => true,
                'feature' => 'bulk_operations'
            ));
        }
        
        $product_ids_raw = isset($_POST['product_ids']) ? $_POST['product_ids'] : array();
        if (is_string($product_ids_raw)) {
            $decoded_ids = json_decode(stripslashes($product_ids_raw), true);
            $product_ids_raw = is_array($decoded_ids) ? $decoded_ids : array();
        }
        $product_ids = array_map('absint', (array)$product_ids_raw);
        
        if (empty($product_ids)) {
            \wp_send_json_error(array('message' => esc_html__('No products selected.', 'custom-product-image-upload')));
        }
        
        $results = array();
        foreach ($product_ids as $product_id) {
            $results[$product_id] = $this->data_manager->delete_product_configuration($product_id);
        }
        
        $success_count = count(array_filter($results));
        $total_count = count($product_ids);
        
        if ($success_count > 0) {
            \wp_send_json_success(array(
                'message' => sprintf(
                    esc_html__('%d of %d configurations deleted successfully.', 'custom-product-image-upload'),
                    $success_count,
                    $total_count
                ),
                'results' => $results
            ));
        } else {
            \wp_send_json_error(array('message' => esc_html__('Failed to delete any configurations.', 'custom-product-image-upload')));
        }
    }
    
    /**
     * Save default settings
     */
    public function save_default_settings() {
        // Verify nonce
        if (!\wp_verify_nonce($_POST['nonce'], 'cpiu_admin_nonce')) {
            \wp_send_json_error(array('message' => esc_html__('Security check failed.', 'custom-product-image-upload')));
        }
        
        // Check permissions
        if (!\current_user_can('manage_options')) {
            \wp_send_json_error(array('message' => esc_html__('Insufficient permissions.', 'custom-product-image-upload')));
        }
        
        // Sanitize and validate settings data
        $settings_data = isset($_POST['settings']) ? $_POST['settings'] : array();
        
        if (!is_array($settings_data)) {
            \wp_send_json_error(array('message' => esc_html__('Invalid settings data.', 'custom-product-image-upload')));
        }
        
        // Sanitize settings
        $sanitized_settings = array(
            'image_count'   => max(1, min(50, absint($settings_data['image_count']))),
            'max_file_size' => max(1024, absint($settings_data['max_file_size'])),
            'button_text'   => sanitize_text_field($settings_data['button_text']),
            'button_color'  => sanitize_hex_color($settings_data['button_color']),
            'resolution_validation' => !empty($settings_data['resolution_validation']),
            'min_width'    => max(0, min(10000, absint($settings_data['min_width'] ?? 0))),
            'min_height'   => max(0, min(10000, absint($settings_data['min_height'] ?? 0))),
            'max_width'    => max(0, min(10000, absint($settings_data['max_width'] ?? 0))),
            'max_height'   => max(0, min(10000, absint($settings_data['max_height'] ?? 0)))
        );
        
        // Sanitize allowed types
        $sanitized_settings['allowed_types'] = array();
        if (is_array($settings_data['allowed_types'])) {
            $allowed_extensions = array('jpg', 'jpeg', 'png', 'gif', 'webp');
            foreach ($settings_data['allowed_types'] as $type) {
                $type = sanitize_text_field($type);
                if (in_array(strtolower($type), $allowed_extensions)) {
                    $sanitized_settings['allowed_types'][] = strtolower($type);
                }
            }
        }
        
        // Ensure at least one type is allowed
        if (empty($sanitized_settings['allowed_types'])) {
            $sanitized_settings['allowed_types'] = array('jpg', 'jpeg', 'png');
        }
        
        $result = $this->data_manager->save_default_settings($sanitized_settings);
        
        if ($result) {
            \wp_send_json_success(array(
                'message' => esc_html__('Default settings saved successfully.', 'custom-product-image-upload'),
                'settings' => $this->data_manager->get_default_settings()
            ));
        } else {
            \wp_send_json_error(array('message' => esc_html__('Failed to save default settings.', 'custom-product-image-upload')));
        }
    }
    
    /**
     * Enhanced secure image upload handler for multi-product support
     */
    public function handle_upload_cropped_images() {
        // Verify nonce
        if (!isset($_POST['security']) || !\wp_verify_nonce(sanitize_text_field($_POST['security']), 'cpiu_image_upload_nonce')) {
            \wp_send_json_error(array('message' => esc_html__('Security check failed.', 'custom-product-image-upload')));
        }
        
        // Handle both authenticated and guest users
        $is_logged_in = \is_user_logged_in();
        $user_id = $is_logged_in ? \get_current_user_id() : 0;
        $ip_address = $this->get_client_ip_address();
        
        // For guest users, ensure we have a session
        if (!$is_logged_in) {
            if (!session_id()) {
                session_start();
            }
            // Create a guest session identifier if it doesn't exist
            if (!isset($_SESSION['cpiu_guest_id'])) {
                $_SESSION['cpiu_guest_id'] = 'guest_' . \wp_generate_password(12, false);
            }
        }
        
        // Enhanced security validation for guest uploads
        $this->validate_guest_upload_security($ip_address, $user_id, $is_logged_in);
        
        // Sanitize and validate product ID
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        
        if ($product_id <= 0) {
            \wp_send_json_error(array('message' => esc_html__('Invalid product ID.', 'custom-product-image-upload')));
        }
        
        // Verify product exists and is supported type
        $product = \wc_get_product($product_id);
        if (!$product) {
            \wp_send_json_error(array('message' => esc_html__('Product not found.', 'custom-product-image-upload')));
        }
        
        // Check if product type is supported (exclude external/affiliate products)
        if (in_array($product->get_type(), array('external', 'affiliate'))) {
            \wp_send_json_error(array('message' => esc_html__('Image upload is not supported for this product type.', 'custom-product-image-upload')));
        }
        
        // Get product configuration
        $config = $this->data_manager->get_frontend_configuration($product_id);
        
        if (!$config) {
            \wp_send_json_error(array('message' => esc_html__('Product not configured for image upload.', 'custom-product-image-upload')));
        }
        
        // Validate and sanitize image data
        if (!isset($_POST['images_data']) || empty($_POST['images_data'])) {
            \wp_send_json_error(array('message' => esc_html__('No image data received.', 'custom-product-image-upload')));
        }
        
        $raw_images_data = sanitize_textarea_field($_POST['images_data']);
        $images_base64_data = json_decode(stripslashes($raw_images_data), true);
        
        if (!is_array($images_base64_data) || count($images_base64_data) !== $config['image_count']) {
            \wp_send_json_error(array(
                'message' => sprintf(
                    esc_html__('Invalid data or image count. Expected %d images.', 'custom-product-image-upload'),
                    $config['image_count']
                )
            ));
        }
        
        // Initialize secure upload handler
        try {
            $secure_upload = new CPIU_Secure_Upload();
            $secure_upload->set_max_file_size($config['max_file_size']);
            
            // Set resolution validation if enabled
            if (!empty($config['resolution_validation'])) {
                $secure_upload->set_resolution_validation(
                    true,
                    $config['min_width'] ?? 0,
                    $config['min_height'] ?? 0,
                    $config['max_width'] ?? 0,
                    $config['max_height'] ?? 0
                );
            }
        } catch (Exception $e) {
            \wp_send_json_error(array('message' => $e->getMessage()));
        }
        
        $saved_image_urls = array();
        $errors = array();
        $uploaded_files = array();
        
        foreach ($images_base64_data as $index => $base64_image_string) {
            try {
                $upload_result = $secure_upload->process_base64_image(
                    $base64_image_string,
                    $index,
                    $product_id,
                    $user_id,
                    $ip_address
                );
                
                $saved_image_urls[] = $upload_result['url'];
                $uploaded_files[] = $upload_result['path'];
                
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
                
                // Clean up any files uploaded so far
                if (!empty($uploaded_files)) {
                    $secure_upload->cleanup_files($uploaded_files);
                }
                
                \wp_send_json_error(array('message' => implode(' ', $errors)));
            }
        }
        
        if (count($saved_image_urls) !== $config['image_count']) {
            // Clean up any files uploaded
            if (!empty($uploaded_files)) {
                $secure_upload->cleanup_files($uploaded_files);
            }
            \wp_send_json_error(array('message' => esc_html__('Unexpected error: Not all images processed.', 'custom-product-image-upload')));
        }
        
        // Add to cart with guest session tracking
        $cart_item_data = array('cpiu_uploaded_images' => $saved_image_urls);
        
        // For guest users, add session identifier to cart data
        if (!$is_logged_in && isset($_SESSION['cpiu_guest_id'])) {
            $cart_item_data['cpiu_guest_id'] = $_SESSION['cpiu_guest_id'];
        }
        
        // Debug: Log what we're adding to cart
        error_log('CPIU AJAX Debug: Adding to cart - Product ID: ' . $product_id);
        error_log('CPIU AJAX Debug: User ID: ' . $user_id . ' (Guest: ' . (!$is_logged_in ? 'Yes' : 'No') . ')');
        error_log('CPIU AJAX Debug: Cart item data: ' . print_r($cart_item_data, true));
        error_log('CPIU AJAX Debug: Saved image URLs: ' . print_r($saved_image_urls, true));
        
        $cart_item_key = \WC()->cart->add_to_cart($product_id, 1, 0, array(), $cart_item_data);
        
        if ($cart_item_key) {
            error_log('CPIU AJAX Debug: Successfully added to cart with key: ' . $cart_item_key);
            
            // Debug: Check what's actually in the cart
            $cart_items = \WC()->cart->get_cart();
            foreach ($cart_items as $key => $item) {
                if ($key === $cart_item_key) {
                    error_log('CPIU AJAX Debug: Cart item data after adding: ' . print_r($item, true));
                    break;
                }
            }
            
            \wp_send_json_success(array('message' => esc_html__('Product added to cart!', 'custom-product-image-upload')));
        } else {
            error_log('CPIU AJAX Debug: Failed to add product to cart');
            // Clean up files if cart addition fails
            if (!empty($uploaded_files)) {
                $secure_upload->cleanup_files($uploaded_files);
            }
            \wp_send_json_error(array('message' => esc_html__('Failed to add product to cart.', 'custom-product-image-upload')));
        }
    }
    
    /**
     * Enhanced security validation for guest uploads
     */
    private function validate_guest_upload_security($ip_address, $user_id, $is_logged_in) {
        // Rate limiting disabled - no upload limits
        // Only keeping guest behavior validation for security
        if (!$is_logged_in) {
            // Additional validation for suspicious patterns
            $this->validate_guest_behavior($ip_address);
        }
        

    }
    
    /**
     * Validate guest user behavior patterns
     */
    private function validate_guest_behavior($ip_address) {
        // Check for rapid successive requests (potential bot behavior)
        $last_request_key = 'cpiu_last_request_' . md5($ip_address);
        $last_request_time = \get_transient($last_request_key);
        
        if ($last_request_time !== false && (time() - $last_request_time) < 5) {
            \wp_send_json_error(array('message' => esc_html__('Please wait a moment before uploading again.', 'custom-product-image-upload')));
        }
        
        \set_transient($last_request_key, time(), 300); // 5 minutes
        
        // Log guest upload attempts for monitoring
        error_log('CPIU: Guest upload attempt from IP: ' . $ip_address . ' at ' . current_time('mysql'));
    }
    
    /**
     * Get client IP address for logging
     */
    private function get_client_ip_address() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }
}
