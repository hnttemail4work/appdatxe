/**
 * Đặt xe — chọn xe/tài xế ngoài trang, ghim điểm đi/đến trên bản đồ, báo giá khi đặt.
 */
(function () {
    var modalEl = document.getElementById('bookingModal');
    var form = document.getElementById('booking-form');
    if (!modalEl || !form) {
        return;
    }

    var modal = typeof bootstrap !== 'undefined' ? new bootstrap.Modal(modalEl) : null;
    var ctx = {};
    var quoteTimer = null;

    function $(id) { return document.getElementById(id); }

    function notify(message, options) {
        if (window.AppDialog && window.AppDialog.alert) {
            window.AppDialog.alert(message, options || { variant: 'warning', title: 'Chưa thể đặt chuyến' });
        } else {
            window.alert(message);
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
            notify('Đã hủy quá nhiều lần trên trình duyệt này. Đóng tab hoặc trình duyệt (hết phiên) rồi mở lại để đặt cuốc mới.');
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
        syncDepartureDate();
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
            totalEl.textContent = displayTotal > 0 ? formatMoney(displayTotal) : '';
            totalEl.classList.toggle('d-none', displayTotal <= 0);
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
                totalEl.textContent = 'Đang tính…';
                totalEl.classList.remove('d-none');
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

    function setStep(step) {
        var s1 = $('booking-step-1');
        var s2 = $('booking-step-2');
        var f1 = $('modal-footer-step1');
        var f2 = $('modal-footer-step2');
        var tripBanner = document.querySelector('#bookingModal .booking-trip-banner');
        if (s1) s1.classList.toggle('d-none', step !== 1);
        if (s2) s2.classList.toggle('d-none', step !== 2);
        if (f1) f1.classList.toggle('d-none', step !== 1);
        if (f2) f2.classList.toggle('d-none', step !== 2);
        if (tripBanner) {
            tripBanner.classList.toggle('d-none', step === 2);
        }
        document.querySelectorAll('.booking-step').forEach(function (el) {
            el.classList.toggle('active', Number(el.dataset.step) === step);
        });
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

    function quoteParams() {
        var route = modalRoute();
        var params = new URLSearchParams({
            pickup_address: route.pickup,
            dropoff_address: route.dropoff,
            pickup_detail: route.pickupDetail,
            dropoff_detail: route.dropoffDetail,
            departure_plan: departurePlan(),
            contact_phone: $('modal-contact-phone') ? $('modal-contact-phone').value : '',
        });
        if (departurePlan() === 'later') {
            params.set('later_return_days', String(laterReturnDays()));
        }
        ['modal-pickup-lat', 'modal-pickup-lng', 'modal-dropoff-lat', 'modal-dropoff-lng'].forEach(function (id) {
            var value = coordValue(id);
            if (value) {
                params.set(id.replace('modal-', '').replace(/-/g, '_'), value);
            }
        });
        if (ctx.driverProfileId) {
            params.set('driver_profile_id', ctx.driverProfileId);
        }
        if (ctx.vehicleId) {
            params.set('vehicle_id', ctx.vehicleId);
        }
        if (ctx.templateId) {
            params.set('template_id', ctx.templateId);
        }
        return params;
    }

    function refreshQuote() {
        if (!window.__quotePriceUrl || (!ctx.driverProfileId && !ctx.vehicleId && !ctx.templateId)) {
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
                    var total = data.total_after_discount != null ? data.total_after_discount : data.whole_car_price;
                    updatePriceDisplay(total, data);
                })
                .catch(function () {
                    updatePriceDisplay(0);
                    if (coordsReady()) {
                        syncDistanceLabel(0);
                    }
                })
                .finally(function () {
                    setPriceLoading(false);
                });
        }, 150);
    }

    function todayIso() {
        return window.__todayDate || new Date().toISOString().slice(0, 10);
    }

    function tomorrowIso() {
        var base = window.__todayDate ? new Date(window.__todayDate + 'T12:00:00') : new Date();
        base.setDate(base.getDate() + 1);
        return base.toISOString().slice(0, 10);
    }

    function departurePlan() {
        var checked = document.querySelector('input[name="departure_plan"]:checked');
        return checked ? checked.value : 'oneway';
    }

    function laterReturnDays() {
        var el = $('modal-later-return-days');
        var min = Number(window.__laterReturnDaysMin || 3);
        var max = Number(window.__laterReturnDaysMax || 60);
        var raw = el ? parseInt(el.value, 10) : min;
        if (!raw || raw < min) {
            return min;
        }
        return Math.min(raw, max);
    }

    function syncLaterReturnDaysHint() {
        var hint = $('modal-later-return-days-hint');
        if (!hint) {
            return;
        }
        var pct = Number(window.__laterReturnPercentPerDay || 0);
        var days = laterReturnDays();
        hint.textContent = 'Từ ' + (window.__laterReturnDaysMin || 3) + ' ngày. Mỗi ngày +' + pct + '% giá chuyến'
            + (days > 0 && pct > 0 ? ' (≈ +' + Math.round(days * pct) + '% cho ' + days + ' ngày).' : '.');
    }

    function syncDepartureDate() {
        var dateEl = $('modal-service-date');
        if (!dateEl) {
            return;
        }
        var plan = departurePlan();
        if (plan === 'later') {
            var base = window.__todayDate ? new Date(window.__todayDate + 'T12:00:00') : new Date();
            base.setDate(base.getDate() + laterReturnDays());
            dateEl.value = base.toISOString().slice(0, 10);
        } else if (plan === 'tomorrow') {
            dateEl.value = tomorrowIso();
        } else {
            dateEl.value = todayIso();
        }
    }

    function syncDeparturePlanUI() {
        var wrap = $('modal-later-return-days-wrap');
        var plan = departurePlan();
        if (wrap) {
            wrap.classList.toggle('d-none', plan !== 'later');
        }
        syncLaterReturnDaysHint();
        syncDepartureDate();
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
            openBookingModal(btn);
        });
    }

    function openBookingModal(btn) {
        ctx = {
            driverProfileId: btn.dataset.driverProfileId || '',
            vehicleId: btn.dataset.vehicleId || '',
            templateId: btn.dataset.templateId || '',
            licensePlate: btn.dataset.licensePlate || '',
            capacityLabel: btn.dataset.capacityLabel || '',
            typeLabel: btn.dataset.typeLabel || '',
            vehiclePhoto: btn.dataset.vehiclePhoto || '',
            driverName: btn.dataset.driverName || '',
            offerLabel: btn.dataset.offerLabel || '',
        };

        if ($('modal-template-id')) {
            $('modal-template-id').value = ctx.templateId;
        }
        if ($('modal-vehicle-id')) {
            $('modal-vehicle-id').value = ctx.vehicleId;
        }
        if ($('modal-driver-profile-id')) {
            $('modal-driver-profile-id').value = ctx.driverProfileId;
        }

        syncRouteLabels();
        syncDeparturePlanUI();

        var vehicleMeta = ctx.offerLabel || [ctx.driverName, ctx.licensePlate, ctx.typeLabel, ctx.capacityLabel]
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

        var pickupTimeEl = $('modal-pickup-time');
        if (pickupTimeEl && window.__defaultPickupTime && !pickupTimeEl.value) {
            pickupTimeEl.value = window.__defaultPickupTime;
        }

        updatePriceDisplay(0);
        setStep(1);
        ensureBrowserIdOnForm();
        refreshQuote();
        if (modal) modal.show();
    }

    function validateStep1() {
        syncDepartureDate();

        var pickupDetail = $('modal-pickup-detail');
        var dropoffDetail = $('modal-dropoff-detail');
        if (!pickupDetail || !pickupDetail.value.trim()) {
            alert('Vui lòng chọn điểm đón trên bản đồ.');
            if (pickupDetail) {
                pickupDetail.focus();
            }
            return false;
        }
        if (!dropoffDetail || !dropoffDetail.value.trim()) {
            alert('Vui lòng chọn điểm trả trên bản đồ.');
            if (dropoffDetail) {
                dropoffDetail.focus();
            }
            return false;
        }
        if (!coordValue('modal-pickup-lat') || !coordValue('modal-pickup-lng')) {
            notify('Vui lòng ghim đúng vị trí điểm đón trên bản đồ.');
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

        if (departurePlan() === 'later') {
            var daysInput = $('modal-later-return-days');
            var minDays = Number(window.__laterReturnDaysMin || 3);
            if (!daysInput || laterReturnDays() < minDays) {
                alert('Vui lòng nhập số ngày chờ về (tối thiểu ' + minDays + ' ngày).');
                if (daysInput) {
                    daysInput.focus();
                }
                return false;
            }
        }

        var route = modalRoute();
        if (!route.pickup || !route.dropoff) {
            syncDepartureDate();
        }

        return true;
    }

    document.querySelectorAll('input[name="departure_plan"]').forEach(function (el) {
        el.addEventListener('change', function () {
            syncDeparturePlanUI();
            refreshQuote();
        });
    });
    var laterDaysEl = $('modal-later-return-days');
    if (laterDaysEl) {
        laterDaysEl.addEventListener('input', function () {
            syncDeparturePlanUI();
            refreshQuote();
        });
        laterDaysEl.addEventListener('change', function () {
            syncDeparturePlanUI();
            refreshQuote();
        });
    }
    syncDeparturePlanUI();
    syncRouteLabels();

    document.querySelectorAll('[data-open-booking]').forEach(function (btn) {
        btn.addEventListener('click', function () { openFromButton(btn); });
    });

    var nextBtn = $('modal-next-btn');
    if (nextBtn) {
        nextBtn.addEventListener('click', function () {
            if (!validateStep1()) return;
            setStep(2);
            refreshQuote();
        });
    }

    var backBtn = $('modal-back-btn');
    if (backBtn) {
        backBtn.addEventListener('click', function () { setStep(1); });
    }

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
            setStep(1);
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

    if (window.__bookingRestoreModal && (window.__bookingRestoreModal.driver_profile_id || window.__bookingRestoreModal.vehicle_id || window.__bookingRestoreModal.template_id)) {
        var restoreId = window.__bookingRestoreModal.driver_profile_id
            || window.__bookingRestoreModal.vehicle_id
            || window.__bookingRestoreModal.template_id;
        var restoreBtn = document.querySelector('[data-open-booking][data-driver-profile-id="' + restoreId + '"]')
            || document.querySelector('[data-open-booking][data-vehicle-id="' + restoreId + '"]')
            || document.querySelector('[data-open-booking][data-template-id="' + restoreId + '"]');
        if (restoreBtn) {
            openFromButton(restoreBtn);
            if (window.__bookingRestoreModal.step === 2) setStep(2);
        }
    }

    modalEl.addEventListener('hidden.bs.modal', function () {
        setStep(1);
    });

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
