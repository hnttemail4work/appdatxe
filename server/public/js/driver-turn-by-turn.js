/**
 * Chỉ đường turn-by-turn TX → điểm đón (chỉ chạy khi đang ở giai đoạn "assigned").
 * - Fetch Direction (kèm steps) 1 lần khi có GPS; vẽ route qua DriverLiveMap.setRoute.
 * - Mỗi tick GPS (`addressmap:applied`): xác định step hiện tại + khoảng cách tới khúc rẽ + ETA,
 *   cập nhật banner #driver-pickup-proximity-sheet (icon hướng rẽ + dòng chỉ dẫn + dòng phụ).
 * - Lệch tuyến (> ngưỡng, nhiều tick liên tiếp) → fetch lại route (tối đa 1 lần / khoảng nghỉ).
 * - Không có steps / fetch lỗi → fallback: banner hiện khoảng cách + thời gian ước tính (haversine).
 */
(function () {
    var target = window.__driverPickupNavTarget;
    var destLat = target ? Number(target.dest_lat) : NaN;
    var destLng = target ? Number(target.dest_lng) : NaN;
    if (!Number.isFinite(destLat) || !Number.isFinite(destLng)) {
        return;
    }

    var directionUrl = window.__geocodeDirectionUrl;

    var bannerSection = document.getElementById('driver-pickup-proximity-sheet');
    var bannerIcon = document.getElementById('driver-nav-banner-icon');
    var bannerInstruction = document.getElementById('driver-nav-banner-instruction');
    var bannerMeta = document.getElementById('driver-pickup-proximity-line');
    if (!bannerSection) {
        return;
    }

    var ARRIVE_STEP_M = 30;
    var ARRIVE_DEST_M = 40;
    var OFF_ROUTE_M = 55;
    var OFF_ROUTE_TICKS = 3;
    var REFETCH_COOLDOWN_MS = 20000;
    var FALLBACK_SPEED_KMH = 26;

    var routeCoords = null; // [[lng, lat], …]
    var steps = [];
    var totalDistanceM = null;
    var totalDurationS = null;
    var stepIndex = 0;
    var offRouteStreak = 0;
    var lastFetchAt = 0;
    var fetchInFlight = false;
    var hasFetchedOnce = false;

    function haversineMeters(a, b) {
        var R = 6371000;
        var dLat = (b.lat - a.lat) * Math.PI / 180;
        var dLng = (b.lng - a.lng) * Math.PI / 180;
        var lat1 = a.lat * Math.PI / 180;
        var lat2 = b.lat * Math.PI / 180;
        var sinDLat = Math.sin(dLat / 2);
        var sinDLng = Math.sin(dLng / 2);
        var h = sinDLat * sinDLat + Math.cos(lat1) * Math.cos(lat2) * sinDLng * sinDLng;
        return 2 * R * Math.asin(Math.min(1, Math.sqrt(h)));
    }

    /** Khoảng cách điểm p tới đoạn a-b (mét) — xấp xỉ phẳng, đủ chính xác ở quy mô đường phố. */
    function distancePointToSegmentMeters(p, a, b) {
        var latRef = p.lat * Math.PI / 180;
        var mPerDegLat = 111320;
        var mPerDegLng = 111320 * Math.cos(latRef);

        var px = p.lng * mPerDegLng, py = p.lat * mPerDegLat;
        var ax = a.lng * mPerDegLng, ay = a.lat * mPerDegLat;
        var bx = b.lng * mPerDegLng, by = b.lat * mPerDegLat;

        var dx = bx - ax, dy = by - ay;
        var lenSq = dx * dx + dy * dy;
        var t = lenSq > 0 ? ((px - ax) * dx + (py - ay) * dy) / lenSq : 0;
        t = Math.max(0, Math.min(1, t));
        var cx = ax + t * dx, cy = ay + t * dy;
        var ddx = px - cx, ddy = py - cy;
        return Math.sqrt(ddx * ddx + ddy * ddy);
    }

    function distanceToRouteMeters(pos) {
        if (!routeCoords || routeCoords.length < 2) {
            return null;
        }
        var min = Infinity;
        for (var i = 0; i < routeCoords.length - 1; i++) {
            var a = { lat: routeCoords[i][1], lng: routeCoords[i][0] };
            var b = { lat: routeCoords[i + 1][1], lng: routeCoords[i + 1][0] };
            var d = distancePointToSegmentMeters(pos, a, b);
            if (d < min) {
                min = d;
            }
        }
        return min;
    }

    function formatDistance(meters) {
        if (meters == null || Number.isNaN(meters)) {
            return '';
        }
        if (meters < 1000) {
            return Math.max(10, Math.round(meters / 10) * 10) + ' m';
        }
        return (meters / 1000).toFixed(1).replace('.', ',') + ' km';
    }

    function formatMinutes(seconds) {
        if (seconds == null || Number.isNaN(seconds)) {
            return '';
        }
        return Math.max(1, Math.round(seconds / 60)) + ' phút';
    }

    // Một mũi tên xoay theo góc rẽ — gọn, không cần bộ icon riêng cho từng loại maneuver.
    var ARROW_SVG = '<svg viewBox="0 0 24 24" width="26" height="26" focusable="false">'
        + '<path fill="currentColor" d="M12 2.5l7 12h-4.4v7h-5.2v-7H5z"/>'
        + '</svg>';
    var FLAG_SVG = '<svg viewBox="0 0 24 24" width="26" height="26" focusable="false">'
        + '<path fill="currentColor" d="M6 3v18h1.6v-6.4h9.1l-2-3.1 2-3.1H7.6V3H6z"/>'
        + '</svg>';
    var ROUNDABOUT_SVG = '<svg viewBox="0 0 24 24" width="26" height="26" focusable="false">'
        + '<path fill="none" stroke="currentColor" stroke-width="2" d="M12 5a7 7 0 1 0 4.95 2.05"/>'
        + '<path fill="currentColor" d="M17.6 4.4l1 4-4-.9z"/>'
        + '<path fill="currentColor" d="M12 1.5l3 3.3-4.2.6z"/>'
        + '</svg>';

    function maneuverRotationDeg(maneuver) {
        var m = String(maneuver || '').toLowerCase();
        if (m.indexOf('sharp-left') >= 0 || m.indexOf('uturn-left') >= 0) {
            return -150;
        }
        if (m.indexOf('uturn') >= 0 && m.indexOf('right') < 0) {
            return -150;
        }
        if (m.indexOf('slight-left') >= 0) {
            return -30;
        }
        if (m.indexOf('left') >= 0) {
            return -70;
        }
        if (m.indexOf('sharp-right') >= 0 || m.indexOf('uturn-right') >= 0) {
            return 150;
        }
        if (m.indexOf('slight-right') >= 0) {
            return 30;
        }
        if (m.indexOf('right') >= 0) {
            return 70;
        }
        return 0;
    }

    function renderBanner(state) {
        bannerSection.classList.remove('d-none');
        bannerSection.hidden = false;
        bannerSection.classList.toggle('driver-nav-banner--arrived', !!state.arrived);

        if (bannerInstruction) {
            bannerInstruction.textContent = state.instruction || '';
        }
        if (bannerMeta) {
            bannerMeta.textContent = state.meta || '';
        }
        if (bannerIcon) {
            if (state.arrived) {
                bannerIcon.innerHTML = FLAG_SVG;
                bannerIcon.style.transform = '';
            } else if (String(state.maneuver || '').toLowerCase().indexOf('roundabout') >= 0) {
                bannerIcon.innerHTML = ROUNDABOUT_SVG;
                bannerIcon.style.transform = '';
            } else {
                bannerIcon.innerHTML = ARROW_SVG;
                bannerIcon.style.transform = 'rotate(' + maneuverRotationDeg(state.maneuver) + 'deg)';
            }
        }
    }

    function renderFallback(pos) {
        var distance = haversineMeters(pos, { lat: destLat, lng: destLng });
        if (distance <= ARRIVE_DEST_M) {
            renderBanner({ instruction: 'Đã tới điểm đón', meta: 'Bấm “Đã đến” để xác nhận.', arrived: true });
            return;
        }
        var etaSeconds = (distance / 1000) / FALLBACK_SPEED_KMH * 3600;
        renderBanner({
            instruction: 'Đang đến điểm đón',
            meta: 'Cách khách ' + formatDistance(distance) + ', khoảng ' + formatMinutes(etaSeconds) + '.',
        });
    }

    function updateStepIndex(pos) {
        while (stepIndex < steps.length - 1) {
            var distToCurrentEnd = haversineMeters(pos, steps[stepIndex].end);
            var distToNextEnd = haversineMeters(pos, steps[stepIndex + 1].end);
            if (distToCurrentEnd <= ARRIVE_STEP_M || distToNextEnd < distToCurrentEnd) {
                stepIndex++;
            } else {
                break;
            }
        }
    }

    function renderFromSteps(pos) {
        updateStepIndex(pos);
        var current = steps[stepIndex];
        var distanceToTurn = haversineMeters(pos, current.end);

        var isLastStep = stepIndex === steps.length - 1;
        if (isLastStep && distanceToTurn <= ARRIVE_DEST_M) {
            renderBanner({ instruction: 'Đã tới điểm đón', meta: 'Bấm “Đã đến” để xác nhận.', arrived: true });
            return;
        }

        var remainingM = distanceToTurn;
        for (var i = stepIndex + 1; i < steps.length; i++) {
            remainingM += steps[i].distance_m || 0;
        }

        var avgSpeedMps = totalDistanceM && totalDurationS
            ? totalDistanceM / totalDurationS
            : (FALLBACK_SPEED_KMH * 1000 / 3600);
        var etaSeconds = remainingM / Math.max(avgSpeedMps, 1.5);

        renderBanner({
            instruction: current.instruction + ' · ' + formatDistance(distanceToTurn),
            meta: 'Còn ' + formatDistance(remainingM) + ' · ' + formatMinutes(etaSeconds) + '.',
            maneuver: current.maneuver,
        });
    }

    function applyRoutePayload(data, driverPos) {
        var coords = data && Array.isArray(data.coordinates) ? data.coordinates : null;
        if (!coords || coords.length < 2) {
            return false;
        }
        routeCoords = coords;
        totalDistanceM = typeof data.distance_m === 'number' ? data.distance_m : null;
        totalDurationS = typeof data.duration_s === 'number' ? data.duration_s : null;
        steps = Array.isArray(data.steps) ? data.steps.filter(function (s) {
            return s && s.end && typeof s.end.lat === 'number' && typeof s.end.lng === 'number';
        }) : [];
        stepIndex = 0;
        offRouteStreak = 0;

        if (window.DriverLiveMap && window.DriverLiveMap.setRoute) {
            window.DriverLiveMap.setRoute(routeCoords);
        }

        if (steps.length > 0) {
            renderFromSteps(driverPos);
        } else {
            renderFallback(driverPos);
        }
        return true;
    }

    function fetchRoute(driverPos, force) {
        if (!directionUrl || fetchInFlight) {
            return;
        }
        var now = Date.now();
        if (!force && hasFetchedOnce && (now - lastFetchAt) < REFETCH_COOLDOWN_MS) {
            return;
        }
        fetchInFlight = true;
        lastFetchAt = now;

        var params = new URLSearchParams({
            origin_lat: String(driverPos.lat),
            origin_lng: String(driverPos.lng),
            dest_lat: String(destLat),
            dest_lng: String(destLng),
        });

        fetch(directionUrl + '?' + params.toString(), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                hasFetchedOnce = true;
                if (!data || !applyRoutePayload(data, driverPos)) {
                    renderFallback(driverPos);
                }
            })
            .catch(function () {
                hasFetchedOnce = true;
                renderFallback(driverPos);
            })
            .finally(function () {
                fetchInFlight = false;
            });
    }

    function handlePositionTick(pos) {
        if (!hasFetchedOnce) {
            fetchRoute(pos, true);
            renderFallback(pos);
            return;
        }

        if (steps.length > 0) {
            renderFromSteps(pos);
        } else {
            renderFallback(pos);
        }

        var offRouteM = distanceToRouteMeters(pos);
        if (offRouteM != null && offRouteM > OFF_ROUTE_M) {
            offRouteStreak++;
            if (offRouteStreak >= OFF_ROUTE_TICKS) {
                offRouteStreak = 0;
                fetchRoute(pos, false);
            }
        } else {
            offRouteStreak = 0;
        }
    }

    function initialDriverCoords() {
        var fromGps = window.DriverLocationGps && window.DriverLocationGps.getLastKnownCoords
            ? window.DriverLocationGps.getLastKnownCoords()
            : null;
        if (fromGps) {
            return fromGps;
        }
        var latInput = document.getElementById('driver-location-lat');
        var lngInput = document.getElementById('driver-location-lng');
        if (latInput && lngInput && latInput.value && lngInput.value) {
            var lat = parseFloat(latInput.value);
            var lng = parseFloat(lngInput.value);
            if (!Number.isNaN(lat) && !Number.isNaN(lng)) {
                return { lat: lat, lng: lng };
            }
        }
        return null;
    }

    var boot = initialDriverCoords();
    if (boot) {
        handlePositionTick(boot);
    }

    document.addEventListener('addressmap:applied', function (event) {
        var detail = event.detail || {};
        if (typeof detail.lat !== 'number' || typeof detail.lng !== 'number') {
            return;
        }
        handlePositionTick({ lat: detail.lat, lng: detail.lng });
    });
})();
