<?php
/**
 * Admin Interface Management Class
 * 
 * Handles the WordPress admin interface for the Live TV Streaming Plugin,
 * including menu creation, page routing, and AJAX operations.
 * 
 * @package LiveTVStreaming
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

/**
 * LiveTVAdminPages class for managing admin interface
 * 
 * This class creates and manages all admin pages, handles AJAX requests
 * for channel management, and provides the administrative interface.
 * 
 * @since 1.0.0
 */
class LiveTVAdminPages {
    
    /**
     * Initialize admin interface functionality
     * 
     * Sets up admin menu and registers AJAX handlers for
     * channel management operations.
     * 
     * @since 1.0.0
     */
    public function __construct() {
        // Register admin menu creation
        add_action('admin_menu', array($this, 'admin_menu'));
        
        // Register AJAX handlers for channel management
        add_action('wp_ajax_save_channel', array($this, 'save_channel'));
        add_action('wp_ajax_delete_channel', array($this, 'delete_channel'));
        add_action('wp_ajax_get_channels', array($this, 'get_channels'));
        
    }
    
    /**
     * Create admin menu structure
     * 
     * Sets up the complete admin menu with main page and subpages
     * for dashboard, channels, analytics, user data, and settings.
     * 
     * @since 1.0.0
     */
    public function admin_menu() {
        // Main menu page with dashboard icon
        add_menu_page(
            __('Live TV Streaming Dashboard', 'live-tv-streaming'), // Page title
            __('Live TV', 'live-tv-streaming'),                      // Menu title
            'manage_options',                                         // Capability
            'live-tv-dashboard',                                      // Menu slug
            array($this, 'dashboard_page'),                          // Callback
            'dashicons-video-alt2',                                  // Icon
            30                                                       // Position
        );
        
        // Dashboard submenu (duplicate of main page)
        add_submenu_page(
            'live-tv-dashboard',
            __('Dashboard', 'live-tv-streaming'),
            __('Dashboard', 'live-tv-streaming'),
            'manage_options',
            'live-tv-dashboard',
            array($this, 'dashboard_page')
        );
        
        // Channel management submenu
        add_submenu_page(
            'live-tv-dashboard',
            __('Manage Channels', 'live-tv-streaming'),
            __('Channels', 'live-tv-streaming'),
            'manage_options',
            'live-tv-channels',
            array($this, 'admin_page')
        );
        
        // Analytics and reporting submenu
        add_submenu_page(
            'live-tv-dashboard',
            __('Analytics & Reports', 'live-tv-streaming'),
            __('Analytics', 'live-tv-streaming'),
            'manage_options',
            'live-tv-analytics',
            array($this, 'analytics_page')
        );
        
        // User data and preferences submenu
        add_submenu_page(
            'live-tv-dashboard',
            __('User Preferences & Data', 'live-tv-streaming'),
            __('User Data', 'live-tv-streaming'),
            'manage_options',
            'live-tv-users',
            array($this, 'users_page')
        );
        
        // Player customization submenu
        add_submenu_page(
            'live-tv-dashboard',
            __('Player Customization', 'live-tv-streaming'),
            __('Customization', 'live-tv-streaming'),
            'manage_options',
            'live-tv-customization',
            array($this, 'customization_page')
        );
        
        // Plugin settings submenu
        add_submenu_page(
            'live-tv-dashboard',
            __('Plugin Settings', 'live-tv-streaming'),
            __('Settings', 'live-tv-streaming'),
            'manage_options',
            'live-tv-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Display the main dashboard page
     * 
     * @since 1.0.0
     */
    public function dashboard_page() {
        $this->load_template('dashboard');
    }
    
    /**
     * Display the channel management page
     * 
     * @since 1.0.0
     */
    public function admin_page() {
        $this->load_template('admin-page');
    }
    
    /**
     * Display the analytics and reports page
     * 
     * @since 1.0.0
     */
    public function analytics_page() {
        $this->load_template('analytics-page');
    }
    
    /**
     * Display the user data and preferences page
     * 
     * @since 1.0.0
     */
    public function users_page() {
        $this->load_template('users-page');
    }
    
    /**
     * Display the player customization page
     * 
     * @since 1.0.0
     */
    public function customization_page() {
        $this->load_template('player-customization-page');
    }
    
    /**
     * Display the plugin settings page
     * 
     * @since 1.0.0
     */
    public function settings_page() {
        $this->load_template('settings-page');
    }
    
    /**
     * Load a template file safely
     * 
     * @since 3.1.0
     * @param string $template_name Name of template file (without .php)
     */
    private function load_template($template_name) {
        $template_path = LIVE_TV_PLUGIN_PATH . 'includes/templates/' . $template_name . '.php';
        
        if (file_exists($template_path)) {
            include $template_path;
        } else {
            echo '<div class="notice notice-error"><p>';
            printf(
                __('Template file "%s" not found.', 'live-tv-streaming'),
                esc_html($template_name)
            );
            echo '</p></div>';
            error_log("Live TV Plugin: Missing template - {$template_path}");
        }
    }
    
    /**
     * Handle AJAX request to retrieve channels
     * 
     * Processes admin requests for channel data with proper security
     * verification and error handling.
     * 
     * @since 1.0.0
     */
    public function get_channels() {
        // Verify user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions.', 'live-tv-streaming')
            ));
            return;
        }
        
