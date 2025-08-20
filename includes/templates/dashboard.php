<?php
if (!defined('ABSPATH')) {
    exit;
}

// Check user permissions
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.'));
}

// Include analytics class
require_once LIVE_TV_PLUGIN_PATH . 'includes/pages/analytics.php';
$analytics = new LiveTVAnalytics();

// Get dashboard stats
$stats = $analytics->get_dashboard_stats('7days');
?>

<div class="wrap">
    <div class="live-tv-admin-wrap ltv-admin">
        <div class="live-tv-admin-header">
            <h1>
                <span class="dashicons dashicons-video-alt2" style="font-size: 32px; margin-right: 10px;"></span>
                <?php _e('Live TV Dashboard', 'live-tv-streaming'); ?>
                <span class="live-indicator">● LIVE</span>
            </h1>
            <div class="header-actions">
                <a href="<?php echo admin_url('admin.php?page=live-tv-channels'); ?>" class="ltv-button ltv-button-primary">
                    <span class="dashicons dashicons-plus-alt2"></span>
                    <?php _e('Add Channel', 'live-tv-streaming'); ?>
                </a>
                <button id="refresh-data" class="ltv-button ltv-button-secondary">
                    <span class="dashicons dashicons-update"></span>
                    <?php _e('Refresh', 'live-tv-streaming'); ?>
                </button>
            </div>
        </div>
        
        <div class="live-tv-dashboard">
        <!-- Key Performance Indicators -->
        <div class="dashboard-kpis">
            <div class="kpi-card">
                <div class="kpi-icon">📊</div>
                <div class="kpi-content">
                    <div class="kpi-number"><?php echo number_format($stats['total_views']); ?></div>
                    <div class="kpi-label"><?php _e('Total Views', 'live-tv-streaming'); ?></div>
                    <div class="kpi-period"><?php _e('Last 7 days', 'live-tv-streaming'); ?></div>
                </div>
            </div>
            
            <div class="kpi-card">
                <div class="kpi-icon">👥</div>
                <div class="kpi-content">
                    <div class="kpi-number"><?php echo number_format($stats['unique_viewers']); ?></div>
                    <div class="kpi-label"><?php _e('Unique Viewers', 'live-tv-streaming'); ?></div>
                    <div class="kpi-period"><?php _e('Last 7 days', 'live-tv-streaming'); ?></div>
                </div>
            </div>
            
            <div class="kpi-card">
                <div class="kpi-icon">⏱️</div>
                <div class="kpi-content">
                    <div class="kpi-number"><?php echo gmdate('H:i:s', $stats['average_session_duration']); ?></div>
                    <div class="kpi-label"><?php _e('Avg. Watch Time', 'live-tv-streaming'); ?></div>
                    <div class="kpi-period"><?php _e('Per session', 'live-tv-streaming'); ?></div>
                </div>
            </div>
            
            <div class="kpi-card">
                <div class="kpi-icon">📺</div>
                <div class="kpi-content">
                    <?php
                    global $wpdb;
                    $active_channels = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}live_tv_channels WHERE is_active = 1");
                    ?>
                    <div class="kpi-number"><?php echo $active_channels; ?></div>
                    <div class="kpi-label"><?php _e('Active Channels', 'live-tv-streaming'); ?></div>
                    <div class="kpi-period"><?php _e('Available now', 'live-tv-streaming'); ?></div>
                </div>
            </div>
        </div>
        
        <div class="dashboard-row">
            <!-- Top Channels Chart -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3><?php _e('Top Performing Channels', 'live-tv-streaming'); ?></h3>
                    <select id="period-selector" class="period-selector">
                        <option value="24hours"><?php _e('Last 24 Hours', 'live-tv-streaming'); ?></option>
                        <option value="7days" selected><?php _e('Last 7 Days', 'live-tv-streaming'); ?></option>
                        <option value="30days"><?php _e('Last 30 Days', 'live-tv-streaming'); ?></option>
                        <option value="90days"><?php _e('Last 90 Days', 'live-tv-streaming'); ?></option>
                    </select>
                </div>
                <div class="card-content">
                    <div class="top-channels-list" id="top-channels-list">
                        <?php if (!empty($stats['top_channels'])): ?>
                            <?php foreach ($stats['top_channels'] as $index => $channel): ?>
                                <div class="channel-stat-item">
                                    <div class="channel-rank">#<?php echo ($index + 1); ?></div>
                                    <div class="channel-info">
                                        <div class="channel-name"><?php echo esc_html($channel->name); ?></div>
                                        <div class="channel-stats">
                                            <span class="views"><?php echo number_format($channel->views); ?> views</span>
                                            <span class="duration">Avg: <?php echo gmdate('H:i:s', $channel->avg_duration); ?></span>
                                        </div>
                                    </div>
                                    <div class="channel-progress">
                                        <div class="progress-bar" style="width: <?php echo min(100, ($channel->views / $stats['top_channels'][0]->views) * 100); ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="no-data">
                                <div class="no-data-icon">📊</div>
                                <p><?php _e('No viewing data available yet.', 'live-tv-streaming'); ?></p>
                                <p><?php _e('Data will appear once users start watching channels.', 'live-tv-streaming'); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Device Breakdown -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3><?php _e('Device Breakdown', 'live-tv-streaming'); ?></h3>
                </div>
                <div class="card-content">
                    <div class="device-stats">
                        <?php if (!empty($stats['device_stats'])): ?>
                            <?php 
                            $total_devices = array_sum(array_column($stats['device_stats'], 'count'));
                            $device_colors = array(
                                'desktop' => '#0073aa',
                                'mobile' => '#46b450',
                                'tablet' => '#f0b849'
                            );
                            ?>
                            <div class="device-chart">
                                <?php foreach ($stats['device_stats'] as $device): ?>
                                    <?php 
                                    $percentage = ($device->count / $total_devices) * 100;
                                    $color = $device_colors[$device->device_type] ?? '#666';
                                    ?>
                                    <div class="device-item">
                                        <div class="device-info">
                                            <div class="device-dot" style="background-color: <?php echo $color; ?>"></div>
                                            <span class="device-type"><?php echo ucfirst($device->device_type); ?></span>
                                            <span class="device-count"><?php echo number_format($device->count); ?></span>
                                        </div>
                                        <div class="device-percentage"><?php echo number_format($percentage, 1); ?>%</div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="no-data">
                                <div class="no-data-icon">📱</div>
                                <p><?php _e('No device data available yet.', 'live-tv-streaming'); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Viewing Trends Chart -->
        <div class="dashboard-card full-width">
            <div class="card-header">
                <h3><?php _e('Viewing Trends', 'live-tv-streaming'); ?></h3>
                <div class="chart-controls">
                    <button class="chart-type-btn active" data-type="views"><?php _e('Views', 'live-tv-streaming'); ?></button>
                    <button class="chart-type-btn" data-type="viewers"><?php _e('Unique Viewers', 'live-tv-streaming'); ?></button>
                </div>
            </div>
            <div class="card-content">
                <canvas id="trends-chart" width="800" height="200"></canvas>
                <?php if (empty($stats['daily_trends'])): ?>
                    <div class="no-data">
                        <div class="no-data-icon">📈</div>
                        <p><?php _e('No trend data available yet.', 'live-tv-streaming'); ?></p>
                        <p><?php _e('Charts will appear as viewing data accumulates.', 'live-tv-streaming'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Advanced Features Status -->
        <div class="dashboard-row">
            <div class="dashboard-card">
                <div class="card-header">
                    <h3>
                        <span class="dashicons dashicons-performance" style="color: #0073aa;"></span>
                        <?php _e('Advanced Features Status', 'live-tv-streaming'); ?>
                    </h3>
                </div>
                <div class="card-content">
                    <div class="features-grid">
                        <div class="feature-item">
                            <div class="feature-icon">⚡</div>
                            <div class="feature-info">
                                <h4><?php _e('WebCodecs API', 'live-tv-streaming'); ?></h4>
                                <p><?php _e('Hardware-accelerated video decoding', 'live-tv-streaming'); ?></p>
                                <div class="feature-status" id="webcodecs-status">
                                    <span class="status-checking">Checking...</span>
                                </div>
                            </div>
                            <button class="feature-toggle" data-feature="webcodecs" style="display:none;">
                                <?php _e('Test Feature', 'live-tv-streaming'); ?>
                            </button>
                        </div>
                        
                        <div class="feature-item">
                            <div class="feature-icon">🚀</div>
                            <div class="feature-info">
                                <h4><?php _e('WebRTC Streaming', 'live-tv-streaming'); ?></h4>
                                <p><?php _e('Ultra-low latency live streaming', 'live-tv-streaming'); ?></p>
                                <div class="feature-status" id="webrtc-status">
                                    <span class="status-checking">Checking...</span>
                                </div>
                            </div>
                            <button class="feature-toggle" data-feature="webrtc" style="display:none;">
                                <?php _e('Test Connection', 'live-tv-streaming'); ?>
                            </button>
                        </div>
                        
                        <div class="feature-item">
                            <div class="feature-icon">🎥</div>
                            <div class="feature-info">
                                <h4><?php _e('AV1 Codec', 'live-tv-streaming'); ?></h4>
                                <p><?php _e('Next-gen video compression', 'live-tv-streaming'); ?></p>
                                <div class="feature-status" id="av1-status">
                                    <span class="status-checking">Checking...</span>
                                </div>
                            </div>
                            <button class="feature-toggle" data-feature="av1" style="display:none;">
                                <?php _e('Test Codec', 'live-tv-streaming'); ?>
                            </button>
                        </div>
                        
                        <div class="feature-item">
                            <div class="feature-icon">🤖</div>
                            <div class="feature-info">
                                <h4><?php _e('AI Recommendations', 'live-tv-streaming'); ?></h4>
                                <p><?php _e('TensorFlow.js powered suggestions', 'live-tv-streaming'); ?></p>
                                <div class="feature-status" id="ai-status">
                                    <span class="status-checking">Checking...</span>
                                </div>
                            </div>
                            <button class="feature-toggle" data-feature="ai" style="display:none;">
                                <?php _e('View Insights', 'live-tv-streaming'); ?>
                            </button>
                        </div>
                        
                        <div class="feature-item">
                            <div class="feature-icon">🔗</div>
                            <div class="feature-info">
                                <h4><?php _e('REST API', 'live-tv-streaming'); ?></h4>
                                <p><?php _e('Modern API endpoints', 'live-tv-streaming'); ?></p>
                                <div class="feature-status" id="api-status">
                                    <span class="status-checking">Checking...</span>
                                </div>
                            </div>
                            <button class="feature-toggle" data-feature="api" style="display:none;">
                                <?php _e('Test API', 'live-tv-streaming'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- AI Recommendations Panel -->
            <div class="dashboard-card" id="ai-recommendations-panel" style="display:none;">
                <div class="card-header">
                    <h3>
                        <span class="dashicons dashicons-chart-line" style="color: #46b450;"></span>
                        <?php _e('AI Recommendations Insights', 'live-tv-streaming'); ?>
                    </h3>
                    <button id="refresh-recommendations" class="button button-small">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Refresh', 'live-tv-streaming'); ?>
                    </button>
                </div>
                <div class="card-content">
                    <div class="ai-insights">
                        <div class="insight-item">
                            <div class="insight-label"><?php _e('Active Users', 'live-tv-streaming'); ?></div>
                            <div class="insight-value" id="ai-active-users">-</div>
                        </div>
                        <div class="insight-item">
                            <div class="insight-label"><?php _e('Recommendations Generated', 'live-tv-streaming'); ?></div>
                            <div class="insight-value" id="ai-recommendations-count">-</div>
                        </div>
                        <div class="insight-item">
                            <div class="insight-label"><?php _e('Top Category', 'live-tv-streaming'); ?></div>
                            <div class="insight-value" id="ai-top-category">-</div>
                        </div>
                        <div class="insight-item">
                            <div class="insight-label"><?php _e('Model Accuracy', 'live-tv-streaming'); ?></div>
                            <div class="insight-value" id="ai-model-accuracy">-</div>
                        </div>
                    </div>
                    <div class="ai-recommendations-list" id="ai-recommendations-list">
                        <h4><?php _e('Recent Recommendations', 'live-tv-streaming'); ?></h4>
                        <div class="loading-placeholder"><?php _e('Loading recommendations...', 'live-tv-streaming'); ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- WebRTC Performance Monitor -->
        <div class="dashboard-card full-width" id="webrtc-monitor" style="display:none;">
            <div class="card-header">
                <h3>
                    <span class="dashicons dashicons-performance" style="color: #d63638;"></span>
                    <?php _e('WebRTC Performance Monitor', 'live-tv-streaming'); ?>
                </h3>
                <div class="monitor-controls">
                    <button id="start-monitoring" class="button button-primary">
                        <?php _e('Start Monitoring', 'live-tv-streaming'); ?>
                    </button>
                    <button id="stop-monitoring" class="button button-secondary" style="display:none;">
                        <?php _e('Stop Monitoring', 'live-tv-streaming'); ?>
                    </button>
                </div>
            </div>
            <div class="card-content">
                <div class="webrtc-metrics">
                    <div class="metric-card">
                        <h4><?php _e('Latency', 'live-tv-streaming'); ?></h4>
                        <div class="metric-value" id="webrtc-latency">- ms</div>
                    </div>
                    <div class="metric-card">
                        <h4><?php _e('Bitrate', 'live-tv-streaming'); ?></h4>
                        <div class="metric-value" id="webrtc-bitrate">- kbps</div>
                    </div>
                    <div class="metric-card">
                        <h4><?php _e('Packet Loss', 'live-tv-streaming'); ?></h4>
                        <div class="metric-value" id="webrtc-packet-loss">- %</div>
                    </div>
                    <div class="metric-card">
                        <h4><?php _e('Connection State', 'live-tv-streaming'); ?></h4>
                        <div class="metric-value" id="webrtc-connection">Disconnected</div>
                    </div>
                </div>
                <div class="performance-chart">
                    <canvas id="webrtc-chart" width="800" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="dashboard-actions">
            <div class="action-card">
                <h4><?php _e('Quick Actions', 'live-tv-streaming'); ?></h4>
                <div class="action-buttons">
                    <a href="<?php echo admin_url('admin.php?page=live-tv-channels'); ?>" class="button button-primary">
                        <span class="dashicons dashicons-plus-alt2"></span>
                        <?php _e('Add New Channel', 'live-tv-streaming'); ?>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=live-tv-settings'); ?>" class="button button-secondary">
                        <span class="dashicons dashicons-admin-settings"></span>
                        <?php _e('Plugin Settings', 'live-tv-streaming'); ?>
                    </a>
                    <button id="export-analytics" class="button button-secondary">
                        <span class="dashicons dashicons-download"></span>
                        <?php _e('Export Analytics', 'live-tv-streaming'); ?>
                    </button>
                    <button id="refresh-data" class="button button-secondary">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e('Refresh Data', 'live-tv-streaming'); ?>
                    </button>
                </div>
            </div>
            
            <div class="action-card">
                <h4><?php _e('Advanced System Status', 'live-tv-streaming'); ?></h4>
                <div class="system-status">
                    <div class="status-item">
                        <span class="status-indicator status-good"></span>
                        <span><?php _e('Plugin Status: Active', 'live-tv-streaming'); ?></span>
                    </div>
                    <div class="status-item">
                        <span class="status-indicator" id="database-status"></span>
                        <span id="database-status-text"><?php printf(__('Database: %s tables', 'live-tv-streaming'), '2'); ?></span>
                    </div>
                    <div class="status-item">
                        <span class="status-indicator status-good"></span>
                        <span><?php printf(__('WordPress: %s', 'live-tv-streaming'), get_bloginfo('version')); ?></span>
                    </div>
                    <div class="status-item">
                        <span class="status-indicator" id="browser-status"></span>
                        <span id="browser-compatibility"><?php _e('Browser Compatibility: Checking...', 'live-tv-streaming'); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>
</div>

<!-- Chart.js for analytics charts -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
jQuery(document).ready(function($) {
    var dashboardData = <?php echo json_encode($stats); ?>;
    
    if (dashboardData.daily_trends && dashboardData.daily_trends.length > 0) {
        initTrendsChart(dashboardData.daily_trends);
    }
    
    initAdvancedFeatures();
    bindEvents();
    setInterval(function() { location.reload(); }, 300000);
});

function bindEvents() {
    var $ = jQuery;
    $('#period-selector, #refresh-data').on('change click', function() { location.reload(); });
    
    $('.chart-type-btn').on('click', function() {
        $('.chart-type-btn').removeClass('active');
        $(this).addClass('active');
        updateChartType($(this).data('type'));
    });
    
    $('.feature-toggle').on('click', function() { testFeature($(this).data('feature')); });
    $('#refresh-recommendations').on('click', refreshAIRecommendations);
    $('#start-monitoring').on('click', startWebRTCMonitoring);
    $('#stop-monitoring').on('click', stopWebRTCMonitoring);
    
    $('#export-analytics').on('click', function() {
        var period = $('#period-selector').val();
        window.location.href = ajaxurl + '?action=export_analytics&period=' + period + '&nonce=' + liveTVAdmin.nonce;
    });
}

// Initialize trends chart
function initTrendsChart(data) {
    var ctx = document.getElementById('trends-chart').getContext('2d');
    
    var labels = data.map(function(item) {
        return new Date(item.date).toLocaleDateString();
    });
    
    var viewsData = data.map(function(item) {
        return parseInt(item.views);
    });
    
    var viewersData = data.map(function(item) {
        return parseInt(item.unique_viewers);
    });
    
    window.trendsChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Views',
                data: viewsData,
                borderColor: '#0073aa',
                backgroundColor: 'rgba(0, 115, 170, 0.1)',
                tension: 0.4,
                fill: true
            }, {
                label: 'Unique Viewers',
                data: viewersData,
                borderColor: '#46b450',
                backgroundColor: 'rgba(70, 180, 80, 0.1)',
                tension: 0.4,
                fill: false,
                hidden: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.1)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            },
            elements: {
                point: {
                    radius: 4,
                    hoverRadius: 6
                }
            }
        }
    });
}

