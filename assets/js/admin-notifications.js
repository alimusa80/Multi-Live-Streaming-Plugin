/**
 * Live TV Admin Notifications
 * 
 * Handles dismissible admin notices and update checking functionality
 * 
 * @package LiveTVStreaming
 * @since 3.2.0
 */

(function($) {
    'use strict';
    
    var LiveTVNotifications = {
        
        init: function() {
            this.bindEvents();
            this.setupAutoCheck();
        },
        
        bindEvents: function() {
            // Handle dismissible notices
            $(document).on('click', '.notice-dismiss', this.dismissNotice);
            
            // Handle manual update check
            $(document).on('click', '#live-tv-check-updates', this.checkUpdates);
            
            // Handle update reminder
            $(document).on('click', '.live-tv-update-reminder', this.showUpdateReminder);
        },
        
        dismissNotice: function(e) {
            var $notice = $(this).closest('.notice');
            var noticeType = $notice.data('notice');
            var version = $notice.data('version') || '';
            
            if (!noticeType || !liveTVNotifications.nonce) {
                return;
            }
            
            // Send AJAX request to dismiss notice
            $.post(liveTVNotifications.ajax_url, {
                action: 'live_tv_dismiss_notice',
                nonce: liveTVNotifications.nonce,
                notice_type: noticeType,
                version: version
            });
        },
        
        checkUpdates: function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $status = $('#live-tv-update-status');
            var originalText = $button.text();
            
            // Show loading state
            $button.prop('disabled', true).html(
                '<span class="dashicons dashicons-update live-tv-update-spinner"></span> ' + 
                liveTVNotifications.strings.checking
            );
            
            if ($status.length) {
                $status.text(liveTVNotifications.strings.checking).removeClass('error success');
            }
            
            // Make AJAX request
            $.post(liveTVNotifications.ajax_url, {
                action: 'live_tv_check_update_status',
                nonce: liveTVNotifications.nonce
            })
            .done(function(response) {
                if (response.success) {
                    var data = response.data;
                    
                    if (data.update_available) {
                        var message = liveTVNotifications.strings.update_available + 
                                    ' (v' + data.new_version + ')';
                        
                        if ($status.length) {
                            $status.html(
                                message + 
                                ' <a href="' + data.update_url + '" class="button button-primary button-small">' +
                                'Update Now</a>'
                            ).addClass('success');
                        } else {
                            LiveTVNotifications.showUpdateNotice(data);
                        }
                    } else {
                        var upToDateMessage = liveTVNotifications.strings.up_to_date + 
                                            ' (v' + data.current_version + ')';
                        
                        if ($status.length) {
                            $status.text(upToDateMessage).addClass('success');
                        }
                    }
                } else {
                    if ($status.length) {
                        $status.text(liveTVNotifications.strings.error).addClass('error');
                    }
                }
            })
            .fail(function() {
                if ($status.length) {
                    $status.text(liveTVNotifications.strings.error).addClass('error');
                }
            })
            .always(function() {
                // Reset button state
                $button.prop('disabled', false).text(originalText);
            });
        },
        
        showUpdateNotice: function(data) {
            // Create update notice dynamically
            var noticeHtml = 
                '<div class="notice notice-success is-dismissible" data-notice="update_available" data-version="' + data.new_version + '">' +
                    '<div style="display: flex; align-items: center; padding: 8px 0;">' +
                        '<div style="margin-right: 15px;">' +
                            '<span class="dashicons dashicons-update" style="color: #00a32a; font-size: 24px;"></span>' +
                        '</div>' +
                        '<div style="flex: 1;">' +
                            '<h3 style="margin: 0 0 5px 0;">Live TV Streaming Pro Update Available</h3>' +
                            '<p style="margin: 0;">Version ' + data.new_version + ' is now available. Update now to get the latest features.</p>' +
                        '</div>' +
                        '<div style="margin-left: 15px;">' +
                            '<a href="' + data.update_url + '" class="button button-primary">Update Now</a>' +
                        '</div>' +
                    '</div>' +
                    '<button type="button" class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></button>' +
                '</div>';
            
            // Insert notice after the first heading
            $('.wrap h1').first().after(noticeHtml);
            
            // Initialize dismiss functionality for new notice
            $('.notice-dismiss').off('click').on('click', this.dismissNotice);
        },
        
        showUpdateReminder: function(e) {
            e.preventDefault();
            
            var reminderHtml = 
                '<div class="live-tv-update-reminder-modal">' +
                    '<div class="live-tv-modal-overlay"></div>' +
                    '<div class="live-tv-modal-content">' +
                        '<h2>Update Available</h2>' +
                        '<p>A new version of Live TV Streaming Pro is available. Would you like to update now?</p>' +
                        '<div class="live-tv-modal-actions">' +
                            '<button type="button" class="button button-primary live-tv-update-now">Update Now</button>' +
                            '<button type="button" class="button live-tv-remind-later">Remind Me Later</button>' +
                            '<button type="button" class="button live-tv-close-modal">Close</button>' +
                        '</div>' +
                    '</div>' +
                '</div>';
            
            $('body').append(reminderHtml);
            
            // Handle modal actions
            $('.live-tv-update-now').on('click', function() {
                window.location.href = $(e.target).attr('href');
            });
            
            $('.live-tv-remind-later, .live-tv-close-modal, .live-tv-modal-overlay').on('click', function() {
                $('.live-tv-update-reminder-modal').remove();
            });
        },
        
        setupAutoCheck: function() {
            // Check for updates every 6 hours when admin is active
            if (typeof liveTVNotifications.auto_check !== 'undefined' && liveTVNotifications.auto_check) {
                setInterval(function() {
                    LiveTVNotifications.backgroundUpdateCheck();
                }, 6 * 60 * 60 * 1000); // 6 hours
            }
        },
        
        backgroundUpdateCheck: function() {
            // Silent background check
            $.post(liveTVNotifications.ajax_url, {
                action: 'live_tv_check_update_status',
                nonce: liveTVNotifications.nonce
            })
            .done(function(response) {
                if (response.success && response.data.update_available) {
                    // Show subtle notification
                    LiveTVNotifications.showSubtleUpdateNotification(response.data);
                }
            });
        },
        
        showSubtleUpdateNotification: function(data) {
            // Only show if not already visible
            if ($('.live-tv-update-bubble').length > 0) {
                return;
            }
            
            var bubbleHtml = 
                '<div class="live-tv-update-bubble">' +
                    '<span class="dashicons dashicons-update"></span>' +
                    '<span>Update available (v' + data.new_version + ')</span>' +
                    '<a href="' + data.update_url + '" class="button button-small">Update</a>' +
                    '<button type="button" class="live-tv-close-bubble">&times;</button>' +
                '</div>';
            
            $('body').append(bubbleHtml);
            
            // Auto-hide after 10 seconds
            setTimeout(function() {
                $('.live-tv-update-bubble').fadeOut();
            }, 10000);
            
            // Handle close button
            $('.live-tv-close-bubble').on('click', function() {
                $('.live-tv-update-bubble').fadeOut();
            });
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        LiveTVNotifications.init();
    });
    
    // Make available globally
    window.LiveTVNotifications = LiveTVNotifications;
    
})(jQuery);

