<?php
/**
 * Plugin Name: Live TV Streaming Pro
 * Plugin URI: https://alimusa.so/
 * Description: Professional live TV streaming solution with multi-channel support, mobile optimization, Google Cast integration, and advanced admin controls.
 * Version: 3.1.1
 * Author: Ali Musa
 * Author URI: https://alimusa.so/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: live-tv-streaming
 * Domain Path: /languages
 * Requires at least: 5.6
 * Tested up to: 6.7
 * Requires PHP: 7.4
 * Network: false
 * Update URI: https://alimusa.so/
 *
 * @package LiveTVStreaming
 * @since 1.0.0
 */

if (!defined('ABSPATH')) exit('Direct access denied.');

if (!defined('LIVE_TV_PLUGIN_URL')) define('LIVE_TV_PLUGIN_URL', plugin_dir_url(__FILE__));
if (!defined('LIVE_TV_PLUGIN_PATH')) define('LIVE_TV_PLUGIN_PATH', plugin_dir_path(__FILE__));
if (!defined('LIVE_TV_VERSION')) define('LIVE_TV_VERSION', '3.1.1');
if (!defined('LIVE_TV_MIN_WP_VERSION')) define('LIVE_TV_MIN_WP_VERSION', '5.6');
if (!defined('LIVE_TV_MIN_PHP_VERSION')) define('LIVE_TV_MIN_PHP_VERSION', '7.4');

/**
 * Main plugin class that handles initialization, dependencies, and core functionality
 * @package LiveTVStreaming
 * @since 1.0.0
 */
class LiveTVStreamingPlugin {
    
    /**
     * Database handler instance
     * @var LiveTVDatabase
     * @since 1.0.0
     */
    private $database;
    
    /**
     * Plugin identifier slug
     * @var string
     * @since 1.0.0
     */
    private $plugin_slug = 'live-tv-streaming';
    
    /**
     * Plugin updater instance
     * @var LiveTVPluginUpdater
     * @since 3.2.0
     */
    private $updater;
    
    /**
     * Initialize the plugin and set up all hooks and dependencies
     * @since 1.0.0
     */
    public function __construct() {
        // Check WordPress and PHP version compatibility before proceeding
        if (!$this->check_environment()) {
            return;
        }
        
        // Initialize core WordPress hooks
        $this->init_hooks();
        
        // Load all plugin dependencies and components
        $this->load_dependencies();
        
        // Set up plugin lifecycle hooks
        $this->register_lifecycle_hooks();
    }
    
