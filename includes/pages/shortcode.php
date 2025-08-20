<?php
/**
 * Shortcode Handler Class
 * 
 * Manages the [live_tv_player] shortcode functionality and provides
 * AJAX endpoints for frontend channel data retrieval.
 * 
 * @package LiveTVStreaming
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

/**
 * LiveTVShortcode class for handling shortcode display and AJAX operations
 * 
 * This class processes the live TV player shortcode and handles
 * frontend AJAX requests for streaming data.
 * 
 * @since 1.0.0
 */
class LiveTVShortcode {
    
    /**
     * Initialize shortcode functionality
     * 
     * Registers the shortcode and sets up AJAX handlers for
     * both logged-in and non-logged-in users.
     * 
     * @since 1.0.0
     */
    public function __construct() {
        // Register the main shortcode
        add_shortcode('live_tv_player', array($this, 'live_tv_shortcode'));
        
        // Register AJAX handlers for channel data retrieval
        add_action('wp_ajax_nopriv_get_stream_url', array($this, 'get_stream_url'));
        add_action('wp_ajax_get_stream_url', array($this, 'get_stream_url'));
    }
    
    /**
     * Process the [live_tv_player] shortcode
     * 
     * Generates the HTML output for the live TV player interface
     * with customizable attributes for width, height, category filtering, and autoplay.
     * 
     * @since 1.0.0
     * @param array $atts Shortcode attributes
     * @return string HTML output for the player
     */
    public function live_tv_shortcode($atts) {
        // Define default shortcode attributes
        $default_atts = array(
            'width' => '100%',
            'height' => '400px', 
            'category' => '',
            'autoplay' => 'false',
            'show_controls' => 'true',
            'responsive' => 'true'
        );
        
        // Merge with user-provided attributes
        $atts = shortcode_atts($default_atts, $atts, 'live_tv_player');
        
        // Sanitize attributes for security
        $atts = array_map('sanitize_text_field', $atts);
        
        // Enqueue gesture help script properly
        $this->enqueue_gesture_scripts($atts);
        
        // Generate and return the player HTML
        ob_start();
        include LIVE_TV_PLUGIN_PATH . 'includes/templates/shortcode-template.php';
        return ob_get_clean();
    }
    
    /**
     * Handle AJAX request for stream URLs and channel data
     * 
     * Processes frontend requests for channel information, with optional
     * category filtering. Includes proper nonce verification and data sanitization.
     * 
     * @since 1.0.0
     */
    public function get_stream_url() {
        // Verify nonce for security (only for logged-in users)
        if (is_user_logged_in()) {
            if (!wp_verify_nonce($_POST['nonce'] ?? '', 'live_tv_nonce')) {
                wp_send_json_error(array(
                    'message' => __('Security verification failed.', 'live-tv-streaming')
                ));
                return;
            }
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'live_tv_channels';
        
        // Check if category filter is requested
        if (isset($_POST['category']) && !empty($_POST['category'])) {
            $category = sanitize_text_field($_POST['category']);
            
            // Get channels filtered by category
            $channels = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE is_active = 1 AND category = %s ORDER BY sort_order ASC, name ASC",
                $category
            ), ARRAY_A);
        } else {
            // Get all active channels
            $channels = $wpdb->get_results(
                "SELECT * FROM {$table_name} WHERE is_active = 1 ORDER BY sort_order ASC, name ASC", 
                ARRAY_A
            );
        }
        
        // Check for database errors
        if ($wpdb->last_error) {
            wp_send_json_error(array(
                'message' => __('Database error occurred.', 'live-tv-streaming'),
                'error' => $wpdb->last_error
            ));
            return;
        }
        
        // Sanitize channel data before sending
        $sanitized_channels = array();
        foreach ($channels as $channel) {
            $sanitized_channels[] = array(
                'id' => intval($channel['id']),
                'name' => esc_html($channel['name']),
                'description' => esc_html($channel['description'] ?? ''),
                'stream_url' => esc_url($channel['stream_url']),
                'logo_url' => esc_url($channel['logo_url'] ?? ''),
                'category' => esc_html($channel['category'] ?? ''),
                'sort_order' => intval($channel['sort_order'] ?? 0)
            );
        }
        
        // Return successful response with channel data
        wp_send_json_success($sanitized_channels);
    }
    
    /**
     * Get available shortcode attributes and their descriptions
     * 
     * Provides information about available shortcode parameters
     * for documentation and help purposes.
     * 
     * @since 3.1.0
     * @return array Array of attributes and descriptions
     */
    public function get_shortcode_attributes() {
        return array(
            'width' => __('Player width (default: 100%)', 'live-tv-streaming'),
            'height' => __('Player height (default: 400px)', 'live-tv-streaming'),
            'category' => __('Filter channels by category', 'live-tv-streaming'),
            'autoplay' => __('Enable autoplay (true/false, default: false)', 'live-tv-streaming'),
            'show_controls' => __('Show player controls (true/false, default: true)', 'live-tv-streaming'),
            'responsive' => __('Enable responsive design (true/false, default: true)', 'live-tv-streaming')
        );
    }
    
    /**
     * Enqueue gesture control scripts properly
     * 
     * @since 3.1.0
     * @param array $atts Shortcode attributes
     */
    private function enqueue_gesture_scripts($atts) {
        static $script_enqueued = false;
        
        // Only enqueue once per page
        if ($script_enqueued) {
            return;
        }
        
        $script_enqueued = true;
        
        // Enqueue the gesture controls script
        wp_enqueue_script(
            'live-tv-gesture-controls',
            LIVE_TV_PLUGIN_URL . 'assets/js/gesture-controls.js',
            array('jquery'),
            LIVE_TV_VERSION,
            true // Load in footer
        );
        
        // Localize script with autoplay setting
        wp_localize_script('live-tv-gesture-controls', 'liveTVGestureData', array(
            'autoplay' => $atts['autoplay'],
            'nonce' => wp_create_nonce('live_tv_gesture_nonce')
        ));
        
        // Set global autoplay variable for immediate use
        wp_add_inline_script('live-tv-gesture-controls', 
            'window.liveTVAutoplay = "' . esc_js($atts['autoplay']) . '";', 
            'before'
        );
    }
}

// Initialize the shortcode functionality
new LiveTVShortcode();