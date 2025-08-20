<?php

if (!defined('ABSPATH')) {
    exit;
}

class LiveTVDatabase {
    
    public function __construct() {
        // Database operations are handled here
    }
    
    public function create_tables() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'live_tv_channels';
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name tinytext NOT NULL,
            description text,
            stream_url varchar(500) NOT NULL,
            logo_url varchar(500),
            category varchar(100),
            is_active tinyint(1) DEFAULT 1,
            sort_order int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Insert sample channels
        $this->insert_sample_channels();
    }
    
    private function insert_sample_channels() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'live_tv_channels';
        
        // Check if channels already exist
        $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        if ($count > 0) {
            return;
        }
        
        $sample_channels = array(
            array(
                'name' => 'News Channel 1',
                'description' => 'Live news and updates',
                'stream_url' => 'https://sample-videos.com/zip/10/mp4/SampleVideo_1280x720_1mb.mp4',
                'category' => 'News',
                'sort_order' => 1
            ),
            array(
                'name' => 'Sports TV',
                'description' => 'Live sports coverage',
                'stream_url' => 'https://sample-videos.com/zip/10/mp4/SampleVideo_1280x720_2mb.mp4',
                'category' => 'Sports',
                'sort_order' => 2
            ),
            array(
                'name' => 'Entertainment Plus',
                'description' => 'Movies and entertainment',
                'stream_url' => 'https://sample-videos.com/zip/10/mp4/SampleVideo_1280x720_5mb.mp4',
                'category' => 'Entertainment',
                'sort_order' => 3
            ),
            array(
                'name' => 'SNTV',
                'description' => 'Demo channel',
                'stream_url' => 'https://ap02.iqplay.tv:8082/iqb8002/s4ne/playlist.m3u8',
                'category' => 'Other',
                'sort_order' => 4
            ),
            array(
                'name' => 'SNTV 2',
                'description' => 'Demo channel',
                'stream_url' => 'https://ap02.iqplay.tv:8082/iqb8002/s2tve/playlist.m3u8',
                'category' => 'Other',
                'sort_order' => 5
            ),
            array(
                'name' => 'RTD DJIBOUTI',
                'description' => 'Demo channel',
                'stream_url' => 'https://dvrfl05.bozztv.com/gin-rtddjibouti/playlist.m3u8',
                'category' => 'Other',
                'sort_order' => 6
            ),
            array(
                'name' => 'UNIVERSAL TV',
                'description' => 'Demo channel',
                'stream_url' => 'https://cdn.mediavisionuk.com:9000/universaltvhd/index.m3u8',
                'category' => 'Other',
                'sort_order' => 7
            ),
            array(
                'name' => 'HIRSHABELLE',
                'description' => 'Demo channel',
                'stream_url' => 'http://ap02.iqplay.tv:8081/iqb8002/h1rshbe1iptv/playlist.m3u8',
                'category' => 'Other',
                'sort_order' => 8
            ),
            array(
                'name' => 'SAAB TV',
                'description' => 'Demo channel',
                'stream_url' => 'https://ap02.iqplay.tv:8082/iqb8002/s03btv/playlist.m3u8',
                'category' => 'News',
                'sort_order' => 9
            ),
            array(
                'name' => 'AFRO BEATS',
                'description' => 'Demo channel',
                'stream_url' => 'https://stream.ecable.tv/afrobeats/index.m3u8',
                'category' => 'Music',
                'sort_order' => 10
            ),
            array(
                'name' => 'CITIZEN EXTRA TV',
                'description' => 'Demo channel',
                'stream_url' => 'https://74937.global.ssl.fastly.net/5ea49827ff3b5d7b22708777/live_40c5808063f711ec89a87b62db2ecab5/index.m3u8',
                'category' => 'News',
                'sort_order' => 11
            ),
            array(
                'name' => 'MAKKAH',
                'description' => 'Demo channel',
                'stream_url' => 'https://ap02.iqplay.tv:8082/iqb8002/3m9n/playlist.m3u8',
                'category' => 'News',
                'sort_order' => 12
            )
        );
        
        // Insert each sample channel with error handling
        $inserted_count = 0;
        foreach ($sample_channels as $channel) {
            $result = $wpdb->insert(
                $table_name, 
                $channel,
                array('%s', '%s', '%s', '%s', '%d') // Data format specification
            );
            
            if ($result !== false) {
                $inserted_count++;
            } else {
                error_log('Failed to insert sample channel: ' . $wpdb->last_error);
            }
        }
        
        // Log the result for debugging
        error_log("Live TV Plugin: Inserted {$inserted_count} sample channels");
        
        return $inserted_count > 0;
    }
    
    /**
     * Get all active channels
     * 
     * Retrieves all channels that are marked as active,
     * ordered by sort order and name.
     * 
     * @since 3.1.0
     * @return array Array of channel objects
     */
    public function get_active_channels() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'live_tv_channels';
        
        return $wpdb->get_results(
            "SELECT * FROM {$table_name} WHERE is_active = 1 ORDER BY sort_order ASC, name ASC",
            ARRAY_A
        );
    }
    
    /**
     * Get channels by category
     * 
     * Retrieves active channels filtered by a specific category.
     * 
     * @since 3.1.0
     * @param string $category Category to filter by
     * @return array Array of channel objects
     */
    public function get_channels_by_category($category) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'live_tv_channels';
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE is_active = 1 AND category = %s ORDER BY sort_order ASC, name ASC",
                sanitize_text_field($category)
            ),
            ARRAY_A
        );
    }
    
    /**
     * Update channel status
     * 
     * Enables or disables a specific channel.
     * 
     * @since 3.1.0
     * @param int $channel_id Channel ID to update
     * @param bool $is_active New active status
     * @return bool True on success, false on failure
     */
    public function update_channel_status($channel_id, $is_active) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'live_tv_channels';
        
        return $wpdb->update(
            $table_name,
            array('is_active' => $is_active ? 1 : 0),
            array('id' => intval($channel_id)),
            array('%d'),
            array('%d')
        ) !== false;
    }
    
    /**
     * Delete a channel
     * 
     * Permanently removes a channel from the database.
     * 
     * @since 3.1.0
     * @param int $channel_id Channel ID to delete
     * @return bool True on success, false on failure
     */
    public function delete_channel($channel_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'live_tv_channels';
        
        return $wpdb->delete(
            $table_name,
            array('id' => intval($channel_id)),
            array('%d')
        ) !== false;
    }
    
    /**
     * Get channel by ID
     * 
     * Retrieves a specific channel by its ID.
     * 
     * @since 3.1.0
     * @param int $channel_id Channel ID to retrieve
     * @return object|null Channel object or null if not found
     */
    public function get_channel_by_id($channel_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'live_tv_channels';
        
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE id = %d",
                intval($channel_id)
            )
        );
    }
}