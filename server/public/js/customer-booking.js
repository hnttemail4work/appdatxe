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
        setStep(1);
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
        var bannerText = distance > 0 ? ('Khoảng ' + formatDistanceKm(distance)) : '';
        var footerText = distance > 0 ? formatDistanceKm(distance) : '';
        setBannerText('modal-route-distance', bannerText);
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

    function setPriceSummaryBlock(suffix, hasDiscount, subtotal, displayTotal, discountNote) {
        var origRow = $('modal-original-row-' + suffix);
        var discRow = $('modal-discount-row-' + suffix);
        var origEl = $('modal-original-price-' + suffix);
        var discEl = $('modal-referral-discount-' + suffix);
        var totalEl = suffix === 'step1' ? $('modal-total-price-step1') : $('modal-total-price');

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
        var label = routeLabel();
        setBannerText('modal-route', label);
        setBannerText('modal-route-step2', label);
        if (!coordsReady()) {
            syncDistanceLabel(0);
        }
    }

    function currentStep() {
        return $('booking-step-2') && !$('booking-step-2').classList.contains('d-none') ? 2 : 1;
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
        var s1 = $('booking-step-1');
        var s2 = $('booking-step-2');
        var f1 = $('modal-footer-step1');
        var f2 = $('modal-footer-step2');
        var tripBanner = flowEl.querySelector('.booking-trip-banner');
        if (s1) s1.classList.toggle('d-none', step !== 1);
        if (s2) s2.classList.toggle('d-none', step !== 2);
        if (f1) f1.classList.toggle('d-none', step !== 1);
        if (f2) f2.classList.toggle('d-none', step !== 2);
        if (tripBanner) {
            tripBanner.classList.toggle('d-none', step === 2);
        }
        flowEl.querySelectorAll('.booking-step').forEach(function (el) {
            el.classList.toggle('active', Number(el.dataset.step) === step);
        });
        scrollFlowBodyToTop();
    }

    function updatePriceDisplay(total, data) {
        var hasDiscount = !!(data && data.referral_eligible && data.referral_discount_amount > 0);
        var subtotal = hasDiscount ? Number(data.subtotal || data.whole_car_price || 0) : 0;
        var finalTotal = total > 0 ? total : (hasDiscount ? Number(data.total_after_discount || 0) : 0);
        var discountNote = '';

        if (data && data.distance_km) {
            syncDistanceLabel(data.distance_km);
        } else if (routeReady()) {
            syncDistanceLabel(0);
        }

        if (hasDiscount) {
            var pct = data.referral_discount_percent;
            var sourceLabel = data.referral_discount_label || window.__referralDiscountLabel || 'giới thiệu';
            discountNote = '−' + formatMoney(data.referral_discount_amount);
            if (pct > 0) {
                discountNote += ' (' + formatReferralPercent(pct) + '% — ' + sourceLabel + ')';
            }
        }

        var displayTotal = hasDiscount ? finalTotal : total;
        ['step1', 'step2'].forEach(function (suffix) {
            setPriceSummaryBlock(suffix, hasDiscount, subtotal, displayTotal, discountNote);
        });
    }

    function quoteParams(overrideCapacity, overrideVehicleType) {
        var route = modalRoute();
        var params = new URLSearchParams({
            pickup_address: route.pickup,
            dropoff_address: route.dropoff,
            pickup_detail: route.pickupDetail,
            dropoff_detail: route.dropoffDetail,
            contact_phone: $('modal-contact-phone') ? $('modal-contact-phone').value : '',
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
                        priceEl.textContent = '';
                        return;
                    }
                    var total = data.total_after_discount != null ? data.total_after_discount : data.whole_car_price;
                    priceEl.textContent = total > 0 ? formatMoney(total) : '';
                })
                .catch(function () {
                    if (String(requestId) === card.getAttribute('data-price-req')) {
                        priceEl.textContent = '';
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
        var chip = $('booking-schedule-later');
        return !!(chip && chip.getAttribute('aria-pressed') === 'true');
    }

    function setScheduleLaterEnabled(enabled) {
        var chip = $('booking-schedule-later');
        if (!chip) {
            return;
        }
        chip.setAttribute('aria-pressed', enabled ? 'true' : 'false');
        chip.classList.toggle('is-active', !!enabled);
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
        var chip = $('booking-schedule-later');
        if (!chip) {
            return;
        }
        var labelEl = chip.querySelector('[data-schedule-chip-label]');
        if (!labelEl) {
            return;
        }
        if (!scheduleLaterEnabled()) {
            labelEl.textContent = 'Đặt sau';
            return;
        }
        var dateEl = $('modal-service-date');
        var timeEl = $('modal-pickup-time');
        labelEl.textContent = formatScheduleChipLabel(
            dateEl ? dateEl.value : '',
            timeEl ? timeEl.value : '',
        );
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

    function applyCustomerPrefill() {
        var prefill = window.__customerBookingPrefill;
        if (!prefill || typeof prefill !== 'object') {
            return;
        }

        var nameEl = $('modal-passenger-name');
        if (nameEl && !nameEl.value.trim() && prefill.passenger_name) {
            nameEl.value = String(prefill.passenger_name);
        }

        var phoneEl = $('modal-contact-phone');
        if (phoneEl && !phoneEl.value.trim() && prefill.contact_phone) {
            phoneEl.value = String(prefill.contact_phone);
        }

        if (prefill.passenger_gender) {
            var genderEl = form.querySelector('input[name="passenger_gender"][value="' + prefill.passenger_gender + '"]');
            if (genderEl) {
                genderEl.checked = true;
            }
        }

        var ageEl = $('modal-passenger-age');
        if (ageEl && !ageEl.value.trim() && prefill.passenger_age != null && prefill.passenger_age !== '') {
            ageEl.value = String(prefill.passenger_age);
        }
    }

    function openBookingModal(btn) {
        ctx = {
            capacity: btn.dataset.capacity || '',
            vehicleType: btn.dataset.vehicleType || '',
            capacityLabel: btn.dataset.capacityLabel || '',
            typeLabel: btn.dataset.typeLabel || '',
            vehiclePhoto: btn.dataset.vehiclePhoto || '',
            offerLabel: btn.dataset.offerLabel || '',
        };

        if ($('modal-capacity')) {
            $('modal-capacity').value = ctx.capacity;
        }
        if ($('modal-vehicle-type')) {
            $('modal-vehicle-type').value = ctx.vehicleType;
        }

        syncRouteLabels();
        syncScheduleLater();

        var vehicleMeta = ctx.offerLabel || [ctx.typeLabel, ctx.capacityLabel]
            .filter(Boolean)
            .join(' - ');
        setBannerText('modal-vehicle-step2', vehicleMeta);
        setBannerText('modal-vehicle-meta', vehicleMeta);

        var photoWrap = $('modal-vehicle-photo-wrap');
        if (photoWrap) {
            if (ctx.vehiclePhoto) {
                photoWrap.classList.remove('d-none');
                photoWrap.innerHTML = '<img src="' + ctx.vehiclePhoto + '" alt="" class="trip-vehicle-photo">';
            } else {
                photoWrap.classList.add('d-none');
                photoWrap.innerHTML = '';
            }
        }

        var step2PhotoWrap = $('modal-step2-vehicle-photo-wrap');
        if (step2PhotoWrap) {
            if (ctx.vehiclePhoto) {
                step2PhotoWrap.classList.remove('d-none');
                step2PhotoWrap.removeAttribute('aria-hidden');
                step2PhotoWrap.innerHTML = '<img src="' + ctx.vehiclePhoto + '" alt="" class="trip-vehicle-photo">';
            } else {
                step2PhotoWrap.classList.add('d-none');
                step2PhotoWrap.setAttribute('aria-hidden', 'true');
                step2PhotoWrap.innerHTML = '';
            }
        }

        syncScheduleLater();

        updatePriceDisplay(0);
        setStep(2);
        applyCustomerPrefill();
        ensureBrowserIdOnForm();
        showBookingFlow();
        refreshQuote();
    }

    function openVehicleSelectModal() {
        syncRouteLabels();
        syncScheduleLater();
        setStep(1);
        showBookingFlow();
        refreshVehicleCardPrices();
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

    function validateStep2() {
        var step2 = $('booking-step-2');
        if (!step2) {
            return false;
        }

        if (window.FormFieldValidation) {
            var result = window.FormFieldValidation.validate(step2, {
                selector: '#modal-passenger-name, #modal-contact-phone',
                message: function (label) {
                    return 'Vui lòng nhập ' + label.toLowerCase();
                },
            });
            if (!result.valid) {
                notify(result.message, { variant: 'warning', title: 'Thiếu thông tin' });
                return false;
            }
            return true;
        }

        var nameEl = $('modal-passenger-name');
        var phoneEl = $('modal-contact-phone');
        if (!nameEl || !nameEl.value.trim()) {
            notify('Vui lòng nhập tên.');
            if (nameEl) {
                nameEl.focus();
            }
            return false;
        }
        if (!phoneEl || !phoneEl.value.trim()) {
            notify('Vui lòng nhập số điện thoại.');
            if (phoneEl) {
                phoneEl.focus();
            }
            return false;
        }

        return true;
    }

    if (window.FormFieldValidation) {
        window.FormFieldValidation.bindClearOnInput(form);
    }

    var scheduleLaterChip = $('booking-schedule-later');
    if (scheduleLaterChip) {
        scheduleLaterChip.addEventListener('click', function () {
            setScheduleLaterEnabled(!scheduleLaterEnabled());
            syncScheduleLater();
            refreshQuote();
        });
    }
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
    autoFillPickupFromGps();

    document.querySelectorAll('[data-open-booking]').forEach(function (btn) {
        btn.addEventListener('click', function () { openFromButton(btn); });
    });

    var routeContinueBtn = $('route-continue-btn');
    if (routeContinueBtn) {
        routeContinueBtn.addEventListener('click', function () {
            if (guardBookingAction()) {
                return;
            }
            if (!validateStep1()) {
                return;
            }
            checkBookingConstraints('').then(function (data) {
                if (data && data.duplicate) {
                    showDuplicateNotice(data.booking);
                    return;
                }
                openVehicleSelectModal();
            });
        });
    }

    function handleModalBack() {
        if (currentStep() === 1) {
            hideBookingFlow();
            return;
        }
        setStep(1);
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

        if (!validateStep1()) {
            hideBookingFlow();
            return;
        }

        if (!validateStep2()) {
            setStep(2);
            return;
        }

        ensureBrowserIdOnForm();

        var phoneEl = $('modal-contact-phone');
        var phone = phoneEl ? phoneEl.value.trim() : '';

        checkBookingConstraints(phone).then(function (data) {
            if (data && data.duplicate) {
                showDuplicateNotice(data.booking);
                if (phoneEl && data.reason === 'phone') {
                    phoneEl.focus();
                }
                return;
            }
            submitBookingForm();
        });
    });

    if (window.__bookingRestoreModal && window.__bookingRestoreModal.capacity) {
        var restoreCapacity = window.__bookingRestoreModal.capacity;
        var restoreType = window.__bookingRestoreModal.vehicle_type;
        var restoreSelector = '[data-open-booking][data-capacity="' + restoreCapacity + '"]'
            + (restoreType ? '[data-vehicle-type="' + restoreType + '"]' : '');
        var restoreBtn = document.querySelector(restoreSelector)
            || document.querySelector('[data-open-booking][data-capacity="' + restoreCapacity + '"]');
        if (restoreBtn) {
            openFromButton(restoreBtn);
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

        refreshDistanceAndQuote();
    });

    ['modal-pickup-lat', 'modal-pickup-lng', 'modal-dropoff-lat', 'modal-dropoff-lng',
        'modal-pickup-detail', 'modal-dropoff-detail'].forEach(function (id) {
        var el = $(id);
        if (!el) {
            return;
        }
        el.addEventListener('change', refreshDistanceAndQuote);
    });
})();