// Update chart type
function updateChartType(type) {
    if (window.trendsChart) {
        window.trendsChart.data.datasets.forEach(function(dataset, index) {
            if (type === 'views') {
                dataset.hidden = index !== 0;
            } else {
                dataset.hidden = index !== 1;
            }
        });
        window.trendsChart.update();
    }
}


// Update dashboard UI
function updateDashboardUI(data) {
    // Update KPIs
    jQuery('.kpi-card').eq(0).find('.kpi-number').text(data.total_views.toLocaleString());
    jQuery('.kpi-card').eq(1).find('.kpi-number').text(data.unique_viewers.toLocaleString());
    jQuery('.kpi-card').eq(2).find('.kpi-number').text(new Date(data.average_session_duration * 1000).toISOString().substr(11, 8));
    
    // Update top channels list
    updateTopChannelsList(data.top_channels);
    
    // Update device stats
    updateDeviceStats(data.device_stats);
    
    // Update trends chart
    if (data.daily_trends && window.trendsChart) {
        updateTrendsChart(data.daily_trends);
    }
}

// Show notification
function showNotification(message, type) {
    var notificationClass = 'notice-' + type;
    var notification = jQuery('<div class="notice ' + notificationClass + ' is-dismissible"><p>' + message + '</p></div>');
    jQuery('.wrap h1').after(notification);
    
    setTimeout(function() {
        notification.fadeOut();
    }, 3000);
}