// Add CSS for notifications
jQuery(document).ready(function($) {
    var notificationCSS = `
        <style>
        .live-tv-update-spinner {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        #live-tv-update-status.success {
            color: #00a32a;
            font-weight: 500;
        }
        
        #live-tv-update-status.error {
            color: #d63638;
            font-weight: 500;
        }
        
        .live-tv-update-reminder-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 100000;
        }
        
        .live-tv-modal-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        
        .live-tv-modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.3);
            max-width: 500px;
            width: 90%;
        }
        
        .live-tv-modal-content h2 {
            margin: 0 0 15px 0;
            font-size: 18px;
        }
        
        .live-tv-modal-actions {
            margin-top: 20px;
            text-align: right;
        }
        
        .live-tv-modal-actions .button {
            margin-left: 10px;
        }
        
        .live-tv-update-bubble {
            position: fixed;
            top: 32px;
            right: 20px;
            background: #0073aa;
            color: white;
            padding: 12px 15px;
            border-radius: 6px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.2);
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
        }
        
        .live-tv-update-bubble .dashicons {
            font-size: 16px;
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .live-tv-update-bubble .button {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            font-size: 12px;
            padding: 4px 8px;
            height: auto;
            line-height: 1.2;
        }
        
        .live-tv-update-bubble .button:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .live-tv-close-bubble {
            background: none;
            border: none;
            color: white;
            font-size: 18px;
            cursor: pointer;
            padding: 0;
            margin: 0;
            opacity: 0.7;
        }
        
        .live-tv-close-bubble:hover {
            opacity: 1;
        }
        
        @media (max-width: 782px) {
            .live-tv-update-bubble {
                top: 46px;
                right: 10px;
                max-width: calc(100% - 20px);
                font-size: 12px;
            }
        }
        </style>
    `;
    
    $('head').append(notificationCSS);
});