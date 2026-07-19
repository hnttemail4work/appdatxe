/**
 * Trang Chuyến — theo dõi chuyến đã đặt và đánh giá sau khi hoàn tất.
 */
(function () {
    var selectedSentiment = '';
    var currentBooking = null;
    var COMPLETION_SHOWN_PREFIX = 'guest_trip_completion_shown:';

    function qs(sel, root) {
        return (root || document).querySelector(sel);
    }

    function setText(el, text, visible) {
        if (!el) {
            return;
        }
        if (!visible || !text) {
            el.textContent = '';
            el.classList.add('d-none');
            return;
        }
        el.textContent = text;
        el.classList.remove('d-none');
    }

    function setWrapText(wrap, el, text) {
        if (!wrap) {
            return;
        }
        if (!text) {
            wrap.classList.add('d-none');
            if (el) {
                el.textContent = '';
            }
            return;
        }
        wrap.classList.remove('d-none');
        if (el) {
            el.textContent = text;
        }
    }

    function setDetailRow(card, wrapField, valueField, text) {
        var wrap = qs('[data-field="' + wrapField + '"]', card);
        var el = qs('[data-field="' + valueField + '"]', card);
        if (!wrap || !el) {
            return;
        }
        var show = !!(text !== null && text !== undefined && String(text).trim() !== '');
        el.textContent = show ? String(text) : '';
        wrap.classList.toggle('d-none', !show);
    }

    function showGuestDriverProximity(booking) {
        if (!booking) {
            return false;
        }
        if (booking.trip_status === 'completed' || booking.trip_status === 'cancelled') {
            return false;
        }
        if (!booking.has_driver) {
            return false;
        }
        if (!booking.driver) {
            return true;
        }
        var stage = String(booking.driver.stage || 'assigned');
        return stage === 'assigned' || stage === 'at_pickup';
    }

    /** Khoảng cách — khi TX đã nhận, chưa tới «Đến điểm đón». */
    function showGuestDriverDistance(booking) {
        if (!showGuestDriverProximity(booking)) {
            return false;
        }
        return !!(booking.driver_distance_label
            || (booking.driver && booking.driver.distance_label)
            || booking.driver_distance_line
            || (booking.driver && booking.driver.distance_line));
    }

    /** ETA — chỉ sau khi TX bấm «Xác nhận», chưa «Đến điểm đón». */
    function showGuestDriverEta(booking) {
        if (!showGuestDriverLiveProximity(booking)) {
            return false;
        }
        var confirmed = booking.driver_movement_confirmed
            || (booking.driver && booking.driver.movement_confirmed);
        return !!confirmed;
    }

    /** Khoảng cách / ETA — chỉ khi TX đang đi đón, chưa bấm «Đến điểm đón». */
    function showGuestDriverLiveProximity(booking) {
        if (!showGuestDriverProximity(booking)) {
            return false;
        }
        if (!booking.driver) {
            return false;
        }
        return String(booking.driver.stage || 'assigned') === 'assigned';
    }

    function isScheduledPickup(booking) {
        if (!booking) {
            return false;
        }
        if (typeof booking.is_scheduled_pickup === 'boolean') {
            return booking.is_scheduled_pickup;
        }
        return !!(booking.pickup_time_label);
    }

    function serviceDateLabel(booking) {
        if (!isScheduledPickup(booking)) {
            return '';
        }
        if (booking.service_date_label) {
            return booking.service_date_label;
        }
        if (booking.service_date && booking.service_date !== 'Đón ngay') {
            return String(booking.service_date).split(' ')[0];
        }
        return '';
    }

    function driverDistanceGuestLabel(booking) {
        if (booking.driver_distance_label) {
            return booking.driver_distance_label;
        }
        if (!booking.driver_location_shared) {
            return '';
        }
        var line = booking.driver_distance_line || '';
        if (line.indexOf('Tài xế cách bạn ') === 0) {
            return line.replace('Tài xế cách bạn ', '');
        }
        return line || '';
    }

    function driverEtaGuestLabel(booking) {
        if (booking.driver_eta_label) {
            return booking.driver_eta_label;
        }
        var line = booking.driver_eta_line || '';
        if (line.indexOf('Dự kiến ') === 0) {
            return line.replace('Dự kiến ', '');
        }
        return line || '';
    }

    function parseRoute(route) {
        var raw = String(route || '').trim();
        if (!raw || raw === '—') {
            return { from: '—', to: '—' };
        }
        var parts = raw.split(/\s*→\s*/);
        if (parts.length >= 2) {
            return { from: parts[0].trim(), to: parts.slice(1).join(' → ').trim() };
        }
        return { from: raw, to: raw };
    }

    function stopAddress(province, detail) {
        if (detail) {
            return String(detail).trim();
        }
        return province ? String(province).trim() : '';
    }

    function driverInitials(name) {
        var parts = String(name || '').trim().split(/\s+/).filter(Boolean);
        if (!parts.length) {
            return 'TX';
        }
        if (parts.length === 1) {
            return parts[0].slice(0, 2).toUpperCase();
        }
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }

    function getBrowserId() {
        if (window.BookingBrowserGuard && window.BookingBrowserGuard.getBrowserSessionId) {
            return window.BookingBrowserGuard.getBrowserSessionId() || '';
        }
        return '';
    }

    function getContactPhone() {
        if (currentBooking && currentBooking.contact_phone) {
            return String(currentBooking.contact_phone);
        }
        if (window.BookingActiveSession && window.BookingActiveSession.load) {
            var stored = window.BookingActiveSession.load();
            if (stored && stored.contact_phone) {
                return String(stored.contact_phone);
            }
            if (stored && stored.booking && stored.booking.contact_phone) {
                return String(stored.booking.contact_phone);
            }
        }
        if (window.__bookingSuccess && window.__bookingSuccess.contact_phone) {
            return String(window.__bookingSuccess.contact_phone);
        }
        return '';
    }

    function getBookingReference() {
        if (window.BookingActiveSession && window.BookingActiveSession.load) {
            var stored = window.BookingActiveSession.load();
            if (stored && stored.booking_reference) {
                return String(stored.booking_reference);
            }
        }
        if (window.__bookingSuccess && window.__bookingSuccess.booking_reference) {
            return String(window.__bookingSuccess.booking_reference);
        }
        return '';
    }

    function buildStatusParams() {
        var params = new URLSearchParams();
        var phone = getContactPhone();
        var browserId = getBrowserId();
        var bookingRef = getBookingReference();

        if (phone) {
            params.set('contact_phone', phone);
        }
        if (browserId) {
            params.set('booking_browser_id', browserId);
        }
        if (bookingRef) {
            params.set('booking_reference', bookingRef);
        }

        return params;
    }

    function syncActiveSession(booking) {
        if (!booking || !window.BookingActiveSession || !window.BookingActiveSession.saveFromBooking) {
            return;
        }

        var stored = window.BookingActiveSession.load() || {};
        window.BookingActiveSession.saveFromBooking(booking, {
            booking_reference: booking.booking_reference || stored.booking_reference || '',
            contact_phone: booking.contact_phone || stored.contact_phone || getContactPhone(),
        });
    }

    function completionShownKey(bookingReference) {
        return COMPLETION_SHOWN_PREFIX + String(bookingReference || '');
    }

    function hasCompletionPromptBeenShown(bookingReference) {
        if (!bookingReference) {
            return true;
        }
        try {
            return sessionStorage.getItem(completionShownKey(bookingReference)) === '1';
        } catch (e) {
            return false;
        }
    }

    function markCompletionPromptShown(bookingReference) {
        if (!bookingReference) {
            return;
        }
        try {
            sessionStorage.setItem(completionShownKey(bookingReference), '1');
        } catch (e) {}
    }

    function shouldFocusInlineReview(booking, previousBooking) {
        if (!booking || booking.trip_status !== 'completed') {
            return false;
        }
        if (hasCompletionPromptBeenShown(booking.booking_reference)) {
            return false;
        }
        if (!booking.can_review) {
            return false;
        }
        if (!previousBooking) {
            return true;
        }
        return previousBooking.trip_status !== 'completed';
    }

    function focusInlineReview(booking) {
        if (!booking) {
            return;
        }
        markCompletionPromptShown(booking.booking_reference);
        var reviewSection = document.getElementById('guest-trip-review-section');
        if (!reviewSection) {
            return;
        }
        reviewSection.classList.remove('d-none');
        reviewSection.classList.add('guest-trip-review--focus');
        try {
            reviewSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } catch (e) {
            /* ignore */
        }
        if (window.CustomerScrollDock && window.CustomerScrollDock.focusTripsContent) {
            window.CustomerScrollDock.focusTripsContent();
        }
    }

    function maybeFocusInlineReview(booking, previousBooking) {
        if (!shouldFocusInlineReview(booking, previousBooking)) {
            return;
        }
        focusInlineReview(booking);
    }

    function clearActiveSessionIfDone(booking) {
        if (!booking || booking.is_active || booking.can_review) {
            return;
        }
        if (window.BookingActiveSession && window.BookingActiveSession.clear) {
            window.BookingActiveSession.clear();
        }
    }

    function renderDriverPanel(booking) {
        var panel = document.getElementById('guest-trip-driver-panel');
        if (!panel) {
            return;
        }

        var driver = booking && booking.driver ? booking.driver : null;
        if (!driver || !booking.has_driver) {
            panel.classList.add('d-none');
            return;
        }

        var photoWrap = qs('[data-field="driver_photo_wrap"]', panel);
        var photoEl = qs('[data-field="driver_photo"]', panel);
        var avatarFallback = qs('[data-field="driver_avatar_fallback"]', panel);
        var vehiclePhoto = driver.vehicle_photo_url || '';

        if (photoWrap && photoEl) {
            if (vehiclePhoto) {
                photoEl.src = vehiclePhoto;
                photoEl.alt = driver.vehicle_name || driver.vehicle_type_label || 'Ảnh xe';
                photoWrap.classList.remove('d-none');
                if (avatarFallback) {
                    avatarFallback.classList.add('d-none');
                }
            } else {
                photoEl.removeAttribute('src');
                photoWrap.classList.add('d-none');
                if (avatarFallback) {
                    avatarFallback.textContent = driverInitials(driver.name);
                    avatarFallback.classList.remove('d-none');
                }
            }
        }

        setText(qs('[data-field="driver_name"]', panel), driver.name || '—', true);
        setText(qs('[data-field="driver_code"]', panel), driver.code ? driver.code : '', !!driver.code);

        var vehicleParts = [];
        if (driver.vehicle_type_label) {
            vehicleParts.push(driver.vehicle_type_label);
        } else if (driver.vehicle_name) {
            vehicleParts.push(driver.vehicle_name);
        }
        setText(qs('[data-field="driver_vehicle"]', panel), vehicleParts.join(' · '), vehicleParts.length > 0);

        var plateText = driver.vehicle_plate || '';
        var seatsLabel = driver.vehicle_seats_label || '';
        if (!seatsLabel && Number(driver.vehicle_seats || 0) > 0) {
            seatsLabel = driver.vehicle_seats + ' chỗ';
        }
        setText(qs('[data-field="driver_plate"]', panel), plateText, !!plateText);
        setText(qs('[data-field="driver_seats"]', panel), seatsLabel, !!seatsLabel);

        if (photoWrap) {
            if (vehiclePhoto) {
                photoWrap.classList.add('is-zoomable');
                photoWrap.setAttribute('role', 'button');
                photoWrap.setAttribute('tabindex', '0');
                photoWrap.setAttribute('aria-label', 'Xem ảnh xe');
                photoWrap.dataset.vehiclePhotoUrl = vehiclePhoto;
            } else {
                photoWrap.classList.remove('is-zoomable');
                photoWrap.removeAttribute('role');
                photoWrap.removeAttribute('tabindex');
                photoWrap.removeAttribute('aria-label');
                delete photoWrap.dataset.vehiclePhotoUrl;
            }
        }

        var statusLine = booking.driver_status_line || driver.status_line || '';
        setText(qs('[data-field="driver_status"]', panel), statusLine, !!statusLine);

        var liveWrap = qs('[data-field="driver_live_wrap"]', panel);
        var distanceEl = qs('[data-field="driver_distance_line"]', panel);
        var etaEl = qs('[data-field="driver_eta_line"]', panel);
        var distanceLine = '';
        var etaLine = '';

        if (showGuestDriverDistance(booking)) {
            distanceLine = booking.driver_distance_line
                || (driver && driver.distance_line)
                || '';
            if (!distanceLine) {
                var distanceLabel = booking.driver_distance_label
                    || (driver && driver.distance_label)
                    || driverDistanceGuestLabel(booking);
                if (distanceLabel) {
                    distanceLine = 'Tài xế cách bạn ' + distanceLabel;
                }
            }
        }

        if (showGuestDriverEta(booking)) {
            etaLine = booking.driver_eta_line || (driver && driver.eta_line) || '';
            if (!etaLine) {
                var etaLabel = booking.driver_eta_label || (driver && driver.eta_label) || '';
                if (etaLabel) {
                    etaLine = 'Dự kiến ' + etaLabel;
                }
            }
        }

        if (distanceEl) {
            distanceEl.textContent = distanceLine;
            distanceEl.classList.toggle('d-none', !distanceLine);
        }

        if (etaEl) {
            etaEl.textContent = etaLine;
            etaEl.classList.toggle('d-none', !etaLine);
        }

        if (liveWrap) {
            liveWrap.classList.toggle('d-none', !distanceLine && !etaLine);
        }

        panel.classList.remove('d-none');
    }

    function syncTripSummarySection(card) {
        var section = qs('[data-field="trip_summary_wrap"]', card);
        if (!section) {
            return;
        }

        var hasVisibleDetail = !!section.querySelector('.guest-trip-detail:not(.d-none)');
        section.classList.toggle('d-none', !hasVisibleDetail);
    }

    function syncVehicleSection(card) {
        var section = qs('[data-field="vehicle_section_wrap"]', card);
        if (!section) {
            return;
        }

        var driverPanel = qs('[data-field="driver_panel"]', card);
        var hasDriver = !!(driverPanel && !driverPanel.classList.contains('d-none'));

        section.classList.toggle('d-none', !hasDriver);
        section.classList.toggle('guest-trip-vehicle-section--driver-only', hasDriver);
    }

    function renderReview(booking) {
        var reviewSection = document.getElementById('guest-trip-review-section');
        var reviewDone = document.getElementById('guest-trip-review-done');
        if (!reviewSection || !reviewDone) {
            return;
        }

        if (booking.review) {
            reviewSection.classList.add('d-none');
            reviewDone.classList.remove('d-none');
            setText(qs('[data-field="review_icon"]', reviewDone), booking.review.icon || '👍', true);
            setText(qs('[data-field="review_label"]', reviewDone), 'Đã đánh giá: ' + (booking.review.label || ''), true);
            setText(qs('[data-field="review_comment"]', reviewDone), booking.review.comment || '', !!booking.review.comment);
            setText(qs('[data-field="review_time"]', reviewDone), booking.review.created_at || '', !!booking.review.created_at);
            return;
        }

        reviewDone.classList.add('d-none');
        if (booking.can_review) {
            reviewSection.classList.remove('d-none');
            selectedSentiment = '';
            document.querySelectorAll('[data-review-sentiment]').forEach(function (btn) {
                btn.classList.remove('active');
            });
            var form = document.getElementById('guest-trip-review-form');
            if (form) {
                form.classList.add('d-none');
            }
            var comment = document.getElementById('guest-trip-review-comment');
            if (comment) {
                comment.value = '';
            }
            hideReviewError();
        } else {
            reviewSection.classList.add('d-none');
        }
    }

    function renderBooking(booking) {
        var previousBooking = currentBooking;
        currentBooking = booking;
        var empty = document.getElementById('guest-trip-empty');
        var card = document.getElementById('guest-trip-card');
        if (!empty || !card) {
            return;
        }

        if (!booking) {
            empty.classList.remove('d-none');
            card.classList.add('d-none');
            if (window.GuestTripLiveMap && window.GuestTripLiveMap.updateFromBooking) {
                window.GuestTripLiveMap.updateFromBooking(null);
            }
            if (window.TripChat && window.TripChat.setCustomerBooking) {
                window.TripChat.setCustomerBooking(null);
            }
            syncPollInterval(null);
            return;
        }

        empty.classList.add('d-none');
        card.classList.remove('d-none');

        setText(qs('[data-field="trip_code"]', card), booking.trip_code || '—', true);
        setText(
            qs('[data-field="guest_status_label"]', card),
            booking.guest_status_label || booking.progress_label || '',
            !!(booking.guest_status_label || booking.progress_label)
        );

        var routeParts = parseRoute(booking.route);
        setText(qs('[data-field="route_from"]', card), routeParts.from, true);
        setText(qs('[data-field="route_to"]', card), routeParts.to, true);

        var scheduledPickup = isScheduledPickup(booking);
        setDetailRow(
            card,
            'pickup_mode_wrap',
            'pickup_mode_label',
            scheduledPickup ? '' : (booking.pickup_mode_label || 'Đón ngay'),
        );
        setDetailRow(card, 'pickup_time_wrap', 'pickup_time_label', scheduledPickup ? (booking.pickup_time_label || '') : '');
        setDetailRow(card, 'service_date_wrap', 'service_date_label', scheduledPickup ? serviceDateLabel(booking) : '');

        var distanceKm = Number(booking.distance_km || 0);
        setDetailRow(
            card,
            'trip_distance_wrap',
            'trip_distance_km',
            distanceKm > 0 ? distanceKm + ' km' : '',
        );

        var priceLabel = booking.total_price_label || '';
        if (!priceLabel && booking.total_price > 0) {
            priceLabel = Number(booking.total_price).toLocaleString('vi-VN') + ' đ';
        }
        setDetailRow(card, 'total_price_wrap', 'total_price', priceLabel);
        var extrasEl = qs('[data-field="price_extras"]', card);
        if (extrasEl) {
            var bits = [];
            if (Number(booking.surcharge_holiday || 0) > 0) bits.push('Lễ/tết');
            if (Number(booking.surcharge_peak || 0) > 0) bits.push('Cao điểm');
            if (Number(booking.surcharge_rain || 0) > 0) bits.push('Mưa');
            if (Number(booking.toll_amount || 0) > 0) bits.push('Thu phí');
            if (Number(booking.referral_discount_amount || 0) > 0) bits.push('Đã giảm giá QR');
            if (bits.length) {
                extrasEl.textContent = bits.join(' · ');
                extrasEl.classList.remove('d-none');
            } else {
                extrasEl.textContent = '';
                extrasEl.classList.add('d-none');
            }
        }

        var pickupText = stopAddress(booking.pickup_address, booking.pickup_detail);
        var dropoffText = stopAddress(booking.dropoff_address, booking.dropoff_detail);
        setWrapText(
            qs('[data-field="pickup_wrap"]', card),
            qs('[data-field="pickup_address"]', card),
            pickupText,
        );
        setWrapText(
            qs('[data-field="dropoff_wrap"]', card),
            qs('[data-field="dropoff_address"]', card),
            dropoffText,
        );

        renderDriverPanel(booking);
        if (window.GuestTripLiveMap && window.GuestTripLiveMap.updateFromBooking) {
            window.GuestTripLiveMap.updateFromBooking(booking);
        }
        if (window.TripChat && window.TripChat.setCustomerBooking) {
            window.TripChat.setCustomerBooking(booking);
        }
        syncVehicleSection(card);
        syncTripSummarySection(card);
        renderReview(booking);
        renderCancelAction(booking);
        syncActiveSession(booking);
        clearActiveSessionIfDone(booking);
        maybeFocusInlineReview(booking, previousBooking);
        syncPollInterval(booking);

        var tripJustEnded = !!(
            previousBooking
            && previousBooking.is_active
            && booking
            && booking.trip_status === 'completed'
            && previousBooking.trip_status !== 'completed'
        );
        if (tripJustEnded && window.CustomerScrollDock && window.CustomerScrollDock.focusTripsPage) {
            window.CustomerScrollDock.focusTripsPage();
        }
    }

    function renderCancelAction(booking) {
        var wrap = document.getElementById('guest-trip-cancel-wrap');
        var err = document.getElementById('guest-trip-cancel-error');
        if (!wrap) {
            return;
        }
        if (err) {
            err.textContent = '';
            err.classList.add('d-none');
        }
        if (booking && booking.can_cancel) {
            wrap.classList.remove('d-none');
        } else {
            wrap.classList.add('d-none');
        }
    }

    function hideCancelError() {
        var err = document.getElementById('guest-trip-cancel-error');
        if (err) {
            err.textContent = '';
            err.classList.add('d-none');
        }
    }

    function showCancelError(message) {
        var err = document.getElementById('guest-trip-cancel-error');
        if (!err) {
            return;
        }
        err.textContent = message;
        err.classList.remove('d-none');
    }

    function hideReviewError() {
        var err = document.getElementById('guest-trip-review-error');
        if (err) {
            err.textContent = '';
            err.classList.add('d-none');
        }
    }

    function showReviewError(message) {
        var err = document.getElementById('guest-trip-review-error');
        if (!err) {
            return;
        }
        err.textContent = message;
        err.classList.remove('d-none');
    }

    function fetchStatus(options) {
        options = options || {};

        if (!window.__bookingTripStatusUrl) {
            return Promise.resolve(null);
        }

        var params = buildStatusParams();
        if (!params.toString()) {
            renderBooking(null);
            return Promise.resolve(null);
        }

        return fetch(window.__bookingTripStatusUrl + '?' + params.toString(), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.has_trip && data.booking) {
                    renderBooking(data.booking);
                    return data.booking;
                }

                renderBooking(null);
                return null;
            })
            .catch(function () {
                return null;
            });
    }

    function submitReview() {
        if (!currentBooking || !selectedSentiment || !window.__bookingTripReviewUrl) {
            return;
        }

        var submitBtn = document.getElementById('guest-trip-review-submit');
        if (submitBtn) {
            submitBtn.disabled = true;
        }
        hideReviewError();

        var token = document.querySelector('meta[name="csrf-token"]');
        var commentEl = document.getElementById('guest-trip-review-comment');

        fetch(window.__bookingTripReviewUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                booking_reference: currentBooking.booking_reference,
                sentiment: selectedSentiment,
                comment: commentEl ? commentEl.value.trim() : '',
                contact_phone: getContactPhone(),
                booking_browser_id: getBrowserId(),
            }),
        })
            .then(function (r) {
                return r.json().then(function (data) {
                    if (!r.ok) {
                        throw new Error(data.message || 'Không gửi được đánh giá.');
                    }
                    return data;
                });
            })
            .then(function (data) {
                if (data.booking) {
                    renderBooking(data.booking);
                } else {
                    fetchStatus();
                }
                closeCompletionModal();
                if (window.CustomerScrollDock && window.CustomerScrollDock.focusTripsContent) {
                    window.CustomerScrollDock.focusTripsContent();
                }
            })
            .catch(function (err) {
                showReviewError(err.message || 'Không gửi được đánh giá.');
            })
            .finally(function () {
                if (submitBtn) {
                    submitBtn.disabled = false;
                }
            });
    }

    function confirmCancelTrip() {
        if (window.AppDialog && window.AppDialog.confirm) {
            return window.AppDialog.confirm({
                title: 'Hủy chuyến',
                message: 'Bạn chắc chắn muốn hủy chuyến này?',
                confirmText: 'Hủy chuyến',
                cancelText: 'Giữ chuyến',
                variant: 'danger',
            });
        }
        return Promise.resolve(window.confirm('Bạn chắc chắn muốn hủy chuyến này?'));
    }

    function pickCancelReason() {
        if (!window.CancellationReasonModal || !window.CancellationReasonModal.pick) {
            return Promise.resolve({ skipped: true, reasonId: null });
        }
        return window.CancellationReasonModal.pick({
            audience: 'customer',
            contactPhone: getContactPhone(),
            title: 'Chọn lý do hủy',
            hint: 'Vui lòng chọn một lý do trước khi hủy chuyến.',
        });
    }

    function submitCancel(reasonId) {
        if (!currentBooking || !window.__bookingTripCancelUrl) {
            return Promise.resolve();
        }

        var cancelBtn = document.getElementById('guest-trip-cancel-btn');
        if (cancelBtn) {
            cancelBtn.disabled = true;
        }
        hideCancelError();

        var token = document.querySelector('meta[name="csrf-token"]');
        var payload = {
            booking_reference: currentBooking.booking_reference,
            contact_phone: getContactPhone(),
            booking_browser_id: getBrowserId(),
        };
        if (reasonId) {
            payload.cancellation_reason_id = reasonId;
        }

        return fetch(window.__bookingTripCancelUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        })
            .then(function (r) {
                return r.json().then(function (data) {
                    if (!r.ok) {
                        throw new Error(data.message || 'Không hủy được chuyến.');
                    }
                    return data;
                });
            })
            .then(function (data) {
                if (window.BookingBrowserGuard && window.BookingBrowserGuard.recordCancelSuccess) {
                    window.BookingBrowserGuard.recordCancelSuccess(data.cancel_count);
                }
                if (window.BookingActiveSession && window.BookingActiveSession.clear) {
                    window.BookingActiveSession.clear();
                }
                currentBooking = null;
                renderBooking(null);
                if (window.CustomerScrollDock && window.CustomerScrollDock.focusTripsPage) {
                    window.CustomerScrollDock.focusTripsPage();
                }
                if (window.AppDialog && window.AppDialog.alert) {
                    var msg = data.cancel_blocked && data.block_message
                        ? data.block_message
                        : (data.message || 'Hủy chuyến thành công.');
                    window.AppDialog.alert(msg, { variant: 'success', title: 'Hủy chuyến thành công.' });
                }
            })
            .catch(function (err) {
                showCancelError(err.message || 'Không hủy được chuyến.');
            })
            .finally(function () {
                if (cancelBtn) {
                    cancelBtn.disabled = false;
                }
            });
    }

    function cancelTrip() {
        if (!currentBooking || !currentBooking.can_cancel) {
            return;
        }

        confirmCancelTrip().then(function (ok) {
            if (!ok) {
                return;
            }
            return pickCancelReason();
        }).then(function (pick) {
            if (!pick) {
                return;
            }
            return submitCancel(pick.reasonId || null);
        }).catch(function (err) {
            if (err && err.message) {
                if (window.AppDialog && window.AppDialog.alert) {
                    window.AppDialog.alert(err.message, { variant: 'danger' });
                } else {
                    showCancelError(err.message);
                }
            }
        });
    }

    function bindReview() {
        document.querySelectorAll('[data-review-sentiment]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                selectedSentiment = btn.getAttribute('data-review-sentiment') || '';
                document.querySelectorAll('[data-review-sentiment]').forEach(function (other) {
                    other.classList.toggle('active', other === btn);
                });
                var form = document.getElementById('guest-trip-review-form');
                if (form) {
                    form.classList.remove('d-none');
                }
                hideReviewError();
            });
        });

        var submitBtn = document.getElementById('guest-trip-review-submit');
        if (submitBtn) {
            submitBtn.addEventListener('click', submitReview);
        }
    }

    function bindVehiclePhotoOverlay() {
        var overlay = document.getElementById('guest-trip-vehicle-photo-overlay');
        var image = document.getElementById('guest-trip-vehicle-photo-overlay-image');
        if (!overlay || !image) {
            return;
        }

        function closeOverlay() {
            overlay.classList.add('d-none');
            overlay.setAttribute('hidden', 'hidden');
            image.removeAttribute('src');
            document.body.classList.remove('guest-trip-vehicle-photo-open');
        }

        function openOverlay(url, altText) {
            if (!url) {
                return;
            }
            image.src = url;
            image.alt = altText || 'Ảnh xe';
            overlay.classList.remove('d-none');
            overlay.removeAttribute('hidden');
            document.body.classList.add('guest-trip-vehicle-photo-open');
        }

        document.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-field="driver_photo_wrap"].is-zoomable');
            if (!trigger || !trigger.dataset.vehiclePhotoUrl) {
                return;
            }
            event.preventDefault();
            var photo = trigger.querySelector('[data-field="driver_photo"]');
            openOverlay(trigger.dataset.vehiclePhotoUrl, photo ? photo.alt : 'Ảnh xe');
        });

        document.addEventListener('keydown', function (event) {
            var trigger = event.target.closest('[data-field="driver_photo_wrap"].is-zoomable');
            if (!trigger || (event.key !== 'Enter' && event.key !== ' ')) {
                return;
            }
            event.preventDefault();
            if (trigger.dataset.vehiclePhotoUrl) {
                var photo = trigger.querySelector('[data-field="driver_photo"]');
                openOverlay(trigger.dataset.vehiclePhotoUrl, photo ? photo.alt : 'Ảnh xe');
            }
        });

        overlay.querySelectorAll('[data-close-guest-vehicle-photo]').forEach(function (btn) {
            btn.addEventListener('click', closeOverlay);
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !overlay.classList.contains('d-none')) {
                closeOverlay();
            }
        });
    }

    var pollHandle = null;
    var pollMs = 5000;

    function needsFastPoll(booking) {
        if (!booking || !booking.is_active) {
            return false;
        }
        if (!booking.has_driver) {
            return true;
        }
        var stage = booking.driver ? String(booking.driver.stage || '') : '';
        return stage === 'assigned' || stage === 'at_pickup'
            || stage === 'picked_up' || stage === 'running';
    }

    function stopPoll() {
        if (pollHandle && typeof pollHandle.stop === 'function') {
            pollHandle.stop();
        } else if (pollHandle) {
            window.clearInterval(pollHandle);
        }
        pollHandle = null;
    }

    function syncPollInterval(booking) {
        var fast = needsFastPoll(booking);
        var next = fast ? 3000 : 5000;
        if (next === pollMs && pollHandle) {
            return;
        }
        pollMs = next;
        stopPoll();
        // Tracking vị trí: setInterval cố định 3s (không dùng IdlePoll — tránh bỏ poll khi đang xem màn hình).
        if (fast) {
            pollHandle = window.setInterval(function () { fetchStatus(); }, pollMs);
            return;
        }
        if (window.IdlePoll) {
            pollHandle = window.IdlePoll.create({
                intervalMs: pollMs,
                onPoll: function () { fetchStatus(); },
            });
            pollHandle.start();
        } else {
            pollHandle = window.setInterval(function () { fetchStatus(); }, pollMs);
        }
    }

    function init() {
        bindReview();
        bindVehiclePhotoOverlay();
        var cancelBtn = document.getElementById('guest-trip-cancel-btn');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', cancelTrip);
        }
        var statusPromise = fetchStatus({ initial: true });
        if (statusPromise && typeof statusPromise.then === 'function') {
            statusPromise.then(function () {
                syncPollInterval(currentBooking);
            });
        } else {
            syncPollInterval(currentBooking);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