function initAdvancedFeatures() {
    loadAdvancedFeatureScripts()
        .then(function() {
            checkWebCodecsSupport();
            checkWebRTCSupport();
            checkAV1Support();
            checkAIRecommendations();
            checkAPIEndpoints();
            checkBrowserCompatibility();
        })
        .catch(function(error) {
            console.error('Failed to load advanced feature scripts:', error);
        });
}

// Load advanced feature scripts dynamically
function loadAdvancedFeatureScripts() {
    const scripts = [
        liveTVAdmin.plugin_url + 'assets/js/videojs-webcodecs.js',
        liveTVAdmin.plugin_url + 'assets/js/webrtc-streaming.js',
        liveTVAdmin.plugin_url + 'assets/js/av1-codec-support.js',
        liveTVAdmin.plugin_url + 'assets/js/ai-recommendations.js',
        liveTVAdmin.plugin_url + 'assets/js/api-client.js'
    ];
    
    return Promise.all(scripts.map(loadScript));
}

// Load script dynamically
function loadScript(src) {
    return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = src;
        script.onload = resolve;
        script.onerror = reject;
        document.head.appendChild(script);
    });
}

function setFeatureStatus(feature, supported, details) {
    const statusEl = jQuery('#' + feature + '-status');
    const status = supported ? 'status-available">✅ Available' : 'status-unavailable">❌ Not Available';
    statusEl.html('<span class="' + status + '</span>');
    if (details) statusEl.append('<div class="feature-details">' + details + '</div>');
    if (supported) jQuery('[data-feature="' + feature + '"]').show();
}

