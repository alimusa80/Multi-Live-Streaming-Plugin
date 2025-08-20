jQuery(document).ready(function($) {
    'use strict';
    
    var liveTVPlayer, currentChannels = [], retryCount = 0, maxRetries = 5, retryDelay = 500;
    var deviceInfo = detectDeviceType();
    var isMobile = deviceInfo.isMobile, isTablet = deviceInfo.isTablet, isDesktop = deviceInfo.isDesktop;
    var hasTouch = deviceInfo.hasTouch, supportsGestures = deviceInfo.supportsGestures;
    
    function initializeLiveTV(autoplay = 'false') {
        if (typeof videojs === 'undefined') {
            console.error('Video.js not loaded');
            return false;
        }
        
        var playerElement = document.getElementById('live-tv-player');
        if (!playerElement) {
            console.error('Player element not found');
            return false;
        }
        
        var playerOptions = {
            autoplay: autoplay === 'true' && !isMobile,
            muted: autoplay === 'true',
            controls: true,
            responsive: true,
            fluid: true,
            aspectRatio: '16:9',
            playbackRates: isMobile ? [0.5, 1, 1.5, 2] : [0.5, 1, 1.25, 1.5, 2],
            pip: { enabled: true },
            html5: {
                vhs: {
                    overrideNative: !videojs.browser.IS_ANY_SAFARI
                },
                nativeAudioTracks: false,
                nativeVideoTracks: false,
                nativeTextTracks: false
            },
            techOrder: ['html5'],
            inactivityTimeout: 3000,
            // Enhanced touch controls for mobile
            userActions: {
                hotkeys: !isMobile,
                doubleClick: !isMobile ? 'fullscreen' : false
            },
            plugins: {
                hotkeys: !isMobile ? {
                    volumeStep: 0.1,
                    seekStep: 5,
                    enableModifiersForNumbers: false
                } : {}
            }
        };
        
        try {
            // Initialize Video.js player
            liveTVPlayer = videojs('live-tv-player', playerOptions);
            
            // Store globally for debugging
            window.liveTVPlayer = liveTVPlayer;
            
            setupPlayerEvents();
            loadChannels();
            
            // Device-specific enhancements
            if (isMobile || isTablet) {
                setupMobileEnhancements();
            }
            
            // Touch-specific enhancements for all touch devices
            if (hasTouch) {
                setupTouchEnhancements();
            }
            
            // Setup additional features
            setupAdvancedFeatures();
            
            // Setup professional customizations
            setupProfessionalCustomizations();
            
            return true;
            
        } catch (error) {
            console.error('Failed to initialize player:', error);
            return false;
        }
    }
    
    // Setup player event listeners
    function setupPlayerEvents() {
        if (!liveTVPlayer) return;
        
        liveTVPlayer.ready(function() {
            // Player ready
            addAccessibilityFeatures();
            
            // Setup center play button
            setupCenterPlayButton();
        });

        liveTVPlayer.on('error', function(e) {
            var error = liveTVPlayer.error();
            console.error('Player error:', error);
            
            var errorMessage = 'Stream unavailable. Please try another channel.';
            
            // Provide specific error messages based on error code
            if (error) {
                switch(error.code) {
                    case 1: // MEDIA_ERR_ABORTED
                        errorMessage = 'Video loading was aborted. Please try again.';
                        break;
                    case 2: // MEDIA_ERR_NETWORK
                        errorMessage = 'Network error occurred. Check your internet connection.';
                        break;
                    case 3: // MEDIA_ERR_DECODE
                        errorMessage = 'Video format error. This stream may not be compatible.';
                        break;
                    case 4: // MEDIA_ERR_SRC_NOT_SUPPORTED
                        errorMessage = 'Video format not supported or source unavailable. Trying next channel...';
                        // Auto-try next channel for this error
                        setTimeout(function() {
                            if (currentChannels.length > 1) {
                                switchToNextChannel();
                            }
                        }, 2000);
                        break;
                    default:
                        errorMessage = 'Unknown video error occurred. Please try another channel.';
                }
            }
            
            console.error('Player error:', errorMessage);
        });

        liveTVPlayer.on('loadstart', function() {
            showLoadingMessage();
        });

        liveTVPlayer.on('canplay', function() {
            hideLoadingMessage();
        });
        
        // Mobile-specific events
        if (isMobile) {
            liveTVPlayer.on('fullscreenchange', function() {
                if (liveTVPlayer.isFullscreen()) {
                    // Lock orientation in landscape for better viewing
                    if (screen.orientation && screen.orientation.lock) {
                        screen.orientation.lock('landscape').catch(function() {
                            // Orientation lock not supported
                        });
                    }
                }
            });
        }
        
        // Handle video quality changes for mobile
        liveTVPlayer.on('loadedmetadata', function() {
            if (isMobile && liveTVPlayer.videoHeight() > 720) {
                // Suggest lower quality for mobile
                // High resolution detected on mobile
            }
        });
    }
    
    // Mobile-specific enhancements
    function setupMobileEnhancements() {
        if (!liveTVPlayer) return;
        
        // Add mobile-friendly controls
        var controlBar = liveTVPlayer.getChild('controlBar');
        if (controlBar) {
            // Increase button sizes for touch
            controlBar.el().classList.add('vjs-mobile-controls');
        }
        
        // Add swipe gesture support for channel switching
        setupSwipeGestures();
        
        // Optimize for mobile performance
        liveTVPlayer.ready(function() {
            // Reduce buffer size for mobile
            var tech = liveTVPlayer.tech();
            if (tech && tech.hls) {
                tech.hls.config.maxBufferLength = 30; // Reduce buffer for mobile
                tech.hls.config.maxMaxBufferLength = 60;
            }
        });
    }
    
    // Enhanced gesture system for all touch devices
    function setupSwipeGestures() {
        if (!supportsGestures || !liveTVPlayer) return;
        
        var gestureHandler = new GestureHandler(liveTVPlayer.el());
        gestureHandler.init();
    }
    
    // Load channels with enhanced error handling
    function loadChannels() {
        if (typeof liveTV === 'undefined') {
            console.error('liveTV localized object not found');
            console.error('Unable to load channels configuration.');
            return;
        }
        
        var category = $('.live-tv-channels').data('category') || '';
        
        $.ajax({
            url: liveTV.ajax_url,
            type: 'POST',
            data: {
                action: 'get_stream_url',
                nonce: liveTV.nonce,
                category: category
            },
            timeout: 10000, // 10 second timeout
            beforeSend: function() {
                showLoadingMessage();
            },
            success: function(response) {
                // Channels loaded successfully
                if (response.success && response.data && response.data.length > 0) {
                    currentChannels = response.data;
                    displayChannels(response.data);
                    
                    // Auto-load first channel
                    if (response.data[0] && liveTVPlayer) {
                        loadChannel(response.data[0]);
                        setActiveChannel(response.data[0].id);
                    }
                } else {
                    console.warn('No channels available.');
                    // Silent handling - no error overlay
                }
            },
            error: function(xhr, status, error) {
                console.error('Failed to load channels:', error);
                // Silent error handling - log only
            },
            complete: function() {
                hideLoadingMessage();
            }
        });
    }
    
    // Display channels with mobile-responsive design
    function displayChannels(channels) {
        var html = '';
        var gridClass = isMobile ? 'mobile-grid' : (isTablet ? 'tablet-grid' : 'desktop-grid');
        
        channels.forEach(function(channel, index) {
            var logoHtml = channel.logo_url ? 
                '<img src="' + escapeHtml(channel.logo_url) + '" alt="' + escapeHtml(channel.name) + '" class="channel-logo" loading="lazy">' : 
                '<div class="channel-logo-placeholder"><i class="dashicons dashicons-video-alt2"></i></div>';
            
            html += '<div class="channel-item ' + gridClass + '" ';
            html += 'data-channel-id="' + channel.id + '" ';
            html += 'data-stream-url="' + escapeHtml(channel.stream_url) + '" ';
            html += 'role="listitem" ';
            html += 'tabindex="0" ';
            html += 'aria-label="Channel: ' + escapeHtml(channel.name) + (channel.category ? ', Category: ' + escapeHtml(channel.category) : '') + '" ';
            html += 'data-index="' + index + '">';
            html += '<div class="channel-logo-container">' + logoHtml + '</div>';
            html += '<div class="channel-info">';
            html += '<h4 class="channel-name">' + escapeHtml(channel.name) + '</h4>';
            html += '<p class="channel-category">' + escapeHtml(channel.category || '') + '</p>';
            if (!isMobile) {
                html += '<p class="channel-description">' + escapeHtml(channel.description || '') + '</p>';
            }
            html += '</div>';
            html += '<button class="channel-play-btn" ';
            html += 'onclick="loadChannelById(' + channel.id + ')" ';
            html += 'aria-label="Play ' + escapeHtml(channel.name) + '">';
            html += '<i class="dashicons dashicons-controls-play"></i>';
            html += '</button>';
            html += '</div>';
        });
        
        $('#channels-grid').html(html).addClass(gridClass);
        
        // Add event handlers
        bindChannelEvents();
        
        // Initialize search functionality
        initializeChannelSearch();
        
        // Add keyboard navigation
        addKeyboardNavigation();
    }
    
    // Bind channel events
    function bindChannelEvents() {
        $('.channel-item').off('click touchstart').on('click', function(e) {
            e.preventDefault();
            var channelId = $(this).data('channel-id');
            loadChannelById(channelId);
        });
        
        // Prevent double-tap zoom on mobile
        if (isMobile) {
            $('.channel-item').on('touchstart', function(e) {
                e.preventDefault();
                $(this).addClass('touch-active');
            });
            
            $('.channel-item').on('touchend', function(e) {
                e.preventDefault();
                var channelId = $(this).data('channel-id');
                loadChannelById(channelId);
                $(this).removeClass('touch-active');
            });
        }
    }
    
    // Load channel by ID
    function loadChannelById(channelId) {
        var channel = currentChannels.find(c => c.id == channelId);
        if (channel) {
            loadChannel(channel);
            setActiveChannel(channelId);
            updateCurrentChannelInfo(channel);
        }
    }
    
    // Load channel with improved error handling
    function loadChannel(channel) {
        if (!liveTVPlayer || !channel) {
            console.error('Player or channel not available');
            return;
        }
        
        // Loading channel
        
        try {
            // Show loading state
            showLoadingMessage();
            
            // Update player source
            liveTVPlayer.src({
                src: channel.stream_url,
                type: getVideoType(channel.stream_url)
            });
            
            // Start playing with error handling
            liveTVPlayer.ready(function() {
                var playPromise = liveTVPlayer.play();
                if (playPromise !== undefined) {
                    playPromise.catch(function(error) {
                        console.error('Autoplay failed:', error);
                        if (error.name === 'NotAllowedError') {
                            console.log('Autoplay prevented - user interaction required');
                        } else {
                            console.error('Failed to play ' + channel.name + ':', error);
                        }
                        hideLoadingMessage();
                    });
                }
            });
            
        } catch (error) {
            console.error('Error loading channel:', error);
            console.error('Error loading channel:', error.message);
        }
    }
    
    // Get video type with enhanced detection
    function getVideoType(url) {
        var extension = url.split('.').pop().toLowerCase().split('?')[0];
        var typeMap = {
            'm3u8': 'application/x-mpegURL',
            'mp4': 'video/mp4',
            'webm': 'video/webm',
            'ogg': 'video/ogg',
            'mov': 'video/quicktime',
            'avi': 'video/x-msvideo',
            'wmv': 'video/x-ms-wmv',
            'flv': 'video/x-flv',
            'mkv': 'video/x-matroska'
        };
        
        return typeMap[extension] || 'video/mp4';
    }
    
    // Set active channel with visual feedback
    function setActiveChannel(channelId) {
        $('.channel-item').removeClass('active').attr('aria-current', 'false');
        var activeChannel = $('.channel-item[data-channel-id="' + channelId + '"]');
        activeChannel.addClass('active').attr('aria-current', 'true');
        
        // Scroll active channel into view on mobile
        if (isMobile && activeChannel.length) {
            activeChannel[0].scrollIntoView({
                behavior: 'smooth',
                block: 'nearest',
                inline: 'center'
            });
        }
    }
    
    // Update current channel info
    function updateCurrentChannelInfo(channel) {
        var infoHtml = '<div class="current-channel-info">';
        infoHtml += '<h3>' + escapeHtml(channel.name) + '</h3>';
        if (channel.description && !isMobile) {
            infoHtml += '<p>' + escapeHtml(channel.description) + '</p>';
        }
        infoHtml += '</div>';
        
        // Remove existing info and add new
        $('.current-channel-info').remove();
        $('.live-tv-player-wrapper').append(infoHtml);
        
        // Auto-hide after 3 seconds
        setTimeout(function() {
            $('.current-channel-info').fadeOut();
        }, 3000);
        
        // Update page title for accessibility
        if (channel.name) {
            $('#current-channel-name').text('Now Playing: ' + channel.name);
        }
        
        // Update center play button
        updateCenterPlayButton(channel);
    }
    
    // Enhanced loading message
    function showLoadingMessage() {
        if (!$('.loading-overlay').length) {
            var loadingHtml = '<div class="loading-overlay" role="status" aria-live="polite">';
            loadingHtml += '<div class="loading-spinner"></div>';
            loadingHtml += '<p>Loading stream...</p>';
            loadingHtml += '</div>';
            $('.live-tv-player-wrapper').append(loadingHtml);
        }
        $('.loading-overlay').show();
    }
    
    // Hide loading message
    function hideLoadingMessage() {
        $('.loading-overlay').fadeOut();
    }
    
    // Error handling simplified - no overlays, silent recovery
    
    // Show notification
    function showNotification(message, type = 'info') {
        var notificationClass = 'notification-' + type;
        var notificationHtml = '<div class="live-tv-notification ' + notificationClass + '" role="alert">';
        notificationHtml += '<p>' + escapeHtml(message) + '</p>';
        notificationHtml += '<button class="notification-close">&times;</button>';
        notificationHtml += '</div>';
        
        $('.live-tv-notification').remove();
        $('.live-tv-player-wrapper').append(notificationHtml);
        
        // Auto-dismiss
        setTimeout(function() {
            $('.live-tv-notification').fadeOut();
        }, 5000);
        
        // Close button
        $('.notification-close').on('click', function() {
            $(this).parent().fadeOut();
        });
    }
    
    // Enhanced keyboard shortcuts
    function setupKeyboardShortcuts() {
        $(document).on('keydown', function(e) {
            if (!liveTVPlayer) return;
            
            // Allow developer tools and other system shortcuts
            if (e.keyCode === 123 || // F12 - Developer Tools
                (e.ctrlKey && e.shiftKey && e.keyCode === 73) || // Ctrl+Shift+I - Developer Tools
                (e.ctrlKey && e.shiftKey && e.keyCode === 74) || // Ctrl+Shift+J - Console
                (e.ctrlKey && e.shiftKey && e.keyCode === 67) || // Ctrl+Shift+C - Inspector
                (e.ctrlKey && e.keyCode === 85) || // Ctrl+U - View Source
                e.altKey || e.metaKey) { // Alt or Cmd key combinations
                return;
            }
            
            // Only handle shortcuts when player area is focused or no input is focused
            if ($(document.activeElement).is('input, textarea, select')) {
                return;
            }
            
            var handled = false;
            
            switch(e.keyCode) {
                case 32: // Spacebar - play/pause
                    e.preventDefault();
                    if (liveTVPlayer.paused()) {
                        liveTVPlayer.play();
                    } else {
                        liveTVPlayer.pause();
                    }
                    handled = true;
                    break;
                    
                case 77: // M - mute/unmute
                    liveTVPlayer.muted(!liveTVPlayer.muted());
                    handled = true;
                    break;
                    
                case 70: // F - fullscreen
                    if (liveTVPlayer.isFullscreen()) {
                        liveTVPlayer.exitFullscreen();
                    } else {
                        liveTVPlayer.requestFullscreen();
                    }
                    handled = true;
                    break;
                    
                case 38: // Up arrow - volume up
                    e.preventDefault();
                    var currentVolume = liveTVPlayer.volume();
                    liveTVPlayer.volume(Math.min(currentVolume + 0.1, 1));
                    handled = true;
                    break;
                    
                case 40: // Down arrow - volume down
                    e.preventDefault();
                    var currentVolume = liveTVPlayer.volume();
                    liveTVPlayer.volume(Math.max(currentVolume - 0.1, 0));
                    handled = true;
                    break;
                    
                case 37: // Left arrow - previous channel
                    e.preventDefault();
                    switchToPreviousChannel();
                    handled = true;
                    break;
                    
                case 39: // Right arrow - next channel
                    e.preventDefault();
                    switchToNextChannel();
                    handled = true;
                    break;
            }
            
            if (handled) {
                showNotification('Keyboard shortcut used', 'info');
                setTimeout(function() {
                    $('.live-tv-notification').fadeOut();
                }, 1000);
            }
        });
    }
    
    // Channel switching functions
    function switchToNextChannel() {
        if (currentChannels.length === 0) return;
        
        var activeChannel = $('.channel-item.active').data('channel-id');
        var currentIndex = currentChannels.findIndex(c => c.id == activeChannel);
        var nextIndex = (currentIndex + 1) % currentChannels.length;
        
        loadChannelById(currentChannels[nextIndex].id);
    }
    
    function switchToPreviousChannel() {
        if (currentChannels.length === 0) return;
        
        var activeChannel = $('.channel-item.active').data('channel-id');
        var currentIndex = currentChannels.findIndex(c => c.id == activeChannel);
        var prevIndex = currentIndex <= 0 ? currentChannels.length - 1 : currentIndex - 1;
        
        loadChannelById(currentChannels[prevIndex].id);
    }
    
    // Channel search functionality
    function initializeChannelSearch() {
        var searchHtml = '<div class="channel-search">';
        searchHtml += '<input type="text" id="channel-search-input" placeholder="Search channels..." aria-label="Search channels">';
        searchHtml += '<button id="clear-search" aria-label="Clear search">Clear</button>';
        searchHtml += '</div>';
        
        $('.live-tv-channels h3').after(searchHtml);
        
        $('#channel-search-input').on('input', debounce(function() {
            var searchTerm = $(this).val().toLowerCase();
            filterChannels(searchTerm);
        }, 300));
        
        $('#clear-search').on('click', function() {
            $('#channel-search-input').val('');
            filterChannels('');
        });
    }
    
    // Filter channels
    function filterChannels(searchTerm) {
        $('.channel-item').each(function() {
            var channelName = $(this).find('.channel-name').text().toLowerCase();
            var channelCategory = $(this).find('.channel-category').text().toLowerCase();
            var channelDescription = $(this).find('.channel-description').text().toLowerCase();
            
            var matches = channelName.includes(searchTerm) || 
                         channelCategory.includes(searchTerm) || 
                         channelDescription.includes(searchTerm);
            
            if (matches || searchTerm === '') {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    }
    
    // Add keyboard navigation support
    function addKeyboardNavigation() {
        $('.channel-item').on('keydown', function(e) {
            var currentIndex = parseInt($(this).data('index'));
            var totalChannels = $('.channel-item').length;
            var nextIndex;
            
            switch(e.keyCode) {
                case 13: // Enter
                case 32: // Space
                    e.preventDefault();
                    var channelId = $(this).data('channel-id');
                    loadChannelById(channelId);
                    break;
                case 37: // Left arrow
                case 38: // Up arrow
                    e.preventDefault();
                    nextIndex = currentIndex > 0 ? currentIndex - 1 : totalChannels - 1;
                    $('.channel-item').eq(nextIndex).focus();
                    break;
                case 39: // Right arrow
                case 40: // Down arrow
                    e.preventDefault();
                    nextIndex = currentIndex < totalChannels - 1 ? currentIndex + 1 : 0;
                    $('.channel-item').eq(nextIndex).focus();
                    break;
                case 36: // Home
                    e.preventDefault();
                    $('.channel-item').first().focus();
                    break;
                case 35: // End
                    e.preventDefault();
                    $('.channel-item').last().focus();
                    break;
            }
        });
    }
    
    // Add accessibility features
    function addAccessibilityFeatures() {
        if (!liveTVPlayer) return;
        
        // Add ARIA labels
        var playerEl = liveTVPlayer.el();
        playerEl.setAttribute('aria-label', 'Live TV Video Player');
        
        // Announce channel changes
        liveTVPlayer.on('loadstart', function() {
            var activeChannel = $('.channel-item.active').find('.channel-name').text();
            if (activeChannel) {
                announceToScreenReader('Loading ' + activeChannel);
            }
        });
    }
    
    // Screen reader announcements
    function announceToScreenReader(message) {
        var announcement = $('<div role="status" aria-live="polite" class="sr-only">' + escapeHtml(message) + '</div>');
        $('body').append(announcement);
        setTimeout(function() {
            announcement.remove();
        }, 1000);
    }
    
    // Setup advanced features
    function setupAdvancedFeatures() {
        // Google Cast setup
        setupGoogleCast();
        
        // Keyboard shortcuts (disabled on mobile)
        if (!isMobile) {
            setupKeyboardShortcuts();
        }
        
        // Handle page visibility changes
        setupVisibilityHandling();
        
        // Performance monitoring
        setupPerformanceMonitoring();
    }
    
    // Google Cast functionality
    function setupGoogleCast() {
        if (typeof chrome === 'undefined' || !chrome.cast) {
            return;
        }
        
        window['__onGCastApiAvailable'] = function(isAvailable) {
            if (isAvailable) {
                initializeCastApi();
            }
        };
        
        function initializeCastApi() {
            try {
                var context = cast.framework.CastContext.getInstance();
                context.setOptions({
                    receiverApplicationId: chrome.cast.media.DEFAULT_MEDIA_RECEIVER_APP_ID,
                    autoJoinPolicy: chrome.cast.AutoJoinPolicy.ORIGIN_SCOPED
                });
                
                $('.cast-controls').show();
                
                $('#cast-toggle').on('click', function(e) {
                    e.preventDefault();
                    toggleGoogleCast();
                });
                
                context.addEventListener(
                    cast.framework.CastContextEventType.CAST_STATE_CHANGED,
                    function(event) {
                        updateCastButton(event.castState);
                    }
                );
            } catch (error) {
                console.error('Cast API initialization failed:', error);
            }
        }
    }
    
    // Update cast button state
    function updateCastButton(castState) {
        var button = $('#cast-toggle');
        switch (castState) {
            case cast.framework.CastState.CONNECTED:
                button.text('📱 Stop Cast').attr('title', 'Stop Casting');
                break;
            case cast.framework.CastState.NOT_CONNECTED:
                button.text('📺 Cast').attr('title', 'Cast to TV');
                break;
            case cast.framework.CastState.CONNECTING:
                button.text('🔄 Connecting').attr('title', 'Connecting to Cast device');
                break;
        }
    }
    
    // Toggle Google Cast
    function toggleGoogleCast() {
        if (!liveTVPlayer || !currentChannels) {
            console.error('Player not available for casting');
            return;
        }
        
        try {
            var context = cast.framework.CastContext.getInstance();
            var castState = context.getCastState();
            
            if (castState === cast.framework.CastState.CONNECTED) {
                var session = context.getCurrentSession();
                if (session) {
                    session.endSession(true);
                }
            } else {
                startCasting();
            }
        } catch (error) {
            console.error('Cast operation failed:', error);
            console.error('Casting not available');
        }
    }
    
    // Start casting
    function startCasting() {
        var videoElement = liveTVPlayer.el().querySelector('video');
        if (!videoElement || !videoElement.src) {
            console.error('No video source available for casting');
            return;
        }
        
        var currentChannel = getCurrentChannel();
        var mediaInfo = new chrome.cast.media.MediaInfo(videoElement.src, getVideoType(videoElement.src));
        
        if (currentChannel) {
            mediaInfo.metadata = new chrome.cast.media.GenericMediaMetadata();
            mediaInfo.metadata.title = currentChannel.name || 'Live TV Stream';
            mediaInfo.metadata.subtitle = currentChannel.description || 'Live Television';
            
            if (currentChannel.logo_url) {
                mediaInfo.metadata.images = [new chrome.cast.Image(currentChannel.logo_url)];
            }
        }
        
        var request = new chrome.cast.media.LoadRequest(mediaInfo);
        request.autoplay = true;
        request.currentTime = videoElement.currentTime || 0;
        
        var context = cast.framework.CastContext.getInstance();
        context.requestSession().then(
            function(session) {
                session.loadMedia(request).then(
                    function() {
                        showNotification('Casting started', 'success');
                    },
                    function(error) {
                        console.error('Failed to start casting:', error.description);
                    }
                );
            },
            function(error) {
                if (error.code !== chrome.cast.ErrorCode.CANCEL) {
                    console.error('Failed to connect to Cast device');
                }
            }
        );
    }
    
    // Get current channel
    function getCurrentChannel() {
        if (!currentChannels) return null;
        
        var activeChannelElement = $('.channel-item.active');
        if (activeChannelElement.length > 0) {
            var channelId = activeChannelElement.data('channel-id');
            return currentChannels.find(c => c.id == channelId);
        }
        
        return currentChannels[0] || null;
    }
    
    // Handle page visibility changes
    function setupVisibilityHandling() {
        document.addEventListener('visibilitychange', function() {
            if (liveTVPlayer) {
                if (document.hidden) {
                    // Pause on mobile to save bandwidth
                    if (isMobile && !liveTVPlayer.paused()) {
                        liveTVPlayer.pause();
                        showNotification('Paused to save data', 'info');
                    }
                } else {
                    // Page visible - live stream active
                }
            }
        });
    }
    
    // Performance monitoring
    function setupPerformanceMonitoring() {
        if (!liveTVPlayer) return;
        
        liveTVPlayer.ready(function() {
            var tech = liveTVPlayer.tech();
            if (tech && tech.hls) {
                tech.hls.on('hlsError', function(event, data) {
                    console.error('HLS Error:', data);
                    if (data.fatal) {
                        console.error('Streaming error occurred');
                    }
                });
            }
        });
        
        // Monitor buffer health
        liveTVPlayer.on('progress', function() {
            if (isMobile) {
                var buffered = liveTVPlayer.buffered();
                if (buffered.length > 0) {
                    var bufferEnd = buffered.end(buffered.length - 1);
                    var currentTime = liveTVPlayer.currentTime();
                    var bufferLength = bufferEnd - currentTime;
                    
                    // Warn if buffer is low
                    if (bufferLength < 5) {
                        // Low buffer detected
                    }
                }
            }
        });
    }
    
    // Utility functions
    function escapeHtml(text) {
        if (!text) return '';
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    function debounce(func, wait) {
        var timeout;
        return function executedFunction(...args) {
            var later = function() {
                clearTimeout(timeout);
                func.apply(this, args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }
    
    // Initialize with retry mechanism
    function tryInitialize() {
        retryCount++;
        
        // Get autoplay setting
        var autoplay = window.liveTVAutoplay || 'false';
        
        if (initializeLiveTV(autoplay)) {
            // Live TV initialized successfully
            return;
        }
        
        if (retryCount < maxRetries) {
            // Retrying initialization
            setTimeout(tryInitialize, retryDelay);
        } else {
            console.error('Failed to initialize Live TV after ' + maxRetries + ' attempts');
            // Silent failure - no error overlay
        }
    }
    
    // ===== FAVORITES AND PLAYLIST FUNCTIONALITY =====
    
    // Toggle channel favorite status
    function toggleFavorite(channelId) {
        if (!channelId) {
            console.error('Invalid channel ID');
            return;
        }
        
        var data = {
            action: 'toggle_favorite',
            channel_id: channelId
        };
        
        // Add nonce for logged-in users
        if (liveTV && liveTV.nonce) {
            data.nonce = liveTV.nonce;
        }
        
        $.ajax({
            url: liveTV.ajax_url,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    updateFavoriteButton(channelId, response.data.is_favorite);
                    var message = response.data.is_favorite ? 'Added to favorites' : 'Removed from favorites';
                    showNotification(message, 'success');
                } else {
                    console.error(response.data || 'Failed to update favorites');
                }
            },
            error: function() {
                console.error('Failed to update favorites');
            }
        });
    }
    
    // Update favorite button appearance
    function updateFavoriteButton(channelId, isFavorite) {
        var favoriteBtn = $('.channel-item[data-channel-id="' + channelId + '"] .favorite-btn');
        if (favoriteBtn.length > 0) {
            if (isFavorite) {
                favoriteBtn.addClass('favorited').removeClass('not-favorited');
                favoriteBtn.find('.favorite-icon').html('❤️');
                favoriteBtn.attr('title', 'Remove from favorites');
            } else {
                favoriteBtn.removeClass('favorited').addClass('not-favorited');
                favoriteBtn.find('.favorite-icon').html('🤍');
                favoriteBtn.attr('title', 'Add to favorites');
            }
        }
    }
    
    // Load user playlists
    function loadUserPlaylists() {
        $.ajax({
            url: liveTV.ajax_url,
            type: 'POST',
            data: {
                action: 'get_user_playlists'
            },
            success: function(response) {
                if (response.success) {
                    displayPlaylists(response.data);
                }
            },
            error: function() {
                console.error('Failed to load playlists');
            }
        });
    }
    
    // Display playlists in UI
    function displayPlaylists(playlists) {
        var playlistContainer = $('#playlist-container');
        if (playlistContainer.length === 0) return;
        
        var playlistHtml = '';
        
        if (playlists && playlists.length > 0) {
            playlists.forEach(function(playlist) {
                playlistHtml += '<div class="playlist-item" data-playlist-id="' + playlist.id + '">';
                playlistHtml += '<div class="playlist-info">';
                playlistHtml += '<h4>' + escapeHtml(playlist.name) + '</h4>';
                playlistHtml += '<p>' + playlist.channel_count + ' channels</p>';
                playlistHtml += '</div>';
                playlistHtml += '<button class="button play-playlist-btn" onclick="playPlaylist(' + playlist.id + ')">Play</button>';
                playlistHtml += '</div>';
            });
        } else {
            playlistHtml = '<p>No playlists available.</p>';
        }
        
        playlistContainer.html(playlistHtml);
    }
    
    // Play playlist
    function playPlaylist(playlistId) {
        if (!playlistId) return;
        
        $.ajax({
            url: liveTV.ajax_url,
            type: 'POST',
            data: {
                action: 'get_playlist_channels',
                playlist_id: playlistId
            },
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    currentChannels = response.data;
                    displayChannels(currentChannels);
                    loadChannelById(currentChannels[0].id);
                    showNotification('Playing playlist', 'success');
                } else {
                    console.error('Playlist is empty or not found');
                }
            },
            error: function() {
                console.error('Failed to load playlist');
            }
        });
    }
    
    // Add channel to playlist
    function addToPlaylist(channelId, playlistId) {
        if (!channelId || !playlistId) return;
        
        var data = {
            action: 'add_to_playlist',
            channel_id: channelId,
            playlist_id: playlistId
        };
        
        if (liveTV && liveTV.nonce) {
            data.nonce = liveTV.nonce;
        }
        
        $.ajax({
            url: liveTV.ajax_url,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    showNotification('Added to playlist', 'success');
                } else {
                    console.error(response.data || 'Failed to add to playlist');
                }
            },
            error: function() {
                console.error('Failed to add to playlist');
            }
        });
    }
    
    // Track channel view for analytics
    function trackChannelView(channelId, duration) {
        if (!channelId) return;
        
        var data = {
            action: 'track_channel_view',
            channel_id: channelId,
            duration: duration || 0
        };
        
        if (liveTV && liveTV.nonce) {
            data.nonce = liveTV.nonce;
        }
        
        $.ajax({
            url: liveTV.ajax_url,
            type: 'POST',
            data: data,
            success: function(response) {
                // Silent tracking - no user notification needed
            }
        });
    }
    
    // Record watch history
    function recordWatchHistory(channelId, duration, percentage) {
        if (!channelId) return;
        
        var data = {
            action: 'add_to_history',
            channel_id: channelId,
            duration: duration || 0,
            percentage: percentage || 0
        };
        
        if (liveTV && liveTV.nonce) {
            data.nonce = liveTV.nonce;
        }
        
        $.ajax({
            url: liveTV.ajax_url,
            type: 'POST',
            data: data
        });
    }
    
    // Enhanced channel loading with favorites support
    function loadChannelsEnhanced() {
        $.ajax({
            url: liveTV.ajax_url,
            type: 'POST',
            data: {
                action: 'get_channels'
            },
            success: function(response) {
                if (response.success) {
                    currentChannels = response.data;
                    displayChannelsWithFeatures(currentChannels);
                    
                    // Load playlists if container exists
                    if ($('#playlist-container').length > 0) {
                        loadUserPlaylists();
                    }
                } else {
                    console.error('Failed to load channels:', response.data);
                    console.error('Failed to load channels');
                }
            },
            error: function() {
                console.error('AJAX error loading channels');
                console.error('Network error loading channels');
            }
        });
    }
    
    // Display channels with enhanced features
    function displayChannelsWithFeatures(channels) {
        var channelsContainer = $('#live-tv-channels');
        if (channelsContainer.length === 0) {
            console.error('Channels container not found');
            return;
        }
        
        var channelsHtml = '';
        
        if (channels && channels.length > 0) {
            channels.forEach(function(channel, index) {
                if (parseInt(channel.is_active) !== 1) return;
                
                var isFirst = index === 0;
                var activeClass = isFirst ? ' active' : '';
                
                channelsHtml += '<div class="channel-item' + activeClass + '" data-channel-id="' + channel.id + '" data-stream-url="' + escapeHtml(channel.stream_url) + '">';
                
                // Channel logo
                if (channel.logo_url) {
                    channelsHtml += '<div class="channel-logo">';
                    channelsHtml += '<img src="' + escapeHtml(channel.logo_url) + '" alt="' + escapeHtml(channel.name) + '" loading="lazy">';
                    channelsHtml += '</div>';
                }
                
                // Channel info
                channelsHtml += '<div class="channel-info">';
                channelsHtml += '<h3>' + escapeHtml(channel.name) + '</h3>';
                if (channel.description) {
                    channelsHtml += '<p>' + escapeHtml(channel.description) + '</p>';
                }
                if (channel.category) {
                    channelsHtml += '<span class="channel-category">' + escapeHtml(channel.category) + '</span>';
                }
                channelsHtml += '</div>';
                
                // Channel actions
                channelsHtml += '<div class="channel-actions">';
                channelsHtml += '<button class="play-btn" onclick="loadChannelById(' + channel.id + ')" title="Play Channel">';
                channelsHtml += '<span class="play-icon">▶️</span> Play';
                channelsHtml += '</button>';
                
                // Favorite button
                channelsHtml += '<button class="favorite-btn not-favorited" onclick="toggleFavorite(' + channel.id + ')" title="Add to favorites">';
                channelsHtml += '<span class="favorite-icon">🤍</span>';
                channelsHtml += '</button>';
                
                // Playlist dropdown (if playlists exist)
                channelsHtml += '<select class="playlist-selector" onchange="addToPlaylist(' + channel.id + ', this.value); this.value=\'\'">';
                channelsHtml += '<option value="">Add to Playlist</option>';
                channelsHtml += '</select>';
                
                channelsHtml += '</div>';
                channelsHtml += '</div>';
            });
        } else {
            channelsHtml = '<div class="no-channels">No channels available</div>';
        }
        
        channelsContainer.html(channelsHtml);
    }
    
    // Setup watch time tracking
    function setupWatchTimeTracking() {
        if (!liveTVPlayer) return;
        
        var watchStartTime = null;
        var currentChannelId = null;
        
        liveTVPlayer.on('play', function() {
            watchStartTime = Date.now();
            currentChannelId = getCurrentChannelId();
            
            if (currentChannelId) {
                trackChannelView(currentChannelId, 0);
            }
        });
        
        liveTVPlayer.on('pause', function() {
            if (watchStartTime && currentChannelId) {
                var watchDuration = Math.floor((Date.now() - watchStartTime) / 1000);
                recordWatchHistory(currentChannelId, watchDuration, 0);
                watchStartTime = null;
            }
        });
        
        // Track every 30 seconds while playing
        setInterval(function() {
            if (liveTVPlayer && !liveTVPlayer.paused() && watchStartTime && currentChannelId) {
                var watchDuration = Math.floor((Date.now() - watchStartTime) / 1000);
                if (watchDuration >= 30) {
                    recordWatchHistory(currentChannelId, watchDuration, 0);
                    watchStartTime = Date.now(); // Reset timer
                }
            }
        }, 30000);
    }
    
    // Get current channel ID
    function getCurrentChannelId() {
        var activeChannel = $('.channel-item.active');
        if (activeChannel.length > 0) {
            return parseInt(activeChannel.data('channel-id'));
        }
        return null;
    }
    
    // ===== ENHANCED DEVICE DETECTION =====
    
    /**
     * Comprehensive device type and capability detection
     * 
     * @returns {Object} Device information object
     */
    function detectDeviceType() {
        var userAgent = navigator.userAgent;
        var platform = navigator.platform;
        var hasTouch = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
        
        // Mobile detection patterns
        var mobilePatterns = [
            /Android.*Mobile/i,
            /iPhone/i,
            /iPod/i,
            /BlackBerry/i,
            /BB10/i,
            /Windows Phone/i,
            /IEMobile/i,
            /Opera Mini/i,
            /Mobile.*Firefox/i
        ];
        
        // Tablet detection patterns
        var tabletPatterns = [
            /iPad/i,
            /Android(?!.*Mobile)/i,
            /Tablet/i,
            /PlayBook/i,
            /Kindle/i,
            /Silk/i
        ];
        
        // Smart TV detection patterns
        var tvPatterns = [
            /Smart.*TV/i,
            /AppleTV/i,
            /GoogleTV/i,
            /WebOS/i,
            /Tizen/i
        ];
        
        var isMobile = mobilePatterns.some(pattern => pattern.test(userAgent));
        var isTablet = tabletPatterns.some(pattern => pattern.test(userAgent)) && !isMobile;
        var isTV = tvPatterns.some(pattern => pattern.test(userAgent));
        var isDesktop = !isMobile && !isTablet && !isTV;
        
        // Screen size detection for edge cases
        var screenWidth = window.screen.width;
        var screenHeight = window.screen.height;
        var maxDimension = Math.max(screenWidth, screenHeight);
        
        // Override detection based on screen size if needed
        if (hasTouch && maxDimension <= 768 && !isMobile && !isTablet) {
            isMobile = true;
            isDesktop = false;
        } else if (hasTouch && maxDimension > 768 && maxDimension <= 1024 && !isTablet) {
            isTablet = true;
            isDesktop = false;
            isMobile = false;
        }
        
        // Gesture support detection
        var supportsGestures = hasTouch && (isMobile || isTablet);
        
        // Detect specific device capabilities
        var capabilities = {
            hasPointerEvents: 'PointerEvent' in window,
            hasOrientationAPI: 'orientation' in screen,
            hasDeviceMotion: 'DeviceMotionEvent' in window,
            hasVibration: 'vibrate' in navigator,
            hasFullscreen: !!(document.fullscreenEnabled || document.webkitFullscreenEnabled || document.mozFullScreenEnabled),
            hasPictureInPicture: 'pictureInPictureEnabled' in document
        };
        
        return {
            isMobile: isMobile,
            isTablet: isTablet,
            isTV: isTV,
            isDesktop: isDesktop,
            hasTouch: hasTouch,
            supportsGestures: supportsGestures,
            screenWidth: screenWidth,
            screenHeight: screenHeight,
            userAgent: userAgent,
            platform: platform,
            capabilities: capabilities,
            // Convenience properties
            isTouchDevice: hasTouch,
            isLargeScreen: maxDimension >= 1024,
            isSmallScreen: maxDimension < 768,
            orientation: screenWidth > screenHeight ? 'landscape' : 'portrait'
        };
    }
    
    // ===== COMPREHENSIVE GESTURE HANDLER =====
    
    /**
     * Advanced gesture handling class for native-feeling controls
     */
    function GestureHandler(element) {
        this.element = element;
        this.isActive = false;
        this.gestures = {
            tap: { enabled: true, threshold: 10, timeout: 300 },
            doubleTap: { enabled: !isMobile, threshold: 10, timeout: 300, delay: 400 },
            longPress: { enabled: true, threshold: 10, timeout: 500 },
            swipe: { enabled: true, threshold: 50, maxTime: 500, velocity: 0.3 },
            pinch: { enabled: isTablet || isDesktop, threshold: 0.1 },
            pan: { enabled: true, threshold: 10 }
        };
        
        // Touch tracking variables
        this.touches = [];
        this.startTime = 0;
        this.lastTap = 0;
        this.longPressTimer = null;
        this.preventClick = false;
        
        return this;
    }
    
    GestureHandler.prototype.init = function() {
        if (!this.element || !supportsGestures) return;
        
        var self = this;
        
        // Use pointer events if available, fallback to touch events
        if (deviceInfo.capabilities.hasPointerEvents) {
            this.element.addEventListener('pointerdown', this.handleStart.bind(this), { passive: false });
            this.element.addEventListener('pointermove', this.handleMove.bind(this), { passive: false });
            this.element.addEventListener('pointerup', this.handleEnd.bind(this), { passive: false });
            this.element.addEventListener('pointercancel', this.handleCancel.bind(this), { passive: false });
        } else {
            this.element.addEventListener('touchstart', this.handleStart.bind(this), { passive: false });
            this.element.addEventListener('touchmove', this.handleMove.bind(this), { passive: false });
            this.element.addEventListener('touchend', this.handleEnd.bind(this), { passive: false });
            this.element.addEventListener('touchcancel', this.handleCancel.bind(this), { passive: false });
        }
        
        // Prevent context menu on long press for mobile
        if (isMobile) {
            this.element.addEventListener('contextmenu', function(e) {
                if (self.preventClick) {
                    e.preventDefault();
                    e.stopPropagation();
                }
            });
        }
        
        // Mouse events for desktop with touch screens
        if (isDesktop && hasTouch) {
            this.setupMouseGestures();
        }
        
        this.isActive = true;
    };
    
    GestureHandler.prototype.handleStart = function(e) {
        if (!this.isActive) return;
        
        var touch = this.getEventData(e);
        this.touches = [touch];
        this.startTime = Date.now();
        this.preventClick = false;
        
        // Clear any existing long press timer
        if (this.longPressTimer) {
            clearTimeout(this.longPressTimer);
            this.longPressTimer = null;
        }
        
        // Set up long press detection
        if (this.gestures.longPress.enabled) {
            var self = this;
            this.longPressTimer = setTimeout(function() {
                if (self.touches.length === 1) {
                    self.handleLongPress(self.touches[0]);
                }
            }, this.gestures.longPress.timeout);
        }
        
        // Multi-touch handling
        if (e.touches && e.touches.length > 1) {
            this.touches = Array.from(e.touches).map(this.getEventData);
            if (this.longPressTimer) {
                clearTimeout(this.longPressTimer);
                this.longPressTimer = null;
            }
        }
    };
    
    GestureHandler.prototype.handleMove = function(e) {
        if (!this.isActive || this.touches.length === 0) return;
        
        var currentTouch = this.getEventData(e);
        var startTouch = this.touches[0];
        
        // Calculate movement distance
        var deltaX = currentTouch.x - startTouch.x;
        var deltaY = currentTouch.y - startTouch.y;
        var distance = Math.sqrt(deltaX * deltaX + deltaY * deltaY);
        
        // Cancel long press if moved too much
        if (distance > this.gestures.longPress.threshold && this.longPressTimer) {
            clearTimeout(this.longPressTimer);
            this.longPressTimer = null;
        }
        
        // Handle pan gesture (for volume/brightness controls)
        if (this.gestures.pan.enabled && distance > this.gestures.pan.threshold) {
            this.handlePan(startTouch, currentTouch, deltaX, deltaY);
        }
        
        // Handle pinch gesture for tablets
        if (this.gestures.pinch.enabled && this.touches.length === 2 && e.touches && e.touches.length === 2) {
            this.handlePinch(e.touches);
        }
    };
    
    GestureHandler.prototype.handleEnd = function(e) {
        if (!this.isActive || this.touches.length === 0) return;
        
        var endTouch = this.getEventData(e);
        var startTouch = this.touches[0];
        var duration = Date.now() - this.startTime;
        
        // Clear long press timer
        if (this.longPressTimer) {
            clearTimeout(this.longPressTimer);
            this.longPressTimer = null;
        }
        
        // Calculate gesture metrics
        var deltaX = endTouch.x - startTouch.x;
        var deltaY = endTouch.y - startTouch.y;
        var distance = Math.sqrt(deltaX * deltaX + deltaY * deltaY);
        var velocity = distance / duration;
        
        // Determine gesture type
        if (distance < this.gestures.tap.threshold && duration < this.gestures.tap.timeout) {
            this.handleTap(endTouch, duration);
        } else if (this.gestures.swipe.enabled && 
                   distance >= this.gestures.swipe.threshold && 
                   duration <= this.gestures.swipe.maxTime && 
                   velocity >= this.gestures.swipe.velocity) {
            this.handleSwipe(deltaX, deltaY, velocity, duration);
        }
        
        // Reset state
        this.touches = [];
        this.startTime = 0;
    };
    
    GestureHandler.prototype.handleCancel = function(e) {
        if (this.longPressTimer) {
            clearTimeout(this.longPressTimer);
            this.longPressTimer = null;
        }
        this.touches = [];
        this.startTime = 0;
        this.preventClick = false;
    };
    
    // Gesture handlers
    GestureHandler.prototype.handleTap = function(touch, duration) {
        var now = Date.now();
        
        // Double tap detection
        if (this.gestures.doubleTap.enabled && 
            (now - this.lastTap) < this.gestures.doubleTap.delay) {
            this.handleDoubleTap(touch);
            this.lastTap = 0; // Prevent triple tap
            return;
        }
        
        this.lastTap = now;
        
        // Single tap - toggle play/pause
        setTimeout(() => {
            if (this.lastTap === now) { // Wasn't part of double tap
                this.togglePlayPause();
            }
        }, this.gestures.doubleTap.enabled ? this.gestures.doubleTap.delay : 0);
    };
    
    GestureHandler.prototype.handleDoubleTap = function(touch) {
        // Double tap - toggle fullscreen (desktop) or show controls (mobile)
        if (isDesktop) {
            this.toggleFullscreen();
        } else {
            this.showControls();
        }
    };
    
    GestureHandler.prototype.handleLongPress = function(touch) {
        this.preventClick = true;
        
        // Long press - show context menu or channel list
        if (isMobile) {
            // Vibrate if available
            if (navigator.vibrate) {
                navigator.vibrate(50);
            }
            this.showChannelSelector();
        } else {
            this.showContextMenu(touch);
        }
    };
    
    GestureHandler.prototype.handleSwipe = function(deltaX, deltaY, velocity, duration) {
        var absX = Math.abs(deltaX);
        var absY = Math.abs(deltaY);
        
        if (absX > absY) {
            // Horizontal swipe
            if (deltaX > 0) {
                this.swipeRight(velocity);
            } else {
                this.swipeLeft(velocity);
            }
        } else {
            // Vertical swipe
            if (deltaY > 0) {
                this.swipeDown(velocity);
            } else {
                this.swipeUp(velocity);
            }
        }
    };
    
    GestureHandler.prototype.handlePan = function(startTouch, currentTouch, deltaX, deltaY) {
        // Pan gestures for volume (vertical) and brightness (horizontal on mobile)
        if (Math.abs(deltaY) > Math.abs(deltaX)) {
            // Vertical pan - volume control
            this.adjustVolume(-deltaY / 100);
        } else if (isMobile && Math.abs(deltaX) > Math.abs(deltaY)) {
            // Horizontal pan on mobile - seeking (disabled during live streams)
            // For live streams, this could show channel info instead
            this.showChannelInfo();
        }
    };
    
    GestureHandler.prototype.handlePinch = function(touches) {
        // Pinch gesture - not commonly used for video players but could zoom UI
        // Implementation would go here for tablets
    };
    
    // Action methods
    GestureHandler.prototype.togglePlayPause = function() {
        if (liveTVPlayer && !liveTVPlayer.paused()) {
            liveTVPlayer.pause();
            this.showFeedback('⏸️ Paused');
        } else if (liveTVPlayer) {
            liveTVPlayer.play();
            this.showFeedback('▶️ Playing');
        }
    };
    
    GestureHandler.prototype.toggleFullscreen = function() {
        if (liveTVPlayer) {
            if (liveTVPlayer.isFullscreen()) {
                liveTVPlayer.exitFullscreen();
                this.showFeedback('↙️ Exit Fullscreen');
            } else {
                liveTVPlayer.requestFullscreen();
                this.showFeedback('↗️ Fullscreen');
            }
        }
    };
    
    GestureHandler.prototype.swipeLeft = function(velocity) {
        // Next channel
        switchToNextChannel();
        this.showFeedback('→ Next Channel');
    };
    
    GestureHandler.prototype.swipeRight = function(velocity) {
        // Previous channel
        switchToPreviousChannel();
        this.showFeedback('← Previous Channel');
    };
    
    GestureHandler.prototype.swipeUp = function(velocity) {
        // Volume up or show channel list
        if (velocity > 1) {
            this.adjustVolume(0.1);
            this.showFeedback('🔊 Volume Up');
        } else {
            this.showChannelSelector();
        }
    };
    
    GestureHandler.prototype.swipeDown = function(velocity) {
        // Volume down or hide UI
        if (velocity > 1) {
            this.adjustVolume(-0.1);
            this.showFeedback('🔉 Volume Down');
        } else {
            this.hideUI();
        }
    };
    
    GestureHandler.prototype.adjustVolume = function(delta) {
        if (liveTVPlayer) {
            var currentVolume = liveTVPlayer.volume();
            var newVolume = Math.max(0, Math.min(1, currentVolume + delta));
            liveTVPlayer.volume(newVolume);
        }
    };
    
    GestureHandler.prototype.showControls = function() {
        if (liveTVPlayer) {
            liveTVPlayer.userActive(true);
        }
    };
    
    GestureHandler.prototype.hideUI = function() {
        if (liveTVPlayer) {
            liveTVPlayer.userActive(false);
        }
    };
    
    GestureHandler.prototype.showChannelSelector = function() {
        // Scroll to channel list
        var channelList = document.querySelector('.live-tv-channels');
        if (channelList) {
            channelList.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    };
    
    GestureHandler.prototype.showChannelInfo = function() {
        // Show current channel info overlay
        var currentChannel = getCurrentChannel();
        if (currentChannel) {
            updateCurrentChannelInfo(currentChannel);
        }
    };
    
    GestureHandler.prototype.showContextMenu = function(touch) {
        // Desktop context menu
        console.log('Context menu at', touch.x, touch.y);
    };
    
    GestureHandler.prototype.showFeedback = function(message) {
        // Show visual feedback for gesture actions
        showNotification(message, 'gesture');
        
        // Auto-hide faster for gesture feedback
        setTimeout(function() {
            $('.live-tv-notification.notification-gesture').fadeOut(200);
        }, 1500);
    };
    
    GestureHandler.prototype.getEventData = function(e) {
        var touch = e.touches ? e.touches[0] : e;
        return {
            x: touch.clientX || touch.pageX,
            y: touch.clientY || touch.pageY,
            timestamp: Date.now()
        };
    };
    
    GestureHandler.prototype.setupMouseGestures = function() {
        // Basic mouse gesture support for desktop touch screens
        var self = this;
        var mouseDown = false;
        
        this.element.addEventListener('mousedown', function(e) {
            mouseDown = true;
            self.handleStart(e);
        });
        
        this.element.addEventListener('mousemove', function(e) {
            if (mouseDown) {
                self.handleMove(e);
            }
        });
        
        this.element.addEventListener('mouseup', function(e) {
            if (mouseDown) {
                mouseDown = false;
                self.handleEnd(e);
            }
        });
    };
    
    GestureHandler.prototype.destroy = function() {
        if (this.longPressTimer) {
            clearTimeout(this.longPressTimer);
        }
        this.isActive = false;
        this.touches = [];
        // Remove event listeners would go here
    };
    
    // Make functions globally available
    window.toggleFavorite = toggleFavorite;
    window.playPlaylist = playPlaylist;
    window.addToPlaylist = addToPlaylist;
    window.GestureHandler = GestureHandler;
    window.deviceInfo = deviceInfo;
    
    // Expose enhanced functions for debugging
    window.liveTVDebug = {
        loadChannels: loadChannels,
        loadChannelsEnhanced: loadChannelsEnhanced,
        loadChannelById: loadChannelById,
        switchToNextChannel: switchToNextChannel,
        switchToPreviousChannel: switchToPreviousChannel,
        toggleGoogleCast: toggleGoogleCast,
        toggleFavorite: toggleFavorite,
        playPlaylist: playPlaylist,
        loadUserPlaylists: loadUserPlaylists,
        tryInitialize: tryInitialize
    };
    
    // Initialize enhanced features
    function initializeEnhancedFeatures() {
        // Setup watch time tracking
        if (liveTVPlayer) {
            setupWatchTimeTracking();
        }
        
        // Load channels with enhanced features
        loadChannelsEnhanced();
    }
    
    // Start initialization with enhanced features
    tryInitialize();
    
    // Initialize enhanced features after a short delay
    setTimeout(function() {
        initializeEnhancedFeatures();
    }, 1000);
    
    // Show channel options menu
    function showChannelOptions(channelId) {
        var channel = currentChannels.find(c => c.id == channelId);
        if (!channel) return;
        
        var optionsHtml = '<div class="channel-options-menu" data-channel-id="' + channelId + '">';
        optionsHtml += '<div class="options-header">' + escapeHtml(channel.name) + '</div>';
        optionsHtml += '<button class="option-play" onclick="loadChannelById(' + channelId + '); hideChannelOptions()">▶️ Play</button>';
        optionsHtml += '<button class="option-favorite" onclick="toggleFavorite(' + channelId + '); hideChannelOptions()">❤️ Favorite</button>';
        optionsHtml += '<button class="option-info" onclick="showChannelDetailedInfo(' + channelId + '); hideChannelOptions()">ℹ️ Info</button>';
        optionsHtml += '<button class="option-close" onclick="hideChannelOptions()">✕ Close</button>';
        optionsHtml += '</div>';
        
        // Remove existing menu
        $('.channel-options-menu').remove();
        
        // Add new menu
        $('body').append(optionsHtml);
        
        // Position menu
        var $menu = $('.channel-options-menu');
        var $channel = $('.channel-item[data-channel-id="' + channelId + '"]');
        var channelPos = $channel.offset();
        
        $menu.css({
            position: 'absolute',
            top: channelPos.top + $channel.height(),
            left: Math.max(10, channelPos.left),
            zIndex: 9999
        });
    }
    
    // Hide channel options menu
    function hideChannelOptions() {
        $('.channel-options-menu').remove();
        $('.channel-item').removeClass('long-press-active');
    }
    
    // Show detailed channel information
    function showChannelDetailedInfo(channelId) {
        var channel = currentChannels.find(c => c.id == channelId);
        if (!channel) return;
        
        var infoHtml = '<div class="channel-info-modal">';
        infoHtml += '<div class="info-content">';
        if (channel.logo_url) {
            infoHtml += '<img src="' + escapeHtml(channel.logo_url) + '" alt="' + escapeHtml(channel.name) + '" class="info-logo">';
        }
        infoHtml += '<h3>' + escapeHtml(channel.name) + '</h3>';
        if (channel.description) {
            infoHtml += '<p>' + escapeHtml(channel.description) + '</p>';
        }
        infoHtml += '<p><strong>Category:</strong> ' + escapeHtml(channel.category || 'General') + '</p>';
        infoHtml += '<div class="info-actions">';
        infoHtml += '<button onclick="loadChannelById(' + channelId + '); hideChannelDetailedInfo()">▶️ Play Now</button>';
        infoHtml += '<button onclick="hideChannelDetailedInfo()">Close</button>';
        infoHtml += '</div>';
        infoHtml += '</div>';
        infoHtml += '<div class="info-overlay" onclick="hideChannelDetailedInfo()"></div>';
        infoHtml += '</div>';
        
        $('body').append(infoHtml);
    }
    
    // Hide channel information modal
    function hideChannelDetailedInfo() {
        $('.channel-info-modal').remove();
    }
    
    // ===== PROFESSIONAL CUSTOMIZATIONS =====
    
    /**
     * Setup professional customizations based on admin settings
     */
    function setupProfessionalCustomizations() {
        if (!liveTVPlayer) return;
        
        liveTVPlayer.ready(function() {
            // Setup professional branding
            setupProfessionalBranding();
            
            // Setup enhanced loading animation
            enhanceLoadingAnimation();
            
            // Apply custom styling based on settings
            applyCustomStyling();
        });
    }
    
    /**
     * Setup professional branding/watermark
     */
    function setupProfessionalBranding() {
        // Get branding settings from WordPress (would be localized)
        var brandingSettings = {
            enabled: false, // This would come from PHP
            type: 'text',
            text: 'Live TV Pro',
            logoUrl: '',
            position: 'top-right',
            opacity: 0.7
        };
        
        // Check if liveTV object has customization settings
        if (typeof liveTV !== 'undefined' && liveTV.customization) {
            brandingSettings = Object.assign(brandingSettings, liveTV.customization);
        }
        
        if (!brandingSettings.enabled) return;
        
        var playerEl = liveTVPlayer.el();
        var brandingEl = document.createElement('div');
        brandingEl.className = 'vjs-professional-branding';
        brandingEl.style.opacity = brandingSettings.opacity;
        
        // Position the branding
        switch(brandingSettings.position) {
            case 'top-left':
                brandingEl.style.top = '20px';
                brandingEl.style.left = '20px';
                brandingEl.style.right = 'auto';
                break;
            case 'top-right':
                brandingEl.style.top = '20px';
                brandingEl.style.right = '20px';
                break;
            case 'bottom-left':
                brandingEl.style.bottom = '80px'; // Above controls
                brandingEl.style.left = '20px';
                brandingEl.style.top = 'auto';
                brandingEl.style.right = 'auto';
                break;
            case 'bottom-right':
                brandingEl.style.bottom = '80px'; // Above controls
                brandingEl.style.right = '20px';
                brandingEl.style.top = 'auto';
                break;
        }
        
        // Create branding content
        var brandingContent = '';
        if (brandingSettings.type === 'logo' && brandingSettings.logoUrl) {
            brandingContent = '<img src="' + escapeHtml(brandingSettings.logoUrl) + '" alt="Logo">';
        } else if (brandingSettings.type === 'text') {
            brandingContent = '<span class="brand-text">' + escapeHtml(brandingSettings.text) + '</span>';
        } else if (brandingSettings.type === 'both' && brandingSettings.logoUrl) {
            brandingContent = '<img src="' + escapeHtml(brandingSettings.logoUrl) + '" alt="Logo"> ';
            brandingContent += '<span class="brand-text">' + escapeHtml(brandingSettings.text) + '</span>';
        }
        
        brandingEl.innerHTML = brandingContent;
        playerEl.appendChild(brandingEl);
        
        // Auto-hide/show with controls
        liveTVPlayer.on('useractive', function() {
            brandingEl.style.opacity = brandingSettings.opacity;
        });
        
        liveTVPlayer.on('userinactive', function() {
            brandingEl.style.opacity = brandingSettings.opacity * 0.5;
        });
    }
    
    /**
     * Enhance loading animation based on settings
     */
    function enhanceLoadingAnimation() {
        var animationType = 'professional'; // Would come from settings
        
        if (typeof liveTV !== 'undefined' && liveTV.customization && liveTV.customization.loadingAnimation) {
            animationType = liveTV.customization.loadingAnimation;
        }
        
        var playerEl = liveTVPlayer.el();
        
        // Add animation type class to player
        playerEl.classList.add('loading-animation-' + animationType);
        
        // Custom loading animations could be implemented here
        switch(animationType) {
            case 'minimal':
                // Minimal loading style already in CSS
                break;
            case 'pulsing':
                // Pulsing animation style
                break;
            case 'dots':
                // Animated dots style
                break;
            case 'professional':
            default:
                // Professional style already implemented
                break;
        }
    }
    
    /**
     * Apply custom styling and control bar enhancements
     */
    function applyCustomStyling() {
        var customSettings = {
            controlBarStyle: 'professional',
            transitionEffects: true,
            hoverEffects: true
        };
        
        if (typeof liveTV !== 'undefined' && liveTV.customization) {
            customSettings = Object.assign(customSettings, liveTV.customization);
        }
        
        var playerEl = liveTVPlayer.el();
        
        // Apply control bar style
        playerEl.classList.add('control-bar-' + customSettings.controlBarStyle);
        
        // Apply transition effects
        if (!customSettings.transitionEffects) {
            playerEl.classList.add('no-transitions');
        }
        
        // Apply hover effects
        if (!customSettings.hoverEffects) {
            playerEl.classList.add('no-hover-effects');
        }
        
        // Apply custom CSS if provided
        if (liveTV && liveTV.customization && liveTV.customization.customCss) {
            var style = document.createElement('style');
            style.textContent = liveTV.customization.customCss;
            document.head.appendChild(style);
        }
    }
    
    /**
     * Professional player state management
     */
    function enhancePlayerStates() {
        if (!liveTVPlayer) return;
        
        liveTVPlayer.on('loadstart', function() {
            liveTVPlayer.el().classList.add('vjs-loading-professional');
        });
        
        liveTVPlayer.on('canplay', function() {
            liveTVPlayer.el().classList.remove('vjs-loading-professional');
        });
        
        liveTVPlayer.on('play', function() {
            liveTVPlayer.el().classList.add('vjs-playing-professional');
            liveTVPlayer.el().classList.remove('vjs-paused-professional');
        });
        
        liveTVPlayer.on('pause', function() {
            liveTVPlayer.el().classList.remove('vjs-playing-professional');
            liveTVPlayer.el().classList.add('vjs-paused-professional');
        });
        
        liveTVPlayer.on('ended', function() {
            liveTVPlayer.el().classList.remove('vjs-playing-professional', 'vjs-paused-professional');
        });
    }
    
    // Initialize enhanced player states
    if (liveTVPlayer) {
        enhancePlayerStates();
    }
    
    /**
     * Setup professional center play button with welcome overlay
     */
    function setupCenterPlayButton() {
        if (!liveTVPlayer) return;
        
        var playerEl = liveTVPlayer.el();
        
        // Create welcome overlay
        var playOverlay = document.createElement('div');
        playOverlay.className = 'vjs-play-overlay';
        
        // Check center play button settings
        var centerPlaySetting = 'true';
        var welcomeTemplate = 'Welcome to {channel_name}';
        
        if (typeof liveTV !== 'undefined' && liveTV.customization) {
            centerPlaySetting = liveTV.customization.centerPlayButton || 'true';
            welcomeTemplate = liveTV.customization.welcomeMessage || 'Welcome to {channel_name}';
        }
        
        // Apply CSS classes based on settings
        playerEl.classList.remove('play-button-minimal', 'play-button-hidden');
        
        if (centerPlaySetting === 'false') {
            playerEl.classList.add('play-button-hidden');
            return;
        } else if (centerPlaySetting === 'minimal') {
            playerEl.classList.add('play-button-minimal');
        }
        
        // Get current channel name if available
        var channelName = 'Live TV Streaming';
        var activeChannel = document.querySelector('.channel-item.active .channel-name');
        if (activeChannel) {
            channelName = activeChannel.textContent.trim();
        }
        
        // Generate welcome message
        var welcomeMessage = welcomeTemplate.replace('{channel_name}', channelName);
        
        // Create overlay content based on settings
        if (centerPlaySetting === 'minimal') {
            playOverlay.innerHTML = ''; // No overlay for minimal mode
        } else {
            playOverlay.innerHTML = `
                <div class="welcome-message">${escapeHtml(welcomeMessage)}</div>
                <div class="welcome-subtitle">Click the play button to start streaming</div>
            `;
        }
        
        playerEl.appendChild(playOverlay);
        
        // Add text to play button
        liveTVPlayer.ready(function() {
            var bigPlayButton = playerEl.querySelector('.vjs-big-play-button');
            if (bigPlayButton) {
                // Add "Start Streaming" text below the button
                var playButtonText = document.createElement('div');
                playButtonText.className = 'play-button-text';
                playButtonText.textContent = 'Start Streaming';
                bigPlayButton.appendChild(playButtonText);
                
                // Enhanced click handler
                bigPlayButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Add visual feedback
                    bigPlayButton.style.transform = 'translate(-50%, -50%) scale(0.95)';
                    
                    setTimeout(function() {
                        // Start playing the first channel if no channel is loaded
                        if (currentChannels.length > 0 && !liveTVPlayer.src()) {
                            loadChannelById(currentChannels[0].id);
                        }
                        
                        // Trigger play
                        var playPromise = liveTVPlayer.play();
                        if (playPromise) {
                            playPromise.then(function() {
                                // Hide overlay on successful play
                                playOverlay.style.opacity = '0';
                                setTimeout(function() {
                                    playOverlay.style.display = 'none';
                                }, 400);
                            }).catch(function(error) {
                                console.log('Play failed:', error);
                                // Reset button transform
                                bigPlayButton.style.transform = 'translate(-50%, -50%) scale(1)';
                            });
                        }
                    }, 100);
                });
                
                // Update play button text when channel changes
                liveTVPlayer.on('loadstart', function() {
                    var currentChannelEl = document.querySelector('.channel-item.active .channel-name');
                    if (currentChannelEl && centerPlaySetting !== 'minimal') {
                        var newChannelName = currentChannelEl.textContent.trim();
                        var newWelcomeMessage = welcomeTemplate.replace('{channel_name}', newChannelName);
                        var welcomeMsg = playOverlay.querySelector('.welcome-message');
                        if (welcomeMsg) {
                            welcomeMsg.textContent = newWelcomeMessage;
                        }
                    }
                });
                
                // Show overlay when paused
                liveTVPlayer.on('pause', function() {
                    playOverlay.style.display = 'flex';
                    playOverlay.style.opacity = '1';
                });
                
                // Hide overlay when playing
                liveTVPlayer.on('play', function() {
                    playOverlay.style.opacity = '0';
                    setTimeout(function() {
                        if (!liveTVPlayer.paused()) {
                            playOverlay.style.display = 'none';
                        }
                    }, 400);
                });
            }
        });
    }
    
    /**
     * Update center play button for channel changes
     */
    function updateCenterPlayButton(channel) {
        if (!channel) return;
        
        var playOverlay = document.querySelector('.vjs-play-overlay');
        if (playOverlay) {
            var welcomeMsg = playOverlay.querySelector('.welcome-message');
            if (welcomeMsg) {
                // Get welcome template from settings
                var welcomeTemplate = 'Welcome to {channel_name}';
                if (typeof liveTV !== 'undefined' && liveTV.customization && liveTV.customization.welcomeMessage) {
                    welcomeTemplate = liveTV.customization.welcomeMessage;
                }
                
                var newWelcomeMessage = welcomeTemplate.replace('{channel_name}', channel.name);
                welcomeMsg.textContent = newWelcomeMessage;
            }
        }
    }
    
    // Make new functions globally available
    window.showChannelOptions = showChannelOptions;
    window.hideChannelOptions = hideChannelOptions;
    window.showChannelDetailedInfo = showChannelDetailedInfo;
    window.hideChannelDetailedInfo = hideChannelDetailedInfo;
    window.setupProfessionalCustomizations = setupProfessionalCustomizations;
    
});