        // Check nonce for security
        if (!check_ajax_referer('live_tv_admin_nonce', 'nonce', false)) {
            wp_send_json_error(array(
                'message' => __('Security verification failed.', 'live-tv-streaming')
            ));
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'live_tv_channels';
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
        
        if (!$table_exists) {
            error_log('Live TV channels table does not exist: ' . $table_name);
            
            // Try to create the table
            require_once LIVE_TV_PLUGIN_PATH . 'includes/pages/database.php';
            $database = new LiveTVDatabase();
            $database->create_tables();
            
            // Check again if table was created
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
            
            if (!$table_exists) {
                wp_send_json_error(array(
                    'message' => __('Database table missing. Please deactivate and reactivate the plugin.', 'live-tv-streaming')
                ));
                return;
            }
        }
        
        error_log('Querying table: ' . $table_name);
        
        $channels = $wpdb->get_results("SELECT * FROM $table_name ORDER BY sort_order ASC, name ASC", ARRAY_A);
        
        if ($wpdb->last_error) {
            error_log('Database error in get_channels: ' . $wpdb->last_error);
            wp_send_json_error(array(
                'message' => __('Database error occurred.', 'live-tv-streaming'),
                'error' => $wpdb->last_error
            ));
            return;
        }
        
        // Ensure channels is an array
        if (!is_array($channels)) {
            $channels = array();
        }
        
        error_log('Found ' . count($channels) . ' channels');
        
        wp_send_json_success($channels);
    }
    
    /**
     * Handle AJAX request to save channel data
     * 
     * Processes channel creation and updates with comprehensive
     * validation and sanitization.
     * 
     * @since 1.0.0
     */
    public function save_channel() {
        $error_handler = LiveTVErrorHandler::getInstance();
        
        // Verify user permissions first
        if (!current_user_can('manage_options')) {
            $error = $error_handler->handle_auth_error('insufficient_permissions', array(
                'action' => 'save_channel',
                'user_id' => get_current_user_id()
            ));
            wp_send_json_error(array(
                'message' => $error->get_error_message()
            ));
            return;
        }
        
        // Verify nonce for security
        if (!check_ajax_referer('live_tv_admin_nonce', 'nonce', false)) {
            $error = $error_handler->handle_auth_error('invalid_nonce', array(
                'action' => 'save_channel',
                'referer' => wp_get_referer()
            ));
            wp_send_json_error(array(
                'message' => $error->get_error_message()
            ));
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'live_tv_channels';
        
        // Validate required fields
        if (empty($_POST['channel_name']) || empty($_POST['stream_url'])) {
            wp_send_json_error(array(
                'message' => __('Channel name and stream URL are required.', 'live-tv-streaming')
            ));
            return;
        }
        
        // Check for duplicate channel names (only for new channels)
        $channel_name = sanitize_text_field($_POST['channel_name']);
        $channel_id = intval($_POST['channel_id'] ?? 0);
        
        if ($channel_id === 0) {
            $duplicate_check = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}live_tv_channels WHERE name = %s",
                $channel_name
            ));
            
