/**
 * js/scale.js
 * Handles dynamic scaling of the Zabbix Wallboard grid.
 *
 * - Defines $.fn.scaledgrid which sets up resize behaviour for #wallboard-grid
 * - A small initializer tries to attach the plugin (with retries)
 */

(function ($) {
    'use strict';

    // Plugin: attach scaling behaviour to a container
    $.fn.scaledgrid = function () {
        const container = $(this);
        if (!container.length) return this;

        const resize = () => {
            const appBarHeight = $('.app-bar').outerHeight() || 0;
            const windowHeight = $(window).height() - appBarHeight;
            const tiles = container.find('.tile-wide:visible');

            // Ensure container takes available height even when there are no tiles
            container.css('min-height', windowHeight + 'px');

            if (!tiles.length) {
                return this;
            }

            if (tiles.length === 1) {
                // Single tile: make it occupy the viewport height
                container.css({
                    display: 'block',
                    'text-align': 'center'
                });
                tiles.css({
                    width: '100%',
                    height: windowHeight + 'px',
                    'max-width': 'none',
                    'max-height': 'none',
                    margin: '0 auto',
                    'font-size': '2.5rem',
                    display: 'flex',
                    'flex-direction': 'column',
                    'justify-content': 'center',
                    'align-items': 'center'
                });
            } else {
                // Multiple tiles: reset any per-tile inline styles and use flex layout
                container.css({
                    display: 'flex',
                    'flex-wrap': 'wrap',
                    'align-content': 'flex-start',
                    'justify-content': 'center'
                });

                tiles.css({
                    width: '',
                    height: '',
                    'max-width': '',
                    'max-height': '',
                    margin: '',
                    'font-size': '',
                    display: '',
                    'flex-direction': '',
                    'justify-content': '',
                    'align-items': ''
                });
            }

            return this;
        };

        // Attach resize handler once (namespaced)
        $(window).off('resize.scaledgrid').on('resize.scaledgrid', resize);

        // Run immediately to size correctly now
        resize();

        return this;
    };

    // Document ready: try to initialize the grid; retry a few times if tiles aren't present yet
    $(function () {
        let retries = 0;
        const maxRetries = 12;
        const retryDelay = 200; // ms

        const tryInit = () => {
            const grid = $('#wallboard-grid');
            if (grid.length && grid.find('.tile-wide').length > 0) {
                grid.scaledgrid();
                console.log('scaledgrid: Initialized on #wallboard-grid');
                return;
            }

            if (retries < maxRetries) {
                retries++;
                setTimeout(tryInit, retryDelay);
                return;
            }

            // final attempt even if there are no tiles (ensures container min-height set)
            if (grid.length) {
                grid.scaledgrid();
                console.log('scaledgrid: Initialized on #wallboard-grid (no tiles found)');
            } else {
                console.log('scaledgrid: #wallboard-grid not found in DOM');
            }
        };

        tryInit();
    });
})(jQuery);
