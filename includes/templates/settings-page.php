<?php
if (!defined('ABSPATH')) exit('Direct access denied.');

// Handle form submission
if (isset($_POST['submit']) && wp_verify_nonce($_POST['live_tv_settings_nonce'], 'live_tv_settings')) {
    $options = array(
        'autoplay' => sanitize_text_field($_POST['autoplay'] ?? 'false'),
        'mobile_optimized' => sanitize_text_field($_POST['mobile_optimized'] ?? 'true'),
        'cast_enabled' => sanitize_text_field($_POST['cast_enabled'] ?? 'true'),
        'player_theme' => sanitize_text_field($_POST['player_theme'] ?? 'premium-blue')
    );
    
    foreach ($options as $option => $value) {
        update_option('live_tv_' . $option, $value);
    }
    
    $success_message = __('Settings saved successfully!', 'live-tv-streaming');
}

// Get current settings
$autoplay = get_option('live_tv_autoplay', 'false');
$mobile_optimized = get_option('live_tv_mobile_optimized', 'true');
$cast_enabled = get_option('live_tv_cast_enabled', 'true');
?>

<div class="wrap">
    <div class="live-tv-admin-wrap ltv-admin">
        <div class="live-tv-admin-header">
            <h1>
                <span class="dashicons dashicons-admin-settings" style="font-size: 32px; margin-right: 10px;"></span>
                <?php _e('Plugin Settings', 'live-tv-streaming'); ?>
            </h1>
            <div class="header-actions">
                <button type="button" id="reset-defaults" class="ltv-button ltv-button-secondary">
                    <span class="dashicons dashicons-undo"></span>
                    <?php _e('Reset to Defaults', 'live-tv-streaming'); ?>
                </button>
            </div>
        </div>
        
        <?php if (isset($success_message)): ?>
            <div class="ltv-notification ltv-notification-success">
                <span class="dashicons dashicons-yes"></span>
                <?php echo esc_html($success_message); ?>
            </div>
        <?php endif; ?>
        
        <div class="ltv-grid ltv-grid-2">
            <!-- Player Settings -->
            <div class="ltv-card">
                <div class="ltv-card-header">
                    <h2><?php _e('Player Settings', 'live-tv-streaming'); ?></h2>
                </div>
                <div class="ltv-card-content">
                    <form method="post" action="" class="ltv-form">
                        <?php wp_nonce_field('live_tv_settings', 'live_tv_settings_nonce'); ?>
                        
                        <div class="ltv-form-group">
                            <label for="autoplay" class="ltv-label">
                                <span class="dashicons dashicons-controls-play"></span>
                                <?php _e('Autoplay', 'live-tv-streaming'); ?>
                            </label>
                            <select name="autoplay" id="autoplay" class="ltv-select">
                                <option value="false" <?php selected($autoplay, 'false'); ?>><?php _e('Disabled', 'live-tv-streaming'); ?></option>
                                <option value="true" <?php selected($autoplay, 'true'); ?>><?php _e('Enabled', 'live-tv-streaming'); ?></option>
                            </select>
                            <p class="ltv-description"><?php _e('Enable autoplay for video streams. Note: Most browsers block autoplay with sound for better user experience.', 'live-tv-streaming'); ?></p>
                        </div>
                        
                        <div class="ltv-form-group">
                            <label for="mobile_optimized" class="ltv-label">
                                <span class="dashicons dashicons-smartphone"></span>
                                <?php _e('Mobile Optimization', 'live-tv-streaming'); ?>
                            </label>
                            <select name="mobile_optimized" id="mobile_optimized" class="ltv-select">
                                <option value="true" <?php selected($mobile_optimized, 'true'); ?>><?php _e('Enabled', 'live-tv-streaming'); ?></option>
                                <option value="false" <?php selected($mobile_optimized, 'false'); ?>><?php _e('Disabled', 'live-tv-streaming'); ?></option>
                            </select>
                            <p class="ltv-description"><?php _e('Enable mobile-specific optimizations including touch controls, gesture support, and responsive layouts.', 'live-tv-streaming'); ?></p>
                        </div>
                        
                        <div class="ltv-form-group">
                            <label for="cast_enabled" class="ltv-label">
                                <span class="dashicons dashicons-screenoptions"></span>
                                <?php _e('Google Cast Support', 'live-tv-streaming'); ?>
                            </label>
                            <select name="cast_enabled" id="cast_enabled" class="ltv-select">
                                <option value="true" <?php selected($cast_enabled, 'true'); ?>><?php _e('Enabled', 'live-tv-streaming'); ?></option>
                                <option value="false" <?php selected($cast_enabled, 'false'); ?>><?php _e('Disabled', 'live-tv-streaming'); ?></option>
                            </select>
                            <p class="ltv-description"><?php _e('Enable Google Cast (Chromecast) support for streaming to TV devices. Requires HTTPS for security.', 'live-tv-streaming'); ?></p>
                        </div>
                        
                        
                        <div class="ltv-form-group">
                            <label class="ltv-label">
                                <span class="dashicons dashicons-admin-customizer"></span>
                                <?php _e('Player Theme', 'live-tv-streaming'); ?>
                            </label>
                            <div id="player-theme-selector" style="margin-top: 10px;">
                                <p class="ltv-description" style="margin-bottom: 15px;"><?php _e('Choose the appearance theme for the video player that your visitors will see.', 'live-tv-streaming'); ?></p>
                                <div class="player-theme-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 15px; margin-top: 10px;">
                                    
                                    <div class="player-theme-item" data-theme="premium-blue" style="cursor: pointer; padding: 15px; border: 2px solid transparent; border-radius: 12px; transition: all 0.2s ease; background: linear-gradient(135deg, #0f1419, #1a202c); position: relative;">
                                        <div style="display: flex; align-items: center; margin-bottom: 10px;">
                                            <div class="theme-color-preview" style="width: 24px; height: 24px; border-radius: 50%; background: linear-gradient(135deg, #00d4ff, #0099cc); display: inline-block; margin-right: 12px; box-shadow: 0 2px 8px rgba(0, 212, 255, 0.3);"></div>
                                            <span style="font-weight: 600; color: #00d4ff;">🎯 <?php _e('Premium Streaming Blue', 'live-tv-streaming'); ?></span>
                                        </div>
                                        <p style="font-size: 12px; color: #a0aec0; margin: 0; line-height: 1.4;"><?php _e('Netflix-inspired theme with cyan accents. Perfect for professional streaming platforms.', 'live-tv-streaming'); ?></p>
                                    </div>
                                    
                                    <div class="player-theme-item" data-theme="tech-purple" style="cursor: pointer; padding: 15px; border: 2px solid transparent; border-radius: 12px; transition: all 0.2s ease; background: linear-gradient(135deg, #0f0f23, #1a1a2e); position: relative;">
                                        <div style="display: flex; align-items: center; margin-bottom: 10px;">
                                            <div class="theme-color-preview" style="width: 24px; height: 24px; border-radius: 50%; background: linear-gradient(135deg, #7c3aed, #5b21b6); display: inline-block; margin-right: 12px; box-shadow: 0 2px 8px rgba(124, 58, 237, 0.3);"></div>
                                            <span style="font-weight: 600; color: #7c3aed;">🚀 <?php _e('Modern Tech Purple', 'live-tv-streaming'); ?></span>
                                        </div>
                                        <p style="font-size: 12px; color: #a0aec0; margin: 0; line-height: 1.4;"><?php _e('Discord/Twitch inspired with rich purple tones. Great for gaming and tech-focused audiences.', 'live-tv-streaming'); ?></p>
                                    </div>
                                    
                                    <div class="player-theme-item" data-theme="premium-gold" style="cursor: pointer; padding: 15px; border: 2px solid transparent; border-radius: 12px; transition: all 0.2s ease; background: linear-gradient(135deg, #1c1917, #292524); position: relative;">
                                        <div style="display: flex; align-items: center; margin-bottom: 10px;">
                                            <div class="theme-color-preview" style="width: 24px; height: 24px; border-radius: 50%; background: linear-gradient(135deg, #fbbf24, #d97706); display: inline-block; margin-right: 12px; box-shadow: 0 2px 8px rgba(251, 191, 36, 0.3);"></div>
                                            <span style="font-weight: 600; color: #fbbf24;">👑 <?php _e('Premium Gold Accent', 'live-tv-streaming'); ?></span>
                                        </div>
                                        <p style="font-size: 12px; color: #d6d3d1; margin: 0; line-height: 1.4;"><?php _e('Luxury streaming service theme with golden highlights. Conveys premium quality and elegance.', 'live-tv-streaming'); ?></p>
                                    </div>
                                    
                                    <div class="player-theme-item" data-theme="gaming-green" style="cursor: pointer; padding: 15px; border: 2px solid transparent; border-radius: 12px; transition: all 0.2s ease; background: linear-gradient(135deg, #0a0e0a, #1a1f1a); position: relative;">
                                        <div style="display: flex; align-items: center; margin-bottom: 10px;">
                                            <div class="theme-color-preview" style="width: 24px; height: 24px; border-radius: 50%; background: linear-gradient(135deg, #00ff41, #00cc33); display: inline-block; margin-right: 12px; box-shadow: 0 2px 8px rgba(0, 255, 65, 0.3);"></div>
                                            <span style="font-weight: 600; color: #00ff41;">⚡ <?php _e('Electric Gaming Green', 'live-tv-streaming'); ?></span>
                                        </div>
                                        <p style="font-size: 12px; color: #a7f3d0; margin: 0; line-height: 1.4;"><?php _e('High-energy gaming theme with electric green accents. Perfect for gaming streams and esports.', 'live-tv-streaming'); ?></p>
                                    </div>
                                    
                                </div>
                                <input type="hidden" name="player_theme" id="player_theme" value="<?php echo esc_attr(get_option('live_tv_player_theme', 'premium-blue')); ?>">
                            </div>
                        </div>
                        
                </div>
                <div class="ltv-card-footer">
                    <button type="submit" name="submit" class="ltv-button ltv-button-primary">
                        <span class="dashicons dashicons-saved"></span>
                        <?php _e('Save Settings', 'live-tv-streaming'); ?>
                    </button>
                </div>
                    </form>
            </div>
            
            <!-- Usage Instructions -->
            <div class="ltv-card">
                <div class="ltv-card-header">
                    <h2><?php _e('Usage Instructions', 'live-tv-streaming'); ?></h2>
                </div>
                <div class="ltv-card-content">
                    <div class="instruction-section">
                        <h4 style="color: var(--ltv-primary); margin-bottom: var(--ltv-space-sm);">
                            <span class="dashicons dashicons-editor-code"></span>
                            <?php _e('Basic Shortcode', 'live-tv-streaming'); ?>
                        </h4>
                        <p><?php _e('Use the following shortcode to display the live TV player:', 'live-tv-streaming'); ?></p>
                        <div class="code-block">
                            <code>[live_tv_player]</code>
                            <button type="button" class="copy-code-btn" onclick="copyToClipboard('[live_tv_player]')">
                                <span class="dashicons dashicons-clipboard"></span>
                            </button>
                        </div>
                    </div>
                    
                    <div class="instruction-section">
                        <h4 style="color: var(--ltv-primary); margin-bottom: var(--ltv-space-sm);">
                            <span class="dashicons dashicons-admin-tools"></span>
                            <?php _e('Advanced Parameters', 'live-tv-streaming'); ?>
                        </h4>
                        <div class="parameter-list">
                            <div class="parameter-item">
                                <div class="code-block">
                                    <code>[live_tv_player width="800" height="450"]</code>
                                    <button type="button" class="copy-code-btn" onclick="copyToClipboard('[live_tv_player width=&quot;800&quot; height=&quot;450&quot;]')">
                                        <span class="dashicons dashicons-clipboard"></span>
                                    </button>
                                </div>
                                <span class="parameter-desc"><?php _e('Custom dimensions', 'live-tv-streaming'); ?></span>
                            </div>
                            <div class="parameter-item">
                                <div class="code-block">
                                    <code>[live_tv_player category="News"]</code>
                                    <button type="button" class="copy-code-btn" onclick="copyToClipboard('[live_tv_player category=&quot;News&quot;]')">
                                        <span class="dashicons dashicons-clipboard"></span>
                                    </button>
                                </div>
                                <span class="parameter-desc"><?php _e('Show only specific category', 'live-tv-streaming'); ?></span>
                            </div>
                            <div class="parameter-item">
                                <div class="code-block">
                                    <code>[live_tv_player autoplay="true"]</code>
                                    <button type="button" class="copy-code-btn" onclick="copyToClipboard('[live_tv_player autoplay=&quot;true&quot;]')">
                                        <span class="dashicons dashicons-clipboard"></span>
                                    </button>
                                </div>
                                <span class="parameter-desc"><?php _e('Enable autoplay (overrides global setting)', 'live-tv-streaming'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Features & Plugin Information -->
        <div class="ltv-grid ltv-grid-2">
            <div class="ltv-card">
                <div class="ltv-card-header">
                    <h3><?php _e('Professional Features', 'live-tv-streaming'); ?></h3>
                </div>
                <div class="ltv-card-content">
                    <div class="feature-list">
                        <div class="feature-item">
                            <span class="feature-icon success">✅</span>
                            <span><?php _e('Mobile-optimized responsive design', 'live-tv-streaming'); ?></span>
                        </div>
                        <div class="feature-item">
                            <span class="feature-icon success">✅</span>
                            <span><?php _e('Google Cast (Chromecast) integration', 'live-tv-streaming'); ?></span>
                        </div>
                        <div class="feature-item">
                            <span class="feature-icon success">✅</span>
                            <span><?php _e('Touch gesture controls for mobile', 'live-tv-streaming'); ?></span>
                        </div>
                        <div class="feature-item">
                            <span class="feature-icon success">✅</span>
                            <span><?php _e('Advanced error handling and fallback', 'live-tv-streaming'); ?></span>
                        </div>
                        <div class="feature-item">
                            <span class="feature-icon success">✅</span>
                            <span><?php _e('Accessibility enhancements (WCAG compliant)', 'live-tv-streaming'); ?></span>
                        </div>
                        <div class="feature-item">
                            <span class="feature-icon success">✅</span>
                            <span><?php _e('Performance monitoring and optimization', 'live-tv-streaming'); ?></span>
                        </div>
                        <div class="feature-item">
                            <span class="feature-icon success">✅</span>
                            <span><?php _e('Advanced analytics and reporting', 'live-tv-streaming'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="ltv-card">
                <div class="ltv-card-header">
                    <h3><?php _e('System Information', 'live-tv-streaming'); ?></h3>
                </div>
                <div class="ltv-card-content">
                    <div class="system-info">
                        <div class="info-item">
                            <div class="info-label">
                                <span class="dashicons dashicons-admin-plugins"></span>
                                <?php _e('Plugin Version', 'live-tv-streaming'); ?>
                            </div>
                            <div class="info-value"><?php echo esc_html(LIVE_TV_VERSION); ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">
                                <span class="dashicons dashicons-video-alt2"></span>
                                <?php _e('Active Channels', 'live-tv-streaming'); ?>
                            </div>
                            <div class="info-value">
                                <?php
                                global $wpdb;
                                $count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}live_tv_channels WHERE is_active = 1");
                                echo esc_html($count);
                                ?>
                            </div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">
                                <span class="dashicons dashicons-wordpress"></span>
                                <?php _e('WordPress Version', 'live-tv-streaming'); ?>
                            </div>
                            <div class="info-value"><?php echo esc_html(get_bloginfo('version')); ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">
                                <span class="dashicons dashicons-admin-tools"></span>
                                <?php _e('PHP Version', 'live-tv-streaming'); ?>
                            </div>
                            <div class="info-value"><?php echo esc_html(PHP_VERSION); ?></div>
                        </div>
                        
                        <div class="info-item">
                            <div class="info-label">
                                <span class="dashicons dashicons-database"></span>
                                <?php _e('Database Status', 'live-tv-streaming'); ?>
                            </div>
                            <div class="info-value">
                                <span class="status-badge status-active"><?php _e('Connected', 'live-tv-streaming'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php
        // Add update settings section
        if (class_exists('LiveTVPluginUpdater') && is_admin()) {
            $updater = new LiveTVPluginUpdater(LIVE_TV_PLUGIN_PATH . 'live-tv-streaming.php', LIVE_TV_VERSION);
            $updater->update_settings_section();
        }
        ?>
    </div>
</div>

<!-- JavaScript for Settings Page -->
<script>
jQuery(document).ready(function($) {
    'use strict';
    
    // Reset defaults button
    $('#reset-defaults').on('click', function() {
        if (confirm('<?php _e('Reset all settings to default values? This cannot be undone.', 'live-tv-streaming'); ?>')) {
            $('#autoplay').val('false');
            $('#mobile_optimized').val('true');
            $('#cast_enabled').val('true');
            $('#player_theme').val('premium-blue');
            
            // Reset player theme UI
            $('.player-theme-item').css({
                'border-color': 'transparent',
                'transform': 'none',
                'box-shadow': 'none'
            }).removeClass('active');
            
            $('.player-theme-item[data-theme="premium-blue"]').addClass('active').css({
                'border-color': '#00d4ff',
                'transform': 'translateY(-2px) scale(1.02)',
                'box-shadow': '0 8px 25px rgba(0, 212, 255, 0.2)'
            });
            
            showNotification('<?php _e('Settings reset to defaults. Click "Save Settings" to apply.', 'live-tv-streaming'); ?>', 'info');
        }
    });
    
    // Show notification helper
    function showNotification(message, type = 'info') {
        const notification = `
            <div class="ltv-notification ltv-notification-${type}" style="margin-bottom: var(--ltv-space-lg);">
                <span class="dashicons dashicons-${type === 'success' ? 'yes' : type === 'error' ? 'no' : 'info'}"></span>
                ${message}
            </div>
        `;
        
        $('.live-tv-admin-header').after(notification);
        
        setTimeout(() => {
            $('.ltv-notification').fadeOut(() => {
                $('.ltv-notification').remove();
            });
        }, 5000);
    }
    
    // Copy to clipboard function with enhanced UX
    window.copyToClipboard = function(text, button) {
        const $btn = button ? $(button) : $(event.target).closest('.copy-code-btn');
        
        navigator.clipboard.writeText(text).then(() => {
            // Add success animation
            $btn.addClass('copied');
            $btn.find('.dashicons').removeClass('dashicons-clipboard').addClass('dashicons-yes');
            
            showNotification('<?php _e('Shortcode copied to clipboard!', 'live-tv-streaming'); ?>', 'success');
            
            // Reset button after animation
            setTimeout(() => {
                $btn.removeClass('copied');
                $btn.find('.dashicons').removeClass('dashicons-yes').addClass('dashicons-clipboard');
            }, 600);
            
        }).catch(() => {
            // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            
            // Add success animation for fallback too
            $btn.addClass('copied');
            $btn.find('.dashicons').removeClass('dashicons-clipboard').addClass('dashicons-yes');
            
            showNotification('<?php _e('Shortcode copied to clipboard!', 'live-tv-streaming'); ?>', 'success');
            
            setTimeout(() => {
                $btn.removeClass('copied');
                $btn.find('.dashicons').removeClass('dashicons-yes').addClass('dashicons-clipboard');
            }, 600);
        });
    };
    
    // Player theme selector functionality
    $('.player-theme-item').on('click', function(e) {
        e.preventDefault();
        const selectedTheme = $(this).data('theme');
        const themeName = $(this).find('span').text().replace(/^[🎯🚀👑⚡]\s*/, ''); // Remove emoji
        
        // Update hidden input
        $('#player_theme').val(selectedTheme);
        
        // Update UI - remove active class from all items
        $('.player-theme-item').css({
            'border-color': 'transparent',
            'transform': 'none',
            'box-shadow': 'none'
        }).removeClass('active');
        
        // Add active class to selected item
        $(this).addClass('active').css({
            'border-color': getPlayerThemeColor(selectedTheme),
            'transform': 'translateY(-2px) scale(1.02)',
            'box-shadow': '0 8px 25px ' + getPlayerThemeColorRgba(selectedTheme)
        });
        
        // Show notification
        showNotification('<?php _e('Player theme selected:', 'live-tv-streaming'); ?> ' + themeName + '. <?php _e('Click "Save Settings" to apply.', 'live-tv-streaming'); ?>', 'success');
        
        console.log('Player theme selected:', selectedTheme);
    });
    
    // Helper function to get theme colors
    function getPlayerThemeColor(theme) {
        const colors = {
            'premium-blue': '#00d4ff',
            'tech-purple': '#7c3aed',
            'premium-gold': '#fbbf24',
            'gaming-green': '#00ff41'
        };
        return colors[theme] || '#00d4ff';
    }
    
    // Helper function to get theme colors with alpha
    function getPlayerThemeColorRgba(theme) {
        const colors = {
            'premium-blue': 'rgba(0, 212, 255, 0.2)',
            'tech-purple': 'rgba(124, 58, 237, 0.2)',
            'premium-gold': 'rgba(251, 191, 36, 0.2)',
            'gaming-green': 'rgba(0, 255, 65, 0.2)'
        };
        return colors[theme] || 'rgba(0, 212, 255, 0.2)';
    }
    
    // Load active theme on page ready
    function loadActivePlayerTheme() {
        const currentTheme = $('#player_theme').val();
        if (currentTheme) {
            $('.player-theme-item[data-theme="' + currentTheme + '"]').addClass('active').css({
                'border-color': getPlayerThemeColor(currentTheme),
                'transform': 'translateY(-2px) scale(1.02)',
                'box-shadow': '0 8px 25px ' + getPlayerThemeColorRgba(currentTheme)
            });
        }
    }
    
    // Initialize active theme display
    loadActivePlayerTheme();
    
});
</script>

<!-- Additional CSS for Settings Page -->
<style>
/* Settings-specific enhancements */
.instruction-section {
    margin-bottom: var(--ltv-space-xl);
}

.instruction-section:last-child {
    margin-bottom: 0;
}

.code-block {
    position: relative;
    background: var(--ltv-gray-800);
    color: var(--ltv-gray-100);
    padding: var(--ltv-space-md);
    border-radius: var(--ltv-radius-sm);
    font-family: 'Courier New', monospace;
    margin: var(--ltv-space-sm) 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.code-block code {
    background: none;
    color: inherit;
    padding: 0;
    font-size: var(--ltv-font-size-sm);
}

.copy-code-btn {
    background: var(--ltv-primary);
    color: white;
    border: none;
    padding: var(--ltv-space-xs);
    border-radius: var(--ltv-radius-sm);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s ease;
}

.copy-code-btn:hover {
    background: var(--ltv-primary-light);
    transform: translateY(-1px);
}

.parameter-list {
    display: flex;
    flex-direction: column;
    gap: var(--ltv-space-md);
}

.parameter-item {
    display: flex;
    flex-direction: column;
    gap: var(--ltv-space-xs);
}

.parameter-desc {
    font-size: var(--ltv-font-size-sm);
    color: var(--ltv-gray-600);
    margin-left: var(--ltv-space-sm);
}

.feature-list {
    display: flex;
    flex-direction: column;
    gap: var(--ltv-space-sm);
}

.feature-item {
    display: flex;
    align-items: center;
    gap: var(--ltv-space-sm);
    padding: var(--ltv-space-xs) 0;
    border-bottom: 1px solid var(--ltv-gray-100);
}

.feature-item:last-child {
    border-bottom: none;
}

.feature-icon.success {
    color: var(--ltv-success);
    font-size: var(--ltv-font-size-lg);
}

.system-info {
    display: flex;
    flex-direction: column;
    gap: var(--ltv-space-md);
}

.info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--ltv-space-sm) 0;
    border-bottom: 1px solid var(--ltv-gray-100);
}

.info-item:last-child {
    border-bottom: none;
}

.info-label {
    display: flex;
    align-items: center;
    gap: var(--ltv-space-xs);
    font-weight: 500;
    color: var(--ltv-gray-700);
}

.info-value {
    font-weight: 600;
    color: var(--ltv-gray-800);
}

.status-badge {
    padding: 2px 8px;
    border-radius: 10px;
    font-size: var(--ltv-font-size-xs);
    font-weight: 500;
    text-transform: uppercase;
}

.status-active {
    background: var(--ltv-success);
    color: white;
}

/* Player theme selector styles */
.player-theme-item {
    position: relative;
    transition: all 0.2s ease;
    border: 2px solid transparent !important;
}

.player-theme-item:hover {
    transform: translateY(-2px);
    border-color: rgba(255, 255, 255, 0.1) !important;
}

.player-theme-item.active {
    transform: translateY(-2px) scale(1.02);
}

.player-theme-grid {
    margin: var(--ltv-space-md) 0;
}

.theme-color-preview {
    flex-shrink: 0;
}

@media (max-width: 768px) {
    .parameter-item {
        gap: var(--ltv-space-sm);
    }
    
    .code-block {
        flex-direction: column;
        align-items: flex-start;
        gap: var(--ltv-space-sm);
    }
    
    .copy-code-btn {
        align-self: flex-end;
    }
    
    .info-item {
        flex-direction: column;
        align-items: flex-start;
        gap: var(--ltv-space-xs);
    }
    
    .player-theme-grid {
        grid-template-columns: 1fr !important;
        gap: 10px;
    }
    
    .player-theme-item {
        padding: 12px !important;
    }
}
</style>