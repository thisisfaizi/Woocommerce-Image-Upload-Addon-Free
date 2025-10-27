<?php
namespace CPIU\Lite;

/**
 * CPIU CDN Cache Manager
 * 
 * Handles aggressive caching of third-party CDN resources to prevent throttling
 * 
 * @package Custom_Product_Image_Upload
 * @since 1.2.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class CPIU_CDN_Cache {
    
    /**
     * Cache duration in seconds (24 hours)
     */
    const CACHE_DURATION = 86400;
    
    /**
     * CDN resources to cache
     */
    private $cdn_resources = array(
        'cropperjs' => array(
            'js' => 'https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js',
            'css' => 'https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css',
            'version' => '1.6.1'
        ),
        'select2' => array(
            'js' => 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/js/select2.min.js',
            'css' => 'https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/css/select2.min.css',
            'version' => '4.1.0-rc.0'
        )
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        \add_action('init', array($this, 'init_cdn_cache'));
        \add_action('wp_ajax_cpiu_refresh_cdn_cache', array($this, 'refresh_cdn_cache'));
    }
    
    /**
     * Initialize CDN cache
     */
    public function init_cdn_cache() {
        if (\is_admin() && \current_user_can('manage_options')) {
            \add_action('admin_init', array($this, 'maybe_refresh_cache'));
        }
    }
    
    /**
     * Check if cache needs refresh
     */
    public function maybe_refresh_cache() {
        $last_refresh = \get_transient('cpiu_cdn_cache_last_refresh');
        
        if (!$last_refresh || $last_refresh < (time() - self::CACHE_DURATION)) {
            $this->refresh_all_cdn_cache();
            \set_transient('cpiu_cdn_cache_last_refresh', time(), self::CACHE_DURATION);
        }
    }
    
    /**
     * Refresh all CDN cache
     */
    public function refresh_all_cdn_cache() {
        foreach ($this->cdn_resources as $resource_name => $resource_data) {
            $this->cache_cdn_resource($resource_name, $resource_data);
        }
        
        // Also update existing cache entries to use HTTPS URLs if needed
        $this->update_existing_cache_urls();
    }
    
    /**
     * Update existing cache URLs to use HTTPS if site is served over HTTPS
     */
    private function update_existing_cache_urls() {
        if (!\is_ssl()) {
            return; // No need to update if not using HTTPS
        }
        
        foreach ($this->cdn_resources as $resource_name => $resource_data) {
            // Update JS cache URL
            $js_cache = \get_option('cpiu_cdn_cache_' . $resource_name . '_js');
            if ($js_cache && isset($js_cache['url']) && strpos($js_cache['url'], 'http://') === 0) {
                $js_cache['url'] = str_replace('http://', 'https://', $js_cache['url']);
                \update_option('cpiu_cdn_cache_' . $resource_name . '_js', $js_cache);
            }
            
            // Update CSS cache URL
            $css_cache = \get_option('cpiu_cdn_cache_' . $resource_name . '_css');
            if ($css_cache && isset($css_cache['url']) && strpos($css_cache['url'], 'http://') === 0) {
                $css_cache['url'] = str_replace('http://', 'https://', $css_cache['url']);
                \update_option('cpiu_cdn_cache_' . $resource_name . '_css', $css_cache);
            }
        }
    }
    
    /**
     * Cache a specific CDN resource
     */
    private function cache_cdn_resource($resource_name, $resource_data) {
        $upload_dir = wp_upload_dir();
        $cache_dir = $upload_dir['basedir'] . '/cpiu-cdn-cache/';
        
        // Create cache directory if it doesn't exist
        if (!file_exists($cache_dir)) {
            \wp_mkdir_p($cache_dir);
            
            // Add .htaccess for security
            $htaccess_content = "Options -Indexes\n";
            $htaccess_content .= "<Files ~ \"\\.(js|css)$\">\n";
            $htaccess_content .= "    Header set Cache-Control \"max-age=86400, public\"\n";
            $htaccess_content .= "</Files>\n";
            \file_put_contents($cache_dir . '.htaccess', $htaccess_content);
        }
        
        // Cache JS file
        if (isset($resource_data['js'])) {
            $js_url = $resource_data['js'];
            $js_content = $this->fetch_remote_content($js_url);
            
            if ($js_content) {
                $js_filename = $resource_name . '-' . $resource_data['version'] . '.min.js';
                file_put_contents($cache_dir . $js_filename, $js_content);
                
                // Store cache info with HTTPS-aware URL
                $cache_url = $upload_dir['baseurl'] . '/cpiu-cdn-cache/' . $js_filename;
                // Ensure HTTPS if the site is served over HTTPS
                if (is_ssl()) {
                    $cache_url = str_replace('http://', 'https://', $cache_url);
                }
                
                update_option('cpiu_cdn_cache_' . $resource_name . '_js', array(
                    'url' => $cache_url,
                    'cached_at' => time(),
                    'version' => $resource_data['version']
                ));
            }
        }
        
        // Cache CSS file
        if (isset($resource_data['css'])) {
            $css_url = $resource_data['css'];
            $css_content = $this->fetch_remote_content($css_url);
            
            if ($css_content) {
                $css_filename = $resource_name . '-' . $resource_data['version'] . '.min.css';
                file_put_contents($cache_dir . $css_filename, $css_content);
                
                // Store cache info with HTTPS-aware URL
                $cache_url = $upload_dir['baseurl'] . '/cpiu-cdn-cache/' . $css_filename;
                // Ensure HTTPS if the site is served over HTTPS
                if (is_ssl()) {
                    $cache_url = str_replace('http://', 'https://', $cache_url);
                }
                
                update_option('cpiu_cdn_cache_' . $resource_name . '_css', array(
                    'url' => $cache_url,
                    'cached_at' => time(),
                    'version' => $resource_data['version']
                ));
            }
        }
    }
    
    /**
     * Fetch remote content with error handling
     */
    private function fetch_remote_content($url) {
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'headers' => array(
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url')
            )
        ));
        
        if (is_wp_error($response)) {
            error_log('CPIU CDN Cache: Failed to fetch ' . $url . ' - ' . $response->get_error_message());
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            error_log('CPIU CDN Cache: HTTP ' . $response_code . ' for ' . $url);
            return false;
        }
        
        return wp_remote_retrieve_body($response);
    }
    
    /**
     * Get cached CDN URL
     */
    public function get_cached_url($resource_name, $type = 'js') {
        $cache_info = get_option('cpiu_cdn_cache_' . $resource_name . '_' . $type);
        
        if ($cache_info && isset($cache_info['url'])) {
            // Check if cache is still valid
            if (isset($cache_info['cached_at']) && 
                $cache_info['cached_at'] > (time() - self::CACHE_DURATION)) {
                return $cache_info['url'];
            }
        }
        
        // Fallback to original CDN URL
        if (isset($this->cdn_resources[$resource_name][$type])) {
            return $this->cdn_resources[$resource_name][$type];
        }
        
        return false;
    }
    
    /**
     * Manual cache refresh via AJAX
     */
    public function refresh_cdn_cache() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'cpiu_admin_nonce')) {
            wp_die('Security check failed');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $this->refresh_all_cdn_cache();
        
        wp_send_json_success(array(
            'message' => esc_html__('CDN cache refreshed successfully', 'custom-product-image-upload'),
            'timestamp' => current_time('mysql')
        ));
    }
    
    /**
     * Get cache status for admin display
     */
    public function get_cache_status() {
        $status = array();
        
        foreach ($this->cdn_resources as $resource_name => $resource_data) {
            $js_cache = get_option('cpiu_cdn_cache_' . $resource_name . '_js');
            $css_cache = get_option('cpiu_cdn_cache_' . $resource_name . '_css');
            
            $status[$resource_name] = array(
                'js' => $js_cache ? $js_cache['cached_at'] : false,
                'css' => $css_cache ? $css_cache['cached_at'] : false,
                'version' => $resource_data['version']
            );
        }
        
        return $status;
    }
    
    /**
     * Clear all cache files
     */
    public function clear_cache() {
        $upload_dir = wp_upload_dir();
        $cache_dir = $upload_dir['basedir'] . '/cpiu-cdn-cache/';
        
        if (file_exists($cache_dir)) {
            $files = glob($cache_dir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
        
        // Clear cache options
        foreach ($this->cdn_resources as $resource_name => $resource_data) {
            delete_option('cpiu_cdn_cache_' . $resource_name . '_js');
            delete_option('cpiu_cdn_cache_' . $resource_name . '_css');
        }
        
        delete_transient('cpiu_cdn_cache_last_refresh');
    }
}
