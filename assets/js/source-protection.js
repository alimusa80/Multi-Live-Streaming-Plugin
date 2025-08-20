// Source Protection and Anti-Debug System
(function() {
    'use strict';
    
    // Obfuscation helper
    var _0x2f4a = function(x) { return String.fromCharCode(x + 13); };
    var _0x3b5c = function(s) { return btoa(s).split('').reverse().join(''); };
    
    // Anti-debug protection
    var devtools = {
        open: false,
        orientation: null
    };
    
    // Detect developer tools
    function detectDevTools() {
        var threshold = 160;
        var devtoolsOpened = false;
        
        if (window.outerHeight - window.innerHeight > threshold || 
            window.outerWidth - window.innerWidth > threshold) {
            devtoolsOpened = true;
        }
        
        if (devtoolsOpened !== devtools.open) {
            devtools.open = devtoolsOpened;
            if (devtoolsOpened) {
                // Hide sensitive content when devtools open
                hideSensitiveContent();
                showDevToolsWarning();
            } else {
                // Restore content when devtools closed
                restoreSensitiveContent();
                hideDevToolsWarning();
            }
        }
    }
    
    // Hide sensitive stream URLs and content
    function hideSensitiveContent() {
        // Obfuscate stream URLs in DOM
        var videoElements = document.querySelectorAll('video');
        videoElements.forEach(function(video) {
            if (video.src) {
                video.setAttribute('data-original-src', video.src);
                video.src = 'data:video/mp4;base64,';
                video.setAttribute('data-obfuscated', 'true');
            }
        });
        
        // Hide AJAX requests in network tab (but allow frontend channel loading)
        window.originalFetch = window.fetch;
        window.fetch = function(...args) {
            if (args[0] && args[0].includes && args[0].includes('admin-ajax.php')) {
                // Allow get_stream_url for frontend functionality
                if (args[1] && args[1].body && args[1].body.includes('get_stream_url')) {
                    return window.originalFetch.apply(this, args);
                }
                console.clear();
                return new Promise(resolve => {
                    setTimeout(() => {
                        resolve(new Response('{"success":false,"data":"Developer tools detected"}'));
                    }, 100);
                });
            }
            return window.originalFetch.apply(this, args);
        };
        
        // Obfuscate jQuery AJAX (but allow frontend channel loading)
        if (window.jQuery) {
            window.jQuery.ajaxSetup({
                beforeSend: function(xhr, settings) {
                    // Allow get_stream_url for frontend functionality
                    if (settings.data && settings.data.includes('get_stream_url')) {
                        return true;
                    }
                    console.clear();
                    return false;
                }
            });
        }
    }
    
    // Restore content when devtools closed
    function restoreSensitiveContent() {
        var videoElements = document.querySelectorAll('video[data-obfuscated="true"]');
        videoElements.forEach(function(video) {
            var originalSrc = video.getAttribute('data-original-src');
            if (originalSrc) {
                video.src = originalSrc;
                video.removeAttribute('data-obfuscated');
                video.removeAttribute('data-original-src');
            }
        });
        
        // Restore fetch
        if (window.originalFetch) {
            window.fetch = window.originalFetch;
        }
        
        // Restore jQuery AJAX
        if (window.jQuery) {
            window.jQuery.ajaxSetup({
                beforeSend: function() {
                    return true;
                }
            });
        }
    }
    
    // Show warning overlay
    function showDevToolsWarning() {
        if (document.getElementById('devtools-warning')) return;
        
        var warning = document.createElement('div');
        warning.id = 'devtools-warning';
        warning.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.95);
            color: #ff4444;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 999999;
            font-family: Arial, sans-serif;
            font-size: 24px;
            text-align: center;
            backdrop-filter: blur(10px);
        `;
        warning.innerHTML = `
            <div>
                <h2>⚠️ Developer Tools Detected</h2>
                <p>Content is protected. Please close developer tools to continue.</p>
                <p style="font-size: 14px; opacity: 0.7;">This helps protect streaming sources and user privacy.</p>
            </div>
        `;
        document.body.appendChild(warning);
    }
    
    // Hide warning overlay
    function hideDevToolsWarning() {
        var warning = document.getElementById('devtools-warning');
        if (warning) {
            warning.remove();
        }
    }
    
    // Console protection
    function protectConsole() {
        // Clear console periodically
        setInterval(() => {
            console.clear();
        }, 5000);
        
        // Override console methods
        var noop = function() {};
        ['log', 'info', 'warn', 'error', 'debug', 'trace'].forEach(function(method) {
            console[method] = noop;
        });
        
        // Prevent console.log from showing sensitive data
        var originalLog = console.log;
        console.log = function(...args) {
            var filteredArgs = args.map(arg => {
                if (typeof arg === 'string' && (arg.includes('http') || arg.includes('stream'))) {
                    return '[PROTECTED]';
                }
                return arg;
            });
            return originalLog.apply(console, filteredArgs);
        };
    }
    
    // Keyboard shortcut protection
    function protectKeyboardShortcuts() {
        document.addEventListener('keydown', function(e) {
            // Disable F12, Ctrl+Shift+I, Ctrl+Shift+J, Ctrl+U
            if (e.keyCode === 123 || 
                (e.ctrlKey && e.shiftKey && (e.keyCode === 73 || e.keyCode === 74)) ||
                (e.ctrlKey && e.keyCode === 85)) {
                e.preventDefault();
                e.stopPropagation();
                showDevToolsWarning();
                return false;
            }
        });
        
        // Disable right-click context menu
        document.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            return false;
        });
        
        // Disable text selection
        document.addEventListener('selectstart', function(e) {
            if (e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA') {
                e.preventDefault();
                return false;
            }
        });
    }
    
    // Source code obfuscation
    function obfuscateSourceCode() {
        // Remove script tags from DOM after execution
        setTimeout(() => {
            var scripts = document.querySelectorAll('script[src*="frontend-init.js"]');
            scripts.forEach(script => {
                script.remove();
            });
        }, 2000);
        
        // Obfuscate inline scripts
        var inlineScripts = document.querySelectorAll('script:not([src])');
        inlineScripts.forEach(script => {
            if (script.innerHTML.includes('liveTVAutoplay')) {
                var obfuscated = _0x3b5c(script.innerHTML);
                script.innerHTML = `eval(atob('${obfuscated}'.split('').reverse().join('')))`;
            }
        });
    }
    
    // Anti-debugger statements
    function antiDebugger() {
        setInterval(() => {
            debugger;
        }, 4000);
        
        // Random function names to confuse debugging
        window[_0x3b5c('protection')] = function() {
            return false;
        };
    }
    
    // Initialize protection systems
    function initProtection() {
        // Only run in production (not localhost)
        if (location.hostname === 'localhost' || location.hostname === '127.0.0.1') {
            return;
        }
        
        protectConsole();
        protectKeyboardShortcuts();
        obfuscateSourceCode();
        
        // Start devtools detection
        setInterval(detectDevTools, 500);
        
        // Anti-debugger (less aggressive)
        if (Math.random() > 0.7) {
            antiDebugger();
        }
        
        // Protect against common debugging techniques
        window.addEventListener('beforeunload', function() {
            console.clear();
        });
        
        // Disable common debugging tools detection
        Object.defineProperty(window, 'console', {
            get: function() {
                return {
                    log: function() {},
                    warn: function() {},
                    error: function() {},
                    info: function() {},
                    debug: function() {},
                    clear: function() {}
                };
            }
        });
    }
    
    // Start protection when DOM is loaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initProtection);
    } else {
        initProtection();
    }
    
    // Export for debugging purposes (obfuscated)
    window[_0x3b5c('sourceProtection')] = {
        status: 'active',
        version: '1.0.0'
    };
    
})();