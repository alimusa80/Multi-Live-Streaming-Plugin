<?php
/**
 * User Data & Preferences Admin Page Template
 * 
 * Professional interface for managing user data, preferences, and privacy compliance.
 * 
 * @package LiveTVStreaming
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

// Check user permissions
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.', 'live-tv-streaming'));
}

// Include user preferences class
require_once LIVE_TV_PLUGIN_PATH . 'includes/pages/user-preferences.php';
require_once LIVE_TV_PLUGIN_PATH . 'includes/pages/playlist-manager.php';

$user_preferences = new LiveTVUserPreferences();
$playlist_manager = new LiveTVPlaylistManager();

// Get user statistics
global $wpdb;

// Count registered users with favorites
$users_with_favorites = $wpdb->get_var(
    "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}live_tv_favorites WHERE user_id IS NOT NULL"
);

// Count total favorites
$total_favorites = $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->prefix}live_tv_favorites"
);

// Count watch history entries
$total_history = $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->prefix}live_tv_watch_history"
);

// Count custom playlists
$custom_playlists = $wpdb->get_var(
    "SELECT COUNT(*) FROM {$wpdb->prefix}live_tv_playlists WHERE category = 'Custom'"
);

// Get most popular channels (by favorites)
$popular_channels = $wpdb->get_results(
    "SELECT c.name, COUNT(f.id) as favorite_count
     FROM {$wpdb->prefix}live_tv_favorites f
     JOIN {$wpdb->prefix}live_tv_channels c ON f.channel_id = c.id
     GROUP BY c.id, c.name
     ORDER BY favorite_count DESC
     LIMIT 10"
);

// Get recent user activity
$recent_activity = $wpdb->get_results(
    "SELECT h.*, c.name as channel_name, u.display_name
     FROM {$wpdb->prefix}live_tv_watch_history h
     JOIN {$wpdb->prefix}live_tv_channels c ON h.channel_id = c.id
     LEFT JOIN {$wpdb->users} u ON h.user_id = u.ID
     ORDER BY h.watch_date DESC
     LIMIT 20"
);
?>

<div class="wrap">
    <div class="live-tv-admin-wrap ltv-admin">
        <div class="live-tv-admin-header">
            <h1>
                <span class="dashicons dashicons-admin-users" style="font-size: 32px; margin-right: 10px;"></span>
                <?php _e('User Data & Preferences', 'live-tv-streaming'); ?>
            </h1>
            <div class="header-actions">
                <button id="export-user-data" class="ltv-button ltv-button-secondary">
                    <span class="dashicons dashicons-download"></span>
                    <?php _e('Export Data', 'live-tv-streaming'); ?>
                </button>
                <button id="refresh-stats" class="ltv-button ltv-button-primary">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('Refresh', 'live-tv-streaming'); ?>
                </button>
            </div>
        </div>
        
        <!-- User Statistics -->
        <div class="ltv-stats-grid">
            <div class="ltv-stat-card">
                <div class="ltv-stat-value"><?php echo number_format($users_with_favorites); ?></div>
                <div class="ltv-stat-label"><?php _e('Users with Favorites', 'live-tv-streaming'); ?></div>
            </div>
            
            <div class="ltv-stat-card">
                <div class="ltv-stat-value"><?php echo number_format($total_favorites); ?></div>
                <div class="ltv-stat-label"><?php _e('Total Favorites', 'live-tv-streaming'); ?></div>
            </div>
            
            <div class="ltv-stat-card">
                <div class="ltv-stat-value"><?php echo number_format($total_history); ?></div>
                <div class="ltv-stat-label"><?php _e('Watch History Entries', 'live-tv-streaming'); ?></div>
            </div>
            
            <div class="ltv-stat-card">
                <div class="ltv-stat-value"><?php echo number_format($custom_playlists); ?></div>
                <div class="ltv-stat-label"><?php _e('Custom Playlists', 'live-tv-streaming'); ?></div>
            </div>
        </div>
        
        <div class="ltv-grid ltv-grid-2">
            <!-- Popular Channels -->
            <div class="ltv-card">
                <div class="ltv-card-header">
                    <h3><?php _e('Most Favorited Channels', 'live-tv-streaming'); ?></h3>
                </div>
                <div class="ltv-card-content" style="padding: 0;">
                    <table class="ltv-table">
                        <thead>
                            <tr>
                                <th><?php _e('Rank', 'live-tv-streaming'); ?></th>
                                <th><?php _e('Channel Name', 'live-tv-streaming'); ?></th>
                                <th><?php _e('Favorites', 'live-tv-streaming'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($popular_channels)): ?>
                                <?php foreach ($popular_channels as $index => $channel): ?>
                                    <tr>
                                        <td><span class="rank-badge">#<?php echo ($index + 1); ?></span></td>
                                        <td><?php echo esc_html($channel->name); ?></td>
                                        <td><span class="favorite-count"><?php echo number_format($channel->favorite_count); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="ltv-text-center">
                                        <div style="padding: var(--ltv-space-xl); color: var(--ltv-gray-500);">
                                            <span class="dashicons dashicons-heart" style="font-size: 32px; margin-bottom: 10px; display: block;"></span>
                                            <?php _e('No favorite data available.', 'live-tv-streaming'); ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="ltv-card">
                <div class="ltv-card-header">
                    <h3><?php _e('Recent Viewing Activity', 'live-tv-streaming'); ?></h3>
                </div>
                <div class="ltv-card-content" style="padding: 0;">
                    <table class="ltv-table">
                        <thead>
                            <tr>
                                <th><?php _e('User', 'live-tv-streaming'); ?></th>
                                <th><?php _e('Channel', 'live-tv-streaming'); ?></th>
                                <th><?php _e('Duration', 'live-tv-streaming'); ?></th>
                                <th><?php _e('Date', 'live-tv-streaming'); ?></th>
                                <th><?php _e('Device', 'live-tv-streaming'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($recent_activity)): ?>
                                <?php foreach ($recent_activity as $activity): ?>
                                    <tr>
                                        <td>
                                            <?php 
                                            if ($activity->display_name) {
                                                echo '<span class="user-name">' . esc_html($activity->display_name) . '</span>';
                                            } else {
                                                echo '<span class="guest-user"><em>' . __('Guest User', 'live-tv-streaming') . '</em></span>';
                                            }
                                            ?>
                                        </td>
                                        <td><span class="channel-name"><?php echo esc_html($activity->channel_name); ?></span></td>
                                        <td><span class="duration-badge"><?php echo gmdate('H:i:s', $activity->watch_duration); ?></span></td>
                                        <td><span class="activity-date"><?php echo date('M j, Y g:i A', strtotime($activity->watch_date)); ?></span></td>
                                        <td><span class="device-badge device-<?php echo strtolower($activity->device_type); ?>"><?php echo ucfirst($activity->device_type); ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="ltv-text-center">
                                        <div style="padding: var(--ltv-space-xl); color: var(--ltv-gray-500);">
                                            <span class="dashicons dashicons-clock" style="font-size: 32px; margin-bottom: 10px; display: block;"></span>
                                            <?php _e('No recent activity available.', 'live-tv-streaming'); ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Data Management Tools -->
        <div class="ltv-card ltv-mt-lg">
            <div class="ltv-card-header">
                <h3><?php _e('Data Management & Privacy Compliance', 'live-tv-streaming'); ?></h3>
            </div>
            <div class="ltv-card-content">
                <p class="ltv-description"><?php _e('Manage user data and preferences for privacy compliance. All data operations are logged for audit purposes.', 'live-tv-streaming'); ?></p>
                
                <div class="ltv-flex" style="gap: var(--ltv-space-md); flex-wrap: wrap; margin: var(--ltv-space-lg) 0;">
                    <button class="ltv-button ltv-button-secondary" onclick="exportUserData()">
                        <span class="dashicons dashicons-download"></span>
                        <?php _e('Export User Data', 'live-tv-streaming'); ?>
                    </button>
                    
                    <button class="ltv-button ltv-button-warning" onclick="clearOldSessions()">
                        <span class="dashicons dashicons-trash"></span>
                        <?php _e('Clear Old Sessions', 'live-tv-streaming'); ?>
                    </button>
                    
                    <button class="ltv-button ltv-button-primary" onclick="optimizeDatabase()">
                        <span class="dashicons dashicons-performance"></span>
                        <?php _e('Optimize Database', 'live-tv-streaming'); ?>
                    </button>
                </div>
                
                <div class="ltv-notification ltv-notification-info">
                    <span class="dashicons dashicons-shield"></span>
                    <div>
                        <strong><?php _e('Privacy Note:', 'live-tv-streaming'); ?></strong> 
                        <?php _e('This plugin collects viewing data to improve user experience. Session data for guest users is anonymized. Registered user data can be exported or deleted upon request in compliance with GDPR and privacy regulations.', 'live-tv-streaming'); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for User Management -->
<script>
jQuery(document).ready(function($) {
    'use strict';
    
    // Header export button
    $('#export-user-data').on('click', exportUserData);
    
    // Refresh stats
    $('#refresh-stats').on('click', function() {
        location.reload();
    });
    
    function showNotification(message, type = 'info') {
        const notification = `
            <div class="ltv-notification ltv-notification-${type}" style="position: fixed; top: 32px; right: 20px; z-index: 9999; min-width: 300px;">
                <span class="dashicons dashicons-${type === 'success' ? 'yes' : type === 'error' ? 'no' : 'info'}"></span>
                ${message}
            </div>
        `;
        
        $('body').append(notification);
        
        setTimeout(() => {
            $('.ltv-notification').fadeOut(() => {
                $('.ltv-notification').remove();
            });
        }, 5000);
    }
    
    window.exportUserData = function() {
        if (confirm('<?php _e('Export all user data including favorites, history, and playlists?', 'live-tv-streaming'); ?>')) {
            showNotification('<?php _e('Preparing export...', 'live-tv-streaming'); ?>', 'info');
            window.location.href = '<?php echo admin_url('admin-ajax.php'); ?>?action=export_user_data&nonce=<?php echo wp_create_nonce('live_tv_admin_nonce'); ?>';
        }
    };
    
    window.clearOldSessions = function() {
        if (confirm('<?php _e('Clear session data older than 30 days? This cannot be undone.', 'live-tv-streaming'); ?>')) {
            const $btn = $('button[onclick="clearOldSessions()"]');
            const originalText = $btn.html();
            
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> <?php _e('Clearing...', 'live-tv-streaming'); ?>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'clear_old_sessions',
                    nonce: '<?php echo wp_create_nonce('live_tv_admin_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        showNotification('<?php _e('Old session data cleared successfully.', 'live-tv-streaming'); ?>', 'success');
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        showNotification(response.data || '<?php _e('Failed to clear session data.', 'live-tv-streaming'); ?>', 'error');
                    }
                },
                error: function() {
                    showNotification('<?php _e('Network error occurred.', 'live-tv-streaming'); ?>', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        }
    };
    
    window.optimizeDatabase = function() {
        if (confirm('<?php _e('Optimize database tables? This may take a moment.', 'live-tv-streaming'); ?>')) {
            const $btn = $('button[onclick="optimizeDatabase()"]');
            const originalText = $btn.html();
            
            $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> <?php _e('Optimizing...', 'live-tv-streaming'); ?>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'optimize_database',
                    nonce: '<?php echo wp_create_nonce('live_tv_admin_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        showNotification('<?php _e('Database optimized successfully.', 'live-tv-streaming'); ?>', 'success');
                    } else {
                        showNotification(response.data || '<?php _e('Failed to optimize database.', 'live-tv-streaming'); ?>', 'error');
                    }
                },
                error: function() {
                    showNotification('<?php _e('Network error occurred.', 'live-tv-streaming'); ?>', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        }
    };
});
</script>

<!-- Additional CSS for User Management Page -->
<style>
/* User-specific enhancements */
.rank-badge {
    background: var(--ltv-primary);
    color: white;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: var(--ltv-font-size-xs);
    font-weight: 600;
}

