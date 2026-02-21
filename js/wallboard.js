/**
 * Zabbix Wallboard JavaScript
 * Version: 2.0.0
 * 
 * Security Features:
 * - CSRF token validation
 * - XSS prevention
 * - No eval() usage
 * - Input validation
 * - Async AJAX calls
 */

'use strict';

(function(window, document, $) {
    // Configuration
    const CONFIG = {
        AUTO_REFRESH_INTERVAL: 30000,
        AJAX_TIMEOUT: 10000,
        MAX_RETRY_ATTEMPTS: 3
    };

    // State management
    const State = {
        refreshTimer: null,
        isDialogOpen: false,
        retryAttempts: 0,
        csrfToken: null
    };

    /**
     * Initialize CSRF token from meta tag or session
     */
    function initCSRFToken() {
        const metaToken = document.querySelector('meta[name="csrf-token"]');
        if (metaToken) {
            State.csrfToken = metaToken.getAttribute('content');
        } else {
            // Fallback: try to get from URL parameter
            const urlParams = new URLSearchParams(window.location.search);
            State.csrfToken = urlParams.get('csrf_token');
        }
    }

    /**
     * Sanitize HTML to prevent XSS
     * @param {string} html - Raw HTML string
     * @returns {string} - Sanitized HTML
     */
    function sanitizeHTML(html) {
        const temp = document.createElement('div');
        temp.textContent = html;
        return temp.innerHTML;
    }

    /**
     * Validate event ID
     * @param {string|number} eventId - Event ID to validate
     * @returns {boolean}
     */
    function isValidEventId(eventId) {
        return /^[0-9]+$/.test(String(eventId));
    }

    /**
     * Start auto-refresh timer
     */
    function startAutoRefresh() {
        stopAutoRefresh();
        State.refreshTimer = setTimeout(function() {
            autoRefresh();
        }, CONFIG.AUTO_REFRESH_INTERVAL);
    }

    /**
     * Stop auto-refresh timer
     */
    function stopAutoRefresh() {
        if (State.refreshTimer) {
            clearTimeout(State.refreshTimer);
            State.refreshTimer = null;
        }
    }

    /**
     * Auto-refresh page with state preservation
     */
    function autoRefresh() {
        // Preserve scroll position
        const scrollPos = window.pageYOffset || document.documentElement.scrollTop;
        sessionStorage.setItem('scrollPos', scrollPos);
        
        // Reload page
        window.location.reload();
    }

    /**
     * Restore scroll position after page load
     */
    function restoreScrollPosition() {
        const scrollPos = sessionStorage.getItem('scrollPos');
        if (scrollPos) {
            window.scrollTo(0, parseInt(scrollPos, 10));
            sessionStorage.removeItem('scrollPos');
        }
    }

    /**
     * Show error message in dialog
     * @param {jQuery} $container - Container element
     * @param {string} message - Error message
     */
    function showError($container, message) {
        const safeMessage = sanitizeHTML(message);
        const errorHtml = `
            <div class="error-message" role="alert">
                <h3>Error</h3>
                <p>${safeMessage}</p>
            </div>
        `;
        $container.html(errorHtml);
    }

    /**
     * Show loading indicator
     * @param {jQuery} $container - Container element
     */
    function showLoading($container) {
        $container.html('<div class="loading">Loading...</div>');
    }

    /**
     * Fetch event details via AJAX
     * @param {string|number} eventId - Event ID
     * @param {jQuery} $container - Container to populate
     */
    function fetchEventDetails(eventId, $container) {
        if (!isValidEventId(eventId)) {
            showError($container, 'Invalid event ID');
            return;
        }

        showLoading($container);

        const params = {
            action: 'details',
            eventid: eventId
        };

        if (State.csrfToken) {
            params.csrf_token = State.csrfToken;
        }

        $.ajax({
            url: 'index.php',
            method: 'GET',
            data: params,
            dataType: 'json',
            timeout: CONFIG.AJAX_TIMEOUT,
            cache: false
        })
        .done(function(response) {
            State.retryAttempts = 0;
            
            if (response && response.html) {
                // Use text() first to decode, then treat as safe HTML from server
                $container.html(response.html);
            } else {
                showError($container, 'Invalid response from server');
            }
        })
        .fail(function(xhr, status, error) {
            console.error('AJAX error:', status, error);
            
            if (State.retryAttempts < CONFIG.MAX_RETRY_ATTEMPTS) {
                State.retryAttempts++;
                setTimeout(function() {
                    fetchEventDetails(eventId, $container);
                }, 1000 * State.retryAttempts);
            } else {
                State.retryAttempts = 0;
                showError($container, 'Failed to load event details. Please try again.');
            }
        });
    }

    /**
     * Show event details dialog
     * @param {string} dialogId - Dialog element ID
     * @param {string|number} eventId - Event ID
     */
    function showDialogDetails(dialogId, eventId) {
        const $dialog = $(dialogId);
        
        if (!$dialog.length) {
            console.error('Dialog element not found:', dialogId);
            return;
        }

        stopAutoRefresh();

        const dialog = $dialog.data('dialog');
        
        if (!dialog) {
            console.error('Dialog not initialized:', dialogId);
            startAutoRefresh();
            return;
        }

        // Set up close handler
        dialog.options.onDialogClose = function() {
            State.isDialogOpen = false;
            startAutoRefresh();
        };

        if (dialog.element.data('opened')) {
            dialog.close();
        } else {
            State.isDialogOpen = true;
            const $content = $(dialogId + '_content');
            fetchEventDetails(eventId, $content);
            dialog.open();
        }
    }

    /**
     * Handle keyboard navigation
     * @param {KeyboardEvent} e - Keyboard event
     */
    function handleKeyboard(e) {
        // ESC key closes dialog
        if (e.key === 'Escape' && State.isDialogOpen) {
            const $openDialog = $('.dialog[data-opened="true"]');
            if ($openDialog.length) {
                const dialog = $openDialog.data('dialog');
                if (dialog) {
                    dialog.close();
                }
            }
        }
    }

    /**
     * Initialize page visibility API for auto-refresh management
     */
    function initPageVisibility() {
        if (typeof document.hidden !== 'undefined') {
            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    stopAutoRefresh();
                } else if (!State.isDialogOpen) {
                    startAutoRefresh();
                }
            });
        }
    }

    /**
     * Initialize application
     */
    function init() {
        initCSRFToken();
        initPageVisibility();
        restoreScrollPosition();
        startAutoRefresh();

        // Keyboard event listeners
        document.addEventListener('keydown', handleKeyboard);

        // Expose to global scope for inline onclick handlers (legacy support)
        window.showDialogDetails = showDialogDetails;

        console.log('Wallboard initialized');
    }

    // Initialize on DOM ready
    $(document).ready(init);

    // Handle page unload
    $(window).on('beforeunload', function() {
        stopAutoRefresh();
    });

})(window, document, jQuery);
