<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="live-tv-container player-theme-<?php echo esc_attr(get_option('live_tv_player_theme', 'premium-blue')); ?>" style="width: <?php echo esc_attr($atts['width']); ?>;" role="application" aria-label="<?php esc_attr_e('Live TV Streaming Player', 'live-tv-streaming'); ?>">
    
    <!-- Gesture Help Overlay for Touch Devices -->
    <div class="gesture-help-overlay" id="gesture-help" style="display: none;">
        <div class="gesture-help-content">
            <h4><?php _e('Touch Gestures', 'live-tv-streaming'); ?></h4>
            <ul class="gesture-list">
                <li><span class="gesture-icon">👆</span> <?php _e('Tap to play/pause', 'live-tv-streaming'); ?></li>
                <li><span class="gesture-icon">👆👆</span> <?php _e('Double tap for fullscreen', 'live-tv-streaming'); ?></li>
                <li><span class="gesture-icon">👈</span> <?php _e('Swipe left for next channel', 'live-tv-streaming'); ?></li>
                <li><span class="gesture-icon">👉</span> <?php _e('Swipe right for previous channel', 'live-tv-streaming'); ?></li>
                <li><span class="gesture-icon">👆📱</span> <?php _e('Swipe up for channel list', 'live-tv-streaming'); ?></li>
                <li><span class="gesture-icon">👇</span> <?php _e('Swipe down to hide controls', 'live-tv-streaming'); ?></li>
                <li><span class="gesture-icon">🖐️</span> <?php _e('Long press channel for options', 'live-tv-streaming'); ?></li>
            </ul>
            <button class="gesture-help-close" onclick="hideGestureHelp()"><?php _e('Got it!', 'live-tv-streaming'); ?></button>
        </div>
    </div>
    
    <div class="live-tv-player-wrapper" role="main" aria-label="<?php esc_attr_e('Video Player', 'live-tv-streaming'); ?>">
        <div class="live-tv-player-aspect-ratio">
            <video
                id="live-tv-player"
                class="video-js vjs-default-skin"
                controls
                preload="auto"
                data-setup='{"fluid": true, "responsive": true}'
                aria-label="<?php esc_attr_e('Live TV Video Player', 'live-tv-streaming'); ?>"
                tabindex="0">
                <p class="vjs-no-js">
                    <?php _e('To view this video please enable JavaScript, and consider upgrading to a web browser that', 'live-tv-streaming'); ?>
                    <a href="https://videojs.com/html5-video-support/" target="_blank" rel="noopener noreferrer"><?php _e('supports HTML5 video', 'live-tv-streaming'); ?></a>.
                </p>
            </video>
        </div>
    </div>
    
    <div class="live-tv-current-info">
        <h3 id="current-channel-name"><?php _e('Available Channels', 'live-tv-streaming'); ?></h3>
        <div class="live-tv-controls">
            <button class="gesture-help-btn" onclick="showGestureHelp()" aria-label="<?php esc_attr_e('Show gesture help', 'live-tv-streaming'); ?>" title="<?php esc_attr_e('Touch Gestures Help', 'live-tv-streaming'); ?>">
                <span class="help-icon">?</span>
            </button>
        </div>
    </div>
    
    <nav class="live-tv-channels" data-category="<?php echo esc_attr($atts['category']); ?>" role="navigation" aria-label="<?php esc_attr_e('TV Channels', 'live-tv-streaming'); ?>">
        <div class="channels-grid" id="channels-grid" role="list" aria-labelledby="current-channel-name">
            <!-- Channels will be loaded here -->
        </div>
    </nav>
</div>

<style>
/* Gesture Help Overlay Styles */
.gesture-help-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(5px);
    -webkit-backdrop-filter: blur(5px);
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: fadeIn 0.3s ease-out;
}

.gesture-help-content {
    background: linear-gradient(135deg, #2c2c2c, #1a1a1a);
    border-radius: 16px;
    padding: 30px;
    margin: 20px;
    max-width: 400px;
    width: 100%;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.8);
    border: 1px solid rgba(255, 255, 255, 0.1);
    animation: modalSlideIn 0.4s ease-out;
}

.gesture-help-content h4 {
    color: #fff;
    font-size: 22px;
    font-weight: 600;
    text-align: center;
    margin: 0 0 20px 0;
}

.gesture-list {
    list-style: none;
    padding: 0;
    margin: 0 0 25px 0;
}

.gesture-list li {
    display: flex;
    align-items: center;
    color: rgba(255, 255, 255, 0.9);
    font-size: 14px;
    padding: 8px 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.gesture-list li:last-child {
    border-bottom: none;
}

.gesture-icon {
    font-size: 18px;
    margin-right: 12px;
    min-width: 30px;
    text-align: center;
}

.gesture-help-close {
    width: 100%;
    padding: 12px 24px;
    background: linear-gradient(135deg, #00a0d2, #0073aa);
    color: #fff;
    border: none;
    border-radius: 25px;
    font-weight: 600;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.gesture-help-close:hover,
.gesture-help-close:focus {
    background: linear-gradient(135deg, #00b4e6, #0085be);
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0, 160, 210, 0.3);
}

.live-tv-controls {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-left: auto;
}

.gesture-help-btn {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: #fff;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 14px;
    font-weight: bold;
}

.gesture-help-btn:hover,
.gesture-help-btn:focus {
    background: rgba(0, 160, 210, 0.2);
    border-color: #00a0d2;
    transform: scale(1.1);
}

.live-tv-current-info {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

/* Hide gesture help button on non-touch devices */
@media (hover: hover) and (pointer: fine) {
    .gesture-help-btn {
        display: none;
    }
}

/* Mobile responsiveness */
@media (max-width: 480px) {
    .gesture-help-content {
        margin: 10px;
        padding: 20px;
    }
    
    .gesture-help-content h4 {
        font-size: 18px;
    }
    
    .gesture-list li {
        font-size: 13px;
    }
    
    .gesture-icon {
        font-size: 16px;
        min-width: 25px;
    }
}

/* Prevent body scroll when gesture help is active */
body.gesture-help-active {
    overflow: hidden;
}
</style>