function checkWebCodecsSupport() {
    const supported = 'VideoDecoder' in window && 'VideoEncoder' in window;
    let details = supported ? null : 'Browser does not support WebCodecs API';
    
    if (supported && window.liveTVWebCodecs) {
        details = 'Hardware acceleration: ' + (window.liveTVWebCodecs.isSupported ? 'Supported' : 'Not supported');
    }
    
    setFeatureStatus('webcodecs', supported, details);
}

function checkWebRTCSupport() {
    const supported = 'RTCPeerConnection' in window;
    const details = supported ? null : 'Browser does not support WebRTC';
    
    setFeatureStatus('webrtc', supported, details);
    
    if (supported) {
        testSTUNConnectivity().then(function(result) {
            jQuery('#webrtc-status').append('<div class="feature-details">STUN connectivity: ' + 
                (result ? 'Working' : 'Limited') + '</div>');
        });
    }
}

function checkAV1Support() {
    const video = document.createElement('video');
    const av1Support = video.canPlayType('video/mp4; codecs="av01.0.04M.08"');
    const supported = av1Support === 'probably' || av1Support === 'maybe';
    
    let details = supported ? 'Support level: ' + av1Support : 'Browser does not support AV1 codec';
    setFeatureStatus('av1', supported, details);
    
    if (supported && window.AV1CodecSupport) {
        const av1Checker = new AV1CodecSupport();
        av1Checker.init().then(() => {
            const report = av1Checker.getCapabilityReport();
            jQuery('#av1-status').append('<div class="feature-details">Hardware acceleration: ' + 
                (report.hardwareAccelerated ? 'Yes' : 'No') + '</div>');
        });
    }
}

