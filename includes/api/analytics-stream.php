<?php
if (!defined('ABSPATH')) exit('Direct access denied.');

class LiveTVAnalyticsStream {
    private $analytics;
    
    public function __construct() {
        require_once LIVE_TV_PLUGIN_PATH . 'includes/pages/analytics.php';
        $this->analytics = new LiveTVAnalytics();
        
        add_action('wp_ajax_live_tv_analytics_stream', array($this, 'stream_analytics_data'));
        add_action('wp_ajax_live_tv_refresh_analytics', array($this, 'refresh_analytics'));
        add_action('wp_ajax_live_tv_analytics_range', array($this, 'get_range_data'));
        add_action('wp_ajax_live_tv_chart_data', array($this, 'get_chart_data'));
        add_action('wp_ajax_live_tv_export', array($this, 'export_data'));
    }

    public function stream_analytics_data() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        if (!wp_verify_nonce($_GET['nonce'] ?? '', 'live_tv_admin_nonce')) {
            wp_die('Invalid nonce');
        }

        // Set headers for Server-Sent Events
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Cache-Control');

        // Prevent timeout
        set_time_limit(0);
        ignore_user_abort(false);

        while (true) {
            $data = $this->generate_real_time_data();
            echo "data: " . json_encode($data) . "\n\n";
            ob_flush();
            flush();

            if (connection_aborted()) {
                break;
            }

            sleep(5); // Update every 5 seconds
        }
        exit;
    }

    public function refresh_analytics() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        if (!wp_verify_nonce($_GET['nonce'] ?? '', 'live_tv_admin_nonce')) {
            wp_die('Invalid nonce');
        }

        $data = $this->generate_real_time_data();
        wp_send_json_success($data);
    }

    public function get_range_data() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        if (!wp_verify_nonce($_GET['nonce'] ?? '', 'live_tv_admin_nonce')) {
            wp_die('Invalid nonce');
        }

        $range = sanitize_text_field($_GET['range'] ?? '24h');
        $data = $this->analytics->get_dashboard_stats($this->convert_range($range));
        
        wp_send_json_success($data);
    }

    public function get_chart_data() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        if (!wp_verify_nonce($_GET['nonce'] ?? '', 'live_tv_admin_nonce')) {
            wp_die('Invalid nonce');
        }

        $type = sanitize_text_field($_GET['type'] ?? 'viewers');
        $data = $this->generate_chart_data($type);
        
        wp_send_json_success($data);
    }

    public function export_data() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        if (!wp_verify_nonce($_GET['nonce'] ?? '', 'live_tv_admin_nonce')) {
            wp_die('Invalid nonce');
        }

        $format = sanitize_text_field($_GET['format'] ?? 'csv');
        $data = $this->analytics->get_dashboard_stats('30days');

        switch ($format) {
            case 'pdf':
                $this->export_pdf($data);
                break;
            case 'json':
                $this->export_json($data);
                break;
            case 'csv':
            default:
                $this->export_csv($data);
                break;
        }
    }

    private function generate_real_time_data() {
        // Simulate real-time data - in production, this would come from actual analytics
        return array(
            'liveViewers' => rand(200, 300),
            'streamHealth' => round(rand(950, 1000) / 10, 1),
            'bandwidth' => round(rand(80, 150) / 100, 1),
            'viewersHistory' => $this->generate_viewers_history(),
            'recentActivity' => $this->generate_recent_activity(),
            'geoDistribution' => $this->generate_geo_data(),
            'chartData' => array(
                'viewers' => $this->generate_chart_data('viewers'),
                'duration' => $this->generate_chart_data('duration'),
                'engagement' => $this->generate_chart_data('engagement')
            )
        );
    }

    private function generate_viewers_history() {
        $history = array();
        $base = 250;
        
        for ($i = 0; $i < 20; $i++) {
            $variance = rand(-30, 30);
            $history[] = max(0, $base + $variance);
            $base = $history[$i]; // Make it somewhat continuous
        }
        
        return $history;
    }

    private function generate_recent_activity() {
        $activities = array(
            array('icon' => '👤', 'message' => 'New viewer from United States', 'time' => date('H:i:s')),
            array('icon' => '📺', 'message' => 'Channel "Sports News" started streaming', 'time' => date('H:i:s', strtotime('-1 minute'))),
            array('icon' => '🔄', 'message' => 'Stream quality improved to HD', 'time' => date('H:i:s', strtotime('-2 minutes'))),
            array('icon' => '🌍', 'message' => 'Peak usage in Europe region', 'time' => date('H:i:s', strtotime('-3 minutes')))
        );
        
        return array_slice($activities, 0, rand(1, 3)); // Return 1-3 random activities
    }

    private function generate_geo_data() {
        $countries = array(
            array('flag' => '🇺🇸', 'country' => 'United States', 'viewers' => rand(80, 120)),
            array('flag' => '🇬🇧', 'country' => 'United Kingdom', 'viewers' => rand(30, 50)),
            array('flag' => '🇨🇦', 'country' => 'Canada', 'viewers' => rand(20, 35)),
            array('flag' => '🇩🇪', 'country' => 'Germany', 'viewers' => rand(15, 25)),
            array('flag' => '🇫🇷', 'country' => 'France', 'viewers' => rand(10, 20))
        );
        
        return array_slice($countries, 0, 3); // Return top 3
    }

    private function generate_chart_data($type) {
        $labels = array();
        $data = array();
        
        // Generate last 24 hours of data
        for ($i = 23; $i >= 0; $i--) {
            $labels[] = date('H:00', strtotime("-{$i} hours"));
            
            switch ($type) {
                case 'viewers':
                    $data[] = rand(50, 300);
                    break;
                case 'duration':
                    $data[] = rand(10, 45); // minutes
                    break;
                case 'engagement':
                    $data[] = rand(60, 95); // percentage
                    break;
            }
        }
        
        return array(
            'labels' => $labels,
            'data' => $data
        );
    }

    private function convert_range($range) {
        switch ($range) {
            case '1h': return '1hour';
            case '24h': return '24hours';
            case '7d': return '7days';
            case '30d': return '30days';
            case 'realtime': return '1hour';
            default: return '24hours';
        }
    }

    private function export_csv($data) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="analytics_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // CSV Headers
        fputcsv($output, array('Metric', 'Value', 'Period'));
        
        // Basic metrics
        fputcsv($output, array('Total Views', $data['total_views'] ?? 0, 'Current Period'));
        fputcsv($output, array('Unique Viewers', $data['unique_viewers'] ?? 0, 'Current Period'));
        fputcsv($output, array('Avg Session Duration', gmdate('H:i:s', $data['average_session_duration'] ?? 0), 'Current Period'));
        
        // Channel data
        if (!empty($data['top_channels'])) {
            fputcsv($output, array('', '', ''));
            fputcsv($output, array('Channel', 'Views', 'Avg Duration'));
            foreach ($data['top_channels'] as $channel) {
                fputcsv($output, array($channel->name, $channel->views, gmdate('H:i:s', $channel->avg_duration)));
            }
        }
        
        fclose($output);
        exit;
    }

    private function export_json($data) {
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="analytics_' . date('Y-m-d') . '.json"');
        
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }

    private function export_pdf($data) {
        // Basic PDF generation - in production, use a proper PDF library
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="analytics_' . date('Y-m-d') . '.txt"');
        
        echo "Live TV Streaming Analytics Report\n";
        echo "Generated: " . date('Y-m-d H:i:s') . "\n\n";
        echo "Total Views: " . ($data['total_views'] ?? 0) . "\n";
        echo "Unique Viewers: " . ($data['unique_viewers'] ?? 0) . "\n";
        echo "Average Session Duration: " . gmdate('H:i:s', $data['average_session_duration'] ?? 0) . "\n\n";
        
        if (!empty($data['top_channels'])) {
            echo "Top Channels:\n";
            foreach ($data['top_channels'] as $i => $channel) {
                echo ($i + 1) . ". " . $channel->name . " - " . $channel->views . " views\n";
            }
        }
        
        exit;
    }
}

// Initialize the analytics stream
new LiveTVAnalyticsStream();