/**
 * Zabbix Wallboard Tile Scaling Plugin
 * Version: 2.0.0
 * 
 * jQuery plugin for responsive tile scaling
 */

'use strict';

(function($) {
    /**
     * Scaled Grid Plugin
     * @param {Function} getContainerSize - Function returning [width, height]
     */
    $.fn.scaledgrid = function(getContainerSize) {
        if (!this.length) {
            console.warn('scaledgrid: No elements found');
            return this;
        }

        const $tiles = this;
        const $firstTile = this.eq(0);
        
        // Cache initial values
        const initialValues = {
            horizontalMargin: parseInt($firstTile.css('margin-left'), 10) + 
                            parseInt($firstTile.css('margin-right'), 10),
            verticalMargin: parseInt($firstTile.css('margin-top'), 10) + 
                          parseInt($firstTile.css('margin-bottom'), 10),
            width: parseInt($firstTile.css('width'), 10),
            height: parseInt($firstTile.css('height'), 10),
            fontSize: parseFloat($firstTile.css('font-size')),
            textAccentSize: parseFloat($firstTile.find('.text-accent').first().css('font-size')),
            textAccentSmallSize: parseFloat($firstTile.find('.text-accent-small').first().css('font-size')),
            textDefaultSize: parseFloat($firstTile.find('.text-default').first().css('font-size')),
            textDefaultSmallSize: parseFloat($firstTile.find('.text-default-small').first().css('font-size'))
        };

        initialValues.totalWidth = initialValues.width + initialValues.horizontalMargin;
        initialValues.totalHeight = initialValues.height + initialValues.verticalMargin;
        initialValues.aspectRatio = initialValues.totalWidth / initialValues.totalHeight;

        /**
         * Calculate grid dimensions
         * @param {number} containerWidth - Container width
         * @param {number} containerHeight - Container height
         * @param {number} tileCount - Number of tiles
         * @returns {Object} - Calculated dimensions
         */
        function calculateGridDimensions(containerWidth, containerHeight, tileCount) {
            let tileWidth = initialValues.totalWidth;
            let tileHeight = initialValues.totalHeight;
            let columnCount = Math.floor(containerWidth / tileWidth) || 1;
            let rowCount = Math.ceil(tileCount / columnCount) || 1;

            // Expand tiles to fill space
            while (tileWidth + initialValues.horizontalMargin < containerWidth && 
                   (columnCount * tileWidth < containerWidth || rowCount * tileHeight < containerHeight)) {
                tileWidth++;

                while (tileHeight + initialValues.verticalMargin < containerHeight && 
                       tileWidth / tileHeight > initialValues.aspectRatio) {
                    tileHeight++;
                }

                columnCount = Math.floor(containerWidth / tileWidth) || 1;
                rowCount = Math.ceil(tileCount / columnCount) || 1;
            }

            // Shrink tiles if overflow
            while (tileWidth - initialValues.horizontalMargin > 0 && 
                   (columnCount * tileWidth > containerWidth || rowCount * tileHeight > containerHeight)) {
                tileWidth--;

                while (tileHeight - initialValues.verticalMargin > 0 && 
                       tileWidth / tileHeight < initialValues.aspectRatio) {
                    tileHeight--;
                }

                columnCount = Math.floor(containerWidth / tileWidth) || 1;
                rowCount = Math.ceil(tileCount / columnCount) || 1;
            }

            return {
                width: tileWidth - initialValues.horizontalMargin,
                height: tileHeight - initialValues.verticalMargin,
                scaleFactor: tileHeight / initialValues.totalHeight
            };
        }

        /**
         * Apply calculated styles to tiles
         * @param {Object} dimensions - Calculated dimensions
         */
        function applyTileStyles(dimensions) {
            const scale = dimensions.scaleFactor;

            $tiles.css({
                'width': dimensions.width + 'px',
                'height': dimensions.height + 'px',
                'font-size': (initialValues.fontSize * scale) + 'px'
            });

            $tiles.find('.text-accent').css('font-size', (initialValues.textAccentSize * scale) + 'px');
            $tiles.find('.text-accent-small').css('font-size', (initialValues.textAccentSmallSize * scale) + 'px');
            $tiles.find('.text-default').css('font-size', (initialValues.textDefaultSize * scale) + 'px');
            $tiles.find('.text-default-small').css('font-size', (initialValues.textDefaultSmallSize * scale) + 'px');
        }

        /**
         * Main resize handler
         */
        function adaptTileSizes() {
            try {
                const containerSize = getContainerSize();
                
                if (!Array.isArray(containerSize) || containerSize.length !== 2) {
                    console.error('scaledgrid: getContainerSize must return [width, height]');
                    return;
                }

                const [containerWidth, containerHeight] = containerSize;

                if (containerWidth <= 0 || containerHeight <= 0) {
                    return; // Skip if container has no dimensions
                }

                const dimensions = calculateGridDimensions(
                    containerWidth,
                    containerHeight,
                    $tiles.length
                );

                applyTileStyles(dimensions);
            } catch (error) {
                console.error('scaledgrid error:', error);
            }
        }

        // Debounced resize handler for performance
        let resizeTimer;
        function debouncedResize() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(adaptTileSizes, 100);
        }

        // Bind resize event
        $(window).on('resize', debouncedResize);

        // Initial sizing
        adaptTileSizes();

        // Return for chaining
        return this;
    };

})(jQuery);

/**
 * Initialize scaled grid on document ready
 */
$(document).ready(function() {
    const $window = $(window);
    const $appBar = $('.app-bar');

    $('.tile-wide').scaledgrid(function() {
        const appBarHeight = $appBar.length ? $appBar.height() : 0;
        return [$window.width(), $window.height() - appBarHeight];
    });
});
