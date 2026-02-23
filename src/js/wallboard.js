(function($) {
    $(document).ready(function() {

        // 1. Locale-aware clock using Intl.DateTimeFormat for displaying the current time
        const formatter = new Intl.DateTimeFormat('nl-NL', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        });

        function updateClock() {
            const now = new Date();
            $('#clock').text(formatter.format(now));
        }

        updateClock();
        setInterval(updateClock, 1000);

        // 2. Dropdown Toggles
        // This allows clicking the menu items to show the sub-menus
        $('.dropdown-toggle').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const menu = $(this).next('.d-menu');
            $('.d-menu').not(menu).hide(); // Close others
            menu.toggle();
        });

        // Close dropdowns when clicking elsewhere
        $(document).on('click', function() {
            $('.d-menu').hide();
        });

        // 3. Login Dialog Logic
        $('.open-login-dialog').on('click', function(e) {
            e.preventDefault();
            $('#login_dialog').show();
            $('#wb-overlay').show();
        });

        // Close dialog if clicking overlay
        $('#wb-overlay').on('click', function() {
            $('.dialog').hide();
            $(this).hide();
        });

        // 4. Disable Tile Clicks (Wallscreen mode)
        $(document).off('click', '.tile-wide');
    });
})(jQuery);
