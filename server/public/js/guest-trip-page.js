/**
 * Trang Chuyến — theo dõi chuyến đã đặt và đánh giá sau khi hoàn tất.
 */
(function () {
    var selectedSentiment = '';
    var currentBooking = null;
    var COMPLETION_SHOWN_PREFIX = 'guest_trip_completion_shown:';
    var etaSheetDeadlineMs = null;
    var etaSheetTickTimer = null;

    function formatArrivalDuration(mins) {
        mins = Math.max(0, Math.round(Number(mins) || 0));
        if (mins < 60) {
            return mins + ' phút';
        }
        var hours = Math.floor(mins / 60);
        var rem = mins % 60;
        if (rem <= 0) {
            return hours + ' giờ';
        }
        return hours + 'h' + rem + ' phút';
    }

    function driverDistanceLabel(booking) {
        if (!booking) {
            return '';
        }
        if (booking.driver_distance_label) {
            return booking.driver_distance_label;
        }
        if (booking.driver && booking.driver.distance_label) {
            return booking.driver.distance_label;
        }
        return driverDistanceGuestLabel(booking) || '';
    }

    /** Dòng trên thanh sheet: cách bạn + dự kiến đến. */
    function buildProximitySummary(booking, remainMins) {
        if (!booking) {
            return '';
        }
        if (booking.driver_proximity_summary && (remainMins == null || remainMins <= 0)) {
            return booking.driver_proximity_summary;
        }
        var dist = driverDistanceLabel(booking);
        var showEta = showGuestDriverEta(booking);
        var duration = '';
        if (showEta) {
            if (remainMins != null && remainMins > 0) {
                duration = formatArrivalDuration(remainMins);
            } else if (booking.driver && booking.driver.eta_duration_label) {
                duration = booking.driver.eta_duration_label;
            } else if (booking.driver_eta_label) {
                duration = String(booking.driver_eta_label).replace(/\s*nữa\s*$/i, '');
            } else if (booking.driver && booking.driver.eta_minutes > 0) {
                duration = formatArrivalDuration(booking.driver.eta_minutes);
            }
        }
        if (dist && duration) {
            return 'Tài xế cách bạn ' + dist + ' — dự kiến đến trong ' + duration;
        }
        if (dist) {
            return 'Tài xế cách bạn ' + dist;
        }
        if (booking.driver_proximity_summary) {
            return booking.driver_proximity_summary;
        }
        return booking.driver_status_line
            || (booking.driver && booking.driver.status_line)
            || '';
    }

    function syncSheetHint(booking, searching, tracking) {
        var sheetHint = document.getElementById('guest-trip-info-sheet-hint');
        if (!sheetHint) {
            return;
        }
        // Thanh vuốt sheet: luôn «Thông tin chuyến» — trạng thái tìm TX nằm banner trên map.
        var text = 'Thông tin chuyến';
        sheetHint.textContent = text;
        sheetHint.classList.toggle('d-none', !text);
        sheetHint.title = text;
    }

    function syncMapStatusBanner(booking, searching, tracking) {
        var el = document.getElementById('guest-trip-map-status');
        var textEl = document.getElementById('guest-trip-map-status-text');
        if (!el || !textEl) {
            return;
        }
        if (searching) {
            var label = (booking
                && booking.wait_progress
                && booking.wait_progress.label)
                ? String(booking.wait_progress.label).trim()
                : '';
            if (!label || label === 'Đang tìm tài xế') {
                label = 'Đang tìm tài xế gần bạn…';
            }
            textEl.textContent = label;
            el.classList.remove('d-none');
            return;
        }
        el.classList.add('d-none');
    }

    function syncTripStatusChrome(booking, searching, tracking) {
        syncSheetHint(booking, searching, tracking);
        syncMapStatusBanner(booking, searching, tracking);
    }

    /** Đồng hồ ETA đếm lùi trên thanh sheet giữa các lần poll. */
    function startEtaSheetTick() {
        if (etaSheetTickTimer) {
            return;
        }
        etaSheetTickTimer = window.setInterval(function () {
            if (etaSheetDeadlineMs == null || !currentBooking) {
                return;
            }
            var tracking = !!(currentBooking.has_driver && currentBooking.trip_status !== 'completed'
                && currentBooking.trip_status !== 'cancelled');
            syncTripStatusChrome(currentBooking, false, tracking);
        }, 20000);
    }

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

    function stopAddress(province, detail) {
        var d = detail ? String(detail).trim() : '';
        var p = province ? String(province).trim() : '';
        if (d && p) {
            var lower = d.toLowerCase();
            if (lower.indexOf(p.toLowerCase()) === -1) {
                return d + ', ' + p;
            }
            return d;
        }
        return d || p || '';
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
        var reviewRef = '';
        try {
            reviewRef = new URLSearchParams(window.location.search).get('review') || '';
        } catch (e) {
            reviewRef = '';
        }
        if (reviewRef && booking && booking.booking_reference === reviewRef && booking.can_review) {
            focusInlineReview(booking);
            return;
        }
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

    function syncGuestDriverCall(panel, booking, driver) {
        var callBtn = qs('[data-field="driver_call"]', panel);
        var reveal = qs('[data-field="driver_call_reveal"]', panel);
        var numberEl = qs('[data-field="driver_call_number"]', panel);
        var phoneTel = driver && driver.phone_tel ? String(driver.phone_tel) : '';
        var phoneDisplay = driver && driver.phone ? String(driver.phone) : phoneTel;
        var ref = booking && booking.booking_reference ? String(booking.booking_reference) : '';
        var canCall = !!phoneTel;

        if (canCall) {
            panel.setAttribute('data-booking-key', ref || 'unknown');
            panel.setAttribute('data-booking-reference', ref);
            panel.setAttribute('data-phone-tel', phoneTel);
            panel.setAttribute('data-phone-display', phoneDisplay);
            panel.classList.add('guest-trip-driver--has-call');
        } else {
            panel.removeAttribute('data-booking-key');
            panel.removeAttribute('data-booking-reference');
            panel.removeAttribute('data-phone-tel');
            panel.removeAttribute('data-phone-display');
            panel.classList.remove('guest-trip-driver--has-call', 'is-call-revealed');
        }

        if (callBtn) {
            callBtn.classList.toggle('d-none', !canCall);
            if (!canCall) {
                callBtn.setAttribute('href', '#');
            }
        }
        if (reveal && !canCall) {
            reveal.classList.add('d-none');
        }
        if (numberEl) {
            if (canCall) {
                numberEl.href = 'tel:' + phoneTel;
                numberEl.textContent = phoneDisplay;
            } else {
                numberEl.removeAttribute('href');
                numberEl.textContent = '';
            }
        }

        if (canCall && window.CallReveal && typeof window.CallReveal.sync === 'function') {
            window.CallReveal.sync(panel);
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
            syncGuestDriverCall(panel, null, null);
            return;
        }

        var photoWrap = qs('[data-field="driver_photo_wrap"]', panel);
        var photoEl = qs('[data-field="driver_photo"]', panel);
        var avatarFallback = qs('[data-field="driver_avatar_fallback"]', panel);
        var vehiclePhotos = Array.isArray(driver.vehicle_photo_urls)
            ? driver.vehicle_photo_urls.filter(Boolean)
            : [];
        if (!vehiclePhotos.length && driver.vehicle_photo_url) {
            vehiclePhotos = [driver.vehicle_photo_url];
        }
        var vehiclePhoto = vehiclePhotos[0] || '';

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

        // Không hiện sao/like khi chuyến đang diễn ra.
        var ratingWrap = qs('[data-field="driver_rating"]', panel);
        var ratingValueEl = qs('[data-field="driver_rating_value"]', panel);
        if (ratingWrap) {
            ratingWrap.classList.add('d-none');
        }
        if (ratingValueEl) {
            ratingValueEl.textContent = '';
        }

        var vehicleParts = [];
        if (driver.vehicle_type_label) {
            vehicleParts.push(driver.vehicle_type_label);
        } else if (driver.vehicle_name) {
            vehicleParts.push(driver.vehicle_name);
        }
        if (driver.vehicle_color) {
            vehicleParts.push(driver.vehicle_color);
        }
        setText(qs('[data-field="driver_vehicle"]', panel), vehicleParts.join(' · '), vehicleParts.length > 0);

        // Hiện biển số thay mã TX.
        var plateText = driver.vehicle_plate || '';
        setText(qs('[data-field="driver_plate"]', panel), plateText, !!plateText);

        syncGuestDriverCall(panel, booking, driver);

        if (photoWrap) {
            if (vehiclePhotos.length) {
                photoWrap.classList.add('is-zoomable');
                photoWrap.setAttribute('role', 'button');
                photoWrap.setAttribute('tabindex', '0');
                photoWrap.setAttribute(
                    'aria-label',
                    vehiclePhotos.length > 1 ? 'Xem ảnh xe (' + vehiclePhotos.length + ' ảnh)' : 'Xem ảnh xe'
                );
                photoWrap.dataset.vehiclePhotoUrls = JSON.stringify(vehiclePhotos);
                photoWrap.dataset.vehiclePhotoUrl = vehiclePhoto;
                photoWrap.classList.toggle('has-multi-photos', vehiclePhotos.length > 1);
            } else {
                photoWrap.classList.remove('is-zoomable', 'has-multi-photos');
                photoWrap.removeAttribute('role');
                photoWrap.removeAttribute('tabindex');
                photoWrap.removeAttribute('aria-label');
                delete photoWrap.dataset.vehiclePhotoUrl;
                delete photoWrap.dataset.vehiclePhotoUrls;
            }
        }

        var liveWrap = qs('[data-field="driver_live_wrap"]', panel);
        if (liveWrap) {
            liveWrap.classList.add('d-none');
        }

        var etaMinutes = Number((driver && driver.eta_minutes) || 0);
        panel.classList.remove('guest-trip-driver--has-eta');
        if (showGuestDriverEta(booking) && etaMinutes > 0) {
            etaSheetDeadlineMs = Date.now() + etaMinutes * 60000;
            startEtaSheetTick();
        } else {
            etaSheetDeadlineMs = null;
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

    /** Stepper 4 bước: Đã nhận → Đang đến → Đã đón → Hoàn thành. */
    function renderTripStepper(booking) {
        var wrap = document.getElementById('guest-trip-stepper');
        if (!wrap) {
            return;
        }
        if (!booking || !booking.has_driver || booking.trip_status === 'cancelled') {
            wrap.classList.add('d-none');
            return;
        }

        var step = 1;
        if (booking.trip_status === 'completed') {
            step = 4;
        } else if (booking.driver && booking.driver.stage_step) {
            step = Number(booking.driver.stage_step) || 1;
        }

        wrap.classList.remove('d-none');
        var steps = wrap.querySelectorAll('.guest-trip-stepper__step');
        for (var i = 0; i < steps.length; i++) {
            var n = Number(steps[i].getAttribute('data-step'));
            steps[i].classList.toggle('is-done', n < step);
            steps[i].classList.toggle('is-active', n === step);
        }
        var lines = wrap.querySelectorAll('.guest-trip-stepper__line');
        for (var j = 0; j < lines.length; j++) {
            lines[j].classList.toggle('is-done', j + 1 < step);
        }
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
            card.classList.remove('is-searching', 'is-tracking');
            document.body.classList.remove('guest-trip-searching', 'guest-trip-tracking');
            etaSheetDeadlineMs = null;
            if (window.TripActionFabs && window.TripActionFabs.setInTrip) {
                window.TripActionFabs.setInTrip(false);
            }
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
        var searching = !booking.has_driver;
        var tracking = !!(booking.is_active && booking.has_driver);
        card.classList.toggle('is-searching', searching);
        card.classList.toggle('is-tracking', tracking);
        document.body.classList.toggle('guest-trip-searching', searching);
        document.body.classList.toggle('guest-trip-tracking', tracking);
        if (window.TripActionFabs && window.TripActionFabs.setInTrip) {
            window.TripActionFabs.setInTrip(tracking);
        }
        if ((searching || tracking) && window.GuestTripSheet) {
            var sheetMode = searching ? 'search' : 'track';
            // Đổi chế độ (chờ TX → đã nhận): luôn thu sheet, ưu tiên map.
            if (card.dataset.sheetMode !== sheetMode) {
                card.dataset.sheetMode = sheetMode;
                if (window.GuestTripSheet.resetUserToggle) {
                    window.GuestTripSheet.resetUserToggle();
                }
                if (window.GuestTripSheet.collapse) {
                    window.GuestTripSheet.collapse();
                }
                window.requestAnimationFrame(function () {
                    if (window.GuestTripLiveMap && window.GuestTripLiveMap.resize) {
                        window.GuestTripLiveMap.resize();
                    }
                    if (window.GuestTripLiveMap && window.GuestTripLiveMap.refitSheetCamera) {
                        window.GuestTripLiveMap.refitSheetCamera();
                    }
                    if (window.GuestTripSheet.syncLocateFabLift) {
                        window.GuestTripSheet.syncLocateFabLift();
                    }
                });
            }
        } else {
            delete card.dataset.sheetMode;
        }
        var sheetHint = document.getElementById('guest-trip-info-sheet-hint');
        if (sheetHint) {
            syncTripStatusChrome(booking, searching, tracking);
        }

        // Thanh tiến trình — chỉ nhắc đánh giá; bỏ thanh chạy khi đang tìm TX.
        var waitBlock = document.getElementById('guest-trip-wait-block');
        if (waitBlock) {
            var waitKind = booking.wait_progress ? booking.wait_progress.kind : '';
            var showWaitBlock = waitKind === 'review';
            waitBlock.classList.toggle('guest-trip-wait--review', waitKind === 'review');
            waitBlock.classList.remove('guest-trip-wait--bar-only', 'guest-trip-wait--driver_search');
            if (showWaitBlock && window.WaitProgress) {
                window.WaitProgress.mount(waitBlock, booking.wait_progress);
                var hintEl = qs('[data-field="wait_hint"]', waitBlock);
                if (hintEl) {
                    hintEl.classList.toggle('d-none', !booking.wait_progress.hint);
                }
            } else {
                waitBlock.classList.add('d-none');
                waitBlock.classList.remove('guest-trip-wait--bar-only', 'guest-trip-wait--driver_search', 'guest-trip-wait--review');
            }
        }

        renderTripStepper(booking);

        if (window.GuestTripSheet && window.GuestTripSheet.sync) {
            window.GuestTripSheet.sync();
        }
        if (window.GuestTripSheet && window.GuestTripSheet.syncLocateFabLift) {
            window.requestAnimationFrame(function () {
                window.GuestTripSheet.syncLocateFabLift();
            });
        }

        var scheduledPickup = isScheduledPickup(booking);
        // Đã có TX nhận chuyến: bỏ Lịch đón / Quãng đường trong meta — Tổng chuyến vẫn hiện riêng.
        var showTripMeta = !booking.has_driver;
        setDetailRow(
            card,
            'pickup_mode_wrap',
            'pickup_mode_label',
            showTripMeta && !scheduledPickup ? (booking.pickup_mode_label || 'Đón ngay') : '',
        );
        setDetailRow(
            card,
            'pickup_time_wrap',
            'pickup_time_label',
            showTripMeta && scheduledPickup ? (booking.pickup_time_label || '') : '',
        );
        setDetailRow(
            card,
            'service_date_wrap',
            'service_date_label',
            showTripMeta && scheduledPickup ? serviceDateLabel(booking) : '',
        );

        var distanceKm = Number(booking.distance_km || 0);
        setDetailRow(
            card,
            'trip_distance_wrap',
            'trip_distance_km',
            showTripMeta && distanceKm > 0 ? distanceKm + ' km' : '',
        );

        var priceLabel = booking.total_price_label || '';
        if (!priceLabel && booking.total_price > 0) {
            priceLabel = Number(booking.total_price).toLocaleString('vi-VN') + ' đ';
        }
        var totalWrap = qs('[data-field="trip_total_wrap"]', card);
        var totalValue = qs('[data-field="trip_total_value"]', card);
        if (totalWrap && totalValue) {
            if (priceLabel) {
                totalValue.textContent = priceLabel;
                totalWrap.classList.remove('d-none');
            } else {
                totalValue.textContent = '';
                totalWrap.classList.add('d-none');
            }
        }

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
        var changeDropoffBtn = qs('[data-field="change_dropoff_btn"]', card);
        if (changeDropoffBtn) {
            changeDropoffBtn.classList.toggle('d-none', !booking.can_change_dropoff);
            var pickupLat = Number(booking.pickup_lat);
            var pickupLng = Number(booking.pickup_lng);
            if (Number.isFinite(pickupLat) && Number.isFinite(pickupLng)) {
                changeDropoffBtn.setAttribute('data-address-map-origin-lat', String(pickupLat));
                changeDropoffBtn.setAttribute('data-address-map-origin-lng', String(pickupLng));
            } else {
                changeDropoffBtn.removeAttribute('data-address-map-origin-lat');
                changeDropoffBtn.removeAttribute('data-address-map-origin-lng');
            }
        }

        renderDriverPanel(booking);
        if (window.GuestTripLiveMap && window.GuestTripLiveMap.updateFromBooking) {
            window.GuestTripLiveMap.updateFromBooking(booking);
        }
        syncTripStatusChrome(booking, searching, tracking);
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
            return Promise.reject(new Error('Không tải được lý do hủy.'));
        }
        // Dùng chung danh sách lý do với tài xế.
        return window.CancellationReasonModal.pick({
            audience: 'driver',
            title: 'Chọn lý do hủy',
            hint: 'Tài xế đã nhận chuyến — vui lòng chọn lý do trước khi hủy.',
            requireReason: true,
        });
    }

    function submitCancel(reasonId, reasonNote) {
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
        if (reasonNote) {
            payload.cancellation_reason_note = reasonNote;
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
                if (window.BookingRouteDraft && window.BookingRouteDraft.clear) {
                    window.BookingRouteDraft.clear();
                } else {
                    try {
                        sessionStorage.removeItem('appdatxe:bookingRouteDraft');
                    } catch (e) { /* ignore */ }
                }
                currentBooking = null;
                renderBooking(null);

                var goHome = function () {
                    if (window.CustomerScrollDock && window.CustomerScrollDock.focusHomePage) {
                        window.CustomerScrollDock.focusHomePage();
                    } else {
                        window.location.href = '/';
                    }
                };

                // Chỉ popup khi bị khóa / cảnh báo; hủy thành công về trang chủ luôn.
                if (data.cancel_blocked && data.block_message && window.AppDialog && window.AppDialog.alert) {
                    window.AppDialog.alert(data.block_message, {
                        variant: 'warning',
                        title: 'Thông báo',
                    }).then(goHome);
                } else {
                    goHome();
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
            // Chưa có TX nhận: hủy thẳng. Đã nhận: chọn lý do (chung với TX).
            if (!currentBooking.cancel_requires_reason) {
                return submitCancel(null);
            }
            return pickCancelReason().then(function (pick) {
                if (!pick || !pick.reasonId) {
                    return;
                }
                return submitCancel(pick.reasonId, pick.note || '');
            });
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
        var counter = document.getElementById('guest-trip-vehicle-photo-counter');
        var prevBtn = overlay ? overlay.querySelector('[data-guest-vehicle-photo-prev]') : null;
        var nextBtn = overlay ? overlay.querySelector('[data-guest-vehicle-photo-next]') : null;
        if (!overlay || !image) {
            return;
        }

        var galleryUrls = [];
        var galleryIndex = 0;
        var galleryAlt = 'Ảnh xe';

        function syncNav() {
            var multi = galleryUrls.length > 1;
            if (prevBtn) {
                prevBtn.classList.toggle('d-none', !multi);
                prevBtn.disabled = !multi;
            }
            if (nextBtn) {
                nextBtn.classList.toggle('d-none', !multi);
                nextBtn.disabled = !multi;
            }
            if (counter) {
                if (multi) {
                    counter.textContent = (galleryIndex + 1) + ' / ' + galleryUrls.length;
                    counter.classList.remove('d-none');
                } else {
                    counter.textContent = '';
                    counter.classList.add('d-none');
                }
            }
        }

        function showGalleryImage() {
            var url = galleryUrls[galleryIndex] || '';
            if (!url) {
                return;
            }
            image.src = url;
            image.alt = galleryAlt + (galleryUrls.length > 1 ? (' (' + (galleryIndex + 1) + '/' + galleryUrls.length + ')') : '');
            syncNav();
        }

        function closeOverlay() {
            overlay.classList.add('d-none');
            overlay.setAttribute('hidden', 'hidden');
            image.removeAttribute('src');
            galleryUrls = [];
            galleryIndex = 0;
            document.body.classList.remove('guest-trip-vehicle-photo-open');
        }

        function openOverlay(urls, altText, startIndex) {
            galleryUrls = (urls || []).filter(Boolean);
            if (!galleryUrls.length) {
                return;
            }
            galleryAlt = altText || 'Ảnh xe';
            galleryIndex = Math.max(0, Math.min(Number(startIndex) || 0, galleryUrls.length - 1));
            showGalleryImage();
            overlay.classList.remove('d-none');
            overlay.removeAttribute('hidden');
            document.body.classList.add('guest-trip-vehicle-photo-open');
        }

        function stepGallery(delta) {
            if (galleryUrls.length < 2) {
                return;
            }
            galleryIndex = (galleryIndex + delta + galleryUrls.length) % galleryUrls.length;
            showGalleryImage();
        }

        function urlsFromTrigger(trigger) {
            try {
                var parsed = JSON.parse(trigger.dataset.vehiclePhotoUrls || '[]');
                if (Array.isArray(parsed) && parsed.length) {
                    return parsed.filter(Boolean);
                }
            } catch (e) { /* noop */ }
            return trigger.dataset.vehiclePhotoUrl ? [trigger.dataset.vehiclePhotoUrl] : [];
        }

        document.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-field="driver_photo_wrap"].is-zoomable');
            if (!trigger) {
                return;
            }
            event.preventDefault();
            var photo = trigger.querySelector('[data-field="driver_photo"]');
            openOverlay(urlsFromTrigger(trigger), photo ? photo.alt : 'Ảnh xe', 0);
        });

        document.addEventListener('keydown', function (event) {
            var trigger = event.target.closest('[data-field="driver_photo_wrap"].is-zoomable');
            if (trigger && (event.key === 'Enter' || event.key === ' ')) {
                event.preventDefault();
                var photo = trigger.querySelector('[data-field="driver_photo"]');
                openOverlay(urlsFromTrigger(trigger), photo ? photo.alt : 'Ảnh xe', 0);
                return;
            }
            if (overlay.classList.contains('d-none')) {
                return;
            }
            if (event.key === 'Escape') {
                closeOverlay();
            } else if (event.key === 'ArrowLeft') {
                event.preventDefault();
                stepGallery(-1);
            } else if (event.key === 'ArrowRight') {
                event.preventDefault();
                stepGallery(1);
            }
        });

        if (prevBtn) {
            prevBtn.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                stepGallery(-1);
            });
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                stepGallery(1);
            });
        }

        overlay.querySelectorAll('[data-close-guest-vehicle-photo]').forEach(function (btn) {
            btn.addEventListener('click', closeOverlay);
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

    function guestAlert(message, options) {
        if (window.AppDialog && typeof window.AppDialog.alert === 'function') {
            return window.AppDialog.alert(message, options || { variant: 'warning' });
        }
        window.alert(message);
        return Promise.resolve();
    }

    function guestConfirmChangeDropoff(message) {
        if (window.AppDialog && typeof window.AppDialog.confirm === 'function') {
            return window.AppDialog.confirm({
                title: 'Đổi điểm đến',
                message: message,
                confirmText: 'Xác nhận',
                cancelText: 'Hủy bỏ',
                variant: 'warning',
            });
        }
        return Promise.resolve(window.confirm(message));
    }

    function changeDropoffPayload(detail, dropDetail, lat, lng) {
        return {
            booking_reference: currentBooking.booking_reference,
            dropoff_detail: dropDetail,
            dropoff_lat: lat,
            dropoff_lng: lng,
            dropoff_address: detail.province || 'TP.HCM',
            contact_phone: getContactPhone(),
            booking_browser_id: getBrowserId(),
        };
    }

    function postChangeDropoff(detail, dropDetail, lat, lng) {
        var token = document.querySelector('meta[name="csrf-token"]');
        return fetch(window.__bookingChangeDropoffUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(changeDropoffPayload(detail, dropDetail, lat, lng)),
        }).then(function (r) {
            return r.json().then(function (data) {
                if (!r.ok) {
                    throw new Error(data.message || 'Không đổi được điểm đến.');
                }
                return data;
            });
        });
    }

    function previewChangeDropoff(detail, dropDetail, lat, lng) {
        var url = window.__bookingPreviewChangeDropoffUrl;
        if (!url) {
            return Promise.resolve(null);
        }
        var token = document.querySelector('meta[name="csrf-token"]');
        return fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(changeDropoffPayload(detail, dropDetail, lat, lng)),
        }).then(function (r) {
            return r.json().then(function (data) {
                if (!r.ok) {
                    throw new Error(data.message || 'Không tính được giá mới.');
                }
                return data;
            });
        });
    }

    function submitChangeDropoff(detail) {
        if (!currentBooking || !window.__bookingChangeDropoffUrl) {
            return;
        }
        var dropDetail = String((detail && detail.address) || '').trim();
        var lat = detail && detail.lat != null ? Number(detail.lat) : NaN;
        var lng = detail && detail.lng != null ? Number(detail.lng) : NaN;
        if (!dropDetail || !Number.isFinite(lat) || !Number.isFinite(lng)) {
            guestAlert('Chọn điểm đến hợp lệ trên bản đồ.', {
                variant: 'warning',
                title: 'Đổi điểm đến',
            });
            return;
        }

        previewChangeDropoff(detail, dropDetail, lat, lng)
            .then(function (preview) {
                var currentLabel = (preview && preview.current_price_label)
                    || currentBooking.total_price_label
                    || '—';
                var newLabel = (preview && preview.new_price_label) || '—';
                var address = (preview && preview.dropoff_detail) || dropDetail;
                var message = 'Đổi điểm đến thành: ' + address
                    + '\n\nGiá hiện tại: ' + currentLabel
                    + '\nGiá mới: ' + newLabel;

                return guestConfirmChangeDropoff(message).then(function (ok) {
                    if (!ok) {
                        // Map picker vẫn mở (change-dropoff keepOpen); nếu đã đóng thì mở lại.
                        var mapModal = document.getElementById('addressMapPickerModal');
                        var mapStillOpen = mapModal && mapModal.classList.contains('show');
                        if (!mapStillOpen && window.AddressMapPicker
                            && typeof window.AddressMapPicker.reopenChangeDropoff === 'function') {
                            window.AddressMapPicker.reopenChangeDropoff();
                        }
                        return null;
                    }
                    return postChangeDropoff(detail, dropDetail, lat, lng).then(function (data) {
                        if (window.AddressMapPicker && typeof window.AddressMapPicker.close === 'function') {
                            window.AddressMapPicker.close();
                        }
                        return data;
                    });
                });
            })
            .then(function (data) {
                if (!data) {
                    return;
                }
                if (data.booking) {
                    renderBooking(data.booking);
                } else {
                    fetchStatus();
                }
                if (data.message) {
                    guestAlert(data.message, {
                        variant: 'success',
                        title: 'Đổi điểm đến',
                    });
                }
            })
            .catch(function (err) {
                guestAlert(err.message || 'Không đổi được điểm đến.', {
                    variant: 'danger',
                    title: 'Đổi điểm đến',
                });
            });
    }

    function bindDriverFocusToggle() {
        var panel = document.getElementById('guest-trip-driver-panel');
        if (!panel || panel.dataset.focusToggleBound === '1') {
            return;
        }
        panel.dataset.focusToggleBound = '1';
        panel.setAttribute('role', 'button');
        panel.setAttribute('tabindex', '0');
        panel.setAttribute('aria-label', 'Nhấn để xem vị trí tài xế trên bản đồ; nhấn lần nữa để thu sheet và xem khoảng cách');
        panel.title = 'Nhấn: zoom TX · Nhấn lại: thu sheet + khoảng cách';

        function onActivate(event) {
            if (event.target.closest('.guest-trip-driver__chat, .guest-trip-driver__call, .guest-trip-driver__actions, .guest-trip-driver__call-reveal, .trip-chat-toggle, .trip-chat-panel, a, button, input, textarea')) {
                return;
            }
            if (event.target.closest('[data-field="driver_photo_wrap"].is-zoomable')) {
                return;
            }
            if (window.GuestTripLiveMap && window.GuestTripLiveMap.toggleDriverFocusCamera) {
                window.GuestTripLiveMap.toggleDriverFocusCamera();
            }
        }

        panel.addEventListener('click', onActivate);
        panel.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                onActivate(event);
            }
        });
    }

    function init() {
        bindReview();
        bindVehiclePhotoOverlay();
        bindDriverFocusToggle();
        var cancelBtn = document.getElementById('guest-trip-cancel-btn');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', cancelTrip);
        }
        document.addEventListener('addressmap:applied', function (e) {
            var d = e.detail || {};
            if (d.targetInputId !== 'guest-change-dropoff-detail') {
                return;
            }
            submitChangeDropoff(d);
        });
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
