/**
 * Bản đồ live trong dashboard tài xế — luôn hiện vị trí tài xế,
 * kèm ghim đón/trả khi có chuyến đang chạy.
 * - Có mục tiêu điều hướng đón khách (`window.__driverPickupNavTarget`, chỉ set khi
 *   TX đang ở giai đoạn "assigned"): camera bám hướng (course-up), nghiêng — TBT engine
 *   (driver-turn-by-turn.js) sở hữu route line + fetch Direction.
 * - Các trường hợp khác (đến điểm trả…): fitBounds TX ↔ ghim, phẳng — giống màn khách đợi đón.
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
    var DEFAULT_ZOOM = Math.min(20, 15 * 1.25);
    var DRIVER_ZOOM = Math.min(20, 16 * 1.3 * 1.25); // sát hơn khi có chuyến (+30% rồi +25%)
    /** Khớp guest-trip-live-map khi assigned / at_pickup / đang chạy. */
    var TRIP_FIT_PADDING = Math.round(48 * 0.7 * 0.75);
    var TRIP_FIT_MAX_ZOOM = Math.min(20, 15 * 1.3 * 1.25);
    var TRIP_FIT_DURATION = 500;

    /** Camera "nav follow" (course-up, nghiêng) khi đang đón — chỉ dùng cho __driverPickupNavTarget. */
    var NAV_ZOOM_PEEK = Math.min(20, 17.6 * 1.3 * 1.25);
    var NAV_PITCH_PEEK = 52;
    var NAV_ZOOM_FULL = Math.min(20, 16.2 * 1.3 * 1.25);
    var NAV_PITCH_FULL = 24;
    var NAV_SIDE_PADDING = 28;
    var NAV_EASE_DURATION = 900;

    var pickupNavTarget = window.__driverPickupNavTarget && Number.isFinite(Number(window.__driverPickupNavTarget.dest_lat))
        && Number.isFinite(Number(window.__driverPickupNavTarget.dest_lng))
        ? { lat: Number(window.__driverPickupNavTarget.dest_lat), lng: Number(window.__driverPickupNavTarget.dest_lng) }
        : null;

    var mapInstance = null;
    var driverMarker = null;
    var pinMarkers = [];
    var assetsPromise = null;
    var hasRealDriverCoords = false;
    var routeSourceId = 'driver-pickup-route';
    var routeLayerId = 'driver-pickup-route-line';
    var lastDriverPos = null;
    var lastHeading = null;
    var directionTimer = null;
    var sheetState = { peek: true, heightPx: 0 };
    var lastRouteCoords = null;
    /** 'follow' | 'passenger' | 'overview' — nhấn card khách để xen kẽ zoom khách / overview khoảng cách. */
    var cameraFocusMode = 'follow';
    var lastFocusPassenger = null;

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

    function hasActiveTripPins() {
        return tripPins.some(function (pin) {
            return pin && typeof pin.lat === 'number' && typeof pin.lng === 'number';
        });
    }

    /** Đang đón khách (assigned) → camera bám hướng, nghiêng; route do turn-by-turn engine sở hữu. */
    function navActive() {
        return !!pickupNavTarget && hasActiveTripPins();
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

    function headingFromMove(prev, next) {
        if (!prev || !next) {
            return null;
        }
        var dLng = next.lng - prev.lng;
        var dLat = next.lat - prev.lat;
        if (Math.abs(dLng) < 1e-7 && Math.abs(dLat) < 1e-7) {
            return null;
        }
        var deg = Math.atan2(dLng, dLat) * 180 / Math.PI;
        return (deg + 360) % 360;
    }

    function resolveHeading(explicitHeading, prev, next) {
        if (explicitHeading != null && !Number.isNaN(Number(explicitHeading))) {
            return Number(explicitHeading);
        }
        var fromMove = headingFromMove(prev, next);
        if (fromMove != null) {
            return fromMove;
        }
        if (lastHeading != null) {
            return lastHeading;
        }
        if (window.DriverLocationGps && window.DriverLocationGps.getLastKnownHeading) {
            var gpsHeading = window.DriverLocationGps.getLastKnownHeading();
            if (gpsHeading != null && !Number.isNaN(gpsHeading)) {
                return gpsHeading;
            }
        }
        return null;
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
        if (!arrow) {
            return;
        }
        // Marker giữ nguyên hướng viewport (không tự xoay theo bearing map) — bù bearing
        // hiện tại để mũi tên luôn chỉ đúng hướng đi thật, kể cả khi camera course-up.
        var mapBearing = mapInstance && mapInstance.getBearing ? mapInstance.getBearing() : 0;
        var relative = ((heading - mapBearing) % 360 + 360) % 360;
        arrow.style.transform = 'rotate(' + relative + 'deg)';
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

    function pickupPin() {
        for (var i = 0; i < tripPins.length; i++) {
            var pin = tripPins[i];
            if (pin && pin.type === 'pickup' && typeof pin.lat === 'number' && typeof pin.lng === 'number') {
                return pin;
            }
        }
        return null;
    }

    function resolvePassengerFocus(coords) {
        if (coords && Number.isFinite(Number(coords.lat)) && Number.isFinite(Number(coords.lng))) {
            return { lat: Number(coords.lat), lng: Number(coords.lng) };
        }
        if (lastFocusPassenger) {
            return lastFocusPassenger;
        }
        var pin = pickupPin();
        if (pin) {
            return { lat: pin.lat, lng: pin.lng };
        }
        if (pickupNavTarget) {
            return { lat: pickupNavTarget.lat, lng: pickupNavTarget.lng };
        }
        return null;
    }

    /**
     * Nhấn card khách (đồng bộ màn khách):
     * lần 1 → zoom vị trí khách; lần 2 → thu sheet + overview TX↔khách (khoảng cách).
     */
    function togglePassengerFocusCamera(coords) {
        if (!mapInstance || !window.goongjs) {
            return false;
        }
        var passenger = resolvePassengerFocus(coords);
        if (!passenger) {
            return false;
        }
        lastFocusPassenger = passenger;
        var driver = lastDriverPos || realDriverCoords();
        var pad = sheetCameraPadding();

        if (cameraFocusMode !== 'passenger') {
            cameraFocusMode = 'passenger';
            if (window.MapSheetCamera && window.MapSheetCamera.easeToFocus) {
                window.MapSheetCamera.easeToFocus(mapInstance, passenger, {
                    mapEl: heroEl || canvasEl,
                    padding: pad,
                    zoom: Math.min(20, DRIVER_ZOOM + 0.35),
                    pitch: 0,
                    bearing: 0,
                    duration: TRIP_FIT_DURATION,
                });
            } else if (mapInstance.easeTo) {
                mapInstance.easeTo({
                    center: [passenger.lng, passenger.lat],
                    zoom: Math.min(20, DRIVER_ZOOM + 0.35),
                    pitch: 0,
                    bearing: 0,
                    padding: pad,
                    duration: TRIP_FIT_DURATION,
                });
            }
            return true;
        }

        cameraFocusMode = 'overview';
        if (window.DriverPickupSheet && typeof window.DriverPickupSheet.collapse === 'function') {
            window.DriverPickupSheet.collapse();
        } else {
            var sheet = document.querySelector('[data-driver-pickup-sheet]');
            if (sheet) {
                sheet.classList.add('is-peek');
                sheet.classList.remove('is-full');
            }
        }

        window.setTimeout(function () {
            if (!mapInstance) {
                return;
            }
            var nextPad = sheetCameraPadding();
            var points = [[passenger.lng, passenger.lat]];
            if (driver) {
                points.unshift([driver.lng, driver.lat]);
            }
            if (points.length === 1) {
                if (window.MapSheetCamera && window.MapSheetCamera.easeToFocus) {
                    window.MapSheetCamera.easeToFocus(mapInstance, passenger, {
                        mapEl: heroEl || canvasEl,
                        padding: nextPad,
                        zoom: DRIVER_ZOOM,
                        pitch: 0,
                        bearing: 0,
                        duration: TRIP_FIT_DURATION,
                    });
                }
                return;
            }
            var bounds = points.reduce(function (b, p) {
                return b ? b.extend(p) : new window.goongjs.LngLatBounds(p, p);
            }, null);
            mapInstance.fitBounds(bounds, {
                padding: nextPad,
                maxZoom: TRIP_FIT_MAX_ZOOM,
                duration: TRIP_FIT_DURATION,
                pitch: 0,
                bearing: 0,
            });
        }, 160);

        return true;
    }

    function clearPassengerFocusHold() {
        cameraFocusMode = 'follow';
        lastFocusPassenger = null;
    }

    function emptyRouteGeo() {
        return { type: 'FeatureCollection', features: [] };
    }

    function lineFeature(coords) {
        return {
            type: 'FeatureCollection',
            features: [{
                type: 'Feature',
                properties: {},
                geometry: { type: 'LineString', coordinates: coords },
            }],
        };
    }

    function ensureRouteLayer() {
        if (!mapInstance || !mapInstance.getStyle) {
            return;
        }
        if (!mapInstance.getSource(routeSourceId)) {
            mapInstance.addSource(routeSourceId, { type: 'geojson', data: emptyRouteGeo() });
        }
        if (!mapInstance.getLayer(routeLayerId)) {
            mapInstance.addLayer({
                id: routeLayerId,
                type: 'line',
                source: routeSourceId,
                layout: { 'line-join': 'round', 'line-cap': 'round' },
                paint: {
                    'line-color': '#2563eb',
                    'line-width': 8,
                    'line-opacity': 0.92,
                },
            });
        }
    }

    function setRouteCoords(coords) {
        lastRouteCoords = coords && coords.length >= 2 ? coords : null;
        if (!mapInstance || !mapInstance.getSource) {
            return;
        }
        ensureRouteLayer();
        var source = mapInstance.getSource(routeSourceId);
        if (!source) {
            return;
        }
        source.setData(lastRouteCoords ? lineFeature(lastRouteCoords) : emptyRouteGeo());
    }

    function syncPickupRoute(driverCoords) {
        var pickup = pickupPin();
        if (!pickup || !driverCoords) {
            setRouteCoords(null);
            return;
        }

        // Đang đón (nav mode): turn-by-turn sở hữu polyline theo đường — không ghi đè
        // bằng đoạn thẳng mỗi tick GPS (trước đây làm mất đường cong vừa fetch).
        if (navActive()) {
            return;
        }

        if (!window.__geocodeDirectionUrl) {
            return;
        }
        if (directionTimer) {
            window.clearTimeout(directionTimer);
        }
        directionTimer = window.setTimeout(function () {
            var params = new URLSearchParams({
                origin_lat: String(driverCoords.lat),
                origin_lng: String(driverCoords.lng),
                dest_lat: String(pickup.lat),
                dest_lng: String(pickup.lng),
            });
            fetch(window.__geocodeDirectionUrl + '?' + params.toString(), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (data) {
                    var coords = data && Array.isArray(data.coordinates) ? data.coordinates : null;
                    if (coords && coords.length >= 2) {
                        setRouteCoords(coords);
                    }
                })
                .catch(function () { /* không fallback line thẳng */ });
        }, 280);
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

        if (lastDriverPos) {
            syncPickupRoute(lastDriverPos);
        }
    }

    /**
     * Fit overview TX + ghim chuyến — cùng kiểu màn khách đợi đón.
     * Map phẳng (pitch/bearing = 0).
     */
    function fitToMarkers(centerCoords, options) {
        if (!mapInstance || !window.goongjs) {
            return;
        }
        options = options || {};

        var points = centerCoords ? [[centerCoords.lng, centerCoords.lat]] : [];
        tripPins.forEach(function (pin) {
            if (typeof pin.lat === 'number' && typeof pin.lng === 'number') {
                points.push([pin.lng, pin.lat]);
            }
        });

        if (points.length === 0) {
            return;
        }

        var duration = options.duration != null
            ? options.duration
            : (points.length > 1 ? TRIP_FIT_DURATION : 0);

        if (points.length === 1) {
            var single = {
                center: points[0],
                zoom: DRIVER_ZOOM,
                pitch: 0,
                bearing: 0,
                duration: duration,
            };
            if (duration > 0 && mapInstance.easeTo) {
                mapInstance.easeTo(single);
            } else {
                mapInstance.jumpTo(single);
            }
            return;
        }

        var bounds = points.reduce(function (b, p) {
            return b ? b.extend(p) : new window.goongjs.LngLatBounds(p, p);
        }, null);

        mapInstance.fitBounds(bounds, {
            padding: options.padding != null ? options.padding : TRIP_FIT_PADDING,
            maxZoom: TRIP_FIT_MAX_ZOOM,
            duration: duration,
            pitch: 0,
            bearing: 0,
        });
    }

    /**
     * Padding map = vùng lộ giữa cạnh trên màn (banner) và cạnh trên panel/sheet.
     * Dùng MapSheetCamera chung với màn khách.
     */
    function sheetCameraPadding() {
        var panel = document.getElementById('driver-bottom-panel');
        var pickupSheet = document.querySelector('[data-driver-pickup-sheet]');
        var sheetTop = null;
        // Ưu tiên mép trên popup đón (đúng cạnh trên sheet user đang kéo).
        if (pickupSheet && pickupSheet.offsetParent !== null) {
            sheetTop = pickupSheet.getBoundingClientRect().top;
        } else if (panel) {
            sheetTop = panel.getBoundingClientRect().top;
        }
        if (sheetState.heightPx > 0 && heroEl) {
            var mapRect = heroEl.getBoundingClientRect();
            var fromHeight = mapRect.top + mapRect.height - sheetState.heightPx;
            sheetTop = sheetTop != null ? Math.min(sheetTop, fromHeight) : fromHeight;
        }
        return window.MapSheetCamera && window.MapSheetCamera.paddingFromEdges
            ? window.MapSheetCamera.paddingFromEdges({
                mapEl: heroEl || canvasEl,
                sheetTop: sheetTop,
                topObstacleEl: document.getElementById('driver-pickup-proximity-sheet'),
                side: NAV_SIDE_PADDING,
                topExtra: 16,
            })
            : {
                top: 16,
                bottom: (sheetState.heightPx || 0) + 16,
                left: NAV_SIDE_PADDING,
                right: NAV_SIDE_PADDING,
            };
    }

    /**
     * Map đang khung nối điểm đón ↔ điểm trả (fit nhiều điểm, không nav follow).
     * Khi kéo sheet: fit lại trong dải map lộ — không kéo về 1 điểm.
     */
    function isPickupDropoffRouteView() {
        if (navActive()) {
            return false;
        }
        var hasPickup = false;
        var hasDropoff = false;
        tripPins.forEach(function (pin) {
            if (pin && pin.type === 'pickup') {
                hasPickup = true;
            }
            if (pin && pin.type === 'dropoff') {
                hasDropoff = true;
            }
        });
        return hasPickup && hasDropoff;
    }

    /**
     * Camera "nav follow" — course-up (bearing = hướng đi), nghiêng, zoom sát.
     * Peek (sheet thu nhỏ): zoom/nghiêng nhiều hơn Full (sheet mở).
     * Focus mũi tên vào giữa vùng map lộ (mép trên ↔ mép popup).
     */
    function applyNavCamera(pos, heading, options) {
        if (!mapInstance || !window.goongjs || !navActive() || !pos) {
            return;
        }
        options = options || {};

        var peek = !!sheetState.peek;
        var zoom = peek ? NAV_ZOOM_PEEK : NAV_ZOOM_FULL;
        var pitch = peek ? NAV_PITCH_PEEK : NAV_PITCH_FULL;
        var bearing = heading != null && !Number.isNaN(heading)
            ? heading
            : (mapInstance.getBearing ? mapInstance.getBearing() : 0);
        var duration = options.duration != null ? options.duration : NAV_EASE_DURATION;
        var pad = options.padding || sheetCameraPadding();

        if (window.MapSheetCamera && window.MapSheetCamera.easeToFocus) {
            window.MapSheetCamera.easeToFocus(mapInstance, pos, {
                mapEl: heroEl || canvasEl,
                padding: pad,
                zoom: zoom,
                pitch: pitch,
                bearing: bearing,
                duration: duration,
            });
        } else {
            mapInstance.easeTo({
                center: [pos.lng, pos.lat],
                zoom: zoom,
                pitch: pitch,
                bearing: bearing,
                padding: pad,
                duration: duration,
            });
        }
        setDriverHeading(heading != null ? heading : lastHeading);
    }

    /**
     * Căn mũi tên / vị trí TX vào giữa vùng map còn lộ khi sheet peek↔full.
     * Overview đón+trả: fitBounds lại theo padding sheet (không kéo 1 điểm).
     * Giữ nguyên chế độ focus card khách (passenger / overview) nếu đang bật.
     */
    function refitSheetCamera(options) {
        options = options || {};
        if (!mapInstance || !window.goongjs) {
            return;
        }
        var pad = sheetCameraPadding();
        var duration = options.duration != null ? options.duration : 320;

        if (cameraFocusMode === 'passenger' && lastFocusPassenger) {
            if (window.MapSheetCamera && window.MapSheetCamera.easeToFocus) {
                window.MapSheetCamera.easeToFocus(mapInstance, lastFocusPassenger, {
                    mapEl: heroEl || canvasEl,
                    padding: pad,
                    zoom: Math.min(20, DRIVER_ZOOM + 0.35),
                    pitch: 0,
                    bearing: 0,
                    duration: duration,
                });
            }
            return;
        }

        if (cameraFocusMode === 'overview') {
            fitToMarkers(lastDriverPos || realDriverCoords(), {
                duration: duration,
                padding: pad,
            });
            return;
        }

        if (isPickupDropoffRouteView()) {
            fitToMarkers(lastDriverPos || realDriverCoords(), {
                duration: duration,
                padding: pad,
            });
            return;
        }

        var pos = lastDriverPos || realDriverCoords();
        if (!pos) {
            return;
        }

        if (navActive()) {
            applyNavCamera(pos, lastHeading, { duration: duration, padding: pad });
            return;
        }

        var zoom = mapInstance.getZoom ? mapInstance.getZoom() : DRIVER_ZOOM;
        if (window.MapSheetCamera && window.MapSheetCamera.easeToFocus) {
            window.MapSheetCamera.easeToFocus(mapInstance, pos, {
                mapEl: heroEl || canvasEl,
                padding: pad,
                zoom: zoom,
                pitch: 0,
                bearing: 0,
                duration: duration,
            });
        } else {
            mapInstance.easeTo({
                center: [pos.lng, pos.lat],
                zoom: zoom,
                pitch: 0,
                bearing: 0,
                padding: pad,
                duration: duration,
            });
        }
        setDriverHeading(lastHeading);
    }

    /** Sheet đón đổi trạng thái peek↔full — đẩy mũi tên vào giữa vùng map lộ. */
    function setSheetState(state) {
        sheetState.peek = state && typeof state.peek === 'boolean' ? state.peek : sheetState.peek;
        sheetState.heightPx = state && typeof state.heightPx === 'number' ? state.heightPx : sheetState.heightPx;
        refitSheetCamera({ duration: 400 });
    }

    function focusPickupRoute(destLat, destLng) {
        if (!mapInstance || !window.goongjs) {
            return;
        }
        var driver = lastDriverPos || realDriverCoords();
        var pickup = pickupPin() || (
            Number.isFinite(destLat) && Number.isFinite(destLng)
                ? { lat: destLat, lng: destLng }
                : null
        );
        if (driver && pickup) {
            syncPickupRoute(driver);
            fitToMarkers(driver, { duration: TRIP_FIT_DURATION });
            return;
        }
        if (driver) {
            fitToMarkers(driver, { duration: TRIP_FIT_DURATION });
        }
    }

    function recenterOnDriver() {
        var coords = parsedInputCoords() || realDriverCoords();
        if (!coords || !mapInstance) {
            return;
        }
        if (navActive()) {
            syncPickupRoute(coords);
            applyNavCamera(coords, resolveHeading(null, lastDriverPos, coords), { duration: TRIP_FIT_DURATION });
            return;
        }
        if (hasActiveTripPins()) {
            syncPickupRoute(coords);
            fitToMarkers(coords, { duration: TRIP_FIT_DURATION });
            return;
        }
        mapInstance.easeTo({
            center: [coords.lng, coords.lat],
            zoom: DRIVER_ZOOM,
            pitch: 0,
            bearing: 0,
            duration: TRIP_FIT_DURATION,
        });
        setDriverHeading(resolveHeading(null, null, coords));
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
            ensureRouteLayer();
            if (hasRealDriverCoords) {
                var heading = window.DriverLocationGps && window.DriverLocationGps.getLastKnownHeading
                    ? window.DriverLocationGps.getLastKnownHeading()
                    : null;
                lastDriverPos = { lat: start.lat, lng: start.lng };
                if (heading != null) {
                    lastHeading = heading;
                }
                placeDriverMarker(start.lat, start.lng, heading);
            }
            placeTripPins();
            // Khôi phục polyline TBT nếu fetch xong trước khi map load.
            if (lastRouteCoords) {
                setRouteCoords(lastRouteCoords);
            } else if (hasRealDriverCoords) {
                syncPickupRoute(start);
            }
            if (navActive() && hasRealDriverCoords) {
                applyNavCamera(start, lastHeading, { duration: 0 });
            } else {
                // Có chuyến (không nav) → fit overview như khách; không có → zoom vị trí TX.
                fitToMarkers(hasRealDriverCoords ? start : null, { duration: 0 });
            }
        });
    }

    document.addEventListener('addressmap:applied', function (event) {
        var detail = event.detail || {};
        if (typeof detail.lat !== 'number' || typeof detail.lng !== 'number') {
            return;
        }
        var next = { lat: detail.lat, lng: detail.lng };
        var heading = resolveHeading(detail.heading, lastDriverPos, next);
        var firstFix = !hasRealDriverCoords;

        placeDriverMarker(detail.lat, detail.lng, heading);
        syncPickupRoute(next);

        // Đang giữ camera theo card khách — chỉ cập nhật marker/route, không giật camera.
        if (cameraFocusMode === 'passenger' || cameraFocusMode === 'overview') {
            lastDriverPos = next;
            if (heading != null) {
                lastHeading = heading;
            }
            hasRealDriverCoords = true;
            return;
        }

        if (navActive()) {
            applyNavCamera(next, heading, { duration: firstFix ? 0 : NAV_EASE_DURATION });
        } else if (hasActiveTripPins()) {
            fitToMarkers(next, {
                duration: firstFix ? 0 : TRIP_FIT_DURATION,
            });
            setDriverHeading(heading);
        } else if (firstFix && mapInstance) {
            // GPS thật đầu tiên — luôn đưa camera về đúng vị trí (không còn ở điểm mặc định).
            mapInstance.jumpTo({ center: [detail.lng, detail.lat], zoom: DRIVER_ZOOM, pitch: 0, bearing: 0 });
            fitToMarkers(next);
            setDriverHeading(heading);
        } else {
            setDriverHeading(heading);
        }

        lastDriverPos = next;
        if (heading != null) {
            lastHeading = heading;
        }
        hasRealDriverCoords = true;
    });

    if (locateBtn) {
        locateBtn.addEventListener('click', function () {
            clearPassengerFocusHold();
            recenterOnDriver();
        });
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

    window.DriverLiveMap = {
        focusPickupRoute: focusPickupRoute,
        togglePassengerFocusCamera: togglePassengerFocusCamera,
        clearPassengerFocusHold: clearPassengerFocusHold,
        syncPickupRoute: function () {
            if (lastDriverPos) {
                syncPickupRoute(lastDriverPos);
            }
        },
        /** Turn-by-turn engine gọi khi có route mới (đã fetch, kèm/không kèm steps). */
        setRoute: function (coords) {
            setRouteCoords(coords);
        },
        /** Sheet đón peek↔full — căn mũi tên TX giữa mép trên và mép popup (giống khách). */
        setSheetState: setSheetState,
        refitSheetCamera: refitSheetCamera,
        /** TBT engine đẩy camera theo từng tick GPS đã tính toán riêng (vd bearing mượt hơn). */
        applyNavCamera: function (pos, heading, options) {
            applyNavCamera(pos, heading, options);
        },
        isNavActive: navActive,
        getPickupNavTarget: function () {
            return pickupNavTarget ? { lat: pickupNavTarget.lat, lng: pickupNavTarget.lng } : null;
        },
        getLastPosition: function () {
            return lastDriverPos ? { lat: lastDriverPos.lat, lng: lastDriverPos.lng } : null;
        },
    };

    loadAssets().then(initMap).catch(function () {
        canvasEl.classList.add('driver-map-hero__canvas--error');
    });
})();
