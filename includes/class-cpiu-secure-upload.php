<?php
namespace CPIU\Lite;

/**
 * CPIU Secure Upload Class
 * 
 * Handles secure file uploads with comprehensive security measures
 * 
 * @package Custom_Product_Image_Upload
 * @since 1.2.0
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

class CPIU_Secure_Upload {
    
    /**
     * Allowed MIME types for images
     */
    private $allowed_mime_types;
    
    /**
     * Allowed file extensions
     */
    private $allowed_extensions = array(
        'jpg',
        'jpeg',
        'png',
        'gif',
        'webp'
    );
    
    /**
     * Maximum file size (will be set dynamically)
     */
    private $max_file_size = 10485760; // 10MB default
    
    /**
     * Resolution validation settings
     */
    private $resolution_validation = false;
    private $min_width = 0;
    private $min_height = 0;
    private $max_width = 0;
    private $max_height = 0;
    
    /**
     * Upload directory path
     */
    private $upload_dir;
    
    /**
     * Upload directory URL
     */
    private $upload_url;
    
    /**
     * Upload logs option name
     */
    const UPLOAD_LOGS_OPTION = 'cpiu_upload_logs';
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_allowed_types();
        $this->init_upload_directory();
    }
    
    /**
     * Initialize allowed file types with filters
     */
    private function init_allowed_types() {
        $default_mime_types = array(
            'image/jpeg',
            'image/jpg', 
            'image/png',
            'image/gif',
            'image/webp'
        );
        
        $default_extensions = array(
            'jpg',
            'jpeg',
            'png',
            'gif',
            'webp'
        );
        
        // Allow filtering of allowed file types
        $this->allowed_mime_types = \apply_filters('cpiu_allowed_mime_types', $default_mime_types);
        $this->allowed_extensions = \apply_filters('cpiu_allowed_extensions', $default_extensions);
    }
    
    /**
     * Initialize upload directory
     */
    private function init_upload_directory() {
        $upload_dir_info = \wp_upload_dir();
        $this->upload_dir = $upload_dir_info['basedir'] . '/' . CPIU_UPLOAD_DIR_NAME;
        $this->upload_url = $upload_dir_info['baseurl'] . '/' . CPIU_UPLOAD_DIR_NAME;
        
        // Ensure directory exists and is secure
        $this->ensure_secure_directory();
    }
    
    /**
     * Ensure upload directory exists and is secure
     */
    private function ensure_secure_directory() {
        if (!file_exists($this->upload_dir)) {
            if (!\wp_mkdir_p($this->upload_dir)) {
                throw new \Exception(\esc_html__('Could not create secure upload directory.', 'custom-product-image-upload'));
            }
        }
        
        // Create security files
        $this->create_security_files();
        
        // Set directory permissions (non-executable)
        if (is_dir($this->upload_dir)) {
            chmod($this->upload_dir, 0755); // Read/write for owner, read for others
        }
    }
    
    /**
     * Create security files to prevent execution
     */
    private function create_security_files() {
        $index_file = $this->upload_dir . '/index.php';
        $htaccess_file = $this->upload_dir . '/.htaccess';
        
        // Create index.php to prevent directory listing
        if (!file_exists($index_file)) {
            file_put_contents($index_file, '<?php // Silence is golden.');
        }
        
        // Create .htaccess to prevent execution and directory listing
        if (!file_exists($htaccess_file)) {
            $htaccess_content = "# Prevent execution of uploaded files\n";
            $htaccess_content .= "Options -Indexes\n";
            $htaccess_content .= "Options -ExecCGI\n";
            $htaccess_content .= "AddHandler cgi-script .php .pl .py .jsp .asp .sh .cgi\n";
            $htaccess_content .= "<FilesMatch \"\\.(php|php3|php4|php5|phtml|pl|py|jsp|asp|sh|cgi)$\">\n";
            $htaccess_content .= "    Order Deny,Allow\n";
            $htaccess_content .= "    Deny from all\n";
            $htaccess_content .= "</FilesMatch>\n";
            $htaccess_content .= "# Allow only image files\n";
            $htaccess_content .= "<FilesMatch \"\\.(jpg|jpeg|png|gif|webp)$\">\n";
            $htaccess_content .= "    Order Allow,Deny\n";
            $htaccess_content .= "    Allow from all\n";
            $htaccess_content .= "</FilesMatch>\n";
            
            file_put_contents($htaccess_file, $htaccess_content);
        }
    }
    
    /**
     * Set maximum file size
     */
    public function set_max_file_size($size_in_bytes) {
        $this->max_file_size = \absint($size_in_bytes);
        if ($this->max_file_size < 1024) {
            $this->max_file_size = 1024; // Minimum 1KB
        }
    }
    
    /**
     * Set resolution validation settings
     */
    public function set_resolution_validation($enabled, $min_width = 0, $min_height = 0, $max_width = 0, $max_height = 0) {
        $this->resolution_validation = (bool) $enabled;
        $this->min_width = max(0, min(10000, \absint($min_width)));
        $this->min_height = max(0, min(10000, \absint($min_height)));
        $this->max_width = max(0, min(10000, \absint($max_width)));
        $this->max_height = max(0, min(10000, \absint($max_height)));
    }
    
    /**
     * Process base64 image data securely
     */
    public function process_base64_image($base64_data, $index, $product_id, $user_id, $ip_address) {
        // Log upload attempt
        $this->log_upload_attempt($user_id, $ip_address, $product_id, 'base64_upload', true);
        
        try {
            // Validate base64 format
            if (empty($base64_data) || strpos($base64_data, 'data:image/') !== 0) {
                throw new Exception(sprintf(esc_html__('Invalid image format for image %d.', 'custom-product-image-upload'), $index + 1));
            }
            
            // Extract image data
            if (!preg_match('/^data:image\/(png|jpe?g|gif|webp);base64,(.*)$/i', $base64_data, $matches) || count($matches) !== 3) {
                throw new Exception(sprintf(esc_html__('Unsupported image type for image %d.', 'custom-product-image-upload'), $index + 1));
            }
            
            $image_type = strtolower($matches[1]);
            $image_data = base64_decode($matches[2]);
            
            if ($image_data === false) {
                throw new Exception(sprintf(esc_html__('Failed to decode image %d.', 'custom-product-image-upload'), $index + 1));
            }
            
            // Create temporary file for validation
            $temp_file = $this->create_temp_file($image_data, $image_type);
            
            try {
                // Validate file security
                $this->validate_file_security($temp_file, $image_type);
                
                // Generate secure filename
                $secure_filename = $this->generate_secure_filename($product_id, $index, $image_type);
                $file_path = $this->upload_dir . '/' . $secure_filename;
                
                // Move file to final location
                if (!file_put_contents($file_path, $image_data)) {
                    throw new Exception(sprintf(esc_html__('Failed to save image %d.', 'custom-product-image-upload'), $index + 1));
                }
                
                // Set file permissions (non-executable)
                chmod($file_path, 0644);
                
                // Log successful upload
                $this->log_upload_attempt($user_id, $ip_address, $product_id, 'success', true, $secure_filename);
                
                $upload_result = array(
                    'path' => $file_path,
                    'url' => $this->upload_url . '/' . $secure_filename,
                    'filename' => $secure_filename
                );
                
                // Allow other plugins to hook into successful uploads
                do_action('cpiu_file_uploaded', $upload_result, $product_id, $user_id);
                
                return $upload_result;
                
            } finally {
                // Clean up temp file
                if (file_exists($temp_file)) {
                    unlink($temp_file);
                }
            }
            
        } catch (Exception $e) {
            // Log failed upload
            $this->log_upload_attempt($user_id, $ip_address, $product_id, 'failed', false, '', $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Create temporary file for validation
     */
    private function create_temp_file($data, $extension) {
        $temp_dir = sys_get_temp_dir();
        $temp_filename = 'cpiu_temp_' . wp_generate_password(12, false) . '.' . $extension;
        $temp_path = $temp_dir . '/' . $temp_filename;
        
        if (file_put_contents($temp_path, $data) === false) {
            throw new Exception(esc_html__('Could not create temporary file for validation.', 'custom-product-image-upload'));
        }
        
        return $temp_path;
    }
    
    /**
     * Validate file security comprehensively
     */
    private function validate_file_security($file_path, $expected_extension) {
        // Check file exists
        if (!file_exists($file_path)) {
            throw new Exception(esc_html__('File does not exist.', 'custom-product-image-upload'));
        }
        
        // Check file size
        $file_size = filesize($file_path);
        if ($file_size > $this->max_file_size) {
            throw new Exception(sprintf(esc_html__('File size (%s) exceeds maximum allowed size (%s).', 'custom-product-image-upload'), 
                size_format($file_size), 
                size_format($this->max_file_size)
            ));
        }
        
        if ($file_size < 100) { // Minimum 100 bytes
            throw new Exception(esc_html__('File is too small to be a valid image.', 'custom-product-image-upload'));
        }
        
        // Use WordPress file type validation
        $wp_filetype = wp_check_filetype_and_ext($file_path, basename($file_path), array(
            'jpg|jpeg|jpe' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp'
        ));
        
        if (!$wp_filetype['type'] || !$wp_filetype['ext']) {
            throw new Exception(esc_html__('Invalid file type. Please upload a valid image file.', 'custom-product-image-upload'));
        }
        
        // Validate MIME type using finfo as additional security check
        if (function_exists('finfo_file')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file_path);
            finfo_close($finfo);
            
            if (!in_array($mime_type, $this->allowed_mime_types)) {
                throw new Exception(sprintf(esc_html__('Invalid MIME type: %s. Only image files are allowed.', 'custom-product-image-upload'), $mime_type));
            }
        }
        
        // Validate using exif_imagetype (more reliable for images)
        if (function_exists('exif_imagetype')) {
            $image_type = exif_imagetype($file_path);
            if ($image_type === false) {
                throw new Exception(esc_html__('File is not a valid image.', 'custom-product-image-upload'));
            }
            
            // Map exif_imagetype constants to our allowed types
            $valid_exif_types = array(
                IMAGETYPE_JPEG,
                IMAGETYPE_PNG,
                IMAGETYPE_GIF,
                IMAGETYPE_WEBP
            );
            
            if (!in_array($image_type, $valid_exif_types)) {
                throw new Exception(esc_html__('Unsupported image type.', 'custom-product-image-upload'));
            }
        }
        
        // Check for double extensions
        $filename = basename($file_path);
        $path_info = pathinfo($filename);
        $extension = strtolower($path_info['extension']);
        
        // Check if filename contains multiple dots (potential double extension)
        if (substr_count($filename, '.') > 1) {
            throw new Exception(esc_html__('Files with multiple extensions are not allowed.', 'custom-product-image-upload'));
        }
        
        // Validate extension matches expected
        if ($extension !== $expected_extension) {
            throw new Exception(sprintf(esc_html__('File extension (%s) does not match expected type (%s).', 'custom-product-image-upload'), $extension, $expected_extension));
        }
        
        // Check for path traversal attempts
        if (strpos($filename, '..') !== false || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
            throw new Exception(esc_html__('Invalid characters in filename detected.', 'custom-product-image-upload'));
        }
        
        // Additional security: Check file content for executable signatures
        $this->check_for_executable_content($file_path);
        
        // Validate image resolution if enabled
        if ($this->resolution_validation) {
            $this->validate_image_resolution($file_path);
        }
    }
    
    /**
     * Validate image resolution
     */
    private function validate_image_resolution($file_path) {
        // Get image dimensions
        $image_info = getimagesize($file_path);
        
        if ($image_info === false) {
            throw new Exception(esc_html__('Could not determine image dimensions.', 'custom-product-image-upload'));
        }
        
        $width = $image_info[0];
        $height = $image_info[1];
        
        // Validate minimum dimensions
        if ($this->min_width > 0 && $width < $this->min_width) {
            throw new Exception(sprintf(
                esc_html__('Image width (%dpx) is below minimum required width (%dpx).', 'custom-product-image-upload'),
                $width,
                $this->min_width
            ));
        }
        
        if ($this->min_height > 0 && $height < $this->min_height) {
            throw new Exception(sprintf(
                esc_html__('Image height (%dpx) is below minimum required height (%dpx).', 'custom-product-image-upload'),
                $height,
                $this->min_height
            ));
        }
        
        // Validate maximum dimensions
        if ($this->max_width > 0 && $width > $this->max_width) {
            throw new Exception(sprintf(
                esc_html__('Image width (%dpx) exceeds maximum allowed width (%dpx).', 'custom-product-image-upload'),
                $width,
                $this->max_width
            ));
        }
        
        if ($this->max_height > 0 && $height > $this->max_height) {
            throw new Exception(sprintf(
                esc_html__('Image height (%dpx) exceeds maximum allowed height (%dpx).', 'custom-product-image-upload'),
                $height,
                $this->max_height
            ));
        }
    }
    
    /**
     * Check for executable content in file
     */
    private function check_for_executable_content($file_path) {
        // Initialize WordPress File System API
        global $wp_filesystem;
        if (empty($wp_filesystem)) {
            require_once ABSPATH . '/wp-admin/includes/file.php';
            WP_Filesystem();
        }
        
        // Read first 1KB to check for executable signatures
        $header = $wp_filesystem->get_contents($file_path);
        if ($header === false) {
            throw new Exception(esc_html__('Could not read file for security check.', 'custom-product-image-upload'));
        }
        
        // Limit to first 1KB for performance
        $header = substr($header, 0, 1024);
        
        // Check for PHP tags
        if (strpos($header, '<?php') !== false || strpos($header, '<?=') !== false) {
            throw new Exception(esc_html__('File contains executable code and is not allowed.', 'custom-product-image-upload'));
        }
        
        // Check for other executable signatures
        $executable_signatures = array(
            '<script',
            'javascript:',
            'vbscript:',
            'onload=',
            'onerror=',
            'eval(',
            'exec(',
            'system(',
            'shell_exec(',
            'passthru('
        );
        
        foreach ($executable_signatures as $signature) {
            if (stripos($header, $signature) !== false) {
                throw new Exception(esc_html__('File contains potentially malicious content.', 'custom-product-image-upload'));
            }
        }
    }
    
    /**
     * Generate secure filename
     */
    private function generate_secure_filename($product_id, $index, $extension) {
        // Generate random hash
        $random_hash = wp_generate_password(16, false, false);
        
        // Sanitize extension properly
        $clean_extension = strtolower(trim($extension));
        
        // Validate extension is in allowed list
        if (!in_array($clean_extension, $this->allowed_extensions)) {
            $clean_extension = 'jpg'; // Default fallback
        }
        
        // Create secure filename: prod-{product_id}-{timestamp}-{random_hash}-{index}.{extension}
        $filename = sprintf(
            'prod-%d-%d-%s-%d.%s',
            absint($product_id),
            time(),
            $random_hash,
            absint($index),
            $clean_extension
        );
        
        return $filename;
    }
    
    /**
     * Log upload attempt
     */
    private function log_upload_attempt($user_id, $ip_address, $product_id, $status, $success, $filename = '', $error_message = '') {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'user_id' => absint($user_id),
            'ip_address' => sanitize_text_field($ip_address),
            'product_id' => absint($product_id),
            'status' => sanitize_text_field($status),
            'success' => (bool) $success,
            'filename' => sanitize_file_name($filename),
            'error_message' => sanitize_text_field($error_message)
        );
        
        // Get existing logs
        $logs = get_option(self::UPLOAD_LOGS_OPTION, array());
        
        // Add new log entry
        $logs[] = $log_entry;
        
        // Keep only last 1000 entries to prevent database bloat
        if (count($logs) > 1000) {
            $logs = array_slice($logs, -1000);
        }
        
        // Save logs
        update_option(self::UPLOAD_LOGS_OPTION, $logs);
    }
    
    /**
     * Get upload logs
     */
    public static function get_upload_logs($limit = 100) {
        $logs = get_option(self::UPLOAD_LOGS_OPTION, array());
        
        // Sort by timestamp (newest first)
        usort($logs, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
        
        return array_slice($logs, 0, $limit);
    }
    
    /**
     * Clear upload logs
     */
    public static function clear_upload_logs() {
        delete_option(self::UPLOAD_LOGS_OPTION);
    }
    
    /**
     * Clean up files
     */
    public function cleanup_files($file_paths) {
        foreach ($file_paths as $file_path) {
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
    }
    
    /**
     * Get upload directory info
     */
    public function get_upload_info() {
        return array(
            'dir' => $this->upload_dir,
            'url' => $this->upload_url,
            'max_size' => $this->max_file_size
        );
    }
}
