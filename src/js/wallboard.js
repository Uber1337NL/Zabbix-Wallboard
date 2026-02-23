(function($) {
    $(document).ready(function() {

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

        $('.dropdown-toggle').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const menu = $(this).next('.d-menu');
            $('.d-menu').not(menu).hide();
            menu.toggle();
        });

        $(document).on('click', function() {
            $('.d-menu').hide();
        });

        const refreshInterval = parseInt($('meta[name="refresh-interval"]').attr('content')) || 30000;
        
        function refreshWallboard() {
            $.ajax({
                url: window.location.href,
                type: 'GET',
                dataType: 'json',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                success: function(response) {
                    if (response.html) {
                        $('#main-content').html(response.html);
                    }
                },
                error: function() {
                    console.error('Failed to refresh wallboard');
                }
            });
        }

        setInterval(refreshWallboard, refreshInterval);
    });
})(jQuery);