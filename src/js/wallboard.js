/**
 * js/wallboard.js
 * Main JavaScript for Wallboard
 * - Clock
 * - Dropdowns
 * - Auto-refresh (AJAX)
 * - Responsive scaled grid using ResizeObserver
 * - Auto-hide mouse cursor after inactivity
 */
(function ($) {
    'use strict';

    /* ---------------------------------------------------------------------
     * CLOCK
     * ------------------------------------------------------------------- */
    const formatter = new Intl.DateTimeFormat('nl-NL', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        hour12: false
    });

    function updateClock() {
        $('#clock').text(formatter.format(new Date()));
    }

    /* ---------------------------------------------------------------------
     * DROPDOWNS
     * ------------------------------------------------------------------- */
    function initDropdowns() {
        $('.dropdown-toggle')
            .off('click.wallboard')
            .on('click.wallboard', function (e) {
                e.preventDefault();
                e.stopPropagation();

                const menu = $(this).next('.d-menu');
                $('.d-menu').not(menu).hide();
                menu.toggle();
            });

        $(document)
            .off('click.wallboard')
            .on('click.wallboard', function () {
                $('.d-menu').hide();
            });
    }

    /* ---------------------------------------------------------------------
     * AUTO REFRESH
     * ------------------------------------------------------------------- */
    function initAutoRefresh() {
        const refreshInterval =
            parseInt($('meta[name="refresh-interval"]').attr('content'), 10) || 30000;

        setInterval(function () {
            $.ajax({
                url: window.location.href,
                type: 'GET',
                dataType: 'json',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                success: function (response) {
                    if (response && response.html) {
                        $('#main-content').html(response.html);
                        $(window).trigger('wallboard:content-updated');
                    }
                },
                error: function () {
                    console.error('Failed to refresh wallboard');
                }
            });
        }, refreshInterval);
    }

    /* ---------------------------------------------------------------------
     * SCALED GRID PLUGIN
     * ------------------------------------------------------------------- */
    $.fn.scaledgrid = function () {
        const container = this;
        if (!container.length) return this;

        const ns = '.scaledgrid';

        const getAppBarHeight = () => {
            const appBar = $('.app-bar');
            return appBar.length ? appBar.outerHeight() : 0;
        };

        const resize = () => {
            const availableHeight = Math.max(
                window.innerHeight - getAppBarHeight(),
                0
            );

            const tiles = container.find('.tile-wide:visible');
            const count = tiles.length;

            container.css({
                height: availableHeight + 'px',
                minHeight: availableHeight + 'px',
                display: 'flex',
                flexWrap: 'wrap',
                alignItems: 'center',
                justifyContent: 'center',
                padding: '10px',
                overflow: 'hidden'
            });

            if (!count) return;

            // Reset
            tiles.css({
                width: '',
                height: '',
                margin: '5px',
                display: 'flex',
                flexDirection: 'column',
                justifyContent: 'center',
                alignItems: 'center',
                maxWidth: 'none',
                maxHeight: 'none'
            });

            if (count === 1) {
                tiles.css({
                    width: '100%',
                    height: '100%',
                    margin: '0'
                });
                tiles.find('.text-accent').css('font-size', '12vh');
                tiles.find('.text-default').css('font-size', '5vh');
            } else if (count === 2) {
                tiles.css({
                    width: 'calc(50% - 20px)',
                    height: '90%'
                });
                tiles.find('.text-accent').css('font-size', '8vh');
                tiles.find('.text-default').css('font-size', '4vh');
            } else if (count <= 4) {
                tiles.css({
                    width: 'calc(50% - 20px)',
                    height: 'calc(50% - 20px)'
                });
                tiles.find('.text-accent').css('font-size', '6vh');
                tiles.find('.text-default').css('font-size', '3vh');
            // todo: count <= 8 and count <= 16
            } else {
                tiles.css({
                    width: 'calc(33.33% - 20px)',
                    height: 'calc(33.33% - 20px)'
                });
                tiles.find('.text-accent').css('font-size', '4vh');
                tiles.find('.text-default').css('font-size', '2vh');
            }
        };

        // Window resize
        $(window)
            .off('resize' + ns)
            .on('resize' + ns, resize);

        // Observe actual layout changes (core fix)
        if (window.ResizeObserver) {
            const ro = new ResizeObserver(resize);
            ro.observe(container.get(0));
        }

        // Initial layout
        resize();
        return this;
    };

    /* ---------------------------------------------------------------------
     * AUTO HIDE MOUSE CURSOR
     * ------------------------------------------------------------------- */
    function initAutoHideCursor() {
        let mouseTimer = null;
        const mouseHideDelay = 3000; // ms

        const hideCursor = () => {
            document.body.style.cursor = 'none';
        };

        const showCursor = () => {
            document.body.style.cursor = 'default';
        };

        // Touch devices: always hide cursor
        if ('ontouchstart' in window) {
            hideCursor();
            return;
        }

        document.addEventListener('mousemove', () => {
            showCursor();
            clearTimeout(mouseTimer);
            mouseTimer = setTimeout(hideCursor, mouseHideDelay);
        });

        window.addEventListener('blur', hideCursor);
        window.addEventListener('focus', showCursor);

        // Start verborgen
        hideCursor();
    }    

    /* ---------------------------------------------------------------------
     * INIT
     * ------------------------------------------------------------------- */
    function initGrid() {
        const grid = $('#wallboard-grid');
        if (grid.length) {
            grid.scaledgrid();
        }
    }

    $(document).ready(function () {
        updateClock();
        setInterval(updateClock, 1000);

        initDropdowns();
        initAutoRefresh();
        initGrid();
        initAutoHideCursor(); 
    });

    $(window).on('wallboard:content-updated load', function () {
        initDropdowns();
        initGrid();
    });

})(jQuery);