    /**
     * Initialize all WordPress action and filter hooks
     * 
     * @since 3.1.0
     */
    private function init_hooks() {
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        
        // Admin interface enhancements
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'plugin_action_links'));
        add_action('admin_notices', array($this, 'admin_notices'));
        
        // Gutenberg block support
        add_action('init', array($this, 'register_gutenberg_block'));
    }
    
    /**
     * Register plugin activation and deactivation hooks
     * 
     * @since 3.1.0
     */
    private function register_lifecycle_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Check if the current environment meets plugin requirements
     * 
     * Validates WordPress and PHP version requirements to ensure
     * the plugin can function properly without conflicts.
     * 
     * @since 1.0.0
     * @return bool True if environment is compatible, false otherwise
     */
    private function check_environment() {
        // Check WordPress version
        if (version_compare(get_bloginfo('version'), LIVE_TV_MIN_WP_VERSION, '<')) {
            add_action('admin_notices', array($this, 'wp_version_notice'));
            return false;
        }
        
        // Check PHP version
        if (version_compare(PHP_VERSION, LIVE_TV_MIN_PHP_VERSION, '<')) {
            add_action('admin_notices', array($this, 'php_version_notice'));
            return false;
        }
        
        return true;
    }
    
    /**
     * Display admin notice for WordPress version incompatibility
     * 
     * Shows an error notice when the current WordPress version
     * is below the minimum required version.
     * 
     * @since 1.0.0
     */
    public function wp_version_notice() {
        $message = sprintf(
            __('Live TV Streaming Pro requires WordPress version %s or higher. You are running version %s.', 'live-tv-streaming'),
            LIVE_TV_MIN_WP_VERSION,
            get_bloginfo('version')
        );
        echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
    }
    
    /**
     * Display admin notice for PHP version incompatibility
     * 
     * Shows an error notice when the current PHP version
     * is below the minimum required version.
     * 
     * @since 1.0.0
     */
    public function php_version_notice() {
        $message = sprintf(
            __('Live TV Streaming Pro requires PHP version %s or higher. You are running version %s.', 'live-tv-streaming'),
            LIVE_TV_MIN_PHP_VERSION,
            PHP_VERSION
        );
        echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
    }
    
    /**
     * Add custom action links to plugin list page
     * 
     * Adds "Settings" and "Pro Features" links to the plugin
     * row on the WordPress plugins page for easy access.
     * 
     * @since 1.0.0
     * @param array $links Existing plugin action links
     * @return array Modified links with custom additions
     */
    public function plugin_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=live-tv-channels') . '">' . __('Settings', 'live-tv-streaming') . '</a>';
        array_unshift($links, $settings_link);
        
        $pro_link = '<a href="https://alimusa.so/" target="_blank" style="color: #46b450; font-weight: bold;">' . __('Pro Features', 'live-tv-streaming') . '</a>';
        array_push($links, $pro_link);
        
        return $links;
    }
    
    /**
     * Display administrative notices and messages
     * 
     * Handles display of activation notices and other important
     * messages to administrators in the WordPress admin area.
     * 
     * @since 1.0.0
     */
    public function admin_notices() {
        // Show welcome notice on activation
        if (get_transient('live_tv_activation_notice')) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p>
                    <?php _e('<strong>Live TV Streaming Pro</strong> has been activated successfully!', 'live-tv-streaming'); ?>
                    <a href="<?php echo admin_url('admin.php?page=live-tv-channels'); ?>" class="button button-primary" style="margin-left: 10px;">
                        <?php _e('Get Started', 'live-tv-streaming'); ?>
                    </a>
                </p>
            </div>
            <?php
            delete_transient('live_tv_activation_notice');
        }
    }
    
    /**
     * Load all plugin dependencies and components
     * 
     * Includes all necessary files and initializes core components
     * in the correct order to avoid dependency conflicts.
     * 
     * @since 1.0.0
     */
    private function load_dependencies() {
        // Load error handler first for comprehensive error management
        require_once LIVE_TV_PLUGIN_PATH . 'includes/class-error-handler.php';
        
        // Load plugin updater for automatic updates
        require_once LIVE_TV_PLUGIN_PATH . 'includes/class-plugin-updater.php';
        
        // Load update notifications
        require_once LIVE_TV_PLUGIN_PATH . 'includes/class-update-notifications.php';
        
        // Load core database functionality
        require_once LIVE_TV_PLUGIN_PATH . 'includes/pages/database.php';
        $this->database = new LiveTVDatabase();
        
        // Load professional feature components
        $this->load_professional_components();
        
        // Load admin interface (only in admin area for performance)
        if (is_admin()) {
            require_once LIVE_TV_PLUGIN_PATH . 'includes/admin/admin-pages.php';
        }
        
        // Load frontend shortcode functionality
        require_once LIVE_TV_PLUGIN_PATH . 'includes/pages/shortcode.php';
        
        // Load REST API
        require_once LIVE_TV_PLUGIN_PATH . 'includes/api/rest-api.php';
        
        // Load advanced analytics stream
        require_once LIVE_TV_PLUGIN_PATH . 'includes/api/analytics-stream.php';
        
        // Load M3U importer
        require_once LIVE_TV_PLUGIN_PATH . 'includes/pages/m3u-importer.php';
        
        // Initialize plugin updater
        $this->init_updater();
    }
    
    /**
     * Load professional feature components
     * 
     * Includes analytics, user preferences, and playlist management
     * components for enhanced functionality.
     * 
     * @since 3.1.0
     */
    private function load_professional_components() {
        $components = array(
            'analytics' => 'includes/pages/analytics.php',
            'user-preferences' => 'includes/pages/user-preferences.php',
            'playlist-manager' => 'includes/pages/playlist-manager.php'
        );
        
        foreach ($components as $name => $file_path) {
            $full_path = LIVE_TV_PLUGIN_PATH . $file_path;
            if (file_exists($full_path)) {
                require_once $full_path;
            } else {
                error_log("Live TV Plugin: Missing component file - {$file_path}");
            }
        }
    }
    
    /**
     * Initialize plugin functionality after WordPress is loaded
     * 
     * Sets up database tables and performs other initialization tasks
     * that require WordPress to be fully loaded.
     * 
     * @since 1.0.0
     */
    public function init() {
        // Initialize database tables
        if ($this->database) {
            $this->database->create_tables();
        }
        
        // Additional initialization tasks can be added here
    }
    
    /**
     * Load plugin translation files
     * 
     * Makes the plugin ready for translation by loading the appropriate
     * language files from the /languages directory.
     * 
     * @since 1.0.0
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'live-tv-streaming', 
            false, 
            dirname(plugin_basename(__FILE__)) . '/languages/'
        );
    }
    
    /**
     * Register Gutenberg block for Live TV Player
     * 
     * @since 3.1.0
     */
    public function register_gutenberg_block() {
        // Only register if Gutenberg is available
        if (!function_exists('register_block_type')) {
            return;
        }
        
        // Register the block
        register_block_type('live-tv-streaming/player', array(
            'editor_script' => 'live-tv-block-editor',
            'editor_style'  => 'live-tv-block-editor-style',
            'style'         => 'live-tv-block-style',
            'render_callback' => array($this, 'render_gutenberg_block'),
            'attributes' => array(
                'width' => array(
                    'type' => 'string',
                    'default' => '100%'
                ),
                'height' => array(
                    'type' => 'string', 
                    'default' => '400px'
                ),
                'category' => array(
                    'type' => 'string',
                    'default' => ''
                ),
                'autoplay' => array(
                    'type' => 'string',
                    'default' => 'false'
                ),
                'show_controls' => array(
                    'type' => 'string',
                    'default' => 'true'
                ),
                'responsive' => array(
                    'type' => 'string',
                    'default' => 'true'
                )
            )
        ));
        
        // Enqueue block editor assets
        add_action('enqueue_block_editor_assets', array($this, 'enqueue_block_editor_assets'));
    }
    
    /**
     * Render callback for Gutenberg block
     * 
     * @since 3.1.0
     * @param array $attributes Block attributes
     * @return string Block HTML output
     */
    public function render_gutenberg_block($attributes) {
        // Include shortcode functionality if not already loaded
        if (!class_exists('LiveTVShortcode')) {
            require_once LIVE_TV_PLUGIN_PATH . 'includes/pages/shortcode.php';
        }
        
        // Create shortcode instance and render
        $shortcode = new LiveTVShortcode();
        return $shortcode->live_tv_shortcode($attributes);
    }
    
    /**
     * Enqueue block editor assets
     * 
     * @since 3.1.0
     */
    public function enqueue_block_editor_assets() {
        wp_enqueue_script(
            'live-tv-block-editor',
            LIVE_TV_PLUGIN_URL . 'assets/js/block-editor.js',
            array('wp-blocks', 'wp-element', 'wp-editor', 'wp-components', 'wp-i18n'),
            LIVE_TV_VERSION,
            true
        );
        
        wp_enqueue_style(
            'live-tv-block-editor-style',
            LIVE_TV_PLUGIN_URL . 'assets/css/block-editor.css',
            array('wp-edit-blocks'),
            LIVE_TV_VERSION
        );
        
        // Localize script with plugin data
        wp_localize_script('live-tv-block-editor', 'liveTVBlock', array(
            'title' => __('Live TV Player', 'live-tv-streaming'),
            'description' => __('Embed a live TV streaming player', 'live-tv-streaming'),
            'icon' => 'video-alt3',
            'category' => 'media'
        ));
    }
    
    /**
     * Handle plugin activation
     * 
     * Performs all necessary setup tasks when the plugin is activated,
     * including database table creation, default option setup, and
     * environment validation.
     * 
     * @since 1.0.0
     */
    public function activate() {
        // Validate environment before activation
        if (!$this->check_environment()) {
            wp_die(
                __('Plugin activation failed due to environment requirements.', 'live-tv-streaming'),
                __('Activation Error', 'live-tv-streaming'),
                array('response' => 200, 'back_link' => true)
            );
        }
        
        // Create core database tables
        if ($this->database) {
            $this->database->create_tables();
        }
        
        // Initialize professional feature tables
        $this->create_professional_tables();
        
        // Configure default plugin options
        $this->set_default_options();
        
        // Set activation notice for admin
        set_transient('live_tv_activation_notice', true, 60);
        
        // Refresh WordPress rewrite rules
        flush_rewrite_rules();
        
        // Log activation for debugging
        error_log('Live TV Streaming Plugin activated successfully');
    }
    
    /**
     * Create database tables for professional features
     * 
     * Initializes tables for analytics, user preferences, and playlist
     * management if the corresponding classes are available.
     * 
     * @since 3.1.0
     */
    private function create_professional_tables() {
        // Create analytics table
        if (class_exists('LiveTVAnalytics')) {
            $analytics = new LiveTVAnalytics();
            $analytics->create_analytics_table();
        }
        
        // Create user preferences tables
        if (class_exists('LiveTVUserPreferences')) {
            $preferences = new LiveTVUserPreferences();
            $preferences->create_user_tables();
        }
        
        // Create playlist tables
        if (class_exists('LiveTVPlaylistManager')) {
            $playlist_manager = new LiveTVPlaylistManager();
            $playlist_manager->create_playlist_tables();
        }
    }
    
    /**
     * Configure default plugin options and settings
     * 
     * Sets up initial configuration values for the plugin,
     * ensuring proper defaults for new installations.
     * 
     * @since 1.0.0
     */
    private function set_default_options() {
        $default_options = array(
            'version' => LIVE_TV_VERSION,
            'autoplay' => 'false',
            'mobile_optimized' => 'true',
            'cast_enabled' => 'true',
            'source_protection' => 'false'
        );
        
        foreach ($default_options as $option => $value) {
            $option_name = 'live_tv_' . $option;
            if (!get_option($option_name)) {
                add_option($option_name, $value);
            }
        }
    }
    
    /**
     * Handle plugin deactivation
     * 
     * Performs cleanup tasks when the plugin is deactivated,
     * including flushing rewrite rules and clearing caches.
     * 
     * @since 1.0.0
     */
    public function deactivate() {
        // Clear WordPress rewrite rules
        flush_rewrite_rules();
        
        // Clear any cached data
        wp_cache_flush();
        
        // Log deactivation for debugging
        error_log('Live TV Streaming Plugin deactivated');
    }
    
    /**
     * Initialize plugin updater
     * 
     * Sets up automatic update checking and license validation
     * 
     * @since 3.2.0
     */
    private function init_updater() {
        if (is_admin()) {
            $this->updater = new LiveTVPluginUpdater(
                __FILE__,
                LIVE_TV_VERSION,
                'https://mltvupgrade.alimusa.so/wp-json/update-server/v1/'
            );
        }
    }
    
    /**
     * Enqueue frontend scripts and styles
     * 
     * Loads all necessary JavaScript and CSS files for the frontend
     * display, including Video.js player, Google Cast API, and custom scripts.
     * 
     * @since 1.0.0
     */
    public function enqueue_scripts() {
        // Load Video.js player library (latest version)
        wp_enqueue_script(
            'video-js', 
            'https://vjs.zencdn.net/8.8.0/video.min.js', 
            array(), 
            '8.8.0', 
            true
        );
        wp_enqueue_style(
            'video-js-css', 
            'https://vjs.zencdn.net/8.8.0/video-js.css', 
            array(), 
            '8.8.0'
        );
        
        // Load WebCodecs polyfill for browsers that don't support it
        wp_enqueue_script(
            'webcodecs-polyfill',
            'https://unpkg.com/@webcodecs/av1-decoder@0.1.1/dist/av1-decoder.umd.js',
            array(),
            '0.1.1',
            true
        );
        
        // Load Video.js WebCodecs plugin
        wp_enqueue_script(
            'videojs-webcodecs',
            LIVE_TV_PLUGIN_URL . 'assets/js/videojs-webcodecs.js',
            array('video-js'),
            LIVE_TV_VERSION,
            true
        );
        
        // Load WebRTC streaming support
        wp_enqueue_script(
            'webrtc-streaming',
            LIVE_TV_PLUGIN_URL . 'assets/js/webrtc-streaming.js',
            array(),
            LIVE_TV_VERSION,
            true
        );
        
        // Load AV1 codec support
        wp_enqueue_script(
            'av1-codec-support',
            LIVE_TV_PLUGIN_URL . 'assets/js/av1-codec-support.js',
            array('video-js'),
            LIVE_TV_VERSION,
            true
        );
        
        // Load AI recommendations system
        wp_enqueue_script(
            'ai-recommendations',
            LIVE_TV_PLUGIN_URL . 'assets/js/ai-recommendations.js',
            array(),
            LIVE_TV_VERSION,
            true
        );
        
        // Load modern API client
        wp_enqueue_script(
            'api-client',
            LIVE_TV_PLUGIN_URL . 'assets/js/api-client.js',
            array(),
            LIVE_TV_VERSION,
            true
        );
        
        // Load Google Cast API for streaming to TV devices
        wp_enqueue_script(
            'google-cast-api', 
            'https://www.gstatic.com/cv/js/sender/v1/cast_sender.js?loadCastFramework=1', 
            array(), 
            null, 
            true
        );
        
        // Load custom plugin scripts
        wp_enqueue_script(
            'live-tv-frontend', 
            LIVE_TV_PLUGIN_URL . 'assets/js/frontend.js', 
            array('jquery', 'video-js', 'google-cast-api'), 
            LIVE_TV_VERSION, 
            true
        );
        
        // Load frontend styles
        wp_enqueue_style(
            'live-tv-frontend', 
            LIVE_TV_PLUGIN_URL . 'assets/css/frontend.css', 
            array('video-js-css'), 
            LIVE_TV_VERSION
        );
        
        // Get customization settings
        $customization_settings = array(
            'enabled' => get_option('live_tv_branding_enabled', 'false') === 'true',
            'type' => get_option('live_tv_branding_type', 'text'),
            'text' => get_option('live_tv_branding_text', 'Live TV Pro'),
            'logoUrl' => get_option('live_tv_branding_logo_url', ''),
            'position' => get_option('live_tv_branding_position', 'top-right'),
            'opacity' => floatval(get_option('live_tv_branding_opacity', 0.7)),
            'loadingAnimation' => get_option('live_tv_loading_animation', 'professional'),
            'controlBarStyle' => get_option('live_tv_control_bar_style', 'professional'),
            'transitionEffects' => get_option('live_tv_transition_effects', 'true') === 'true',
            'hoverEffects' => get_option('live_tv_hover_effects', 'true') === 'true',
            'centerPlayButton' => get_option('live_tv_center_play_button', 'true'),
            'welcomeMessage' => get_option('live_tv_welcome_message', 'Welcome to {channel_name}'),
            'customCss' => get_option('live_tv_custom_css', '')
        );
        
        // Localize script with AJAX data and customization settings
        wp_localize_script('live-tv-frontend', 'liveTV', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('live_tv_nonce'),
            'plugin_url' => LIVE_TV_PLUGIN_URL,
            'customization' => $customization_settings
        ));
    }
    
    /**
     * Enqueue admin scripts and styles
     * 
     * Loads JavaScript and CSS files specific to the WordPress admin area
     * for plugin management and configuration pages.
     * 
     * @since 1.0.0
     * @param string $hook Current admin page hook
     */
    public function admin_enqueue_scripts($hook) {
        // Only load on plugin admin pages for performance
        if (strpos($hook, 'live-tv') === false) {
            return;
        }
        
        // Load admin JavaScript
        wp_enqueue_script(
            'live-tv-admin', 
            LIVE_TV_PLUGIN_URL . 'assets/js/admin.js', 
            array('jquery'), 
            LIVE_TV_VERSION, 
            true
        );
        
        // Load admin styles
        wp_enqueue_style(
            'live-tv-admin', 
            LIVE_TV_PLUGIN_URL . 'assets/css/admin.css', 
            array(), 
            LIVE_TV_VERSION
        );
        
        // Provide AJAX data for admin scripts
        wp_localize_script('live-tv-admin', 'liveTVAdmin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('live_tv_admin_nonce'),
            'plugin_url' => LIVE_TV_PLUGIN_URL
        ));
    }
}

/**
 * Initialize the plugin
 * 
 * Create a single instance of the main plugin class to start
 * the Live TV Streaming functionality.
 * 
 * @since 1.0.0
 */
function live_tv_streaming_init() {
    return new LiveTVStreamingPlugin();
}

// Start the plugin
live_tv_streaming_init();