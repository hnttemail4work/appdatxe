/**
 * Đặt xe — chọn xe/tài xế ngoài trang, nhập tuyến trong modal, báo giá khi đặt.
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

    var modalPickup = document.getElementById('modal-pickup');
    var modalDropoff = document.getElementById('modal-dropoff');

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
        ensureBrowserIdOnForm();
        form.submit();
    }

    function formatMoney(n) {
        return (Number(n) || 0).toLocaleString('vi-VN') + ' đ';
    }

    function modalRoute() {
        return {
            pickup: modalPickup ? modalPickup.value.trim() : '',
            dropoff: modalDropoff ? modalDropoff.value.trim() : '',
        };
    }

    function routeLabel(pickup, dropoff) {
        if (!pickup || !dropoff) {
            return '';
        }
        return pickup + ' → ' + dropoff;
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

    function isIntraCityRoute(route) {
        return !!(route.pickup && route.dropoff && route.pickup === route.dropoff);
    }

    function routeReady() {
        var route = modalRoute();
        return !!(route.pickup && route.dropoff);
    }

    function syncRouteLabels() {
        var route = modalRoute();
        var label = routeLabel(route.pickup, route.dropoff);
        setBannerText('modal-route', label);
        setBannerText('modal-route-step2', label);
        syncIntraCityRequiredUI(route);
    }

    function syncIntraCityRequiredUI(route) {
        route = route || modalRoute();
        var intraCity = isIntraCityRoute(route);
        var dropoffDetail = $('modal-dropoff-detail');
        var dropoffRequired = $('modal-dropoff-required');
        var hint = $('modal-intracity-hint');

        if (dropoffDetail) {
            if (intraCity) {
                dropoffDetail.setAttribute('required', 'required');
            } else {
                dropoffDetail.removeAttribute('required');
            }
        }
        if (dropoffRequired) {
            dropoffRequired.classList.toggle('d-none', !intraCity);
        }
        if (hint) {
            hint.classList.toggle('d-none', !intraCity);
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

        if (hasDiscount) {
            var pct = data.referral_discount_percent;
            discountNote = 'Giảm ' + formatMoney(data.referral_discount_amount);
            if (pct > 0) {
                discountNote += ' (' + pct + '% — mã QR)';
            }
        }

        [['modal-original-price-step1', 'modal-total-price-step1', 'modal-referral-discount-step1'],
         ['modal-original-price', 'modal-total-price', 'modal-referral-discount']].forEach(function (group) {
            var origEl = $(group[0]);
            var totalEl = $(group[1]);
            var discEl = $(group[2]);

            if (origEl) {
                if (hasDiscount && subtotal > 0) {
                    origEl.textContent = formatMoney(subtotal);
                    origEl.classList.remove('d-none');
                } else {
                    origEl.textContent = '';
                    origEl.classList.add('d-none');
                }
            }

            if (totalEl) {
                var displayTotal = hasDiscount ? finalTotal : total;
                totalEl.textContent = displayTotal > 0 ? formatMoney(displayTotal) : '';
                totalEl.classList.toggle('d-none', displayTotal <= 0);
                totalEl.classList.toggle('booking-price-discounted', hasDiscount && displayTotal > 0);
            }

            if (discEl) {
                if (hasDiscount && discountNote) {
                    discEl.textContent = discountNote;
                    discEl.classList.remove('d-none');
                } else {
                    discEl.textContent = '';
                    discEl.classList.add('d-none');
                }
            }
        });
    }

    function quoteParams() {
        var route = modalRoute();
        var params = new URLSearchParams({
            pickup_address: route.pickup,
            dropoff_address: route.dropoff,
            contact_phone: $('modal-contact-phone') ? $('modal-contact-phone').value : '',
        });
        var pickupLat = coordValue('modal-pickup-lat');
        var pickupLng = coordValue('modal-pickup-lng');
        var dropoffLat = coordValue('modal-dropoff-lat');
        var dropoffLng = coordValue('modal-dropoff-lng');
        if (pickupLat) {
            params.set('pickup_lat', pickupLat);
        }
        if (pickupLng) {
            params.set('pickup_lng', pickupLng);
        }
        if (dropoffLat) {
            params.set('dropoff_lat', dropoffLat);
        }
        if (dropoffLng) {
            params.set('dropoff_lng', dropoffLng);
        }
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
        var route = modalRoute();
        if (!window.__quotePriceUrl || (!ctx.driverProfileId && !ctx.vehicleId) || !route.pickup || !route.dropoff) {
            updatePriceDisplay(0);
            return;
        }
        if (isIntraCityRoute(route)) {
            if (!coordValue('modal-pickup-lat') || !coordValue('modal-pickup-lng')
                || !coordValue('modal-dropoff-lat') || !coordValue('modal-dropoff-lng')) {
                updatePriceDisplay(0);
                return;
            }
        }
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
                });
        }, 200);
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
        var route = modalRoute();
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

        if ($('modal-service-date') && window.__defaultServiceDate && !$('modal-service-date').value) {
            $('modal-service-date').value = window.__defaultServiceDate;
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
        var route = modalRoute();
        if (!route.pickup || !route.dropoff) {
            alert('Vui lòng chọn điểm đi và điểm đến.');
            if (modalPickup && !route.pickup) {
                modalPickup.focus();
            } else if (modalDropoff) {
                modalDropoff.focus();
            }
            return false;
        }
        var date = $('modal-service-date');
        var pickupTime = $('modal-pickup-time');
        var pickupDetail = $('modal-pickup-detail');
        if (!date || !date.value) {
            alert('Vui lòng chọn ngày đi.');
            if (date) {
                date.focus();
            }
            return false;
        }
        if (!pickupTime || !pickupTime.value) {
            alert('Vui lòng chọn giờ đón.');
            if (pickupTime) {
                pickupTime.focus();
            }
            return false;
        }
        if (!pickupDetail || !pickupDetail.value.trim()) {
            var pickupMsg = isIntraCityRoute(route)
                ? 'Cùng tỉnh/thành — vui lòng chọn điểm đón cụ thể trên bản đồ.'
                : 'Vui lòng chọn điểm đón cụ thể trên bản đồ.';
            alert(pickupMsg);
            if (pickupDetail) {
                pickupDetail.focus();
            }
            return false;
        }
        var pickupLat = $('modal-pickup-lat');
        var pickupLng = $('modal-pickup-lng');
        if (!pickupLat || !pickupLng || !pickupLat.value || !pickupLng.value) {
            notify('Vui lòng bấm ô điểm đón và ghim đúng vị trí trên bản đồ.');
            if (pickupDetail) {
                pickupDetail.focus();
            }
            return false;
        }
        if (isIntraCityRoute(route)) {
            var dropoffDetail = $('modal-dropoff-detail');
            var dropoffLat = $('modal-dropoff-lat');
            var dropoffLng = $('modal-dropoff-lng');
            if (!dropoffDetail || !dropoffDetail.value.trim()) {
                alert('Cùng tỉnh/thành — vui lòng chọn điểm trả cụ thể trên bản đồ.');
                if (dropoffDetail) {
                    dropoffDetail.focus();
                }
                return false;
            }
            if (!dropoffLat || !dropoffLng || !dropoffLat.value || !dropoffLng.value) {
                notify('Cùng tỉnh/thành — vui lòng chọn điểm trả trên bản đồ.');
                if (dropoffDetail) {
                    dropoffDetail.focus();
                }
                return false;
            }
        }
        return true;
    }

    function clearAddressField(prefix) {
        var detailId = prefix + '-detail';
        var detail = $(detailId);
        if (detail) {
            detail.value = '';
        }
        clearAddressCoords(prefix);
    }

    function clearAddressCoords(prefix) {
        var lat = $(prefix + '-lat');
        var lng = $(prefix + '-lng');
        if (lat) {
            lat.value = '';
        }
        if (lng) {
            lng.value = '';
        }
    }

    [modalPickup, modalDropoff].forEach(function (el) {
        if (!el) return;
        el.addEventListener('change', function () {
            if (el.id === 'modal-pickup') {
                clearAddressField('modal-pickup');
            } else if (el.id === 'modal-dropoff') {
                clearAddressField('modal-dropoff');
            }
            syncRouteLabels();
            refreshQuote();
        });
    });
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

    ['modal-pickup-detail', 'modal-dropoff-detail', 'modal-contact-phone'].forEach(function (id) {
        var el = $(id);
        if (!el) {
            return;
        }
        el.addEventListener('blur', function () {
            refreshQuote();
            if (id !== 'modal-contact-phone') {
                return;
            }
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

    document.addEventListener('addressmap:applied', function () {
        refreshQuote();
    });
})();