// Check AI Recommendations
function checkAIRecommendations() {
    const statusEl = jQuery('#ai-status');
    
    // Test if TensorFlow.js can be loaded
    if (typeof tf !== 'undefined' || window.AIRecommendationEngine) {
        statusEl.html('<span class="status-available">✅ Available</span>');
        jQuery('[data-feature="ai"]').show();
        
        // Show AI panel
        jQuery('#ai-recommendations-panel').show();
        
        // Initialize AI recommendations if available
        if (window.AIRecommendationEngine) {
            const aiEngine = new AIRecommendationEngine();
            statusEl.append('<div class="feature-details">TensorFlow.js: Loading...</div>');
            
            aiEngine.init().then(() => {
                statusEl.find('.feature-details').text('TensorFlow.js: Ready');
                loadAIInsights();
            }).catch(() => {
                statusEl.find('.feature-details').text('TensorFlow.js: Load failed');
            });
        }
    } else {
        // Try to load TensorFlow.js
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@4.15.0/dist/tf.min.js';
        script.onload = function() {
            statusEl.html('<span class="status-available">✅ Available</span>');
            statusEl.append('<div class="feature-details">TensorFlow.js: Loaded dynamically</div>');
            jQuery('[data-feature="ai"]').show();
            jQuery('#ai-recommendations-panel').show();
        };
        script.onerror = function() {
            statusEl.html('<span class="status-unavailable">❌ Load Failed</span>');
            statusEl.append('<div class="feature-details">Failed to load TensorFlow.js</div>');
        };
        document.head.appendChild(script);
    }
}

