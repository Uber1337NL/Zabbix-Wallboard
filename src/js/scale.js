/**
 * js/scale.js
 * Dynamic scaling for Zabbix Wallboard.
 * Optimizes tile sizes based on count to fill the screen.
 */

(function ($) {
    'use strict';

    $.fn.scaledgrid = function () {
        const container = $(this);
        if (!container.length) return this;

        const ns = '.scaledgrid';

        const getAppBarHeight = () => {
            const appBar = $('.app-bar');
            return appBar.length ? (appBar.outerHeight() || 50) : 50;
        };

        const resize = () => {
            const appBarHeight = getAppBarHeight();
            const windowHeight = Math.max(window.innerHeight - appBarHeight, 0);
            const windowWidth = window.innerWidth;
            const tiles = container.find('.tile-wide:visible');
            const count = tiles.length;

            container.css({
                height: windowHeight + 'px',
                minHeight: windowHeight + 'px',
                display: 'flex',
                'flex-wrap': 'wrap',
                'align-items': 'center',
                'justify-content': 'center',
                padding: '10px',
                overflow: 'hidden'
            });

            if (!count) return;

            // Reset styles first
            tiles.css({
                width: '', height: '', margin: '5px', fontSize: '',
                display: 'flex', flexDirection: 'column',
                justifyContent: 'center', alignItems: 'center',
                maxWidth: 'none', maxHeight: 'none'
            });

            if (count === 1) {
                tiles.css({ width: '100%', height: '100%', margin: '0' });
                tiles.find('.text-accent').css('font-size', '12vh');
                tiles.find('.text-default').css('font-size', '5vh');
            } 
            else if (count === 2) {
                // Twee tegels naast elkaar
                tiles.css({ width: 'calc(50% - 20px)', height: '90%' });
                tiles.find('.text-accent').css('font-size', '8vh');
                tiles.find('.text-default').css('font-size', '4vh');
            }
            else if (count <= 4) {
                // 2x2 grid
                tiles.css({ width: 'calc(50% - 20px)', height: 'calc(50% - 20px)' });
                tiles.find('.text-accent').css('font-size', '6vh');
                tiles.find('.text-default').css('font-size', '3vh');
            }
            else {
                // Meer dan 4: standaard grid maar groter dan voorheen
                tiles.css({ width: 'calc(33.33% - 20px)', height: 'calc(33.33% - 20px)' });
                tiles.find('.text-accent').css('font-size', '4vh');
                tiles.find('.text-default').css('font-size', '2vh');
            }
        };

        $(window).off('resize' + ns).on('resize' + ns, resize);
        
        const observer = new MutationObserver(() => {
            clearTimeout(container.data('timer'));
            container.data('timer', setTimeout(resize, 100));
        });
        observer.observe(container.get(0), { childList: true });

        resize();
        return this;
    };

    $(function () {
        const grid = $('#wallboard-grid');
        if (grid.length) grid.scaledgrid();
    });
})(jQuery);
