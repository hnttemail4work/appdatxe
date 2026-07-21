/**
 * Sheet chọn điểm đi / điểm đến (kiểu Grab).
 * Đủ 2 điểm + coords → đóng sheet, bắn booking:route-ready.
 */
(function () {
    var RECENT_KEY = 'appdatxe:recentAddresses';
    var RECENT_MAX = 10;

    var sheet = document.getElementById('booking-address-sheet');
    var card = document.getElementById('booking-route-card');
    if (!sheet || !card) {
        return;
    }

    var pickupInput = document.getElementById('addr-sheet-pickup');
    var dropoffInput = document.getElementById('addr-sheet-dropoff');
    var suggestEl = document.getElementById('booking-addr-suggest');
    var searchUrl = window.__geocodeSearchUrl || '';
    var reverseUrl = window.__geocodeReverseUrl || '';

    var activeField = 'dropoff';
    var searchTimer = null;
    var searchAbort = null;
    var advancing = false;
    var gpsOrigin = null;

    var state = {
        pickup: { address: '', lat: null, lng: null },
        dropoff: { address: '', lat: null, lng: null },
    };

    function $(id) {
        return document.getElementById(id);
    }

    function loadRecents() {
        try {
            var raw = window.localStorage.getItem(RECENT_KEY);
            var list = raw ? JSON.parse(raw) : [];
            return Array.isArray(list) ? list.slice(0, RECENT_MAX) : [];
        } catch (e) {
            return [];
        }
    }

    function saveRecent(address, lat, lng, placeId) {
        var text = String(address || '').trim();
        if (!text || lat == null || lng == null) {
            return;
        }
        try {
            var pid = placeId ? String(placeId).trim() : '';
            var list = loadRecents().filter(function (item) {
                var sameAddr = String(item.address || '').trim().toLowerCase() === text.toLowerCase();
                var samePlace = pid && item.place_id && String(item.place_id) === pid;
                return !sameAddr && !samePlace;
            });
            list.unshift({
                address: text,
                lat: Number(lat),
                lng: Number(lng),
                place_id: pid || undefined,
            });
            window.localStorage.setItem(RECENT_KEY, JSON.stringify(list.slice(0, RECENT_MAX)));
            if (pid && window.GeocodeResolve && window.GeocodeResolve.putCache) {
                window.GeocodeResolve.putCache(pid, { lat: lat, lng: lng, address: text });
            }
        } catch (e) {
        }
    }

    function coordsFromMemory(row) {
        var placeId = row && row.place_id ? String(row.place_id).trim() : '';
        if (placeId && window.GeocodeResolve && window.GeocodeResolve.getCached) {
            var cached = window.GeocodeResolve.getCached(placeId);
            if (cached) {
                return cached;
            }
        }
        var addr = String((row && (row.address || row.title)) || '').trim().toLowerCase();
        if (!addr) {
            return null;
        }
        var hit = loadRecents().find(function (r) {
            return String(r.address || '').trim().toLowerCase() === addr;
        });
        if (!hit || hit.lat == null || hit.lng == null) {
            return null;
        }
        return { lat: Number(hit.lat), lng: Number(hit.lng), lon: Number(hit.lng) };
    }

    function syncHiddenForm() {
        var map = {
            pickup: {
                detail: 'modal-pickup-detail',
                lat: 'modal-pickup-lat',
                lng: 'modal-pickup-lng',
            },
            dropoff: {
                detail: 'modal-dropoff-detail',
                lat: 'modal-dropoff-lat',
                lng: 'modal-dropoff-lng',
            },
        };
        Object.keys(map).forEach(function (key) {
            var s = state[key];
            var ids = map[key];
            var detail = $(ids.detail);
            var lat = $(ids.lat);
            var lng = $(ids.lng);
            if (detail) {
                detail.value = s.address || '';
            }
            if (lat) {
                lat.value = s.lat != null ? String(s.lat) : '';
            }
            if (lng) {
                lng.value = s.lng != null ? String(s.lng) : '';
                lng.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });

        var homeLabel = card.querySelector('[data-home-dest-label]');
        if (homeLabel) {
            homeLabel.textContent = state.dropoff.address || 'Bạn muốn đi đâu?';
            homeLabel.classList.toggle('has-value', !!state.dropoff.address);
        }

        // Giữ điểm đã chọn khi reload (chỉ khi chưa vào flow — tránh ghi đè resume mid-flow).
        if (!bookingFlowIsOpen()
            && window.BookingRouteDraft
            && typeof window.BookingRouteDraft.save === 'function') {
            window.BookingRouteDraft.save({ resume: false });
        }
    }

    function hasCoords(point) {
        return !!(point
            && point.address
            && typeof point.lat === 'number' && !isNaN(point.lat)
            && typeof point.lng === 'number' && !isNaN(point.lng));
    }

    function bothReady() {
        return hasCoords(state.pickup) && hasCoords(state.dropoff);
    }

    function bookingFlowIsOpen() {
        var flow = document.getElementById('booking-flow');
        return !!(flow && !flow.classList.contains('d-none') && !flow.hasAttribute('hidden'));
    }

    function haversineMeters(lat1, lng1, lat2, lng2) {
        var toRad = Math.PI / 180;
        var dLat = (lat2 - lat1) * toRad;
        var dLng = (lng2 - lng1) * toRad;
        var a = Math.sin(dLat / 2) * Math.sin(dLat / 2)
            + Math.cos(lat1 * toRad) * Math.cos(lat2 * toRad)
            * Math.sin(dLng / 2) * Math.sin(dLng / 2);
        return 6371000 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    var MIN_TRIP_METERS = 200;

    function tripDistanceMeters() {
        if (!bothReady()) {
            return 0;
        }
        return haversineMeters(
            state.pickup.lat,
            state.pickup.lng,
            state.dropoff.lat,
            state.dropoff.lng,
        );
    }

    function wouldBeTooClose(field, lat, lng) {
        var other = field === 'dropoff' ? state.pickup : state.dropoff;
        if (other.lat == null || other.lng == null || lat == null || lng == null) {
            return false;
        }
        var latN = Number(lat);
        var lngN = Number(lng);
        if (isNaN(latN) || isNaN(lngN)) {
            return false;
        }
        var meters = haversineMeters(
            Number(other.lat),
            Number(other.lng),
            latN,
            lngN,
        );
        // Gồm cả cùng vị trí (0m).
        return typeof meters === 'number' && isFinite(meters) && meters < MIN_TRIP_METERS;
    }

    function pointsAreTooClose() {
        if (!bothReady()) {
            return false;
        }
        var meters = tripDistanceMeters();
        return typeof meters === 'number' && isFinite(meters) && meters < MIN_TRIP_METERS;
    }

    /** AppDialog không mở được khi modal bản đồ Bootstrap đang đóng — chờ hidden rồi mới hiện. */
    function runAfterMapPickerClosed(fn) {
        var mapModal = document.getElementById('addressMapPickerModal');
        if (mapModal && mapModal.classList.contains('show')) {
            var done = false;
            var finish = function () {
                if (done) {
                    return;
                }
                done = true;
                mapModal.removeEventListener('hidden.bs.modal', finish);
                window.setTimeout(fn, 40);
            };
            mapModal.addEventListener('hidden.bs.modal', finish);
            window.setTimeout(finish, 700);
            return;
        }
        window.setTimeout(fn, 0);
    }

    function alertPointsTooClose(options) {
        options = options || {};
        var message = 'Điểm đi và điểm đến quá gần (dưới 200m). Vui lòng chọn lại điểm đến.';
        var title = 'Không thể đặt chuyến';
        var after = function () {
            if (options.clearDropoff) {
                clearField('dropoff');
            }
            focusField('dropoff');
            if (dropoffInput) {
                try {
                    dropoffInput.focus();
                } catch (e) { /* ignore */ }
            }
        };
        runAfterMapPickerClosed(function () {
            if (window.AppDialog && typeof window.AppDialog.alert === 'function') {
                window.AppDialog.alert(message, {
                    title: title,
                    okText: 'Chọn lại',
                    variant: 'warning',
                }).then(after);
                return;
            }
            window.alert(message);
            after();
        });
    }

    function tryAdvance() {
        if (!bothReady() || advancing) {
            return;
        }
        // Đã sang xác nhận điểm đón / chọn xe — đừng bắn lại route-ready (GPS trễ).
        if (bookingFlowIsOpen()) {
            return;
        }

        if (pointsAreTooClose()) {
            // Safety net — trường hợp chính đã chặn ngay lúc chọn điểm đến.
            alertPointsTooClose({ clearDropoff: true });
            return;
        }

        advancing = true;
        syncHiddenForm();
        // Bắn event trước (listener mở booking-flow sync), rồi mới đóng sheet —
        // tránh flash về home "chọn điểm đến" nửa giây.
        document.dispatchEvent(new CustomEvent('booking:route-ready', {
            bubbles: true,
            detail: {
                pickup: Object.assign({}, state.pickup),
                dropoff: Object.assign({}, state.dropoff),
            },
        }));
        closeSheet({ skipResume: true });
        window.setTimeout(function () {
            advancing = false;
        }, 400);
    }

    /** Đóng sheet về trang chủ — không tự mở lại xác nhận điểm đón / chọn xe. */
    function dismissSheetToHome() {
        closeSheet({ skipResume: true });
        syncHiddenForm();
        if (window.BookingRouteDraft && typeof window.BookingRouteDraft.save === 'function') {
            window.BookingRouteDraft.save({
                resume: false,
                step: null,
                confirmed_pickup: null,
            });
        }
    }

    function setFieldValue(field, address, lat, lng, options) {
        options = options || {};
        var latNum = lat != null && lat !== '' ? Number(lat) : NaN;
        var lngNum = lng != null && lng !== '' ? Number(lng) : NaN;
        state[field] = {
            address: String(address || '').trim(),
            lat: !isNaN(latNum) ? latNum : null,
            lng: !isNaN(lngNum) ? lngNum : null,
        };
        var input = field === 'pickup' ? pickupInput : dropoffInput;
        if (input) {
            input.value = state[field].address;
        }
        updateClearButtons();
        syncHiddenForm();
        if (state[field].address && state[field].lat != null) {
            saveRecent(
                state[field].address,
                state[field].lat,
                state[field].lng,
                options.place_id || state[field].place_id || '',
            );
        }
        if (!options.skipAdvance) {
            afterFieldApplied(field, options);
        }
    }

    function clearField(field) {
        state[field] = { address: '', lat: null, lng: null };
        var input = field === 'pickup' ? pickupInput : dropoffInput;
        if (input) {
            input.value = '';
            input.focus();
        }
        updateClearButtons();
        syncHiddenForm();
        renderIdleSuggest();
    }

    function updateClearButtons() {
        sheet.querySelectorAll('[data-addr-clear]').forEach(function (btn) {
            var field = btn.getAttribute('data-addr-clear');
            var has = !!(state[field] && state[field].address);
            btn.classList.toggle('d-none', !has);
        });
    }

    function escapeHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function haversineKm(lat1, lng1, lat2, lng2) {
        var toRad = function (d) { return d * Math.PI / 180; };
        var R = 6371;
        var dLat = toRad(lat2 - lat1);
        var dLng = toRad(lng2 - lng1);
        var a = Math.sin(dLat / 2) * Math.sin(dLat / 2)
            + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2))
            * Math.sin(dLng / 2) * Math.sin(dLng / 2);
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    function suggestOrigin() {
        if (activeField === 'dropoff' && state.pickup.lat != null && state.pickup.lng != null) {
            return { lat: Number(state.pickup.lat), lng: Number(state.pickup.lng) };
        }
        if (activeField === 'pickup' && state.dropoff.lat != null && state.dropoff.lng != null) {
            return { lat: Number(state.dropoff.lat), lng: Number(state.dropoff.lng) };
        }
        if (state.pickup.lat != null && state.pickup.lng != null) {
            return { lat: Number(state.pickup.lat), lng: Number(state.pickup.lng) };
        }
        if (gpsOrigin && gpsOrigin.lat != null && gpsOrigin.lng != null) {
            return { lat: Number(gpsOrigin.lat), lng: Number(gpsOrigin.lng) };
        }
        return null;
    }

    function formatSuggestKm(km) {
        if (!(km >= 0) || isNaN(km)) {
            return '';
        }
        if (km < 0.1) {
            return 'Gần đây';
        }
        if (km < 1) {
            return Math.round(km * 1000) + ' m';
        }
        return (Math.round(km * 10) / 10).toFixed(1).replace('.', ',') + ' km';
    }

    function distanceLabelForItem(item) {
        var origin = suggestOrigin();
        if (!origin) {
            return '';
        }
        var lat = item.lat != null ? Number(item.lat) : NaN;
        var lng = item.lng != null ? Number(item.lng)
            : (item.lon != null ? Number(item.lon) : NaN);
        if (isNaN(lat) || isNaN(lng)) {
            return '';
        }
        return formatSuggestKm(haversineKm(origin.lat, origin.lng, lat, lng));
    }

    function renderSuggestItems(items) {
        if (!suggestEl) {
            return;
        }
        suggestEl.innerHTML = '';
        if (!items.length) {
            suggestEl.innerHTML = '<div class="booking-addr-suggest__empty">Không có gợi ý phù hợp</div>';
            return;
        }
        items.forEach(function (item, index) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'booking-addr-suggest__item';
            btn.setAttribute('role', 'option');
            btn.setAttribute('data-suggest-index', String(index));
            var kmLabel = distanceLabelForItem(item);
            btn.innerHTML = '<span class="booking-addr-suggest__icon" aria-hidden="true">'
                + (item.icon || '📍')
                + '</span><span class="booking-addr-suggest__copy">'
                + '<span class="booking-addr-suggest__title">' + escapeHtml(item.title) + '</span>'
                + (item.subtitle
                    ? '<span class="booking-addr-suggest__sub">' + escapeHtml(item.subtitle) + '</span>'
                    : '')
                + '</span>'
                + '<span class="booking-addr-suggest__km' + (kmLabel ? '' : ' d-none') + '">'
                + escapeHtml(kmLabel || '')
                + '</span>';
            btn._suggestItem = item;
            suggestEl.appendChild(btn);
        });
    }

    function patchSuggestKm(btn, item) {
        if (!btn) {
            return;
        }
        var kmEl = btn.querySelector('.booking-addr-suggest__km');
        var kmLabel = distanceLabelForItem(item);
        if (!kmEl) {
            return;
        }
        if (kmLabel) {
            kmEl.textContent = kmLabel;
            kmEl.classList.remove('d-none');
        } else {
            kmEl.textContent = '';
            kmEl.classList.add('d-none');
        }
    }

    function enrichSuggestCoords(items) {
        if (!items || !items.length || !window.GeocodeResolve || !window.GeocodeResolve.resolvePlace) {
            return;
        }
        if (!suggestOrigin()) {
            return;
        }
        items.forEach(function (item, index) {
            var hasLat = item.lat != null && item.lat !== '';
            var hasLng = (item.lng != null && item.lng !== '') || (item.lon != null && item.lon !== '');
            if ((hasLat && hasLng) || !item.place_id) {
                return;
            }
            window.GeocodeResolve.resolvePlace(item).then(function (resolved) {
                if (!resolved) {
                    return;
                }
                var lat = resolved.lat != null ? resolved.lat : item.lat;
                var lng = resolved.lng != null ? resolved.lng
                    : (resolved.lon != null ? resolved.lon : (item.lng != null ? item.lng : item.lon));
                if (lat == null || lng == null) {
                    return;
                }
                item.lat = Number(lat);
                item.lng = Number(lng);
                item.lon = Number(lng);
                var btn = suggestEl
                    ? suggestEl.querySelector('[data-suggest-index="' + index + '"]')
                    : null;
                if (btn) {
                    btn._suggestItem = item;
                    patchSuggestKm(btn, item);
                }
            });
        });
    }

    function showSuggestPending(message) {
        if (!suggestEl) {
            return;
        }
        suggestEl.innerHTML = '<div class="booking-addr-suggest__empty">'
            + escapeHtml(message || 'Đang xử lý…')
            + '</div>';
    }

    /** Điểm đón chưa có GPS — lấy êm, không nhảy sang list "Vị trí hiện tại". */
    function ensurePickupThenAdvance() {
        if (bothReady()) {
            tryAdvance();
            return;
        }
        showSuggestPending('Đang lấy điểm đón…');
        locateCurrent(true);
    }

    /**
     * Sau khi chọn 1 điểm:
     * - Điểm đến quá gần điểm đi → popup ngay, không sang bước sau.
     * - Đủ 2 điểm hợp lệ → sang đặt chuyến.
     */
    function afterFieldApplied(field, options) {
        options = options || {};
        if (bothReady()) {
            if (pointsAreTooClose()) {
                alertPointsTooClose({ clearDropoff: true });
                return;
            }
            if (field === 'dropoff' || options.advanceIfReady) {
                tryAdvance();
                return;
            }
            if (field === 'pickup' && options.keepSheetOpen) {
                tryAdvance();
                return;
            }
            tryAdvance();
            return;
        }
        // Chọn điểm đến rồi mà điểm đón chưa sẵn → chờ GPS, đừng focusField(pickup).
        if (field === 'dropoff' && (state.pickup.lat == null || state.pickup.lng == null)) {
            ensurePickupThenAdvance();
            return;
        }
        // Chọn điểm đón mà chưa có điểm đến → sang ô đến (bình thường).
        if (field === 'pickup' && !(state.dropoff.address && state.dropoff.lat != null)) {
            focusField('dropoff');
            return;
        }
        // Thiếu tọa độ ô vừa chọn — giữ field hiện tại, không đổi list sang field kia.
        showSuggestPending('Đang lấy tọa độ…');
    }

    function applyFieldAndContinue(field, address, lat, lng, options) {
        options = options || {};
        var wasBothReady = bothReady();
        // Luôn ghi nhận lên ô sheet trước — tránh xác nhận map xong mà UI trống.
        setFieldValue(field, address, lat, lng, Object.assign({}, options, { skipAdvance: true }));

        if (field === 'dropoff' && pointsAreTooClose()) {
            alertPointsTooClose({ clearDropoff: true });
            return;
        }
        if (field === 'pickup' && bothReady() && pointsAreTooClose()) {
            alertPointsTooClose({ clearDropoff: true });
            return;
        }
        afterFieldApplied(field, Object.assign({}, options, {
            keepSheetOpen: wasBothReady && field === 'pickup',
        }));
    }

    function applySuggest(item) {
        if (!item) {
            return;
        }
        var field = activeField;

        var itemLat = item.lat != null ? item.lat : null;
        var itemLng = item.lng != null ? item.lng : (item.lon != null ? item.lon : null);

        var finalize = function (resolved) {
            if (!resolved && !item) {
                return;
            }
            resolved = resolved || item;
            var lat = resolved.lat != null ? resolved.lat : item.lat;
            var lng = resolved.lon != null ? resolved.lon
                : (resolved.lng != null ? resolved.lng : (item.lng != null ? item.lng : item.lon));
            var address = String(
                resolved.address || item.title || item.address || ''
            ).trim();
            if (!address) {
                return;
            }
            applyFieldAndContinue(field, address, lat, lng, {
                place_id: item.place_id || resolved.place_id || '',
            });
        };

        if (item.kind === 'current') {
            locateCurrent(true);
            return;
        }

        // Gợi ý điểm đến quá gần: vẫn đi qua applyFieldAndContinue để hiện địa chỉ + popup.
        if (field === 'dropoff' && itemLat != null && itemLng != null
            && wouldBeTooClose('dropoff', itemLat, itemLng)) {
            applyFieldAndContinue(field, item.address || item.title || '', itemLat, itemLng, {
                place_id: item.place_id || '',
            });
            return;
        }

        if (window.GeocodeResolve && window.GeocodeResolve.resolvePlace && item.place_id) {
            var wasBothReady = bothReady();
            if (field === 'dropoff' && itemLat != null && itemLng != null
                && wouldBeTooClose('dropoff', itemLat, itemLng)) {
                applyFieldAndContinue(field, item.address || item.title || '', itemLat, itemLng, {
                    place_id: item.place_id || '',
                });
                return;
            }
            setFieldValue(field, item.address || item.title || '', item.lat, item.lng != null ? item.lng : item.lon, {
                skipAdvance: true,
                place_id: item.place_id,
            });
            if (bothReady()) {
                afterFieldApplied(field, {
                    keepSheetOpen: wasBothReady && field === 'pickup',
                });
                if (field === 'dropoff' || (field === 'pickup' && !wasBothReady)) {
                    // Resolve nền — không đụng UI nữa khi đã sang bước đặt.
                    window.GeocodeResolve.resolvePlace(item).then(function (resolved) {
                        if (!resolved || advancing || sheet.hidden) {
                            return;
                        }
                        var lat = resolved.lat != null ? resolved.lat : state[field].lat;
                        var lng = resolved.lon != null ? resolved.lon
                            : (resolved.lng != null ? resolved.lng : state[field].lng);
                        var address = String(resolved.address || state[field].address || '').trim();
                        if (!address || lat == null || lng == null) {
                            return;
                        }
                        if (field === 'dropoff' && wouldBeTooClose('dropoff', lat, lng)) {
                            setFieldValue(field, address, lat, lng, {
                                skipAdvance: true,
                                place_id: item.place_id || resolved.place_id || '',
                            });
                            alertPointsTooClose({ clearDropoff: true });
                            return;
                        }
                        setFieldValue(field, address, lat, lng, {
                            skipAdvance: true,
                            place_id: item.place_id || resolved.place_id || '',
                        });
                    });
                }
                return;
            }
            showSuggestPending('Đang lấy tọa độ…');
            window.GeocodeResolve.resolvePlace(item).then(finalize);
            return;
        }

        if (item.lat != null && (item.lng != null || item.lon != null)) {
            finalize({
                address: item.address || item.title,
                lat: item.lat,
                lon: item.lng != null ? item.lng : item.lon,
                place_id: item.place_id,
            });
            return;
        }

        finalize(item);
    }

    if (suggestEl) {
        // mousedown: tránh blur input làm mất click trên mobile
        suggestEl.addEventListener('mousedown', function (e) {
            var btn = e.target.closest('.booking-addr-suggest__item');
            if (btn) {
                e.preventDefault();
            }
        });
        suggestEl.addEventListener('click', function (e) {
            var btn = e.target.closest('.booking-addr-suggest__item');
            if (!btn || !btn._suggestItem) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            applySuggest(btn._suggestItem);
        });
    }

    function recentSubtitle(address) {
        var parts = String(address || '').split(',').map(function (p) { return p.trim(); }).filter(Boolean);
        if (parts.length >= 2) {
            return parts.slice(1, 3).join(', ');
        }
        return 'Đã chọn gần đây';
    }

    function renderIdleSuggest() {
        var items = [];
        if (activeField === 'pickup') {
            items.push({
                kind: 'current',
                icon: '◎',
                title: 'Vị trí hiện tại',
                subtitle: 'Dùng GPS của bạn',
            });
        }
        loadRecents().forEach(function (r) {
            var full = String(r.address || '').trim();
            var parts = full.split(',').map(function (p) { return p.trim(); }).filter(Boolean);
            items.push({
                kind: 'recent',
                icon: '⏱',
                title: parts[0] || full,
                address: full,
                lat: r.lat,
                lng: r.lng,
                place_id: r.place_id || '',
                subtitle: recentSubtitle(full),
            });
        });
        renderSuggestItems(items.slice(0, activeField === 'pickup' ? 6 : 5));
    }

    function renderEmptySearch() {
        if (!suggestEl) {
            return;
        }
        suggestEl.innerHTML = '<div class="booking-addr-suggest__empty">Không có gợi ý phù hợp</div>';
    }

    function runSearch(query) {
        var q = window.AddressQueryNormalize && window.AddressQueryNormalize.normalize
            ? window.AddressQueryNormalize.normalize(query)
            : String(query || '').trim();
        if (!searchUrl || q.length < 2) {
            renderIdleSuggest();
            return;
        }
        if (searchTimer) {
            window.clearTimeout(searchTimer);
        }
        searchTimer = window.setTimeout(function () {
            if (searchAbort) {
                searchAbort.abort();
            }
            searchAbort = new AbortController();
            if (window.GeocodeSearchUi && window.GeocodeSearchUi.setLoading) {
                window.GeocodeSearchUi.setLoading(suggestEl, 'Đang tìm…');
            } else {
                suggestEl.innerHTML = '<div class="booking-addr-suggest__empty">Đang tìm…</div>';
            }
            fetch(searchUrl + '?q=' + encodeURIComponent(q) + '&province=' + encodeURIComponent('TP.HCM'), {
                signal: searchAbort.signal,
                headers: { Accept: 'application/json' },
            })
                .then(function (r) { return r.ok ? r.json() : { results: [] }; })
                .then(function (data) {
                    var results = (data && data.results) || [];
                    var items = results.slice(0, 5).map(function (row) {
                        var lat = row.lat != null ? Number(row.lat) : null;
                        var lon = row.lon != null ? Number(row.lon)
                            : (row.lng != null ? Number(row.lng) : null);
                        if (lat == null || lon == null) {
                            var mem = coordsFromMemory(row);
                            if (mem) {
                                lat = mem.lat;
                                lon = mem.lng != null ? mem.lng : mem.lon;
                            }
                        }
                        return {
                            kind: 'search',
                            icon: '📍',
                            title: row.title || row.address || row.name || '',
                            subtitle: row.subtitle || row.kind_label || row.province || '',
                            address: row.address || row.title || '',
                            place_id: row.place_id,
                            lat: lat,
                            lon: lon,
                            lng: lon,
                        };
                    });
                    if (!items.length) {
                        renderEmptySearch();
                        return;
                    }
                    renderSuggestItems(items);
                    enrichSuggestCoords(items);
                })
                .catch(function (err) {
                    if (err && err.name === 'AbortError') {
                        return;
                    }
                    renderEmptySearch();
                });
        }, 320);
    }

    function locateCurrent(forceApply) {
        if (!navigator.geolocation) {
            return;
        }
        function applyPickup(address, lat, lng, province) {
            // GPS điểm đi: không tự nhảy màn hình; chỉ advance khi force (sau khi đã chọn điểm đến).
            setFieldValue('pickup', address, lat, lng, { skipAdvance: true });
            if (province) {
                var pickupAddr = $('modal-pickup-address');
                if (pickupAddr) {
                    pickupAddr.value = province;
                }
            }
            if (forceApply) {
                afterFieldApplied('pickup', { advanceIfReady: true });
            }
        }
        navigator.geolocation.getCurrentPosition(function (pos) {
            var lat = pos.coords.latitude;
            var lng = pos.coords.longitude;
            gpsOrigin = { lat: lat, lng: lng };
            if (!reverseUrl) {
                applyPickup('Vị trí hiện tại', lat, lng, null);
                return;
            }
            fetch(reverseUrl + '?lat=' + encodeURIComponent(lat) + '&lon=' + encodeURIComponent(lng), {
                headers: { Accept: 'application/json' },
            })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (data) {
                    var address = data && data.address ? String(data.address).trim() : 'Vị trí hiện tại';
                    applyPickup(address, lat, lng, data && data.province ? data.province : null);
                })
                .catch(function () {
                    applyPickup('Vị trí hiện tại', lat, lng, null);
                });
        }, function () {}, { enableHighAccuracy: true, timeout: 12000, maximumAge: 30000 });
    }

    function focusField(field) {
        activeField = field;
        sheet.querySelectorAll('[data-addr-field]').forEach(function (el) {
            el.classList.toggle('is-focused', el.getAttribute('data-addr-field') === field);
        });
        var input = field === 'pickup' ? pickupInput : dropoffInput;
        if (input) {
            window.setTimeout(function () {
                input.focus();
                var q = input.value.trim();
                if (q.length >= 2) {
                    runSearch(q);
                } else {
                    renderIdleSuggest();
                }
            }, 40);
        }
    }

    function hydrateFromForm() {
        var pDetail = $('modal-pickup-detail');
        var dDetail = $('modal-dropoff-detail');
        var pLat = $('modal-pickup-lat');
        var pLng = $('modal-pickup-lng');
        var dLat = $('modal-dropoff-lat');
        var dLng = $('modal-dropoff-lng');
        if (pDetail && pDetail.value) {
            state.pickup = {
                address: pDetail.value.trim(),
                lat: pLat && pLat.value ? Number(pLat.value) : null,
                lng: pLng && pLng.value ? Number(pLng.value) : null,
            };
            pickupInput.value = state.pickup.address;
        }
        if (dDetail && dDetail.value) {
            state.dropoff = {
                address: dDetail.value.trim(),
                lat: dLat && dLat.value ? Number(dLat.value) : null,
                lng: dLng && dLng.value ? Number(dLng.value) : null,
            };
            dropoffInput.value = state.dropoff.address;
        }
        updateClearButtons();
    }

    function openSheet(focus) {
        hydrateFromForm();
        sheet.hidden = false;
        sheet.setAttribute('aria-hidden', 'false');
        document.body.classList.add('booking-addr-sheet-open');
        if (!state.pickup.address || state.pickup.lat == null) {
            locateCurrent(false);
        }
        focusField(focus || (state.dropoff.address ? 'pickup' : 'dropoff'));
    }

    function closeSheet(options) {
        options = options || {};
        var wasOpen = !sheet.hidden;
        sheet.hidden = true;
        sheet.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('booking-addr-sheet-open');
        if (searchAbort) {
            searchAbort.abort();
        }
        // Đủ 2 điểm rồi đóng sheet (sau khi đổi đón/trả / bấm back) → mở lại flow đặt chuyến.
        if (!options.skipResume && wasOpen && bothReady() && !bookingFlowIsOpen() && !advancing) {
            tryAdvance();
        }
    }

    // Open triggers
    card.querySelectorAll('[data-open-address-sheet-main]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            openSheet('dropoff');
        });
    });
    var bar = card.querySelector('[data-open-address-sheet]');
    if (bar) {
        bar.addEventListener('click', function (e) {
            if (e.target.closest('.grab-home-bar__map') || e.target.closest('.grab-home-bar__schedule')) {
                return;
            }
            if (e.target.closest('[data-open-address-sheet-main]')) {
                return;
            }
            openSheet('dropoff');
        });
    }

    sheet.querySelectorAll('[data-addr-sheet-close]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            dismissSheetToHome();
        });
    });

    function openMapForActiveField() {
        var trigger = document.getElementById('addr-sheet-map-trigger');
        if (!trigger) {
            return;
        }
        var field = activeField || 'dropoff';
        if (field === 'pickup') {
            trigger.setAttribute('data-address-map-for', 'modal-pickup-detail');
            trigger.setAttribute('data-address-map-lat', 'modal-pickup-lat');
            trigger.setAttribute('data-address-map-lng', 'modal-pickup-lng');
            trigger.setAttribute('data-address-map-label', 'Chọn điểm đón');
        } else {
            trigger.setAttribute('data-address-map-for', 'modal-dropoff-detail');
            trigger.setAttribute('data-address-map-lat', 'modal-dropoff-lat');
            trigger.setAttribute('data-address-map-lng', 'modal-dropoff-lng');
            trigger.setAttribute('data-address-map-label', 'Chọn điểm trả');
        }
        trigger.setAttribute('data-address-map-default-province', 'TP.HCM');
        // Chỉ GPS tự động khi chọn điểm đón — điểm đến để user tìm/ ghim vàng.
        trigger.setAttribute('data-address-map-locate', field === 'pickup' ? '1' : '0');
        syncHiddenForm();
        trigger.click();
    }

    var sheetMapBtn = sheet.querySelector('[data-addr-sheet-map]');
    if (sheetMapBtn) {
        sheetMapBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            openMapForActiveField();
        });
    }

    sheet.querySelectorAll('[data-addr-clear]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            clearField(btn.getAttribute('data-addr-clear'));
        });
    });

    [pickupInput, dropoffInput].forEach(function (input) {
        if (!input) {
            return;
        }
        input.addEventListener('focus', function () {
            focusField(input.id === 'addr-sheet-pickup' ? 'pickup' : 'dropoff');
        });
        input.addEventListener('input', function (e) {
            if (e && e.isComposing) {
                return;
            }
            var field = input.id === 'addr-sheet-pickup' ? 'pickup' : 'dropoff';
            var q = window.AddressQueryNormalize && window.AddressQueryNormalize.applyToInput
                ? window.AddressQueryNormalize.applyToInput(input)
                : input.value.trim();
            // Gõ lại → bỏ khóa tọa độ cũ
            state[field].lat = null;
            state[field].lng = null;
            state[field].address = q;
            updateClearButtons();
            if (q.length >= 2) {
                runSearch(q);
            } else {
                renderIdleSuggest();
            }
        });
        input.addEventListener('compositionend', function () {
            input.dispatchEvent(new Event('input', { bubbles: true }));
        });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && !sheet.hidden) {
            dismissSheetToHome();
        }
    });

    document.addEventListener('addressmap:applied', function (e) {
        var detail = e.detail || {};
        var target = detail.targetInputId || '';
        if (bookingFlowIsOpen()) {
            return;
        }
        if (target === 'modal-pickup-detail' && detail.address) {
            applyFieldAndContinue('pickup', detail.address, detail.lat, detail.lng);
            return;
        }
        if (target === 'modal-dropoff-detail' && detail.address) {
            applyFieldAndContinue('dropoff', detail.address, detail.lat, detail.lng);
        }
    });

    // Prefill GPS quietly for home readiness
    hydrateFromForm();
    if (!state.pickup.address) {
        locateCurrent(false);
    }

    var swapBtn = sheet.querySelector('[data-addr-swap]');
    if (swapBtn) {
        swapBtn.addEventListener('click', function () {
            var tmp = state.pickup;
            state.pickup = state.dropoff;
            state.dropoff = tmp;
            if (pickupInput) pickupInput.value = state.pickup.address || '';
            if (dropoffInput) dropoffInput.value = state.dropoff.address || '';
            // Đảo cả tỉnh/thành (hidden) — syncHiddenForm chỉ ghi detail/lat/lng.
            var pAddr = $('modal-pickup-address');
            var dAddr = $('modal-dropoff-address');
            if (pAddr && dAddr) {
                var addrTmp = pAddr.value;
                pAddr.value = dAddr.value;
                dAddr.value = addrTmp;
            }
            updateClearButtons();
            syncHiddenForm();
            if (pointsAreTooClose()) {
                alertPointsTooClose({ clearDropoff: true });
                return;
            }
            // Đảo xong vẫn ở form đón/trả — không nhảy màn hình.
            focusField(activeField === 'pickup' ? 'dropoff' : 'pickup');
        });
    }

    window.BookingAddressSheet = {
        open: openSheet,
        close: closeSheet,
    };
})();
