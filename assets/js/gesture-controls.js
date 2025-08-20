/**
 * Live TV Gesture Controls
 * Handles touch gesture help overlay and accessibility features
 */

(function($) {
    'use strict';

    // Initialize gesture controls when DOM is ready
    $(document).ready(function() {
        initGestureControls();
        bindGestureEvents();
        handleAccessibility();
        
        // Auto-show gesture help for touch devices on first visit
        autoShowGestureHelp();
        
        console.log('Live TV gesture controls initialized with autoplay:', window.liveTVAutoplay || 'false');
    });

    /**
     * Initialize gesture control functionality
     */
    function initGestureControls() {
        // Set default autoplay value if not already set
        if (typeof window.liveTVAutoplay === 'undefined') {
            window.liveTVAutoplay = 'false';
        }
    }

    /**
     * Bind gesture-related events
     */
    function bindGestureEvents() {
        // Bind help button click
        $(document).on('click', '.gesture-help-btn', function(e) {
            e.preventDefault();
            showGestureHelp();
        });

        // Bind close button click
        $(document).on('click', '.gesture-help-close', function(e) {
            e.preventDefault();
            hideGestureHelp();
        });

        // Bind overlay click to close
        $(document).on('click', '.gesture-help-overlay', function(e) {
            if (e.target === this) {
                hideGestureHelp();
            }
        });
    }

    /**
     * Handle keyboard accessibility
     */
    function handleAccessibility() {
        $(document).on('keydown', function(e) {
            var helpOverlay = document.getElementById('gesture-help');
            if (helpOverlay && helpOverlay.style.display !== 'none') {
                if (e.key === 'Escape' || e.keyCode === 27) {
                    hideGestureHelp();
                }
                
                // Trap focus within modal
                trapFocus(e, helpOverlay);
            }
        });
    }

    /**
     * Show gesture help overlay
     */
    function showGestureHelp() {
        var helpOverlay = document.getElementById('gesture-help');
        if (helpOverlay) {
            helpOverlay.style.display = 'flex';
            helpOverlay.setAttribute('aria-hidden', 'false');
            
            // Focus management for accessibility
            var closeButton = helpOverlay.querySelector('.gesture-help-close');
            if (closeButton) {
                closeButton.focus();
            }
            
            // Prevent body scroll when modal is open
            $('body').addClass('gesture-help-active');
        }
    }

    /**
     * Hide gesture help overlay
     */
    function hideGestureHelp() {
        var helpOverlay = document.getElementById('gesture-help');
        if (helpOverlay) {
            helpOverlay.style.display = 'none';
            helpOverlay.setAttribute('aria-hidden', 'true');
            
            // Return focus to help button
            var helpButton = document.querySelector('.gesture-help-btn');
            if (helpButton) {
                helpButton.focus();
            }
            
            // Allow body scroll again
            $('body').removeClass('gesture-help-active');
            
            // Remember that user has seen the help
            if (typeof(Storage) !== 'undefined') {
                localStorage.setItem('live_tv_gesture_help_seen', 'true');
            }
        }
    }

    /**
     * Auto-show gesture help for touch devices on first visit
     */
    function autoShowGestureHelp() {
        // Check if device supports touch and user hasn't seen help before
        var hasTouch = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
        var hasSeenHelp = localStorage.getItem('live_tv_gesture_help_seen');
        
        if (hasTouch && !hasSeenHelp) {
            // Show help after a short delay
            setTimeout(function() {
                showGestureHelp();
            }, 2000);
        }
    }

    /**
     * Trap focus within modal for accessibility
     * @param {Event} e - Keyboard event
     * @param {Element} modal - Modal element
     */
    function trapFocus(e, modal) {
        var focusableElements = modal.querySelectorAll(
            'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );
        var firstFocusable = focusableElements[0];
        var lastFocusable = focusableElements[focusableElements.length - 1];

        if (e.key === 'Tab' || e.keyCode === 9) {
            if (e.shiftKey) {
                // Shift + Tab
                if (document.activeElement === firstFocusable) {
                    lastFocusable.focus();
                    e.preventDefault();
                }
            } else {
                // Tab
                if (document.activeElement === lastFocusable) {
                    firstFocusable.focus();
                    e.preventDefault();
                }
            }
        }
    }

    // Make functions globally available if needed
    window.showGestureHelp = showGestureHelp;
    window.hideGestureHelp = hideGestureHelp;

})(jQuery);