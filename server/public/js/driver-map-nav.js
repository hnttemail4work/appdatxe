/**
 * Mở chỉ đường — Google Maps (map FAB) hoặc geo: (nút trên thẻ cuốc).
 */
(function () {
    function readDriverCoords() {
        var latInput = document.getElementById('driver-location-lat');
        var lngInput = document.getElementById('driver-location-lng');
        var lat = latInput ? parseFloat(latInput.value) : NaN;
        var lng = lngInput ? parseFloat(lngInput.value) : NaN;
        if (Number.isFinite(lat) && Number.isFinite(lng)) {
            return { lat: lat, lng: lng };
        }

        if (window.DriverLocationGps && typeof window.DriverLocationGps.getLastKnownCoords === 'function') {
            var last = window.DriverLocationGps.getLastKnownCoords();
            if (last && Number.isFinite(last.lat) && Number.isFinite(last.lng)) {
                return { lat: last.lat, lng: last.lng };
            }
        }

        return null;
    }

    function formatCoord(value) {
        return Number(value).toFixed(6);
    }

    function buildGoogleUrl(link) {
        var destLat = parseFloat(link.getAttribute('data-dest-lat') || '');
        var destLng = parseFloat(link.getAttribute('data-dest-lng') || '');
        if (!Number.isFinite(destLat) || !Number.isFinite(destLng)) {
            return link.getAttribute('href') || '';
        }

        var params = new URLSearchParams({
            api: '1',
            destination: formatCoord(destLat) + ',' + formatCoord(destLng),
            travelmode: 'driving',
        });

        var originLat = parseFloat(link.getAttribute('data-origin-lat') || '');
        var originLng = parseFloat(link.getAttribute('data-origin-lng') || '');
        var useCurrent = link.getAttribute('data-map-nav-use-current-origin') === '1';

        if (useCurrent) {
            var current = readDriverCoords();
            if (current) {
                params.set('origin', formatCoord(current.lat) + ',' + formatCoord(current.lng));
            }
        } else if (Number.isFinite(originLat) && Number.isFinite(originLng)) {
            params.set('origin', formatCoord(originLat) + ',' + formatCoord(originLng));
        }

        return 'https://www.google.com/maps/dir/?' + params.toString();
    }

    document.addEventListener('click', function (event) {
        var link = event.target.closest('[data-driver-map-nav]');
        if (!link || !link.href) {
            return;
        }

        var provider = link.getAttribute('data-map-nav-provider') || '';
        var isGoogle = provider === 'google'
            || link.href.indexOf('google.com/maps') !== -1
            || link.hasAttribute('data-dest-lat');

        if (isGoogle) {
            event.preventDefault();
            var googleUrl = buildGoogleUrl(link) || link.href;
            if (!googleUrl) {
                return;
            }
            // Mobile: ưu tiên mở app / tab; desktop: tab mới.
            try {
                var opened = window.open(googleUrl, '_blank', 'noopener,noreferrer');
                if (!opened) {
                    window.location.href = googleUrl;
                }
            } catch (e) {
                window.location.href = googleUrl;
            }
            return;
        }

        if (window.innerWidth >= 768 && !window.matchMedia('(pointer: coarse)').matches) {
            return;
        }

        event.preventDefault();
        window.location.href = link.href;
    });
})();
