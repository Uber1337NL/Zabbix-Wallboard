(function ($) {
    $(document).ready(function () {
        console.log("Wallboard: Logic initialized.");

        // 1. Clock Update
        function updateClock() {
            const now = new Date();
            const fmt = now.getFullYear() + '-' +
                String(now.getMonth() + 1).padStart(2, '0') + '-' +
                String(now.getDate()).padStart(2, '0') + ' ' +
                String(now.getHours()).padStart(2, '0') + ':' +
                String(now.getMinutes()).padStart(2, '0') + ':' +
                String(now.getSeconds()).padStart(2, '0');
            $('#clock').text(fmt);
        }
        updateClock();
        setInterval(updateClock, 1000);

        // 2. Dropdown Toggles
        // This allows clicking the menu items to show the sub-menus
        $('.dropdown-toggle').on('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            const menu = $(this).next('.d-menu');
            $('.d-menu').not(menu).hide(); // Close others
            menu.toggle();
        });

        // Close dropdowns when clicking elsewhere
        $(document).on('click', function () {
            $('.d-menu').hide();
        });

        // 3. Login Dialog Logic
        $('.open-login-dialog').on('click', function (e) {
            e.preventDefault();
            $('#login_dialog').show();
            $('#wb-overlay').show();
        });

        // Close dialog if clicking overlay
        $('#wb-overlay').on('click', function () {
            $('.dialog').hide();
            $(this).hide();
        });

        // 4. Disable Tile Clicks (Wallscreen mode)
        $(document).off('click', '.tile-wide');
    });
})(jQuery);
