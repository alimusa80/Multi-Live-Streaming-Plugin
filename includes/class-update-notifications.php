<?php
/**
 * Update Notifications Class
 * 
 * Handles enhanced update notifications and admin notices for the Live TV Streaming Plugin
 * 
 * @package LiveTVStreaming
 * @version 1.0.0
 * @author Ali Musa
 */

if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

class LiveTVUpdateNotifications {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_notices', array($this, 'show_update_notices'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_notification_scripts'));
        add_action('wp_ajax_live_tv_dismiss_notice', array($this, 'dismiss_notice'));
        add_action('wp_ajax_live_tv_check_update_status', array($this, 'check_update_status'));
        
        // Enhanced update notifications
        add_action('admin_init', array($this, 'check_for_important_updates'));
        add_filter('plugin_row_meta', array($this, 'add_plugin_row_meta'), 10, 2);
    }
    
    /**
     * Show update notices in admin
     */
    public function show_update_notices() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Check if on plugin pages
        $screen = get_current_screen();
        $show_on_all_pages = false;
        
        if (isset($_GET['page']) && strpos($_GET['page'], 'live-tv') !== false) {
            $show_on_all_pages = true;
        }
        
        // Show update availability notice
        $this->show_update_available_notice($show_on_all_pages);
        
        // Show post-update notices
        $this->show_post_update_notices();
    }
    
    
    /**
     * Show update available notice
     */
    private function show_update_available_notice($show_on_all_pages) {
        // Get update transient to check for available updates
        $update_plugins = get_site_transient('update_plugins');
        $plugin_file = plugin_basename(LIVE_TV_PLUGIN_PATH . 'live-tv-streaming.php');
        
        if (isset($update_plugins->response[$plugin_file])) {
            $update_data = $update_plugins->response[$plugin_file];
            $dismissed = get_user_meta(get_current_user_id(), 'live_tv_update_' . $update_data->new_version . '_dismissed', true);
            
            if (!$dismissed && ($show_on_all_pages || get_current_screen()->id === 'plugins')) {
                ?>
                <div class="notice notice-success is-dismissible" data-notice="update_available" data-version="<?php echo esc_attr($update_data->new_version); ?>">
                    <div style="display: flex; align-items: center; padding: 8px 0;">
                        <div style="margin-right: 15px;">
                            <span class="dashicons dashicons-update" style="color: #00a32a; font-size: 24px;"></span>
                        </div>
                        <div style="flex: 1;">
                            <h3 style="margin: 0 0 5px 0;"><?php _e('Live TV Streaming Update Available', 'live-tv-streaming'); ?></h3>
                            <p style="margin: 0;">
                                <?php 
                                printf(
                                    __('Version %s is now available. Update now to get the latest features and security improvements.', 'live-tv-streaming'),
                                    $update_data->new_version
                                );
                                ?>
                            </p>
                        </div>
                        <div style="margin-left: 15px;">
                            <a href="<?php echo wp_nonce_url(self_admin_url('update.php?action=upgrade-plugin&plugin=' . urlencode($plugin_file)), 'upgrade-plugin_' . $plugin_file); ?>" class="button button-primary">
                                <?php _e('Update Now', 'live-tv-streaming'); ?>
                            </a>
                            <a href="<?php echo esc_url($update_data->url); ?>" target="_blank" class="button button-secondary" style="margin-left: 5px;">
                                <?php _e('View Details', 'live-tv-streaming'); ?>
                            </a>
                        </div>
                    </div>
                </div>
                <?php
            }
        }
    }
    
    /**
     * Show post-update notices
     */
    private function show_post_update_notices() {
        // Check if plugin was recently updated
        $last_update_version = get_option('live_tv_last_update_version');
        $current_version = LIVE_TV_VERSION;
        
        if ($last_update_version && version_compare($last_update_version, $current_version, '<')) {
            $dismissed = get_user_meta(get_current_user_id(), 'live_tv_updated_to_' . $current_version . '_dismissed', true);
            
            if (!$dismissed) {
                ?>
                <div class="notice notice-success is-dismissible" data-notice="updated" data-version="<?php echo esc_attr($current_version); ?>">
                    <div style="display: flex; align-items: center; padding: 8px 0;">
                        <div style="margin-right: 15px;">
                            <span class="dashicons dashicons-yes-alt" style="color: #00a32a; font-size: 24px;"></span>
                        </div>
                        <div style="flex: 1;">
                            <h3 style="margin: 0 0 5px 0;"><?php _e('Live TV Streaming Updated Successfully!', 'live-tv-streaming'); ?></h3>
                            <p style="margin: 0;">
                                <?php 
                                printf(
                                    __('Plugin updated to version %s. Check out the new features and improvements.', 'live-tv-streaming'),
                                    $current_version
                                );
                                ?>
                            </p>
                        </div>
                        <div style="margin-left: 15px;">
                            <a href="<?php echo admin_url('admin.php?page=live-tv-dashboard'); ?>" class="button button-primary">
                                <?php _e('View Dashboard', 'live-tv-streaming'); ?>
                            </a>
                            <a href="https://alimusa.so/live-tv-changelog/" target="_blank" class="button button-secondary" style="margin-left: 5px;">
                                <?php _e('What\'s New', 'live-tv-streaming'); ?>
                            </a>
                        </div>
                    </div>
                </div>
                <?php
            }
            
            // Update the last update version
            update_option('live_tv_last_update_version', $current_version);
        }
    }
    
    /**
     * Enqueue notification scripts
     */
    public function enqueue_notification_scripts($hook) {
        // Only load on admin pages
        if (!current_user_can('manage_options')) {
            return;
        }
        
        wp_enqueue_script('live-tv-notifications', LIVE_TV_PLUGIN_URL . 'assets/js/admin-notifications.js', array('jquery'), LIVE_TV_VERSION, true);
        
        wp_localize_script('live-tv-notifications', 'liveTVNotifications', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('live_tv_notifications_nonce'),
            'strings' => array(
                'checking' => __('Checking for updates...', 'live-tv-streaming'),
                'up_to_date' => __('Plugin is up to date', 'live-tv-streaming'),
                'update_available' => __('Update available', 'live-tv-streaming'),
                'error' => __('Error checking for updates', 'live-tv-streaming')
            )
        ));
        
        // Add notification styles
        wp_add_inline_style('wp-admin', '
            .live-tv-notice {
                border-left: 4px solid #0073aa;
                background: #fff;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
                margin: 5px 0 15px;
                padding: 1px 12px;
            }
            
            .live-tv-notice.notice-success {
                border-left-color: #00a32a;
            }
            
            .live-tv-notice.notice-warning {
                border-left-color: #f56e28;
            }
            
            .live-tv-notice.notice-error {
                border-left-color: #d63638;
            }
            
            .live-tv-notice h3 {
                font-size: 14px;
                font-weight: 600;
                line-height: 1.4;
            }
            
            .live-tv-notice p {
                font-size: 13px;
                line-height: 1.5;
            }
            
            .live-tv-update-spinner {
                display: inline-block;
                width: 16px;
                height: 16px;
                margin-right: 5px;
                vertical-align: middle;
            }
        ');
    }
    
    /**
     * Dismiss notice via AJAX
     */
    public function dismiss_notice() {
        if (!wp_verify_nonce($_POST['nonce'], 'live_tv_notifications_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $notice_type = sanitize_text_field($_POST['notice_type']);
        $version = sanitize_text_field($_POST['version'] ?? '');
        
        $user_id = get_current_user_id();
        
        switch ($notice_type) {
            case 'update_available':
                if ($version) {
                    update_user_meta($user_id, 'live_tv_update_' . $version . '_dismissed', time());
                }
                break;
                
            case 'updated':
                if ($version) {
                    update_user_meta($user_id, 'live_tv_updated_to_' . $version . '_dismissed', time());
                }
                break;
        }
        
        wp_send_json_success('Notice dismissed');
    }
    
    /**
     * Check update status via AJAX
     */
    public function check_update_status() {
        if (!wp_verify_nonce($_POST['nonce'], 'live_tv_notifications_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        // Force check for updates
        delete_site_transient('update_plugins');
        wp_update_plugins();
        
        $update_plugins = get_site_transient('update_plugins');
        $plugin_file = plugin_basename(LIVE_TV_PLUGIN_PATH . 'live-tv-streaming.php');
        
        if (isset($update_plugins->response[$plugin_file])) {
            $update_data = $update_plugins->response[$plugin_file];
            wp_send_json_success(array(
                'update_available' => true,
                'new_version' => $update_data->new_version,
                'current_version' => LIVE_TV_VERSION,
                'update_url' => wp_nonce_url(
                    self_admin_url('update.php?action=upgrade-plugin&plugin=' . urlencode($plugin_file)), 
                    'upgrade-plugin_' . $plugin_file
                )
            ));
        } else {
            wp_send_json_success(array(
                'update_available' => false,
                'current_version' => LIVE_TV_VERSION
            ));
        }
    }
    
    /**
     * Check for important updates
     */
    public function check_for_important_updates() {
        // Check if we need to show important security updates
        $last_security_check = get_option('live_tv_last_security_check', 0);
        
        if (time() - $last_security_check > DAY_IN_SECONDS) {
            $this->check_security_updates();
            update_option('live_tv_last_security_check', time());
        }
    }
    
    /**
     * Check for security updates
     */
    private function check_security_updates() {
        $response = wp_remote_get('https://alimusa.so/wp-json/security/v1/check-version', array(
            'timeout' => 10,
            'body' => array(
                'plugin' => 'live-tv-streaming',
                'version' => LIVE_TV_VERSION,
                'site_url' => home_url()
            )
        ));
        
        if (!is_wp_error($response)) {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if ($data && isset($data['security_update']) && $data['security_update']) {
                // Store security update notice
                update_option('live_tv_security_update_available', $data);
            }
        }
    }
    
    /**
     * Add plugin row meta
     */
    public function add_plugin_row_meta($plugin_meta, $plugin_file) {
        if (plugin_basename(LIVE_TV_PLUGIN_PATH . 'live-tv-streaming.php') === $plugin_file) {
            $plugin_meta[] = '<span style="color: #00a32a; font-weight: bold;">' . __('Free Plugin', 'live-tv-streaming') . '</span>';
            $plugin_meta[] = '<a href="https://alimusa.so/support/" target="_blank">' . __('Support', 'live-tv-streaming') . '</a>';
            $plugin_meta[] = '<a href="https://alimusa.so/live-tv-changelog/" target="_blank">' . __('Changelog', 'live-tv-streaming') . '</a>';
        }
        
        return $plugin_meta;
    }
}

// Initialize update notifications
new LiveTVUpdateNotifications();