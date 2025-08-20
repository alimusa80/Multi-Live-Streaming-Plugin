/**
 * AV1 Codec Support Enhancement
 * 
 * Provides comprehensive AV1 codec support with fallback mechanisms
 * Includes hardware acceleration detection and optimization
 * 
 * @version 1.0.0
 * @author Live TV Streaming Pro
 */

(function() {
    'use strict';

    class AV1CodecSupport {
        constructor(options = {}) {
            this.options = Object.assign({
                enableHardwareAcceleration: true,
                fallbackCodecs: ['avc1.64001E', 'vp09.00.10.08', 'vp8'],
                preferredProfile: 'main',
                qualityLevels: ['1080p', '720p', '480p', '360p'],
                adaptiveStreaming: true,
                enableWebCodecs: true
            }, options);
            
            this.supported = false;
            this.hardwareAccelerated = false;
            this.supportedProfiles = [];
            this.capabilities = {};
            
            this.init();
        }
        
        /**
         * Initialize AV1 codec support detection
         */
        async init() {
            console.log('AV1: Initializing codec support detection');
            
            // Detect basic AV1 support
            await this.detectAV1Support();
            
            // Check hardware acceleration
            if (this.supported) {
                await this.checkHardwareAcceleration();
                await this.detectSupportedProfiles();
                await this.benchmarkPerformance();
            }
            
            console.log('AV1 Support Summary:', {
                supported: this.supported,
                hardwareAccelerated: this.hardwareAccelerated,
                profiles: this.supportedProfiles,
                capabilities: this.capabilities
            });
        }
        
        /**
         * Detect basic AV1 support across different APIs
         */
        async detectAV1Support() {
            // Test various AV1 codec strings
            const av1Codecs = [
                'video/mp4; codecs="av01.0.04M.08"',      // Main Profile, Level 4.0
                'video/mp4; codecs="av01.0.05M.08"',      // Main Profile, Level 5.0
                'video/mp4; codecs="av01.0.08M.08"',      // Main Profile, Level 8.0
                'video/webm; codecs="av01.0.04M.08"',     // WebM container
                'video/webm; codecs="av01.0.05M.08"'
            ];
            
            // Method 1: HTML5 Video Element Support
            const video = document.createElement('video');
            this.htmlSupported = av1Codecs.some(codec => {
                const support = video.canPlayType(codec);
                return support === 'probably' || support === 'maybe';
            });
            
            // Method 2: MediaSource Extensions Support
            this.mseSupported = false;
            if ('MediaSource' in window) {
                this.mseSupported = av1Codecs.some(codec => 
                    MediaSource.isTypeSupported(codec)
                );
            }
            
            // Method 3: WebCodecs API Support
            this.webCodecsSupported = false;
            if (this.options.enableWebCodecs && 'VideoDecoder' in window) {
                try {
                    const config = {
                        codec: 'av01.0.04M.08',
                        codedWidth: 1920,
                        codedHeight: 1080
                    };
                    
                    const support = await VideoDecoder.isConfigSupported(config);
                    this.webCodecsSupported = support.supported;
                } catch (error) {
                    console.warn('AV1: WebCodecs check failed:', error);
                }
            }
            
            // Overall support determination
            this.supported = this.htmlSupported || this.mseSupported || this.webCodecsSupported;
            
            console.log('AV1 Detection Results:', {
                html: this.htmlSupported,
                mse: this.mseSupported,
                webcodecs: this.webCodecsSupported,
                overall: this.supported
            });
        }
        
        /**
         * Check for hardware acceleration support
         */
        async checkHardwareAcceleration() {
            if (!this.options.enableHardwareAcceleration || !('VideoDecoder' in window)) {
                return;
            }
            
            try {
                // Test hardware acceleration for different resolutions
                const testConfigs = [
                    { width: 1920, height: 1080, profile: 'main' },
                    { width: 3840, height: 2160, profile: 'main' }, // 4K
                    { width: 7680, height: 4320, profile: 'main' }  // 8K
                ];
                
                for (const config of testConfigs) {
                    const decoderConfig = {
                        codec: 'av01.0.04M.08',
                        codedWidth: config.width,
                        codedHeight: config.height,
                        hardwareAcceleration: 'prefer-hardware'
                    };
                    
                    try {
                        const support = await VideoDecoder.isConfigSupported(decoderConfig);
                        
                        if (support.supported && support.config.hardwareAcceleration === 'prefer-hardware') {
                            this.hardwareAccelerated = true;
                            this.capabilities[`${config.width}x${config.height}`] = {
                                supported: true,
                                hardware: true
                            };
                        }
                    } catch (error) {
                        console.warn(`AV1: Hardware check failed for ${config.width}x${config.height}:`, error);
                    }
                }
                
                console.log('AV1 Hardware Acceleration:', this.hardwareAccelerated ? 'Supported' : 'Not supported');
                
            } catch (error) {
                console.error('AV1: Hardware acceleration check failed:', error);
            }
        }
        
        /**
         * Detect supported AV1 profiles and levels
         */
        async detectSupportedProfiles() {
            const profiles = [
                { name: 'main', codec: 'av01.0.04M.08' },      // Main Profile, Level 4.0
                { name: 'high', codec: 'av01.1.04M.08' },      // High Profile, Level 4.0
                { name: 'professional', codec: 'av01.2.04M.08' } // Professional Profile
            ];
            
            for (const profile of profiles) {
                let supported = false;
                
                // Check HTML5 support
                const video = document.createElement('video');
                const htmlSupport = video.canPlayType(`video/mp4; codecs="${profile.codec}"`);
                
                if (htmlSupport === 'probably' || htmlSupport === 'maybe') {
                    supported = true;
                }
                
                // Check WebCodecs support if available
                if ('VideoDecoder' in window && !supported) {
                    try {
                        const config = {
                            codec: profile.codec,
                            codedWidth: 1920,
                            codedHeight: 1080
                        };
                        
                        const webCodecsSupport = await VideoDecoder.isConfigSupported(config);
                        supported = webCodecsSupport.supported;
                    } catch (error) {
                        // Profile not supported
                    }
                }
                
                if (supported) {
                    this.supportedProfiles.push(profile.name);
                }
            }
            
            console.log('AV1 Supported Profiles:', this.supportedProfiles);
        }
        
        /**
         * Benchmark AV1 decoding performance
         */
        async benchmarkPerformance() {
            if (!('VideoDecoder' in window)) {
                console.log('AV1: Performance benchmarking requires WebCodecs API');
                return;
            }
            
            try {
                const decoder = new VideoDecoder({
                    output: (frame) => {
                        const endTime = performance.now();
                        const decodeTime = endTime - this.benchmarkStartTime;
                        
                        this.capabilities.averageDecodeTime = decodeTime;
                        this.capabilities.canDecode4K = decodeTime < 16.67; // 60fps threshold
                        this.capabilities.canDecodeHD = decodeTime < 33.33; // 30fps threshold
                        
                        frame.close();
                        decoder.close();
                    },
                    error: (error) => {
                        console.warn('AV1: Benchmark decode error:', error);
                        decoder.close();
                    }
                });
                
                const config = {
                    codec: 'av01.0.04M.08',
                    codedWidth: 1920,
                    codedHeight: 1080,
                    hardwareAcceleration: this.hardwareAccelerated ? 'prefer-hardware' : 'prefer-software'
                };
                
                decoder.configure(config);
                
                // Create a minimal test frame (this would need actual AV1 bitstream data)
                // For now, we'll simulate the timing
                this.benchmarkStartTime = performance.now();
                
                console.log('AV1: Performance benchmarking initiated');
                
            } catch (error) {
                console.error('AV1: Performance benchmark failed:', error);
            }
        }
        
        /**
         * Get optimal AV1 codec string for current device
         */
        getOptimalCodec(width = 1920, height = 1080) {
            if (!this.supported) {
                return this.getFallbackCodec();
            }
            
            // Select profile based on capabilities
            let profile = '0'; // Main profile
            if (this.supportedProfiles.includes('high') && this.hardwareAccelerated) {
                profile = '1'; // High profile for better compression
            }
            
            // Select level based on resolution
            let level = '04M'; // Level 4.0 (1080p)
            if (width >= 3840 || height >= 2160) {
                level = '05M'; // Level 5.0 (4K)
            } else if (width >= 7680 || height >= 4320) {
                level = '06M'; // Level 6.0 (8K)
            }
            
            const codec = `av01.${profile}.${level}.08`;
            console.log(`AV1: Selected codec: ${codec} for ${width}x${height}`);
            
            return codec;
        }
        
        /**
         * Get fallback codec when AV1 is not supported
         */
        getFallbackCodec() {
            for (const codec of this.options.fallbackCodecs) {
                const video = document.createElement('video');
                const support = video.canPlayType(`video/mp4; codecs="${codec}"`);
                
                if (support === 'probably' || support === 'maybe') {
                    console.log(`AV1: Using fallback codec: ${codec}`);
                    return codec;
                }
            }
            
            // Last resort
            return 'avc1.42E01E'; // H.264 Baseline
        }
        
        /**
         * Create optimized source configuration for Video.js
         */
        createSourceConfig(streamUrl, width, height) {
            const config = {
                src: streamUrl,
                type: 'application/x-mpegURL' // HLS by default
            };
            
            if (this.supported) {
                // Add AV1-specific configurations
                config.av1Supported = true;
                config.preferredCodec = this.getOptimalCodec(width, height);
                config.hardwareAccelerated = this.hardwareAccelerated;
            } else {
                config.fallbackCodec = this.getFallbackCodec();
            }
            
            return config;
        }
        
        /**
         * Enhance HLS playlist for AV1 support
         */
        enhanceHLSPlaylist(playlistUrl) {
            if (!this.supported) {
                return playlistUrl;
            }
            
            // Add AV1 preference parameters
            const url = new URL(playlistUrl);
            url.searchParams.set('codec', 'av1');
            
            if (this.hardwareAccelerated) {
                url.searchParams.set('hw_accel', '1');
            }
            
            // Request appropriate quality levels
            const maxResolution = this.getMaxSupportedResolution();
            if (maxResolution) {
                url.searchParams.set('max_res', maxResolution);
            }
            
            return url.toString();
        }
        
        /**
         * Get maximum supported resolution
         */
        getMaxSupportedResolution() {
            if (this.capabilities['7680x4320'] && this.capabilities['7680x4320'].supported) {
                return '8k';
            } else if (this.capabilities['3840x2160'] && this.capabilities['3840x2160'].supported) {
                return '4k';
            } else if (this.capabilities['1920x1080']) {
                return '1080p';
            }
            
            return '720p';
        }
        
        /**
         * Monitor AV1 decoding performance in real-time
         */
        startPerformanceMonitoring(videoElement) {
            if (!videoElement || !this.supported) {
                return;
            }
            
            this.performanceMonitor = {
                frames: 0,
                droppedFrames: 0,
                startTime: Date.now()
            };
            
            // Use requestVideoFrameCallback if available
            if ('requestVideoFrameCallback' in videoElement) {
                const frameCallback = (now, metadata) => {
                    this.performanceMonitor.frames++;
                    
                    if (metadata.droppedVideoFrames !== undefined) {
                        this.performanceMonitor.droppedFrames = metadata.droppedVideoFrames;
                    }
                    
                    videoElement.requestVideoFrameCallback(frameCallback);
                };
                
                videoElement.requestVideoFrameCallback(frameCallback);
            }
            
            // Report performance every 5 seconds
            setInterval(() => {
                this.reportPerformance();
            }, 5000);
        }
        
        /**
         * Report AV1 performance metrics
         */
        reportPerformance() {
            if (!this.performanceMonitor) return;
            
            const runtime = (Date.now() - this.performanceMonitor.startTime) / 1000;
            const fps = this.performanceMonitor.frames / runtime;
            const dropRate = this.performanceMonitor.droppedFrames / this.performanceMonitor.frames;
            
            const metrics = {
                codec: 'AV1',
                fps: Math.round(fps),
                droppedFrameRate: Math.round(dropRate * 100) + '%',
                hardwareAccelerated: this.hardwareAccelerated,
                totalFrames: this.performanceMonitor.frames,
                runtime: Math.round(runtime) + 's'
            };
            
            console.log('AV1 Performance Metrics:', metrics);
            
            // Dispatch custom event for analytics
            if (typeof window !== 'undefined') {
                window.dispatchEvent(new CustomEvent('av1-performance', {
                    detail: metrics
                }));
            }
        }
        
        /**
         * Get comprehensive capability report
         */
        getCapabilityReport() {
            return {
                supported: this.supported,
                hardwareAccelerated: this.hardwareAccelerated,
                supportedProfiles: this.supportedProfiles,
                capabilities: this.capabilities,
                htmlSupported: this.htmlSupported,
                mseSupported: this.mseSupported,
                webCodecsSupported: this.webCodecsSupported,
                maxResolution: this.getMaxSupportedResolution(),
                optimalCodec: this.getOptimalCodec(),
                fallbackCodec: this.getFallbackCodec()
            };
        }
    }
    
    // Video.js plugin for AV1 support
    const av1Plugin = function(options) {
        const player = this;
        const av1Support = new AV1CodecSupport(options);
        
        // Wait for initialization
        av1Support.init().then(() => {
            // Add AV1 indicator if supported
            if (av1Support.supported) {
                addAV1Indicator(player, av1Support.hardwareAccelerated);
            }
            
            // Enhance source selection
            const originalSrc = player.src;
            player.src = function(source) {
                if (source && av1Support.supported) {
                    // Enhance source for AV1 if it's an HLS stream
                    if (typeof source === 'string' && source.includes('.m3u8')) {
                        source = av1Support.enhanceHLSPlaylist(source);
                    } else if (typeof source === 'object' && source.src && source.src.includes('.m3u8')) {
                        source = av1Support.createSourceConfig(source.src, 1920, 1080);
                    }
                }
                
                return originalSrc.call(this, source);
            };
            
            // Start performance monitoring
            player.ready(() => {
                const videoEl = player.el().querySelector('video');
                if (videoEl) {
                    av1Support.startPerformanceMonitoring(videoEl);
                }
            });
        });
        
        // Expose AV1 support info
        player.av1Support = () => av1Support.getCapabilityReport();
    };
    
    /**
     * Add AV1 support indicator to player
     */
    function addAV1Indicator(player, hardwareAccelerated) {
        player.ready(() => {
            const indicator = document.createElement('div');
            indicator.className = 'av1-indicator';
            indicator.innerHTML = hardwareAccelerated ? '⚡ AV1 HW' : '🎥 AV1';
            indicator.style.cssText = `
                position: absolute;
                top: 10px;
                left: 10px;
                background: rgba(0,0,0,0.8);
                color: ${hardwareAccelerated ? '#00ff00' : '#ffff00'};
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 12px;
                z-index: 1000;
                pointer-events: none;
                font-weight: bold;
            `;
            
            player.el().appendChild(indicator);
            
            // Remove after 5 seconds
            setTimeout(() => {
                if (indicator.parentNode) {
                    indicator.parentNode.removeChild(indicator);
                }
            }, 5000);
        });
    }
    
    // Register Video.js plugin
    if (typeof videojs !== 'undefined') {
        videojs.registerPlugin('av1Support', av1Plugin);
    }
    
    // Export for standalone use
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = AV1CodecSupport;
    } else if (typeof window !== 'undefined') {
        window.AV1CodecSupport = AV1CodecSupport;
    }
    
})();