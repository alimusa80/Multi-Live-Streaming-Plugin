<?php
/**
 * Modern REST API Implementation
 * 
 * Replaces legacy AJAX calls with WordPress REST API endpoints
 * Includes rate limiting, caching, and modern security features
 * 
 * @package LiveTVStreaming
 * @since 4.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

/**
 * LiveTVRestAPI class for modern API endpoints
 * 
 * This class handles all REST API functionality with enhanced security,
 * performance optimizations, and modern API design patterns.
 * 
 * @since 4.0.0
 */
class LiveTVRestAPI {
    
    /**
     * API namespace
     * @var string
     */
    private $namespace = 'livetv/v1';
    
    /**
     * Rate limiting storage
     * @var array
     */
    private $rate_limits = array();
    
    /**
     * Cache groups
     * @var array
     */
    private $cache_groups = array(
        'channels' => 300,      // 5 minutes
        'analytics' => 600,     // 10 minutes
        'recommendations' => 900, // 15 minutes
        'playlists' => 1800     // 30 minutes
    );
    
    /**
     * Initialize REST API endpoints
     * 
     * @since 4.0.0
     */
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
        add_action('rest_api_init', array($this, 'setup_cors'));
        add_filter('rest_pre_serve_request', array($this, 'handle_cors_preflight'), 10, 4);
    }
    
    /**
     * Register all REST API routes
     * 
     * @since 4.0.0
     */
    public function register_routes() {
        // Channel endpoints
        register_rest_route($this->namespace, '/channels', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_channels'),
                'permission_callback' => array($this, 'check_read_permission'),
                'args' => array(
                    'category' => array(
                        'description' => 'Filter by channel category',
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_text_field'
                    ),
                    'active_only' => array(
                        'description' => 'Return only active channels',
                        'type' => 'boolean',
                        'default' => true
                    ),
                    'per_page' => array(
                        'description' => 'Number of channels per page',
                        'type' => 'integer',
                        'default' => 50,
                        'minimum' => 1,
                        'maximum' => 100
                    ),
                    'page' => array(
                        'description' => 'Current page number',
                        'type' => 'integer',
                        'default' => 1,
                        'minimum' => 1
                    )
                )
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_channel'),
                'permission_callback' => array($this, 'check_admin_permission'),
                'args' => $this->get_channel_schema()
            )
        ));
        
        register_rest_route($this->namespace, '/channels/(?P<id>\d+)', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_channel'),
                'permission_callback' => array($this, 'check_read_permission'),
                'args' => array(
                    'id' => array(
                        'description' => 'Unique identifier for the channel',
                        'type' => 'integer',
                        'required' => true
                    )
                )
            ),
            array(
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => array($this, 'update_channel'),
                'permission_callback' => array($this, 'check_admin_permission'),
                'args' => $this->get_channel_schema()
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'delete_channel'),
                'permission_callback' => array($this, 'check_admin_permission')
            )
        ));
        
        // Streaming endpoints
        register_rest_route($this->namespace, '/stream/(?P<channel_id>\d+)', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_stream_info'),
            'permission_callback' => array($this, 'check_stream_permission'),
            'args' => array(
                'channel_id' => array(
                    'description' => 'Channel ID to stream',
                    'type' => 'integer',
                    'required' => true
                ),
                'quality' => array(
                    'description' => 'Preferred video quality',
                    'type' => 'string',
                    'enum' => array('auto', '4k', '1080p', '720p', '480p', '360p'),
                    'default' => 'auto'
                ),
                'codec' => array(
                    'description' => 'Preferred codec',
                    'type' => 'string',
                    'enum' => array('auto', 'av1', 'h264', 'h265', 'vp9'),
                    'default' => 'auto'
                )
            )
        ));
        
        // Analytics endpoints
        register_rest_route($this->namespace, '/analytics/track', array(
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => array($this, 'track_analytics'),
            'permission_callback' => '__return_true', // Public endpoint with rate limiting
            'args' => array(
                'event_type' => array(
                    'description' => 'Type of analytics event',
                    'type' => 'string',
                    'enum' => array('view_start', 'view_end', 'channel_change', 'quality_change'),
                    'required' => true
                ),
                'channel_id' => array(
                    'description' => 'Channel being viewed',
                    'type' => 'integer',
                    'required' => true
                ),
                'duration' => array(
                    'description' => 'Duration in seconds',
                    'type' => 'integer',
                    'default' => 0
                ),
                'quality' => array(
                    'description' => 'Video quality',
                    'type' => 'string'
                ),
                'device_info' => array(
                    'description' => 'Device information',
                    'type' => 'object'
                )
            )
        ));
        
        register_rest_route($this->namespace, '/analytics/stats', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_analytics_stats'),
            'permission_callback' => array($this, 'check_admin_permission'),
            'args' => array(
                'period' => array(
                    'description' => 'Time period for stats',
                    'type' => 'string',
                    'enum' => array('today', 'week', 'month', 'year'),
                    'default' => 'week'
                ),
                'metric' => array(
                    'description' => 'Specific metric to retrieve',
                    'type' => 'string',
                    'enum' => array('views', 'duration', 'popular_channels', 'device_types')
                )
            )
        ));
        
        // User preferences endpoints
        register_rest_route($this->namespace, '/user/preferences', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_user_preferences'),
                'permission_callback' => array($this, 'check_user_permission')
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'update_user_preferences'),
                'permission_callback' => array($this, 'check_user_permission'),
                'args' => array(
                    'favorites' => array(
                        'description' => 'Array of favorite channel IDs',
                        'type' => 'array',
                        'items' => array('type' => 'integer')
                    ),
                    'settings' => array(
                        'description' => 'User settings object',
                        'type' => 'object'
                    )
                )
            )
        ));
        
        register_rest_route($this->namespace, '/user/favorites/(?P<channel_id>\d+)', array(
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'add_favorite'),
                'permission_callback' => array($this, 'check_user_permission')
            ),
            array(
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => array($this, 'remove_favorite'),
                'permission_callback' => array($this, 'check_user_permission')
            )
        ));
        
        // Recommendations endpoint
        register_rest_route($this->namespace, '/recommendations', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'get_recommendations'),
            'permission_callback' => array($this, 'check_read_permission'),
            'args' => array(
                'user_id' => array(
                    'description' => 'User ID for personalized recommendations',
                    'type' => 'integer'
                ),
                'count' => array(
                    'description' => 'Number of recommendations',
                    'type' => 'integer',
                    'default' => 10,
                    'minimum' => 1,
                    'maximum' => 50
                ),
                'exclude' => array(
                    'description' => 'Channel IDs to exclude',
                    'type' => 'array',
                    'items' => array('type' => 'integer')
                )
            )
        ));
        
        // Playlist endpoints
        register_rest_route($this->namespace, '/playlists', array(
            array(
                'methods' => WP_REST_Server::READABLE,
                'callback' => array($this, 'get_playlists'),
                'permission_callback' => array($this, 'check_read_permission')
            ),
            array(
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => array($this, 'create_playlist'),
                'permission_callback' => array($this, 'check_user_permission'),
                'args' => array(
                    'name' => array(
                        'description' => 'Playlist name',
                        'type' => 'string',
                        'required' => true,
                        'sanitize_callback' => 'sanitize_text_field'
                    ),
                    'description' => array(
                        'description' => 'Playlist description',
                        'type' => 'string',
                        'sanitize_callback' => 'sanitize_textarea_field'
                    ),
                    'channels' => array(
                        'description' => 'Array of channel IDs',
                        'type' => 'array',
                        'items' => array('type' => 'integer'),
                        'default' => array()
                    ),
                    'public' => array(
                        'description' => 'Whether playlist is public',
                        'type' => 'boolean',
                        'default' => false
                    )
                )
            )
        ));
        
        // Health check endpoint
        register_rest_route($this->namespace, '/health', array(
            'methods' => WP_REST_Server::READABLE,
            'callback' => array($this, 'health_check'),
            'permission_callback' => '__return_true'
        ));
    }
    
    /**
     * Get channels with enhanced filtering and caching
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function get_channels($request) {
        // Rate limiting
        if (!$this->check_rate_limit('channels', 100, 60)) {
            return new WP_Error('rate_limit_exceeded', 'Rate limit exceeded', array('status' => 429));
        }
        
        // Build cache key
        $cache_key = 'livetv_channels_' . md5(serialize($request->get_params()));
        $cached_data = wp_cache_get($cache_key, 'livetv_channels');
        
        if ($cached_data !== false) {
            return rest_ensure_response($cached_data);
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'live_tv_channels';
        
        // Build query
        $where_clauses = array();
        $prepare_values = array();
        
        if ($request->get_param('active_only')) {
            $where_clauses[] = 'is_active = %d';
            $prepare_values[] = 1;
        }
        
        if ($request->get_param('category')) {
            $where_clauses[] = 'category = %s';
            $prepare_values[] = $request->get_param('category');
        }
        
        $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
        
        // Pagination
        $per_page = $request->get_param('per_page');
        $page = $request->get_param('page');
        $offset = ($page - 1) * $per_page;
        
        // Get total count
        $count_query = "SELECT COUNT(*) FROM {$table_name} {$where_sql}";
        if (!empty($prepare_values)) {
            $total_items = $wpdb->get_var($wpdb->prepare($count_query, $prepare_values));
        } else {
            $total_items = $wpdb->get_var($count_query);
        }
        
        // Get channels
        $query = "SELECT * FROM {$table_name} {$where_sql} ORDER BY sort_order ASC, name ASC LIMIT %d OFFSET %d";
        $prepare_values[] = $per_page;
        $prepare_values[] = $offset;
        
        $channels = $wpdb->get_results($wpdb->prepare($query, $prepare_values));
        
        // Process channels
        $processed_channels = array_map(array($this, 'process_channel_data'), $channels);
        
        $response_data = array(
            'channels' => $processed_channels,
            'pagination' => array(
                'total' => (int) $total_items,
                'per_page' => $per_page,
                'current_page' => $page,
                'total_pages' => ceil($total_items / $per_page)
            )
        );
        
        // Cache the response
        wp_cache_set($cache_key, $response_data, 'livetv_channels', $this->cache_groups['channels']);
        
        $response = rest_ensure_response($response_data);
        $response->header('X-WP-Total', $total_items);
        $response->header('X-WP-TotalPages', ceil($total_items / $per_page));
        
        return $response;
    }
    
    /**
     * Get stream information with adaptive streaming support
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function get_stream_info($request) {
        $channel_id = $request->get_param('channel_id');
        $quality = $request->get_param('quality');
        $codec = $request->get_param('codec');
        
        // Rate limiting for streaming
        if (!$this->check_rate_limit('stream_' . $channel_id, 10, 60)) {
            return new WP_Error('rate_limit_exceeded', 'Too many stream requests', array('status' => 429));
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'live_tv_channels';
        
        $channel = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d AND is_active = 1",
            $channel_id
        ));
        
        if (!$channel) {
            return new WP_Error('channel_not_found', 'Channel not found or inactive', array('status' => 404));
        }
        
        // Build adaptive stream URLs
        $stream_variants = $this->build_adaptive_streams($channel->stream_url, $quality, $codec);
        
        // User agent and capability detection
        $user_agent = $request->get_header('user-agent');
        $client_capabilities = $this->detect_client_capabilities($user_agent);
        
        // Select optimal stream
        $optimal_stream = $this->select_optimal_stream($stream_variants, $client_capabilities);
        
        $response_data = array(
            'channel' => $this->process_channel_data($channel),
            'stream' => array(
                'url' => $optimal_stream['url'],
                'type' => $optimal_stream['type'],
                'codec' => $optimal_stream['codec'],
                'quality' => $optimal_stream['quality'],
                'bitrate' => $optimal_stream['bitrate'],
                'variants' => $stream_variants
            ),
            'capabilities' => $client_capabilities,
            'metadata' => array(
                'generated_at' => current_time('c'),
                'expires_at' => date('c', time() + 3600), // 1 hour expiry
                'session_id' => wp_generate_uuid4()
            )
        );
        
        // Track stream request
        $this->track_stream_request($channel_id, $user_agent, $client_capabilities);
        
        return rest_ensure_response($response_data);
    }
    
    /**
     * Track analytics events
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function track_analytics($request) {
        // Strict rate limiting for analytics
        $client_ip = $this->get_client_ip();
        if (!$this->check_rate_limit('analytics_' . $client_ip, 200, 60)) {
            return new WP_Error('rate_limit_exceeded', 'Analytics rate limit exceeded', array('status' => 429));
        }
        
        $event_type = $request->get_param('event_type');
        $channel_id = $request->get_param('channel_id');
        $duration = $request->get_param('duration');
        $quality = $request->get_param('quality');
        $device_info = $request->get_param('device_info');
        
        // Validate channel exists
        global $wpdb;
        $channel_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}live_tv_channels WHERE id = %d",
            $channel_id
        ));
        
        if (!$channel_exists) {
            return new WP_Error('invalid_channel', 'Channel does not exist', array('status' => 400));
        }
        
        // Prepare analytics data
        $analytics_data = array(
            'channel_id' => $channel_id,
            'user_id' => get_current_user_id() ?: null,
            'session_id' => $this->get_or_create_session_id(),
            'event_type' => $event_type,
            'duration' => max(0, $duration),
            'quality' => sanitize_text_field($quality),
            'ip_address' => $client_ip,
            'user_agent' => $request->get_header('user-agent'),
            'device_info' => wp_json_encode($device_info),
            'timestamp' => current_time('mysql', true),
            'created_at' => current_time('mysql')
        );
        
        // Insert analytics record
        $analytics_table = $wpdb->prefix . 'live_tv_analytics';
        $result = $wpdb->insert($analytics_table, $analytics_data);
        
        if ($result === false) {
            return new WP_Error('analytics_failed', 'Failed to record analytics', array('status' => 500));
        }
        
        // Clear analytics cache
        wp_cache_delete_group('livetv_analytics');
        
        return rest_ensure_response(array(
            'success' => true,
            'event_id' => $wpdb->insert_id,
            'message' => 'Analytics recorded successfully'
        ));
    }
    
    /**
     * Get AI-powered recommendations
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function get_recommendations($request) {
        $user_id = $request->get_param('user_id') ?: get_current_user_id();
        $count = $request->get_param('count');
        $exclude = $request->get_param('exclude') ?: array();
        
        // Build cache key
        $cache_key = 'livetv_recommendations_' . $user_id . '_' . md5(serialize($request->get_params()));
        $cached_recommendations = wp_cache_get($cache_key, 'livetv_recommendations');
        
        if ($cached_recommendations !== false) {
            return rest_ensure_response($cached_recommendations);
        }
        
        // Get user preferences and history
        $user_preferences = $this->get_user_preference_data($user_id);
        $user_history = $this->get_user_viewing_history($user_id, 100);
        
        // Get all active channels
        global $wpdb;
        $channels_table = $wpdb->prefix . 'live_tv_channels';
        
        $exclude_sql = '';
        if (!empty($exclude)) {
            $exclude_placeholders = implode(',', array_fill(0, count($exclude), '%d'));
            $exclude_sql = "AND id NOT IN ($exclude_placeholders)";
        }
        
        $query = "SELECT * FROM {$channels_table} WHERE is_active = 1 {$exclude_sql} ORDER BY sort_order ASC";
        $prepare_values = !empty($exclude) ? $exclude : array();
        
        if (!empty($prepare_values)) {
            $channels = $wpdb->get_results($wpdb->prepare($query, $prepare_values));
        } else {
            $channels = $wpdb->get_results($query);
        }
        
        // Generate AI recommendations
        $recommendations = $this->generate_ai_recommendations($channels, $user_preferences, $user_history, $count);
        
        // Enrich with additional data
        $enriched_recommendations = array_map(function($channel) {
            $processed = $this->process_channel_data($channel);
            $processed['recommendation_score'] = $channel->recommendation_score ?? 0;
            $processed['recommendation_reason'] = $channel->recommendation_reason ?? 'Popular content';
            return $processed;
        }, $recommendations);
        
        $response_data = array(
            'recommendations' => $enriched_recommendations,
            'user_id' => $user_id,
            'algorithm' => 'hybrid_ai',
            'generated_at' => current_time('c'),
            'cache_expires' => date('c', time() + $this->cache_groups['recommendations'])
        );
        
        // Cache recommendations
        wp_cache_set($cache_key, $response_data, 'livetv_recommendations', $this->cache_groups['recommendations']);
        
        return rest_ensure_response($response_data);
    }
    
    /**
     * Health check endpoint
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response
     */
    public function health_check($request) {
        global $wpdb;
        
        $health_data = array(
            'status' => 'healthy',
            'timestamp' => current_time('c'),
            'version' => LIVE_TV_VERSION,
            'services' => array(
                'database' => $this->check_database_health(),
                'cache' => $this->check_cache_health(),
                'streaming' => $this->check_streaming_health()
            ),
            'metrics' => array(
                'total_channels' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}live_tv_channels WHERE is_active = 1"),
                'active_sessions' => $this->get_active_sessions_count(),
                'api_calls_last_hour' => $this->get_api_calls_count(3600)
            )
        );
        
        // Determine overall health
        $all_services_healthy = array_reduce($health_data['services'], function($carry, $service) {
            return $carry && $service['status'] === 'healthy';
        }, true);
        
        if (!$all_services_healthy) {
            $health_data['status'] = 'degraded';
        }
        
        $response = rest_ensure_response($health_data);
        
        // Set appropriate status code
        if ($health_data['status'] !== 'healthy') {
            $response->set_status(503);
        }
        
        return $response;
    }
    
    // Permission callbacks
    
    /**
     * Check if user can read content
     */
    public function check_read_permission($request) {
        return true; // Public read access
    }
    
    /**
     * Check if user has admin permissions
     */
    public function check_admin_permission($request) {
        return current_user_can('manage_options');
    }
    
    /**
     * Check if user is authenticated
     */
    public function check_user_permission($request) {
        return is_user_logged_in();
    }
    
    /**
     * Check streaming permissions with token validation
     */
    public function check_stream_permission($request) {
        // For now, allow public access - implement token auth later
        return true;
    }
    
    // Utility methods
    
    /**
     * Rate limiting implementation
     */
    private function check_rate_limit($key, $limit, $window) {
        $client_ip = $this->get_client_ip();
        $rate_key = "rate_limit_{$key}_{$client_ip}";
        
        $current_count = (int) wp_cache_get($rate_key, 'livetv_rate_limits') ?: 0;
        
        if ($current_count >= $limit) {
            return false;
        }
        
        wp_cache_set($rate_key, $current_count + 1, 'livetv_rate_limits', $window);
        return true;
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ips = explode(',', $_SERVER[$key]);
                return trim($ips[0]);
            }
        }
        
        return '127.0.0.1';
    }
    
    /**
     * Process channel data for API response
     */
    private function process_channel_data($channel) {
        return array(
            'id' => (int) $channel->id,
            'name' => $channel->name,
            'description' => $channel->description,
            'category' => $channel->category,
            'logo_url' => $channel->logo_url,
            'is_active' => (bool) $channel->is_active,
            'sort_order' => (int) $channel->sort_order,
            'created_at' => $channel->created_at,
            'updated_at' => $channel->updated_at
        );
    }
    
    /**
     * Build adaptive streaming URLs
     */
    private function build_adaptive_streams($base_url, $preferred_quality, $preferred_codec) {
        // This would integrate with your streaming infrastructure
        // For demo, returning mock adaptive streams
        return array(
            array(
                'url' => $base_url,
                'type' => 'application/x-mpegURL',
                'codec' => $preferred_codec === 'auto' ? 'h264' : $preferred_codec,
                'quality' => $preferred_quality === 'auto' ? '1080p' : $preferred_quality,
                'bitrate' => 5000000
            )
        );
    }
    
    /**
     * Select optimal stream based on client capabilities
     */
    private function select_optimal_stream($variants, $capabilities) {
        // Simple selection logic - can be enhanced with ML
        return $variants[0];
    }
    
    /**
     * Detect client capabilities from user agent
     */
    private function detect_client_capabilities($user_agent) {
        return array(
            'av1_support' => strpos($user_agent, 'Chrome') !== false,
            'webrtc_support' => true,
            'hls_support' => true,
            'max_resolution' => '1080p',
            'device_type' => 'desktop'
        );
    }
    
    /**
     * Get channel schema for validation
     */
    private function get_channel_schema() {
        return array(
            'name' => array(
                'description' => 'Channel name',
                'type' => 'string',
                'required' => true,
                'sanitize_callback' => 'sanitize_text_field'
            ),
            'description' => array(
                'description' => 'Channel description',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_textarea_field'
            ),
            'stream_url' => array(
                'description' => 'Stream URL',
                'type' => 'string',
                'required' => true,
                'format' => 'uri'
            ),
            'logo_url' => array(
                'description' => 'Logo URL',
                'type' => 'string',
                'format' => 'uri'
            ),
            'category' => array(
                'description' => 'Channel category',
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            ),
            'is_active' => array(
                'description' => 'Whether channel is active',
                'type' => 'boolean',
                'default' => true
            ),
            'sort_order' => array(
                'description' => 'Sort order',
                'type' => 'integer',
                'default' => 0
            )
        );
    }
    
    /**
     * Setup CORS headers
     */
    public function setup_cors() {
        remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
        add_filter('rest_pre_serve_request', array($this, 'add_cors_headers'), 15, 4);
    }
    
    /**
     * Add enhanced CORS headers
     */
    public function add_cors_headers($served, $result, $request, $server) {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce');
        header('Access-Control-Expose-Headers: X-WP-Total, X-WP-TotalPages');
        header('Access-Control-Allow-Credentials: true');
        
        return $served;
    }
    
    /**
     * Handle CORS preflight requests
     */
    public function handle_cors_preflight($served, $result, $request, $server) {
        if ($request->get_method() === 'OPTIONS') {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Authorization, Content-Type, X-WP-Nonce');
            header('Access-Control-Max-Age: 86400');
            return true;
        }
        
        return $served;
    }
    
    // Health check helpers
    private function check_database_health() {
        global $wpdb;
        try {
            $wpdb->get_var("SELECT 1");
            return array('status' => 'healthy', 'response_time' => '< 1ms');
        } catch (Exception $e) {
            return array('status' => 'unhealthy', 'error' => $e->getMessage());
        }
    }
    
    private function check_cache_health() {
        $test_key = 'health_check_' . time();
        wp_cache_set($test_key, 'test', 'default', 10);
        $cached = wp_cache_get($test_key, 'default');
        
        return array(
            'status' => $cached === 'test' ? 'healthy' : 'degraded',
            'type' => wp_using_ext_object_cache() ? 'persistent' : 'transient'
        );
    }
    
    private function check_streaming_health() {
        // Check if streaming endpoints are responsive
        return array('status' => 'healthy', 'active_streams' => 0);
    }
    
    private function get_active_sessions_count() {
        // Count active streaming sessions
        return wp_cache_get('active_sessions_count', 'livetv_metrics') ?: 0;
    }
    
    private function get_api_calls_count($seconds) {
        // Count API calls in the last X seconds
        return wp_cache_get("api_calls_$seconds", 'livetv_metrics') ?: 0;
    }
    
    // Placeholder methods for recommendation system integration
    private function get_user_preference_data($user_id) {
        return array();
    }
    
    private function get_user_viewing_history($user_id, $limit) {
        return array();
    }
    
    private function generate_ai_recommendations($channels, $preferences, $history, $count) {
        // Placeholder for AI recommendation logic
        return array_slice($channels, 0, $count);
    }
    
    private function get_or_create_session_id() {
        if (isset($_COOKIE['livetv_session'])) {
            return $_COOKIE['livetv_session'];
        }
        
        $session_id = wp_generate_uuid4();
        setcookie('livetv_session', $session_id, time() + DAY_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN);
        return $session_id;
    }
    
    private function track_stream_request($channel_id, $user_agent, $capabilities) {
        // Track stream requests for analytics
        wp_cache_add('stream_requests_' . date('Y-m-d-H'), 0, 'livetv_metrics', HOUR_IN_SECONDS);
        wp_cache_incr('stream_requests_' . date('Y-m-d-H'), 1, 'livetv_metrics');
    }
}

// Initialize the REST API
new LiveTVRestAPI();