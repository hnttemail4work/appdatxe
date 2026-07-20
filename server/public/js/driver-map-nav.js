/**
 * Điều hướng: in-app focus route + mở Google Maps / geo URI.
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

    function openExternalNav(el) {
        var googleUrl = el.getAttribute('data-google-url') || el.getAttribute('href') || '';
        var geoUrl = el.getAttribute('data-geo-url') || '';
        var url = googleUrl || geoUrl;
        if (!url) {
            focusInAppRoute(el);
            return;
        }
        window.open(url, '_blank', 'noopener');
    }

    document.addEventListener('click', function (event) {
        var inApp = event.target.closest('[data-driver-map-nav-inapp]');
        if (inApp) {
            event.preventDefault();
            focusInAppRoute(inApp);
            return;
        }

        var external = event.target.closest('[data-driver-map-nav-external]');
        if (external) {
            event.preventDefault();
            openExternalNav(external);
            return;
        }

        var link = event.target.closest('[data-driver-map-nav]');
        if (!link) {
            return;
        }
        event.preventDefault();
        // Mặc định: ưu tiên Google Maps / geo; giữ in-app nếu không có URL ngoài.
        if (link.getAttribute('data-google-url') || (link.getAttribute('href') || '').indexOf('google.com/maps') !== -1) {
            openExternalNav(link);
            return;
        }
        if ((link.getAttribute('href') || '').indexOf('geo:') === 0) {
            openExternalNav(link);
            return;
        }
        focusInAppRoute(link);
    });
})();
