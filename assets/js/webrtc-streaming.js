/**
 * WebRTC Live Streaming Implementation
 * 
 * Provides ultra-low latency streaming using WebRTC technology
 * Supports peer-to-peer and server-based streaming architectures
 * 
 * @version 1.0.0
 * @author Live TV Streaming Pro
 */

(function() {
    'use strict';

    class WebRTCStreaming {
        constructor(options = {}) {
            this.options = Object.assign({
                iceServers: [
                    { urls: 'stun:stun.l.google.com:19302' },
                    { urls: 'stun:stun1.l.google.com:19302' }
                ],
                enableAudio: true,
                enableVideo: true,
                videoCodec: 'av01', // Prefer AV1 for efficiency
                audioCodec: 'opus',
                maxBitrate: 5000000, // 5 Mbps max
                minBitrate: 500000,  // 500 Kbps min
                adaptiveBitrate: true,
                reconnectAttempts: 5,
                reconnectDelay: 1000,
                enableDataChannel: true
            }, options);
            
            this.peerConnection = null;
            this.dataChannel = null;
            this.localStream = null;
            this.remoteStream = null;
            this.isConnected = false;
            this.reconnectCount = 0;
            this.stats = {
                bytesReceived: 0,
                packetsLost: 0,
                jitter: 0,
                latency: 0,
                bitrate: 0
            };
            
            this.eventListeners = new Map();
            this.setupEventHandlers();
        }
        
        /**
         * Setup event handling system
         */
        setupEventHandlers() {
            this.emit = (event, data) => {
                const listeners = this.eventListeners.get(event) || [];
                listeners.forEach(listener => listener(data));
            };
            
            this.on = (event, listener) => {
                if (!this.eventListeners.has(event)) {
                    this.eventListeners.set(event, []);
                }
                this.eventListeners.get(event).push(listener);
            };
            
            this.off = (event, listener) => {
                const listeners = this.eventListeners.get(event) || [];
                const index = listeners.indexOf(listener);
                if (index > -1) {
                    listeners.splice(index, 1);
                }
            };
        }
        
        /**
         * Initialize WebRTC peer connection
         */
        async initializePeerConnection() {
            try {
                // Create peer connection with optimal configuration
                this.peerConnection = new RTCPeerConnection({
                    iceServers: this.options.iceServers,
                    iceCandidatePoolSize: 10,
                    bundlePolicy: 'balanced',
                    rtcpMuxPolicy: 'require',
                    iceTransportPolicy: 'all'
                });
                
                // Setup event listeners
                this.setupPeerConnectionHandlers();
                
                // Create data channel for metadata and control
                if (this.options.enableDataChannel) {
                    this.createDataChannel();
                }
                
                console.log('WebRTC: Peer connection initialized');
                this.emit('initialized', { connection: this.peerConnection });
                
                return true;
                
            } catch (error) {
                console.error('WebRTC: Failed to initialize peer connection:', error);
                this.emit('error', { type: 'initialization', error });
                return false;
            }
        }
        
        /**
         * Setup peer connection event handlers
         */
        setupPeerConnectionHandlers() {
            // ICE candidate handling
            this.peerConnection.onicecandidate = (event) => {
                if (event.candidate) {
                    this.emit('icecandidate', { candidate: event.candidate });
                } else {
                    console.log('WebRTC: ICE gathering complete');
                }
            };
            
            // Connection state changes
            this.peerConnection.onconnectionstatechange = () => {
                const state = this.peerConnection.connectionState;
                console.log('WebRTC: Connection state:', state);
                
                this.isConnected = state === 'connected';
                this.emit('connectionstatechange', { state });
                
                if (state === 'failed' || state === 'disconnected') {
                    this.handleConnectionFailure();
                }
            };
            
            // ICE connection state changes
            this.peerConnection.oniceconnectionstatechange = () => {
                const state = this.peerConnection.iceConnectionState;
                console.log('WebRTC: ICE connection state:', state);
                this.emit('iceconnectionstatechange', { state });
            };
            
            // Remote stream handling
            this.peerConnection.ontrack = (event) => {
                console.log('WebRTC: Remote track received');
                this.remoteStream = event.streams[0];
                this.emit('remotestream', { stream: this.remoteStream });
                
                // Start statistics monitoring
                this.startStatsMonitoring();
            };
            
            // Data channel from remote peer
            this.peerConnection.ondatachannel = (event) => {
                const dataChannel = event.channel;
                this.setupDataChannelHandlers(dataChannel);
            };
        }
        
        /**
         * Create data channel for control and metadata
         */
        createDataChannel() {
            this.dataChannel = this.peerConnection.createDataChannel('control', {
                ordered: true,
                maxRetransmits: 3
            });
            
            this.setupDataChannelHandlers(this.dataChannel);
        }
        
        /**
         * Setup data channel event handlers
         */
        setupDataChannelHandlers(dataChannel) {
            dataChannel.onopen = () => {
                console.log('WebRTC: Data channel opened');
                this.emit('datachannel-open', { channel: dataChannel });
            };
            
            dataChannel.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data);
                    this.handleDataChannelMessage(data);
                } catch (error) {
                    console.warn('WebRTC: Invalid data channel message:', event.data);
                }
            };
            
            dataChannel.onerror = (error) => {
                console.error('WebRTC: Data channel error:', error);
            };
            
            dataChannel.onclose = () => {
                console.log('WebRTC: Data channel closed');
            };
        }
        
        /**
         * Handle data channel messages
         */
        handleDataChannelMessage(data) {
            switch (data.type) {
                case 'channel-info':
                    this.emit('channel-info', data.payload);
                    break;
                case 'quality-change':
                    this.handleQualityChange(data.payload);
                    break;
                case 'latency-measure':
                    this.handleLatencyMeasurement(data.payload);
                    break;
                default:
                    console.log('WebRTC: Unknown data channel message:', data);
            }
        }
        
        /**
         * Start streaming from signaling server
         */
        async startStreaming(signalingUrl, channelId) {
            try {
                if (!this.peerConnection) {
                    await this.initializePeerConnection();
                }
                
                // Connect to signaling server
                this.signalingSocket = new WebSocket(signalingUrl);
                
                this.signalingSocket.onopen = () => {
                    console.log('WebRTC: Connected to signaling server');
                    
                    // Request to join channel
                    this.sendSignalingMessage({
                        type: 'join-channel',
                        channelId: channelId
                    });
                };
                
                this.signalingSocket.onmessage = (event) => {
                    this.handleSignalingMessage(JSON.parse(event.data));
                };
                
                this.signalingSocket.onerror = (error) => {
                    console.error('WebRTC: Signaling error:', error);
                    this.emit('error', { type: 'signaling', error });
                };
                
                this.signalingSocket.onclose = () => {
                    console.log('WebRTC: Signaling connection closed');
                    this.handleConnectionFailure();
                };
                
                return true;
                
            } catch (error) {
                console.error('WebRTC: Failed to start streaming:', error);
                this.emit('error', { type: 'streaming', error });
                return false;
            }
        }
        
        /**
         * Handle signaling messages
         */
        async handleSignalingMessage(message) {
            try {
                switch (message.type) {
                    case 'offer':
                        await this.handleOffer(message.offer);
                        break;
                    case 'answer':
                        await this.handleAnswer(message.answer);
                        break;
                    case 'ice-candidate':
                        await this.handleIceCandidate(message.candidate);
                        break;
                    case 'channel-joined':
                        console.log('WebRTC: Successfully joined channel');
                        this.emit('channel-joined', message);
                        break;
                    case 'error':
                        console.error('WebRTC: Signaling error:', message.error);
                        this.emit('error', { type: 'signaling', error: message.error });
                        break;
                    default:
                        console.log('WebRTC: Unknown signaling message:', message);
                }
            } catch (error) {
                console.error('WebRTC: Error handling signaling message:', error);
            }
        }
        
        /**
         * Handle incoming offer
         */
        async handleOffer(offer) {
            await this.peerConnection.setRemoteDescription(offer);
            
            // Configure codecs for optimal performance
            const answer = await this.peerConnection.createAnswer();
            await this.optimizeCodecs(answer);
            
            await this.peerConnection.setLocalDescription(answer);
            
            this.sendSignalingMessage({
                type: 'answer',
                answer: answer
            });
        }
        
        /**
         * Handle incoming answer
         */
        async handleAnswer(answer) {
            await this.peerConnection.setRemoteDescription(answer);
        }
        
        /**
         * Handle ICE candidate
         */
        async handleIceCandidate(candidate) {
            await this.peerConnection.addIceCandidate(candidate);
        }
        
        /**
         * Optimize codec configuration
         */
        async optimizeCodecs(sessionDescription) {
            if (this.options.videoCodec === 'av01') {
                // Prefer AV1 codec for better compression
                sessionDescription.sdp = sessionDescription.sdp.replace(
                    /(a=rtpmap:\d+ AV1.*\r\n)/g,
                    '$1a=fmtp:$1 profile-id=0;level-idx=8;tier=0\r\n'
                );
            }
            
            if (this.options.audioCodec === 'opus') {
                // Optimize Opus for low latency
                sessionDescription.sdp = sessionDescription.sdp.replace(
                    /(a=rtpmap:\d+ opus.*\r\n)/g,
                    '$1a=fmtp:$1 minptime=10;useinbandfec=1\r\n'
                );
            }
            
            // Set bitrate constraints
            if (this.options.adaptiveBitrate) {
                sessionDescription.sdp = this.setBitrateConstraints(sessionDescription.sdp);
            }
        }
        
        /**
         * Set bitrate constraints in SDP
         */
        setBitrateConstraints(sdp) {
            const videoLines = sdp.split('\r\n').filter(line => line.includes('video'));
            
            videoLines.forEach((line, index) => {
                if (line.includes('a=mid:video')) {
                    const insertPoint = sdp.indexOf(line) + line.length;
                    const bitrateAttribute = `\r\nb=AS:${Math.floor(this.options.maxBitrate / 1000)}`;
                    sdp = sdp.slice(0, insertPoint) + bitrateAttribute + sdp.slice(insertPoint);
                }
            });
            
            return sdp;
        }
        
        /**
         * Send message through signaling channel
         */
        sendSignalingMessage(message) {
            if (this.signalingSocket && this.signalingSocket.readyState === WebSocket.OPEN) {
                this.signalingSocket.send(JSON.stringify(message));
            }
        }
        
        /**
         * Start monitoring connection statistics
         */
        startStatsMonitoring() {
            if (this.statsInterval) {
                clearInterval(this.statsInterval);
            }
            
            this.statsInterval = setInterval(async () => {
                if (this.peerConnection && this.isConnected) {
                    await this.updateStats();
                }
            }, 1000);
        }
        
        /**
         * Update connection statistics
         */
        async updateStats() {
            try {
                const stats = await this.peerConnection.getStats();
                
                stats.forEach(report => {
                    if (report.type === 'inbound-rtp' && report.mediaType === 'video') {
                        this.stats.bytesReceived = report.bytesReceived;
                        this.stats.packetsLost = report.packetsLost;
                        this.stats.jitter = report.jitter;
                        
                        // Calculate bitrate
                        if (this.previousStats) {
                            const timeDiff = report.timestamp - this.previousStats.timestamp;
                            const bytesDiff = report.bytesReceived - this.previousStats.bytesReceived;
                            this.stats.bitrate = Math.round((bytesDiff * 8) / (timeDiff / 1000));
                        }
                        
                        this.previousStats = {
                            timestamp: report.timestamp,
                            bytesReceived: report.bytesReceived
                        };
                    }
                });
                
                // Emit stats for monitoring
                this.emit('stats-update', this.stats);
                
                // Adaptive bitrate adjustment
                if (this.options.adaptiveBitrate) {
                    this.adjustBitrate();
                }
                
            } catch (error) {
                console.warn('WebRTC: Error updating stats:', error);
            }
        }
        
        /**
         * Adjust bitrate based on network conditions
         */
        adjustBitrate() {
            const packetLossRate = this.stats.packetsLost / (this.stats.packetsLost + 100);
            
            if (packetLossRate > 0.05) { // More than 5% packet loss
                this.reduceQuality();
            } else if (packetLossRate < 0.01 && this.stats.bitrate < this.options.maxBitrate) {
                this.increaseQuality();
            }
        }
        
        /**
         * Reduce streaming quality
         */
        reduceQuality() {
            if (this.dataChannel && this.dataChannel.readyState === 'open') {
                this.dataChannel.send(JSON.stringify({
                    type: 'quality-change',
                    payload: { action: 'reduce' }
                }));
            }
        }
        
        /**
         * Increase streaming quality
         */
        increaseQuality() {
            if (this.dataChannel && this.dataChannel.readyState === 'open') {
                this.dataChannel.send(JSON.stringify({
                    type: 'quality-change',
                    payload: { action: 'increase' }
                }));
            }
        }
        
        /**
         * Handle connection failure and attempt reconnection
         */
        handleConnectionFailure() {
            if (this.reconnectCount < this.options.reconnectAttempts) {
                console.log(`WebRTC: Attempting reconnection ${this.reconnectCount + 1}/${this.options.reconnectAttempts}`);
                
                setTimeout(() => {
                    this.reconnectCount++;
                    this.reconnect();
                }, this.options.reconnectDelay * Math.pow(2, this.reconnectCount));
            } else {
                console.error('WebRTC: Max reconnection attempts reached');
                this.emit('connection-failed', { attempts: this.reconnectCount });
            }
        }
        
        /**
         * Reconnect to the stream
         */
        async reconnect() {
            try {
                this.dispose();
                await this.initializePeerConnection();
                this.emit('reconnecting', { attempt: this.reconnectCount });
            } catch (error) {
                console.error('WebRTC: Reconnection failed:', error);
                this.handleConnectionFailure();
            }
        }
        
        /**
         * Measure round-trip latency
         */
        measureLatency() {
            if (this.dataChannel && this.dataChannel.readyState === 'open') {
                const timestamp = Date.now();
                this.dataChannel.send(JSON.stringify({
                    type: 'latency-measure',
                    payload: { timestamp }
                }));
            }
        }
        
        /**
         * Handle latency measurement response
         */
        handleLatencyMeasurement(payload) {
            const currentTime = Date.now();
            this.stats.latency = currentTime - payload.timestamp;
            console.log(`WebRTC: Latency: ${this.stats.latency}ms`);
        }
        
        /**
         * Get current streaming statistics
         */
        getStats() {
            return { ...this.stats };
        }
        
        /**
         * Stop streaming and clean up resources
         */
        dispose() {
            // Stop stats monitoring
            if (this.statsInterval) {
                clearInterval(this.statsInterval);
                this.statsInterval = null;
            }
            
            // Close data channel
            if (this.dataChannel) {
                this.dataChannel.close();
                this.dataChannel = null;
            }
            
            // Close peer connection
            if (this.peerConnection) {
                this.peerConnection.close();
                this.peerConnection = null;
            }
            
            // Close signaling socket
            if (this.signalingSocket) {
                this.signalingSocket.close();
                this.signalingSocket = null;
            }
            
            // Reset state
            this.isConnected = false;
            this.reconnectCount = 0;
            
            console.log('WebRTC: Resources disposed');
        }
    }
    
    // Export for use in other modules
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = WebRTCStreaming;
    } else if (typeof window !== 'undefined') {
        window.WebRTCStreaming = WebRTCStreaming;
    }
    
})();