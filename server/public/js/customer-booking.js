/**
 * Đặt xe — flow inline kiểu Be: tuyến → chọn xe → liên hệ → đặt.
 */
(function () {
    var flowEl = document.getElementById('booking-flow');
    var form = document.getElementById('booking-form');
    var homeSurface = document.getElementById('booking-home-surface');
    if (!flowEl || !form) {
        return;
    }

    var ctx = {};
    var quoteTimer = null;
    var quoteRequestSeq = 0;

    function $(id) { return document.getElementById(id); }

    function customerContactPhone() {
        var phoneEl = $('modal-contact-phone');
        if (phoneEl && phoneEl.value.trim()) {
            return phoneEl.value.trim();
        }
        var prefill = window.__customerBookingPrefill;
        if (prefill && prefill.contact_phone) {
            return String(prefill.contact_phone).trim();
        }
        return '';
    }

    function isFlowOpen() {
        return flowEl && !flowEl.classList.contains('d-none') && !flowEl.hasAttribute('hidden');
    }

    function showBookingFlow() {
        if (!flowEl) {
            return;
        }
        flowEl.classList.remove('d-none');
        flowEl.removeAttribute('hidden');
        if (homeSurface) {
            homeSurface.classList.add('d-none');
        }
        document.body.classList.add('be-booking-flow-open');
        try {
            flowEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } catch (e) {
            /* ignore */
        }
    }

    function hideBookingFlow() {
        if (!flowEl) {
            return;
        }
        flowEl.classList.add('d-none');
        flowEl.setAttribute('hidden', '');
        if (homeSurface) {
            homeSurface.classList.remove('d-none');
        }
        document.body.classList.remove('be-booking-flow-open');
        setStep('pickup');
        if (window.FormFieldValidation) {
            window.FormFieldValidation.clearAll(form);
        }
        if (window.AppFlash && window.AppFlash.clear) {
            window.AppFlash.clear(document.getElementById('booking-modal-flash'));
        }
        var routeCard = document.getElementById('booking-route-card');
        if (routeCard) {
            try {
                routeCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } catch (e) {
                /* ignore */
            }
        }
    }

    function notifyTarget() {
        if (isFlowOpen()) {
            return '#booking-modal-flash';
        }
        return '#app-flash-stack';
    }

    function notify(message, options) {
        options = options || {};
        if (window.BookingBrowserGuard
            && window.BookingBrowserGuard.isBookingBlocked
            && window.BookingBrowserGuard.isBookingBlocked()
            && document.getElementById('booking-browser-guard-banner')) {
            if (window.BookingBrowserGuard.syncBanner) {
                window.BookingBrowserGuard.syncBanner();
            }
            return;
        }
        if (window.AppFlash && window.AppFlash.show) {
            window.AppFlash.show(message, {
                variant: options.variant || 'warning',
                title: options.title || 'Chưa thể đặt chuyến',
                target: options.target || notifyTarget(),
                autoDismiss: options.autoDismiss != null ? options.autoDismiss : 8000,
            });
            return;
        }
        if (window.AppDialog && window.AppDialog.alert) {
            window.AppDialog.alert(message, options || { variant: 'warning', title: 'Chưa thể đặt chuyến' });
        }
    }

    function browserBlocked() {
        return window.BookingBrowserGuard && window.BookingBrowserGuard.isBookingBlocked();
    }

    function guardBookingAction() {
        if (!browserBlocked()) {
            return false;
        }
        if (window.BookingBrowserGuard.alertIfBlocked) {
            window.BookingBrowserGuard.alertIfBlocked();
        } else {
            notify(window.BookingBrowserGuard && window.BookingBrowserGuard.blockMessage
                ? window.BookingBrowserGuard.blockMessage()
                : 'Đã hủy quá nhiều lần trên trình duyệt này, vui lòng thử lại sau hoặc liên hệ tổng đài để biết thêm thông tin chi tiết.');
        }
        return true;
    }

    function duplicateMessage(booking) {
        var parts = ['Bạn đang có một cuốc chưa hoàn thành. Vui lòng hoàn tất hoặc hủy cuốc đó trước khi đặt cuốc mới.'];
        if (booking) {
            if (booking.trip_code && booking.trip_code !== '—') {
                parts.push('Mã chuyến: ' + booking.trip_code);
            }
            if (booking.route && booking.route !== '—') {
                parts.push('Tuyến: ' + booking.route);
            }
            if (booking.progress_label) {
                parts.push('Trạng thái: ' + booking.progress_label);
            }
        }
        return parts.join('\n');
    }

    function checkBookingConstraints(phone) {
        if (window.BookingBrowserGuard && window.BookingBrowserGuard.checkBookingEligibility) {
            return window.BookingBrowserGuard.checkBookingEligibility(phone || '');
        }
        return checkDuplicatePhone(phone);
    }

    function checkDuplicatePhone(phone) {
        if (!window.__bookingCheckDuplicateUrl) {
            return Promise.resolve(null);
        }
        var params = new URLSearchParams();
        if (phone) {
            params.set('contact_phone', phone);
        }
        if (window.BookingBrowserGuard && window.BookingBrowserGuard.getBrowserSessionId) {
            params.set('booking_browser_id', window.BookingBrowserGuard.getBrowserSessionId());
        }
        return fetch(window.__bookingCheckDuplicateUrl + '?' + params.toString(), {
            headers: window.BookingBrowserGuard && window.BookingBrowserGuard.appendHeaders
                ? window.BookingBrowserGuard.appendHeaders({ Accept: 'application/json' })
                : { Accept: 'application/json' },
        })
            .then(function (r) {
                if (!r.ok) {
                    throw new Error('duplicate_check_failed');
                }
                return r.json();
            })
            .catch(function () {
                return null;
            });
    }

    function showDuplicateNotice(booking) {
        notify(duplicateMessage(booking), { variant: 'warning', title: 'Đang có cuốc chưa xong' });
    }

    function ensureBrowserIdOnForm() {
        if (window.BookingBrowserGuard && window.BookingBrowserGuard.ensureHiddenInput) {
            window.BookingBrowserGuard.ensureHiddenInput(form);
        }
    }

    function submitBookingForm() {
        syncScheduleLater();
        ensureBrowserIdOnForm();
        form.submit();
    }

    function formatMoney(n) {
        return (Number(n) || 0).toLocaleString('vi-VN') + ' đ';
    }

    function shortAddress(text) {
        var value = String(text || '').trim();
        if (!value) {
            return '';
        }
        var parts = value.split(',').map(function (p) { return p.trim(); }).filter(Boolean);
        return parts.slice(0, 2).join(', ') || value;
    }

    function modalRoute() {
        return {
            pickup: $('modal-pickup-address') ? $('modal-pickup-address').value.trim() : '',
            dropoff: $('modal-dropoff-address') ? $('modal-dropoff-address').value.trim() : '',
            pickupDetail: $('modal-pickup-detail') ? $('modal-pickup-detail').value.trim() : '',
            dropoffDetail: $('modal-dropoff-detail') ? $('modal-dropoff-detail').value.trim() : '',
        };
    }

    function routeLabel(route) {
        route = route || modalRoute();
        var from = shortAddress(route.pickupDetail) || route.pickup;
        var to = shortAddress(route.dropoffDetail) || route.dropoff;
        if (!from || !to) {
            return from || to || '';
        }
        return from + ' → ' + to;
    }

    function setBannerText(id, text) {
        var el = $(id);
        if (!el) {
            return;
        }
        el.textContent = text || '';
        el.classList.toggle('d-none', !text);
    }

    function coordValue(id) {
        var el = $(id);
        if (!el || el.value === '') {
            return '';
        }
        return el.value;
    }

    var pickupGpsInFlight = false;

    function applyPickupFromGps(lat, lng, address, province) {
        var latEl = $('modal-pickup-lat');
        var lngEl = $('modal-pickup-lng');
        var detailEl = $('modal-pickup-detail');

        if (latEl) {
            latEl.value = String(lat);
        }
        if (lngEl) {
            lngEl.value = String(lng);
        }
        if (detailEl && address) {
            detailEl.value = address;
            detailEl.dispatchEvent(new Event('change', { bubbles: true }));
        }

        document.dispatchEvent(new CustomEvent('addressmap:applied', {
            bubbles: true,
            detail: {
                targetInputId: 'modal-pickup-detail',
                latInputId: 'modal-pickup-lat',
                lngInputId: 'modal-pickup-lng',
                lat: lat,
                lng: lng,
                address: address,
                province: province || '',
            },
        }));
    }

    function autoFillPickupFromGps() {
        if (pickupGpsInFlight) {
            return;
        }
        if (!navigator.geolocation || !window.__geocodeReverseUrl) {
            return;
        }
        if (coordValue('modal-pickup-lat') && coordValue('modal-pickup-lng')) {
            var existingDetail = $('modal-pickup-detail');
            if (existingDetail && existingDetail.value.trim()) {
                return;
            }
        }

        pickupGpsInFlight = true;
        var detailEl = $('modal-pickup-detail');
        var previousPlaceholder = detailEl ? detailEl.placeholder : '';
        if (detailEl && !detailEl.value.trim()) {
            detailEl.placeholder = 'Đang lấy vị trí GPS…';
        }

        navigator.geolocation.getCurrentPosition(
            function (pos) {
                var lat = pos.coords.latitude;
                var lng = pos.coords.longitude;
                fetch(window.__geocodeReverseUrl + '?lat=' + encodeURIComponent(lat) + '&lon=' + encodeURIComponent(lng), {
                    headers: { Accept: 'application/json' },
                })
                    .then(function (r) { return r.ok ? r.json() : null; })
                    .then(function (data) {
                        var address = data
                            ? String(data.address || data.display_name || '').trim()
                            : '';
                        var province = data ? String(data.province || '').trim() : '';
                        if (!address) {
                            address = 'Vị trí GPS (' + lat.toFixed(5) + ', ' + lng.toFixed(5) + ')';
                        }
                        applyPickupFromGps(lat, lng, address, province);
                    })
                    .catch(function () {
                        applyPickupFromGps(
                            lat,
                            lng,
                            'Vị trí GPS (' + lat.toFixed(5) + ', ' + lng.toFixed(5) + ')',
                            ''
                        );
                    })
                    .finally(function () {
                        pickupGpsInFlight = false;
                        if (detailEl) {
                            detailEl.placeholder = previousPlaceholder || 'Tìm địa chỉ hoặc chọn trên bản đồ';
                        }
                    });
            },
            function () {
                pickupGpsInFlight = false;
                if (detailEl) {
                    detailEl.placeholder = previousPlaceholder || 'Tìm địa chỉ hoặc chọn trên bản đồ';
                }
            },
            {
                enableHighAccuracy: true,
                timeout: 15000,
                maximumAge: 60000,
            }
        );
    }

    function coordsReady() {
        return !!(coordValue('modal-pickup-lat') && coordValue('modal-pickup-lng')
            && coordValue('modal-dropoff-lat') && coordValue('modal-dropoff-lng'));
    }

    function haversineKm(lat1, lng1, lat2, lng2) {
        var toRad = function (deg) { return deg * Math.PI / 180; };
        var r = 6371;
        var dLat = toRad(lat2 - lat1);
        var dLng = toRad(lng2 - lng1);
        var a = Math.sin(dLat / 2) * Math.sin(dLat / 2)
            + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(dLng / 2) * Math.sin(dLng / 2);
        return r * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    function localDistanceKm() {
        if (!coordsReady()) {
            return 0;
        }
        return Math.max(1, Math.round(haversineKm(
            Number(coordValue('modal-pickup-lat')),
            Number(coordValue('modal-pickup-lng')),
            Number(coordValue('modal-dropoff-lat')),
            Number(coordValue('modal-dropoff-lng')),
        )));
    }

    function formatDistanceKm(km) {
        var n = Number(km);
        if (!(n > 0)) {
            return '';
        }
        if (Math.abs(n - Math.round(n)) < 0.05) {
            return Math.round(n) + ' km';
        }

        return n.toFixed(1).replace('.', ',') + ' km';
    }

    function formatReferralPercent(pct) {
        var n = Number(pct);
        if (!(n > 0)) {
            return '';
        }
        if (Math.abs(n - Math.round(n)) < 0.05) {
            return String(Math.round(n));
        }

        return n.toFixed(1).replace('.', ',');
    }

    function syncDistanceLabel(km) {
        var distance = Number(km) > 0 ? Number(km) : localDistanceKm();
        var shortDist = distance > 0 ? ('~' + formatDistanceKm(distance)) : '';
        var bannerText = distance > 0 ? ('Khoảng ' + formatDistanceKm(distance)) : '';
        var footerText = distance > 0 ? formatDistanceKm(distance) : '';
        // Chip route: chữ ngắn dưới nút swap; chỗ khác giữ "Khoảng …"
        setBannerText('modal-route-distance', shortDist);
        setBannerText('modal-route-distance-step2', bannerText);
        [
            ['modal-price-distance-row-step1', 'modal-price-distance-step1'],
            ['modal-price-distance-row-step2', 'modal-price-distance-step2'],
        ].forEach(function (pair) {
            var row = $(pair[0]);
            var valEl = $(pair[1]);
            if (!valEl) {
                return;
            }
            if (footerText) {
                valEl.textContent = footerText;
                if (row) row.classList.remove('d-none');
            } else {
                valEl.textContent = '';
                if (row) row.classList.add('d-none');
            }
        });
    }

    function setPriceSummaryBlock(suffix, hasDiscount, subtotal, displayTotal, discountNote, extrasNote) {
        var origRow = $('modal-original-row-' + suffix);
        var discRow = $('modal-discount-row-' + suffix);
        var extrasRow = $('modal-extras-row-' + suffix);
        var origEl = $('modal-original-price-' + suffix);
        var discEl = $('modal-referral-discount-' + suffix);
        var extrasEl = $('modal-price-extras-' + suffix);
        var totalEl = suffix === 'step1' ? $('modal-total-price-step1') : $('modal-total-price');

        if (extrasEl && extrasRow) {
            if (extrasNote) {
                extrasEl.textContent = extrasNote;
                extrasRow.classList.remove('d-none');
            } else {
                extrasEl.textContent = '';
                extrasRow.classList.add('d-none');
            }
        }

        if (origEl && origRow) {
            if (hasDiscount && subtotal > 0) {
                origEl.textContent = formatMoney(subtotal);
                origRow.classList.remove('d-none');
            } else {
                origEl.textContent = '';
                origRow.classList.add('d-none');
            }
        }

        if (discEl && discRow) {
            if (discountNote) {
                discEl.textContent = discountNote;
                discRow.classList.remove('d-none');
            } else {
                discEl.textContent = '';
                discRow.classList.add('d-none');
            }
        }

        if (totalEl) {
            if (displayTotal > 0) {
                totalEl.textContent = formatMoney(displayTotal);
            } else if (totalEl.getAttribute('data-price-loading') === '1') {
                totalEl.textContent = 'Đang tính…';
            } else if (routeReady()) {
                totalEl.textContent = '—';
            } else {
                totalEl.textContent = 'Chọn đủ thông tin';
            }
            totalEl.classList.remove('d-none');
            totalEl.classList.toggle('booking-price-discounted', hasDiscount && displayTotal > 0);
        }

        var summary = $('modal-price-summary-' + suffix);
        if (summary) {
            summary.classList.toggle('is-discounted', hasDiscount && displayTotal > 0);
        }
    }

    function routeReady() {
        var route = modalRoute();
        return !!(route.pickupDetail && route.dropoffDetail && coordsReady());
    }

    function setPriceLoading(loading) {
        ['step1', 'step2'].forEach(function (suffix) {
            var totalEl = suffix === 'step1' ? $('modal-total-price-step1') : $('modal-total-price');
            if (!totalEl) {
                return;
            }
            if (loading && routeReady()) {
                totalEl.setAttribute('data-price-loading', '1');
                totalEl.textContent = 'Đang tính…';
                totalEl.classList.remove('d-none');
            } else {
                totalEl.removeAttribute('data-price-loading');
            }
        });
    }

    function refreshDistanceAndQuote() {
        syncRouteLabels();
        if (coordsReady()) {
            syncDistanceLabel(0);
        }
        refreshQuote();
    }

    function syncRouteLabels() {
        var route = modalRoute();
        var from = shortAddress(route.pickupDetail) || route.pickup || '—';
        var to = shortAddress(route.dropoffDetail) || route.dropoff || '—';
        setBannerText('modal-route-pickup', from);
        setBannerText('modal-route-dropoff', to);
        setBannerText('modal-route', routeLabel(route));
        setBannerText('modal-route-step2', routeLabel(route));
        if (!coordsReady()) {
            syncDistanceLabel(0);
        }
    }

    function swapRouteEnds() {
        var pairs = [
            ['modal-pickup-detail', 'modal-dropoff-detail'],
            ['modal-pickup-address', 'modal-dropoff-address'],
            ['modal-pickup-lat', 'modal-dropoff-lat'],
            ['modal-pickup-lng', 'modal-dropoff-lng'],
        ];
        pairs.forEach(function (ids) {
            var a = $(ids[0]);
            var b = $(ids[1]);
            if (!a || !b) {
                return;
            }
            var tmp = a.value;
            a.value = b.value;
            b.value = tmp;
        });
        ['modal-pickup-lng', 'modal-dropoff-lng'].forEach(function (id) {
            var el = $(id);
            if (el) {
                el.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
        syncRouteLabels();
        syncPickupConfirmUi();
        if (currentStep() === 'vehicle') {
            ensureFlowMapAssets(scheduleFlowMapPreview);
        } else if (currentStep() === 'pickup') {
            ensureFlowMapAssets(function () {
                window.setTimeout(updatePickupMapPreview, 80);
            });
        }
        refreshDistanceAndQuote();
    }

    var MIN_TRIP_METERS = 200;

    function currentStep() {
        var vehicle = $('booking-step-vehicle');
        if (vehicle && !vehicle.classList.contains('d-none')) {
            return 'vehicle';
        }
        return 'pickup';
    }

    function scrollFlowBodyToTop() {
        var body = flowEl.querySelector('.be-booking-flow__body') || flowEl.querySelector('.booking-modal-body');
        if (body) {
            body.scrollTop = 0;
        }
        try {
            flowEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } catch (e) {
            /* ignore */
        }
    }

    function setStep(step) {
        var pickup = $('booking-step-pickup');
        var vehicle = $('booking-step-vehicle');
        var isPickup = step === 'pickup' || step === 0;
        var isVehicle = step === 'vehicle' || step === 1;
        if (pickup) pickup.classList.toggle('d-none', !isPickup);
        if (vehicle) vehicle.classList.toggle('d-none', !isVehicle);
        scrollFlowBodyToTop();
        if (isPickup) {
            ensureFlowMapAssets(function () {
                window.setTimeout(updatePickupMapPreview, 80);
            });
        } else if (isVehicle) {
            ensureFlowMapAssets(scheduleFlowMapPreview);
        }
    }

    function returnToAddressSheet(focus) {
        hideBookingFlow();
        if (window.BookingAddressSheet && typeof window.BookingAddressSheet.open === 'function') {
            window.BookingAddressSheet.open(focus || 'pickup');
        }
    }

    function syncNotesHidden() {
        var ta = $('modal-notes');
        var hidden = $('modal-notes-value');
        if (ta && hidden) {
            hidden.value = ta.value || '';
        }
    }

    function tripDistanceMeters() {
        if (!coordsReady()) {
            return 0;
        }
        return haversineKm(
            Number(coordValue('modal-pickup-lat')),
            Number(coordValue('modal-pickup-lng')),
            Number(coordValue('modal-dropoff-lat')),
            Number(coordValue('modal-dropoff-lng')),
        ) * 1000;
    }

    function assertMinTripDistance() {
        var meters = tripDistanceMeters();
        if (meters > 0 && meters < MIN_TRIP_METERS) {
            notify('Không được đặt xe: điểm đi quá gần điểm trả (dưới 200m).', {
                variant: 'warning',
                title: 'Khoảng cách quá ngắn',
            });
            return false;
        }
        return true;
    }

    function updatePriceDisplay(total, data) {
        var hasDiscount = !!(data && data.referral_eligible && data.referral_discount_amount > 0);
        var subtotal = Number(data && (data.price_subtotal != null ? data.price_subtotal : (data.subtotal || data.whole_car_price)) || 0);
        var finalTotal = total > 0 ? total : (hasDiscount ? Number(data.total_after_discount || 0) : 0);
        var discountNote = '';
        var extrasNote = '';

        if (data && data.distance_km) {
            syncDistanceLabel(data.distance_km);
        } else if (routeReady()) {
            syncDistanceLabel(0);
        }

        if (data) {
            var extras = Number(data.surcharge_holiday || 0)
                + Number(data.surcharge_peak || 0)
                + Number(data.surcharge_rain || 0)
                + Number(data.toll_amount || 0);
            if (extras > 0) {
                var bits = [];
                if (Number(data.surcharge_holiday || 0) > 0) bits.push('lễ ' + formatMoney(data.surcharge_holiday));
                if (Number(data.surcharge_peak || 0) > 0) bits.push('cao điểm ' + formatMoney(data.surcharge_peak));
                if (Number(data.surcharge_rain || 0) > 0) bits.push('mưa ' + formatMoney(data.surcharge_rain));
                if (Number(data.toll_amount || 0) > 0) bits.push('thu phí ' + formatMoney(data.toll_amount));
                extrasNote = bits.join(' · ');
            }
        }

        if (hasDiscount) {
            var pct = data.referral_discount_percent;
            var sourceLabel = data.referral_discount_label || window.__referralDiscountLabel || 'giới thiệu';
            discountNote = '−' + formatMoney(data.referral_discount_amount);
            if (pct > 0) {
                discountNote += ' (' + formatReferralPercent(pct) + '% — ' + sourceLabel + ')';
            }
        }

        var displayTotal = hasDiscount ? finalTotal : (finalTotal > 0 ? finalTotal : total);
        if (!displayTotal && data && data.total_after_discount != null) {
            displayTotal = Number(data.total_after_discount);
        }
        ['step1', 'step2'].forEach(function (suffix) {
            setPriceSummaryBlock(suffix, hasDiscount, subtotal, displayTotal, discountNote, extrasNote);
        });
    }

    function quoteParams(overrideCapacity, overrideVehicleType) {
        var route = modalRoute();
        var params = new URLSearchParams({
            pickup_address: route.pickup,
            dropoff_address: route.dropoff,
            pickup_detail: route.pickupDetail,
            dropoff_detail: route.dropoffDetail,
            contact_phone: customerContactPhone(),
        });
        ['modal-pickup-lat', 'modal-pickup-lng', 'modal-dropoff-lat', 'modal-dropoff-lng'].forEach(function (id) {
            var value = coordValue(id);
            if (value) {
                params.set(id.replace('modal-', '').replace(/-/g, '_'), value);
            }
        });
        var capacity = overrideCapacity != null ? overrideCapacity : ctx.capacity;
        var vehicleType = overrideVehicleType != null ? overrideVehicleType : ctx.vehicleType;
        if (capacity) {
            params.set('capacity', capacity);
        }
        if (vehicleType) {
            params.set('vehicle_type', vehicleType);
        }
        var pickupTimeEl = $('modal-pickup-time') || document.querySelector('[name="pickup_time"]');
        if (pickupTimeEl && pickupTimeEl.value) {
            params.set('pickup_time', pickupTimeEl.value);
        }
        return params;
    }

    function refreshVehicleCardPrices() {
        if (!window.__quotePriceUrl || !routeReady()) {
            return;
        }
        document.querySelectorAll('#trips-list [data-capacity]').forEach(function (card) {
            var capacity = card.getAttribute('data-capacity');
            var vehicleType = card.getAttribute('data-vehicle-type') || '';
            var priceEl = card.querySelector('[data-price-slot]');
            if (!capacity || !priceEl) {
                return;
            }
            var requestId = (Number(card.getAttribute('data-price-req')) || 0) + 1;
            card.setAttribute('data-price-req', String(requestId));
            priceEl.textContent = 'Đang tính giá…';
            fetch(window.__quotePriceUrl + '?' + quoteParams(capacity, vehicleType).toString(), {
                headers: { Accept: 'application/json' },
            })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (data) {
                    // Bỏ qua nếu thẻ này đã có yêu cầu mới hơn — tránh giá cũ đè giá mới.
                    if (String(requestId) !== card.getAttribute('data-price-req')) {
                        return;
                    }
                    if (!data) {
                        priceEl.textContent = '—';
                        return;
                    }
                    var total = data.total_after_discount != null ? data.total_after_discount : data.whole_car_price;
                    priceEl.textContent = total > 0 ? formatMoney(total) : '—';
                })
                .catch(function () {
                    if (String(requestId) === card.getAttribute('data-price-req')) {
                        priceEl.textContent = '—';
                    }
                });
        });
    }

    function refreshQuote() {
        if (!window.__quotePriceUrl || !ctx.capacity) {
            updatePriceDisplay(0);
            if (!coordsReady()) {
                syncDistanceLabel(0);
            }
            return;
        }
        if (!routeReady()) {
            updatePriceDisplay(0);
            if (coordsReady()) {
                syncDistanceLabel(0);
            }
            return;
        }

        syncDistanceLabel(0);
        setPriceLoading(true);
        clearTimeout(quoteTimer);
        var requestId = ++quoteRequestSeq;
        quoteTimer = setTimeout(function () {
            fetch(window.__quotePriceUrl + '?' + quoteParams().toString(), {
                headers: { Accept: 'application/json' },
            })
                .then(function (r) {
                    if (!r.ok) {
                        throw new Error('quote failed');
                    }
                    return r.json();
                })
                .then(function (data) {
                    // Bỏ qua nếu đã có yêu cầu mới hơn xuất phát sau — tránh giá cũ đè giá mới do phản hồi tới không theo thứ tự.
                    if (requestId !== quoteRequestSeq) {
                        return;
                    }
                    var total = data.total_after_discount != null ? data.total_after_discount : data.whole_car_price;
                    updatePriceDisplay(total, data);
                })
                .catch(function () {
                    if (requestId !== quoteRequestSeq) {
                        return;
                    }
                    updatePriceDisplay(0);
                    if (coordsReady()) {
                        syncDistanceLabel(0);
                    }
                })
                .finally(function () {
                    if (requestId === quoteRequestSeq) {
                        setPriceLoading(false);
                    }
                });
        }, 150);
    }

    function todayIso() {
        return window.__todayDate || new Date().toISOString().slice(0, 10);
    }

    function suggestedPickupTime() {
        var lead = Number(window.__pickupLeadMinutes || 30);
        var d = new Date();
        d.setSeconds(0, 0);
        d.setMinutes(d.getMinutes() + lead);
        var remainder = d.getMinutes() % 5;
        if (remainder !== 0) {
            d.setMinutes(d.getMinutes() + (5 - remainder));
        }
        return String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
    }

    function scheduleLaterEnabled() {
        var toggle = $('booking-schedule-later');
        var btn = $('booking-schedule-later-btn');
        if (toggle && toggle.getAttribute('aria-pressed') === 'true') {
            return true;
        }
        return !!(btn && btn.getAttribute('aria-pressed') === 'true');
    }

    function setScheduleLaterEnabled(enabled) {
        var toggle = $('booking-schedule-later');
        var btn = $('booking-schedule-later-btn')
            || document.querySelector('[data-schedule-mode="later"]');
        enabled = !!enabled;
        if (toggle) {
            toggle.setAttribute('aria-pressed', enabled ? 'true' : 'false');
        }
        if (btn) {
            btn.classList.toggle('is-active', enabled);
            btn.setAttribute('aria-pressed', enabled ? 'true' : 'false');
        }
    }

    function formatScheduleChipLabel(dateIso, timeValue) {
        if (!dateIso || !timeValue) {
            return 'Đặt sau';
        }
        var parts = String(dateIso).split('-');
        if (parts.length !== 3) {
            return timeValue;
        }
        var dateLabel = parts[2] + '/' + parts[1];
        if (dateIso === todayIso()) {
            return 'Hôm nay · ' + timeValue;
        }
        var tomorrow = new Date();
        tomorrow.setHours(0, 0, 0, 0);
        tomorrow.setDate(tomorrow.getDate() + 1);
        var tomorrowIso = [
            tomorrow.getFullYear(),
            String(tomorrow.getMonth() + 1).padStart(2, '0'),
            String(tomorrow.getDate()).padStart(2, '0'),
        ].join('-');
        if (dateIso === tomorrowIso) {
            return 'Ngày mai · ' + timeValue;
        }
        return dateLabel + ' · ' + timeValue;
    }

    function syncScheduleChipLabel() {
        var btn = $('booking-schedule-later-btn');
        if (!btn) {
            return;
        }
        btn.title = scheduleLaterEnabled()
            ? formatScheduleChipLabel(
                ($('modal-service-date') || {}).value || '',
                ($('modal-pickup-time') || {}).value || '',
            )
            : 'Đặt sau';
    }

    function syncScheduleLater() {
        var enabled = scheduleLaterEnabled();
        var fields = $('booking-schedule-fields');
        var dateEl = $('modal-service-date');
        var timeEl = $('modal-pickup-time');

        if (fields) {
            fields.classList.toggle('d-none', !enabled);
        }

        if (dateEl) {
            dateEl.min = todayIso();
            dateEl.disabled = !enabled;
            if (enabled) {
                dateEl.setAttribute('required', 'required');
                if (!dateEl.value || dateEl.value < dateEl.min) {
                    dateEl.value = window.__defaultServiceDate || todayIso();
                }
            } else {
                dateEl.removeAttribute('required');
                dateEl.value = '';
            }
        }

        if (timeEl) {
            timeEl.disabled = !enabled;
            if (enabled) {
                timeEl.setAttribute('required', 'required');
                if (!timeEl.value) {
                    timeEl.value = suggestedPickupTime();
                }
            } else {
                timeEl.removeAttribute('required');
                timeEl.value = '';
            }
        }

        syncScheduleChipLabel();
    }

    function markSelectedVehicle(btn) {
        document.querySelectorAll('#trips-list .vehicle-select-row').forEach(function (row) {
            row.classList.remove('is-selected');
        });
        if (!btn) {
            return;
        }
        var row = btn.closest('.vehicle-select-row');
        if (row) {
            row.classList.add('is-selected');
        }
    }

    function openFromButton(btn) {
        if (guardBookingAction()) {
            return;
        }

        checkBookingConstraints('').then(function (data) {
            if (data && data.duplicate) {
                showDuplicateNotice(data.booking);
                return;
            }
            markSelectedVehicle(btn);
            openBookingModal(btn);
        });
    }

    /** Prefill SĐT cũ (nếu còn ô liên hệ); họ tên / tuổi / GT lấy từ hồ sơ server khi submit. */
    function applyCustomerPrefill() {
        var prefill = window.__customerBookingPrefill;
        if (!prefill || typeof prefill !== 'object') {
            return;
        }
        var phoneEl = $('modal-contact-phone');
        if (phoneEl && !phoneEl.value.trim() && prefill.contact_phone) {
            phoneEl.value = String(prefill.contact_phone);
        }
    }

    var VEHICLE_COLLAPSED_VISIBLE = 3;

    function vehicleListRows() {
        var list = $('trips-list');
        if (!list) {
            return [];
        }
        return Array.prototype.slice.call(list.querySelectorAll('[data-select-vehicle]'));
    }

    function vehicleSheetEl() {
        return $('booking-vehicle-sheet')
            || document.querySelector('#booking-step-vehicle .be-step__sheet--vehicle');
    }

    function vehicleExtraCount() {
        return Math.max(0, vehicleListRows().length - VEHICLE_COLLAPSED_VISIBLE);
    }

    function isVehicleListExpanded() {
        var sheet = vehicleSheetEl();
        return !!(sheet && sheet.getAttribute('data-vehicle-expanded') === 'true');
    }

    function resizeFlowMapSoon() {
        window.requestAnimationFrame(function () {
            window.setTimeout(function () {
                if (flowMapInstance && typeof flowMapInstance.resize === 'function') {
                    try {
                        flowMapInstance.resize();
                    } catch (e) { /* ignore */ }
                }
            }, 40);
        });
    }

    /** Gắn lại --extra theo thứ tự DOM; thu gọn chỉ hiện N xe đầu (xe chọn đã promote lên đầu). */
    function syncVehicleListVisibility(expanded) {
        var rows = vehicleListRows();
        var nextOpen = !!expanded;
        var extraCount = Math.max(0, rows.length - VEHICLE_COLLAPSED_VISIBLE);
        if (extraCount < 1) {
            nextOpen = false;
        }

        rows.forEach(function (row, index) {
            var isExtra = index >= VEHICLE_COLLAPSED_VISIBLE;
            row.classList.toggle('be-vehicle-row--extra', isExtra);
            if (!nextOpen && isExtra) {
                row.setAttribute('hidden', '');
            } else {
                row.removeAttribute('hidden');
            }
        });

        var sheet = vehicleSheetEl();
        if (sheet) {
            sheet.classList.toggle('is-collapsed', !nextOpen);
            sheet.setAttribute('data-vehicle-expanded', nextOpen ? 'true' : 'false');
            sheet.classList.toggle('has-vehicle-extra', extraCount > 0);
        }

        var handle = $('booking-vehicle-sheet-handle');
        if (handle) {
            handle.hidden = extraCount < 1;
            handle.setAttribute('aria-expanded', nextOpen ? 'true' : 'false');
            handle.setAttribute(
                'aria-label',
                nextOpen
                    ? 'Vuốt xuống để thu gọn danh sách xe'
                    : 'Vuốt lên để xem thêm loại xe'
            );
        }

        var countEl = $('booking-vehicle-extra-count');
        if (countEl) {
            countEl.setAttribute('data-extra-count', String(extraCount));
        }

        resizeFlowMapSoon();
    }

    function setVehicleListExpanded(open) {
        syncVehicleListVisibility(!!open);
    }

    function bindVehicleSheetHandle() {
        var handle = $('booking-vehicle-sheet-handle');
        if (!handle || handle.getAttribute('data-bound') === '1') {
            return;
        }
        handle.setAttribute('data-bound', '1');

        var startY = null;
        var tracking = false;

        function canToggle() {
            return vehicleExtraCount() > 0;
        }

        function onPointerDown(clientY) {
            if (!canToggle()) {
                return;
            }
            startY = clientY;
            tracking = true;
        }

        function onPointerUp(clientY) {
            if (!tracking || startY === null) {
                tracking = false;
                startY = null;
                return;
            }
            var dy = clientY - startY;
            tracking = false;
            startY = null;
            if (Math.abs(dy) < 28) {
                return;
            }
            // Vuốt xuống = thu gọn; vuốt lên = mở rộng
            if (dy > 0) {
                setVehicleListExpanded(false);
            } else {
                setVehicleListExpanded(true);
            }
        }

        handle.addEventListener('touchstart', function (e) {
            if (!e.touches || !e.touches[0]) {
                return;
            }
            onPointerDown(e.touches[0].clientY);
        }, { passive: true });

        handle.addEventListener('touchend', function (e) {
            var t = e.changedTouches && e.changedTouches[0];
            if (!t) {
                tracking = false;
                startY = null;
                return;
            }
            onPointerUp(t.clientY);
        }, { passive: true });

        handle.addEventListener('mousedown', function (e) {
            onPointerDown(e.clientY);
        });

        window.addEventListener('mouseup', function (e) {
            if (!tracking) {
                return;
            }
            onPointerUp(e.clientY);
        });

        handle.addEventListener('keydown', function (e) {
            if (!canToggle()) {
                return;
            }
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                setVehicleListExpanded(!isVehicleListExpanded());
            } else if (e.key === 'ArrowDown') {
                e.preventDefault();
                setVehicleListExpanded(false);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                setVehicleListExpanded(true);
            }
        });
    }

    function promoteVehicleCard(card) {
        var list = $('trips-list');
        if (!list || !card || !list.contains(card)) {
            return;
        }
        // Xe đang chọn luôn nằm đầu list.
        if (list.firstElementChild !== card) {
            list.insertBefore(card, list.firstElementChild);
        }
        var scroll = document.querySelector('#booking-step-vehicle .be-step__sheet-scroll');
        if (scroll) {
            scroll.scrollTop = 0;
        }
        // Giữ trạng thái mở/đóng hiện tại, gắn lại extra theo thứ tự DOM.
        syncVehicleListVisibility(isVehicleListExpanded());
    }

    function selectVehicleCard(card) {
        if (!card) {
            return;
        }
        document.querySelectorAll('#trips-list [data-select-vehicle]').forEach(function (el) {
            el.classList.toggle('is-selected', el === card);
            el.setAttribute('aria-selected', el === card ? 'true' : 'false');
        });
        promoteVehicleCard(card);
        ctx = {
            capacity: card.getAttribute('data-capacity') || '',
            vehicleType: card.getAttribute('data-vehicle-type') || '',
            capacityLabel: card.getAttribute('data-capacity-label') || '',
            typeLabel: card.getAttribute('data-type-label') || '',
            vehiclePhoto: card.getAttribute('data-vehicle-photo') || '',
            offerLabel: card.getAttribute('data-offer-label') || '',
        };
        if ($('modal-capacity')) {
            $('modal-capacity').value = ctx.capacity;
        }
        if ($('modal-vehicle-type')) {
            $('modal-vehicle-type').value = ctx.vehicleType;
        }
        var submitBtn = $('modal-submit-btn');
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Đặt chuyến';
        }
        refreshQuote();
        syncBookingTransferQr();
    }

    function openBookingModal(btn) {
        selectVehicleCard(btn);
        syncRouteLabels();
        syncScheduleLater();
        applyCustomerPrefill();
        ensureBrowserIdOnForm();
        setStep('vehicle');
        showBookingFlow();
    }

    var flowMapInstance = null;
    var flowPickupMarker = null;
    var flowDropoffMarker = null;
    var pickupMapInstance = null;
    var pickupMapMarker = null;

    var pickupReverseTimer = null;
    var pickupMapBound = false;
    var pickupNearbyTimer = null;
    var pickupNearbySeq = 0;

    function syncPickupConfirmUi() {
        var addrEl = $('booking-pickup-confirm-addr');
        var detail = $('modal-pickup-detail');
        var lat = coordValue('modal-pickup-lat');
        var lng = coordValue('modal-pickup-lng');
        if (addrEl) {
            addrEl.textContent = detail && detail.value.trim()
                ? detail.value.trim()
                : (lat && lng ? (Number(lat).toFixed(5) + ', ' + Number(lng).toFixed(5)) : '—');
        }
    }

    function formatNearbyDist(meters) {
        var m = Number(meters);
        if (!(m >= 0)) {
            return '';
        }
        if (m < 100) {
            return 'Gần đây';
        }
        if (m < 1000) {
            return Math.round(m) + ' m';
        }
        return (Math.round(m / 100) / 10).toFixed(1).replace('.', ',') + ' km';
    }

    function dedupeNearbyItems(items) {
        var out = [];
        var seenLabel = Object.create(null);
        (items || []).forEach(function (item) {
            if (!item) {
                return;
            }
            var label = String(item.address || item.title || '')
                .toLowerCase()
                .replace(/[^\p{L}\p{N}]+/gu, '');
            var lat = Number(item.lat);
            var lng = Number(item.lon != null ? item.lon : item.lng);
            if (label && seenLabel[label]) {
                return;
            }
            var near = out.some(function (ex) {
                var dLat = (Number(ex.lat) - lat) * 111320;
                var dLng = (Number(ex.lon != null ? ex.lon : ex.lng) - lng) * 111320 * Math.cos(lat * Math.PI / 180);
                return Math.sqrt(dLat * dLat + dLng * dLng) < 25;
            });
            if (near) {
                return;
            }
            if (label) {
                seenLabel[label] = true;
            }
            out.push(item);
        });
        return out;
    }

    function renderPickupNearby(items) {
        var box = $('booking-pickup-nearby');
        if (!box) {
            return;
        }
        box.innerHTML = '';
        items = dedupeNearbyItems(items);
        if (!items || !items.length) {
            box.innerHTML = '';
            return;
        }
        items.forEach(function (item) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'be-pickup-nearby__item';
            btn.innerHTML = '<span class="be-pickup-nearby__copy">'
                + '<span class="be-pickup-nearby__title"></span>'
                + '<span class="be-pickup-nearby__sub"></span>'
                + '</span><span class="be-pickup-nearby__dist"></span>';
            btn.querySelector('.be-pickup-nearby__title').textContent = item.title || item.address || '—';
            btn.querySelector('.be-pickup-nearby__sub').textContent = item.subtitle || item.kind_label || '';
            btn.querySelector('.be-pickup-nearby__dist').textContent = formatNearbyDist(item.distance_m);
            btn.addEventListener('click', function () {
                box.querySelectorAll('.be-pickup-nearby__item').forEach(function (el) {
                    el.classList.toggle('is-active', el === btn);
                });
                applyPickupCoords(
                    Number(item.lat),
                    Number(item.lon != null ? item.lon : item.lng),
                    item.address || item.title || '',
                    item.province || null,
                    { skipNearbyRefresh: true },
                );
                placePickupMarker(Number(item.lat), Number(item.lon != null ? item.lon : item.lng), true);
            });
            box.appendChild(btn);
        });
    }

    function loadPickupNearby() {
        var box = $('booking-pickup-nearby');
        var lat = coordValue('modal-pickup-lat');
        var lng = coordValue('modal-pickup-lng');
        if (!box || !lat || !lng || !window.__geocodeNearbyUrl) {
            return;
        }
        if (pickupNearbyTimer) {
            clearTimeout(pickupNearbyTimer);
        }
        pickupNearbyTimer = setTimeout(function () {
            var seq = ++pickupNearbySeq;
            box.innerHTML = '<p class="be-pickup-nearby__loading">Đang tìm điểm gần…</p>';
            var params = new URLSearchParams({
                lat: String(lat),
                lng: String(lng),
                radius_m: '300',
            });
            fetch(window.__geocodeNearbyUrl + '?' + params.toString(), {
                headers: { Accept: 'application/json' },
            })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (data) {
                    if (seq !== pickupNearbySeq) {
                        return;
                    }
                    renderPickupNearby(data && Array.isArray(data.results) ? data.results : []);
                })
                .catch(function () {
                    if (seq === pickupNearbySeq) {
                        renderPickupNearby([]);
                    }
                });
        }, 280);
    }

    function applyPickupCoords(lat, lng, address, province, options) {
        options = options || {};
        var latEl = $('modal-pickup-lat');
        var lngEl = $('modal-pickup-lng');
        var detailEl = $('modal-pickup-detail');
        var addrEl = $('modal-pickup-address');
        if (latEl) latEl.value = String(lat);
        if (lngEl) lngEl.value = String(lng);
        if (detailEl && address) detailEl.value = address;
        if (addrEl && province) addrEl.value = province;
        syncPickupConfirmUi();
        ['modal-pickup-lat', 'modal-pickup-lng'].forEach(function (id) {
            var el = $(id);
            if (el) el.dispatchEvent(new Event('change', { bubbles: true }));
        });
        if (!options.skipNearbyRefresh) {
            loadPickupNearby();
        }
    }

    function reversePickupAt(lat, lng) {
        if (!window.__geocodeReverseUrl) {
            applyPickupCoords(lat, lng, null, null);
            return;
        }
        fetch(window.__geocodeReverseUrl + '?lat=' + encodeURIComponent(lat) + '&lon=' + encodeURIComponent(lng), {
            headers: { Accept: 'application/json' },
        })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                var address = data ? String(data.address || '').trim() : '';
                var province = data ? String(data.province || '').trim() : '';
                if (!address) {
                    address = Number(lat).toFixed(5) + ', ' + Number(lng).toFixed(5);
                }
                applyPickupCoords(lat, lng, address, province);
            })
            .catch(function () {
                applyPickupCoords(lat, lng, Number(lat).toFixed(5) + ', ' + Number(lng).toFixed(5), null);
            });
    }

    function placePickupMarker(lat, lng, moveView) {
        if (!pickupMapInstance || !window.goongjs) {
            return;
        }
        var ll = [Number(lng), Number(lat)];
        if (!pickupMapMarker) {
            pickupMapMarker = new window.goongjs.Marker({ color: '#3b82f6', draggable: true })
                .setLngLat(ll)
                .addTo(pickupMapInstance);
            pickupMapMarker.on('dragend', function () {
                var pos = pickupMapMarker.getLngLat();
                reversePickupAt(pos.lat, pos.lng);
            });
        } else {
            pickupMapMarker.setLngLat(ll);
        }
        if (moveView) {
            pickupMapInstance.easeTo({ center: ll, zoom: Math.max(pickupMapInstance.getZoom(), 16) });
        }
    }

    function updatePickupMapPreview() {
        syncPickupConfirmUi();
        loadPickupNearby();
        var canvas = $('booking-pickup-map-canvas');
        var lat = coordValue('modal-pickup-lat');
        var lng = coordValue('modal-pickup-lng');
        if (!canvas || !window.goongjs || !window.__goongMaptilesKey) {
            return;
        }
        var centerLng = lng ? Number(lng) : 106.7009;
        var centerLat = lat ? Number(lat) : 10.7769;
        try {
            if (!pickupMapInstance) {
                window.goongjs.accessToken = String(window.__goongMaptilesKey || '');
                pickupMapInstance = new window.goongjs.Map({
                    container: canvas,
                    style: 'https://tiles.goong.io/assets/goong_map_web.json',
                    center: [centerLng, centerLat],
                    zoom: 16,
                    interactive: true,
                    attributionControl: false,
                });
                pickupMapInstance.on('load', function () {
                    if (lat && lng) {
                        placePickupMarker(Number(lat), Number(lng), false);
                    }
                    pickupMapInstance.resize();
                });
                if (!pickupMapBound) {
                    pickupMapBound = true;
                    pickupMapInstance.on('click', function (e) {
                        placePickupMarker(e.lngLat.lat, e.lngLat.lng, false);
                        if (pickupReverseTimer) {
                            clearTimeout(pickupReverseTimer);
                        }
                        pickupReverseTimer = setTimeout(function () {
                            reversePickupAt(e.lngLat.lat, e.lngLat.lng);
                        }, 120);
                    });
                }
            } else {
                if (lat && lng) {
                    placePickupMarker(Number(lat), Number(lng), true);
                }
                pickupMapInstance.resize();
            }
        } catch (e) {
            /* optional */
        }
    }

    function fitRouteBounds(map, coordinates, animate) {
        if (!map || !window.goongjs || !coordinates || coordinates.length < 2) {
            return;
        }
        try {
            var bounds = new window.goongjs.LngLatBounds(coordinates[0], coordinates[0]);
            coordinates.forEach(function (c) { bounds.extend(c); });
            map.fitBounds(bounds, {
                padding: 56,
                maxZoom: 15,
                duration: animate ? 400 : 0,
            });
        } catch (e) {
            /* ignore */
        }
    }

    function drawRouteCoordinates(map, coordinates, animateFit) {
        if (!map || !window.goongjs || !coordinates || coordinates.length < 2) {
            return;
        }
        var geo = {
            type: 'Feature',
            properties: {},
            geometry: {
                type: 'LineString',
                coordinates: coordinates,
            },
        };
        if (map.getSource('booking-route')) {
            map.getSource('booking-route').setData(geo);
        } else {
            map.addSource('booking-route', { type: 'geojson', data: geo });
            map.addLayer({
                id: 'booking-route-line',
                type: 'line',
                source: 'booking-route',
                paint: {
                    'line-color': '#2563eb',
                    'line-width': 5,
                    'line-opacity': 0.92,
                },
            });
        }
        fitRouteBounds(map, coordinates, !!animateFit);
    }

    function fetchRouteCoordinates(from, to, done) {
        var fallback = [from, to];
        if (!window.__geocodeDirectionUrl) {
            done(fallback);
            return;
        }
        var params = new URLSearchParams({
            origin_lat: String(from[1]),
            origin_lng: String(from[0]),
            dest_lat: String(to[1]),
            dest_lng: String(to[0]),
        });
        fetch(window.__geocodeDirectionUrl + '?' + params.toString(), {
            headers: { Accept: 'application/json' },
        })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                var coords = data && Array.isArray(data.coordinates) ? data.coordinates : null;
                done(coords && coords.length >= 2 ? coords : fallback);
            })
            .catch(function () {
                done(fallback);
            });
    }

    var flowMapPreviewKey = '';
    var flowMapPreviewTimer = null;

    function updateFlowMapPreview() {
        var canvas = $('booking-flow-map-canvas');
        var pLat = coordValue('modal-pickup-lat');
        var pLng = coordValue('modal-pickup-lng');
        var dLat = coordValue('modal-dropoff-lat');
        var dLng = coordValue('modal-dropoff-lng');
        if (!canvas || !pLat || !pLng || !dLat || !dLng || !window.goongjs || !window.__goongMaptilesKey) {
            return;
        }
        var from = [Number(pLng), Number(pLat)];
        var to = [Number(dLng), Number(dLat)];
        var previewKey = from.join(',') + '>' + to.join(',');

        function paint(coords) {
            if (!flowMapInstance) {
                return;
            }
            if (flowPickupMarker) flowPickupMarker.setLngLat(from);
            if (flowDropoffMarker) flowDropoffMarker.setLngLat(to);
            // Không animate fit — tránh flash “vị trí hiện tại” rồi mới nhảy tuyến.
            if (flowMapInstance.isStyleLoaded && !flowMapInstance.isStyleLoaded()) {
                flowMapInstance.once('load', function () {
                    drawRouteCoordinates(flowMapInstance, coords, false);
                });
            } else {
                drawRouteCoordinates(flowMapInstance, coords, false);
            }
            flowMapInstance.resize();
        }

        try {
            if (!flowMapInstance) {
                window.goongjs.accessToken = String(window.__goongMaptilesKey || '');
                // Mở thẳng khung đón→trả, không center GPS/điểm đón rồi mới zoom ra.
                var mapOpts = {
                    container: canvas,
                    style: 'https://tiles.goong.io/assets/goong_map_web.json',
                    interactive: true,
                    attributionControl: false,
                };
                try {
                    mapOpts.bounds = [from, to];
                    mapOpts.fitBoundsOptions = { padding: 56, maxZoom: 15 };
                } catch (boundErr) {
                    mapOpts.center = [
                        (from[0] + to[0]) / 2,
                        (from[1] + to[1]) / 2,
                    ];
                    mapOpts.zoom = 12;
                }
                flowMapInstance = new window.goongjs.Map(mapOpts);
                flowPickupMarker = new window.goongjs.Marker({ color: '#3b82f6' })
                    .setLngLat(from)
                    .addTo(flowMapInstance);
                flowDropoffMarker = new window.goongjs.Marker({ color: '#eab308' })
                    .setLngLat(to)
                    .addTo(flowMapInstance);
                flowMapInstance.on('load', function () {
                    fitRouteBounds(flowMapInstance, [from, to], false);
                    fetchRouteCoordinates(from, to, paint);
                });
                flowMapPreviewKey = previewKey;
            } else if (flowMapPreviewKey !== previewKey) {
                flowMapPreviewKey = previewKey;
                fitRouteBounds(flowMapInstance, [from, to], false);
                fetchRouteCoordinates(from, to, paint);
            } else {
                flowMapInstance.resize();
            }
        } catch (e) {
            /* map preview optional */
        }
    }

    function scheduleFlowMapPreview() {
        if (flowMapPreviewTimer) {
            clearTimeout(flowMapPreviewTimer);
        }
        flowMapPreviewTimer = window.setTimeout(function () {
            flowMapPreviewTimer = null;
            updateFlowMapPreview();
        }, 80);
    }

    function ensureFlowMapAssets(done) {
        if (window.goongjs) {
            done();
            return;
        }
        if (!window.__goongMaptilesKey) {
            done();
            return;
        }
        if (document.querySelector('script[data-booking-flow-goong]')) {
            window.setTimeout(function () {
                done();
            }, 400);
            return;
        }
        var link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = 'https://cdn.jsdelivr.net/npm/@goongmaps/goong-js@1.0.9/dist/goong-js.css';
        document.head.appendChild(link);
        var script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/@goongmaps/goong-js@1.0.9/dist/goong-js.js';
        script.async = true;
        script.setAttribute('data-booking-flow-goong', '1');
        script.onload = function () { done(); };
        script.onerror = function () { done(); };
        document.body.appendChild(script);
    }

    function openPickupConfirmModal() {
        syncRouteLabels();
        syncScheduleLater();
        syncPickupConfirmUi();
        applyCustomerPrefill();
        ensureBrowserIdOnForm();
        setStep('pickup');
        showBookingFlow();
        ensureFlowMapAssets(function () {
            window.setTimeout(updatePickupMapPreview, 80);
        });
    }

    function openVehicleSelectModal() {
        syncRouteLabels();
        syncScheduleLater();
        applyCustomerPrefill();
        ensureBrowserIdOnForm();
        setStep('vehicle');
        showBookingFlow();
        refreshVehicleCardPrices();
        // Auto-select first vehicle card
        var first = document.querySelector('#trips-list [data-select-vehicle]');
        if (first && !ctx.capacity) {
            selectVehicleCard(first);
        }
    }

    function persistRouteDraft(extra) {
        if (window.BookingRouteDraft && typeof window.BookingRouteDraft.save === 'function') {
            window.BookingRouteDraft.save(extra || {});
        }
    }

    function proceedAfterAuthChecks() {
        if (!validateStep1() || !assertMinTripDistance()) {
            return;
        }
        if (window.BookingRouteDraft) {
            window.BookingRouteDraft.clear();
        }
        // Mở màn xác nhận điểm đón ngay — không chờ fetch (tránh flash home ~0.5s).
        openPickupConfirmModal();

        if (!window.__bookingCheckDuplicateUrl) {
            return;
        }
        fetch(window.__bookingCheckDuplicateUrl, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (data && data.duplicate) {
                    notify(data.message || 'Bạn đang có chuyến chưa hoàn tất.');
                    hideBookingFlow();
                }
            })
            .catch(function () { /* ignore */ });
    }

    function handleRouteReady() {
        // GPS / reverse-geocode trễ có thể bắn lại route-ready khi đã vào flow —
        // không kéo user từ chọn xe về lại xác nhận điểm đón.
        if (isFlowOpen()) {
            return;
        }
        var card = document.getElementById('booking-route-card');
        if (card && card.getAttribute('data-booking-blocked')) {
            notify(card.getAttribute('data-booking-blocked'));
            return;
        }
        if (!validateStep1() || !assertMinTripDistance()) {
            return;
        }
        persistRouteDraft({ resume: true });
        if (card && card.getAttribute('data-can-book') !== '1') {
            var loginUrl = (card && card.getAttribute('data-login-url')) || '/login';
            var contactPhone = customerContactPhone();
            if (contactPhone) {
                loginUrl += (loginUrl.indexOf('?') >= 0 ? '&' : '?') + 'phone=' + encodeURIComponent(contactPhone);
            }
            window.location.href = loginUrl;
            return;
        }
        proceedAfterAuthChecks();
    }

    function restoreRouteDraft() {
        if (!window.BookingRouteDraft) {
            return;
        }
        var draft = window.BookingRouteDraft.load();
        if (!draft) {
            return;
        }
        var ready = window.BookingRouteDraft.applyToForm(draft);
        if (draft.schedule_later) {
            setScheduleLaterEnabled(true);
            syncScheduleLater();
        }
        syncRouteLabels();
        if (!ready || !draft.resume) {
            return;
        }
        window.BookingRouteDraft.save(Object.assign({}, draft, { resume: false }));
        var card = document.getElementById('booking-route-card');
        if (!card || card.getAttribute('data-can-book') !== '1') {
            return;
        }
        window.setTimeout(function () {
            proceedAfterAuthChecks();
        }, 120);
    }

    function validateStep1() {
        syncScheduleLater();

        var pickupDetail = $('modal-pickup-detail');
        var dropoffDetail = $('modal-dropoff-detail');
        if (!pickupDetail || !pickupDetail.value.trim()) {
            notify('Vui lòng cho phép truy cập vị trí hoặc chọn điểm đón trên bản đồ.');
            if (pickupDetail) {
                pickupDetail.focus();
            }
            return false;
        }
        if (!dropoffDetail || !dropoffDetail.value.trim()) {
            notify('Vui lòng chọn điểm trả trên bản đồ.');
            if (dropoffDetail) {
                dropoffDetail.focus();
            }
            return false;
        }
        if (!coordValue('modal-pickup-lat') || !coordValue('modal-pickup-lng')) {
            notify('Vui lòng cho phép truy cập vị trí hoặc ghim đúng vị trí điểm đón trên bản đồ.');
            if (pickupDetail) {
                pickupDetail.focus();
            }
            return false;
        }
        if (!coordValue('modal-dropoff-lat') || !coordValue('modal-dropoff-lng')) {
            notify('Vui lòng ghim đúng vị trí điểm trả trên bản đồ.');
            if (dropoffDetail) {
                dropoffDetail.focus();
            }
            return false;
        }

        if (scheduleLaterEnabled()) {
            var serviceDate = $('modal-service-date');
            if (!serviceDate || !serviceDate.value) {
                notify('Vui lòng chọn ngày đón khi đặt sau.');
                if (serviceDate) {
                    serviceDate.focus();
                }
                return false;
            }
            if (serviceDate.value < todayIso()) {
                notify('Ngày đón phải từ hôm nay trở đi.');
                serviceDate.focus();
                return false;
            }

            var pickupTimeEl = $('modal-pickup-time');
            if (!pickupTimeEl || !pickupTimeEl.value || !/^\d{1,2}:\d{2}$/.test(pickupTimeEl.value)) {
                notify('Vui lòng chọn giờ đón khi đặt sau.');
                if (pickupTimeEl) {
                    pickupTimeEl.focus();
                }
                return false;
            }
        }

        return true;
    }

    function currentPaymentMethod() {
        var hidden = $('booking-payment-method');
        if (hidden && hidden.value) {
            return String(hidden.value);
        }
        var checked = form.querySelector('input[name="payment_method"]:checked');
        return checked ? String(checked.value) : 'cash';
    }

    function setPaymentMethod(value) {
        var next = value === 'bank_transfer' ? 'bank_transfer' : 'cash';
        var hidden = $('booking-payment-method');
        if (hidden) {
            hidden.value = next;
        }
        var label = document.querySelector('[data-pay-label]');
        if (label) {
            label.textContent = next === 'bank_transfer' ? 'Chuyển khoản' : 'Tiền mặt';
        }
        document.querySelectorAll('[data-pay-value]').forEach(function (opt) {
            var active = opt.getAttribute('data-pay-value') === next;
            opt.classList.toggle('is-active', active);
            opt.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        syncBookingTransferQr();
    }

    function setPayDropdownOpen(open) {
        var wrap = document.querySelector('[data-pay-dropdown]');
        var btn = $('booking-pay-method-btn');
        var menu = $('booking-pay-method-menu');
        if (!wrap || !btn || !menu) {
            return;
        }
        var next = !!open;
        wrap.classList.toggle('is-open', next);
        btn.setAttribute('aria-expanded', next ? 'true' : 'false');
        menu.hidden = !next;
    }

    function syncBookingTransferQr() {
        var panel = $('booking-pay-transfer');
        var isTransfer = currentPaymentMethod() === 'bank_transfer';
        if (panel) {
            panel.classList.toggle('d-none', !isTransfer);
        }
        var proof = $('booking-payment-proof');
        if (proof) {
            proof.required = !!isTransfer;
        }
        if (!isTransfer) {
            return;
        }
        var img = $('booking-transfer-qr');
        if (!img) {
            return;
        }
        var amount = 0;
        var selected = document.querySelector('#trips-list [data-select-vehicle].is-selected [data-price-slot]');
        // amount from quote if available via data attribute later; QR works with 0 + addInfo
        var bin = img.getAttribute('data-bank-bin') || '';
        var account = img.getAttribute('data-account') || '';
        var addInfo = img.getAttribute('data-add-info') || '';
        var accountName = img.getAttribute('data-account-name') || '';
        if (!bin || !account) {
            return;
        }
        var params = [];
        if (amount > 0) params.push('amount=' + encodeURIComponent(String(amount)));
        if (addInfo) params.push('addInfo=' + encodeURIComponent(addInfo));
        if (accountName) params.push('accountName=' + encodeURIComponent(accountName));
        var url = 'https://img.vietqr.io/image/' + bin + '-' + account + '-compact2.jpg'
            + (params.length ? ('?' + params.join('&')) : '');
        img.src = url;
        img.hidden = false;
        img.classList.remove('is-hidden');
        var ph = panel.querySelector('[data-deposit-qr-placeholder]');
        if (ph) {
            ph.hidden = true;
            ph.classList.add('is-hidden');
        }
    }

    function validateStep2() {
        // SĐT / họ tên / tuổi / giới tính lấy từ hồ sơ khách khi submit (server).
        if (!ctx.capacity) {
            notify('Vui lòng chọn loại xe.');
            return false;
        }
        if (currentPaymentMethod() === 'bank_transfer') {
            var proof = $('booking-payment-proof');
            if (!proof || !proof.files || !proof.files.length) {
                notify('Vui lòng đính kèm ảnh chuyển khoản.');
                if (proof) proof.focus();
                return false;
            }
        }
        return true;
    }

    if (window.FormFieldValidation) {
        window.FormFieldValidation.bindClearOnInput(form);
    }

    var laterBtn = $('booking-schedule-later-btn')
        || document.querySelector('#booking-route-card [data-schedule-mode="later"]');
    if (laterBtn) {
        laterBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            setScheduleLaterEnabled(!scheduleLaterEnabled());
            syncScheduleLater();
            refreshQuote();
        });
    }

    document.addEventListener('booking:route-ready', function () {
        handleRouteReady();
    });
    var serviceDateEl = $('modal-service-date');
    if (serviceDateEl) {
        serviceDateEl.addEventListener('change', function () {
            syncScheduleChipLabel();
            refreshQuote();
        });
    }
    var pickupTimeEl = $('modal-pickup-time');
    if (pickupTimeEl) {
        pickupTimeEl.addEventListener('change', function () {
            syncScheduleChipLabel();
        });
        pickupTimeEl.addEventListener('input', function () {
            syncScheduleChipLabel();
        });
    }
    syncScheduleLater();
    syncRouteLabels();
    restoreRouteDraft();
    autoFillPickupFromGps();

    document.querySelectorAll('[data-select-vehicle]').forEach(function (card) {
        card.addEventListener('click', function () {
            selectVehicleCard(card);
        });
    });

    bindVehicleSheetHandle();
    syncVehicleListVisibility(false);

    var routeSwapBtn = $('booking-route-swap');
    if (routeSwapBtn) {
        routeSwapBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            swapRouteEnds();
        });
    }

    var confirmPickupBtn = $('booking-confirm-pickup-btn');
    if (confirmPickupBtn) {
        confirmPickupBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            syncNotesHidden();
            if (!validateStep1() || !assertMinTripDistance()) {
                return;
            }
            // Defer sang macrotask để click/touch hiện tại kết thúc trước khi đổi DOM bước.
            window.setTimeout(function () {
                openVehicleSelectModal();
            }, 0);
        });
    }

    var noteToggle = $('booking-note-toggle');
    var noteEditor = $('booking-note-editor');
    var noteTa = $('modal-notes');
    if (noteToggle && noteEditor) {
        noteToggle.addEventListener('click', function () {
            var open = noteEditor.classList.contains('d-none');
            noteEditor.classList.toggle('d-none', !open);
            noteToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            if (open && noteTa) {
                noteTa.focus();
            }
        });
    }
    if (noteTa) {
        noteTa.addEventListener('input', syncNotesHidden);
        noteTa.addEventListener('change', syncNotesHidden);
    }

    var recenterBtn = $('booking-pickup-recenter');
    if (recenterBtn) {
        recenterBtn.addEventListener('click', function () {
            var lat = coordValue('modal-pickup-lat');
            var lng = coordValue('modal-pickup-lng');
            if (lat && lng && pickupMapInstance) {
                placePickupMarker(Number(lat), Number(lng), true);
            }
        });
    }

    (function bindPayDropdown() {
        var btn = $('booking-pay-method-btn');
        var menu = $('booking-pay-method-menu');
        if (!btn || !menu) {
            return;
        }
        btn.addEventListener('click', function () {
            setPayDropdownOpen(btn.getAttribute('aria-expanded') !== 'true');
        });
        menu.querySelectorAll('[data-pay-value]').forEach(function (opt) {
            opt.addEventListener('click', function () {
                setPaymentMethod(opt.getAttribute('data-pay-value'));
                setPayDropdownOpen(false);
            });
        });
        document.addEventListener('click', function (e) {
            var wrap = document.querySelector('[data-pay-dropdown]');
            if (!wrap || wrap.contains(e.target)) {
                return;
            }
            setPayDropdownOpen(false);
        });
        setPaymentMethod(currentPaymentMethod());
    })();

    var payProof = $('booking-payment-proof');
    if (payProof) {
        payProof.addEventListener('change', function () {
            var wrap = $('booking-pay-proof-preview');
            var img = $('booking-pay-proof-preview-img');
            var file = payProof.files && payProof.files[0];
            if (!wrap || !img) return;
            if (wrap.dataset.objectUrl) {
                URL.revokeObjectURL(wrap.dataset.objectUrl);
                delete wrap.dataset.objectUrl;
            }
            if (file && file.type.indexOf('image/') === 0) {
                var url = URL.createObjectURL(file);
                wrap.dataset.objectUrl = url;
                img.src = url;
                wrap.hidden = false;
                wrap.classList.remove('d-none');
            } else {
                img.removeAttribute('src');
                wrap.hidden = true;
                wrap.classList.add('d-none');
            }
        });
    }

    function handleModalBack() {
        if (currentStep() === 'vehicle') {
            setStep('pickup');
            return;
        }
        returnToAddressSheet('pickup');
    }

    document.querySelectorAll('[data-modal-back]').forEach(function (btn) {
        btn.addEventListener('click', handleModalBack);
    });

    document.querySelectorAll('[data-booking-flow-close]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            hideBookingFlow();
        });
    });

    ['modal-contact-phone'].forEach(function (id) {
        var el = $(id);
        if (!el) {
            return;
        }
        el.addEventListener('blur', function () {
            refreshQuote();
            var phone = el.value.trim();
            if (!phone) {
                return;
            }
            checkBookingConstraints(phone).then(function (data) {
                if (data && data.duplicate) {
                    showDuplicateNotice(data.booking);
                }
            });
        });
    });

    form.addEventListener('submit', function (event) {
        if (guardBookingAction()) {
            event.preventDefault();
            return;
        }

        event.preventDefault();
        syncNotesHidden();

        if (!validateStep1()) {
            hideBookingFlow();
            return;
        }

        if (!validateStep2()) {
            setStep('vehicle');
            return;
        }

        ensureBrowserIdOnForm();

        var phone = customerContactPhone();

        checkBookingConstraints(phone).then(function (data) {
            if (data && data.duplicate) {
                showDuplicateNotice(data.booking);
                return;
            }
            submitBookingForm();
        });
    });

    if (window.__bookingRestoreModal && window.__bookingRestoreModal.capacity) {
        var restoreCapacity = window.__bookingRestoreModal.capacity;
        var restoreType = window.__bookingRestoreModal.vehicle_type;
        var restoreSelector = '[data-select-vehicle][data-capacity="' + restoreCapacity + '"]'
            + (restoreType ? '[data-vehicle-type="' + restoreType + '"]' : '');
        var restoreBtn = document.querySelector(restoreSelector)
            || document.querySelector('[data-select-vehicle][data-capacity="' + restoreCapacity + '"]');
        if (restoreBtn) {
            openPickupConfirmModal();
            window.setTimeout(function () {
                openVehicleSelectModal();
                selectVehicleCard(restoreBtn);
            }, 80);
        }
    }

    function syncRouteSlotHints() {
        var pickupInput = $('modal-pickup-detail');
        var dropoffInput = $('modal-dropoff-detail');
        var dropoffVal = dropoffInput ? String(dropoffInput.value || '').trim() : '';
        var homeLabel = document.querySelector('[data-home-dest-label]');
        if (homeLabel) {
            homeLabel.textContent = dropoffVal || 'Bạn muốn đi đâu?';
            homeLabel.classList.toggle('has-value', !!dropoffVal);
        }
        if (pickupInput) {
            /* keep for quote readiness */
        }
    }

    document.addEventListener('addressmap:applied', function (event) {
        var detail = event.detail || {};
        var province = detail.province ? String(detail.province).trim() : '';
        var targetId = detail.targetInputId || '';

        if (targetId === 'modal-pickup-detail' && province) {
            var pickupAddr = $('modal-pickup-address');
            if (pickupAddr) {
                pickupAddr.value = province;
            }
        } else if (targetId === 'modal-dropoff-detail' && province) {
            var dropoffAddr = $('modal-dropoff-address');
            if (dropoffAddr) {
                dropoffAddr.value = province;
            }
        }

        syncRouteSlotHints();
        refreshDistanceAndQuote();
        if (currentStep() === 'pickup') {
            syncPickupConfirmUi();
            updatePickupMapPreview();
        } else if (currentStep() === 'vehicle') {
            scheduleFlowMapPreview();
        }
    });

    ['modal-pickup-lat', 'modal-pickup-lng', 'modal-dropoff-lat', 'modal-dropoff-lng',
        'modal-pickup-detail', 'modal-dropoff-detail'].forEach(function (id) {
        var el = $(id);
        if (!el) {
            return;
        }
        el.addEventListener('change', function () {
            syncRouteSlotHints();
            refreshDistanceAndQuote();
        });
    });

    syncRouteSlotHints();
})();
