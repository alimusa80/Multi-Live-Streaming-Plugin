<?php
if (!defined('ABSPATH')) {
    exit;
}

class LiveTVUserPreferences {
    
    private $favorites_table;
    private $history_table;
    
    public function __construct() {
        global $wpdb;
        $this->favorites_table = $wpdb->prefix . 'live_tv_favorites';
        $this->history_table = $wpdb->prefix . 'live_tv_watch_history';
        
        // AJAX handlers
        add_action('wp_ajax_toggle_favorite', array($this, 'toggle_favorite'));
        add_action('wp_ajax_nopriv_toggle_favorite', array($this, 'toggle_favorite_guest'));
        add_action('wp_ajax_get_user_favorites', array($this, 'get_user_favorites'));
        add_action('wp_ajax_nopriv_get_user_favorites', array($this, 'get_user_favorites_guest'));
        add_action('wp_ajax_get_watch_history', array($this, 'get_watch_history'));
        add_action('wp_ajax_clear_watch_history', array($this, 'clear_watch_history'));
        add_action('wp_ajax_add_to_history', array($this, 'add_to_history'));
        add_action('wp_ajax_nopriv_add_to_history', array($this, 'add_to_history_guest'));
    }
    
    /**
     * Create user preferences tables
     */
    public function create_user_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Favorites table
        $favorites_sql = "CREATE TABLE $this->favorites_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) DEFAULT NULL,
            session_id varchar(64) DEFAULT NULL,
            channel_id int(11) NOT NULL,
            added_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_channel (user_id, channel_id),
            UNIQUE KEY session_channel (session_id, channel_id),
            KEY channel_id (channel_id)
        ) $charset_collate;";
        
        // Watch history table
        $history_sql = "CREATE TABLE $this->history_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) DEFAULT NULL,
            session_id varchar(64) DEFAULT NULL,
            channel_id int(11) NOT NULL,
            watch_date datetime DEFAULT CURRENT_TIMESTAMP,
            watch_duration int(11) DEFAULT 0,
            completed_percentage decimal(5,2) DEFAULT 0.00,
            device_type varchar(50) DEFAULT 'desktop',
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY session_id (session_id),
            KEY channel_id (channel_id),
            KEY watch_date (watch_date)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($favorites_sql);
        dbDelta($history_sql);
    }
    
    /**
     * Toggle favorite for logged-in users
     */
    public function toggle_favorite() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'live_tv_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        if (!is_user_logged_in()) {
            wp_send_json_error('User not logged in');
        }
        
        $channel_id = intval($_POST['channel_id'] ?? 0);
        $user_id = get_current_user_id();
        
        if (!$channel_id) {
            wp_send_json_error('Invalid channel ID');
        }
        
        $result = $this->toggle_user_favorite($user_id, $channel_id);
        wp_send_json_success(array('is_favorite' => $result));
    }
    
    /**
     * Toggle favorite for guest users (session-based)
     */
    public function toggle_favorite_guest() {
        $channel_id = intval($_POST['channel_id'] ?? 0);
        $session_id = $this->get_session_id();
        
        if (!$channel_id) {
            wp_send_json_error('Invalid channel ID');
        }
        
        $result = $this->toggle_session_favorite($session_id, $channel_id);
        wp_send_json_success(array('is_favorite' => $result));
    }
    
    /**
     * Toggle favorite for a user
     */
    private function toggle_user_favorite($user_id, $channel_id) {
        global $wpdb;
        
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $this->favorites_table WHERE user_id = %d AND channel_id = %d",
            $user_id, $channel_id
        ));
        
        if ($existing) {
            $wpdb->delete($this->favorites_table, array('user_id' => $user_id, 'channel_id' => $channel_id));
            return false;
        } else {
            $wpdb->insert($this->favorites_table, array(
                'user_id' => $user_id,
                'channel_id' => $channel_id
            ));
            return true;
        }
    }
    
    /**
     * Toggle favorite for a session
     */
    private function toggle_session_favorite($session_id, $channel_id) {
        global $wpdb;
        
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $this->favorites_table WHERE session_id = %s AND channel_id = %d",
            $session_id, $channel_id
        ));
        
        if ($existing) {
            $wpdb->delete($this->favorites_table, array('session_id' => $session_id, 'channel_id' => $channel_id));
            return false;
        } else {
            $wpdb->insert($this->favorites_table, array(
                'session_id' => $session_id,
                'channel_id' => $channel_id
            ));
            return true;
        }
    }
    
    /**
     * Get user favorites
     */
    public function get_user_favorites() {
        if (!is_user_logged_in()) {
            wp_send_json_error('User not logged in');
        }
        
        $user_id = get_current_user_id();
        $favorites = $this->get_user_favorite_channels($user_id);
        
        wp_send_json_success($favorites);
    }
    
    /**
     * Get guest favorites
     */
    public function get_user_favorites_guest() {
        $session_id = $this->get_session_id();
        $favorites = $this->get_session_favorite_channels($session_id);
        
        wp_send_json_success($favorites);
    }
    
    /**
     * Get user's favorite channels
     */
    public function get_user_favorite_channels($user_id = null) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return array();
        }
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, f.added_date
             FROM $this->favorites_table f
             JOIN {$wpdb->prefix}live_tv_channels c ON f.channel_id = c.id
             WHERE f.user_id = %d AND c.is_active = 1
             ORDER BY f.added_date DESC",
            $user_id
        ));
    }
    
    /**
     * Get session's favorite channels
     */
    public function get_session_favorite_channels($session_id) {
        global $wpdb;
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, f.added_date
             FROM $this->favorites_table f
             JOIN {$wpdb->prefix}live_tv_channels c ON f.channel_id = c.id
             WHERE f.session_id = %s AND c.is_active = 1
             ORDER BY f.added_date DESC",
            $session_id
        ));
    }
    
    /**
     * Add to watch history
     */
    public function add_to_history() {
        if (is_user_logged_in() && !wp_verify_nonce($_POST['nonce'] ?? '', 'live_tv_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        $channel_id = intval($_POST['channel_id'] ?? 0);
        $duration = intval($_POST['duration'] ?? 0);
        $percentage = floatval($_POST['percentage'] ?? 0);
        
        if (!$channel_id) {
            wp_send_json_error('Invalid channel ID');
        }
        
        $this->record_watch_history($channel_id, $duration, $percentage);
        wp_send_json_success('History recorded');
    }
    
    /**
     * Add to history for guest users
     */
    public function add_to_history_guest() {
        $this->add_to_history();
    }
    
    /**
     * Record watch history
     */
    private function record_watch_history($channel_id, $duration = 0, $percentage = 0) {
        global $wpdb;
        
        $data = array(
            'channel_id' => $channel_id,
            'watch_duration' => $duration,
            'completed_percentage' => $percentage,
            'device_type' => $this->detect_device_type()
        );
        
        if (is_user_logged_in()) {
            $data['user_id'] = get_current_user_id();
        } else {
            $data['session_id'] = $this->get_session_id();
        }
        
        $wpdb->insert($this->history_table, $data);
    }
    
    /**
     * Get watch history
     */
    public function get_watch_history() {
        global $wpdb;
        
        $limit = intval($_POST['limit'] ?? 10);
        $limit = min($limit, 100); // Max 100 items
        
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $history = $wpdb->get_results($wpdb->prepare(
                "SELECT h.*, c.name as channel_name, c.logo_url, c.category
                 FROM $this->history_table h
                 JOIN {$wpdb->prefix}live_tv_channels c ON h.channel_id = c.id
                 WHERE h.user_id = %d AND c.is_active = 1
                 ORDER BY h.watch_date DESC
                 LIMIT %d",
                $user_id, $limit
            ));
        } else {
            $session_id = $this->get_session_id();
            $history = $wpdb->get_results($wpdb->prepare(
                "SELECT h.*, c.name as channel_name, c.logo_url, c.category
                 FROM $this->history_table h
                 JOIN {$wpdb->prefix}live_tv_channels c ON h.channel_id = c.id
                 WHERE h.session_id = %s AND c.is_active = 1
                 ORDER BY h.watch_date DESC
                 LIMIT %d",
                $session_id, $limit
            ));
        }
        
        wp_send_json_success($history);
    }
    
    /**
     * Clear watch history
     */
    public function clear_watch_history() {
        global $wpdb;
        
        if (is_user_logged_in()) {
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'live_tv_nonce')) {
                wp_send_json_error('Invalid nonce');
            }
            
            $user_id = get_current_user_id();
            $wpdb->delete($this->history_table, array('user_id' => $user_id));
        } else {
            $session_id = $this->get_session_id();
            $wpdb->delete($this->history_table, array('session_id' => $session_id));
        }
        
        wp_send_json_success('History cleared');
    }
    
    /**
     * Get personalized channel recommendations
     */
    public function get_recommendations($user_id = null, $limit = 5) {
        global $wpdb;
        
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            // For guests, return popular channels
            return $wpdb->get_results($wpdb->prepare(
                "SELECT c.*, COUNT(a.id) as popularity_score
                 FROM {$wpdb->prefix}live_tv_channels c
                 LEFT JOIN {$wpdb->prefix}live_tv_analytics a ON c.id = a.channel_id
                 WHERE c.is_active = 1
                 GROUP BY c.id
                 ORDER BY popularity_score DESC, c.sort_order ASC
                 LIMIT %d",
                $limit
            ));
        }
        
        // Get user's favorite categories
        $favorite_categories = $wpdb->get_results($wpdb->prepare(
            "SELECT c.category, COUNT(*) as count
             FROM $this->favorites_table f
             JOIN {$wpdb->prefix}live_tv_channels c ON f.channel_id = c.id
             WHERE f.user_id = %d
             GROUP BY c.category
             ORDER BY count DESC
             LIMIT 3",
            $user_id
        ));
        
        if (empty($favorite_categories)) {
            // If no favorites, recommend popular channels
            return $wpdb->get_results($wpdb->prepare(
                "SELECT c.*, COUNT(a.id) as popularity_score
                 FROM {$wpdb->prefix}live_tv_channels c
                 LEFT JOIN {$wpdb->prefix}live_tv_analytics a ON c.id = a.channel_id
                 WHERE c.is_active = 1
                 GROUP BY c.id
                 ORDER BY popularity_score DESC, c.sort_order ASC
                 LIMIT %d",
                $limit
            ));
        }
        
        // Recommend based on favorite categories
        $categories = array_column($favorite_categories, 'category');
        $category_placeholders = str_repeat(',%s', count($categories) - 1);
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, COUNT(a.id) as popularity_score
             FROM {$wpdb->prefix}live_tv_channels c
             LEFT JOIN {$wpdb->prefix}live_tv_analytics a ON c.id = a.channel_id
             LEFT JOIN $this->favorites_table f ON (c.id = f.channel_id AND f.user_id = %d)
             WHERE c.is_active = 1 
             AND c.category IN (%s$category_placeholders)
             AND f.id IS NULL
             GROUP BY c.id
             ORDER BY popularity_score DESC, c.sort_order ASC
             LIMIT %d",
            array_merge(array($user_id), $categories, array($limit))
        ));
    }
    
    /**
     * Check if channel is favorite
     */
    public function is_favorite($channel_id, $user_id = null) {
        global $wpdb;
        
        if ($user_id) {
            return $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $this->favorites_table WHERE user_id = %d AND channel_id = %d",
                $user_id, $channel_id
            )) ? true : false;
        } elseif (is_user_logged_in()) {
            return $this->is_favorite($channel_id, get_current_user_id());
        } else {
            $session_id = $this->get_session_id();
            return $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $this->favorites_table WHERE session_id = %s AND channel_id = %d",
                $session_id, $channel_id
            )) ? true : false;
        }
    }
    
    /**
     * Get session ID
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
}

new LiveTVUserPreferences();