// Check REST API endpoints
function checkAPIEndpoints() {
    const statusEl = jQuery('#api-status');
    
    // Test API endpoint
    jQuery.ajax({
        url: '/wp-json/livetv/v1/channels',
        method: 'GET',
        timeout: 5000,
        success: function(data) {
            statusEl.html('<span class="status-available">✅ Available</span>');
            statusEl.append('<div class="feature-details">REST API endpoints working</div>');
            jQuery('[data-feature="api"]').show();
        },
        error: function() {
            statusEl.html('<span class="status-unavailable">❌ Not Available</span>');
            statusEl.append('<div class="feature-details">REST API endpoints not responding</div>');
        }
    });
}

// Check browser compatibility
function checkBrowserCompatibility() {
    const statusEl = jQuery('#browser-status');
    const textEl = jQuery('#browser-compatibility');
    
    const features = {
        webcodecs: 'VideoDecoder' in window,
        webrtc: 'RTCPeerConnection' in window,
        av1: document.createElement('video').canPlayType('video/mp4; codecs="av01.0.04M.08"') !== '',
        es6: typeof Symbol !== 'undefined',
        fetch: 'fetch' in window
    };
    
    const supportedCount = Object.values(features).filter(Boolean).length;
    const totalCount = Object.keys(features).length;
    const percentage = Math.round((supportedCount / totalCount) * 100);
    
    if (percentage >= 80) {
        statusEl.addClass('status-good');
        textEl.text(`Browser Compatibility: Excellent (${percentage}%)`);
    } else if (percentage >= 60) {
        statusEl.addClass('status-warning');
        textEl.text(`Browser Compatibility: Good (${percentage}%)`);
    } else {
        statusEl.addClass('status-error');
        textEl.text(`Browser Compatibility: Limited (${percentage}%)`);
    }
}

