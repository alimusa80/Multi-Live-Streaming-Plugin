<?php
if (!defined('ABSPATH')) exit('Direct access denied.');
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.', 'live-tv-streaming'));
}
?>

<div class="wrap">
    <div class="live-tv-admin-wrap ltv-admin">
        <div class="live-tv-admin-header">
            <h1>
                <span class="dashicons dashicons-admin-media" style="font-size: 32px; margin-right: 10px;"></span>
                <?php _e('Channel Management', 'live-tv-streaming'); ?>
            </h1>
            <div class="header-actions">
                <button id="bulk-import-btn" class="ltv-button ltv-button-secondary">
                    <span class="dashicons dashicons-upload"></span>
                    <?php _e('Import M3U', 'live-tv-streaming'); ?>
                </button>
                <button id="export-channels-btn" class="ltv-button ltv-button-secondary">
                    <span class="dashicons dashicons-download"></span>
                    <?php _e('Export', 'live-tv-streaming'); ?>
                </button>
            </div>
        </div>
        
        <!-- Notification Area -->
        <div id="notification-area"></div>
        
        <div class="ltv-grid ltv-grid-2">
            <!-- Channel Form -->
            <div class="ltv-card">
                <div class="ltv-card-header">
                    <h2 id="form-title"><?php _e('Add New Channel', 'live-tv-streaming'); ?></h2>
                    <div class="card-actions">
                        <button id="reset-form" class="ltv-button ltv-button-small ltv-button-secondary" style="display: none;">
                            <span class="dashicons dashicons-undo"></span>
                            <?php _e('Reset', 'live-tv-streaming'); ?>
                        </button>
                    </div>
                </div>
                <div class="ltv-card-content">
                    <form id="channel-form" class="ltv-form">
                        <input type="hidden" id="channel-id" name="channel_id" value="">
                        <input type="hidden" id="edit-mode" name="edit_mode" value="0">
                        
                        <div class="ltv-form-group">
                            <label for="channel-name" class="ltv-label">
                                <?php _e('Channel Name', 'live-tv-streaming'); ?> <span style="color: var(--ltv-error);">*</span>
                            </label>
                            <input type="text" id="channel-name" name="channel_name" class="ltv-input" required 
                                   placeholder="<?php esc_attr_e('Enter channel name...', 'live-tv-streaming'); ?>">
                        </div>
                        
                        <div class="ltv-form-group">
                            <label for="channel-description" class="ltv-label">
                                <?php _e('Description', 'live-tv-streaming'); ?>
                            </label>
                            <textarea id="channel-description" name="channel_description" class="ltv-textarea" rows="3"
                                      placeholder="<?php esc_attr_e('Channel description (optional)...', 'live-tv-streaming'); ?>"></textarea>
                            <p class="ltv-description"><?php _e('Brief description of the channel content.', 'live-tv-streaming'); ?></p>
                        </div>
                        
                        <div class="ltv-form-group">
                            <label for="stream-url" class="ltv-label">
                                <?php _e('Stream URL', 'live-tv-streaming'); ?> <span style="color: var(--ltv-error);">*</span>
                            </label>
                            <input type="url" id="stream-url" name="stream_url" class="ltv-input" required
                                   placeholder="https://example.com/stream.m3u8">
                            <p class="ltv-description"><?php _e('Direct URL to the video stream (HLS, MP4, etc.).', 'live-tv-streaming'); ?></p>
                        </div>
                        
                        <div class="ltv-form-group">
                            <label for="logo-url" class="ltv-label">
                                <?php _e('Logo URL', 'live-tv-streaming'); ?>
                            </label>
                            <input type="url" id="logo-url" name="logo_url" class="ltv-input"
                                   placeholder="https://example.com/logo.png">
                            <p class="ltv-description"><?php _e('URL to channel logo image (recommended: 100x100px).', 'live-tv-streaming'); ?></p>
                        </div>
                        
                        <div class="ltv-grid ltv-grid-2">
                            <div class="ltv-form-group">
                                <label for="category" class="ltv-label">
                                    <?php _e('Category', 'live-tv-streaming'); ?>
                                </label>
                                <select id="category" name="category" class="ltv-select">
                                    <option value="Other"><?php _e('Other', 'live-tv-streaming'); ?></option>
                                    <option value="News"><?php _e('News', 'live-tv-streaming'); ?></option>
                                    <option value="Sports"><?php _e('Sports', 'live-tv-streaming'); ?></option>
                                    <option value="Entertainment"><?php _e('Entertainment', 'live-tv-streaming'); ?></option>
                                    <option value="Movies"><?php _e('Movies', 'live-tv-streaming'); ?></option>
                                    <option value="Music"><?php _e('Music', 'live-tv-streaming'); ?></option>
                                    <option value="Documentary"><?php _e('Documentary', 'live-tv-streaming'); ?></option>
                                    <option value="Kids"><?php _e('Kids', 'live-tv-streaming'); ?></option>
                                </select>
                            </div>
                            
                            <div class="ltv-form-group">
                                <label for="sort-order" class="ltv-label">
                                    <?php _e('Sort Order', 'live-tv-streaming'); ?>
                                </label>
                                <input type="number" id="sort-order" name="sort_order" class="ltv-input" value="0" min="0" max="9999">
                                <p class="ltv-description"><?php _e('Lower numbers appear first.', 'live-tv-streaming'); ?></p>
                            </div>
                        </div>
                        
                        <div class="ltv-form-group">
                            <label class="ltv-label">
                                <input type="checkbox" id="is-active" name="is_active" value="1" checked style="margin-right: 8px;">
                                <?php _e('Channel is Active', 'live-tv-streaming'); ?>
                            </label>
                            <p class="ltv-description"><?php _e('Only active channels will be displayed to users.', 'live-tv-streaming'); ?></p>
                        </div>
                    </form>
                </div>
                <div class="ltv-card-footer">
                    <button type="button" id="cancel-edit" class="ltv-button ltv-button-secondary" style="display: none;">
                        <?php _e('Cancel', 'live-tv-streaming'); ?>
                    </button>
                    <button type="submit" form="channel-form" class="ltv-button ltv-button-primary">
                        <span class="dashicons dashicons-saved"></span>
                        <span id="save-text"><?php _e('Save Channel', 'live-tv-streaming'); ?></span>
                    </button>
                </div>
            </div>
            
            <!-- Channels List -->
            <div class="ltv-card">
                <div class="ltv-card-header">
                    <h2><?php _e('Existing Channels', 'live-tv-streaming'); ?></h2>
                    <div class="card-actions">
                        <div class="search-box" style="display: flex; gap: 10px; align-items: center;">
                            <input type="text" id="channel-search" placeholder="<?php esc_attr_e('Search channels...', 'live-tv-streaming'); ?>" 
                                   class="ltv-input" style="width: 200px;">
                            <select id="category-filter" class="ltv-select" style="width: 120px;">
                                <option value=""><?php _e('All Categories', 'live-tv-streaming'); ?></option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="ltv-card-content" style="padding: 0;">
                    <div id="channels-list" style="min-height: 400px;">
                        <div class="ltv-flex-center" style="height: 400px;">
                            <div class="loading-spinner">
                                <div class="spin" style="width: 32px; height: 32px; border: 3px solid var(--ltv-gray-300); border-top: 3px solid var(--ltv-primary); border-radius: 50%;"></div>
                                <p style="margin-top: 15px; color: var(--ltv-gray-500);"><?php _e('Loading channels...', 'live-tv-streaming'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Channel Statistics -->
        <div class="ltv-card ltv-mt-lg">
            <div class="ltv-card-header">
                <h3><?php _e('Channel Statistics', 'live-tv-streaming'); ?></h3>
                <div class="card-actions">
                    <button id="refresh-stats" class="ltv-button ltv-button-small ltv-button-secondary">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Refresh', 'live-tv-streaming'); ?>
                    </button>
                </div>
            </div>
            <div class="ltv-card-content">
                <div class="ltv-stats-grid">
                    <div class="ltv-stat-card">
                        <div class="ltv-stat-value" id="total-channels">-</div>
                        <div class="ltv-stat-label"><?php _e('Total Channels', 'live-tv-streaming'); ?></div>
                    </div>
                    <div class="ltv-stat-card">
                        <div class="ltv-stat-value" id="active-channels">-</div>
                        <div class="ltv-stat-label"><?php _e('Active Channels', 'live-tv-streaming'); ?></div>
                    </div>
                    <div class="ltv-stat-card">
                        <div class="ltv-stat-value" id="categories-count">-</div>
                        <div class="ltv-stat-label"><?php _e('Categories', 'live-tv-streaming'); ?></div>
                    </div>
                    <div class="ltv-stat-card">
                        <div class="ltv-stat-value" id="recent-additions">-</div>
                        <div class="ltv-stat-label"><?php _e('Added This Week', 'live-tv-streaming'); ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="ltv-card ltv-mt-lg">
            <div class="ltv-card-header">
                <h3><?php _e('Quick Actions', 'live-tv-streaming'); ?></h3>
            </div>
            <div class="ltv-card-content">
                <div class="ltv-flex" style="gap: 15px; flex-wrap: wrap;">
                    <button class="ltv-button ltv-button-success" onclick="activateAllChannels()">
                        <span class="dashicons dashicons-yes"></span>
                        <?php _e('Activate All', 'live-tv-streaming'); ?>
                    </button>
                    <button class="ltv-button ltv-button-warning" onclick="deactivateAllChannels()">
                        <span class="dashicons dashicons-no"></span>
                        <?php _e('Deactivate All', 'live-tv-streaming'); ?>
                    </button>
                    <button class="ltv-button ltv-button-secondary" onclick="resetSortOrder()">
                        <span class="dashicons dashicons-sort"></span>
                        <?php _e('Reset Sort Order', 'live-tv-streaming'); ?>
                    </button>
                    <button class="ltv-button ltv-button-error" onclick="deleteInactiveChannels()" style="margin-left: auto;">
                        <span class="dashicons dashicons-trash"></span>
                        <?php _e('Delete Inactive', 'live-tv-streaming'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- M3U Import Modal -->
<div id="m3u-import-modal" class="ltv-modal" style="display: none;">
    <div class="ltv-modal-overlay" onclick="closeM3UModal()"></div>
    <div class="ltv-modal-content">
        <div class="ltv-modal-header">
            <h2><?php _e('Import M3U Playlist', 'live-tv-streaming'); ?></h2>
            <button class="ltv-modal-close" onclick="closeM3UModal()">×</button>
        </div>
        <div class="ltv-modal-body">
            <!-- Step 1: Source Selection -->
            <div id="import-step-1" class="import-step">
                <h3><?php _e('Step 1: Select M3U Source', 'live-tv-streaming'); ?></h3>
                <div class="source-selection">
                    <label class="source-option">
                        <input type="radio" name="import_source" value="file" checked>
                        <span><?php _e('Upload File', 'live-tv-streaming'); ?></span>
                    </label>
                    <label class="source-option">
                        <input type="radio" name="import_source" value="url">
                        <span><?php _e('From URL', 'live-tv-streaming'); ?></span>
                    </label>
                </div>
                
                <div id="file-upload-section" class="input-section">
                    <label class="ltv-label"><?php _e('Select M3U File', 'live-tv-streaming'); ?></label>
                    <input type="file" id="m3u-file" accept=".m3u,.m3u8,.txt" class="ltv-input">
                </div>
                
                <div id="url-input-section" class="input-section" style="display: none;">
                    <label class="ltv-label"><?php _e('M3U URL', 'live-tv-streaming'); ?></label>
                    <input type="url" id="m3u-url" class="ltv-input" placeholder="https://example.com/playlist.m3u8">
                </div>
                
                <div class="step-actions">
                    <button id="parse-m3u-btn" class="ltv-button ltv-button-primary">
                        <?php _e('Parse & Preview', 'live-tv-streaming'); ?>
                    </button>
                </div>
            </div>
            
            <!-- Step 2: Preview and Category Mapping -->
            <div id="import-step-2" class="import-step" style="display: none;">
                <h3><?php _e('Step 2: Review Channels & Map Categories', 'live-tv-streaming'); ?></h3>
                
                <div class="import-summary">
                    <p><strong><?php _e('Found:', 'live-tv-streaming'); ?></strong> <span id="channels-found">0</span> <?php _e('channels', 'live-tv-streaming'); ?></p>
                    <p><strong><?php _e('Categories found:', 'live-tv-streaming'); ?></strong> <span id="categories-found">0</span></p>
                </div>
                
                <div class="category-mapping">
                    <h4><?php _e('Category Mapping', 'live-tv-streaming'); ?></h4>
                    <p class="ltv-description"><?php _e('Map M3U categories to your channel categories. Unknown categories will use the default below.', 'live-tv-streaming'); ?></p>
                    
                    <div class="default-category">
                        <label class="ltv-label"><?php _e('Default Category for Unknown', 'live-tv-streaming'); ?></label>
                        <select id="default-category" class="ltv-select">
                            <option value="Other"><?php _e('Other', 'live-tv-streaming'); ?></option>
                            <option value="News"><?php _e('News', 'live-tv-streaming'); ?></option>
                            <option value="Sports"><?php _e('Sports', 'live-tv-streaming'); ?></option>
                            <option value="Entertainment"><?php _e('Entertainment', 'live-tv-streaming'); ?></option>
                            <option value="Movies"><?php _e('Movies', 'live-tv-streaming'); ?></option>
                            <option value="Music"><?php _e('Music', 'live-tv-streaming'); ?></option>
                            <option value="Documentary"><?php _e('Documentary', 'live-tv-streaming'); ?></option>
                            <option value="Kids"><?php _e('Kids', 'live-tv-streaming'); ?></option>
                        </select>
                    </div>
                    
                    <div id="category-mappings"></div>
                </div>
                
                <div class="channel-preview">
                    <h4><?php _e('Channel Preview (First 10)', 'live-tv-streaming'); ?></h4>
                    <div id="channels-preview" class="channels-preview-list"></div>
                </div>
                
                <div class="step-actions">
                    <button id="back-to-step1" class="ltv-button ltv-button-secondary">
                        <?php _e('Back', 'live-tv-streaming'); ?>
                    </button>
                    <button id="start-import-btn" class="ltv-button ltv-button-success">
                        <?php _e('Start Import', 'live-tv-streaming'); ?>
                    </button>
                </div>
            </div>
            
            <!-- Step 3: Import Progress -->
            <div id="import-step-3" class="import-step" style="display: none;">
                <h3><?php _e('Step 3: Importing Channels...', 'live-tv-streaming'); ?></h3>
                
                <div class="import-progress">
                    <div class="progress-bar">
                        <div id="import-progress-fill" class="progress-fill"></div>
                    </div>
                    <p id="import-status"><?php _e('Starting import...', 'live-tv-streaming'); ?></p>
                </div>
                
                <div id="import-results" style="display: none;">
                    <div class="import-summary-final">
                        <p><strong><?php _e('Import Complete!', 'live-tv-streaming'); ?></strong></p>
                        <p><?php _e('Imported:', 'live-tv-streaming'); ?> <span id="imported-count">0</span> <?php _e('channels', 'live-tv-streaming'); ?></p>
                        <p><?php _e('Skipped (duplicates):', 'live-tv-streaming'); ?> <span id="skipped-count">0</span></p>
                        <p><?php _e('Categories created:', 'live-tv-streaming'); ?> <span id="categories-created">0</span></p>
                        <div id="import-errors" style="display: none;">
                            <p><strong><?php _e('Errors:', 'live-tv-streaming'); ?></strong></p>
                            <ul id="error-list"></ul>
                        </div>
                    </div>
                </div>
                
                <div class="step-actions">
                    <button id="close-import-modal" class="ltv-button ltv-button-primary" style="display: none;">
                        <?php _e('Close', 'live-tv-streaming'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for Channel Management -->
<script>
jQuery(document).ready(function($) {
    'use strict';
    
    // Debug: Check if liveTVAdmin object exists
    console.log('LiveTV Admin Debug:', {
        liveTVAdmin: typeof liveTVAdmin !== 'undefined' ? liveTVAdmin : 'undefined',
        ajaxurl: typeof ajaxurl !== 'undefined' ? ajaxurl : 'undefined',
        jQuery: typeof $ !== 'undefined' ? 'loaded' : 'undefined'
    });
    
    // Ensure we have the required variables
    if (typeof liveTVAdmin === 'undefined') {
        console.error('liveTVAdmin object not found! Admin functionality may not work.');
        showNotification('Admin scripts not properly loaded. Please refresh the page.', 'error');
        return;
    }
    
    if (!liveTVAdmin.nonce) {
        console.error('Admin nonce not found! AJAX requests will fail.');
        showNotification('Security token missing. Please refresh the page.', 'error');
        return;
    }
    
    let isEditMode = false;
    let channels = [];
    let filteredChannels = [];
    
    // Prevent admin.js from interfering
    if (typeof initializeAdmin === 'function') {
        // Disable the old admin.js initialization
        window.initializeAdmin = function() { return false; };
    }
    
    // Initialize
    loadChannels();
    loadChannelStats();
    setupEventListeners();
    
    function setupEventListeners() {
        // Form submission
        $('#channel-form').on('submit', handleFormSubmit);
        
        // Cancel edit
        $('#cancel-edit').on('click', resetForm);
        $('#reset-form').on('click', resetForm);
        
        // Search and filter
        $('#channel-search').on('input', debounce(filterChannels, 300));
        $('#category-filter').on('change', filterChannels);
        
        // Refresh buttons
        $('#refresh-stats').on('click', loadChannelStats);
    }
    
    function loadChannels() {
        showNotification('<?php _e('Loading channels...', 'live-tv-streaming'); ?>', 'info');
        
        $.ajax({
            url: liveTVAdmin.ajax_url || ajaxurl,
            type: 'POST',
            data: {
                action: 'get_channels',
                nonce: liveTVAdmin.nonce
            },
            success: function(response) {
                console.log('Get channels response:', response);
                
                if (response && response.success) {
                    channels = response.data || [];
                    filteredChannels = [...channels];
                    displayChannels();
                    populateCategoryFilter();
                    hideNotification();
                    
                    console.log('Loaded', channels.length, 'channels successfully');
                } else {
                    const errorMessage = response?.data?.message || response?.data || '<?php _e('Failed to load channels.', 'live-tv-streaming'); ?>';
                    console.error('Channel loading failed:', errorMessage);
                    showNotification(errorMessage, 'error');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error Details:', {
                    xhr: xhr,
                    status: status,
                    error: error,
                    responseText: xhr.responseText
                });
                
                let errorMessage = '<?php _e('Network error occurred.', 'live-tv-streaming'); ?>';
                
                if (xhr.responseText) {
                    try {
                        const errorResponse = JSON.parse(xhr.responseText);
                        if (errorResponse.data && errorResponse.data.message) {
                            errorMessage = errorResponse.data.message;
                        }
                    } catch (e) {
                        // Use default error message
                    }
                }
                
                showNotification(errorMessage, 'error');
            }
        });
    }
    
    function loadChannelStats() {
        $('#total-channels, #active-channels, #categories-count, #recent-additions').text('-');
        
        // Calculate stats from loaded channels
        if (channels.length > 0) {
            const totalChannels = channels.length;
            const activeChannels = channels.filter(ch => parseInt(ch.is_active) === 1).length;
            const categories = [...new Set(channels.map(ch => ch.category).filter(Boolean))];
            const categoriesCount = categories.length;
            
            // Calculate recent additions (last 7 days)
            const weekAgo = new Date();
            weekAgo.setDate(weekAgo.getDate() - 7);
            const recentAdditions = channels.filter(ch => {
                return new Date(ch.created_at) > weekAgo;
            }).length;
            
            $('#total-channels').text(totalChannels);
            $('#active-channels').text(activeChannels);
            $('#categories-count').text(categoriesCount);
            $('#recent-additions').text(recentAdditions);
        }
    }
    
    function populateCategoryFilter() {
        const categories = [...new Set(channels.map(ch => ch.category).filter(Boolean))];
        const $filter = $('#category-filter');
        
        $filter.find('option:not(:first)').remove();
        
        categories.sort().forEach(category => {
            $filter.append(`<option value="${escapeHtml(category)}">${escapeHtml(category)}</option>`);
        });
    }
    
    function displayChannels() {
        console.log('Displaying channels:', filteredChannels.length, 'filtered,', channels.length, 'total');
        
        const $list = $('#channels-list');
        
        if (!Array.isArray(filteredChannels) || filteredChannels.length === 0) {
            const emptyMessage = !Array.isArray(channels) || channels.length === 0 
                ? '<?php _e('No channels found. Add your first channel!', 'live-tv-streaming'); ?>'
                : '<?php _e('No channels match your search.', 'live-tv-streaming'); ?>';
                
            $list.html(`
                <div class="ltv-flex-center" style="height: 200px; flex-direction: column;">
                    <div style="font-size: 48px; margin-bottom: 15px;">📺</div>
                    <p style="color: var(--ltv-gray-500); font-size: 16px;">${emptyMessage}</p>
                    ${!Array.isArray(channels) ? '<p style="color: var(--ltv-error); font-size: 14px;">Error: Invalid channel data received</p>' : ''}
                </div>
            `);
            return;
        }
        
        let html = '<div style="padding: 0;">';
        
        filteredChannels.forEach((channel, index) => {
            const isActive = parseInt(channel.is_active) === 1;
            const statusClass = isActive ? 'success' : 'warning';
            const statusText = isActive ? '<?php _e('Active', 'live-tv-streaming'); ?>' : '<?php _e('Inactive', 'live-tv-streaming'); ?>';
            
            html += `
                <div class="channel-row" style="display: flex; align-items: center; padding: 15px 20px; border-bottom: 1px solid var(--line); transition: background 0.2s ease;">
                    <div class="channel-logo" style="width: 50px; height: 50px; margin-right: 15px; border-radius: 8px; overflow: hidden; background: var(--bg-3); display: flex; align-items: center; justify-content: center;">
                        ${channel.logo_url ? 
                            `<img src="${escapeHtml(channel.logo_url)}" alt="${escapeHtml(channel.name)}" style="width: 100%; height: 100%; object-fit: cover;">` :
                            '<span class="dashicons dashicons-video-alt2" style="color: var(--muted);"></span>'
                        }
                    </div>
                    <div class="channel-info" style="flex: 1; min-width: 0;">
                        <div class="channel-name" style="font-weight: 600; font-size: 14px; color: var(--text); margin-bottom: 2px;">${escapeHtml(channel.name)}</div>
                        <div class="channel-details" style="font-size: 12px; color: var(--muted);">
                            ${channel.category ? `<span class="category-tag" style="background: var(--accent-500); color: #041018; padding: 2px 6px; border-radius: 10px; margin-right: 8px; font-weight: 600;">${escapeHtml(channel.category)}</span>` : ''}
                            <span>Sort: ${channel.sort_order || 0}</span>
                        </div>
                    </div>
                    <div class="channel-status" style="margin-right: 15px;">
                        <span class="status-badge status-${statusClass}" style="padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: 500; text-transform: uppercase; background: var(--${statusClass}); color: white;">${statusText}</span>
                    </div>
                    <div class="channel-actions" style="display: flex; gap: 5px;">
                        <button class="ltv-button ltv-button-small ltv-button-secondary" onclick="editChannel(${channel.id})" title="<?php esc_attr_e('Edit Channel', 'live-tv-streaming'); ?>">
                            <span class="dashicons dashicons-edit"></span>
                        </button>
                        <button class="ltv-button ltv-button-small ltv-button-${isActive ? 'warning' : 'success'}" onclick="toggleChannelStatus(${channel.id}, ${isActive ? 0 : 1})" title="${isActive ? '<?php esc_attr_e('Deactivate', 'live-tv-streaming'); ?>' : '<?php esc_attr_e('Activate', 'live-tv-streaming'); ?>'}">
                            <span class="dashicons dashicons-${isActive ? 'hidden' : 'visibility'}"></span>
                        </button>
                        <button class="ltv-button ltv-button-small ltv-button-error" onclick="deleteChannel(${channel.id})" title="<?php esc_attr_e('Delete Channel', 'live-tv-streaming'); ?>">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        $list.html(html);
        
        // Add hover effects
        $('.channel-row').hover(
            function() { $(this).css('background', 'var(--bg-3)'); },
            function() { $(this).css('background', 'transparent'); }
        );
    }
    
    function filterChannels() {
        const search = $('#channel-search').val().toLowerCase();
        const category = $('#category-filter').val();
        
        filteredChannels = channels.filter(channel => {
            const matchesSearch = !search || 
                channel.name.toLowerCase().includes(search) ||
                (channel.description && channel.description.toLowerCase().includes(search));
            
            const matchesCategory = !category || channel.category === category;
            
            return matchesSearch && matchesCategory;
        });
        
        displayChannels();
    }
    
    function handleFormSubmit(e) {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const data = {
            action: 'save_channel',
            nonce: liveTVAdmin.nonce
        };
        
        for (let [key, value] of formData.entries()) {
            data[key] = value;
        }
        
        // Clear any previous validation errors
        $('#channel-form .form-invalid').removeClass('form-invalid');
        $('#channel-form .validation-error').remove();
        
        // Validate form data
        let validationErrors = [];
        
        if (!data.channel_name || data.channel_name.trim() === '') {
            validationErrors.push('Channel name is required');
            $('#channel-name').addClass('form-invalid');
        } else if (data.channel_name.trim().length < 2) {
            validationErrors.push('Channel name must be at least 2 characters');
            $('#channel-name').addClass('form-invalid');
        } else if (data.channel_name.trim().length > 100) {
            validationErrors.push('Channel name is too long (max 100 characters)');
            $('#channel-name').addClass('form-invalid');
        }
        
        if (!data.stream_url || data.stream_url.trim() === '') {
            validationErrors.push('Stream URL is required');
            $('#stream-url').addClass('form-invalid');
        } else {
            // Basic URL validation
            try {
                const url = new URL(data.stream_url.trim());
                if (!['http:', 'https:'].includes(url.protocol)) {
                    validationErrors.push('Stream URL must use HTTP or HTTPS protocol');
                    $('#stream-url').addClass('form-invalid');
                }
            } catch (e) {
                validationErrors.push('Please enter a valid stream URL');
                $('#stream-url').addClass('form-invalid');
            }
        }
        
        if (data.logo_url && data.logo_url.trim() !== '') {
            try {
                const logoUrl = new URL(data.logo_url.trim());
                if (!['http:', 'https:'].includes(logoUrl.protocol)) {
                    validationErrors.push('Logo URL must use HTTP or HTTPS protocol');
                    $('#logo-url').addClass('form-invalid');
                }
            } catch (e) {
                validationErrors.push('Please enter a valid logo URL or leave empty');
                $('#logo-url').addClass('form-invalid');
            }
        }
        
        if (validationErrors.length > 0) {
            showNotification('Validation errors: ' + validationErrors.join(', '), 'error');
            return;
        }
        
        // Ensure channel_id and edit_mode are properly set
        const channelId = $('#channel-id').val();
        const editModeField = $('#edit-mode').val();
        
        console.log('Form submission debug:', {
            channelId: channelId,
            editModeField: editModeField,
            isEditMode: isEditMode,
            channelIdParsed: parseInt(channelId || 0),
            formData: data,
            validationPassed: true
        });
        
        if (channelId && channelId.trim() !== '' && channelId !== '0' && parseInt(channelId) > 0) {
            data.channel_id = parseInt(channelId);
            data.edit_mode = 1; // Force edit mode if we have a valid channel ID
            console.log('Setting edit mode: channel_id =', data.channel_id);
        } else {
            data.channel_id = 0; // Explicitly set to 0 for new channels
            data.edit_mode = 0; // New channel
            console.log('Setting create mode: new channel');
        }
        
        // Final validation
        if (isEditMode && (!data.channel_id || data.channel_id <= 0)) {
            console.error('Edit mode validation failed!', {
                isEditMode: isEditMode,
                channelId: data.channel_id
            });
            showNotification('<?php _e('Invalid channel ID for editing. Please refresh and try again.', 'live-tv-streaming'); ?>', 'error');
            return;
        }
        
        // Set checkbox value properly
        data.is_active = $('#is-active').is(':checked') ? 1 : 0;
        
        // Debug logging for troubleshooting
        console.log('Form submission data:', {
            channel_id: data.channel_id,
            channel_name: data.channel_name,
            edit_mode: data.edit_mode,
            is_edit_mode: isEditMode
        });
        
        const $saveBtn = $('button[type="submit"]');
        const originalText = $('#save-text').text();
        
        $saveBtn.prop('disabled', true);
        $('#save-text').text('<?php _e('Saving...', 'live-tv-streaming'); ?>');
        
        $.ajax({
            url: liveTVAdmin.ajax_url || ajaxurl,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    showNotification(
                        isEditMode ? '<?php _e('Channel updated successfully!', 'live-tv-streaming'); ?>' : '<?php _e('Channel added successfully!', 'live-tv-streaming'); ?>',
                        'success'
                    );
                    resetForm();
                    loadChannels();
                    loadChannelStats();
                } else {
                    showNotification(response.data || '<?php _e('Failed to save channel.', 'live-tv-streaming'); ?>', 'error');
                }
            },
            error: function() {
                showNotification('<?php _e('Network error occurred.', 'live-tv-streaming'); ?>', 'error');
            },
            complete: function() {
                $saveBtn.prop('disabled', false);
                $('#save-text').text(originalText);
            }
        });
    }
    
    function resetForm() {
        console.log('Resetting form - current state:', {
            isEditMode: isEditMode,
            channelId: $('#channel-id').val(),
            editMode: $('#edit-mode').val()
        });
        
        $('#channel-form')[0].reset();
        $('#channel-id').val(''); // Clear channel ID
        $('#edit-mode').val('0'); // Reset edit mode
        $('#form-title').text('<?php _e('Add New Channel', 'live-tv-streaming'); ?>');
        $('#save-text').text('<?php _e('Save Channel', 'live-tv-streaming'); ?>');
        $('#cancel-edit, #reset-form').hide();
        
        // Remove visual indicator
        $('.ltv-card').removeClass('ltv-border-glow');
        
        // Reset form state
        isEditMode = false;
        
        // Clear any validation errors
        $('.ltv-input, .ltv-select, .ltv-textarea').removeClass('error');
        
        console.log('Form reset complete - new state:', {
            isEditMode: isEditMode,
            channelId: $('#channel-id').val(),
            editMode: $('#edit-mode').val()
        });
    }
    
    // Global functions for onclick handlers
    window.editChannel = function(id) {
        const channel = channels.find(ch => ch.id == id);
        if (!channel) {
            console.error('Channel not found:', id);
            showNotification('<?php _e('Channel not found.', 'live-tv-streaming'); ?>', 'error');
            return;
        }
        
        console.log('Editing channel:', channel);
        
        // Set form values
        $('#channel-id').val(channel.id);
        $('#channel-name').val(channel.name);
        $('#channel-description').val(channel.description || '');
        $('#stream-url').val(channel.stream_url);
        $('#logo-url').val(channel.logo_url || '');
        $('#category').val(channel.category || 'Other');
        $('#sort-order').val(channel.sort_order || 0);
        $('#is-active').prop('checked', parseInt(channel.is_active) === 1);
        
        // Update UI for edit mode
        $('#form-title').text('<?php _e('Edit Channel', 'live-tv-streaming'); ?>');
        $('#save-text').text('<?php _e('Update Channel', 'live-tv-streaming'); ?>');
        $('#cancel-edit, #reset-form').show();
        $('#edit-mode').val('1'); // Set hidden field
        
        // Add visual indicator for edit mode
        $('.ltv-card').first().addClass('ltv-border-glow');
        
        isEditMode = true;
        
        console.log('Edit mode activated for channel:', {
            id: channel.id,
            name: channel.name,
            isEditMode: isEditMode,
            hiddenFieldValue: $('#edit-mode').val()
        });
        
        // Scroll to form
        $('html, body').animate({
            scrollTop: $('#channel-form').offset().top - 100
        }, 500);
        
        // Show success notification
        showNotification('<?php _e('Channel loaded for editing.', 'live-tv-streaming'); ?>', 'info');
    };
    
    window.toggleChannelStatus = function(id, newStatus) {
        const channel = channels.find(ch => ch.id == id);
        if (!channel) return;
        
        $.ajax({
            url: liveTVAdmin.ajax_url || ajaxurl,
            type: 'POST',
            data: {
                action: 'save_channel',
                nonce: liveTVAdmin.nonce,
                channel_id: parseInt(id),
                edit_mode: 1, // Always 1 for status updates
                channel_name: channel.name,
                channel_description: channel.description || '',
                stream_url: channel.stream_url,
                logo_url: channel.logo_url || '',
                category: channel.category || 'Other',
                sort_order: channel.sort_order || 0,
                is_active: parseInt(newStatus)
            },
            success: function(response) {
                if (response.success) {
                    loadChannels();
                    loadChannelStats();
                    showNotification('<?php _e('Channel status updated.', 'live-tv-streaming'); ?>', 'success');
                } else {
                    showNotification(response.data || '<?php _e('Failed to update status.', 'live-tv-streaming'); ?>', 'error');
                }
            }
        });
    };
    
    window.deleteChannel = function(id) {
        const channel = channels.find(ch => ch.id == id);
        if (!channel) return;
        
        if (!confirm(`<?php _e('Are you sure you want to delete', 'live-tv-streaming'); ?> "${channel.name}"? <?php _e('This action cannot be undone.', 'live-tv-streaming'); ?>`)) {
            return;
        }
        
        $.ajax({
            url: liveTVAdmin.ajax_url || ajaxurl,
            type: 'POST',
            data: {
                action: 'delete_channel',
                nonce: liveTVAdmin.nonce,
                channel_id: id
            },
            success: function(response) {
                if (response.success) {
                    showNotification('<?php _e('Channel deleted successfully.', 'live-tv-streaming'); ?>', 'success');
                    loadChannels();
                    loadChannelStats();
                    
                    // Reset form if editing this channel
                    if (parseInt($('#channel-id').val()) === parseInt(id)) {
                        resetForm();
                    }
                } else {
                    showNotification(response.data || '<?php _e('Failed to delete channel.', 'live-tv-streaming'); ?>', 'error');
                }
            }
        });
    };
    
    // Quick Actions
    window.activateAllChannels = function() {
        if (!confirm('<?php _e('Activate all channels?', 'live-tv-streaming'); ?>')) return;
        bulkUpdateStatus(1);
    };
    
    window.deactivateAllChannels = function() {
        if (!confirm('<?php _e('Deactivate all channels?', 'live-tv-streaming'); ?>')) return;
        bulkUpdateStatus(0);
    };
    
    window.deleteInactiveChannels = function() {
        if (!confirm('<?php _e('Delete all inactive channels? This cannot be undone.', 'live-tv-streaming'); ?>')) return;
        
        const inactiveIds = channels.filter(ch => parseInt(ch.is_active) === 0).map(ch => ch.id);
        if (inactiveIds.length === 0) {
            showNotification('<?php _e('No inactive channels to delete.', 'live-tv-streaming'); ?>', 'info');
            return;
        }
        
        // Delete each inactive channel
        let deleted = 0;
        inactiveIds.forEach(id => {
            $.ajax({
                url: liveTVAdmin.ajax_url || ajaxurl,
                type: 'POST',
                data: {
                    action: 'delete_channel',
                    nonce: liveTVAdmin.nonce,
                    channel_id: id
                },
                success: function(response) {
                    deleted++;
                    if (deleted === inactiveIds.length) {
                        showNotification(`<?php _e('Deleted', 'live-tv-streaming'); ?> ${deleted} <?php _e('inactive channels.', 'live-tv-streaming'); ?>`, 'success');
                        loadChannels();
                        loadChannelStats();
                    }
                }
            });
        });
    };
    
    function bulkUpdateStatus(status) {
        let updated = 0;
        const total = channels.length;
        
        channels.forEach(channel => {
            $.ajax({
                url: liveTVAdmin.ajax_url || ajaxurl,
                type: 'POST',
                data: {
                    action: 'save_channel',
                    nonce: liveTVAdmin.nonce,
                    channel_id: channel.id,
                    edit_mode: 1, // Always 1 for bulk updates
                    channel_name: channel.name,
                    channel_description: channel.description || '',
                    stream_url: channel.stream_url,
                    logo_url: channel.logo_url || '',
                    category: channel.category || 'Other',
                    sort_order: channel.sort_order || 0,
                    is_active: status
                },
                success: function() {
                    updated++;
                    if (updated === total) {
                        loadChannels();
                        loadChannelStats();
                        showNotification(`<?php _e('Updated', 'live-tv-streaming'); ?> ${total} <?php _e('channels.', 'live-tv-streaming'); ?>`, 'success');
                    }
                }
            });
        });
    }
    
    // Utility functions
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = function() {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    function showNotification(message, type = 'info') {
        const notification = `
            <div class="ltv-notification ltv-notification-${type}">
                <span class="dashicons dashicons-${type === 'success' ? 'yes' : type === 'error' ? 'no' : 'info'}"></span>
                ${message}
            </div>
        `;
        
        $('#notification-area').html(notification);
        
        setTimeout(() => {
            $('.ltv-notification').fadeOut(() => {
                $('#notification-area').empty();
            });
        }, 5000);
    }
    
    function hideNotification() {
        $('#notification-area').empty();
    }
    
    // M3U Import Functionality
    let parsedM3UData = null;
    
    // Event listeners for M3U import
    $('#bulk-import-btn').on('click', function() {
        openM3UModal();
    });
    
    $('input[name="import_source"]').on('change', function() {
        if ($(this).val() === 'file') {
            $('#file-upload-section').show();
            $('#url-input-section').hide();
        } else {
            $('#file-upload-section').hide();
            $('#url-input-section').show();
        }
    });
    
    $('#parse-m3u-btn').on('click', function() {
        parseM3U();
    });
    
    $('#back-to-step1').on('click', function() {
        showImportStep(1);
    });
    
    $('#start-import-btn').on('click', function() {
        startImport();
    });
    
    $('#close-import-modal').on('click', function() {
        closeM3UModal();
    });
    
    function openM3UModal() {
        $('#m3u-import-modal').show();
        showImportStep(1);
        resetImportModal();
    }
    
    function resetImportModal() {
        parsedM3UData = null;
        $('#m3u-file').val('');
        $('#m3u-url').val('');
        $('input[name="import_source"][value="file"]').prop('checked', true);
        $('#file-upload-section').show();
        $('#url-input-section').hide();
        $('#default-category').val('Other');
        showImportStep(1);
    }
    
    function showImportStep(step) {
        $('.import-step').hide();
        $('#import-step-' + step).show();
    }
    
    function parseM3U() {
        const source = $('input[name="import_source"]:checked').val();
        let content = '';
        let url = '';
        
        if (source === 'file') {
            const fileInput = $('#m3u-file')[0];
            if (!fileInput.files || !fileInput.files[0]) {
                showNotification('<?php _e('Please select an M3U file.', 'live-tv-streaming'); ?>', 'error');
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                content = e.target.result;
                parseM3UContent(content, source, '');
            };
            reader.readAsText(fileInput.files[0]);
        } else {
            url = $('#m3u-url').val().trim();
            if (!url) {
                showNotification('<?php _e('Please enter an M3U URL.', 'live-tv-streaming'); ?>', 'error');
                return;
            }
            parseM3UContent('', source, url);
        }
    }
    
    function parseM3UContent(content, source, url) {
        $('#parse-m3u-btn').prop('disabled', true).text('<?php _e('Parsing...', 'live-tv-streaming'); ?>');
        
        $.ajax({
            url: liveTVAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'live_tv_parse_m3u_preview',
                nonce: liveTVAdmin.nonce,
                source: source,
                content: content,
                url: url
            },
            success: function(response) {
                if (response.success) {
                    parsedM3UData = response.data;
                    displayM3UPreview();
                    showImportStep(2);
                } else {
                    showNotification(response.data || '<?php _e('Failed to parse M3U file.', 'live-tv-streaming'); ?>', 'error');
                }
            },
            error: function() {
                showNotification('<?php _e('Network error occurred while parsing.', 'live-tv-streaming'); ?>', 'error');
            },
            complete: function() {
                $('#parse-m3u-btn').prop('disabled', false).text('<?php _e('Parse & Preview', 'live-tv-streaming'); ?>');
            }
        });
    }
    
    function displayM3UPreview() {
        if (!parsedM3UData) return;
        
        $('#channels-found').text(parsedM3UData.total_count);
        $('#categories-found').text(parsedM3UData.categories.length);
        
        // Display category mappings
        const $mappings = $('#category-mappings');
        $mappings.empty();
        
        if (parsedM3UData.categories.length > 0) {
            parsedM3UData.categories.forEach(function(category) {
                $mappings.append(`
                    <div class="category-mapping-row" style="display: flex; align-items: center; gap: 10px; margin: 10px 0;">
                        <div class="source-category" style="width: 150px; font-weight: 500;">${escapeHtml(category)}</div>
                        <div style="margin: 0 10px;">→</div>
                        <select class="category-map ltv-select" data-source="${escapeHtml(category)}" style="width: 150px;">
                            <option value="Other"><?php _e('Other', 'live-tv-streaming'); ?></option>
                            <option value="News" ${category.toLowerCase().includes('news') ? 'selected' : ''}><?php _e('News', 'live-tv-streaming'); ?></option>
                            <option value="Sports" ${category.toLowerCase().includes('sport') ? 'selected' : ''}><?php _e('Sports', 'live-tv-streaming'); ?></option>
                            <option value="Entertainment" ${category.toLowerCase().includes('entertainment') ? 'selected' : ''}><?php _e('Entertainment', 'live-tv-streaming'); ?></option>
                            <option value="Movies" ${category.toLowerCase().includes('movie') ? 'selected' : ''}><?php _e('Movies', 'live-tv-streaming'); ?></option>
                            <option value="Music" ${category.toLowerCase().includes('music') ? 'selected' : ''}><?php _e('Music', 'live-tv-streaming'); ?></option>
                            <option value="Documentary" ${category.toLowerCase().includes('doc') ? 'selected' : ''}><?php _e('Documentary', 'live-tv-streaming'); ?></option>
                            <option value="Kids" ${category.toLowerCase().includes('kid') || category.toLowerCase().includes('child') ? 'selected' : ''}><?php _e('Kids', 'live-tv-streaming'); ?></option>
                        </select>
                    </div>
                `);
            });
        }
        
        // Display channel preview (first 10)
        const $preview = $('#channels-preview');
        $preview.empty();
        
        const previewChannels = parsedM3UData.channels.slice(0, 10);
        previewChannels.forEach(function(channel) {
            $preview.append(`
                <div class="channel-preview-item" style="display: flex; align-items: center; padding: 10px; border: 1px solid var(--line); border-radius: 4px; margin: 5px 0;">
                    <div class="channel-logo" style="width: 40px; height: 40px; margin-right: 10px; border-radius: 4px; overflow: hidden; background: var(--bg-3); display: flex; align-items: center; justify-content: center;">
                        ${channel.logo_url ? 
                            `<img src="${escapeHtml(channel.logo_url)}" alt="${escapeHtml(channel.name)}" style="width: 100%; height: 100%; object-fit: cover;">` :
                            '<span class="dashicons dashicons-video-alt2" style="color: var(--muted);"></span>'
                        }
                    </div>
                    <div class="channel-info" style="flex: 1;">
                        <div class="channel-name" style="font-weight: 500; font-size: 14px;">${escapeHtml(channel.name)}</div>
                        <div class="channel-details" style="font-size: 12px; color: var(--muted);">
                            <span class="category-tag" style="background: var(--accent-500); color: #041018; padding: 2px 6px; border-radius: 10px; margin-right: 8px;">${escapeHtml(channel.category)}</span>
                            ${channel.description ? escapeHtml(channel.description) : ''}
                        </div>
                    </div>
                </div>
            `);
        });
        
        if (parsedM3UData.total_count > 10) {
            $preview.append(`
                <div style="text-align: center; padding: 10px; color: var(--muted);">
                    <?php _e('... and', 'live-tv-streaming'); ?> ${parsedM3UData.total_count - 10} <?php _e('more channels', 'live-tv-streaming'); ?>
                </div>
            `);
        }
    }
    
    function startImport() {
        if (!parsedM3UData) return;
        
        showImportStep(3);
        
        // Collect category mappings
        const categoryMapping = {};
        $('.category-map').each(function() {
            const sourceCategory = $(this).data('source');
            const targetCategory = $(this).val();
            categoryMapping[sourceCategory] = targetCategory;
        });
        
        const defaultCategory = $('#default-category').val();
        
        $('#import-status').text('<?php _e('Importing channels...', 'live-tv-streaming'); ?>');
        $('#import-progress-fill').css('width', '0%');
        
        $.ajax({
            url: liveTVAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'live_tv_import_m3u',
                nonce: liveTVAdmin.nonce,
                import_data: JSON.stringify(parsedM3UData.channels),
                category_mapping: categoryMapping,
                default_category: defaultCategory
            },
            success: function(response) {
                $('#import-progress-fill').css('width', '100%');
                
                if (response.success) {
                    const results = response.data;
                    $('#import-status').text('<?php _e('Import completed successfully!', 'live-tv-streaming'); ?>');
                    
                    // Show results
                    $('#imported-count').text(results.imported);
                    $('#skipped-count').text(results.skipped);
                    $('#categories-created').text(results.categories_created ? results.categories_created.join(', ') : '<?php _e('None', 'live-tv-streaming'); ?>');
                    
                    if (results.errors && results.errors.length > 0) {
                        $('#import-errors').show();
                        const $errorList = $('#error-list');
                        $errorList.empty();
                        results.errors.forEach(function(error) {
                            $errorList.append(`<li>${escapeHtml(error)}</li>`);
                        });
                    }
                    
                    $('#import-results').show();
                    $('#close-import-modal').show();
                    
                    // Refresh channels list
                    loadChannels();
                    loadChannelStats();
                    
                    showNotification('<?php _e('M3U import completed successfully!', 'live-tv-streaming'); ?>', 'success');
                } else {
                    $('#import-status').text('<?php _e('Import failed: ', 'live-tv-streaming'); ?>' + (response.data || '<?php _e('Unknown error', 'live-tv-streaming'); ?>'));
                    showNotification('<?php _e('Import failed: ', 'live-tv-streaming'); ?>' + (response.data || '<?php _e('Unknown error', 'live-tv-streaming'); ?>'), 'error');
                }
            },
            error: function() {
                $('#import-status').text('<?php _e('Network error during import', 'live-tv-streaming'); ?>');
                showNotification('<?php _e('Network error occurred during import.', 'live-tv-streaming'); ?>', 'error');
            }
        });
    }
    
    // Global functions
    window.openM3UModal = openM3UModal;
    window.closeM3UModal = function() {
        $('#m3u-import-modal').hide();
        resetImportModal();
    };
    
});
</script>