.favorite-count {
    background: var(--ltv-success);
    color: white;
    padding: 4px 8px;
    border-radius: var(--ltv-radius-sm);
    font-size: var(--ltv-font-size-sm);
    font-weight: 500;
}

.user-name {
    font-weight: 600;
    color: var(--ltv-gray-800);
}

.guest-user {
    color: var(--ltv-gray-500);
    font-style: italic;
}

.channel-name {
    font-weight: 500;
    color: var(--ltv-primary);
}

.duration-badge {
    background: var(--ltv-info);
    color: white;
    padding: 2px 6px;
    border-radius: var(--ltv-radius-sm);
    font-family: 'Courier New', monospace;
    font-size: var(--ltv-font-size-xs);
}

.activity-date {
    color: var(--ltv-gray-600);
    font-size: var(--ltv-font-size-sm);
}

.device-badge {
    padding: 2px 8px;
    border-radius: 10px;
    font-size: var(--ltv-font-size-xs);
    font-weight: 500;
    text-transform: uppercase;
    color: white;
}

.device-desktop {
    background: var(--ltv-primary);
}

.device-mobile {
    background: var(--ltv-success);
}

.device-tablet {
    background: var(--ltv-warning);
    color: var(--ltv-gray-800);
}

/* Loading and animation styles */
.spin {
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}
</style>