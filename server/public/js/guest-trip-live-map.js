/**
 * Map chuyến khách: khi tìm TX — radar + mũi tên ở điểm đón; camera căn một lần sát sheet.
 */
(function () {
    'use strict';

    var mapEl = document.getElementById('guest-trip-live-map');
    var canvas = document.getElementById('guest-trip-live-map-canvas');
    var locateBtn = document.getElementById('guest-trip-locate-btn');
    if (!mapEl || !canvas) {
        return;
    }

    var mapInstance = null;
    var pickupMarker = null;
    var dropoffMarker = null;
    var carMarker = null;
    var radarMarker = null;
    /** Marker vị trí GPS của khách (nút định vị) — không dùng điểm đón / TX / điểm đến. */
    var guestSelfMarker = null;
    var assetsPromise = null;
    var lastPickup = null;
    var lastUserPos = null;
    var searchingActive = false;
    /** Chỉ auto-center khi mới vào tìm chuyến; sau đó để user kéo/zoom tự do. */
    var searchCameraSettled = false;
    /** Chỉ căn camera lần đầu khi TX nhận / đang chạy — poll không kéo nữa. */
    var trackingCameraSettled = false;
    /** Khách đã kéo/zoom map hoặc đã có hành động camera → không auto fitBounds/easeTo. */
    var userMovedCamera = false;
    var cameraGestureBound = false;
    var lastCameraBookingRef = null;
    /** Đi đón / đang chạy: zoom sát hơn ~30% rồi +25% nữa (cap 20). */
    var TRIP_ACTIVE_ZOOM = Math.min(20, 15 * 1.3 * 1.25);
    var TRIP_ACTIVE_MAX_ZOOM = Math.min(20, 15 * 1.3 * 1.25);

    var lastDriverPos = null;
    /** 'follow' | 'overview' | 'driver' — nhấn card TX để xen kẽ overview / zoom TX. */
    var cameraFocusMode = 'follow';

    var ROUTE_SOURCE_ID = 'guest-trip-route';
    var ROUTE_LAYER_ID = 'guest-trip-route-line';
    var ROUTE_REFETCH_MS = 25000;
    var lastRouteCoords = null;
    var lastRouteFetchKey = '';
    var lastRouteFetchAt = 0;
    var routeFetchInFlight = false;
    var lastRouteContext = '';

    function ensureAssets() {
        if (window.goongjs && window.__goongMaptilesKey) {
            return Promise.resolve();
        }
        if (!window.__goongMaptilesKey) {
            return Promise.reject(new Error('no_maptiles'));
        }
        if (assetsPromise) {
            return assetsPromise;
        }
        assetsPromise = new Promise(function (resolve, reject) {
            if (!document.querySelector('link[data-guest-trip-goong]')) {
                var link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = 'https://cdn.jsdelivr.net/npm/@goongmaps/goong-js@1.0.9/dist/goong-js.css';
                link.setAttribute('data-guest-trip-goong', '1');
                document.head.appendChild(link);
            }
            if (window.goongjs) {
                resolve();
                return;
            }
            var script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/@goongmaps/goong-js@1.0.9/dist/goong-js.js';
            script.onload = function () { resolve(); };
            script.onerror = function () { reject(new Error('goong_load')); };
            document.body.appendChild(script);
        });
        return assetsPromise;
    }

    function carIconEl(heading) {
        var el = document.createElement('div');
        el.className = 'guest-trip-car-marker';
        el.innerHTML = ''
            + '<div class="guest-trip-car-marker__radar" aria-hidden="true">'
            + '<span></span><span></span><span></span>'
            + '</div>'
            + '<div class="guest-trip-car-marker__arrow">'
            + '<svg viewBox="0 0 48 48" width="44" height="44" aria-hidden="true">'
            + '<defs>'
            + '<linearGradient id="navArrowFill" x1="24" y1="4" x2="24" y2="44" gradientUnits="userSpaceOnUse">'
            + '<stop stop-color="#93c5fd"/><stop offset="1" stop-color="#3b82f6"/>'
            + '</linearGradient>'
            + '<filter id="navArrowSh" x="-25%" y="-15%" width="150%" height="150%">'
            + '<feDropShadow dx="0" dy="1.5" stdDeviation="1.4" flood-color="#000" flood-opacity=".45"/>'
            + '</filter>'
            + '</defs>'
            + '<g filter="url(#navArrowSh)">'
            + '<path d="M24 5.5L39.5 38.2c.55 1.15-.55 2.4-1.75 1.95L24 33.4 10.25 40.15c-1.2.45-2.3-.8-1.75-1.95L24 5.5z" fill="url(#navArrowFill)" stroke="#0f172a" stroke-width="1.8" stroke-linejoin="round"/>'
            + '<path d="M24 14.2L32.6 33.1 24 29.4 15.4 33.1 24 14.2z" fill="#fff" opacity=".92"/>'
            + '</g>'
            + '</svg>'
            + '</div>';
        var arrow = el.querySelector('.guest-trip-car-marker__arrow');
        if (arrow && heading != null && !Number.isNaN(Number(heading))) {
            arrow.style.transform = 'rotate(' + Number(heading) + 'deg)';
        }
        return el;
    }

    function radarPinEl() {
        var el = document.createElement('div');
        el.className = 'guest-trip-radar-pin';
        el.innerHTML = ''
            + '<div class="guest-trip-radar-pin__rings" aria-hidden="true"><span></span><span></span><span></span></div>'
            + '<div class="guest-trip-radar-pin__arrow" aria-hidden="true">'
            + '<svg viewBox="0 0 24 24" fill="currentColor">'
            + '<path d="M12 2.5L4.8 19.2c-.25.58.4 1.15.96.84L12 16.7l6.24 3.34c.56.3 1.21-.26.96-.84L12 2.5z"/>'
            + '</svg></div>';
        return el;
    }

    function showMapShell(show) {
        if (show) {
            mapEl.hidden = false;
            mapEl.classList.remove('d-none');
        } else {
            mapEl.hidden = true;
            mapEl.classList.add('d-none');
        }
        if (locateBtn) {
            locateBtn.hidden = !show;
            locateBtn.classList.toggle('d-none', !show);
        }
        if (show && window.GuestTripSheet && window.GuestTripSheet.syncLocateFabLift) {
            window.requestAnimationFrame(function () {
                window.GuestTripSheet.syncLocateFabLift();
            });
        }
    }

    function bindCameraGestureGuards(map) {
        if (!map || cameraGestureBound) {
            return;
        }
        cameraGestureBound = true;
        var markUserMove = function (event) {
            // Chỉ gesture thật (có originalEvent); bỏ qua easeTo/fitBounds programmatic.
            if (event && event.originalEvent) {
                userMovedCamera = true;
            }
        };
        map.on('dragstart', markUserMove);
        map.on('zoomstart', markUserMove);
        map.on('rotatestart', markUserMove);
        map.on('pitchstart', markUserMove);
    }

    function canAutoMoveCamera() {
        return !userMovedCamera;
    }

    function resumeAutoCamera() {
        userMovedCamera = false;
    }

    function ensureMap(center) {
        return ensureAssets().then(function () {
            window.goongjs.accessToken = String(window.__goongMaptilesKey || '');
            if (!mapInstance) {
                mapInstance = new window.goongjs.Map({
                    container: canvas,
                    style: 'https://tiles.goong.io/assets/goong_map_web.json',
                    center: center,
                    zoom: Math.min(20, 15 * 1.25),
                    interactive: true,
                    attributionControl: false,
                });
                bindCameraGestureGuards(mapInstance);
            }
            return mapInstance;
        });
    }

    function placePin(kind, lat, lng, color) {
        if (!mapInstance || lat == null || lng == null || !window.goongjs) {
            return null;
        }
        var lngLat = [Number(lng), Number(lat)];
        if (kind === 'pickup') {
            if (!pickupMarker) {
                pickupMarker = new window.goongjs.Marker({ color: color || '#3b82f6' })
                    .setLngLat(lngLat)
                    .addTo(mapInstance);
            } else {
                pickupMarker.setLngLat(lngLat);
            }
            return pickupMarker;
        }
        if (kind === 'dropoff') {
            if (!dropoffMarker) {
                dropoffMarker = new window.goongjs.Marker({ color: color || '#eab308' })
                    .setLngLat(lngLat)
                    .addTo(mapInstance);
            } else {
                dropoffMarker.setLngLat(lngLat);
            }
            return dropoffMarker;
        }
        return null;
    }

    function clearRadarPin() {
        if (radarMarker) {
            radarMarker.remove();
            radarMarker = null;
        }
    }

    function placeRadarPin(lat, lng) {
        if (!mapInstance || lat == null || lng == null || !window.goongjs) {
            return;
        }
        var lngLat = [Number(lng), Number(lat)];
        if (!radarMarker) {
            radarMarker = new window.goongjs.Marker({
                element: radarPinEl(),
                anchor: 'center',
            }).setLngLat(lngLat).addTo(mapInstance);
            return;
        }
        radarMarker.setLngLat(lngLat);
    }

    function activeLocateTarget() {
        // Đang tìm: luôn điểm đón (radar không nhảy sang GPS rồi mất khỏi khung hình).
        if (searchingActive && lastPickup) {
            return lastPickup;
        }
        if (lastUserPos) {
            return lastUserPos;
        }
        if (lastPickup) {
            return lastPickup;
        }
        return null;
    }

    /**
     * Padding map = vùng lộ giữa cạnh trên màn hình và cạnh trên sheet.
     * Sheet xổ ra giữa màn → bottom lớn → item căn giữa dải lộ (MapSheetCamera chung).
     */
    function sheetCameraPadding() {
        return window.MapSheetCamera && window.MapSheetCamera.paddingFromEdges
            ? window.MapSheetCamera.paddingFromEdges({
                mapEl: mapEl,
                sheetEl: document.getElementById('guest-trip-info-sheet'),
                side: 28,
                topExtra: 12,
            })
            : { top: 12, bottom: 0, left: 28, right: 28 };
    }

    function searchCameraPadding() {
        return sheetCameraPadding();
    }

    function trackingCameraPadding() {
        return sheetCameraPadding();
    }

    function fitOrFlyOnce(points, zoom, options) {
        fitOrFlyForced(points, zoom, options);
    }

    function placeCar(lat, lng, heading) {
        if (!mapInstance || lat == null || lng == null || !window.goongjs) {
            return;
        }
        var lngLat = [Number(lng), Number(lat)];
        lastDriverPos = { lat: Number(lat), lng: Number(lng) };
        if (!carMarker) {
            var fresh = carIconEl(heading);
            fresh.dataset.markerVer = 'nav-radar-v2';
            carMarker = new window.goongjs.Marker({ element: fresh, anchor: 'center' })
                .setLngLat(lngLat)
                .addTo(mapInstance);
            return;
        }
        carMarker.setLngLat(lngLat);
        var el = carMarker.getElement();
        if (el && el.dataset.markerVer !== 'nav-radar-v2') {
            var next = carIconEl(heading);
            next.dataset.markerVer = 'nav-radar-v2';
            el.className = next.className;
            el.dataset.markerVer = 'nav-radar-v2';
            el.innerHTML = next.innerHTML;
            return;
        }
        if (el && !el.dataset.markerVer) {
            el.dataset.markerVer = 'nav-radar-v2';
        }
        var arrow = el ? el.querySelector('.guest-trip-car-marker__arrow') : null;
        if (arrow && heading != null && !Number.isNaN(Number(heading))) {
            arrow.style.transform = 'rotate(' + Number(heading) + 'deg)';
        }
    }

    function clearCar() {
        if (carMarker) {
            carMarker.remove();
            carMarker = null;
        }
        lastDriverPos = null;
        cameraFocusMode = 'follow';
    }

    /**
     * Nhấn card TX / khách: lần 1 zoom vào đối phương, lần 2 thu sheet + overview khoảng cách.
     * Lặp lại như vậy (đồng bộ màn tài xế).
     */
    function toggleDriverFocusCamera() {
        if (!mapInstance || !lastDriverPos) {
            return false;
        }
        var pad = trackingCameraPadding();
        if (cameraFocusMode !== 'driver') {
            fitOrFlyOnce([[lastDriverPos.lng, lastDriverPos.lat]], Math.min(20, TRIP_ACTIVE_ZOOM + 0.4), {
                padding: pad,
                maxZoom: TRIP_ACTIVE_MAX_ZOOM,
            });
            cameraFocusMode = 'driver';
            return true;
        }

        var sheet = document.getElementById('guest-trip-info-sheet');
        if (sheet) {
            sheet.dataset.userToggled = '1';
        }
        if (window.GuestTripSheet && typeof window.GuestTripSheet.collapse === 'function') {
            window.GuestTripSheet.collapse();
        }

        cameraFocusMode = 'overview';
        window.setTimeout(function () {
            if (!mapInstance || !lastDriverPos) {
                return;
            }
            var pts = [[lastDriverPos.lng, lastDriverPos.lat]];
            if (lastPickup) {
                pts.push([lastPickup.lng, lastPickup.lat]);
            }
            fitOrFlyOnce(pts, null, {
                padding: trackingCameraPadding(),
                maxZoom: Math.min(20, 16 * 1.25),
            });
        }, 160);
        return true;
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
        if (!mapInstance.getSource(ROUTE_SOURCE_ID)) {
            mapInstance.addSource(ROUTE_SOURCE_ID, { type: 'geojson', data: emptyRouteGeo() });
        }
        if (!mapInstance.getLayer(ROUTE_LAYER_ID)) {
            mapInstance.addLayer({
                id: ROUTE_LAYER_ID,
                type: 'line',
                source: ROUTE_SOURCE_ID,
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
        var source = mapInstance.getSource(ROUTE_SOURCE_ID);
        if (!source) {
            return;
        }
        source.setData(lastRouteCoords ? lineFeature(lastRouteCoords) : emptyRouteGeo());
    }

    function clearRoute() {
        lastRouteFetchKey = '';
        lastRouteContext = '';
        setRouteCoords(null);
    }

    /** Vẽ tuyến TX→điểm đón (đang đến đón) hoặc điểm đón→điểm trả (đang chạy), theo đường thật qua Goong Direction. */
    function syncRouteLine(fromPos, toPos, context) {
        if (!fromPos || !toPos) {
            clearRoute();
            return;
        }

        if (context && context !== lastRouteContext) {
            // Đổi giai đoạn (đến đón ↔ đang chạy) — bỏ polyline cũ, vẽ lại từ đường thẳng tạm.
            lastRouteContext = context;
            lastRouteCoords = null;
            lastRouteFetchKey = '';
        }

        var key = [fromPos.lat, fromPos.lng, toPos.lat, toPos.lng].map(function (n) {
            return Math.round(Number(n) * 2000) / 2000;
        }).join(',');

        if (!window.__geocodeDirectionUrl || routeFetchInFlight) {
            return;
        }
        var now = Date.now();
        if (key === lastRouteFetchKey && (now - lastRouteFetchAt) < ROUTE_REFETCH_MS) {
            return;
        }

        routeFetchInFlight = true;
        lastRouteFetchKey = key;
        lastRouteFetchAt = now;

        var params = new URLSearchParams({
            origin_lat: String(fromPos.lat),
            origin_lng: String(fromPos.lng),
            dest_lat: String(toPos.lat),
            dest_lng: String(toPos.lng),
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
            .catch(function () { /* không vẽ line thẳng — chờ direction */ })
            .finally(function () {
                routeFetchInFlight = false;
            });
    }

    function fitOrFly(points, zoom, options) {
        if (!mapInstance || !points.length || !canAutoMoveCamera()) {
            return;
        }
        applyCamera(points, zoom, options);
    }

    /** Bay camera luôn (nút định vị / card TX / kéo sheet) — không phụ thuộc khóa poll. */
    function applyCamera(points, zoom, options) {
        if (!mapInstance || !points.length) {
            return;
        }
        options = options || {};
        var padding = options.padding;
        var maxZoom = options.maxZoom != null ? options.maxZoom : Math.min(20, 15 * 1.25);
        var duration = options.duration != null ? options.duration : 500;
        if (points.length === 1) {
            var ease = { center: points[0], zoom: zoom || Math.min(20, 15 * 1.25), duration: duration };
            if (padding) {
                ease.padding = padding;
            }
            mapInstance.easeTo(ease);
            return;
        }
        var bounds = new window.goongjs.LngLatBounds(points[0], points[0]);
        points.forEach(function (p) { bounds.extend(p); });
        mapInstance.fitBounds(bounds, {
            padding: padding || 48,
            maxZoom: maxZoom,
            duration: duration,
        });
    }

    /** Hành động user: bay camera rồi khóa — poll không kéo loạn nữa. */
    function fitOrFlyForced(points, zoom, options) {
        resumeAutoCamera();
        applyCamera(points, zoom, options);
        userMovedCamera = true;
    }

    function refitSearchCamera() {
        refitSheetCamera();
    }

    /**
     * Kéo sheet: căn điểm focus (TX / điểm đón) vào giữa vùng map còn lộ.
     * Có cả đón+trả không TX → fit lại khung lộ trình trong dải map lộ (không kéo 1 điểm).
     */
    function refitSheetCamera() {
        if (!mapInstance) {
            return;
        }
        // Chỉ có đón+trả, không có vị trí TX → fitBounds trong vùng map lộ.
        if (!lastDriverPos && pickupMarker && dropoffMarker) {
            try {
                var p = pickupMarker.getLngLat();
                var d = dropoffMarker.getLngLat();
                if (p && d) {
                    resumeAutoCamera();
                    applyCamera(
                        [[p.lng, p.lat], [d.lng, d.lat]],
                        null,
                        { padding: sheetCameraPadding(), duration: 280, maxZoom: mapInstance.getZoom() }
                    );
                    userMovedCamera = true;
                }
            } catch (e) { /* ignore */ }
            return;
        }
        var focus = null;
        if (lastDriverPos && lastDriverPos.lat != null && lastDriverPos.lng != null) {
            focus = { lng: Number(lastDriverPos.lng), lat: Number(lastDriverPos.lat) };
        } else if (lastPickup && lastPickup.lat != null && lastPickup.lng != null) {
            focus = { lng: Number(lastPickup.lng), lat: Number(lastPickup.lat) };
        } else {
            var center = mapInstance.getCenter();
            if (center) {
                focus = { lng: center.lng, lat: center.lat };
            }
        }
        if (!focus) {
            return;
        }
        resumeAutoCamera();
        if (window.MapSheetCamera && window.MapSheetCamera.easeToFocus) {
            window.MapSheetCamera.easeToFocus(mapInstance, focus, {
                mapEl: mapEl,
                padding: sheetCameraPadding(),
                zoom: mapInstance.getZoom(),
                duration: 280,
            });
        } else {
            mapInstance.easeTo({
                center: [focus.lng, focus.lat],
                zoom: mapInstance.getZoom(),
                padding: sheetCameraPadding(),
                duration: 280,
            });
        }
        userMovedCamera = true;
    }

    function readGps(options) {
        options = options || {};
        return new Promise(function (resolve, reject) {
            if (!navigator.geolocation) {
                reject(new Error('no_geo'));
                return;
            }
            navigator.geolocation.getCurrentPosition(
                function (pos) {
                    resolve({
                        lat: pos.coords.latitude,
                        lng: pos.coords.longitude,
                    });
                },
                function (err) {
                    reject(err || new Error('geo_fail'));
                },
                {
                    enableHighAccuracy: !!options.highAccuracy,
                    timeout: options.timeout || 10000,
                    maximumAge: options.maximumAge != null ? options.maximumAge : 15000,
                }
            );
        });
    }

    function syncRadarAroundLocation() {
        if (!searchingActive) {
            clearRadarPin();
            return;
        }
        var target = activeLocateTarget();
        if (!target) {
            clearRadarPin();
            return;
        }
        placeRadarPin(target.lat, target.lng);
    }

    function guestSelfPinEl() {
        var el = document.createElement('div');
        el.className = 'guest-trip-self-marker';
        el.innerHTML = ''
            + '<span class="guest-trip-self-marker__pulse" aria-hidden="true"></span>'
            + '<span class="guest-trip-self-marker__dot" aria-hidden="true"></span>';
        return el;
    }

    function placeGuestSelfMarker(lat, lng) {
        if (!mapInstance || lat == null || lng == null || !window.goongjs) {
            return;
        }
        var lngLat = [Number(lng), Number(lat)];
        if (!guestSelfMarker) {
            guestSelfMarker = new window.goongjs.Marker({
                element: guestSelfPinEl(),
                anchor: 'center',
            }).setLngLat(lngLat).addTo(mapInstance);
            return;
        }
        guestSelfMarker.setLngLat(lngLat);
    }

    function clearGuestSelfMarker() {
        if (guestSelfMarker) {
            guestSelfMarker.remove();
            guestSelfMarker = null;
        }
    }

    function notifyLocateFail() {
        var msg = 'Không lấy được vị trí hiện tại của bạn. Hãy bật GPS và thử lại.';
        if (window.AppFlash && window.AppFlash.show) {
            window.AppFlash.show(msg, { variant: 'warning', title: 'Vị trí khách', autoDismiss: 5000 });
            return;
        }
        if (window.AppDialog && window.AppDialog.alert) {
            window.AppDialog.alert(msg, { variant: 'warning', title: 'Vị trí khách' });
        }
    }

    function flyToGuestPosition(pos) {
        if (!pos || mapInstance == null) {
            return;
        }
        placeGuestSelfMarker(pos.lat, pos.lng);
        fitOrFlyForced([[Number(pos.lng), Number(pos.lat)]], Math.min(20, 16 * 1.25), {
            padding: sheetCameraPadding(),
        });
    }

    function locateMe(fly) {
        // Chỉ GPS khách đang đứng — không nhảy sang TX / điểm đón / điểm đến.
        return readGps({ highAccuracy: true, timeout: 15000, maximumAge: 0 })
            .then(function (pos) {
                lastUserPos = pos;
                if (fly !== false) {
                    flyToGuestPosition(pos);
                } else {
                    placeGuestSelfMarker(pos.lat, pos.lng);
                }
                return pos;
            })
            .catch(function () {
                // Chỉ dùng GPS khách đã lấy trước đó — không fallback điểm đón/TX.
                if (lastUserPos && fly !== false) {
                    flyToGuestPosition(lastUserPos);
                    return lastUserPos;
                }
                notifyLocateFail();
                return null;
            });
    }

    function updateFromBooking(booking) {
        if (!booking || booking.trip_status === 'completed' || booking.trip_status === 'cancelled') {
            searchingActive = false;
            searchCameraSettled = false;
            trackingCameraSettled = false;
            userMovedCamera = false;
            lastCameraBookingRef = null;
            lastDriverPos = null;
            cameraFocusMode = 'follow';
            showMapShell(false);
            clearCar();
            clearRadarPin();
            clearGuestSelfMarker();
            clearRoute();
            lastUserPos = null;
            return;
        }

        var bookingRef = booking.booking_reference || booking.id || '';
        if (bookingRef && bookingRef !== lastCameraBookingRef) {
            lastCameraBookingRef = bookingRef;
            userMovedCamera = false;
            searchCameraSettled = false;
            trackingCameraSettled = false;
            cameraFocusMode = 'follow';
        }

        var pickupLat = booking.pickup_lat;
        var pickupLng = booking.pickup_lng;
        var dropLat = booking.dropoff_lat;
        var dropLng = booking.dropoff_lng;
        var searching = !booking.has_driver;
        var prevSearching = searchingActive;
        searchingActive = searching;
        if (searching) {
            trackingCameraSettled = false;
        } else {
            searchCameraSettled = false;
            // Vừa có TX nhận → cho phép căn camera 1 lần quanh mũi tên TX.
            if (prevSearching) {
                trackingCameraSettled = false;
                userMovedCamera = false;
            }
        }
        var driver = booking.driver || null;
        var stage = driver ? String(driver.stage || '') : '';
        var inTrip = stage === 'picked_up' || stage === 'running';
        var enRoute = stage === 'assigned' || stage === 'at_pickup';

        if (pickupLat == null || pickupLng == null) {
            showMapShell(false);
            return;
        }

        lastPickup = { lat: Number(pickupLat), lng: Number(pickupLng) };

        showMapShell(true);

        var center = [Number(pickupLng), Number(pickupLat)];
        ensureMap(center).then(function (map) {
            bindCameraGestureGuards(map);
            window.requestAnimationFrame(function () {
                try { map.resize(); } catch (e) { /* ignore */ }
            });

            if (searching) {
                // Đang tìm: luôn hiện radar + mũi tên tại điểm đón.
                if (pickupMarker) {
                    pickupMarker.remove();
                    pickupMarker = null;
                }
                placeRadarPin(lastPickup.lat, lastPickup.lng);
            } else {
                clearRadarPin();
                placePin('pickup', pickupLat, pickupLng, '#3b82f6');
            }

            if (dropLat != null && dropLng != null && (inTrip || enRoute)) {
                placePin('dropoff', dropLat, dropLng, '#eab308');
            } else if (dropoffMarker) {
                dropoffMarker.remove();
                dropoffMarker = null;
            }

            var dLat = driver && driver.lat != null ? Number(driver.lat) : null;
            var dLng = driver && driver.lng != null ? Number(driver.lng) : null;
            var heading = driver && driver.heading != null ? Number(driver.heading) : null;

            if ((enRoute || inTrip) && dLat != null && dLng != null) {
                placeCar(dLat, dLng, heading);
                // Chỉ căn lần đầu khi TX nhận chuyến — poll sau không fitBounds nhảy loạn.
                if (!trackingCameraSettled && canAutoMoveCamera()) {
                    trackingCameraSettled = true;
                    applyCamera([[dLng, dLat]], TRIP_ACTIVE_ZOOM, {
                        padding: sheetCameraPadding(),
                        maxZoom: TRIP_ACTIVE_MAX_ZOOM,
                    });
                    userMovedCamera = true;
                }

                if (enRoute) {
                    syncRouteLine({ lat: dLat, lng: dLng }, lastPickup, 'to_pickup');
                } else if (inTrip && dropLat != null && dropLng != null) {
                    syncRouteLine(
                        { lat: dLat, lng: dLng },
                        { lat: Number(dropLat), lng: Number(dropLng) },
                        'to_dropoff'
                    );
                } else {
                    clearRoute();
                }
            } else {
                clearCar();
                clearRoute();
                if (searching) {
                    // Chỉ căn lần đầu theo điểm đón; poll sau không kéo camera.
                    if (!searchCameraSettled && canAutoMoveCamera()) {
                        searchCameraSettled = true;
                        window.requestAnimationFrame(function () {
                            if (!searchingActive || !lastPickup || !mapInstance) {
                                return;
                            }
                            applyCamera([[lastPickup.lng, lastPickup.lat]], Math.min(20, 16 * 1.25), {
                                padding: sheetCameraPadding(),
                            });
                            userMovedCamera = true;
                        });
                    }
                }
                // Không auto fit pickup↔dropoff — tránh nhảy zoom khoảng cách mỗi lần poll.
            }
        }).catch(function () {
            /* map optional */
        });
    }

    function resize() {
        if (!mapInstance) {
            return;
        }
        try {
            mapInstance.resize();
        } catch (e) {
            /* ignore */
        }
    }

    if (locateBtn) {
        locateBtn.addEventListener('click', function () {
            locateBtn.disabled = true;
            locateMe(true).finally(function () {
                locateBtn.disabled = false;
            });
        });
    }

    window.GuestTripLiveMap = {
        updateFromBooking: updateFromBooking,
        resize: resize,
        locateMe: locateMe,
        refitSearchCamera: refitSearchCamera,
        refitSheetCamera: refitSheetCamera,
        toggleDriverFocusCamera: toggleDriverFocusCamera,
    };
})();
