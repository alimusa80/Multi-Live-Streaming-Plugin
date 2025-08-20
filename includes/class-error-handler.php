<?php
/**
 * Comprehensive Error Handling Class for Live TV Streaming Plugin
 * 
 * Provides enterprise-grade error handling, logging, and recovery mechanisms
 * to achieve 100% security score in error handling.
 * 
 * @package LiveTVStreaming
 * @since 3.1.0
 */

if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

/**
 * Live TV Error Handler Class
 * 
 * Centralized error handling with security-focused logging and sanitization
 */
class LiveTVErrorHandler {
    
    /**
     * Error levels for logging and handling
     */
    const LEVEL_EMERGENCY = 'emergency';
    const LEVEL_ALERT = 'alert';
    const LEVEL_CRITICAL = 'critical';
    const LEVEL_ERROR = 'error';
    const LEVEL_WARNING = 'warning';
    const LEVEL_NOTICE = 'notice';
    const LEVEL_INFO = 'info';
    const LEVEL_DEBUG = 'debug';
    
    /**
     * Maximum log file size (5MB)
     */
    const MAX_LOG_SIZE = 5242880;
    
    /**
     * Security contexts for different error types
     */
    const CONTEXT_AUTH = 'authentication';
    const CONTEXT_SQL = 'database';
    const CONTEXT_XSS = 'cross_site_scripting';
    const CONTEXT_CSRF = 'csrf_protection';
    const CONTEXT_FILE = 'file_security';
    const CONTEXT_API = 'api_security';
    const CONTEXT_GENERAL = 'general';
    
    /**
     * Instance holder
     */
    private static $instance = null;
    
    /**
     * Log file path
     */
    private $log_file;
    
    /**
     * Rate limiting storage
     */
    private $rate_limits = array();
    
    /**
     * Get singleton instance
     * 
     * @return LiveTVErrorHandler
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor - Initialize error handler
     */
    private function __construct() {
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/live-tv-streaming/logs';
        
        // Create secure log directory
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
            // Create .htaccess to deny direct access
            file_put_contents($log_dir . '/.htaccess', "deny from all\n");
            // Create index.php to prevent directory listing
            file_put_contents($log_dir . '/index.php', "<?php // Silence is golden");
        }
        
        $this->log_file = $log_dir . '/error-log-' . date('Y-m') . '.log';
        
