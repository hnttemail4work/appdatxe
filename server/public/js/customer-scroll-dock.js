/**
 * Customer booking — thanh điều hướng Trang / Chuyến.
 */
(function () {
    function isTripsPage() {
        return !!document.getElementById('guest-trip-page');
    }

    function isBookingHomePage() {
        return !!document.getElementById('booking-page-top');
    }

    function getTripsUrl() {
        var link = document.querySelector('.customer-scroll-dock a[href*="chuyen"]');
        return link && link.href ? link.href : null;
    }

    function focusTripsContent() {
        var target = document.getElementById('guest-trip-page')
            || document.getElementById('guest-trip-page-panel');
        if (!target) {
            return;
        }

        window.requestAnimationFrame(function () {
            try {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } catch (e) {
                target.scrollIntoView(true);
            }
        });
    }

    /** Chỉ gọi sau hủy chuyến hoặc chuyến vừa kết thúc — không tự redirect khi mở trang. */
    function focusTripsPage() {
        if (isTripsPage()) {
            focusTripsContent();
            return;
        }

        var tripsUrl = getTripsUrl();
        if (tripsUrl && isBookingHomePage()) {
            window.location.href = tripsUrl;
        }
    }

    window.CustomerScrollDock = {
        focusTripsPage: focusTripsPage,
        focusTripsContent: focusTripsContent,
    };
})();
