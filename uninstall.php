<?php
if (!defined('WP_UNINSTALL_PLUGIN')) exit('Direct access denied.');
if (!current_user_can('activate_plugins')) exit('Insufficient permissions.');

function live_tv_streaming_uninstall() {
    live_tv_remove_database_tables();
    live_tv_remove_plugin_options();
    live_tv_clear_cached_data();
    live_tv_remove_capabilities();
    live_tv_remove_uploaded_files();
    
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Live TV Streaming Plugin: Uninstall completed');
    }
}

function live_tv_remove_database_tables() {
    global $wpdb;
    
    $tables = array(
        $wpdb->prefix . 'live_tv_channels',
        $wpdb->prefix . 'live_tv_analytics', 
        $wpdb->prefix . 'live_tv_favorites',
        $wpdb->prefix . 'live_tv_watch_history',
        $wpdb->prefix . 'live_tv_playlists',
        $wpdb->prefix . 'live_tv_playlist_items'
    );
    
    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS `{$table}`");
    }
    
    $wpdb->flush();
}

function live_tv_remove_plugin_options() {
    global $wpdb;
    
    // Remove all options starting with 'live_tv_'
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'live_tv_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_live_tv_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_live_tv_%'");
    
    // For multisite
    if (is_multisite()) {
        $wpdb->query("DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE 'live_tv_%'");
        $wpdb->query("DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE '_site_transient_live_tv_%'");
        $wpdb->query("DELETE FROM {$wpdb->sitemeta} WHERE meta_key LIKE '_site_transient_timeout_live_tv_%'");
    }
    
    // Remove user meta related to plugin
    $wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'live_tv_%'");
}

function live_tv_clear_cached_data() {
    wp_cache_flush();
    
    if (function_exists('wp_cache_delete_group')) {
        wp_cache_delete_group('live_tv_streaming');
    }
    
    flush_rewrite_rules();
}

function live_tv_remove_capabilities() {
    global $wp_roles;
    
    if (!isset($wp_roles)) $wp_roles = new WP_Roles();
    
    $capabilities = array(
        'manage_live_tv',
        'edit_live_tv_channels', 
        'view_live_tv_analytics'
    );
    
    foreach ($wp_roles->roles as $role => $details) {
        $role_obj = get_role($role);
        if ($role_obj) {
            foreach ($capabilities as $capability) {
                $role_obj->remove_cap($capability);
            }
        }
    }
}

function live_tv_remove_uploaded_files() {
    $upload_dir = wp_upload_dir();
    $plugin_upload_dir = $upload_dir['basedir'] . '/live-tv-streaming';
    
    if (is_dir($plugin_upload_dir)) {
        live_tv_remove_directory($plugin_upload_dir);
    }
}

function live_tv_remove_directory($dir) {
    if (!is_dir($dir)) return false;
    
    $files = array_diff(scandir($dir), array('.', '..'));
    
    foreach ($files as $file) {
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        if (is_dir($path)) {
            live_tv_remove_directory($path);
        } else {
            unlink($path);
        }
    }
    
    return rmdir($dir);
}

live_tv_streaming_uninstall();
wp_cache_flush();