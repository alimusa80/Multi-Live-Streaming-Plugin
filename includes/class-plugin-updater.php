<?php
/**
 * WordPress Plugin Updater Class
 * 
 * Handles automatic updates for the Live TV Streaming Plugin
 * Provides secure update checking and automatic downloads
 * 
 * @package LiveTVStreaming
 * @version 1.0.0
 * @author Ali Musa
 */

if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

class LiveTVPluginUpdater {
    
    /**
     * Plugin file path
     * @var string
     */
    private $plugin_file;
    
    /**
     * Plugin slug
     * @var string
     */
    private $plugin_slug;
    
    /**
     * Current plugin version
     * @var string
     */
    private $version;
    
    /**
     * Update server URL
     * @var string
     */
    private $update_server;
    
    /**
     * Plugin data
     * @var array
     */
    private $plugin_data;
    
    /**
     * Cache key for update data
     * @var string
     */
    private $cache_key;
    
    /**
     * Constructor
     * 
     * @param string $plugin_file Main plugin file path
     * @param string $version Current plugin version
     * @param string $update_server Update server URL
     */
    public function __construct($plugin_file, $version, $update_server = 'https://mltvupgrade.alimusa.so/wp-json/update-server/v1/') {
        $this->plugin_file = $plugin_file;
        $this->plugin_slug = plugin_basename($plugin_file);
        $this->version = $version;
        $this->update_server = $update_server;
        $this->cache_key = 'live_tv_update_check';
        
        // Get plugin data
        if (!function_exists('get_plugin_data')) {
            if (file_exists(ABSPATH . 'wp-admin/includes/plugin.php')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
        }
        if (function_exists('get_plugin_data')) {
            $this->plugin_data = get_plugin_data($plugin_file);
        } else {
            $this->plugin_data = ['Name' => 'Live TV Streaming', 'Version' => $version];
        }
        
        // Initialize hooks
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Check for updates
        add_filter('pre_set_site_transient_update_plugins', array($this, 'check_for_updates'));
        
        // Plugin information popup
        add_filter('plugins_api', array($this, 'plugin_info'), 10, 3);
        
        // After plugin update
        add_action('upgrader_process_complete', array($this, 'after_update'), 10, 2);
        
        // AJAX handlers for manual update checking
        add_action('wp_ajax_live_tv_check_updates', array($this, 'manual_update_check'));
    }
    
    /**
     * Check for plugin updates
     * 
     * @param object $transient WordPress update transient
     * @return object Modified transient
     */
    public function check_for_updates($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }
        
        // Check if our plugin is in the checked plugins
        if (!isset($transient->checked[$this->plugin_slug])) {
            return $transient;
        }
        
        // Get cached update data
        $update_data = get_transient($this->cache_key);
        
        // If no cached data or cache expired, check for updates
        if ($update_data === false) {
            $update_data = $this->get_remote_version();
            
            // Cache the result for 12 hours
            set_transient($this->cache_key, $update_data, 12 * HOUR_IN_SECONDS);
        }
        
        // If update is available, add to transient
        if ($update_data && version_compare($this->version, $update_data->new_version, '<')) {
            $transient->response[$this->plugin_slug] = $update_data;
        } else {
            // Remove from updates if no update available
            unset($transient->response[$this->plugin_slug]);
        }
        
        return $transient;
    }
    
    /**
     * Get remote version information
     * 
     * @return object|false Update data or false on failure
     */
    private function get_remote_version() {
        $request_args = array(
            'method' => 'GET',
            'timeout' => 30,
            'redirection' => 5,
            'httpversion' => '1.1',
            'blocking' => true,
            'headers' => array(
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url()
            )
        );
        
        // Make request to update server
        $response = wp_remote_get($this->update_server . 'check-version', $request_args);
        
        // Handle errors
        if (is_wp_error($response)) {
            $this->log_error('Update check failed: ' . $response->get_error_message());
            return false;
        }
        
        // Check response code
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            $this->log_error('Update server returned HTTP ' . $response_code);
            return false;
        }
        
