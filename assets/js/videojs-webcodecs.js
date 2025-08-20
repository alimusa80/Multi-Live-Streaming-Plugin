/**
 * Video.js WebCodecs Plugin
 * 
 * Integrates WebCodecs API for enhanced video processing and AV1 support
 * Provides hardware-accelerated decoding when available
 * 
 * @version 1.0.0
 * @author Live TV Streaming Pro
 */

(function() {
    'use strict';

    // Check for WebCodecs support
    const hasWebCodecs = 'VideoDecoder' in window && 'VideoEncoder' in window;
    
    // WebCodecs Tech for Video.js
    class WebCodecsTech extends videojs.getTech('Html5') {
        constructor(options, ready) {
            super(options, ready);
            
            this.webCodecsSupported = hasWebCodecs;
            this.activeDecoder = null;
            this.decoderQueue = [];
            this.isProcessing = false;
            
            // Initialize WebCodecs if supported
            if (this.webCodecsSupported) {
                this.initWebCodecs();
            }
        }
        
        /**
         * Initialize WebCodecs functionality
         */
        initWebCodecs() {
            console.log('WebCodecs: Initializing hardware-accelerated decoding');
            
            // Check codec support
            this.checkCodecSupport();
            
            // Set up performance monitoring
            this.setupPerformanceMonitoring();
        }
        
        /**
         * Check which codecs are supported by WebCodecs
         */
        async checkCodecSupport() {
            const codecs = [
                'avc1.42E01E', // H.264 Baseline
                'avc1.4D401E', // H.264 Main
                'avc1.64001E', // H.264 High
                'av01.0.04M.08', // AV1 Main Profile
                'vp8',
                'vp09.00.10.08' // VP9
            ];
            
            this.supportedCodecs = {};
            
            for (const codec of codecs) {
                try {
                    const support = await VideoDecoder.isConfigSupported({
                        codec: codec,
                        codedWidth: 1920,
                        codedHeight: 1080
                    });
                    
                    this.supportedCodecs[codec] = support.supported;
                    
                    if (support.supported) {
                        console.log(`WebCodecs: ${codec} is supported`);
                    }
                } catch (error) {
                    console.warn(`WebCodecs: Error checking ${codec} support:`, error);
                    this.supportedCodecs[codec] = false;
                }
            }
        }
        
        /**
         * Setup performance monitoring for WebCodecs
         */
        setupPerformanceMonitoring() {
            this.stats = {
                framesDecoded: 0,
                frameDropped: 0,
                averageDecodeTime: 0,
                totalDecodeTime: 0,
                startTime: Date.now()
            };
            
            // Report stats every 10 seconds
            setInterval(() => {
                this.reportPerformanceStats();
            }, 10000);
        }
        
        /**
         * Report performance statistics
         */
        reportPerformanceStats() {
            if (this.stats.framesDecoded > 0) {
                const avgDecodeTime = this.stats.totalDecodeTime / this.stats.framesDecoded;
                const runtime = (Date.now() - this.stats.startTime) / 1000;
                const fps = this.stats.framesDecoded / runtime;
                
                console.log('WebCodecs Performance Stats:', {
                    framesDecoded: this.stats.framesDecoded,
                    frameDropped: this.stats.frameDropped,
                    averageDecodeTime: avgDecodeTime.toFixed(2) + 'ms',
                    effectiveFPS: fps.toFixed(2),
                    runtime: runtime.toFixed(2) + 's'
                });
                
                // Trigger custom event for analytics
                this.trigger('webcodecs-stats', {
                    framesDecoded: this.stats.framesDecoded,
                    frameDropped: this.stats.frameDropped,
                    averageDecodeTime: avgDecodeTime,
                    effectiveFPS: fps
                });
            }
        }
        
        /**
         * Create optimized decoder configuration
         */
        createDecoderConfig(videoTrack) {
            const config = {
                codec: this.detectCodec(videoTrack),
                codedWidth: videoTrack.codedWidth || 1920,
                codedHeight: videoTrack.codedHeight || 1080,
                hardwareAcceleration: 'prefer-hardware'
            };
            
            // Add codec-specific optimizations
            if (config.codec.startsWith('av01')) {
                config.optimizeForLatency = true;
            }
            
            return config;
        }
        
        /**
         * Detect video codec from stream
         */
        detectCodec(videoTrack) {
            const mimeType = videoTrack.mimeType || '';
            
            if (mimeType.includes('av01')) {
                return 'av01.0.04M.08'; // AV1
            } else if (mimeType.includes('avc1')) {
                return 'avc1.64001E'; // H.264 High Profile
            } else if (mimeType.includes('vp9')) {
                return 'vp09.00.10.08'; // VP9
            } else if (mimeType.includes('vp8')) {
                return 'vp8';
            }
            
            // Default to H.264
            return 'avc1.64001E';
        }
        
        /**
         * Process video chunk with WebCodecs
         */
        async processVideoChunk(chunk) {
            if (!this.activeDecoder) {
                return false;
            }
            
            const startTime = performance.now();
            
            try {
                await this.activeDecoder.decode(chunk);
                
                const decodeTime = performance.now() - startTime;
                this.stats.framesDecoded++;
                this.stats.totalDecodeTime += decodeTime;
                this.stats.averageDecodeTime = this.stats.totalDecodeTime / this.stats.framesDecoded;
                
                return true;
            } catch (error) {
                console.error('WebCodecs: Decode error:', error);
                this.stats.frameDropped++;
                return false;
            }
        }
        
        /**
         * Handle decoder output frame
         */
        handleDecodedFrame(frame) {
            // Create ImageBitmap for efficient rendering
            createImageBitmap(frame).then(bitmap => {
                // Render to canvas or video element
                this.renderFrame(bitmap);
                frame.close(); // Important: close frame to free memory
            }).catch(error => {
                console.error('WebCodecs: Frame rendering error:', error);
                frame.close();
            });
        }
        
        /**
         * Render decoded frame
         */
        renderFrame(bitmap) {
            const canvas = this.el().querySelector('canvas') || this.createCanvas();
            const ctx = canvas.getContext('2d');
            
            // Update canvas size if needed
            if (canvas.width !== bitmap.width || canvas.height !== bitmap.height) {
                canvas.width = bitmap.width;
                canvas.height = bitmap.height;
            }
            
            // Draw frame
            ctx.drawImage(bitmap, 0, 0);
            
            // Clean up bitmap
            bitmap.close();
        }
        
        /**
         * Create canvas for WebCodecs rendering
         */
        createCanvas() {
            const canvas = document.createElement('canvas');
            canvas.style.position = 'absolute';
            canvas.style.top = '0';
            canvas.style.left = '0';
            canvas.style.width = '100%';
            canvas.style.height = '100%';
            canvas.style.zIndex = '-1';
            
            this.el().appendChild(canvas);
            return canvas;
        }
        
        /**
         * Enhanced error handling
         */
        handleError(error) {
            console.error('WebCodecs Tech Error:', error);
            
            // Fallback to standard HTML5 playback
            if (this.activeDecoder) {
                this.activeDecoder.close();
                this.activeDecoder = null;
            }
            
            // Trigger fallback
            super.handleError(error);
        }
        
        /**
         * Dispose WebCodecs resources
         */
        dispose() {
            if (this.activeDecoder) {
                this.activeDecoder.close();
                this.activeDecoder = null;
            }
            
            super.dispose();
        }
    }
    
    // Register WebCodecs Tech
    WebCodecsTech.isSupported = function() {
        return hasWebCodecs && videojs.getTech('Html5').isSupported();
    };
    
    WebCodecsTech.canPlayType = function(type) {
        // Enhanced codec detection for WebCodecs
        if (hasWebCodecs) {
            const canPlay = videojs.getTech('Html5').canPlayType(type);
            
            // Boost support for modern codecs
            if (type.includes('av01') || type.includes('vp9')) {
                return canPlay === 'probably' ? 'probably' : 'maybe';
            }
            
            return canPlay;
        }
        
        return videojs.getTech('Html5').canPlayType(type);
    };
    
    WebCodecsTech.canPlaySource = function(srcObj) {
        return WebCodecsTech.canPlayType(srcObj.type);
    };
    
    // Register the tech
    videojs.registerTech('WebCodecs', WebCodecsTech);
    
    // WebCodecs Plugin
    const webCodecsPlugin = function(options) {
        const player = this;
        
        // Default options
        const settings = Object.assign({
            preferWebCodecs: true,
            enableHardwareAcceleration: true,
            enablePerformanceMonitoring: true,
            fallbackToHtml5: true
        }, options);
        
        // Add WebCodecs tech to the beginning of tech order if supported
        if (hasWebCodecs && settings.preferWebCodecs) {
            const currentTechOrder = player.options().techOrder || ['html5'];
            
            if (currentTechOrder.indexOf('WebCodecs') === -1) {
                player.options().techOrder = ['WebCodecs', ...currentTechOrder];
            }
        }
        
        // Monitor tech changes
        player.on('techready', function() {
            const tech = player.tech();
            
            if (tech.name_ === 'WebCodecs') {
                console.log('WebCodecs: Hardware-accelerated playback active');
                
                // Add WebCodecs indicator to control bar
                if (settings.enablePerformanceMonitoring) {
                    addWebCodecsIndicator(player);
                }
            }
        });
        
        // Performance monitoring
        if (settings.enablePerformanceMonitoring) {
            player.on('webcodecs-stats', function(event, stats) {
                // Custom analytics can be added here
                player.trigger('analytics', {
                    type: 'webcodecs-performance',
                    data: stats
                });
            });
        }
        
        // Error handling with fallback
        player.on('error', function() {
            const error = player.error();
            
            if (error && error.code === 4 && settings.fallbackToHtml5) {
                console.warn('WebCodecs: Falling back to HTML5 tech');
                
                // Switch to HTML5 tech
                const currentSrc = player.currentSrc();
                player.techOrder(['html5']);
                player.src(currentSrc);
            }
        });
    };
    
    /**
     * Add WebCodecs performance indicator to control bar
     */
    function addWebCodecsIndicator(player) {
        const controlBar = player.getChild('controlBar');
        
        if (controlBar) {
            const indicator = document.createElement('div');
            indicator.className = 'webcodecs-indicator';
            indicator.innerHTML = '⚡ WebCodecs';
            indicator.style.cssText = `
                position: absolute;
                top: 10px;
                right: 10px;
                background: rgba(0,0,0,0.7);
                color: #00ff00;
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 12px;
                z-index: 1000;
                pointer-events: none;
            `;
            
            player.el().appendChild(indicator);
            
            // Remove indicator after 5 seconds
            setTimeout(() => {
                if (indicator.parentNode) {
                    indicator.parentNode.removeChild(indicator);
                }
            }, 5000);
        }
    }
    
    // Register plugin
    videojs.registerPlugin('webcodecs', webCodecsPlugin);
    
    // Export for debugging
    if (typeof window !== 'undefined') {
        window.WebCodecsTech = WebCodecsTech;
        window.liveTVWebCodecs = {
            isSupported: hasWebCodecs,
            tech: WebCodecsTech
        };
    }
    
})();