            if ($duplicate_check) {
                wp_send_json_error(array(
                    'message' => __('A channel with this name already exists. Please choose a different name.', 'live-tv-streaming')
                ));
                return;
            }
        }
        
        $channel_data = array(
            'name' => sanitize_text_field($_POST['channel_name']),
            'description' => sanitize_textarea_field($_POST['channel_description']),
            'stream_url' => esc_url_raw($_POST['stream_url']),
            'logo_url' => esc_url_raw($_POST['logo_url']),
            'category' => sanitize_text_field($_POST['category']),
            'sort_order' => intval($_POST['sort_order']),
            'is_active' => intval($_POST['is_active'])
        );
        
        $channel_id = intval($_POST['channel_id'] ?? 0);
        $edit_mode = intval($_POST['edit_mode'] ?? 0);
        
        // Enhanced debugging
        error_log('Live TV Channel Save Debug - Raw POST data: ' . print_r([
            'channel_id' => $_POST['channel_id'] ?? 'NOT_SET',
            'edit_mode' => $_POST['edit_mode'] ?? 'NOT_SET',
            'channel_name' => $channel_data['name']
        ], true));
        
        // Additional validation for edit mode
        if ($channel_id > 0 && $edit_mode === 0) {
            // If we have a channel_id but edit_mode is 0, assume it's an edit
            $edit_mode = 1;
            error_log('Live TV: Auto-corrected edit_mode to 1 due to valid channel_id');
        }
        
        // Log for debugging
        error_log('Live TV Channel Save - Final values: ID=' . $channel_id . ', Edit Mode=' . $edit_mode . ', Channel Name=' . $channel_data['name']);
        
        // Determine if this is an update or insert operation
        $is_update = false;
        $existing_channel = null;
        
        if ($channel_id > 0) {
            // Check if channel exists
            $existing_channel = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE id = %d",
                $channel_id
            ));
            
            if ($existing_channel) {
                $is_update = true;
                error_log('Live TV: Found existing channel for update - ID=' . $channel_id . ', Name=' . $existing_channel->name);
            } else {
                // Channel ID provided but doesn't exist - return error
                error_log('Live TV Error: Channel ID ' . $channel_id . ' provided but channel does not exist in database');
                wp_send_json_error(array(
                    'message' => __('Channel not found. It may have been deleted. Please refresh the page.', 'live-tv-streaming'),
                    'debug_info' => array(
                        'channel_id' => $channel_id,
                        'edit_mode' => $edit_mode
                    )
                ));
                return;
            }
        }
        
        if ($is_update && $existing_channel) {
            // Update existing channel
            $result = $wpdb->update(
                $table_name, 
                $channel_data, 
                array('id' => $channel_id),
                array('%s', '%s', '%s', '%s', '%s', '%d', '%d'),
                array('%d')
            );
            
            $action_performed = 'updated';
            $final_channel_id = $channel_id;
            
            error_log('Updated channel ID ' . $channel_id . ' - Rows affected: ' . $result);
        } else {
            // Insert new channel
            $result = $wpdb->insert(
                $table_name, 
                $channel_data,
                array('%s', '%s', '%s', '%s', '%s', '%d', '%d')
            );
            
            $action_performed = 'created';
            $final_channel_id = $wpdb->insert_id;
            
            error_log('Created new channel - New ID: ' . $final_channel_id);
        }
        
        // Check result and provide detailed feedback
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => sprintf(
                    __('Channel %s successfully.', 'live-tv-streaming'),
                    $action_performed
                ),
                'data' => $channel_data,
                'action' => $action_performed,
                'channel_id' => $final_channel_id
            ));
        } else {
            // Log the error for debugging
            error_log('Live TV Channel Save Error: ' . $wpdb->last_error);
            
            wp_send_json_error(array(
                'message' => sprintf(
                    __('Failed to %s channel. Please try again.', 'live-tv-streaming'),
                    $action_performed === 'updated' ? 'update' : 'create'
                ),
                'error' => $wpdb->last_error,
                'debug_info' => array(
                    'channel_id' => $channel_id,
                    'edit_mode' => $edit_mode,
                    'action' => $action_performed
                )
            ));
        }
    }
    
    /**
     * Handle AJAX request to delete a channel
     * 
     * Permanently removes a channel from the database with
     * proper security checks and error handling.
     * 
     * @since 1.0.0
     */
    public function delete_channel() {
        // Verify user permissions first
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions.', 'live-tv-streaming')
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
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'live_tv_channels';
        
        $result = $wpdb->delete(
            $table_name, 
            array('id' => intval($_POST['channel_id'])),
            array('%d')
        );
        
        // Provide appropriate feedback
        if ($result !== false) {
            wp_send_json_success(array(
                'message' => __('Channel deleted successfully.', 'live-tv-streaming')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to delete channel.', 'live-tv-streaming'),
                'error' => $wpdb->last_error
            ));
        }
    }
    
    
    /**
     * Enqueue admin scripts and styles for Live TV pages
     * 
     * Loads the necessary CSS and JavaScript files for the admin interface.
     * 
     * @since 1.0.0
     */
    public function enqueue_admin_assets() {
        // Only enqueue on Live TV admin pages
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'live-tv') === false) {
            return;
        }
        
        // Enqueue admin CSS with Premium Streaming Blue theme
        wp_enqueue_style(
            'live-tv-admin-css',
            LIVE_TV_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            LIVE_TV_VERSION
        );
        
        // Enqueue main admin JavaScript
        wp_enqueue_script(
            'live-tv-admin',
            LIVE_TV_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            LIVE_TV_VERSION,
            true
        );
        
        // Localize admin script with necessary data
        $localize_data = array(
            'nonce' => wp_create_nonce('live_tv_admin_nonce'),
            'ajax_url' => admin_url('admin-ajax.php'),
            'plugin_url' => LIVE_TV_PLUGIN_URL
        );
        
        wp_localize_script('live-tv-admin', 'liveTVAdmin', $localize_data);
    }
}

// Initialize the admin interface
$live_tv_admin = new LiveTVAdminPages();

// Hook the enqueue function to admin_enqueue_scripts
add_action('admin_enqueue_scripts', array($live_tv_admin, 'enqueue_admin_assets'));