<?php
/**
 * Player Customization Admin Page Template
 * 
 * Professional interface for customizing player visual enhancements
 * 
 * @package LiveTVStreaming
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

// Handle form submission
if (isset($_POST['submit']) && wp_verify_nonce($_POST['player_customization_nonce'], 'player_customization')) {
    $customizations = array(
        'branding_enabled' => sanitize_text_field($_POST['branding_enabled'] ?? 'false'),
        'branding_type' => sanitize_text_field($_POST['branding_type'] ?? 'text'),
        'branding_text' => sanitize_text_field($_POST['branding_text'] ?? ''),
        'branding_logo_url' => esc_url_raw($_POST['branding_logo_url'] ?? ''),
        'branding_position' => sanitize_text_field($_POST['branding_position'] ?? 'top-right'),
        'branding_opacity' => floatval($_POST['branding_opacity'] ?? 0.7),
        'loading_animation' => sanitize_text_field($_POST['loading_animation'] ?? 'professional'),
        'control_bar_style' => sanitize_text_field($_POST['control_bar_style'] ?? 'professional'),
        'transition_effects' => sanitize_text_field($_POST['transition_effects'] ?? 'true'),
        'hover_effects' => sanitize_text_field($_POST['hover_effects'] ?? 'true'),
        'center_play_button' => sanitize_text_field($_POST['center_play_button'] ?? 'true'),
        'welcome_message' => sanitize_text_field($_POST['welcome_message'] ?? 'Welcome to {channel_name}'),
        'custom_css' => wp_strip_all_tags($_POST['custom_css'] ?? ''),
    );
    
    foreach ($customizations as $option => $value) {
        update_option('live_tv_' . $option, $value);
    }
    
    $success_message = __('Player customization settings saved successfully!', 'live-tv-streaming');
}

// Get current settings
$branding_enabled = get_option('live_tv_branding_enabled', 'false');
$branding_type = get_option('live_tv_branding_type', 'text');
$branding_text = get_option('live_tv_branding_text', 'Live TV Pro');
$branding_logo_url = get_option('live_tv_branding_logo_url', '');
$branding_position = get_option('live_tv_branding_position', 'top-right');
$branding_opacity = get_option('live_tv_branding_opacity', 0.7);
$loading_animation = get_option('live_tv_loading_animation', 'professional');
$control_bar_style = get_option('live_tv_control_bar_style', 'professional');
$transition_effects = get_option('live_tv_transition_effects', 'true');
$hover_effects = get_option('live_tv_hover_effects', 'true');
$custom_css = get_option('live_tv_custom_css', '');
?>

<div class="wrap">
    <div class="live-tv-admin-wrap ltv-admin">
        <div class="live-tv-admin-header">
            <h1>
                <span class="dashicons dashicons-art" style="font-size: 32px; margin-right: 10px;"></span>
                <?php _e('Player Customization', 'live-tv-streaming'); ?>
            </h1>
            <div class="header-actions">
                <button type="button" id="reset-customization" class="ltv-button ltv-button-secondary">
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
            <!-- Branding & Visual Settings -->
            <div class="ltv-card">
                <div class="ltv-card-header">
                    <h2><?php _e('Professional Branding', 'live-tv-streaming'); ?></h2>
                </div>
                <div class="ltv-card-content">
                    <form method="post" action="" class="ltv-form">
                        <?php wp_nonce_field('player_customization', 'player_customization_nonce'); ?>
                        
                        <div class="ltv-form-group">
                            <label for="branding_enabled" class="ltv-label">
                                <span class="dashicons dashicons-admin-appearance"></span>
                                <?php _e('Enable Branding', 'live-tv-streaming'); ?>
                            </label>
                            <select name="branding_enabled" id="branding_enabled" class="ltv-select">
                                <option value="false" <?php selected($branding_enabled, 'false'); ?>><?php _e('Disabled', 'live-tv-streaming'); ?></option>
                                <option value="true" <?php selected($branding_enabled, 'true'); ?>><?php _e('Enabled', 'live-tv-streaming'); ?></option>
                            </select>
                            <p class="ltv-description"><?php _e('Show professional branding/watermark on the video player.', 'live-tv-streaming'); ?></p>
                        </div>
                        
                        <div class="ltv-form-group" id="branding-options" style="<?php echo $branding_enabled === 'false' ? 'display: none;' : ''; ?>">
                            <label for="branding_type" class="ltv-label">
                                <span class="dashicons dashicons-format-image"></span>
                                <?php _e('Branding Type', 'live-tv-streaming'); ?>
                            </label>
                            <select name="branding_type" id="branding_type" class="ltv-select">
                                <option value="text" <?php selected($branding_type, 'text'); ?>><?php _e('Text Only', 'live-tv-streaming'); ?></option>
                                <option value="logo" <?php selected($branding_type, 'logo'); ?>><?php _e('Logo Image', 'live-tv-streaming'); ?></option>
                                <option value="both" <?php selected($branding_type, 'both'); ?>><?php _e('Logo + Text', 'live-tv-streaming'); ?></option>
                            </select>
                        </div>
                        
                        <div class="ltv-form-group" id="branding-text-group" style="<?php echo ($branding_enabled === 'false' || $branding_type === 'logo') ? 'display: none;' : ''; ?>">
                            <label for="branding_text" class="ltv-label">
                                <span class="dashicons dashicons-text"></span>
                                <?php _e('Branding Text', 'live-tv-streaming'); ?>
                            </label>
                            <input type="text" name="branding_text" id="branding_text" class="ltv-input" value="<?php echo esc_attr($branding_text); ?>" placeholder="<?php esc_attr_e('Enter branding text', 'live-tv-streaming'); ?>">
                        </div>
                        
                        <div class="ltv-form-group" id="branding-logo-group" style="<?php echo ($branding_enabled === 'false' || $branding_type === 'text') ? 'display: none;' : ''; ?>">
                            <label for="branding_logo_url" class="ltv-label">
                                <span class="dashicons dashicons-format-image"></span>
                                <?php _e('Logo URL', 'live-tv-streaming'); ?>
                            </label>
                            <div class="ltv-input-group">
                                <input type="url" name="branding_logo_url" id="branding_logo_url" class="ltv-input" value="<?php echo esc_attr($branding_logo_url); ?>" placeholder="<?php esc_attr_e('https://example.com/logo.png', 'live-tv-streaming'); ?>">
                                <button type="button" id="upload-logo" class="ltv-button ltv-button-secondary">
                                    <span class="dashicons dashicons-upload"></span>
                                    <?php _e('Upload', 'live-tv-streaming'); ?>
                                </button>
                            </div>
                            <p class="ltv-description"><?php _e('Recommended: PNG format, max 120x40px for best results.', 'live-tv-streaming'); ?></p>
                        </div>
                        
                        <div class="ltv-form-group" id="branding-position-group" style="<?php echo $branding_enabled === 'false' ? 'display: none;' : ''; ?>">
                            <label for="branding_position" class="ltv-label">
                                <span class="dashicons dashicons-move"></span>
                                <?php _e('Position', 'live-tv-streaming'); ?>
                            </label>
                            <select name="branding_position" id="branding_position" class="ltv-select">
                                <option value="top-left" <?php selected($branding_position, 'top-left'); ?>><?php _e('Top Left', 'live-tv-streaming'); ?></option>
                                <option value="top-right" <?php selected($branding_position, 'top-right'); ?>><?php _e('Top Right', 'live-tv-streaming'); ?></option>
                                <option value="bottom-left" <?php selected($branding_position, 'bottom-left'); ?>><?php _e('Bottom Left', 'live-tv-streaming'); ?></option>
                                <option value="bottom-right" <?php selected($branding_position, 'bottom-right'); ?>><?php _e('Bottom Right', 'live-tv-streaming'); ?></option>
                            </select>
                        </div>
                        
                        <div class="ltv-form-group" id="branding-opacity-group" style="<?php echo $branding_enabled === 'false' ? 'display: none;' : ''; ?>">
                            <label for="branding_opacity" class="ltv-label">
                                <span class="dashicons dashicons-visibility"></span>
                                <?php _e('Opacity', 'live-tv-streaming'); ?>
                            </label>
                            <div class="opacity-control">
                                <input type="range" name="branding_opacity" id="branding_opacity" class="ltv-range" min="0.1" max="1" step="0.1" value="<?php echo esc_attr($branding_opacity); ?>">
                                <span class="opacity-value"><?php echo esc_html($branding_opacity * 100); ?>%</span>
                            </div>
                        </div>
                        
                </div>
            </div>
            
            <!-- Player Enhancement Settings -->
            <div class="ltv-card">
                <div class="ltv-card-header">
                    <h2><?php _e('Player Enhancements', 'live-tv-streaming'); ?></h2>
                </div>
                <div class="ltv-card-content">
                        
                        <div class="ltv-form-group">
                            <label for="loading_animation" class="ltv-label">
                                <span class="dashicons dashicons-update"></span>
                                <?php _e('Loading Animation', 'live-tv-streaming'); ?>
                            </label>
                            <select name="loading_animation" id="loading_animation" class="ltv-select">
                                <option value="professional" <?php selected($loading_animation, 'professional'); ?>><?php _e('Professional Spinner', 'live-tv-streaming'); ?></option>
                                <option value="minimal" <?php selected($loading_animation, 'minimal'); ?>><?php _e('Minimal', 'live-tv-streaming'); ?></option>
                                <option value="pulsing" <?php selected($loading_animation, 'pulsing'); ?>><?php _e('Pulsing Ring', 'live-tv-streaming'); ?></option>
                                <option value="dots" <?php selected($loading_animation, 'dots'); ?>><?php _e('Animated Dots', 'live-tv-streaming'); ?></option>
                            </select>
                            <p class="ltv-description"><?php _e('Choose the loading animation style that appears during buffering.', 'live-tv-streaming'); ?></p>
                        </div>
                        
                        <div class="ltv-form-group">
                            <label for="control_bar_style" class="ltv-label">
                                <span class="dashicons dashicons-admin-tools"></span>
                                <?php _e('Control Bar Style', 'live-tv-streaming'); ?>
                            </label>
                            <select name="control_bar_style" id="control_bar_style" class="ltv-select">
                                <option value="professional" <?php selected($control_bar_style, 'professional'); ?>><?php _e('Professional', 'live-tv-streaming'); ?></option>
                                <option value="minimal" <?php selected($control_bar_style, 'minimal'); ?>><?php _e('Minimal', 'live-tv-streaming'); ?></option>
                                <option value="gaming" <?php selected($control_bar_style, 'gaming'); ?>><?php _e('Gaming Style', 'live-tv-streaming'); ?></option>
                                <option value="classic" <?php selected($control_bar_style, 'classic'); ?>><?php _e('Classic', 'live-tv-streaming'); ?></option>
                            </select>
                            <p class="ltv-description"><?php _e('Select the visual style for the video player controls.', 'live-tv-streaming'); ?></p>
                        </div>
                        
                        <div class="ltv-form-group">
                            <label for="transition_effects" class="ltv-label">
                                <span class="dashicons dashicons-leftright"></span>
                                <?php _e('Smooth Transitions', 'live-tv-streaming'); ?>
                            </label>
                            <select name="transition_effects" id="transition_effects" class="ltv-select">
                                <option value="true" <?php selected($transition_effects, 'true'); ?>><?php _e('Enabled', 'live-tv-streaming'); ?></option>
                                <option value="false" <?php selected($transition_effects, 'false'); ?>><?php _e('Disabled', 'live-tv-streaming'); ?></option>
                            </select>
                            <p class="ltv-description"><?php _e('Enable smooth transitions for player state changes and interactions.', 'live-tv-streaming'); ?></p>
                        </div>
                        
                        <div class="ltv-form-group">
                            <label for="hover_effects" class="ltv-label">
                                <span class="dashicons dashicons-marker"></span>
                                <?php _e('Hover Effects', 'live-tv-streaming'); ?>
                            </label>
                            <select name="hover_effects" id="hover_effects" class="ltv-select">
                                <option value="true" <?php selected($hover_effects, 'true'); ?>><?php _e('Enabled', 'live-tv-streaming'); ?></option>
                                <option value="false" <?php selected($hover_effects, 'false'); ?>><?php _e('Disabled', 'live-tv-streaming'); ?></option>
                            </select>
                            <p class="ltv-description"><?php _e('Enable interactive hover effects for buttons and controls.', 'live-tv-streaming'); ?></p>
                        </div>
                        
                        <div class="ltv-form-group">
                            <label for="center_play_button" class="ltv-label">
                                <span class="dashicons dashicons-controls-play"></span>
                                <?php _e('Center Play Button', 'live-tv-streaming'); ?>
                            </label>
                            <select name="center_play_button" id="center_play_button" class="ltv-select">
                                <option value="true" <?php selected(get_option('live_tv_center_play_button', 'true'), 'true'); ?>><?php _e('Show with Welcome', 'live-tv-streaming'); ?></option>
                                <option value="minimal" <?php selected(get_option('live_tv_center_play_button', 'true'), 'minimal'); ?>><?php _e('Minimal Button Only', 'live-tv-streaming'); ?></option>
                                <option value="false" <?php selected(get_option('live_tv_center_play_button', 'true'), 'false'); ?>><?php _e('Hidden', 'live-tv-streaming'); ?></option>
                            </select>
                            <p class="ltv-description"><?php _e('Configure the center play button display and welcome message.', 'live-tv-streaming'); ?></p>
                        </div>
                        
                        <div class="ltv-form-group">
                            <label for="welcome_message" class="ltv-label">
                                <span class="dashicons dashicons-format-chat"></span>
                                <?php _e('Welcome Message', 'live-tv-streaming'); ?>
                            </label>
                            <input type="text" name="welcome_message" id="welcome_message" class="ltv-input" value="<?php echo esc_attr(get_option('live_tv_welcome_message', 'Welcome to {channel_name}')); ?>" placeholder="<?php esc_attr_e('Welcome to {channel_name}', 'live-tv-streaming'); ?>">
                            <p class="ltv-description"><?php _e('Customize the welcome message. Use {channel_name} for dynamic channel names.', 'live-tv-streaming'); ?></p>
                        </div>
                        
                </div>
                <div class="ltv-card-footer">
                    <button type="submit" name="submit" class="ltv-button ltv-button-primary">
                        <span class="dashicons dashicons-saved"></span>
                        <?php _e('Save Customizations', 'live-tv-streaming'); ?>
                    </button>
                </div>
                    </form>
            </div>
        </div>
        
        <!-- Advanced Customization -->
        <div class="ltv-grid ltv-grid-1">
            <div class="ltv-card">
                <div class="ltv-card-header">
                    <h2><?php _e('Advanced Customization', 'live-tv-streaming'); ?></h2>
                </div>
                <div class="ltv-card-content">
                    <form method="post" action="" class="ltv-form">
                        <?php wp_nonce_field('player_customization', 'player_customization_nonce'); ?>
                        
                        <div class="ltv-form-group">
                            <label for="custom_css" class="ltv-label">
                                <span class="dashicons dashicons-editor-code"></span>
                                <?php _e('Custom CSS', 'live-tv-streaming'); ?>
                            </label>
                            <textarea name="custom_css" id="custom_css" class="ltv-textarea code-editor" rows="10" placeholder="<?php esc_attr_e('/* Add your custom CSS here */\n.video-js {\n    /* Custom styles */\n}', 'live-tv-streaming'); ?>"><?php echo esc_textarea($custom_css); ?></textarea>
                            <p class="ltv-description"><?php _e('Add custom CSS to further customize the player appearance. Changes apply immediately.', 'live-tv-streaming'); ?></p>
                        </div>
                        
                        
                        <div class="ltv-card-footer">
                            <button type="submit" name="submit" class="ltv-button ltv-button-primary">
                                <span class="dashicons dashicons-saved"></span>
                                <?php _e('Save Advanced Settings', 'live-tv-streaming'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Usage Guide -->
        <div class="ltv-grid ltv-grid-2">
            <div class="ltv-card">
                <div class="ltv-card-header">
                    <h3><?php _e('Customization Guide', 'live-tv-streaming'); ?></h3>
                </div>
                <div class="ltv-card-content">
                    <div class="guide-section">
                        <h4><?php _e('🎨 Branding Best Practices', 'live-tv-streaming'); ?></h4>
                        <ul class="guide-list">
                            <li><?php _e('Use PNG logos with transparent backgrounds', 'live-tv-streaming'); ?></li>
                            <li><?php _e('Keep logo size under 120x40px for optimal display', 'live-tv-streaming'); ?></li>
                            <li><?php _e('Set opacity between 60-80% for subtle branding', 'live-tv-streaming'); ?></li>
                            <li><?php _e('Test on different screen sizes and themes', 'live-tv-streaming'); ?></li>
                        </ul>
                    </div>
                    
                    <div class="guide-section">
                        <h4><?php _e('⚡ Performance Tips', 'live-tv-streaming'); ?></h4>
                        <ul class="guide-list">
                            <li><?php _e('Disable transitions on slower devices', 'live-tv-streaming'); ?></li>
                            <li><?php _e('Use minimal animations for mobile users', 'live-tv-streaming'); ?></li>
                            <li><?php _e('Optimize logo images for web (use WebP if possible)', 'live-tv-streaming'); ?></li>
                            <li><?php _e('Test custom CSS thoroughly before deployment', 'live-tv-streaming'); ?></li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="ltv-card">
                <div class="ltv-card-header">
                    <h3><?php _e('Theme Integration', 'live-tv-streaming'); ?></h3>
                </div>
                <div class="ltv-card-content">
                    <div class="theme-preview-grid">
                        <div class="theme-preview" data-theme="premium-blue">
                            <div class="preview-header">Premium Blue</div>
                            <div class="preview-player"></div>
                            <div class="preview-controls"></div>
                        </div>
                        <div class="theme-preview" data-theme="tech-purple">
                            <div class="preview-header">Tech Purple</div>
                            <div class="preview-player"></div>
                            <div class="preview-controls"></div>
                        </div>
                        <div class="theme-preview" data-theme="premium-gold">
                            <div class="preview-header">Premium Gold</div>
                            <div class="preview-player"></div>
                            <div class="preview-controls"></div>
                        </div>
                        <div class="theme-preview" data-theme="gaming-green">
                            <div class="preview-header">Gaming Green</div>
                            <div class="preview-player"></div>
                            <div class="preview-controls"></div>
                        </div>
                    </div>
                    <p class="ltv-description"><?php _e('Customizations automatically adapt to the selected player theme colors.', 'live-tv-streaming'); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Player Customization JavaScript -->
<script>
jQuery(document).ready(function($) {
    'use strict';
    
    // Toggle branding options visibility
    $('#branding_enabled').on('change', function() {
        var isEnabled = $(this).val() === 'true';
        $('#branding-options, #branding-position-group, #branding-opacity-group').toggle(isEnabled);
        toggleBrandingTypeOptions();
    });
    
    // Toggle branding type options
    $('#branding_type').on('change', toggleBrandingTypeOptions);
    
    function toggleBrandingTypeOptions() {
        var brandingEnabled = $('#branding_enabled').val() === 'true';
        var brandingType = $('#branding_type').val();
        
        if (!brandingEnabled) return;
        
        $('#branding-text-group').toggle(brandingType !== 'logo');
        $('#branding-logo-group').toggle(brandingType !== 'text');
    }
    
    // Opacity slider
    $('#branding_opacity').on('input', function() {
        var value = parseFloat($(this).val()) * 100;
        $('.opacity-value').text(value + '%');
    });
    
    // Logo upload handler
    $('#upload-logo').on('click', function(e) {
        e.preventDefault();
        
        var customUploader = wp.media({
            title: 'Choose Logo',
            library: {
                type: 'image'
            },
            button: {
                text: 'Use this Logo'
            },
            multiple: false
        });
        
        customUploader.on('select', function() {
            var attachment = customUploader.state().get('selection').first().toJSON();
            $('#branding_logo_url').val(attachment.url);
        });
        
        customUploader.open();
    });
    
    
    // Reset to defaults
    $('#reset-customization').on('click', function() {
        if (confirm('<?php _e('Reset all customizations to default values? This cannot be undone.', 'live-tv-streaming'); ?>')) {
            $('#branding_enabled').val('false');
            $('#branding_type').val('text');
            $('#branding_text').val('Live TV Pro');
            $('#branding_logo_url').val('');
            $('#branding_position').val('top-right');
            $('#branding_opacity').val(0.7);
            $('#loading_animation').val('professional');
            $('#control_bar_style').val('professional');
            $('#transition_effects').val('true');
            $('#hover_effects').val('true');
            $('#custom_css').val('');
            
            $('.opacity-value').text('70%');
            $('#branding-options, #branding-position-group, #branding-opacity-group').hide();
            $('#branding-text-group').show();
            $('#branding-logo-group').hide();
            
            showNotification('<?php _e('Settings reset to defaults. Click "Save Customizations" to apply.', 'live-tv-streaming'); ?>', 'info');
        }
    });
    
    
    
    // Show notification helper
    function showNotification(message, type) {
        type = type || 'info';
        var iconClass = type === 'success' ? 'yes' : type === 'error' ? 'no' : 'info';
        var notification = 
            '<div class="ltv-notification ltv-notification-' + type + '" style="margin-bottom: var(--ltv-space-lg);">' +
                '<span class="dashicons dashicons-' + iconClass + '"></span>' +
                message +
            '</div>';
        
        $('.live-tv-admin-header').after(notification);
        
        setTimeout(function() {
            $('.ltv-notification').fadeOut(function() {
                $('.ltv-notification').remove();
            });
        }, 5000);
    }
    
    // Initialize
    toggleBrandingTypeOptions();
});
</script>

<!-- Player Customization Styles -->
<style>
/* Customization specific styles */
.opacity-control {
    display: flex;
    align-items: center;
    gap: var(--ltv-space-md);
}

.ltv-range {
    flex: 1;
    height: 6px;
    background: var(--ltv-gray-200);
    border-radius: 3px;
    outline: none;
    -webkit-appearance: none;
}

.ltv-range::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 20px;
    height: 20px;
    background: var(--ltv-primary);
    border-radius: 50%;
    cursor: pointer;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
}

