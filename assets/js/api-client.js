/**
 * Modern API Client
 * 
 * Replaces legacy AJAX calls with modern fetch-based REST API client
 * Includes automatic retries, caching, and error handling
 * 
 * @version 1.0.0
 * @author Live TV Streaming Pro
 */

(function() {
    'use strict';

    class LiveTVAPIClient {
        constructor(options = {}) {
            this.options = Object.assign({
                baseUrl: '/wp-json/livetv/v1',
                timeout: 30000,
                retryAttempts: 3,
                retryDelay: 1000,
                enableCaching: true,
                cacheTimeout: 300000, // 5 minutes
                enableOffline: true,
                enableAnalytics: true
            }, options);
            
            this.cache = new Map();
            this.requestQueue = new Map();
            this.offlineQueue = [];
            this.isOnline = navigator.onLine;
            this.analytics = {
                requests: 0,
                errors: 0,
                cacheHits: 0,
                averageResponseTime: 0
            };
            
            this.init();
        }
        
        /**
         * Initialize the API client
         */
        init() {
            // Setup online/offline detection
            this.setupOfflineHandling();
            
            // Setup request interceptors
            this.setupInterceptors();
            
            // Setup periodic cache cleanup
            this.setupCacheCleanup();
            
            console.log('LiveTV API Client initialized');
        }
        
        /**
         * Setup offline handling
         */
        setupOfflineHandling() {
            window.addEventListener('online', () => {
                this.isOnline = true;
                this.processOfflineQueue();
            });
            
            window.addEventListener('offline', () => {
                this.isOnline = false;
                console.log('API Client: Offline mode activated');
            });
        }
        
        /**
         * Setup request interceptors
         */
        setupInterceptors() {
            // Request interceptor
            this.requestInterceptor = (config) => {
                // Add authentication headers
                if (typeof wpApiSettings !== 'undefined') {
                    config.headers = Object.assign({
                        'X-WP-Nonce': wpApiSettings.nonce
                    }, config.headers || {});
                }
                
                // Add request ID for tracking
                config.requestId = this.generateRequestId();
                
                return config;
            };
            
            // Response interceptor
            this.responseInterceptor = (response, config) => {
                // Update analytics
                this.updateAnalytics('success', response, config);
                
                return response;
            };
            
            // Error interceptor
            this.errorInterceptor = (error, config) => {
                // Update analytics
                this.updateAnalytics('error', error, config);
                
                // Handle specific error types
                if (error.status === 429) {
                    console.warn('API Client: Rate limit exceeded, queuing request');
                    return this.queueRequest(config);
                }
                
                return Promise.reject(error);
            };
        }
        
        /**
         * Make HTTP request with modern fetch API
         */
        async request(endpoint, options = {}) {
            const config = Object.assign({
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json'
                },
                timeout: this.options.timeout
            }, options);
            
            // Apply request interceptor
            const interceptedConfig = this.requestInterceptor(config);
            
            // Check cache for GET requests
            if (config.method === 'GET' && this.options.enableCaching) {
                const cached = this.getFromCache(endpoint, config);
                if (cached) {
                    this.analytics.cacheHits++;
                    return Promise.resolve(cached);
                }
            }
            
            // Check if offline and queue request
            if (!this.isOnline && this.options.enableOffline) {
                return this.queueOfflineRequest(endpoint, interceptedConfig);
            }
            
            const startTime = performance.now();
            
            try {
                const response = await this.fetchWithTimeout(
                    this.buildUrl(endpoint),
                    interceptedConfig
                );
                
                const endTime = performance.now();
                const responseTime = endTime - startTime;
                
                // Update average response time
                this.updateResponseTime(responseTime);
                
                const data = await this.parseResponse(response);
                
                // Apply response interceptor
                const interceptedData = this.responseInterceptor(data, interceptedConfig);
                
                // Cache successful GET requests
                if (config.method === 'GET' && this.options.enableCaching) {
                    this.setCache(endpoint, config, interceptedData);
                }
                
                return interceptedData;
                
            } catch (error) {
                return this.handleError(error, endpoint, interceptedConfig);
            }
        }
        
        /**
         * Fetch with timeout and retry logic
         */
        async fetchWithTimeout(url, config, attempt = 1) {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), config.timeout);
            
            try {
                const response = await fetch(url, {
                    ...config,
                    signal: controller.signal
                });
                
                clearTimeout(timeoutId);
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                return response;
                
            } catch (error) {
                clearTimeout(timeoutId);
                
                // Retry on network errors
                if (attempt < this.options.retryAttempts && 
                    (error.name === 'AbortError' || error.name === 'TypeError')) {
                    
                    console.log(`API Client: Retrying request (${attempt}/${this.options.retryAttempts})`);
                    
                    await this.delay(this.options.retryDelay * Math.pow(2, attempt - 1));
                    return this.fetchWithTimeout(url, config, attempt + 1);
                }
                
                throw error;
            }
        }
        
        /**
         * Parse response based on content type
         */
        async parseResponse(response) {
            const contentType = response.headers.get('content-type');
            
            if (contentType && contentType.includes('application/json')) {
                return response.json();
            }
            
            if (contentType && contentType.includes('text/')) {
                return response.text();
            }
            
            return response.blob();
        }
        
        /**
         * Handle errors with retry and fallback logic
         */
        async handleError(error, endpoint, config) {
            console.error('API Client Error:', error);
            
            try {
                return await this.errorInterceptor(error, config);
            } catch (interceptedError) {
                // Try cache for GET requests as fallback
                if (config.method === 'GET') {
                    const cached = this.getFromCache(endpoint, config, true); // Allow stale
                    if (cached) {
                        console.warn('API Client: Using stale cache due to error');
                        return cached;
                    }
                }
                
                throw interceptedError;
            }
        }
        
        // Channel API methods
        
        /**
         * Get channels with filtering and pagination
         */
        async getChannels(params = {}) {
            const queryString = this.buildQueryString(params);
            const endpoint = `/channels${queryString}`;
            
            return this.request(endpoint);
        }
        
        /**
         * Get single channel by ID
         */
        async getChannel(channelId) {
            return this.request(`/channels/${channelId}`);
        }
        
        /**
         * Create new channel (admin only)
         */
        async createChannel(channelData) {
            return this.request('/channels', {
                method: 'POST',
                body: JSON.stringify(channelData)
            });
        }
        
        /**
         * Update channel (admin only)
         */
        async updateChannel(channelId, channelData) {
            return this.request(`/channels/${channelId}`, {
                method: 'PUT',
                body: JSON.stringify(channelData)
            });
        }
        
        /**
         * Delete channel (admin only)
         */
        async deleteChannel(channelId) {
            return this.request(`/channels/${channelId}`, {
                method: 'DELETE'
            });
        }
        
        // Streaming API methods
        
        /**
         * Get stream information
         */
        async getStreamInfo(channelId, options = {}) {
            const queryString = this.buildQueryString(options);
            const endpoint = `/stream/${channelId}${queryString}`;
            
            return this.request(endpoint);
        }
        
        // Analytics API methods
        
        /**
         * Track analytics event
         */
        async trackAnalytics(eventData) {
            return this.request('/analytics/track', {
                method: 'POST',
                body: JSON.stringify(eventData)
            });
        }
        
        /**
         * Get analytics stats (admin only)
         */
        async getAnalyticsStats(params = {}) {
            const queryString = this.buildQueryString(params);
            const endpoint = `/analytics/stats${queryString}`;
            
            return this.request(endpoint);
        }
        
        // User API methods
        
        /**
         * Get user preferences
         */
        async getUserPreferences() {
            return this.request('/user/preferences');
        }
        
        /**
         * Update user preferences
         */
        async updateUserPreferences(preferences) {
            return this.request('/user/preferences', {
                method: 'POST',
                body: JSON.stringify(preferences)
            });
        }
        
        /**
         * Add channel to favorites
         */
        async addFavorite(channelId) {
            return this.request(`/user/favorites/${channelId}`, {
                method: 'POST'
            });
        }
        
        /**
         * Remove channel from favorites
         */
        async removeFavorite(channelId) {
            return this.request(`/user/favorites/${channelId}`, {
                method: 'DELETE'
            });
        }
        
        // Recommendation API methods
        
        /**
         * Get AI-powered recommendations
         */
        async getRecommendations(params = {}) {
            const queryString = this.buildQueryString(params);
            const endpoint = `/recommendations${queryString}`;
            
            return this.request(endpoint);
        }
        
        // Playlist API methods
        
        /**
         * Get user playlists
         */
        async getPlaylists() {
            return this.request('/playlists');
        }
        
        /**
         * Create new playlist
         */
        async createPlaylist(playlistData) {
            return this.request('/playlists', {
                method: 'POST',
                body: JSON.stringify(playlistData)
            });
        }
        
        // Health check
        
        /**
         * Check API health
         */
        async healthCheck() {
            return this.request('/health');
        }
        
        // Utility methods
        
        /**
         * Build full URL
         */
        buildUrl(endpoint) {
            const baseUrl = this.options.baseUrl.replace(/\/+$/, '');
            const cleanEndpoint = endpoint.replace(/^\/+/, '');
            return `${baseUrl}/${cleanEndpoint}`;
        }
        
        /**
         * Build query string from parameters
         */
        buildQueryString(params) {
            if (!params || Object.keys(params).length === 0) {
                return '';
            }
            
            const searchParams = new URLSearchParams();
            
            Object.entries(params).forEach(([key, value]) => {
                if (value !== null && value !== undefined) {
                    if (Array.isArray(value)) {
                        value.forEach(item => searchParams.append(key, item));
                    } else {
                        searchParams.append(key, value);
                    }
                }
            });
            
            return `?${searchParams.toString()}`;
        }
        
        /**
         * Generate unique request ID
         */
        generateRequestId() {
            return `req_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
        }
        
        /**
         * Delay utility for retries
         */
        delay(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        }
        
        // Caching methods
        
        /**
         * Get from cache
         */
        getFromCache(endpoint, config, allowStale = false) {
            const cacheKey = this.buildCacheKey(endpoint, config);
            const cached = this.cache.get(cacheKey);
            
            if (!cached) {
                return null;
            }
            
            const isExpired = Date.now() > cached.expires;
            
            if (isExpired && !allowStale) {
                this.cache.delete(cacheKey);
                return null;
            }
            
            return cached.data;
        }
        
        /**
         * Set cache
         */
        setCache(endpoint, config, data) {
            const cacheKey = this.buildCacheKey(endpoint, config);
            
            this.cache.set(cacheKey, {
                data: data,
                expires: Date.now() + this.options.cacheTimeout,
                created: Date.now()
            });
        }
        
        /**
         * Build cache key
         */
        buildCacheKey(endpoint, config) {
            const method = config.method || 'GET';
            const params = JSON.stringify(config.params || {});
            return `${method}:${endpoint}:${btoa(params)}`;
        }
        
        /**
         * Setup cache cleanup
         */
        setupCacheCleanup() {
            setInterval(() => {
                const now = Date.now();
                
                for (const [key, value] of this.cache.entries()) {
                    if (now > value.expires + 300000) { // 5 minutes grace period
                        this.cache.delete(key);
                    }
                }
            }, 600000); // Clean every 10 minutes
        }
        
        // Offline handling methods
        
        /**
         * Queue request for offline processing
         */
        queueOfflineRequest(endpoint, config) {
            return new Promise((resolve, reject) => {
                this.offlineQueue.push({
                    endpoint,
                    config,
                    resolve,
                    reject,
                    timestamp: Date.now()
                });
                
                console.log('API Client: Request queued for offline processing');
            });
        }
        
        /**
         * Process offline queue when online
         */
        async processOfflineQueue() {
            console.log(`API Client: Processing ${this.offlineQueue.length} offline requests`);
            
            const queue = [...this.offlineQueue];
            this.offlineQueue = [];
            
            for (const queuedRequest of queue) {
                try {
                    const result = await this.request(queuedRequest.endpoint, queuedRequest.config);
                    queuedRequest.resolve(result);
                } catch (error) {
                    queuedRequest.reject(error);
                }
            }
        }
        
        /**
         * Queue request with rate limiting
         */
        queueRequest(config) {
            return new Promise((resolve, reject) => {
                const delay = 5000; // 5 second delay for rate limited requests
                
                setTimeout(async () => {
                    try {
                        const result = await this.request(config.endpoint, config);
                        resolve(result);
                    } catch (error) {
                        reject(error);
                    }
                }, delay);
            });
        }
        
        // Analytics methods
        
        /**
         * Update analytics
         */
        updateAnalytics(type, data, config) {
            this.analytics.requests++;
            
            if (type === 'error') {
                this.analytics.errors++;
            }
            
            // Send analytics if enabled
            if (this.options.enableAnalytics && typeof data.responseTime === 'number') {
                this.trackAnalytics({
                    event_type: 'api_call',
                    endpoint: config.endpoint,
                    method: config.method,
                    response_time: data.responseTime,
                    status: type
                }).catch(error => {
                    console.warn('Failed to track API analytics:', error);
                });
            }
        }
        
        /**
         * Update average response time
         */
        updateResponseTime(responseTime) {
            const currentAvg = this.analytics.averageResponseTime;
            const totalRequests = this.analytics.requests;
            
            this.analytics.averageResponseTime = 
                ((currentAvg * (totalRequests - 1)) + responseTime) / totalRequests;
        }
        
        /**
         * Get analytics data
         */
        getAnalytics() {
            return {
                ...this.analytics,
                cacheSize: this.cache.size,
                offlineQueueSize: this.offlineQueue.length,
                isOnline: this.isOnline
            };
        }
        
        /**
         * Clear cache
         */
        clearCache() {
            this.cache.clear();
            console.log('API Client: Cache cleared');
        }
        
        /**
         * Reset analytics
         */
        resetAnalytics() {
            this.analytics = {
                requests: 0,
                errors: 0,
                cacheHits: 0,
                averageResponseTime: 0
            };
        }
    }
    
    // Create global instance
    const apiClient = new LiveTVAPIClient();
    
    // Export for use in other modules
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = LiveTVAPIClient;
    } else if (typeof window !== 'undefined') {
        window.LiveTVAPIClient = LiveTVAPIClient;
        window.liveTVAPI = apiClient;
    }
    
})();