/**
 * Điều hướng trên map app — không mở Google Maps.
 */
(function () {
    function focusInAppRoute(el) {
        var destLat = parseFloat(el.getAttribute('data-dest-lat') || '');
        var destLng = parseFloat(el.getAttribute('data-dest-lng') || '');
        if (!Number.isFinite(destLat) || !Number.isFinite(destLng)) {
            return;
        }
        if (window.DriverLiveMap && typeof window.DriverLiveMap.focusPickupRoute === 'function') {
            window.DriverLiveMap.focusPickupRoute(destLat, destLng);
            return;
        }
        var locateBtn = document.getElementById('driver-map-locate-btn');
        if (locateBtn) {
            locateBtn.click();
        }
    }

    document.addEventListener('click', function (event) {
        var inApp = event.target.closest('[data-driver-map-nav-inapp]');
        if (inApp) {
            event.preventDefault();
            focusInAppRoute(inApp);
            return;
        }

        // Chặn mọi link Google Maps cũ còn sót.
        var link = event.target.closest('[data-driver-map-nav]');
        if (!link) {
            return;
        }
        event.preventDefault();
        focusInAppRoute(link);
    });
})();
