<?php
if (!defined('ABSPATH')) exit('Direct access denied.');

class LiveTVM3UImporter {
    private $database;
    
    public function __construct() {
        global $wpdb;
        $this->database = $wpdb;
        
        add_action('wp_ajax_live_tv_import_m3u', array($this, 'handle_import'));
        add_action('wp_ajax_live_tv_parse_m3u_preview', array($this, 'parse_preview'));
    }

    public function handle_import() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'live_tv_admin_nonce')) {
            wp_die('Invalid nonce');
        }

        $import_data = json_decode(stripslashes($_POST['import_data'] ?? '{}'), true);
        $category_mapping = $_POST['category_mapping'] ?? array();
        $default_category = sanitize_text_field($_POST['default_category'] ?? 'Other');

        if (empty($import_data)) {
            wp_send_json_error('No import data provided');
        }

        $results = $this->import_channels($import_data, $category_mapping, $default_category);
        wp_send_json_success($results);
    }

    public function parse_preview() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'live_tv_admin_nonce')) {
            wp_die('Invalid nonce');
        }

        $source = sanitize_text_field($_POST['source'] ?? '');
        $content = $_POST['content'] ?? '';

        if ($source === 'url') {
            $url = sanitize_url($_POST['url'] ?? '');
            $content = $this->fetch_m3u_from_url($url);
            if (!$content) {
                wp_send_json_error('Failed to fetch M3U from URL');
            }
        }

        $parsed = $this->parse_m3u_content($content);
        wp_send_json_success($parsed);
    }

    private function fetch_m3u_from_url($url) {
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'headers' => array(
                'User-Agent' => 'LiveTV-Plugin/3.1.0'
            )
        ));

        if (is_wp_error($response)) {
            return false;
        }

        return wp_remote_retrieve_body($response);
    }

    private function parse_m3u_content($content) {
        $lines = explode("\n", $content);
        $channels = array();
        $current_channel = null;
        $categories = array();

        foreach ($lines as $line) {
            $line = trim($line);
            
            if (empty($line) || $line === '#EXTM3U') {
                continue;
            }

            if (strpos($line, '#EXTINF:') === 0) {
                $current_channel = $this->parse_extinf_line($line);
            } elseif (!empty($line) && !str_starts_with($line, '#') && $current_channel) {
                $current_channel['stream_url'] = $line;
                
                // Extract category and add to list
                $category = $current_channel['category'] ?? 'Other';
                if (!in_array($category, $categories)) {
                    $categories[] = $category;
                }
                
                $channels[] = $current_channel;
                $current_channel = null;
            }
        }

        return array(
            'channels' => $channels,
            'categories' => $categories,
            'total_count' => count($channels)
        );
    }

    private function parse_extinf_line($line) {
        // Parse EXTINF line format: #EXTINF:duration,name
        // Extended format with attributes: #EXTINF:-1 tvg-id="id" tvg-name="name" tvg-logo="logo" group-title="category",Display Name
        
        $channel = array(
            'name' => '',
            'description' => '',
            'logo_url' => '',
            'category' => 'Other',
            'sort_order' => 0,
            'is_active' => 1
        );

        // Extract attributes using regex
        preg_match_all('/([a-zA-Z0-9-]+)="([^"]*)"/', $line, $matches);
        $attributes = array();
        
        if (!empty($matches[1])) {
            for ($i = 0; $i < count($matches[1]); $i++) {
                $attributes[$matches[1][$i]] = $matches[2][$i];
            }
        }

        // Extract display name (after the last comma)
        if (preg_match('/,(.+)$/', $line, $name_match)) {
            $channel['name'] = trim($name_match[1]);
        }

        // Map M3U attributes to channel fields
        if (!empty($attributes['tvg-name'])) {
            $channel['name'] = $attributes['tvg-name'];
        }
        
        if (!empty($attributes['tvg-logo'])) {
            $channel['logo_url'] = $attributes['tvg-logo'];
        }
        
        if (!empty($attributes['group-title'])) {
            $channel['category'] = $attributes['group-title'];
        }

        if (!empty($attributes['tvg-id'])) {
            $channel['description'] = 'ID: ' . $attributes['tvg-id'];
        }

        // Clean up category name
        $channel['category'] = $this->normalize_category($channel['category']);

        return $channel;
    }

    private function normalize_category($category) {
        $category = trim($category);
        
        // Map common M3U categories to our standard categories
        $category_mapping = array(
            'news' => 'News',
            'sport' => 'Sports', 
            'sports' => 'Sports',
            'movie' => 'Movies',
            'movies' => 'Movies',
            'entertainment' => 'Entertainment',
            'music' => 'Music',
            'documentary' => 'Documentary',
            'kids' => 'Kids',
            'children' => 'Kids',
            'cartoon' => 'Kids'
        );

        $lower_category = strtolower($category);
        
        return $category_mapping[$lower_category] ?? ucwords(strtolower($category));
    }

    private function import_channels($channels_data, $category_mapping, $default_category) {
        $results = array(
            'imported' => 0,
            'skipped' => 0,
            'errors' => array(),
            'categories_created' => array()
        );

        $table_name = $this->database->prefix . 'live_tv_channels';

        foreach ($channels_data as $channel_data) {
            try {
                // Apply category mapping
                $original_category = $channel_data['category'] ?? 'Other';
                $mapped_category = $category_mapping[$original_category] ?? $default_category;
                $channel_data['category'] = $mapped_category;

                // Check if channel already exists
                $existing = $this->database->get_var(
                    $this->database->prepare(
                        "SELECT id FROM {$table_name} WHERE stream_url = %s",
                        $channel_data['stream_url']
                    )
                );

                if ($existing) {
                    $results['skipped']++;
                    continue;
                }

                // Insert channel
                $inserted = $this->database->insert(
                    $table_name,
                    array(
                        'name' => sanitize_text_field($channel_data['name']),
                        'description' => sanitize_textarea_field($channel_data['description']),
                        'stream_url' => sanitize_url($channel_data['stream_url']),
                        'logo_url' => sanitize_url($channel_data['logo_url']),
                        'category' => sanitize_text_field($channel_data['category']),
                        'sort_order' => intval($channel_data['sort_order']),
                        'is_active' => intval($channel_data['is_active']),
                        'created_at' => current_time('mysql')
                    ),
                    array('%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s')
                );

                if ($inserted) {
                    $results['imported']++;
                    
                    // Track categories created
                    if (!in_array($mapped_category, $results['categories_created'])) {
                        $results['categories_created'][] = $mapped_category;
                    }
                } else {
                    $results['errors'][] = 'Failed to import: ' . $channel_data['name'];
                }

            } catch (Exception $e) {
                $results['errors'][] = 'Error importing ' . ($channel_data['name'] ?? 'unknown') . ': ' . $e->getMessage();
            }
        }

        return $results;
    }

    public function get_available_categories() {
        $table_name = $this->database->prefix . 'live_tv_channels';
        
        $categories = $this->database->get_col(
            "SELECT DISTINCT category FROM {$table_name} WHERE category IS NOT NULL AND category != '' ORDER BY category"
        );

        // Add default categories if not present
        $default_categories = array('News', 'Sports', 'Entertainment', 'Movies', 'Music', 'Documentary', 'Kids', 'Other');
        
        foreach ($default_categories as $cat) {
            if (!in_array($cat, $categories)) {
                $categories[] = $cat;
            }
        }

        sort($categories);
        return $categories;
    }
}

new LiveTVM3UImporter();