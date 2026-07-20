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

    function getHomeUrl() {
        var link = document.querySelector('.customer-scroll-dock a.customer-scroll-dock-item');
        if (link && link.href) {
            return link.href;
        }
        var back = document.querySelector('.app-navbar a[href="/"], a.app-nav-back');
        if (back && back.href) {
            return back.href;
        }
        return '/';
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

    /** Chỉ gọi sau chuyến vừa kết thúc — không tự redirect khi mở trang. */
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

    /** Sau hủy chuyến — về trang chủ đặt xe (“Bạn muốn đi đâu?”). */
    function focusHomePage() {
        if (isBookingHomePage()) {
            window.scrollTo(0, 0);
            return;
        }
        window.location.href = getHomeUrl();
    }

    window.CustomerScrollDock = {
        focusTripsPage: focusTripsPage,
        focusTripsContent: focusTripsContent,
        focusHomePage: focusHomePage,
    };
})();