.ltv-range::-moz-range-thumb {
    width: 20px;
    height: 20px;
    background: var(--ltv-primary);
    border-radius: 50%;
    cursor: pointer;
    border: none;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
}

.opacity-value {
    font-weight: 600;
    color: var(--ltv-primary);
    min-width: 50px;
    text-align: center;
}

.ltv-input-group {
    display: flex;
    gap: var(--ltv-space-sm);
}

.ltv-input-group .ltv-input {
    flex: 1;
}

.code-editor {
    font-family: 'Courier New', monospace;
    font-size: 13px;
    line-height: 1.4;
}


/* Theme Preview Grid */
.theme-preview-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: var(--ltv-space-md);
    margin: var(--ltv-space-md) 0;
}

.theme-preview {
    background: var(--ltv-gray-800);
    border-radius: var(--ltv-radius-md);
    padding: var(--ltv-space-sm);
    text-align: center;
    transition: all 0.3s ease;
    cursor: pointer;
}

.theme-preview:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
}

.theme-preview[data-theme="premium-blue"] {
    background: linear-gradient(135deg, #0f1419, #1a202c);
    color: #00d4ff;
}

.theme-preview[data-theme="tech-purple"] {
    background: linear-gradient(135deg, #0f0f23, #1a1a2e);
    color: #7c3aed;
}

.theme-preview[data-theme="premium-gold"] {
    background: linear-gradient(135deg, #1c1917, #292524);
    color: #fbbf24;
}

.theme-preview[data-theme="gaming-green"] {
    background: linear-gradient(135deg, #0a0e0a, #1a1f1a);
    color: #00ff41;
}

.preview-header {
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 8px;
}

.preview-player {
    width: 100%;
    height: 40px;
    background: rgba(0, 0, 0, 0.5);
    border-radius: 4px;
    margin-bottom: 4px;
}

.preview-controls {
    width: 100%;
    height: 16px;
    background: rgba(0, 0, 0, 0.7);
    border-radius: 0 0 4px 4px;
}

.guide-section {
    margin-bottom: var(--ltv-space-xl);
}

.guide-section:last-child {
    margin-bottom: 0;
}

.guide-section h4 {
    color: var(--ltv-primary);
    margin-bottom: var(--ltv-space-sm);
    font-size: var(--ltv-font-size-md);
}

.guide-list {
    list-style: none;
    padding: 0;
}

.guide-list li {
    padding: var(--ltv-space-xs) 0;
    border-bottom: 1px solid var(--ltv-gray-100);
    position: relative;
    padding-left: var(--ltv-space-lg);
}

.guide-list li:before {
    content: '✓';
    position: absolute;
    left: 0;
    top: var(--ltv-space-xs);
    color: var(--ltv-success);
    font-weight: bold;
}

.guide-list li:last-child {
    border-bottom: none;
}

@keyframes spin {
    to { transform: translate(-50%, -50%) rotate(360deg); }
}
</style>