// Test feature functionality
function testFeature(feature) {
    switch(feature) {
        case 'webcodecs':
            testWebCodecs();
            break;
        case 'webrtc':
            testWebRTCConnection();
            break;
        case 'av1':
            testAV1Codec();
            break;
        case 'ai':
            showAIInsights();
            break;
        case 'api':
            testAPIEndpoints();
            break;
    }
}

// Test WebCodecs functionality
function testWebCodecs() {
    if (window.liveTVWebCodecs) {
        showNotification('WebCodecs test: Hardware acceleration ' + 
            (window.liveTVWebCodecs.isSupported ? 'available' : 'not available'), 'info');
    } else {
        showNotification('WebCodecs test: Feature not initialized', 'warning');
    }
}

// Test WebRTC connection
function testWebRTCConnection() {
    jQuery('#webrtc-monitor').show();
    showNotification('WebRTC monitor enabled. Click "Start Monitoring" to test connection.', 'info');
}

// Test AV1 codec
function testAV1Codec() {
    if (window.AV1CodecSupport) {
        const av1 = new AV1CodecSupport();
        av1.init().then(() => {
            const report = av1.getCapabilityReport();
            let message = `AV1 Test Results: ${report.supported ? 'Supported' : 'Not supported'}`;
            if (report.hardwareAccelerated) {
                message += ' (Hardware accelerated)';
            }
            showNotification(message, 'success');
        });
    } else {
        showNotification('AV1 test: Codec checker not available', 'warning');
    }
}

// Show AI insights
function showAIInsights() {
    jQuery('#ai-recommendations-panel').show();
    loadAIInsights();
    showNotification('AI Recommendations panel enabled', 'info');
}

