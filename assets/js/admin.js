(function($) {
    'use strict';
    
    var LiveTVAdmin = {
        init: function() {
            if ($('#channel-form').length && $('#channels-list').length) {
                this.initCompatibilityMode();
                return;
            }
            this.initBasicFeatures();
        },
        
        initCompatibilityMode: function() {
            this.ensureNonceField();
            this.setupBasicSecurity();
        },
        
        initBasicFeatures: function() {
            this.ensureNonceField();
            this.setupBasicSecurity();
            this.bindBasicEvents();
            this.setupNotifications();
        },
        
        ensureNonceField: function() {
            if (!$('#live-tv-admin-nonce').length && typeof liveTVAdmin !== 'undefined' && liveTVAdmin.nonce) {
                $('body').append('<input type="hidden" id="live-tv-admin-nonce" value="' + liveTVAdmin.nonce + '">');
            }
        },
        
        setupBasicSecurity: function() {
            $(document).ajaxSend(function(event, xhr, settings) {
                if (settings.url && settings.url.includes('admin-ajax.php')) {
                    var nonce = typeof liveTVAdmin !== 'undefined' ? liveTVAdmin.nonce : $('#live-tv-admin-nonce').val();
                    if (!nonce && settings.data && settings.data.includes('action=')) {
                        console.warn('Live TV Admin: Security nonce missing for AJAX request');
                    }
                }
            });
            
            $('form[id*="live-tv"], form[class*="live-tv"]').on('submit', function() {
                $(this).find('input[type="text"], textarea').each(function() {
                    var value = $(this).val();
                    if (value && typeof value === 'string') {
                        var sanitized = value.replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '');
                        if (sanitized !== value) {
                            $(this).val(sanitized);
                            console.warn('Live TV Admin: Potentially dangerous content removed from form field');
                        }
                    }
                });
            });
        },
        
        bindBasicEvents: function() {
            $('.live-tv-refresh').on('click', function(e) {
                e.preventDefault();
                location.reload();
            });
            
            $('.live-tv-confirm').on('click', function(e) {
                var message = $(this).data('confirm') || 'Are you sure?';
                if (!confirm(message)) {
                    e.preventDefault();
                    return false;
                }
            });
            
            $('button[disabled], input[disabled]').attr('aria-disabled', 'true');
        },
        
        setupNotifications: function() {
            if (!$('#live-tv-notifications').length) {
                $('body').append('<div id="live-tv-notifications" style="position: fixed; top: 32px; right: 20px; z-index: 999999;"></div>');
            }
            window.liveTVShowNotification = this.showNotification;
        },
        
        showNotification: function(message, type) {
            type = type || 'info';
            
            var iconClass = {
                'success': 'dashicons-yes',
                'error': 'dashicons-warning',
                'warning': 'dashicons-flag',
                'info': 'dashicons-info'
            }[type] || 'dashicons-info';
            
            var notification = $('<div class="live-tv-notification notice notice-' + type + '">' +
                '<span class="dashicons ' + iconClass + '"></span>' +
                '<span class="message">' + LiveTVAdmin.escapeHtml(message) + '</span>' +
                '<button type="button" class="notice-dismiss"><span class="dashicons dashicons-dismiss"></span></button>' +
                '</div>');
            
            $('#live-tv-notifications').append(notification);
            
            notification.find('.notice-dismiss').on('click', function() {
                notification.fadeOut(300, function() {
                    $(this).remove();
                });
            });
            
            if (type !== 'error') {
                setTimeout(function() {
                    if (notification.is(':visible')) {
                        notification.fadeOut(300, function() {
                            $(this).remove();
                        });
                    }
                }, 5000);
            }
        },
        
        /**
         * Escape HTML to prevent XSS
         * @param {string} text - Text to escape
         * @returns {string} Escaped text
         */
        escapeHtml: function(text) {
            if (!text) return '';
            var map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, function(m) { 
                return map[m]; 
            });
        },
        
        /**
         * Utility function for debouncing
         * @param {Function} func - Function to debounce
         * @param {number} wait - Wait time in milliseconds
         * @returns {Function} Debounced function
         */
        debounce: function(func, wait) {
            var timeout;
            return function executedFunction() {
                var context = this;
                var args = arguments;
                var later = function() {
                    clearTimeout(timeout);
                    func.apply(context, args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    };
    
    /**
     * Initialize when DOM is ready
     */
    $(document).ready(function() {
        LiveTVAdmin.init();
    });
    
    /**
     * Make LiveTVAdmin available globally for compatibility
     */
    window.LiveTVAdmin = LiveTVAdmin;
    
})(jQuery);

/**
 * Basic CSS for notifications
 */
(function() {
    var css = `
        #live-tv-notifications {
            position: fixed !important;
            top: 32px !important;
            right: 20px !important;
            z-index: 999999 !important;
            max-width: 350px;
        }
        
        .live-tv-notification {
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
            padding: 12px 16px !important;
            margin-bottom: 8px !important;
            border-radius: 6px !important;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1) !important;
            animation: slideInRight 0.3s ease-out !important;
        }
        
        .live-tv-notification .dashicons {
            flex-shrink: 0 !important;
        }
        
        .live-tv-notification .message {
            flex: 1 !important;
            font-size: 14px !important;
            line-height: 1.4 !important;
        }
        
        .live-tv-notification .notice-dismiss {
            flex-shrink: 0 !important;
            background: none !important;
            border: none !important;
            cursor: pointer !important;
            padding: 0 !important;
            margin: 0 !important;
            width: 20px !important;
            height: 20px !important;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @media (max-width: 782px) {
            #live-tv-notifications {
                top: 46px !important;
                right: 10px !important;
                max-width: calc(100% - 20px);
            }
        }
    `;
    
    // Inject CSS
    var style = document.createElement('style');
    style.textContent = css;
    document.head.appendChild(style);
})();