/**
 * Map chuyến khách: khi tìm TX — radar + mũi tên ở điểm đón; camera căn một lần sát sheet.
 */
(function () {
    'use strict';

    var mapEl = document.getElementById('guest-trip-live-map');
    var canvas = document.getElementById('guest-trip-live-map-canvas');
    var statusEl = document.getElementById('guest-trip-live-status');
    var locateBtn = document.getElementById('guest-trip-locate-btn');
    if (!mapEl || !canvas) {
        return;
    }

    var mapInstance = null;
    var pickupMarker = null;
    var dropoffMarker = null;
    var carMarker = null;
    var radarMarker = null;
    var assetsPromise = null;
    var lastPickup = null;
    var lastUserPos = null;
    var searchingActive = false;
    /** Chỉ auto-center khi mới vào tìm chuyến; sau đó để user kéo/zoom tự do. */
    var searchCameraSettled = false;

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
        el.innerHTML = '<svg width="28" height="28" viewBox="0 0 24 24" fill="none">'
            + '<rect x="4" y="9" width="16" height="7" rx="2" fill="#facc15" stroke="#111" stroke-width="1"/>'
            + '<path d="M7 9l1.5-3h7L17 9" fill="#fde68a" stroke="#111" stroke-width="1"/>'
            + '<circle cx="8" cy="16.5" r="1.4" fill="#111"/><circle cx="16" cy="16.5" r="1.4" fill="#111"/>'
            + '</svg>';
        if (heading != null && !Number.isNaN(Number(heading))) {
            el.style.transform = 'rotate(' + Number(heading) + 'deg)';
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
    }

    function setLiveStatus(text) {
        if (!statusEl) {
            return;
        }
        if (!text) {
            statusEl.textContent = '';
            statusEl.classList.add('d-none');
            return;
        }
        statusEl.textContent = text;
        statusEl.classList.remove('d-none');
    }

    function ensureMap(center) {
        return ensureAssets().then(function () {
            window.goongjs.accessToken = String(window.__goongMaptilesKey || '');
            if (!mapInstance) {
                mapInstance = new window.goongjs.Map({
                    container: canvas,
                    style: 'https://tiles.goong.io/assets/goong_map_web.json',
                    center: center,
                    zoom: 15,
                    interactive: true,
                    attributionControl: false,
                });
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
                pickupMarker = new window.goongjs.Marker({ color: color || '#22c55e' })
                    .setLngLat(lngLat)
                    .addTo(mapInstance);
            } else {
                pickupMarker.setLngLat(lngLat);
            }
            return pickupMarker;
        }
        if (kind === 'dropoff') {
            if (!dropoffMarker) {
                dropoffMarker = new window.goongjs.Marker({ color: color || '#ef4444' })
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

    /** Padding: đặt radar/mũi tên giữa mép trên màn hình và mép trên sheet. */
    function searchCameraPadding() {
        var sheet = document.getElementById('guest-trip-info-sheet');
        var mapH = (mapEl.getBoundingClientRect().height) || window.innerHeight || 600;
        var sheetH = sheet ? sheet.getBoundingClientRect().height : 0;
        if (sheetH < 8) {
            sheetH = Math.round(mapH * 0.42);
        }
        // Luôn chừa tối thiểu ~160px map để radar không bị đẩy khỏi viewport.
        var minVisible = 160;
        var bottom = Math.min(Math.round(sheetH), Math.max(0, Math.round(mapH - minVisible)));
        // top nhỏ → tâm camera nằm giữa vùng map còn lộ (trên sheet ↔ cạnh trên).
        return { top: 24, bottom: bottom, left: 20, right: 20 };
    }

    function placeCar(lat, lng, heading) {
        if (!mapInstance || lat == null || lng == null || !window.goongjs) {
            return;
        }
        var lngLat = [Number(lng), Number(lat)];
        if (!carMarker) {
            carMarker = new window.goongjs.Marker({ element: carIconEl(heading), anchor: 'center' })
                .setLngLat(lngLat)
                .addTo(mapInstance);
            return;
        }
        carMarker.setLngLat(lngLat);
        var el = carMarker.getElement();
        if (el && heading != null && !Number.isNaN(Number(heading))) {
            el.style.transform = 'rotate(' + Number(heading) + 'deg)';
        }
    }

    function clearCar() {
        if (carMarker) {
            carMarker.remove();
            carMarker = null;
        }
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
                    'line-color': '#facc15',
                    'line-width': 4,
                    'line-opacity': 0.85,
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

        var straight = [[fromPos.lng, fromPos.lat], [toPos.lng, toPos.lat]];
        var key = [fromPos.lat, fromPos.lng, toPos.lat, toPos.lng].map(function (n) {
            return Math.round(Number(n) * 2000) / 2000;
        }).join(',');

        if (!lastRouteCoords) {
            setRouteCoords(straight);
        }

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
            .catch(function () { /* giữ đường thẳng đã vẽ tạm */ })
            .finally(function () {
                routeFetchInFlight = false;
            });
    }

    function fitOrFly(points, zoom, options) {
        if (!mapInstance || !points.length) {
            return;
        }
        options = options || {};
        var padding = options.padding;
        if (points.length === 1) {
            var ease = { center: points[0], zoom: zoom || 15, duration: 500 };
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
            maxZoom: 15,
            duration: 500,
        });
    }

    function refitSearchCamera() {
        if (!searchingActive || !mapInstance || !lastPickup) {
            return;
        }
        fitOrFly([[lastPickup.lng, lastPickup.lat]], 16, { padding: searchCameraPadding() });
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

    function locateMe(fly) {
        // Đang tìm: radar giữ ở điểm đón; nút định vị chỉ kéo camera về đó.
        if (searchingActive && lastPickup) {
            syncRadarAroundLocation();
            if (fly !== false && mapInstance) {
                refitSearchCamera();
            }
            readGps({ highAccuracy: true, timeout: 12000, maximumAge: 5000 })
                .then(function (pos) { lastUserPos = pos; })
                .catch(function () { /* ignore */ });
            return Promise.resolve(lastPickup);
        }

        return readGps({ highAccuracy: true, timeout: 12000, maximumAge: 5000 })
            .then(function (pos) {
                lastUserPos = pos;
                syncRadarAroundLocation();
                if (fly !== false && mapInstance) {
                    fitOrFly([[pos.lng, pos.lat]], 16);
                }
                return pos;
            })
            .catch(function () {
                var fallback = lastPickup;
                if (fallback && mapInstance && fly !== false) {
                    fitOrFly([[fallback.lng, fallback.lat]], 16);
                }
                return fallback;
            });
    }

    function updateFromBooking(booking) {
        if (!booking || booking.trip_status === 'completed' || booking.trip_status === 'cancelled') {
            searchingActive = false;
            searchCameraSettled = false;
            showMapShell(false);
            setLiveStatus('');
            clearCar();
            clearRadarPin();
            clearRoute();
            return;
        }

        var pickupLat = booking.pickup_lat;
        var pickupLng = booking.pickup_lng;
        var dropLat = booking.dropoff_lat;
        var dropLng = booking.dropoff_lng;
        var searching = !booking.has_driver;
        if (!searching) {
            searchCameraSettled = false;
        }
        searchingActive = searching;
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

        var statusLine = booking.driver_status_line
            || (driver && driver.status_line)
            || (inTrip ? 'Đang trong chuyến' : '')
            || booking.guest_status_label
            || booking.progress_label
            || '';
        setLiveStatus(searching ? '' : statusLine);

        var center = [Number(pickupLng), Number(pickupLat)];
        ensureMap(center).then(function (map) {
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
                placePin('pickup', pickupLat, pickupLng, '#22c55e');
            }

            if (dropLat != null && dropLng != null && (inTrip || enRoute)) {
                placePin('dropoff', dropLat, dropLng, '#ef4444');
            }

            var dLat = driver && driver.lat != null ? Number(driver.lat) : null;
            var dLng = driver && driver.lng != null ? Number(driver.lng) : null;
            var heading = driver && driver.heading != null ? Number(driver.heading) : null;

            if ((enRoute || inTrip) && dLat != null && dLng != null) {
                placeCar(dLat, dLng, heading);
                var pts = [[Number(pickupLng), Number(pickupLat)], [dLng, dLat]];
                if (inTrip && dropLat != null && dropLng != null) {
                    pts.push([Number(dropLng), Number(dropLat)]);
                }
                fitOrFly(pts, 14);

                if (enRoute) {
                    syncRouteLine({ lat: dLat, lng: dLng }, lastPickup, 'to_pickup');
                } else if (inTrip && dropLat != null && dropLng != null) {
                    syncRouteLine(lastPickup, { lat: Number(dropLat), lng: Number(dropLng) }, 'to_dropoff');
                } else {
                    clearRoute();
                }
            } else {
                clearCar();
                clearRoute();
                if (searching) {
                    // Chỉ căn lần đầu theo điểm đón (sát sheet); poll sau không kéo camera.
                    if (!searchCameraSettled) {
                        searchCameraSettled = true;
                        window.requestAnimationFrame(function () {
                            refitSearchCamera();
                            window.setTimeout(function () {
                                if (searchingActive) {
                                    refitSearchCamera();
                                }
                            }, 320);
                        });
                    }
                } else if (dropLat != null && dropLng != null) {
                    fitOrFly([center, [Number(dropLng), Number(dropLat)]], 13);
                } else {
                    fitOrFly([center], 15);
                }
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
    };
})();