        // Parse response body
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data || !isset($data['success']) || !$data['success']) {
            $error_message = isset($data['message']) ? $data['message'] : 'Unknown error';
            $this->log_error('Update check error: ' . $error_message);
            return false;
        }
        
        // Build update object
        if (isset($data['data']) && $data['data']) {
            $update_data = $data['data'];
            
            return (object) array(
                'id' => $this->plugin_slug,
                'slug' => dirname($this->plugin_slug),
                'plugin' => $this->plugin_slug,
                'new_version' => $update_data['version'],
                'url' => $update_data['details_url'],
                'package' => 'https://mltvupgrade.alimusa.so/livtv/live-tv-streaming.zip',
                'icons' => $update_data['icons'],
                'banners' => $update_data['banners'],
                'tested' => $update_data['tested'],
                'requires_php' => $update_data['requires_php'],
                'compatibility' => array(),
                'sections' => array(
                    'description' => $update_data['description'],
                    'changelog' => $update_data['changelog']
                )
            );
        }
        
        return false;
    }
    
    /**
     * Plugin information popup
     * 
     * @param false|object|array $result
     * @param string $action
     * @param object $args
     * @return false|object
     */
    public function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information') {
            return $result;
        }
        
        if (!isset($args->slug) || $args->slug !== dirname($this->plugin_slug)) {
            return $result;
        }
        
        // Get plugin information from server
        $plugin_info = $this->get_remote_plugin_info();
        
        if ($plugin_info) {
            return $plugin_info;
        }
        
        return $result;
    }
    
    /**
     * Get remote plugin information
     * 
     * @return object|false Plugin info or false on failure
     */
    private function get_remote_plugin_info() {
        $request_args = array(
            'method' => 'GET',
            'timeout' => 30,
            'headers' => array(
                'User-Agent' => 'WordPress/' . get_bloginfo('version') . '; ' . home_url()
            )
        );
        
        $response = wp_remote_get($this->update_server . 'plugin-info', $request_args);
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if ($data && isset($data['success']) && $data['success'] && isset($data['data'])) {
            $info = $data['data'];
            
            return (object) array(
                'name' => $info['name'],
                'slug' => dirname($this->plugin_slug),
                'version' => $info['version'],
                'author' => $info['author'],
                'author_profile' => $info['author_profile'],
                'homepage' => $info['homepage'],
                'short_description' => $info['short_description'],
                'sections' => $info['sections'],
                'download_link' => $info['download_url'],
                'screenshots' => $info['screenshots'],
                'tags' => $info['tags'],
                'requires' => $info['requires'],
                'tested' => $info['tested'],
                'requires_php' => $info['requires_php'],
                'rating' => $info['rating'],
                'num_ratings' => $info['num_ratings'],
                'active_installs' => $info['active_installs'],
                'last_updated' => $info['last_updated'],
                'added' => $info['added']
            );
        }
        
        return false;
    }
    
    /**
     * After plugin update hook
     * 
     * @param object $upgrader_object
     * @param array $options
     */
    public function after_update($upgrader_object, $options) {
        if ($options['action'] === 'update' && $options['type'] === 'plugin') {
            if (isset($options['plugins']) && in_array($this->plugin_slug, $options['plugins'])) {
                // Clear update cache
                delete_transient($this->cache_key);
                
                // Log successful update
                $this->log_info('Plugin updated successfully to version ' . $this->version);
                
                // Trigger any post-update actions
                do_action('live_tv_after_plugin_update', $this->version);
            }
        }
    }
    
    /**
     * Add professional update check section to settings
     */
    public function update_settings_section() {
        ?>
        <!-- Plugin Updates Section -->
        <div class="ltv-card ltv-update-card">
            <div class="ltv-card-header">
                <h2>
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('Plugin Updates', 'live-tv-streaming'); ?>
                </h2>
                <div class="ltv-version-badge">
                    <span class="dashicons dashicons-yes-alt"></span>
                    v<?php echo esc_html(LIVE_TV_VERSION); ?>
                </div>
            </div>
            
            <div class="ltv-card-content">
                <div class="ltv-update-info">
                    <div class="ltv-update-status">
                        <div class="ltv-status-indicator ltv-status-active">
                            <span class="ltv-status-dot"></span>
                            <span class="ltv-status-text"><?php _e('Active & Up-to-Date', 'live-tv-streaming'); ?></span>
                        </div>
                        <p class="ltv-update-description">
                            <?php _e('This plugin receives free automatic updates. WordPress checks for new versions every 12 hours and will notify you when updates are available.', 'live-tv-streaming'); ?>
                        </p>
                    </div>
                    
                    <div class="ltv-update-features">
                        <div class="ltv-feature-item">
                            <span class="dashicons dashicons-shield-alt"></span>
                            <div class="ltv-feature-content">
                                <strong><?php _e('Automatic Security Updates', 'live-tv-streaming'); ?></strong>
                                <p><?php _e('Critical security patches are delivered automatically', 'live-tv-streaming'); ?></p>
                            </div>
                        </div>
                        
                        <div class="ltv-feature-item">
                            <span class="dashicons dashicons-star-filled"></span>
                            <div class="ltv-feature-content">
                                <strong><?php _e('New Features & Improvements', 'live-tv-streaming'); ?></strong>
                                <p><?php _e('Get the latest features and performance improvements', 'live-tv-streaming'); ?></p>
                            </div>
                        </div>
                        
                        <div class="ltv-feature-item">
                            <span class="dashicons dashicons-heart"></span>
                            <div class="ltv-feature-content">
                                <strong><?php _e('Completely Free', 'live-tv-streaming'); ?></strong>
                                <p><?php _e('No license required - enjoy all features and updates for free', 'live-tv-streaming'); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="ltv-update-actions">
                        <button type="button" id="check-updates" class="ltv-button ltv-button-primary">
                            <span class="dashicons dashicons-update-alt"></span>
                            <?php _e('Check for Updates Now', 'live-tv-streaming'); ?>
                        </button>
                        <span id="update-status" class="ltv-update-status-text"></span>
                    </div>
                    
                    <div class="ltv-update-info-box">
                        <div class="ltv-info-header">
                            <span class="dashicons dashicons-info"></span>
                            <?php _e('Update Information', 'live-tv-streaming'); ?>
                        </div>
                        <div class="ltv-info-grid">
                            <div class="ltv-info-item">
                                <label><?php _e('Current Version:', 'live-tv-streaming'); ?></label>
                                <span class="ltv-version-number"><?php echo esc_html(LIVE_TV_VERSION); ?></span>
                            </div>
                            <div class="ltv-info-item">
                                <label><?php _e('Release Channel:', 'live-tv-streaming'); ?></label>
                                <span class="ltv-release-channel"><?php _e('Stable', 'live-tv-streaming'); ?></span>
                            </div>
                            <div class="ltv-info-item">
                                <label><?php _e('Last Check:', 'live-tv-streaming'); ?></label>
                                <span class="ltv-last-check" id="last-update-check"><?php _e('WordPress automatic', 'live-tv-streaming'); ?></span>
                            </div>
                            <div class="ltv-info-item">
                                <label><?php _e('Update Source:', 'live-tv-streaming'); ?></label>
                                <span class="ltv-update-source"><?php _e('Official Repository', 'live-tv-streaming'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        
        <!-- Professional CSS Styling -->
        <style>
        /* Plugin Updates Card Styling */
        .ltv-update-card {
            margin-top: 24px;
            background: var(--surface, #151932);
            border: 1px solid var(--border, #2d3748);
            border-radius: 16px;
            box-shadow: var(--shadow, 0 10px 25px rgba(0, 0, 0, 0.2));
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .ltv-update-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
        }
        
        .ltv-update-card .ltv-card-header {
            background: var(--card, #1e2139);
            border-bottom: 1px solid var(--border, #2d3748);
            padding: 20px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
        }
        
        .ltv-update-card .ltv-card-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 50%, #00d4aa 100%);
        }
        
        .ltv-update-card h2 {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary, #ffffff);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .ltv-update-card h2 .dashicons {
            font-size: 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        .ltv-version-badge {
            background: linear-gradient(135deg, #00d4aa 0%, #0099cc 100%);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 4px 12px rgba(0, 212, 170, 0.3);
        }
        
        .ltv-version-badge .dashicons {
            font-size: 16px;
        }
        
        .ltv-card-content {
            padding: 24px;
        }
        
        .ltv-update-info {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }
        
        .ltv-update-status {
            text-align: center;
            padding: 20px;
            background: rgba(0, 212, 170, 0.1);
            border-radius: 12px;
            border: 1px solid rgba(0, 212, 170, 0.2);
        }
        
        .ltv-status-indicator {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 12px;
        }
        
        .ltv-status-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #00d4aa;
            animation: statusPulse 2s ease-in-out infinite;
        }
        
        @keyframes statusPulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(0, 212, 170, 0.7); }
            50% { box-shadow: 0 0 0 8px rgba(0, 212, 170, 0); }
        }
        
        .ltv-status-text {
            font-size: 16px;
            font-weight: 600;
            color: #00d4aa;
        }
        
        .ltv-update-description {
            color: var(--text-secondary, #8892b0);
            line-height: 1.6;
            margin: 0;
        }
        
        .ltv-update-features {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
        }
        
        .ltv-feature-item {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            padding: 16px;
            background: rgba(255, 255, 255, 0.02);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.05);
            transition: all 0.3s ease;
        }
        
        .ltv-feature-item:hover {
            background: rgba(255, 255, 255, 0.05);
            border-color: rgba(102, 126, 234, 0.3);
            transform: translateX(4px);
        }
        
        .ltv-feature-item .dashicons {
            font-size: 24px;
            color: #667eea;
            margin-top: 2px;
            flex-shrink: 0;
        }
        
        .ltv-feature-content {
            flex: 1;
        }
        
        .ltv-feature-content strong {
            display: block;
            color: var(--text-primary, #ffffff);
            font-size: 16px;
            margin-bottom: 4px;
        }
        
        .ltv-feature-content p {
            color: var(--text-secondary, #8892b0);
            margin: 0;
            line-height: 1.5;
        }
        
        .ltv-update-actions {
            display: flex;
            align-items: center;
            gap: 16px;
            justify-content: center;
            padding: 20px;
            background: rgba(255, 255, 255, 0.02);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        
        .ltv-button-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .ltv-button-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }
        
        .ltv-button-primary:active {
            transform: translateY(0);
        }
        
        .ltv-button-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .ltv-button-primary .dashicons {
            font-size: 16px;
            animation: rotate 2s linear infinite;
        }
        
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .ltv-button-primary:not(:disabled) .dashicons {
            animation: none;
        }
        
        .ltv-update-status-text {
            font-weight: 500;
            padding: 8px 16px;
            border-radius: 6px;
            transition: all 0.3s ease;
        }
        
        .ltv-update-status-text.success {
            color: #00d4aa;
            background: rgba(0, 212, 170, 0.1);
            border: 1px solid rgba(0, 212, 170, 0.2);
        }
        
        .ltv-update-status-text.error {
            color: #f5576c;
            background: rgba(245, 87, 108, 0.1);
            border: 1px solid rgba(245, 87, 108, 0.2);
        }
        
        .ltv-update-info-box {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            overflow: hidden;
        }
        
        .ltv-info-header {
            background: rgba(255, 255, 255, 0.03);
            padding: 16px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            font-weight: 600;
            color: var(--text-primary, #ffffff);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .ltv-info-header .dashicons {
            color: #667eea;
        }
        
        .ltv-info-grid {
            padding: 20px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        
        .ltv-info-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .ltv-info-item label {
            font-size: 12px;
            color: var(--text-secondary, #8892b0);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 500;
        }
        
        .ltv-info-item span {
            color: var(--text-primary, #ffffff);
            font-weight: 600;
        }
        
        .ltv-version-number {
            color: #00d4aa !important;
        }
        
        .ltv-release-channel {
            color: #667eea !important;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .ltv-update-card .ltv-card-header {
                flex-direction: column;
                gap: 12px;
                text-align: center;
            }
            
            .ltv-info-grid {
                grid-template-columns: 1fr;
            }
            
            .ltv-update-actions {
                flex-direction: column;
            }
        }
        
        /* Dark Mode Support */
        @media (prefers-color-scheme: light) {
            .ltv-update-card {
                background: #ffffff;
                border-color: #e2e8f0;
                color: #1a202c;
            }
            
            .ltv-update-card .ltv-card-header {
                background: #f7fafc;
                border-bottom-color: #e2e8f0;
            }
            
            .ltv-update-card h2 {
                color: #1a202c;
            }
            
            .ltv-update-description,
            .ltv-feature-content p {
                color: #4a5568;
            }
            
            .ltv-feature-content strong,
            .ltv-info-item span {
                color: #1a202c;
            }
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Enhanced update checking with animations
            $('#check-updates').on('click', function() {
                var $button = $(this);
                var $status = $('#update-status');
                var $lastCheck = $('#last-update-check');
                
                // Store original text
                var originalText = $button.find('span:not(.dashicons)').text() || $button.text();
                var originalHtml = $button.html();
                
                // Update button state
                $button.prop('disabled', true)
                       .html('<span class="dashicons dashicons-update-alt"></span><?php _e("Checking...", "live-tv-streaming"); ?>');
                
                // Update status
                $status.removeClass('success error')
                       .text('<?php _e("Checking for updates...", "live-tv-streaming"); ?>');
                
                // Update last check time
                var now = new Date();
                $lastCheck.text(now.toLocaleString());
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'live_tv_check_updates',
                        nonce: '<?php echo wp_create_nonce("live_tv_update_nonce"); ?>'
                    },
                    success: function(response) {
                        // Reset button
                        $button.prop('disabled', false).html(originalHtml);
                        
                        if (response.success) {
                            $status.addClass('success').text(response.data);
                            
                            // If update available, show reload notification
                            if (response.data.indexOf('available') !== -1) {
                                $status.append(' <em><?php _e("Page will refresh shortly...", "live-tv-streaming"); ?></em>');
                                setTimeout(function() {
                                    location.reload();
                                }, 3000);
                            }
                        } else {
                            $status.addClass('error')
                                   .text(response.data || '<?php _e("Update check failed", "live-tv-streaming"); ?>');
                        }
                    },
                    error: function() {
                        $button.prop('disabled', false).html(originalHtml);
                        $status.addClass('error')
                               .text('<?php _e("Connection error - please try again", "live-tv-streaming"); ?>');
                    }
                });
            });
            
            // Auto-hide status messages after 10 seconds
            setTimeout(function() {
                $('#update-status').fadeOut();
            }, 10000);
        });
        </script>
        <?php
    }
    
    /**
     * Manual update check via AJAX
     */
    public function manual_update_check() {
        // Security check
        if (!wp_verify_nonce($_POST['nonce'], 'live_tv_update_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        // Clear cache and check for updates
        delete_transient($this->cache_key);
        $update_data = $this->get_remote_version();
        
        if ($update_data && version_compare($this->version, $update_data->new_version, '<')) {
            wp_send_json_success(sprintf(
                __('Update available: Version %s', 'live-tv-streaming'),
                $update_data->new_version
            ));
        } else {
            wp_send_json_success(__('Plugin is up to date', 'live-tv-streaming'));
        }
    }
    
    
    /**
     * Log error message
     * 
     * @param string $message Error message
     */
    private function log_error($message) {
        if (class_exists('LiveTVErrorHandler')) {
            $error_handler = LiveTVErrorHandler::getInstance();
            $error_handler->log_security_error(
                'Plugin Updater Error: ' . $message,
                'error',
                'general'
            );
        } else {
            error_log('Live TV Plugin Updater Error: ' . $message);
        }
    }
    
    /**
     * Log info message
     * 
     * @param string $message Info message
     */
    private function log_info($message) {
        if (class_exists('LiveTVErrorHandler')) {
            $error_handler = LiveTVErrorHandler::getInstance();
            $error_handler->log_security_error(
                'Plugin Updater Info: ' . $message,
                'info',
                'general'
            );
        } else {
            error_log('Live TV Plugin Updater Info: ' . $message);
        }
    }
    
    
    /**
     * Force update check
     * 
     * @return object|false Update data or false
     */
    public function force_update_check() {
        delete_transient($this->cache_key);
        return $this->get_remote_version();
    }
}