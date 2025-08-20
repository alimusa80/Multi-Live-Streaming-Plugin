<?php
if (!defined('ABSPATH')) {
    exit;
}

class LiveTVPlaylistManager {
    
    private $playlists_table;
    private $playlist_items_table;
    
    public function __construct() {
        global $wpdb;
        $this->playlists_table = $wpdb->prefix . 'live_tv_playlists';
        $this->playlist_items_table = $wpdb->prefix . 'live_tv_playlist_items';
        
        // AJAX handlers
        add_action('wp_ajax_create_playlist', array($this, 'create_playlist'));
        add_action('wp_ajax_delete_playlist', array($this, 'delete_playlist'));
        add_action('wp_ajax_add_to_playlist', array($this, 'add_to_playlist'));
        add_action('wp_ajax_remove_from_playlist', array($this, 'remove_from_playlist'));
        add_action('wp_ajax_get_user_playlists', array($this, 'get_user_playlists'));
        add_action('wp_ajax_get_playlist_channels', array($this, 'get_playlist_channels'));
        add_action('wp_ajax_update_playlist_order', array($this, 'update_playlist_order'));
        add_action('wp_ajax_import_m3u_playlist', array($this, 'import_m3u_playlist'));
        add_action('wp_ajax_export_playlist', array($this, 'export_playlist'));
        
        // Guest user support
        add_action('wp_ajax_nopriv_get_user_playlists', array($this, 'get_guest_playlists'));
        add_action('wp_ajax_nopriv_create_playlist', array($this, 'create_guest_playlist'));
    }
    