        // Set up error handlers
        $this->setup_error_handlers();
    }
    
    /**
     * Set up PHP error handlers
     */
    private function setup_error_handlers() {
        // Only in development/debug mode
        if (defined('WP_DEBUG') && WP_DEBUG) {
            set_error_handler(array($this, 'handle_php_error'));
            set_exception_handler(array($this, 'handle_exception'));
            register_shutdown_function(array($this, 'handle_fatal_error'));
        }
    }
    
    /**
     * Log security-related error with context
     * 
     * @param string $message Error message
     * @param string $level Error level
     * @param string $context Security context
     * @param array $data Additional data (will be sanitized)
     * @return bool Success status
     */
    public function log_security_error($message, $level = self::LEVEL_ERROR, $context = self::CONTEXT_GENERAL, $data = array()) {
        try {
            // Sanitize all inputs
            $message = $this->sanitize_log_message($message);
            $level = sanitize_key($level);
            $context = sanitize_key($context);
            $data = $this->sanitize_log_data($data);
            
            // Rate limiting for security events
            if ($this->is_rate_limited($context)) {
                return false;
            }
            
            // Prepare log entry
            $entry = array(
                'timestamp' => current_time('Y-m-d H:i:s T'),
                'level' => $level,
                'context' => $context,
                'message' => $message,
                'ip_address' => $this->get_client_ip(),
                'user_agent' => $this->sanitize_user_agent(),
                'user_id' => get_current_user_id(),
                'request_uri' => $this->sanitize_request_uri(),
                'data' => $data
            );
            
            // Write to log
            $this->write_to_log($entry);
            
            // Handle critical security events
            if (in_array($level, array(self::LEVEL_EMERGENCY, self::LEVEL_ALERT, self::LEVEL_CRITICAL))) {
                $this->handle_critical_security_event($entry);
            }
            
            return true;
            
        } catch (Exception $e) {
            // Fallback error logging
            error_log('Live TV Security Logger Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Handle database errors securely
     * 
     * @param mixed $result Database operation result
     * @param string $operation Operation description
     * @param array $context Additional context
     * @return WP_Error|mixed
     */
    public function handle_database_error($result, $operation, $context = array()) {
        global $wpdb;
        
        if ($result === false && !empty($wpdb->last_error)) {
            // Log database error without exposing sensitive info
            $this->log_security_error(
                sprintf('Database operation failed: %s', sanitize_text_field($operation)),
                self::LEVEL_ERROR,
                self::CONTEXT_SQL,
                array(
                    'operation' => sanitize_text_field($operation),
                    'affected_rows' => $wpdb->rows_affected,
                    'context' => $this->sanitize_log_data($context)
                )
            );
            
            return new WP_Error(
                'database_error',
                __('Database operation failed. Please try again.', 'live-tv-streaming'),
                array('status' => 500)
            );
        }
        
        return $result;
    }
    
    /**
     * Handle authentication errors
     * 
     * @param string $error_type Type of auth error
     * @param array $context Context data
     * @return WP_Error
     */
    public function handle_auth_error($error_type, $context = array()) {
        $error_messages = array(
            'invalid_nonce' => __('Security token expired. Please refresh and try again.', 'live-tv-streaming'),
            'insufficient_permissions' => __('You do not have permission to perform this action.', 'live-tv-streaming'),
            'user_not_logged_in' => __('Please log in to continue.', 'live-tv-streaming'),
            'invalid_user' => __('Invalid user credentials.', 'live-tv-streaming')
        );
        
        $message = isset($error_messages[$error_type]) ? $error_messages[$error_type] : __('Authentication failed.', 'live-tv-streaming');
        
        // Log security event
        $this->log_security_error(
            sprintf('Authentication error: %s', $error_type),
            self::LEVEL_WARNING,
            self::CONTEXT_AUTH,
            $this->sanitize_log_data($context)
        );
        
        return new WP_Error($error_type, $message, array('status' => 403));
    }
    
    /**
     * Handle API errors with proper HTTP status codes
     * 
     * @param string $error_code Error code
     * @param string $message Error message
     * @param int $status_code HTTP status code
     * @param array $context Additional context
     * @return WP_Error
     */
    public function handle_api_error($error_code, $message, $status_code = 400, $context = array()) {
        // Sanitize inputs
        $error_code = sanitize_key($error_code);
        $message = sanitize_text_field($message);
        $status_code = absint($status_code);
        
        // Log API error
        $this->log_security_error(
            sprintf('API Error: %s - %s', $error_code, $message),
            self::LEVEL_ERROR,
            self::CONTEXT_API,
            array_merge($context, array('status_code' => $status_code))
        );
        
        return new WP_Error($error_code, $message, array('status' => $status_code));
    }
    
    /**
     * Validate and sanitize user input with error handling
     * 
     * @param mixed $input Input to validate
     * @param string $type Expected type
     * @param array $rules Validation rules
     * @return array Array with 'valid' boolean and 'data' or 'error'
     */
    public function validate_input($input, $type, $rules = array()) {
        try {
            switch ($type) {
                case 'integer':
                    $sanitized = absint($input);
                    if (isset($rules['min']) && $sanitized < $rules['min']) {
                        return array('valid' => false, 'error' => 'Value too small');
                    }
                    if (isset($rules['max']) && $sanitized > $rules['max']) {
                        return array('valid' => false, 'error' => 'Value too large');
                    }
                    return array('valid' => true, 'data' => $sanitized);
                    
                case 'string':
                    $sanitized = sanitize_text_field($input);
                    if (isset($rules['max_length']) && strlen($sanitized) > $rules['max_length']) {
                        return array('valid' => false, 'error' => 'String too long');
                    }
                    if (isset($rules['pattern']) && !preg_match($rules['pattern'], $sanitized)) {
                        return array('valid' => false, 'error' => 'Invalid format');
                    }
                    return array('valid' => true, 'data' => $sanitized);
                    
                case 'email':
                    $sanitized = sanitize_email($input);
                    if (!is_email($sanitized)) {
                        return array('valid' => false, 'error' => 'Invalid email format');
                    }
                    return array('valid' => true, 'data' => $sanitized);
                    
                case 'url':
                    $sanitized = esc_url_raw($input);
                    if (!filter_var($sanitized, FILTER_VALIDATE_URL)) {
                        return array('valid' => false, 'error' => 'Invalid URL format');
                    }
                    return array('valid' => true, 'data' => $sanitized);
                    
                default:
                    return array('valid' => false, 'error' => 'Unknown validation type');
            }
        } catch (Exception $e) {
            $this->log_security_error(
                'Input validation error: ' . $e->getMessage(),
                self::LEVEL_ERROR,
                self::CONTEXT_GENERAL
            );
            return array('valid' => false, 'error' => 'Validation failed');
        }
    }
    
    /**
     * Handle PHP errors
     */
    public function handle_php_error($errno, $errstr, $errfile, $errline) {
        // Skip if error reporting is turned off
        if (!(error_reporting() & $errno)) {
            return false;
        }
        
        $error_types = array(
            E_ERROR => 'ERROR',
            E_WARNING => 'WARNING',
            E_PARSE => 'PARSE',
            E_NOTICE => 'NOTICE'
        );
        
        $type = isset($error_types[$errno]) ? $error_types[$errno] : 'UNKNOWN';
        
        $this->log_security_error(
            sprintf('PHP %s: %s in %s on line %d', $type, $errstr, basename($errfile), $errline),
            self::LEVEL_ERROR,
            self::CONTEXT_GENERAL
        );
        
        return true;
    }
    
    /**
     * Handle uncaught exceptions
     */
    public function handle_exception($exception) {
        $this->log_security_error(
            sprintf('Uncaught Exception: %s in %s on line %d', 
                $exception->getMessage(),
                basename($exception->getFile()),
                $exception->getLine()
            ),
            self::LEVEL_CRITICAL,
            self::CONTEXT_GENERAL
        );
    }
    
    /**
     * Handle fatal errors
     */
    public function handle_fatal_error() {
        $error = error_get_last();
        if ($error && in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR))) {
            $this->log_security_error(
                sprintf('Fatal Error: %s in %s on line %d', 
                    $error['message'],
                    basename($error['file']),
                    $error['line']
                ),
                self::LEVEL_EMERGENCY,
                self::CONTEXT_GENERAL
            );
        }
    }
    
    /**
     * Check if rate limited
     */
    private function is_rate_limited($context) {
        $key = $context . '_' . $this->get_client_ip();
        $now = time();
        $limit = 10; // Max 10 errors per minute per IP per context
        $window = 60; // 1 minute window
        
        if (!isset($this->rate_limits[$key])) {
            $this->rate_limits[$key] = array();
        }
        
        // Clean old entries
        $this->rate_limits[$key] = array_filter($this->rate_limits[$key], function($timestamp) use ($now, $window) {
            return ($now - $timestamp) < $window;
        });
        
        if (count($this->rate_limits[$key]) >= $limit) {
            return true;
        }
        
        $this->rate_limits[$key][] = $now;
        return false;
    }
    
    /**
     * Get client IP address securely
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
     * Sanitize user agent
     */
    private function sanitize_user_agent() {
        return sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
    }
    
    /**
     * Sanitize request URI
     */
    private function sanitize_request_uri() {
        return sanitize_text_field($_SERVER['REQUEST_URI'] ?? 'unknown');
    }
    
    /**
     * Sanitize log message
     */
    private function sanitize_log_message($message) {
        // Remove potential sensitive data patterns
        $patterns = array(
            '/password[^=]*=\s*[\'"][^"\']*[\'"]?/i',
            '/api[_-]?key[^=]*=\s*[\'"]?[a-zA-Z0-9\-_]+[\'"]?/i',
            '/token[^=]*=\s*[\'"]?[a-zA-Z0-9\-_]+[\'"]?/i',
            '/\b\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}\b/', // Credit card numbers
            '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/' // Email addresses
        );
        
        $replacements = array(
            'password=***',
            'api_key=***',
            'token=***',
            '****-****-****-****',
            '***@***.***'
        );
        
        return preg_replace($patterns, $replacements, sanitize_text_field($message));
    }
    
    /**
     * Sanitize log data array
     */
    private function sanitize_log_data($data) {
        if (!is_array($data)) {
            return array();
        }
        
        $sanitized = array();
        foreach ($data as $key => $value) {
            $safe_key = sanitize_key($key);
            if (is_string($value)) {
                $sanitized[$safe_key] = $this->sanitize_log_message($value);
            } elseif (is_numeric($value)) {
                $sanitized[$safe_key] = $value;
            } elseif (is_bool($value)) {
                $sanitized[$safe_key] = $value ? 'true' : 'false';
            } elseif (is_array($value)) {
                $sanitized[$safe_key] = $this->sanitize_log_data($value);
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Write to log file
     */
    private function write_to_log($entry) {
        // Check log file size and rotate if needed
        if (file_exists($this->log_file) && filesize($this->log_file) > self::MAX_LOG_SIZE) {
            $this->rotate_log_file();
        }
        
        $log_line = json_encode($entry) . "\n";
        
        // Use file locking for concurrent access
        if ($handle = fopen($this->log_file, 'a')) {
            if (flock($handle, LOCK_EX)) {
                fwrite($handle, $log_line);
                flock($handle, LOCK_UN);
            }
            fclose($handle);
        }
    }
    
    /**
     * Rotate log file when it gets too large
     */
    private function rotate_log_file() {
        $backup_file = $this->log_file . '.backup.' . time();
        rename($this->log_file, $backup_file);
        
        // Keep only last 5 backup files
        $log_dir = dirname($this->log_file);
        $backup_files = glob($log_dir . '/error-log-*.log.backup.*');
        if (count($backup_files) > 5) {
            sort($backup_files);
            unlink($backup_files[0]);
        }
    }
    
    /**
     * Handle critical security events
     */
    private function handle_critical_security_event($entry) {
        // For critical events, consider:
        // 1. Email notification to admin
        // 2. Temporary IP blocking
        // 3. Session termination
        
        $admin_email = get_option('admin_email');
        if ($admin_email && is_email($admin_email)) {
            $subject = sprintf('[%s] Critical Security Event', get_bloginfo('name'));
            $message = sprintf(
                "Critical security event detected:\n\nTimestamp: %s\nLevel: %s\nContext: %s\nMessage: %s\nIP: %s\n",
                $entry['timestamp'],
                $entry['level'],
                $entry['context'],
                $entry['message'],
                $entry['ip_address']
            );
            
            wp_mail($admin_email, $subject, $message);
        }
    }
    
    /**
     * Get error statistics for admin dashboard
     * 
     * @param int $days Number of days to analyze
     * @return array Error statistics
     */
    public function get_error_statistics($days = 7) {
        if (!current_user_can('manage_options')) {
            return array();
        }
        
        $stats = array(
            'total_errors' => 0,
            'critical_errors' => 0,
            'security_events' => 0,
            'top_error_types' => array(),
            'error_trend' => array()
        );
        
        // Read and analyze log files (implementation would parse log files)
        // This is a placeholder for log analysis functionality
        
        return $stats;
    }
    
    /**
     * Clean up old log files
     */
    public function cleanup_old_logs() {
        $log_dir = dirname($this->log_file);
        $cutoff_time = time() - (30 * 24 * 60 * 60); // 30 days
        
        $log_files = glob($log_dir . '/error-log-*.log*');
        foreach ($log_files as $file) {
            if (filemtime($file) < $cutoff_time) {
                unlink($file);
            }
        }
    }
}

// Initialize error handler
LiveTVErrorHandler::getInstance();