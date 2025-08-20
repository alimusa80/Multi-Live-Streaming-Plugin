<?php
if (!defined('ABSPATH')) exit('Direct access denied.');
if (!current_user_can('manage_options')) {
    wp_die(__('You do not have sufficient permissions to access this page.', 'live-tv-streaming'));
}

require_once LIVE_TV_PLUGIN_PATH . 'includes/pages/analytics.php';
$analytics = new LiveTVAnalytics();

$period = sanitize_text_field($_GET['period'] ?? '24hours');
$stats = $analytics->get_dashboard_stats($period);
?>

<div class="wrap">
    <div class="ltv-analytics-container">
        <!-- Header -->
        <div class="ltv-analytics-header">
            <div class="header-content">
                <h1 class="page-title">
                    <span class="title-icon">📊</span>
                    <?php _e('Real-Time Analytics', 'live-tv-streaming'); ?>
                    <span class="live-indicator">
                        <span class="pulse-dot"></span>
                        LIVE
                    </span>
                </h1>
                <div class="header-actions">
                    <button id="toggle-theme" class="action-btn theme-toggle" title="Toggle Dark Mode">
                        <span class="icon">🌙</span>
                    </button>
                    <button id="refresh-all" class="action-btn refresh">
                        <span class="icon">🔄</span>
                        Refresh
                    </button>
                    <div class="dropdown">
                        <button class="action-btn export">
                            <span class="icon">📥</span>
                            Export
                            <span class="dropdown-arrow">▼</span>
                        </button>
                        <div class="dropdown-menu">
                            <a href="#" onclick="exportData('csv')">CSV Report</a>
                            <a href="#" onclick="exportData('pdf')">PDF Report</a>
                            <a href="#" onclick="exportData('json')">JSON Data</a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Time Controls -->
            <div class="time-controls">
                <div class="time-range">
                    <button class="time-btn active" data-range="realtime">Real-time</button>
                    <button class="time-btn" data-range="1h">1H</button>
                    <button class="time-btn" data-range="24h">24H</button>
                    <button class="time-btn" data-range="7d">7D</button>
                    <button class="time-btn" data-range="30d">30D</button>
                </div>
                <div class="auto-refresh">
                    <label class="toggle-switch">
                        <input type="checkbox" id="auto-refresh" checked>
                        <span class="slider"></span>
                    </label>
                    <span>Auto-refresh (5s)</span>
                </div>
            </div>
        </div>

        <!-- Real-time Metrics Dashboard -->
        <div class="metrics-grid">
            <!-- Live Viewers -->
            <div class="metric-card highlight">
                <div class="metric-header">
                    <h3>Live Viewers</h3>
                    <div class="metric-trend up" id="viewers-trend">+12%</div>
                </div>
                <div class="metric-value" id="live-viewers">
                    <span class="number">247</span>
                    <span class="unit">active</span>
                </div>
                <div class="metric-chart">
                    <canvas id="viewers-chart" width="200" height="60"></canvas>
                </div>
            </div>

            <!-- Stream Health -->
            <div class="metric-card">
                <div class="metric-header">
                    <h3>Stream Health</h3>
                    <div class="status-indicator healthy" id="health-status"></div>
                </div>
                <div class="metric-value" id="stream-health">
                    <span class="number">98.5</span>
                    <span class="unit">%</span>
                </div>
                <div class="health-details">
                    <div class="health-item">
                        <span>Uptime</span>
                        <span id="uptime">99.2%</span>
                    </div>
                    <div class="health-item">
                        <span>Avg Latency</span>
                        <span id="latency">45ms</span>
                    </div>
                </div>
            </div>

            <!-- Bandwidth Usage -->
            <div class="metric-card">
                <div class="metric-header">
                    <h3>Bandwidth</h3>
                    <div class="metric-trend" id="bandwidth-trend">-3%</div>
                </div>
                <div class="metric-value" id="bandwidth-usage">
                    <span class="number">1.2</span>
                    <span class="unit">GB/h</span>
                </div>
                <div class="bandwidth-breakdown">
                    <div class="quality-bar">
                        <div class="quality-segment hd" style="width: 45%"></div>
                        <div class="quality-segment sd" style="width: 35%"></div>
                        <div class="quality-segment mobile" style="width: 20%"></div>
                    </div>
                    <div class="quality-legend">
                        <span><span class="dot hd"></span>HD (45%)</span>
                        <span><span class="dot sd"></span>SD (35%)</span>
                        <span><span class="dot mobile"></span>Mobile (20%)</span>
                    </div>
                </div>
            </div>

            <!-- Geographic Distribution -->
            <div class="metric-card">
                <div class="metric-header">
                    <h3>Top Regions</h3>
                    <button class="view-map-btn" onclick="openGeoMap()">🗺️ View Map</button>
                </div>
                <div class="geo-list" id="geo-distribution">
                    <div class="geo-item">
                        <span class="country">🇺🇸 United States</span>
                        <span class="viewers">89 viewers</span>
                    </div>
                    <div class="geo-item">
                        <span class="country">🇬🇧 United Kingdom</span>
                        <span class="viewers">34 viewers</span>
                    </div>
                    <div class="geo-item">
                        <span class="country">🇨🇦 Canada</span>
                        <span class="viewers">23 viewers</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Advanced Analytics Tabs -->
        <div class="analytics-tabs">
            <div class="tab-navigation">
                <button class="tab-btn active" data-tab="overview">📈 Overview</button>
                <button class="tab-btn" data-tab="performance">⚡ Performance</button>
                <button class="tab-btn" data-tab="audience">👥 Audience</button>
                <button class="tab-btn" data-tab="content">📺 Content</button>
                <button class="tab-btn" data-tab="insights">🤖 AI Insights</button>
            </div>

            <!-- Overview Tab -->
            <div class="tab-content active" id="overview-tab">
                <div class="charts-grid">
                    <!-- Main Analytics Chart -->
                    <div class="chart-container large">
                        <div class="chart-header">
                            <h3>Viewing Analytics</h3>
                            <div class="chart-controls">
                                <button class="chart-type-btn active" data-type="viewers">Viewers</button>
                                <button class="chart-type-btn" data-type="duration">Duration</button>
                                <button class="chart-type-btn" data-type="engagement">Engagement</button>
                            </div>
                        </div>
                        <div class="chart-wrapper">
                            <canvas id="main-analytics-chart" width="800" height="400"></canvas>
                            <div class="chart-overlay" id="chart-overlay"></div>
                        </div>
                    </div>

                    <!-- Channel Performance -->
                    <div class="chart-container medium">
                        <div class="chart-header">
                            <h3>Top Channels</h3>
                            <button class="fullscreen-btn" onclick="expandChart('channels')">⛶</button>
                        </div>
                        <div class="channels-list" id="top-channels">
                            <!-- Dynamic content loaded here -->
                        </div>
                    </div>
                </div>

                <!-- Real-time Activity Feed -->
                <div class="activity-feed">
                    <div class="feed-header">
                        <h3>Live Activity</h3>
                        <div class="activity-controls">
                            <button class="pause-feed">⏸️</button>
                            <button class="clear-feed">🗑️</button>
                        </div>
                    </div>
                    <div class="feed-content" id="activity-feed">
                        <!-- Real-time events appear here -->
                    </div>
                </div>
            </div>

            <!-- Performance Tab -->
            <div class="tab-content" id="performance-tab">
                <div class="performance-grid">
                    <!-- Stream Quality Metrics -->
                    <div class="perf-card">
                        <h3>Stream Quality</h3>
                        <div class="quality-metrics">
                            <div class="quality-item">
                                <span class="label">Bitrate</span>
                                <span class="value" id="avg-bitrate">2.4 Mbps</span>
                                <div class="trend positive">+5%</div>
                            </div>
                            <div class="quality-item">
                                <span class="label">Frame Rate</span>
                                <span class="value" id="frame-rate">30 fps</span>
                                <div class="trend stable">0%</div>
                            </div>
                            <div class="quality-item">
                                <span class="label">Buffer Health</span>
                                <span class="value" id="buffer-health">95%</span>
                                <div class="trend positive">+2%</div>
                            </div>
                        </div>
                        <canvas id="quality-chart" width="400" height="200"></canvas>
                    </div>

                    <!-- CDN Performance -->
                    <div class="perf-card">
                        <h3>CDN Performance</h3>
                        <div id="cdn-map" class="cdn-visualization">
                            <!-- Interactive CDN performance map -->
                        </div>
                    </div>

                    <!-- Error Analytics -->
                    <div class="perf-card">
                        <h3>Error Analytics</h3>
                        <div class="error-stats">
                            <div class="error-type">
                                <span class="error-label">Connection Errors</span>
                                <span class="error-count">12</span>
                                <span class="error-rate">0.02%</span>
                            </div>
                            <div class="error-type">
                                <span class="error-label">Buffer Underruns</span>
                                <span class="error-count">8</span>
                                <span class="error-rate">0.01%</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Audience Tab -->
            <div class="tab-content" id="audience-tab">
                <div class="audience-grid">
                    <!-- User Heatmap -->
                    <div class="heatmap-container">
                        <h3>User Engagement Heatmap</h3>
                        <div id="engagement-heatmap" class="heatmap"></div>
                    </div>

                    <!-- Demographics -->
                    <div class="demographics">
                        <h3>Audience Demographics</h3>
                        <div class="demo-charts">
                            <div class="demo-chart">
                                <h4>Age Groups</h4>
                                <canvas id="age-chart" width="200" height="200"></canvas>
                            </div>
                            <div class="demo-chart">
                                <h4>Devices</h4>
                                <canvas id="device-chart" width="200" height="200"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Session Analysis -->
                    <div class="session-analysis">
                        <h3>Session Patterns</h3>
                        <div class="session-metrics">
                            <div class="session-metric">
                                <span class="metric-name">Avg Session Time</span>
                                <span class="metric-value">24m 32s</span>
                            </div>
                            <div class="session-metric">
                                <span class="metric-name">Bounce Rate</span>
                                <span class="metric-value">15.2%</span>
                            </div>
                            <div class="session-metric">
                                <span class="metric-name">Return Visitors</span>
                                <span class="metric-value">68.4%</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Tab -->
            <div class="tab-content" id="content-tab">
                <div class="content-analytics">
                    <!-- Content Performance Matrix -->
                    <div class="content-matrix">
                        <h3>Content Performance Matrix</h3>
                        <div id="content-bubble-chart" class="bubble-chart"></div>
                    </div>

                    <!-- Watch Time Analysis -->
                    <div class="watch-time-analysis">
                        <h3>Watch Time Distribution</h3>
                        <canvas id="watch-time-chart" width="600" height="300"></canvas>
                    </div>
                </div>
            </div>

            <!-- AI Insights Tab -->
            <div class="tab-content" id="insights-tab">
                <div class="insights-grid">
                    <!-- Predictive Analytics -->
                    <div class="insight-card">
                        <h3>🔮 Predictive Insights</h3>
                        <div class="predictions">
                            <div class="prediction">
                                <span class="prediction-label">Peak Hours Today</span>
                                <span class="prediction-value">7-9 PM</span>
                                <span class="confidence">92% confidence</span>
                            </div>
                            <div class="prediction">
                                <span class="prediction-label">Expected Viewers</span>
                                <span class="prediction-value">340-380</span>
                                <span class="confidence">87% confidence</span>
                            </div>
                        </div>
                    </div>

                    <!-- Anomaly Detection -->
                    <div class="insight-card">
                        <h3>🚨 Anomaly Detection</h3>
                        <div class="anomalies" id="anomaly-list">
                            <!-- Dynamic anomaly alerts -->
                        </div>
                    </div>

                    <!-- Auto-Generated Insights -->
                    <div class="insight-card">
                        <h3>💡 Smart Insights</h3>
                        <div class="auto-insights" id="auto-insights">
                            <!-- AI-generated insights -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Advanced Analytics JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://d3js.org/d3.v7.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/heatmap.js@2.0.5/build/heatmap.min.js"></script>

