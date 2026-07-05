/**
 * Customer booking — thanh điều hướng Trang / Chuyến.
 */
(function () {
    if (window.location.hash === '#booking-results-main') {
        var homeUrl = document.querySelector('.customer-scroll-dock [href$="/"]')
            || document.querySelector('.customer-scroll-dock a[href]:not([href*="chuyen"])');
        if (homeUrl && homeUrl.href) {
            window.location.replace(homeUrl.href);
        }
    }
})();
