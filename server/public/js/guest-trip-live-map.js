/**
 * Map chuyến khách: radar khi tìm TX; icon ô tô cập nhật sau khi nhận / trong chuyến.
 */
(function () {
    'use strict';

    var mapEl = document.getElementById('guest-trip-live-map');
    var canvas = document.getElementById('guest-trip-live-map-canvas');
    var radarEl = document.getElementById('guest-trip-radar');
    var statusEl = document.getElementById('guest-trip-live-status');
    if (!mapEl || !canvas) {
        return;
    }

    var mapInstance = null;
    var pickupMarker = null;
    var dropoffMarker = null;
    var carMarker = null;
    var assetsPromise = null;

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

    function showMapShell(show) {
        if (show) {
            mapEl.hidden = false;
            mapEl.classList.remove('d-none');
        } else {
            mapEl.hidden = true;
            mapEl.classList.add('d-none');
        }
    }

    function setRadar(visible, waitProgress) {
        if (!radarEl) {
            return;
        }
        radarEl.classList.toggle('d-none', !visible);
        if (!visible || !waitProgress) {
            return;
        }
        var title = document.getElementById('guest-trip-radar-title');
        var hint = document.getElementById('guest-trip-radar-hint');
        var countdown = document.getElementById('guest-trip-radar-countdown');
        if (title) {
            title.textContent = waitProgress.label || waitProgress.title || 'Đang tìm tài xế gần bạn…';
        }
        if (hint) {
            hint.textContent = waitProgress.hint || 'Hệ thống sẽ tự hủy sau 10 phút nếu không có tài xế nhận.';
        }
        if (countdown) {
            var cd = waitProgress.countdown_label || '';
            if (!cd && waitProgress.deadline_at) {
                var remain = Math.max(0, Math.floor((Date.parse(waitProgress.deadline_at) - Date.now()) / 1000));
                if (remain > 0) {
                    var m = Math.floor(remain / 60);
                    var s = remain % 60;
                    cd = 'Còn ' + m + ':' + String(s).padStart(2, '0');
                }
            }
            if (cd) {
                countdown.textContent = cd;
                countdown.classList.remove('d-none');
                countdown.hidden = false;
            } else {
                countdown.textContent = '';
                countdown.classList.add('d-none');
                countdown.hidden = true;
            }
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

    function fitOrFly(points, zoom) {
        if (!mapInstance || !points.length) {
            return;
        }
        if (points.length === 1) {
            mapInstance.easeTo({ center: points[0], zoom: zoom || 15, duration: 500 });
            return;
        }
        var bounds = new window.goongjs.LngLatBounds(points[0], points[0]);
        points.forEach(function (p) { bounds.extend(p); });
        mapInstance.fitBounds(bounds, { padding: 48, maxZoom: 15, duration: 500 });
    }

    function updateFromBooking(booking) {
        if (!booking || booking.trip_status === 'completed' || booking.trip_status === 'cancelled') {
            showMapShell(false);
            setRadar(false);
            setLiveStatus('');
            clearCar();
            return;
        }

        var pickupLat = booking.pickup_lat;
        var pickupLng = booking.pickup_lng;
        var dropLat = booking.dropoff_lat;
        var dropLng = booking.dropoff_lng;
        var searching = !booking.has_driver;
        var driver = booking.driver || null;
        var stage = driver ? String(driver.stage || '') : '';
        var inTrip = stage === 'picked_up' || stage === 'running';
        var enRoute = stage === 'assigned' || stage === 'at_pickup';

        if (pickupLat == null || pickupLng == null) {
            showMapShell(false);
            return;
        }

        showMapShell(true);
        setRadar(searching, booking.wait_progress || null);

        var statusLine = booking.driver_status_line
            || (driver && driver.status_line)
            || (inTrip ? 'Đang trong chuyến' : '');
        setLiveStatus(searching ? '' : statusLine);

        var center = [Number(pickupLng), Number(pickupLat)];
        ensureMap(center).then(function (map) {
            map.resize();
            placePin('pickup', pickupLat, pickupLng, '#22c55e');
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
            } else {
                clearCar();
                if (searching) {
                    fitOrFly([center], 16);
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

    window.GuestTripLiveMap = {
        updateFromBooking: updateFromBooking,
    };
})();
