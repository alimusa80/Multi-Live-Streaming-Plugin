<?php
/**
 * Analytics and Reporting Class
 * 
 * Handles viewing analytics, user tracking, and reporting functionality
 * for the Live TV Streaming Plugin with comprehensive data protection.
 * 
 * @package LiveTVStreaming
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

/**
 * LiveTVAnalytics class for tracking and reporting
 * 
 * This class manages all analytics functionality including view tracking,
 * user engagement metrics, and administrative reporting with privacy protection.
 * 
 * @since 1.0.0
 */
class LiveTVAnalytics {
    
    /**
     * Analytics database table name
     * @var string
     * @since 1.0.0
     */
    private $table_name;
    
    /**
     * Initialize analytics functionality
     * 
     * Sets up database table reference and registers AJAX handlers
     * for both logged-in and guest users with proper security.
     * 
     * @since 1.0.0
     */
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'live_tv_analytics';
        
        // Register AJAX handlers for view tracking (both user types)
        add_action('wp_ajax_track_channel_view', array($this, 'track_channel_view'));
        add_action('wp_ajax_nopriv_track_channel_view', array($this, 'track_channel_view'));
        
        // Register AJAX handler for admin analytics data (admin only)
        add_action('wp_ajax_get_analytics_data', array($this, 'get_analytics_data'));
    }
    
    /**
     * Create analytics database table
     * 
     * Creates the analytics table with proper structure, indexes,
     * and constraints for optimal performance and data integrity.
     * 
     * @since 1.0.0
     * @return bool True on success, false on failure
     */
    public function create_analytics_table() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $this->table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            channel_id int(11) NOT NULL,
            user_id bigint(20) DEFAULT NULL,
            session_id varchar(64) NOT NULL,
            ip_address varchar(45) NOT NULL,
            user_agent text,
            watch_duration int(11) DEFAULT 0,
            device_type varchar(50) DEFAULT 'desktop',
            country varchar(10) DEFAULT '',
            referrer text,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY channel_id (channel_id),
            KEY user_id (user_id),
            KEY timestamp (timestamp),
            KEY device_type (device_type)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Handle AJAX request to track channel views
     * 
     * Processes view tracking requests with comprehensive validation
     * and privacy protection measures.
     * 
     * @since 1.0.0
     */
    public function track_channel_view() {
        // Rate limiting check - prevent spam tracking
        if ($this->is_rate_limited()) {
            wp_send_json_error(array(
                'message' => __('Rate limit exceeded. Please wait before tracking again.', 'live-tv-streaming')
            ));
            return;
        }
        
        // Verify nonce for logged-in users only
        if (is_user_logged_in()) {
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'live_tv_nonce')) {
                wp_send_json_error(array(
                    'message' => __('Security verification failed.', 'live-tv-streaming')
                ));
                return;
            }
        }
        
        // Validate and sanitize input data
        $channel_id = intval($_POST['channel_id'] ?? 0);
        $watch_duration = intval($_POST['duration'] ?? 0);
        
        if (!$channel_id || $channel_id <= 0) {
            wp_send_json_error(array(
                'message' => __('Invalid channel ID provided.', 'live-tv-streaming')
            ));
            return;
        }
        
        // Validate watch duration (reasonable limits)
        if ($watch_duration < 0 || $watch_duration > 86400) { // Max 24 hours
            $watch_duration = 0;
        }
        
        // Verify channel exists and is active
        if (!$this->channel_exists($channel_id)) {
            wp_send_json_error(array(
                'message' => __('Channel not found or inactive.', 'live-tv-streaming')
            ));
            return;
        }
        
        // Record the view
        $result = $this->record_view($channel_id, $watch_duration);
        
        if ($result) {
            wp_send_json_success(array(
                'message' => __('View tracked successfully.', 'live-tv-streaming')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to track view.', 'live-tv-streaming')
            ));
        }
    }
    
    /**
     * Record a channel view in the database
     * 
     * Safely stores view data with proper validation and privacy measures.
     * 
     * @since 1.0.0
     * @param int $channel_id Channel ID being viewed
     * @param int $watch_duration Duration watched in seconds
     * @return bool True on success, false on failure
     */
    public function record_view($channel_id, $watch_duration = 0) {
        global $wpdb;
        
        // Sanitize and validate all data
        $data = array(
            'channel_id' => intval($channel_id),
            'user_id' => is_user_logged_in() ? get_current_user_id() : null,
            'session_id' => $this->get_session_id(),
            'ip_address' => $this->get_user_ip_anonymized(), // Anonymize IP for privacy
            'user_agent' => sanitize_text_field(substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500)),
            'watch_duration' => max(0, intval($watch_duration)),
            'device_type' => sanitize_text_field($this->detect_device_type()),
            'country' => sanitize_text_field($this->get_user_country()),
            'referrer' => esc_url_raw(wp_get_referer() ?: '')
        );
        
        // Insert with proper data formatting
        $result = $wpdb->insert(
            $this->table_name, 
            $data,
            array('%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s') // Data formats
        );
        
        if ($result === false && defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Live TV Analytics: Failed to record view - ' . $wpdb->last_error);
        }
        
        return $result !== false;
    }
    
    /**
     * Handle AJAX request for analytics data (admin only)
     * 
     * Provides dashboard analytics with proper security checks
     * and comprehensive data validation.
     * 
     * @since 1.0.0
     */
    public function get_analytics_data() {
        // Verify admin permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions to access analytics.', 'live-tv-streaming')
            ));
            return;
        }
        
        // Verify nonce for security
        if (!check_ajax_referer('live_tv_admin_nonce', 'nonce', false)) {
            wp_send_json_error(array(
                'message' => __('Security verification failed.', 'live-tv-streaming')
            ));
            return;
        }
        
        // Validate and sanitize period parameter
        $period = sanitize_text_field($_POST['period'] ?? '7days');
        $allowed_periods = array('24hours', '7days', '30days', '90days');
        
        if (!in_array($period, $allowed_periods)) {
            $period = '7days'; // Default fallback
        }
        
        // Get analytics data
        $data = $this->get_dashboard_stats($period);
        
        if ($data !== false) {
            wp_send_json_success(array(
                'data' => $data,
                'period' => $period,
                'generated_at' => current_time('mysql')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to retrieve analytics data.', 'live-tv-streaming')
            ));
        }
    }
    
    /**
     * Get dashboard statistics
     */
    public function get_dashboard_stats($period = '7days') {
        global $wpdb;
        
        $date_condition = $this->get_date_condition($period);
        
        // Total views
        $total_views = $wpdb->get_var("SELECT COUNT(*) FROM $this->table_name WHERE $date_condition");
        
        // Unique viewers
        $unique_viewers = $wpdb->get_var("SELECT COUNT(DISTINCT session_id) FROM $this->table_name WHERE $date_condition");
        
        // Top channels
        $top_channels = $wpdb->get_results("
            SELECT 
                c.name,
                COUNT(a.id) as views,
                AVG(a.watch_duration) as avg_duration
            FROM $this->table_name a
            JOIN {$wpdb->prefix}live_tv_channels c ON a.channel_id = c.id
            WHERE $date_condition
            GROUP BY a.channel_id, c.name
            ORDER BY views DESC
            LIMIT 10
        ");
        
        // Device breakdown
        $device_stats = $wpdb->get_results("
            SELECT 
                device_type,
                COUNT(*) as count
            FROM $this->table_name 
            WHERE $date_condition
            GROUP BY device_type
            ORDER BY count DESC
        ");
        
        // Viewing trends (daily)
        $daily_trends = $wpdb->get_results("
            SELECT 
                DATE(timestamp) as date,
                COUNT(*) as views,
                COUNT(DISTINCT session_id) as unique_viewers
            FROM $this->table_name
            WHERE $date_condition
            GROUP BY DATE(timestamp)
            ORDER BY date ASC
        ");
        
        return array(
            'total_views' => (int) $total_views,
            'unique_viewers' => (int) $unique_viewers,
            'top_channels' => $top_channels,
            'device_stats' => $device_stats,
            'daily_trends' => $daily_trends,
            'average_session_duration' => $this->get_average_session_duration($period)
        );
    }
    
    /**
     * Get date condition for SQL queries
     */
    private function get_date_condition($period) {
        switch ($period) {
            case '24hours':
                return "timestamp >= DATE_SUB(NOW(), INTERVAL 1 DAY)";
            case '7days':
                return "timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
            case '30days':
                return "timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
            case '90days':
                return "timestamp >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
            default:
                return "timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        }
    }
    
    /**
     * Get average session duration
     */
    private function get_average_session_duration($period) {
        global $wpdb;
        
        $date_condition = $this->get_date_condition($period);
        
        $avg_duration = $wpdb->get_var("
            SELECT AVG(watch_duration) 
            FROM $this->table_name 
            WHERE $date_condition AND watch_duration > 0
        ");
        
        return round($avg_duration ?: 0);
    }
    
    /**
     * Get or generate session ID
     */
    private function get_session_id() {
        if (!session_id()) {
            session_start();
        }
        
        if (!isset($_SESSION['live_tv_session'])) {
            $_SESSION['live_tv_session'] = wp_generate_password(32, false);
        }
        
        return $_SESSION['live_tv_session'];
    }
    
    /**
     * Get anonymized user IP address for privacy compliance
     * 
     * Retrieves and anonymizes the user's IP address to comply
     * with privacy regulations while maintaining analytics value.
     * 
     * @since 3.1.0
     * @return string Anonymized IP address
     */
    private function get_user_ip_anonymized() {
        $ip = $this->get_user_ip();
        
        // Anonymize IP for privacy (remove last octet for IPv4, last 80 bits for IPv6)
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ip_parts = explode('.', $ip);
            $ip_parts[3] = '0'; // Remove last octet
            return implode('.', $ip_parts);
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $ip_parts = explode(':', $ip);
            // Keep first 48 bits, anonymize the rest
            return implode(':', array_slice($ip_parts, 0, 3)) . '::0';
        }
        
        return '0.0.0.0';
    }
    
    /**
     * Get user IP address from various headers
     * 
     * Attempts to get the real IP address considering proxies and CDNs.
     * 
     * @since 1.0.0
     * @return string IP address
     */
    private function get_user_ip() {
        $ip_keys = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    // Validate IP and exclude private/reserved ranges
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }
    
    /**
     * Detect device type
     */
    private function detect_device_type() {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (preg_match('/Mobile|Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i', $user_agent)) {
            if (preg_match('/iPad|Android(?!.*Mobile)/i', $user_agent)) {
                return 'tablet';
            }
            return 'mobile';
        }
        
        return 'desktop';
    }
    
    /**
     * Get user country (basic implementation)
     */
    private function get_user_country() {
        // In a real implementation, you'd use a GeoIP service
        // For now, return empty string
        return '';
    }
    
    /**
     * Check if current user/session is rate limited
     * 
     * Prevents abuse by limiting the frequency of view tracking.
     * 
     * @since 3.1.0
     * @return bool True if rate limited, false otherwise
     */
    private function is_rate_limited() {
        $session_id = $this->get_session_id();
        $rate_limit_key = 'live_tv_rate_limit_' . md5($session_id . $this->get_user_ip());
        
        $last_track_time = get_transient($rate_limit_key);
        $current_time = time();
        
        // Allow one tracking per 10 seconds per session/IP
        if ($last_track_time && ($current_time - $last_track_time) < 10) {
            return true;
        }
        
        // Update rate limit timestamp
        set_transient($rate_limit_key, $current_time, 60); // Expire in 1 minute
        
        return false;
    }
    
    /**
     * Verify that a channel exists and is active
     * 
     * @since 3.1.0
     * @param int $channel_id Channel ID to verify
     * @return bool True if channel exists and is active, false otherwise
     */
    private function channel_exists($channel_id) {
        global $wpdb;
        
        $channel_table = $wpdb->prefix . 'live_tv_channels';
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$channel_table} WHERE id = %d AND is_active = 1",
            intval($channel_id)
        ));
        
        return intval($exists) > 0;
    }
    
    /**
     * Clean up old analytics data for privacy compliance
     * 
     * Removes analytics data older than the specified retention period.
     * This method should be called periodically via cron job.
     * 
     * @since 3.1.0
     * @param int $retention_days Number of days to retain data (default: 90)
     * @return int Number of records deleted
     */
    public function cleanup_old_analytics($retention_days = 90) {
        global $wpdb;
        
        $retention_days = max(1, intval($retention_days)); // Minimum 1 day
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table_name} WHERE timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $retention_days
        ));
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("Live TV Analytics: Cleaned up {$deleted} old records");
        }
        
        return intval($deleted);
    }
    
    /**
     * Get analytics data with privacy filters applied
     * 
     * Returns analytics data while respecting privacy settings.
     * 
     * @since 3.1.0
     * @param array $args Query arguments
     * @return array Filtered analytics data
     */
    public function get_privacy_compliant_data($args = array()) {
        $defaults = array(
            'period' => '7days',
            'anonymize_ip' => true,
            'exclude_admin' => true,
            'limit' => 1000
        );
        
        $args = wp_parse_args($args, $defaults);
        
        // Implementation would filter data according to privacy settings
        // This is a framework for privacy-compliant data retrieval
        
        return array();
    }
    
    /**
     * Export analytics data
     */
    public function export_analytics($format = 'csv', $period = '30days') {
        global $wpdb;
        
        if (!current_user_can('manage_options')) {
            return false;
        }
        
        $date_condition = $this->get_date_condition($period);
        
        $data = $wpdb->get_results("
            SELECT 
                c.name as channel_name,
                c.category,
                a.timestamp,
                a.watch_duration,
                a.device_type,
                a.country,
                a.ip_address
            FROM $this->table_name a
            JOIN {$wpdb->prefix}live_tv_channels c ON a.channel_id = c.id
            WHERE $date_condition
            ORDER BY a.timestamp DESC
        ", ARRAY_A);
        
        if ($format === 'csv') {
            return $this->export_to_csv($data);
        }
        
        return $data;
    }
    
    /**
     * Export data to CSV
     */
    private function export_to_csv($data) {
        if (empty($data)) {
            return false;
        }
        
        $filename = 'live-tv-analytics-' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Headers
        fputcsv($output, array_keys($data[0]));
        
        // Data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }
}

// Initialize analytics functionality
new LiveTVAnalytics();

// Schedule cleanup cron job if not already scheduled
if (!wp_next_scheduled('live_tv_analytics_cleanup')) {
    wp_schedule_event(time(), 'daily', 'live_tv_analytics_cleanup');
}

// Hook cleanup function to cron event
add_action('live_tv_analytics_cleanup', function() {
    $analytics = new LiveTVAnalytics();
    $analytics->cleanup_old_analytics(90); // Keep 90 days of data
});