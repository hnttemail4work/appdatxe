/**
 * Bản đồ live trong dashboard tài xế — luôn hiện vị trí tài xế,
 * kèm ghim đón/trả khi có chuyến đang chạy.
 */
(function () {
    var GOONG_JS_CSS = 'https://cdn.jsdelivr.net/npm/@goongmaps/goong-js@1.0.9/dist/goong-js.css';
    var GOONG_JS = 'https://cdn.jsdelivr.net/npm/@goongmaps/goong-js@1.0.9/dist/goong-js.js';
    var goongMaptilesKey = String(window.__goongMaptilesKey || '').trim();

    var heroEl = document.getElementById('driver-map-hero');
    var canvasEl = document.getElementById('driver-map-canvas');
    if (!heroEl || !canvasEl || !goongMaptilesKey) {
        return;
    }

    var latInput = document.getElementById('driver-location-lat');
    var lngInput = document.getElementById('driver-location-lng');
    var locateBtn = document.getElementById('driver-map-locate-btn');
    var tripPins = Array.isArray(window.__driverMapTripPins) ? window.__driverMapTripPins : [];

    var DEFAULT_CENTER = [10.7769, 106.7009]; // TP.HCM — chỉ dùng làm khung nhìn tạm, không đặt ghim
    var DEFAULT_ZOOM = 15;
    var DRIVER_ZOOM = 16;

    var mapInstance = null;
    var driverMarker = null;
    var pinMarkers = [];
    var assetsPromise = null;
    var hasRealDriverCoords = false;

    function loadStylesheet(href) {
        return new Promise(function (resolve, reject) {
            if (document.querySelector('link[data-driver-map-asset="css"]')) {
                resolve();
                return;
            }
            var link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = href;
            link.setAttribute('data-driver-map-asset', 'css');
            link.onload = function () { resolve(); };
            link.onerror = reject;
            document.head.appendChild(link);
        });
    }

    function loadScript(src) {
        return new Promise(function (resolve, reject) {
            var script = document.createElement('script');
            script.src = src;
            script.async = true;
            script.setAttribute('data-driver-map-asset', 'js');
            script.onload = function () { resolve(); };
            script.onerror = reject;
            document.body.appendChild(script);
        });
    }

    function loadAssets() {
        if (window.goongjs) {
            return Promise.resolve();
        }
        if (!assetsPromise) {
            assetsPromise = loadStylesheet(GOONG_JS_CSS).then(function () {
                return loadScript(GOONG_JS);
            });
        }
        return assetsPromise;
    }

    function parsedInputCoords() {
        if (latInput && lngInput && latInput.value && lngInput.value) {
            var lat = parseFloat(latInput.value);
            var lng = parseFloat(lngInput.value);
            if (!Number.isNaN(lat) && !Number.isNaN(lng)) {
                return { lat: lat, lng: lng };
            }
        }
        return null;
    }

    /** Vị trí THẬT của tài xế (GPS/ô nhập đã xác nhận) — null nếu chưa có. */
    function realDriverCoords() {
        var fromGps = window.DriverLocationGps && window.DriverLocationGps.getLastKnownCoords
            ? window.DriverLocationGps.getLastKnownCoords()
            : null;
        if (fromGps) {
            return fromGps;
        }

        var fromInputs = parsedInputCoords();
        if (fromInputs) {
            return fromInputs;
        }

        var attrLat = parseFloat(heroEl.getAttribute('data-driver-map-lat') || '');
        var attrLng = parseFloat(heroEl.getAttribute('data-driver-map-lng') || '');
        if (!Number.isNaN(attrLat) && !Number.isNaN(attrLng)) {
            return { lat: attrLat, lng: attrLng };
        }

        return null;
    }

    function driverDivIcon() {
        var el = document.createElement('div');
        el.className = 'driver-map-marker driver-map-marker--self';
        el.innerHTML = '<span class="driver-map-marker__pulse" aria-hidden="true"></span>'
            + '<span class="driver-map-marker__arrow" aria-hidden="true">'
            + '<svg viewBox="0 0 24 24" width="44" height="44" focusable="false">'
            + '<path fill="currentColor" d="M12 2.2L20.5 20.2 12 16.4 3.5 20.2 12 2.2z"/>'
            + '</svg>'
            + '</span>';
        return el;
    }

    function setDriverHeading(heading) {
        if (!driverMarker || heading == null || Number.isNaN(heading)) {
            return;
        }
        var el = driverMarker.getElement();
        if (!el) {
            return;
        }
        var arrow = el.querySelector('.driver-map-marker__arrow');
        if (arrow) {
            arrow.style.transform = 'rotate(' + heading + 'deg)';
        }
    }

    function placeDriverMarker(lat, lng, heading) {
        if (!mapInstance || !window.goongjs) {
            return;
        }
        if (driverMarker) {
            driverMarker.setLngLat([lng, lat]);
            setDriverHeading(heading);
            return;
        }
        driverMarker = new window.goongjs.Marker({ element: driverDivIcon(), anchor: 'center' })
            .setLngLat([lng, lat])
            .addTo(mapInstance);
        setDriverHeading(heading);
    }

    function pinDivIcon(type) {
        var el = document.createElement('div');
        el.className = 'driver-map-marker driver-map-marker--' + (type === 'dropoff' ? 'dropoff' : 'pickup');
        el.innerHTML = '<span class="driver-map-marker__pin" aria-hidden="true"></span>';
        return el;
    }

    function placeTripPins() {
        if (!mapInstance || !window.goongjs) {
            return;
        }
        pinMarkers.forEach(function (m) { m.remove(); });
        pinMarkers = [];

        tripPins.forEach(function (pin) {
            if (typeof pin.lat !== 'number' || typeof pin.lng !== 'number') {
                return;
            }
            var marker = new window.goongjs.Marker({ element: pinDivIcon(pin.type), anchor: 'bottom' })
                .setLngLat([pin.lng, pin.lat])
                .addTo(mapInstance);

            if (pin.label) {
                marker.setPopup(new window.goongjs.Popup({ closeButton: false, offset: 18 }).setText(pin.label));
            }
            pinMarkers.push(marker);
        });
    }

    /** Chỉ fit theo vị trí THẬT của tài xế + ghim chuyến — không tính điểm mặc định (TP.HCM). */
    function fitToMarkers(centerCoords) {
        if (!mapInstance || !window.goongjs) {
            return;
        }

        var points = centerCoords ? [[centerCoords.lng, centerCoords.lat]] : [];
        tripPins.forEach(function (pin) {
            if (typeof pin.lat === 'number' && typeof pin.lng === 'number') {
                points.push([pin.lng, pin.lat]);
            }
        });

        if (points.length === 0) {
            return;
        }

        if (points.length === 1) {
            mapInstance.jumpTo({ center: points[0], zoom: DRIVER_ZOOM });
            return;
        }

        var bounds = points.reduce(function (b, p) {
            return b ? b.extend(p) : new window.goongjs.LngLatBounds(p, p);
        }, null);

        mapInstance.fitBounds(bounds, { padding: 70, maxZoom: 17, duration: 0 });
    }

    function recenterOnDriver() {
        var coords = parsedInputCoords() || realDriverCoords();
        if (!coords || !mapInstance) {
            return;
        }
        mapInstance.flyTo({ center: [coords.lng, coords.lat], zoom: DRIVER_ZOOM });
    }

    function initMap() {
        if (!canvasEl || !window.goongjs) {
            return;
        }

        window.goongjs.accessToken = goongMaptilesKey;
        var start = realDriverCoords();
        hasRealDriverCoords = !!start;
        var initialCenter = start || { lat: DEFAULT_CENTER[0], lng: DEFAULT_CENTER[1] };

        mapInstance = new window.goongjs.Map({
            container: canvasEl,
            style: 'https://tiles.goong.io/assets/goong_map_web.json',
            center: [initialCenter.lng, initialCenter.lat],
            zoom: DEFAULT_ZOOM,
            attributionControl: false,
        });

        mapInstance.on('load', function () {
            if (hasRealDriverCoords) {
                var heading = window.DriverLocationGps && window.DriverLocationGps.getLastKnownHeading
                    ? window.DriverLocationGps.getLastKnownHeading()
                    : null;
                placeDriverMarker(start.lat, start.lng, heading);
            }
            placeTripPins();
            fitToMarkers(hasRealDriverCoords ? start : null);
        });
    }

    document.addEventListener('addressmap:applied', function (event) {
        var detail = event.detail || {};
        if (typeof detail.lat !== 'number' || typeof detail.lng !== 'number') {
            return;
        }
        placeDriverMarker(detail.lat, detail.lng, detail.heading);

        // GPS thật đầu tiên — luôn đưa camera về đúng vị trí (không còn ở điểm mặc định).
        if (!hasRealDriverCoords && mapInstance) {
            mapInstance.jumpTo({ center: [detail.lng, detail.lat], zoom: DRIVER_ZOOM });
        }
        hasRealDriverCoords = true;
    });

    if (locateBtn) {
        locateBtn.addEventListener('click', recenterOnDriver);
    }

    // Modal chọn vị trí thủ công (address-map-picker.js) có thể dỡ goongjs khi đóng — nạp lại nếu cần.
    var addressPickerModalEl = document.getElementById('addressMapPickerModal');
    if (addressPickerModalEl) {
        addressPickerModalEl.addEventListener('hidden.bs.modal', function () {
            if (!window.goongjs) {
                if (mapInstance && typeof mapInstance.remove === 'function') {
                    try { mapInstance.remove(); } catch (e) { /* noop */ }
                }
                mapInstance = null;
                driverMarker = null;
                pinMarkers = [];
                assetsPromise = null;
                loadAssets().then(initMap).catch(function () {});
            }
        });
    }

    window.addEventListener('resize', function () {
        if (mapInstance && typeof mapInstance.resize === 'function') {
            mapInstance.resize();
        }
    });

    document.addEventListener('drivertab:changed', function (event) {
        var tab = event.detail && event.detail.tab;
        if (tab !== 'trips' || !mapInstance || typeof mapInstance.resize !== 'function') {
            return;
        }
        // Overlay che map lúc init → canvas trắng; resize khi về Trang chủ.
        window.requestAnimationFrame(function () {
            mapInstance.resize();
            if (hasRealDriverCoords) {
                recenterOnDriver();
            }
        });
    });

    loadAssets().then(initMap).catch(function () {
        canvasEl.classList.add('driver-map-hero__canvas--error');
    });
})();