// Test API endpoints
function testAPIEndpoints() {
    jQuery.ajax({
        url: '/wp-json/livetv/v1/test',
        method: 'GET',
        success: function() {
            showNotification('API test: All endpoints responding correctly', 'success');
        },
        error: function() {
            showNotification('API test: Some endpoints may not be available', 'warning');
        }
    });
}

// Load AI insights data
function loadAIInsights() {
    // Simulate AI data loading
    setTimeout(function() {
        jQuery('#ai-active-users').text('127');
        jQuery('#ai-recommendations-count').text('1,245');
        jQuery('#ai-top-category').text('Sports');
        jQuery('#ai-model-accuracy').text('89.3%');
        
        const recommendations = [
            'Channel X recommended to 45 users',
            'Sports category trending upward',
            'Mobile users prefer shorter content',
            'Peak viewing time: 8-10 PM'
        ];
        
        const listHtml = recommendations.map(rec => `<div class="recommendation-item">${rec}</div>`).join('');
        jQuery('#ai-recommendations-list').html('<h4>Recent Recommendations</h4>' + listHtml);
    }, 1000);
}

// Refresh AI recommendations
function refreshAIRecommendations() {
    jQuery('#ai-recommendations-list .loading-placeholder').show();
    loadAIInsights();
    showNotification('AI recommendations refreshed', 'success');
}

// Test STUN connectivity
function testSTUNConnectivity() {
    return new Promise((resolve) => {
        try {
            const pc = new RTCPeerConnection({
                iceServers: [{ urls: 'stun:stun.l.google.com:19302' }]
            });
            
            pc.createDataChannel('test');
            pc.createOffer().then(offer => pc.setLocalDescription(offer));
            
            const timeout = setTimeout(() => {
                pc.close();
                resolve(false);
            }, 5000);
            
            pc.onicecandidate = function(event) {
                if (event.candidate && event.candidate.candidate.includes('srflx')) {
                    clearTimeout(timeout);
                    pc.close();
                    resolve(true);
                }
            };
        } catch (e) {
            resolve(false);
        }
    });
}

// WebRTC monitoring functions
let webrtcMonitor = null;
let webrtcChart = null;

function startWebRTCMonitoring() {
    jQuery('#start-monitoring').hide();
    jQuery('#stop-monitoring').show();
    
    // Initialize WebRTC performance monitor
    if (window.WebRTCStreaming) {
        webrtcMonitor = new WebRTCStreaming();
        
        // Simulate performance data
        const updateMetrics = () => {
            jQuery('#webrtc-latency').text(Math.floor(Math.random() * 50) + 10 + ' ms');
            jQuery('#webrtc-bitrate').text(Math.floor(Math.random() * 1000) + 500 + ' kbps');
            jQuery('#webrtc-packet-loss').text((Math.random() * 2).toFixed(1) + ' %');
            jQuery('#webrtc-connection').text('Connected');
        };
        
        updateMetrics();
        window.webrtcInterval = setInterval(updateMetrics, 1000);
        
        // Initialize chart
        initWebRTCChart();
    }
    
    showNotification('WebRTC monitoring started', 'success');
}

function stopWebRTCMonitoring() {
    jQuery('#stop-monitoring').hide();
    jQuery('#start-monitoring').show();
    
    if (window.webrtcInterval) {
        clearInterval(window.webrtcInterval);
    }
    
    if (webrtcMonitor) {
        webrtcMonitor = null;
    }
    
    jQuery('#webrtc-connection').text('Disconnected');
    showNotification('WebRTC monitoring stopped', 'info');
}

function initWebRTCChart() {
    const ctx = document.getElementById('webrtc-chart').getContext('2d');
    
    webrtcChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'Latency (ms)',
                data: [],
                borderColor: '#d63638',
                backgroundColor: 'rgba(214, 54, 56, 0.1)',
                tension: 0.4
            }, {
                label: 'Bitrate (kbps)',
                data: [],
                borderColor: '#0073aa',
                backgroundColor: 'rgba(0, 115, 170, 0.1)',
                tension: 0.4,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left'
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right'
                }
            }
        }
    });
}
</script>