<script>
class RealTimeAnalytics {
    constructor() {
        this.eventSource = null;
        this.charts = {};
        this.darkMode = localStorage.getItem('ltv-dark-mode') === 'true';
        this.autoRefresh = true;
        this.currentTab = 'overview';
        this.refreshInterval = 5000;
        
        this.init();
    }

    init() {
        this.setupEventSource();
        this.initializeCharts();
        this.bindEvents();
        this.applyTheme();
        this.startAutoRefresh();
    }

    setupEventSource() {
        const sseUrl = `${ajaxurl}?action=live_tv_analytics_stream&nonce=${liveTVAdmin.nonce}`;
        this.eventSource = new EventSource(sseUrl);
        
        this.eventSource.onmessage = (event) => {
            const data = JSON.parse(event.data);
            this.updateRealTimeData(data);
        };

        this.eventSource.onerror = () => {
            console.warn('SSE connection lost, attempting to reconnect...');
            setTimeout(() => this.setupEventSource(), 5000);
        };
    }

    initializeCharts() {
        // Initialize live viewers chart
        const ctx = document.getElementById('viewers-chart').getContext('2d');
        this.charts.viewers = new Chart(ctx, {
            type: 'line',
            data: {
                labels: Array(20).fill(''),
                datasets: [{
                    data: Array(20).fill(0),
                    borderColor: '#00d4aa',
                    backgroundColor: 'rgba(0, 212, 170, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { x: { display: false }, y: { display: false } },
                plugins: { legend: { display: false } },
                animation: { duration: 0 }
            }
        });

        // Initialize main analytics chart
        this.initMainChart();
        this.initPerformanceCharts();
        this.initAudienceCharts();
    }

    initMainChart() {
        const ctx = document.getElementById('main-analytics-chart').getContext('2d');
        this.charts.main = new Chart(ctx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Viewers',
                    data: [],
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: '#667eea',
                        borderWidth: 1
                    }
                },
                scales: {
                    x: { 
                        grid: { color: 'rgba(255, 255, 255, 0.1)' },
                        ticks: { color: '#8892b0' }
                    },
                    y: { 
                        grid: { color: 'rgba(255, 255, 255, 0.1)' },
                        ticks: { color: '#8892b0' }
                    }
                }
            }
        });
    }

    initPerformanceCharts() {
        // Quality metrics chart
        const qualityCtx = document.getElementById('quality-chart')?.getContext('2d');
        if (qualityCtx) {
            this.charts.quality = new Chart(qualityCtx, {
                type: 'radar',
                data: {
                    labels: ['Bitrate', 'Frame Rate', 'Buffer Health', 'Latency', 'Error Rate'],
                    datasets: [{
                        data: [85, 92, 95, 88, 96],
                        borderColor: '#00d4aa',
                        backgroundColor: 'rgba(0, 212, 170, 0.2)',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        r: {
                            beginAtZero: true,
                            max: 100,
                            ticks: { display: false },
                            grid: { color: 'rgba(255, 255, 255, 0.1)' }
                        }
                    }
                }
            });
        }
    }

    initAudienceCharts() {
        // Age demographics chart
        const ageCtx = document.getElementById('age-chart')?.getContext('2d');
        if (ageCtx) {
            this.charts.age = new Chart(ageCtx, {
                type: 'doughnut',
                data: {
                    labels: ['18-24', '25-34', '35-44', '45-54', '55+'],
                    datasets: [{
                        data: [25, 35, 20, 15, 5],
                        backgroundColor: ['#667eea', '#764ba2', '#f093fb', '#f5576c', '#4facfe']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { color: '#8892b0' } }
                    }
                }
            });
        }

        // Device chart
        const deviceCtx = document.getElementById('device-chart')?.getContext('2d');
        if (deviceCtx) {
            this.charts.device = new Chart(deviceCtx, {
                type: 'pie',
                data: {
                    labels: ['Desktop', 'Mobile', 'Tablet'],
                    datasets: [{
                        data: [45, 35, 20],
                        backgroundColor: ['#667eea', '#00d4aa', '#ffecd2']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { color: '#8892b0' } }
                    }
                }
            });
        }
    }

    updateRealTimeData(data) {
        // Update live metrics
        this.updateElement('live-viewers .number', data.liveViewers || 0);
        this.updateElement('stream-health .number', data.streamHealth || 98.5);
        this.updateElement('bandwidth-usage .number', data.bandwidth || 1.2);
        
        // Update viewers chart
        if (this.charts.viewers && data.viewersHistory) {
            this.charts.viewers.data.datasets[0].data = data.viewersHistory;
            this.charts.viewers.update('none');
        }

        // Update activity feed
        if (data.recentActivity) {
            this.updateActivityFeed(data.recentActivity);
        }

        // Update geographic data
        if (data.geoDistribution) {
            this.updateGeoDistribution(data.geoDistribution);
        }

        // Update main chart based on current view
        if (data.chartData && this.charts.main) {
            const currentType = document.querySelector('.chart-type-btn.active').dataset.type;
            this.updateMainChart(data.chartData[currentType]);
        }
    }

    updateActivityFeed(activities) {
        const feed = document.getElementById('activity-feed');
        activities.forEach(activity => {
            const item = document.createElement('div');
            item.className = 'activity-item';
            item.innerHTML = `
                <div class="activity-time">${activity.time}</div>
                <div class="activity-content">
                    <span class="activity-icon">${activity.icon}</span>
                    <span class="activity-text">${activity.message}</span>
                </div>
            `;
            feed.insertBefore(item, feed.firstChild);
            
            // Keep only last 50 items
            if (feed.children.length > 50) {
                feed.removeChild(feed.lastChild);
            }
        });
    }

    updateGeoDistribution(geoData) {
        const container = document.getElementById('geo-distribution');
        container.innerHTML = geoData.map(region => `
            <div class="geo-item">
                <span class="country">${region.flag} ${region.country}</span>
                <span class="viewers">${region.viewers} viewers</span>
            </div>
        `).join('');
    }

    updateMainChart(chartData) {
        if (!chartData || !this.charts.main) return;
        
        this.charts.main.data.labels = chartData.labels;
        this.charts.main.data.datasets[0].data = chartData.data;
        this.charts.main.update('none');
    }

    bindEvents() {
        // Tab switching
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => this.switchTab(btn.dataset.tab));
        });

        // Time range buttons
        document.querySelectorAll('.time-btn').forEach(btn => {
            btn.addEventListener('click', () => this.changeTimeRange(btn.dataset.range));
        });

        // Theme toggle
        document.getElementById('toggle-theme').addEventListener('click', () => {
            this.toggleTheme();
        });

        // Auto-refresh toggle
        document.getElementById('auto-refresh').addEventListener('change', (e) => {
            this.autoRefresh = e.target.checked;
            if (this.autoRefresh) {
                this.startAutoRefresh();
            } else {
                this.stopAutoRefresh();
            }
        });

        // Chart type switching
        document.querySelectorAll('.chart-type-btn').forEach(btn => {
            btn.addEventListener('click', () => this.switchChartType(btn.dataset.type));
        });

        // Refresh button
        document.getElementById('refresh-all').addEventListener('click', () => {
            this.refreshAllData();
        });
    }

    switchTab(tabName) {
        // Update tab buttons
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.tab === tabName);
        });

        // Update tab content
        document.querySelectorAll('.tab-content').forEach(content => {
            content.classList.toggle('active', content.id === `${tabName}-tab`);
        });

        this.currentTab = tabName;
    }

    changeTimeRange(range) {
        document.querySelectorAll('.time-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.range === range);
        });

        // Fetch data for new time range
        this.fetchDataForRange(range);
    }

    toggleTheme() {
        this.darkMode = !this.darkMode;
        localStorage.setItem('ltv-dark-mode', this.darkMode);
        this.applyTheme();
    }

    applyTheme() {
        document.body.classList.toggle('dark-mode', this.darkMode);
        const icon = document.querySelector('#toggle-theme .icon');
        icon.textContent = this.darkMode ? '☀️' : '🌙';
    }

    switchChartType(type) {
        document.querySelectorAll('.chart-type-btn').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.type === type);
        });

        // Update main chart with new data type
        this.fetchChartData(type);
    }

    startAutoRefresh() {
        if (this.refreshTimer) clearInterval(this.refreshTimer);
        
        this.refreshTimer = setInterval(() => {
            if (this.autoRefresh) {
                this.refreshAllData();
            }
        }, this.refreshInterval);
    }

    stopAutoRefresh() {
        if (this.refreshTimer) {
            clearInterval(this.refreshTimer);
            this.refreshTimer = null;
        }
    }

    refreshAllData() {
        // Trigger manual refresh
        fetch(`${ajaxurl}?action=live_tv_refresh_analytics&nonce=${liveTVAdmin.nonce}`)
            .then(response => response.json())
            .then(data => {
                this.updateRealTimeData(data);
                this.showNotification('Analytics refreshed', 'success');
            })
            .catch(error => {
                console.error('Refresh failed:', error);
                this.showNotification('Refresh failed', 'error');
            });
    }

    fetchDataForRange(range) {
        fetch(`${ajaxurl}?action=live_tv_analytics_range&range=${range}&nonce=${liveTVAdmin.nonce}`)
            .then(response => response.json())
            .then(data => {
                this.updateRealTimeData(data);
            });
    }

    fetchChartData(type) {
        fetch(`${ajaxurl}?action=live_tv_chart_data&type=${type}&nonce=${liveTVAdmin.nonce}`)
            .then(response => response.json())
            .then(data => {
                this.updateMainChart(data);
            });
    }

    updateElement(selector, value) {
        const element = document.querySelector(selector);
        if (element) {
            element.textContent = typeof value === 'number' ? 
                (value % 1 === 0 ? value.toLocaleString() : value.toFixed(1)) : value;
        }
    }

    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `
            <span class="notification-icon">${type === 'success' ? '✅' : type === 'error' ? '❌' : 'ℹ️'}</span>
            <span class="notification-message">${message}</span>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.classList.add('show');
        }, 100);

        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
}

// Global functions
window.exportData = function(format) {
    const url = `${ajaxurl}?action=live_tv_export&format=${format}&nonce=${liveTVAdmin.nonce}`;
    window.open(url, '_blank');
};

window.openGeoMap = function() {
    // Open geographic visualization modal
    console.log('Opening geo map...');
};

window.expandChart = function(chartType) {
    // Expand chart to fullscreen
    console.log('Expanding chart:', chartType);
};

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.analyticsApp = new RealTimeAnalytics();
});
</script>

<!-- Advanced CSS Styles -->
<style>
:root {
    --primary-color: #667eea;
    --secondary-color: #764ba2;
    --success-color: #00d4aa;
    --warning-color: #ffecd2;
    --error-color: #f5576c;
    --background: #0a0e27;
    --surface: #151932;
    --card: #1e2139;
    --text-primary: #ffffff;
    --text-secondary: #8892b0;
    --border: #2d3748;
    --shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    --gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

body.dark-mode {
    background: var(--background);
    color: var(--text-primary);
}

.ltv-analytics-container {
    max-width: 100%;
    margin: 0;
    padding: 20px;
    background: var(--background);
    min-height: 100vh;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

/* Header Styles */
.ltv-analytics-header {
    background: var(--surface);
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: var(--shadow);
    border: 1px solid var(--border);
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.page-title {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 32px;
    font-weight: 700;
    background: var(--gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin: 0;
}

.title-icon {
    font-size: 36px;
}

.live-indicator {
    display: flex;
    align-items: center;
    gap: 8px;
    background: var(--success-color);
    color: white;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.pulse-dot {
    width: 8px;
    height: 8px;
    background: white;
    border-radius: 50%;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.header-actions {
    display: flex;
    gap: 12px;
}

.action-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    background: var(--card);
    border: 1px solid var(--border);
    color: var(--text-primary);
    padding: 10px 16px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 14px;
    font-weight: 500;
}

.action-btn:hover {
    background: var(--primary-color);
    border-color: var(--primary-color);
}

.dropdown {
    position: relative;
}

.dropdown-menu {
    position: absolute;
    top: 100%;
    right: 0;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 8px;
    box-shadow: var(--shadow);
    min-width: 150px;
    display: none;
    z-index: 1000;
}

.dropdown:hover .dropdown-menu {
    display: block;
}

.dropdown-menu a {
    display: block;
    padding: 12px 16px;
    color: var(--text-primary);
    text-decoration: none;
    border-bottom: 1px solid var(--border);
}

.dropdown-menu a:hover {
    background: var(--primary-color);
}

.time-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
}

.time-range {
    display: flex;
    gap: 8px;
}

.time-btn {
    padding: 8px 16px;
    background: transparent;
    border: 1px solid var(--border);
    color: var(--text-secondary);
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
    font-weight: 500;
}

.time-btn.active,
.time-btn:hover {
    background: var(--primary-color);
    border-color: var(--primary-color);
    color: white;
}

.auto-refresh {
    display: flex;
    align-items: center;
    gap: 12px;
    color: var(--text-secondary);
}

/* Toggle Switch */
.toggle-switch {
    position: relative;
    width: 50px;
    height: 24px;
}

.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}

.slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: var(--border);
    border-radius: 24px;
    transition: 0.3s;
}

.slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background: white;
    border-radius: 50%;
    transition: 0.3s;
}

input:checked + .slider {
    background: var(--success-color);
}

input:checked + .slider:before {
    transform: translateX(26px);
}

/* Metrics Grid */
.metrics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 24px;
    margin-bottom: 32px;
}

.metric-card {
    background: var(--surface);
    border-radius: 16px;
    padding: 24px;
    border: 1px solid var(--border);
    box-shadow: var(--shadow);
    transition: transform 0.2s;
}

.metric-card:hover {
    transform: translateY(-2px);
}

.metric-card.highlight {
    background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
    color: white;
}

.metric-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.metric-header h3 {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    color: inherit;
    opacity: 0.8;
}

.metric-trend {
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}

.metric-trend.up {
    background: rgba(0, 212, 170, 0.2);
    color: var(--success-color);
}

.metric-trend.down {
    background: rgba(245, 87, 108, 0.2);
    color: var(--error-color);
}

.metric-value {
    display: flex;
    align-items: baseline;
    gap: 8px;
    margin-bottom: 16px;
}

.metric-value .number {
    font-size: 36px;
    font-weight: 700;
    line-height: 1;
}

.metric-value .unit {
    font-size: 16px;
    opacity: 0.7;
}

.status-indicator {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    animation: pulse 2s infinite;
}

.status-indicator.healthy {
    background: var(--success-color);
}

.health-details,
.bandwidth-breakdown,
.geo-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.health-item,
.geo-item {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.quality-bar {
    display: flex;
    height: 4px;
    border-radius: 2px;
    overflow: hidden;
    margin-bottom: 8px;
}

.quality-segment.hd {
    background: var(--primary-color);
}

.quality-segment.sd {
    background: var(--success-color);
}

.quality-segment.mobile {
    background: var(--warning-color);
}

.quality-legend {
    display: flex;
    gap: 16px;
    font-size: 12px;
}

.quality-legend .dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
    margin-right: 4px;
}

.quality-legend .dot.hd {
    background: var(--primary-color);
}

.quality-legend .dot.sd {
    background: var(--success-color);
}

.quality-legend .dot.mobile {
    background: var(--warning-color);
}

.view-map-btn {
    background: none;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    font-size: 12px;
    padding: 4px 8px;
    border-radius: 4px;
    transition: all 0.2s;
}

.view-map-btn:hover {
    background: var(--border);
    color: var(--text-primary);
}

/* Analytics Tabs */
.analytics-tabs {
    background: var(--surface);
    border-radius: 16px;
    border: 1px solid var(--border);
    box-shadow: var(--shadow);
    overflow: hidden;
}

.tab-navigation {
    display: flex;
    background: var(--card);
    border-bottom: 1px solid var(--border);
    padding: 4px;
    gap: 4px;
}

.tab-btn {
    flex: 1;
    padding: 12px 16px;
    background: none;
    border: none;
    color: var(--text-secondary);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    font-weight: 500;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.tab-btn.active,
.tab-btn:hover {
    background: var(--primary-color);
    color: white;
}

.tab-content {
    display: none;
    padding: 32px;
}

.tab-content.active {
    display: block;
}

/* Charts Grid */
.charts-grid {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 24px;
    margin-bottom: 32px;
}

.chart-container {
    background: var(--card);
    border-radius: 12px;
    border: 1px solid var(--border);
    overflow: hidden;
}

.chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    background: rgba(102, 126, 234, 0.05);
    border-bottom: 1px solid var(--border);
}

.chart-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
}

.chart-controls {
    display: flex;
    gap: 8px;
}

.chart-type-btn {
    padding: 6px 12px;
    background: none;
    border: 1px solid var(--border);
    color: var(--text-secondary);
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 12px;
}

.chart-type-btn.active,
.chart-type-btn:hover {
    background: var(--primary-color);
    border-color: var(--primary-color);
    color: white;
}

.chart-wrapper {
    position: relative;
    padding: 20px;
    height: 400px;
}

.fullscreen-btn {
    background: none;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    padding: 4px;
    border-radius: 4px;
    transition: all 0.2s;
}

.fullscreen-btn:hover {
    background: var(--border);
    color: var(--text-primary);
}

/* Activity Feed */
.activity-feed {
    background: var(--card);
    border-radius: 12px;
    border: 1px solid var(--border);
    overflow: hidden;
}

.feed-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    background: rgba(102, 126, 234, 0.05);
    border-bottom: 1px solid var(--border);
}

.feed-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
}

.activity-controls {
    display: flex;
    gap: 8px;
}

.activity-controls button {
    background: none;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    padding: 6px;
    border-radius: 4px;
    transition: all 0.2s;
}

.activity-controls button:hover {
    background: var(--border);
}

.feed-content {
    max-height: 400px;
    overflow-y: auto;
    padding: 16px;
}

.activity-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 8px;
    background: rgba(255, 255, 255, 0.02);
    border: 1px solid transparent;
    transition: all 0.2s;
}

.activity-item:hover {
    background: rgba(102, 126, 234, 0.05);
    border-color: var(--primary-color);
}

.activity-time {
    font-size: 11px;
    color: var(--text-secondary);
    min-width: 60px;
}

.activity-content {
    display: flex;
    align-items: center;
    gap: 8px;
    flex: 1;
}

.activity-icon {
    font-size: 16px;
}

.activity-text {
    font-size: 13px;
    color: var(--text-primary);
}

/* Performance Tab */
.performance-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 24px;
}

.perf-card {
    background: var(--card);
    border-radius: 12px;
    padding: 24px;
    border: 1px solid var(--border);
}

.perf-card h3 {
    margin: 0 0 20px 0;
    font-size: 18px;
    font-weight: 600;
}

.quality-metrics {
    display: flex;
    flex-direction: column;
    gap: 16px;
    margin-bottom: 20px;
}

.quality-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    background: rgba(255, 255, 255, 0.02);
    border-radius: 8px;
    border: 1px solid var(--border);
}

.quality-item .label {
    font-weight: 500;
    color: var(--text-secondary);
}

.quality-item .value {
    font-weight: 600;
    font-size: 16px;
}

.trend {
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
}

.trend.positive {
    background: rgba(0, 212, 170, 0.2);
    color: var(--success-color);
}

.trend.negative {
    background: rgba(245, 87, 108, 0.2);
    color: var(--error-color);
}

.trend.stable {
    background: rgba(136, 146, 176, 0.2);
    color: var(--text-secondary);
}

.cdn-visualization {
    height: 200px;
    background: rgba(102, 126, 234, 0.05);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-secondary);
    border: 1px dashed var(--border);
}

.error-stats {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.error-type {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    background: rgba(245, 87, 108, 0.05);
    border-radius: 8px;
    border-left: 3px solid var(--error-color);
}

.error-label {
    font-weight: 500;
}

.error-count {
    font-weight: 600;
    color: var(--error-color);
}

.error-rate {
    font-size: 12px;
    color: var(--text-secondary);
}

/* Audience Tab */
.audience-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    grid-template-rows: auto auto;
    gap: 24px;
}

.heatmap-container {
    grid-column: 1 / -1;
    background: var(--card);
    border-radius: 12px;
    padding: 24px;
    border: 1px solid var(--border);
}

.heatmap {
    height: 300px;
    background: rgba(102, 126, 234, 0.05);
    border-radius: 8px;
    margin-top: 16px;
}

.demographics,
.session-analysis {
    background: var(--card);
    border-radius: 12px;
    padding: 24px;
    border: 1px solid var(--border);
}

.demographics h3,
.session-analysis h3 {
    margin: 0 0 20px 0;
    font-size: 18px;
    font-weight: 600;
}

.demo-charts {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.demo-chart h4 {
    margin: 0 0 12px 0;
    font-size: 14px;
    font-weight: 500;
    color: var(--text-secondary);
}

.session-metrics {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.session-metric {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    background: rgba(255, 255, 255, 0.02);
    border-radius: 8px;
    border: 1px solid var(--border);
}

.metric-name {
    font-weight: 500;
    color: var(--text-secondary);
}

.metric-value {
    font-weight: 600;
    font-size: 16px;
    color: var(--success-color);
}

/* Content Tab */
.content-analytics {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
}

.content-matrix,
.watch-time-analysis {
    background: var(--card);
    border-radius: 12px;
    padding: 24px;
    border: 1px solid var(--border);
}

.content-matrix h3,
.watch-time-analysis h3 {
    margin: 0 0 20px 0;
    font-size: 18px;
    font-weight: 600;
}

.bubble-chart {
    height: 300px;
    background: rgba(102, 126, 234, 0.05);
    border-radius: 8px;
}

/* Insights Tab */
.insights-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 24px;
}

.insight-card {
    background: var(--card);
    border-radius: 12px;
    padding: 24px;
    border: 1px solid var(--border);
}

.insight-card h3 {
    margin: 0 0 20px 0;
    font-size: 18px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 8px;
}

.predictions,
.anomalies,
.auto-insights {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.prediction {
    padding: 16px;
    background: rgba(102, 126, 234, 0.05);
    border-radius: 8px;
    border-left: 3px solid var(--primary-color);
}

.prediction-label {
    display: block;
    font-weight: 500;
    margin-bottom: 4px;
}

.prediction-value {
    display: block;
    font-size: 18px;
    font-weight: 600;
    color: var(--primary-color);
    margin-bottom: 4px;
}

.confidence {
    font-size: 12px;
    color: var(--text-secondary);
}

/* Notifications */
.notification {
    position: fixed;
    top: 80px;
    right: 20px;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 16px;
    box-shadow: var(--shadow);
    display: flex;
    align-items: center;
    gap: 12px;
    transform: translateX(400px);
    opacity: 0;
    transition: all 0.3s ease;
    z-index: 10000;
}

.notification.show {
    transform: translateX(0);
    opacity: 1;
}

.notification.success {
    border-left: 3px solid var(--success-color);
}

.notification.error {
    border-left: 3px solid var(--error-color);
}

.notification.info {
    border-left: 3px solid var(--primary-color);
}

.notification-icon {
    font-size: 18px;
}

.notification-message {
    font-weight: 500;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .charts-grid {
        grid-template-columns: 1fr;
    }
    
    .audience-grid,
    .content-analytics {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .ltv-analytics-container {
        padding: 12px;
    }
    
    .header-content {
        flex-direction: column;
        gap: 16px;
        align-items: flex-start;
    }
    
    .time-controls {
        flex-direction: column;
        gap: 16px;
    }
    
    .metrics-grid {
        grid-template-columns: 1fr;
    }
    
    .tab-navigation {
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .tab-btn {
        flex: 1 1 auto;
        min-width: 120px;
    }
    
    .insights-grid,
    .performance-grid {
        grid-template-columns: 1fr;
    }
}
</style>