    /**
     * Create playlist tables
     */
    public function create_playlist_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Playlists table
        $playlists_sql = "CREATE TABLE $this->playlists_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) DEFAULT NULL,
            session_id varchar(64) DEFAULT NULL,
            name varchar(255) NOT NULL,
            description text DEFAULT NULL,
            is_public tinyint(1) DEFAULT 0,
            thumbnail_url varchar(500) DEFAULT NULL,
            category varchar(100) DEFAULT 'Custom',
            sort_order int(11) DEFAULT 0,
            created_date datetime DEFAULT CURRENT_TIMESTAMP,
            updated_date datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY session_id (session_id),
            KEY category (category),
            KEY is_public (is_public)
        ) $charset_collate;";
        
        // Playlist items table
        $playlist_items_sql = "CREATE TABLE $this->playlist_items_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            playlist_id bigint(20) NOT NULL,
            channel_id int(11) NOT NULL,
            sort_order int(11) DEFAULT 0,
            added_date datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY playlist_channel (playlist_id, channel_id),
            KEY playlist_id (playlist_id),
            KEY channel_id (channel_id),
            KEY sort_order (sort_order)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($playlists_sql);
        dbDelta($playlist_items_sql);
        
        // Create default playlists
        $this->create_default_playlists();
    }
    
    /**
     * Create default playlists
     */
    private function create_default_playlists() {
        global $wpdb;
        
        // Check if default playlists already exist
        $existing = $wpdb->get_var("SELECT COUNT(*) FROM $this->playlists_table WHERE user_id IS NULL AND session_id IS NULL");
        if ($existing > 0) {
            return;
        }
        
        $default_playlists = array(
            array(
                'name' => 'Featured Channels',
                'description' => 'Hand-picked selection of the best channels',
                'is_public' => 1,
                'category' => 'Featured'
            ),
            array(
                'name' => 'News & Current Affairs',
                'description' => 'Stay informed with the latest news',
                'is_public' => 1,
                'category' => 'News'
            ),
            array(
                'name' => 'Entertainment Hub',
                'description' => 'Movies, shows, and entertainment channels',
                'is_public' => 1,
                'category' => 'Entertainment'
            ),
            array(
                'name' => 'Sports Central',
                'description' => 'All your favorite sports channels in one place',
                'is_public' => 1,
                'category' => 'Sports'
            )
        );
        
        foreach ($default_playlists as $playlist) {
            $wpdb->insert($this->playlists_table, $playlist);
            
            // Add channels to default playlists based on category
            if ($playlist['category'] !== 'Featured') {
                $this->populate_default_playlist($wpdb->insert_id, $playlist['category']);
            }
        }
    }
    
    /**
     * Populate default playlist with channels
     */
    private function populate_default_playlist($playlist_id, $category) {
        global $wpdb;
        
        $channels = $wpdb->get_results($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}live_tv_channels WHERE category = %s AND is_active = 1 ORDER BY sort_order ASC",
            $category
        ));
        
        $sort_order = 1;
        foreach ($channels as $channel) {
            $wpdb->insert($this->playlist_items_table, array(
                'playlist_id' => $playlist_id,
                'channel_id' => $channel->id,
                'sort_order' => $sort_order++
            ));
        }
    }
    
    /**
     * Create playlist
     */
    public function create_playlist() {
        if (is_user_logged_in() && !wp_verify_nonce($_POST['nonce'] ?? '', 'live_tv_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        $name = sanitize_text_field($_POST['name'] ?? '');
        $description = sanitize_textarea_field($_POST['description'] ?? '');
        $is_public = intval($_POST['is_public'] ?? 0);
        
        if (empty($name)) {
            wp_send_json_error('Playlist name is required');
        }
        
        $playlist_id = $this->create_user_playlist($name, $description, $is_public);
        
        if ($playlist_id) {
            wp_send_json_success(array('playlist_id' => $playlist_id));
        } else {
            wp_send_json_error('Failed to create playlist');
        }
    }
    
    /**
     * Create guest playlist
     */
    public function create_guest_playlist() {
        $name = sanitize_text_field($_POST['name'] ?? '');
        $description = sanitize_textarea_field($_POST['description'] ?? '');
        
        if (empty($name)) {
            wp_send_json_error('Playlist name is required');
        }
        
        $session_id = $this->get_session_id();
        $playlist_id = $this->create_session_playlist($session_id, $name, $description);
        
        if ($playlist_id) {
            wp_send_json_success(array('playlist_id' => $playlist_id));
        } else {
            wp_send_json_error('Failed to create playlist');
        }
    }
    
    /**
     * Create user playlist
     */
    private function create_user_playlist($name, $description = '', $is_public = 0) {
        global $wpdb;
        
        $data = array(
            'name' => $name,
            'description' => $description,
            'is_public' => $is_public,
            'category' => 'Custom'
        );
        
        if (is_user_logged_in()) {
            $data['user_id'] = get_current_user_id();
        } else {
            $data['session_id'] = $this->get_session_id();
        }
        
        $result = $wpdb->insert($this->playlists_table, $data);
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Create session playlist
     */
    private function create_session_playlist($session_id, $name, $description = '') {
        global $wpdb;
        
        $result = $wpdb->insert($this->playlists_table, array(
            'session_id' => $session_id,
            'name' => $name,
            'description' => $description,
            'is_public' => 0,
            'category' => 'Custom'
        ));
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Delete playlist
     */
    public function delete_playlist() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'live_tv_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        $playlist_id = intval($_POST['playlist_id'] ?? 0);
        
        if (!$playlist_id) {
            wp_send_json_error('Invalid playlist ID');
        }
        
        // Verify ownership
        if (!$this->can_modify_playlist($playlist_id)) {
            wp_send_json_error('Permission denied');
        }
        
        global $wpdb;
        
        // Delete playlist items first
        $wpdb->delete($this->playlist_items_table, array('playlist_id' => $playlist_id));
        
        // Delete playlist
        $result = $wpdb->delete($this->playlists_table, array('id' => $playlist_id));
        
        if ($result) {
            wp_send_json_success('Playlist deleted');
        } else {
            wp_send_json_error('Failed to delete playlist');
        }
    }
    
    /**
     * Add channel to playlist
     */
    public function add_to_playlist() {
        if (is_user_logged_in() && !wp_verify_nonce($_POST['nonce'] ?? '', 'live_tv_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        $playlist_id = intval($_POST['playlist_id'] ?? 0);
        $channel_id = intval($_POST['channel_id'] ?? 0);
        
        if (!$playlist_id || !$channel_id) {
            wp_send_json_error('Invalid parameters');
        }
        
        // Verify ownership
        if (!$this->can_modify_playlist($playlist_id)) {
            wp_send_json_error('Permission denied');
        }
        
        global $wpdb;
        
        // Get next sort order
        $max_order = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(sort_order) FROM $this->playlist_items_table WHERE playlist_id = %d",
            $playlist_id
        ));
        
        $sort_order = ($max_order ?? 0) + 1;
        
        $result = $wpdb->insert($this->playlist_items_table, array(
            'playlist_id' => $playlist_id,
            'channel_id' => $channel_id,
            'sort_order' => $sort_order
        ));
        
        if ($result) {
            wp_send_json_success('Channel added to playlist');
        } else {
            wp_send_json_error('Channel already in playlist or failed to add');
        }
    }
    
    /**
     * Remove channel from playlist
     */
    public function remove_from_playlist() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'live_tv_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        $playlist_id = intval($_POST['playlist_id'] ?? 0);
        $channel_id = intval($_POST['channel_id'] ?? 0);
        
        if (!$playlist_id || !$channel_id) {
            wp_send_json_error('Invalid parameters');
        }
        
        // Verify ownership
        if (!$this->can_modify_playlist($playlist_id)) {
            wp_send_json_error('Permission denied');
        }
        
        global $wpdb;
        
        $result = $wpdb->delete($this->playlist_items_table, array(
            'playlist_id' => $playlist_id,
            'channel_id' => $channel_id
        ));
        
        if ($result) {
            wp_send_json_success('Channel removed from playlist');
        } else {
            wp_send_json_error('Failed to remove channel');
        }
    }
    
    /**
     * Get user playlists
     */
    public function get_user_playlists() {
        global $wpdb;
        
        $playlists = array();
        
        // Get public playlists
        $public_playlists = $wpdb->get_results(
            "SELECT p.*, COUNT(pi.id) as channel_count
             FROM $this->playlists_table p
             LEFT JOIN $this->playlist_items_table pi ON p.id = pi.playlist_id
             WHERE p.is_public = 1
             GROUP BY p.id
             ORDER BY p.sort_order ASC, p.name ASC"
        );
        
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $user_playlists = $wpdb->get_results($wpdb->prepare(
                "SELECT p.*, COUNT(pi.id) as channel_count
                 FROM $this->playlists_table p
                 LEFT JOIN $this->playlist_items_table pi ON p.id = pi.playlist_id
                 WHERE p.user_id = %d
                 GROUP BY p.id
                 ORDER BY p.sort_order ASC, p.name ASC",
                $user_id
            ));
            
            $playlists = array_merge($public_playlists, $user_playlists);
        } else {
            $session_id = $this->get_session_id();
            $session_playlists = $wpdb->get_results($wpdb->prepare(
                "SELECT p.*, COUNT(pi.id) as channel_count
                 FROM $this->playlists_table p
                 LEFT JOIN $this->playlist_items_table pi ON p.id = pi.playlist_id
                 WHERE p.session_id = %s
                 GROUP BY p.id
                 ORDER BY p.sort_order ASC, p.name ASC",
                $session_id
            ));
            
            $playlists = array_merge($public_playlists, $session_playlists);
        }
        
        wp_send_json_success($playlists);
    }
    
    /**
     * Get guest playlists
     */
    public function get_guest_playlists() {
        $this->get_user_playlists();
    }
    
    /**
     * Get playlist channels
     */
    public function get_playlist_channels() {
        $playlist_id = intval($_POST['playlist_id'] ?? 0);
        
        if (!$playlist_id) {
            wp_send_json_error('Invalid playlist ID');
        }
        
        global $wpdb;
        
        $channels = $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, pi.sort_order as playlist_order
             FROM $this->playlist_items_table pi
             JOIN {$wpdb->prefix}live_tv_channels c ON pi.channel_id = c.id
             WHERE pi.playlist_id = %d AND c.is_active = 1
             ORDER BY pi.sort_order ASC",
            $playlist_id
        ));
        
        wp_send_json_success($channels);
    }
    
    /**
     * Update playlist order
     */
    public function update_playlist_order() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'live_tv_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        $playlist_id = intval($_POST['playlist_id'] ?? 0);
        $channel_orders = json_decode(stripslashes($_POST['orders'] ?? '[]'), true);
        
        if (!$playlist_id || !is_array($channel_orders)) {
            wp_send_json_error('Invalid parameters');
        }
        
        // Verify ownership
        if (!$this->can_modify_playlist($playlist_id)) {
            wp_send_json_error('Permission denied');
        }
        
        global $wpdb;
        
        foreach ($channel_orders as $channel_id => $sort_order) {
            $wpdb->update(
                $this->playlist_items_table,
                array('sort_order' => intval($sort_order)),
                array(
                    'playlist_id' => $playlist_id,
                    'channel_id' => intval($channel_id)
                )
            );
        }
        
        wp_send_json_success('Order updated');
    }
    
    /**
     * Import M3U playlist
     */
    public function import_m3u_playlist() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'live_tv_admin_nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        $m3u_url = esc_url_raw($_POST['m3u_url'] ?? '');
        $playlist_name = sanitize_text_field($_POST['playlist_name'] ?? '');
        
        if (!$m3u_url || !$playlist_name) {
            wp_send_json_error('M3U URL and playlist name are required');
        }
        
        $result = $this->parse_m3u_playlist($m3u_url, $playlist_name);
        
        if ($result) {
            wp_send_json_success(array('message' => "Imported {$result['channels']} channels into playlist '{$playlist_name}'"));
        } else {
            wp_send_json_error('Failed to import M3U playlist');
        }
    }
    
    /**
     * Parse M3U playlist
     */
    private function parse_m3u_playlist($url, $playlist_name) {
        $content = wp_remote_get($url);
        
        if (is_wp_error($content)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($content);
        $lines = explode("\n", $body);
        
        global $wpdb;
        
        // Create playlist
        $playlist_id = $wpdb->insert($this->playlists_table, array(
            'name' => $playlist_name,
            'description' => 'Imported from M3U playlist',
            'is_public' => 1,
            'category' => 'Imported'
        ));
        
        if (!$playlist_id) {
            return false;
        }
        
        $playlist_id = $wpdb->insert_id;
        $channels_added = 0;
        $sort_order = 1;
        
        $current_extinf = '';
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            if (strpos($line, '#EXTINF:') === 0) {
                $current_extinf = $line;
            } elseif (!empty($line) && strpos($line, '#') !== 0 && !empty($current_extinf)) {
                // Parse channel info
                $channel_info = $this->parse_extinf($current_extinf);
                $stream_url = $line;
                
                // Add channel to database
                $channel_id = $wpdb->insert(
                    $wpdb->prefix . 'live_tv_channels',
                    array(
                        'name' => $channel_info['name'],
                        'description' => 'Imported from M3U playlist',
                        'stream_url' => $stream_url,
                        'logo_url' => $channel_info['logo'] ?? '',
                        'category' => $channel_info['category'] ?? 'Imported',
                        'sort_order' => $sort_order,
                        'is_active' => 1
                    )
                );
                
                if ($channel_id) {
                    $channel_id = $wpdb->insert_id;
                    
                    // Add to playlist
                    $wpdb->insert($this->playlist_items_table, array(
                        'playlist_id' => $playlist_id,
                        'channel_id' => $channel_id,
                        'sort_order' => $sort_order++
                    ));
                    
                    $channels_added++;
                }
                
                $current_extinf = '';
            }
        }
        
        return array(
            'playlist_id' => $playlist_id,
            'channels' => $channels_added
        );
    }
    
    /**
     * Parse EXTINF line
     */
    private function parse_extinf($extinf) {
        $info = array();
        
        // Extract channel name (last part after comma)
        if (preg_match('/,(.+)$/', $extinf, $matches)) {
            $info['name'] = trim($matches[1]);
        }
        
        // Extract logo URL
        if (preg_match('/tvg-logo="([^"]*)"/', $extinf, $matches)) {
            $info['logo'] = $matches[1];
        }
        
        // Extract category
        if (preg_match('/group-title="([^"]*)"/', $extinf, $matches)) {
            $info['category'] = $matches[1];
        }
        
        return $info;
    }
    
    /**
     * Export playlist
     */
    public function export_playlist() {
        $playlist_id = intval($_POST['playlist_id'] ?? 0);
        $format = sanitize_text_field($_POST['format'] ?? 'm3u');
        
        if (!$playlist_id) {
            wp_send_json_error('Invalid playlist ID');
        }
        
        global $wpdb;
        
        $playlist = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $this->playlists_table WHERE id = %d",
            $playlist_id
        ));
        
        if (!$playlist) {
            wp_send_json_error('Playlist not found');
        }
        
        $channels = $wpdb->get_results($wpdb->prepare(
            "SELECT c.*, pi.sort_order as playlist_order
             FROM $this->playlist_items_table pi
             JOIN {$wpdb->prefix}live_tv_channels c ON pi.channel_id = c.id
             WHERE pi.playlist_id = %d AND c.is_active = 1
             ORDER BY pi.sort_order ASC",
            $playlist_id
        ));
        
        if ($format === 'm3u') {
            $this->export_m3u($playlist, $channels);
        } else {
            wp_send_json_error('Unsupported format');
        }
    }
    
    /**
     * Export as M3U
     */
    private function export_m3u($playlist, $channels) {
        $filename = sanitize_file_name($playlist->name) . '.m3u8';
        
        header('Content-Type: application/vnd.apple.mpegurl');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        echo "#EXTM3U\n";
        echo "#PLAYLIST:" . $playlist->name . "\n";
        
        foreach ($channels as $channel) {
            $extinf = "#EXTINF:-1";
            
            if (!empty($channel->logo_url)) {
                $extinf .= ' tvg-logo="' . $channel->logo_url . '"';
            }
            
            if (!empty($channel->category)) {
                $extinf .= ' group-title="' . $channel->category . '"';
            }
            
            $extinf .= "," . $channel->name . "\n";
            
            echo $extinf;
            echo $channel->stream_url . "\n";
        }
        
        exit;
    }
    
    /**
     * Check if user can modify playlist
     */
    private function can_modify_playlist($playlist_id) {
        global $wpdb;
        
        $playlist = $wpdb->get_row($wpdb->prepare(
            "SELECT user_id, session_id FROM $this->playlists_table WHERE id = %d",
            $playlist_id
        ));
        
        if (!$playlist) {
            return false;
        }
        
        // Admin can modify any playlist
        if (current_user_can('manage_options')) {
            return true;
        }
        
        // Check user ownership
        if ($playlist->user_id && is_user_logged_in()) {
            return $playlist->user_id == get_current_user_id();
        }
        
        // Check session ownership
        if ($playlist->session_id && !is_user_logged_in()) {
            return $playlist->session_id === $this->get_session_id();
        }
        
        return false;
    }
    
    /**
     * Get session ID
     */
    private function get_session_id() {
        if (!session_id()) {
            session_start();
        }
        
        if (!isset($_SESSION['live_tv_session'])) {
            $_SESSION['live_tv_session'] = wp_generate_password(32, false);
        }
        
        return $_SESSION['live_tv_session'];
    }
}

new LiveTVPlaylistManager();