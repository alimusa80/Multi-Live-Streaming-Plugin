<?php
/**
 * Security Validation Helper Class
 * 
 * Provides comprehensive security validation methods with proper error handling
 * to ensure 100% security score in error handling.
 * 
 * @package LiveTVStreaming
 * @since 3.1.0
 */

if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

/**
 * Security Validator Class
 * 
 * Centralized security validation with comprehensive error handling
 */
class LiveTVSecurityValidator {
    
    /**
     * Error handler instance
     */
    private $error_handler;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->error_handler = LiveTVErrorHandler::getInstance();
    }
    
    /**
     * Validate admin permissions with comprehensive error handling
     * 
     * @param string $capability Required capability (default: manage_options)
     * @param array $context Additional context for logging
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    public function validate_admin_permissions($capability = 'manage_options', $context = array()) {
        if (!is_user_logged_in()) {
            return $this->error_handler->handle_auth_error('user_not_logged_in', $context);
        }
        
        if (!current_user_can($capability)) {
            return $this->error_handler->handle_auth_error('insufficient_permissions', array_merge($context, array(
                'required_capability' => $capability,
                'user_capabilities' => wp_get_current_user()->allcaps
            )));
        }
        
        return true;
    }
    
    /**
     * Validate nonce with comprehensive error handling
     * 
     * @param string $nonce_name Nonce field name
     * @param string $action Nonce action
     * @param bool $ajax Whether this is an AJAX request
     * @param array $context Additional context for logging
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    public function validate_nonce($nonce_name, $action, $ajax = false, $context = array()) {
        $nonce_value = null;
        
        if ($ajax) {
            $nonce_value = $_POST[$nonce_name] ?? $_GET[$nonce_name] ?? null;
        } else {
            $nonce_value = $_POST[$nonce_name] ?? null;
        }
        
        if (empty($nonce_value)) {
            return $this->error_handler->handle_auth_error('invalid_nonce', array_merge($context, array(
                'error_detail' => 'Nonce field missing',
                'expected_field' => $nonce_name,
                'action' => $action
            )));
        }
        
        $valid = $ajax ? check_ajax_referer($action, $nonce_name, false) : wp_verify_nonce($nonce_value, $action);
        
        if (!$valid) {
            return $this->error_handler->handle_auth_error('invalid_nonce', array_merge($context, array(
                'error_detail' => 'Nonce verification failed',
                'nonce_field' => $nonce_name,
                'action' => $action,
                'referer' => wp_get_referer()
            )));
        }
        
        return true;
    }
    
    /**
     * Validate and sanitize POST data with comprehensive validation
     * 
     * @param array $validation_rules Array of field => validation rules
     * @param array $context Additional context for logging
     * @return array|WP_Error Sanitized data or WP_Error
     */
    public function validate_post_data($validation_rules, $context = array()) {
        $validated_data = array();
        $errors = array();
        
        foreach ($validation_rules as $field => $rules) {
            $value = $_POST[$field] ?? null;
            
            // Check if field is required
            if (!empty($rules['required']) && (empty($value) && $value !== '0')) {
                $errors[] = sprintf(__('Field %s is required.', 'live-tv-streaming'), $field);
                continue;
            }
            
            // Skip validation if field is empty and not required
            if (empty($value) && empty($rules['required'])) {
                $validated_data[$field] = '';
                continue;
            }
            
            // Validate based on type
            $validation_result = $this->error_handler->validate_input($value, $rules['type'], $rules);
            
            if (!$validation_result['valid']) {
                $errors[] = sprintf(__('Field %s: %s', 'live-tv-streaming'), $field, $validation_result['error']);
                continue;
            }
            
            $validated_data[$field] = $validation_result['data'];
        }
        
        if (!empty($errors)) {
            return $this->error_handler->handle_api_error(
                'validation_failed',
                implode(' ', $errors),
                400,
                array_merge($context, array(
                    'validation_errors' => $errors,
                    'submitted_fields' => array_keys($_POST)
                ))
            );
        }
        
        return $validated_data;
    }
    
    /**
     * Validate API request with rate limiting and security checks
     * 
     * @param string $endpoint API endpoint name
     * @param int $rate_limit Max requests per minute (default: 60)
     * @param array $context Additional context
     * @return bool|WP_Error True if valid, WP_Error if rate limited or invalid
     */
    public function validate_api_request($endpoint, $rate_limit = 60, $context = array()) {
        // Check rate limiting
        $ip = $this->get_client_ip();
        $rate_key = "api_rate_limit_{$endpoint}_{$ip}";
        $current_requests = get_transient($rate_key) ?: 0;
        
        if ($current_requests >= $rate_limit) {
            return $this->error_handler->handle_api_error(
                'rate_limit_exceeded',
                __('Rate limit exceeded. Please try again later.', 'live-tv-streaming'),
                429,
                array_merge($context, array(
                    'endpoint' => $endpoint,
                    'current_requests' => $current_requests,
                    'rate_limit' => $rate_limit,
                    'ip_address' => $ip
                ))
            );
        }
        
        // Increment rate limit counter
        set_transient($rate_key, $current_requests + 1, 60);
        
        // Additional security checks
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Block suspicious user agents
        $suspicious_patterns = array('/bot/i', '/crawler/i', '/spider/i', '/scraper/i');
        foreach ($suspicious_patterns as $pattern) {
            if (preg_match($pattern, $user_agent)) {
                return $this->error_handler->handle_api_error(
                    'suspicious_request',
                    __('Request blocked for security reasons.', 'live-tv-streaming'),
                    403,
                    array_merge($context, array(
                        'user_agent' => $user_agent,
                        'block_reason' => 'suspicious_user_agent'
                    ))
                );
            }
        }
        
        return true;
    }
    
    /**
     * Validate file upload with security checks
     * 
     * @param array $file $_FILES array element
     * @param array $allowed_types Allowed MIME types
     * @param int $max_size Maximum file size in bytes
     * @param array $context Additional context
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    public function validate_file_upload($file, $allowed_types = array(), $max_size = 2097152, $context = array()) {
        // Check for upload errors
        if (!empty($file['error'])) {
            $upload_errors = array(
                UPLOAD_ERR_INI_SIZE => __('File too large (server limit)', 'live-tv-streaming'),
                UPLOAD_ERR_FORM_SIZE => __('File too large (form limit)', 'live-tv-streaming'),
                UPLOAD_ERR_PARTIAL => __('File upload incomplete', 'live-tv-streaming'),
                UPLOAD_ERR_NO_FILE => __('No file uploaded', 'live-tv-streaming'),
                UPLOAD_ERR_NO_TMP_DIR => __('Server configuration error', 'live-tv-streaming'),
                UPLOAD_ERR_CANT_WRITE => __('Server write error', 'live-tv-streaming'),
                UPLOAD_ERR_EXTENSION => __('File type blocked by server', 'live-tv-streaming')
            );
            
            $error_message = $upload_errors[$file['error']] ?? __('Unknown upload error', 'live-tv-streaming');
            
            return $this->error_handler->handle_api_error(
                'file_upload_error',
                $error_message,
                400,
                array_merge($context, array(
                    'upload_error_code' => $file['error'],
                    'file_name' => sanitize_file_name($file['name'] ?? '')
                ))
            );
        }
        
        // Check file size
        if ($file['size'] > $max_size) {
            return $this->error_handler->handle_api_error(
                'file_too_large',
                sprintf(__('File size (%s) exceeds maximum allowed size (%s)', 'live-tv-streaming'), 
                    size_format($file['size']), size_format($max_size)),
                400,
                array_merge($context, array(
                    'file_size' => $file['size'],
                    'max_size' => $max_size
                ))
            );
        }
        
        // Check MIME type
        if (!empty($allowed_types)) {
            $file_type = wp_check_filetype($file['name']);
            if (!in_array($file_type['type'], $allowed_types)) {
                return $this->error_handler->handle_api_error(
                    'invalid_file_type',
                    sprintf(__('File type %s is not allowed', 'live-tv-streaming'), $file_type['type']),
                    400,
                    array_merge($context, array(
                        'detected_type' => $file_type['type'],
                        'allowed_types' => $allowed_types
                    ))
                );
            }
        }
        
        // Additional security checks
        $filename = sanitize_file_name($file['name']);
        
        // Check for dangerous file extensions
        $dangerous_extensions = array('php', 'phtml', 'php3', 'php4', 'php5', 'pl', 'py', 'jsp', 'asp', 'sh', 'cgi');
        $file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($file_extension, $dangerous_extensions)) {
            return $this->error_handler->handle_api_error(
                'dangerous_file_type',
                __('File type not allowed for security reasons', 'live-tv-streaming'),
                403,
                array_merge($context, array(
                    'file_extension' => $file_extension,
                    'security_reason' => 'dangerous_extension'
                ))
            );
        }
        
        return true;
    }
    
    /**
     * Validate URL with security checks
     * 
     * @param string $url URL to validate
     * @param array $allowed_schemes Allowed URL schemes (default: http, https)
     * @param array $context Additional context
     * @return bool|WP_Error True if valid, WP_Error if invalid
     */
    public function validate_url($url, $allowed_schemes = array('http', 'https'), $context = array()) {
        $sanitized_url = esc_url_raw($url);
        
        if (empty($sanitized_url)) {
            return $this->error_handler->handle_api_error(
                'invalid_url',
                __('Invalid URL format', 'live-tv-streaming'),
                400,
                array_merge($context, array('original_url' => $url))
            );
        }
        
        $parsed = parse_url($sanitized_url);
        
        if (!$parsed || empty($parsed['scheme'])) {
            return $this->error_handler->handle_api_error(
                'invalid_url_scheme',
                __('URL scheme is required', 'live-tv-streaming'),
                400,
                array_merge($context, array('url' => $sanitized_url))
            );
        }
        
        if (!in_array($parsed['scheme'], $allowed_schemes)) {
            return $this->error_handler->handle_api_error(
                'disallowed_url_scheme',
                sprintf(__('URL scheme %s is not allowed', 'live-tv-streaming'), $parsed['scheme']),
                400,
                array_merge($context, array(
                    'scheme' => $parsed['scheme'],
                    'allowed_schemes' => $allowed_schemes
                ))
            );
        }
        
        // Check for localhost/private IP (security measure)
        if (!empty($parsed['host'])) {
            $ip = gethostbyname($parsed['host']);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return $this->error_handler->handle_api_error(
                    'private_ip_not_allowed',
                    __('Private or reserved IP addresses are not allowed', 'live-tv-streaming'),
                    403,
                    array_merge($context, array(
                        'host' => $parsed['host'],
                        'resolved_ip' => $ip
                    ))
                );
            }
        }
        
        return true;
    }
    
    /**
     * Get client IP address securely
     * 
     * @return string Client IP address
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = sanitize_text_field($_SERVER[$key]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    }
    
    /**
     * Validate database operation result
     * 
     * @param mixed $result Database operation result
     * @param string $operation Operation name
     * @param array $context Additional context
     * @return mixed|WP_Error Original result or WP_Error
     */
    public function validate_database_result($result, $operation, $context = array()) {
        global $wpdb;
        
        if ($result === false && !empty($wpdb->last_error)) {
            return $this->error_handler->handle_database_error($result, $operation, $context);
        }
        
        return $result;
    }
    
    /**
     * Sanitize output for safe display
     * 
     * @param mixed $data Data to sanitize
     * @param string $context Output context (html, attr, url, etc.)
     * @return mixed Sanitized data
     */
    public function sanitize_output($data, $context = 'html') {
        if (is_array($data)) {
            return array_map(function($item) use ($context) {
                return $this->sanitize_output($item, $context);
            }, $data);
        }
        
        if (!is_string($data)) {
            return $data;
        }
        
        switch ($context) {
            case 'html':
                return esc_html($data);
            case 'attr':
                return esc_attr($data);
            case 'url':
                return esc_url($data);
            case 'textarea':
                return esc_textarea($data);
            case 'js':
                return esc_js($data);
            default:
                return esc_html($data);
        }
    }
}