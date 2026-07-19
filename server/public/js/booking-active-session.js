/**
 * Lưu phiên đặt chuyến (sessionStorage): mã chuyến đang active.
 * Còn section này = chưa đặt được cuốc mới; mất section = có thể đặt lại.
 */
(function () {
    var STORAGE_KEY = 'guest_booking_session';
    var rootEl = null;

    function normalizePayload(raw) {
        if (!raw || !raw.trip_code) {
            return null;
        }

        return {
            trip_code: String(raw.trip_code),
            booking_reference: raw.booking_reference ? String(raw.booking_reference) : '',
            contact_phone: raw.contact_phone ? String(raw.contact_phone) : '',
            hotline_phone: raw.hotline_phone ? String(raw.hotline_phone) : '',
            booking: raw.booking && typeof raw.booking === 'object' ? raw.booking : null,
        };
    }

    function load() {
        try {
            var raw = sessionStorage.getItem(STORAGE_KEY);
            if (!raw) {
                return null;
            }
            return normalizePayload(JSON.parse(raw));
        } catch (e) {
            return null;
        }

    }

    function save(raw) {
        var payload = normalizePayload(raw);
        if (!payload) {
            return null;
        }

        try {
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
        } catch (e) {}

        render(payload);
        return payload;
    }

    function clear() {
        try {
            sessionStorage.removeItem(STORAGE_KEY);
        } catch (e) {}
        hide();
    }

    function hide() {
        if (!rootEl) {
            rootEl = document.getElementById('booking-active-session');
        }
        if (rootEl) {
            rootEl.classList.add('d-none');
        }
    }

    function resolveHotlinePhone() {
        if (window.__appContactPhone) {
            return String(window.__appContactPhone);
        }

        try {
            var stored = load();
            if (stored && stored.hotline_phone) {
                return String(stored.hotline_phone);
            }
        } catch (e) {}

        return '';
    }

    function syncHeroHotline() {
        var hotline = resolveHotlinePhone();
        if (!hotline) {
            return;
        }

        var tel = hotline.replace(/[^\d+]/g, '');
        var zaloDigits = tel.replace(/^\+/, '');
        if (zaloDigits.indexOf('0') === 0) {
            zaloDigits = '84' + zaloDigits.slice(1);
        } else if (zaloDigits.indexOf('84') !== 0) {
            zaloDigits = '84' + zaloDigits;
        }

        document.querySelectorAll('[data-contact-hotline="phone"]').forEach(function (el) {
            el.setAttribute('href', 'tel:' + tel);
            el.setAttribute('aria-label', 'Gọi tổng đài ' + hotline);
        });

        document.querySelectorAll('[data-contact-hotline="zalo"]').forEach(function (el) {
            el.setAttribute('href', 'https://zalo.me/' + zaloDigits);
            el.setAttribute('aria-label', 'Chat Zalo tổng đài ' + hotline);
        });
    }

    function updateNotes(payload) {
        var contactNote = document.getElementById('booking-active-session-note-contact');
        var stored = payload || load();
        var customerPhone = stored && stored.contact_phone ? String(stored.contact_phone) : '';

        if (contactNote) {
            if (customerPhone) {
                contactNote.innerHTML = 'Vui lòng đợi tài xế nhận chuyến, chúng tôi sẽ liên hệ lại cho bạn qua số điện thoại <strong>'
                    + customerPhone
                    + '</strong>.';
            } else {
                contactNote.textContent = 'Vui lòng đợi tài xế nhận chuyến, chúng tôi sẽ liên hệ lại cho bạn qua số điện thoại của bạn.';
            }
        }
    }

    function setInlineText(el, text, visible) {
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

    function buildVehicleLine(driver) {
        var parts = [];
        var typeLabel = driver.vehicle_type_label || '';
        var vehicleName = driver.vehicle_name || '';

        if (vehicleName && vehicleName.toLowerCase() !== typeLabel.toLowerCase()) {
            parts.push(vehicleName);
        }
        if (typeLabel) {
            parts.push(typeLabel);
        }
        if (driver.vehicle_plate) {
            parts.push(driver.vehicle_plate);
        }

        return parts.join(' · ');
    }

    function resolveDriverStatusLines(driver, booking) {
        var statusLine = '';
        var distanceLine = '';
        var etaLine = '';

        if (driver) {
            statusLine = driver.status_line || '';
            distanceLine = driver.distance_line || '';
            etaLine = driver.eta_line || '';
        }

        if (!statusLine && booking) {
            statusLine = booking.driver_status_line || '';
            if (!distanceLine) {
                distanceLine = booking.driver_distance_line || '';
            }
            if (!etaLine) {
                etaLine = booking.driver_eta_line || '';
            }
        }

        if (!statusLine) {
            var hint = (driver && driver.proximity_hint) || (booking && booking.driver_proximity_hint) || '';
            if (hint) {
                var hintParts = String(hint).split('\n');
                statusLine = hintParts[0] || '';
                distanceLine = distanceLine || hintParts[1] || '';
                etaLine = etaLine || hintParts[2] || '';
            }
        }

        var stage = String((driver && driver.stage) || 'assigned');
        var movementConfirmed = !!(booking && booking.driver_movement_confirmed)
            || !!(driver && driver.movement_confirmed);

        if (stage !== 'assigned' && stage !== 'at_pickup') {
            distanceLine = '';
            etaLine = '';
        } else if (stage === 'at_pickup') {
            etaLine = '';
        } else {
            if (!distanceLine && driver && driver.distance_label) {
                distanceLine = 'Tài xế cách bạn ' + driver.distance_label;
            } else if (!distanceLine && booking && booking.driver_distance_label) {
                distanceLine = 'Tài xế cách bạn ' + booking.driver_distance_label;
            }

            if (!movementConfirmed) {
                etaLine = '';
            }
        }

        return {
            statusLine: statusLine,
            distanceLine: distanceLine,
            etaLine: etaLine,
        };
    }

    function formatCountdown(deadlineIso) {
        if (!deadlineIso) {
            return '';
        }
        var end = Date.parse(deadlineIso);
        if (!end) {
            return '';
        }
        var left = Math.max(0, Math.floor((end - Date.now()) / 1000));
        var m = Math.floor(left / 60);
        var s = left % 60;
        return 'Còn ' + m + ':' + String(s).padStart(2, '0');
    }

    function updateFindingDriverUi(booking) {
        var finding = document.getElementById('booking-finding-driver');
        if (!finding) {
            return;
        }
        var wait = booking && booking.wait_progress ? booking.wait_progress : null;
        var titleEl = document.getElementById('booking-finding-driver-title');
        var hintEl = document.getElementById('booking-finding-driver-hint');
        var cdEl = document.getElementById('booking-finding-driver-countdown');
        if (titleEl) {
            titleEl.textContent = (wait && wait.label)
                ? wait.label
                : 'Đang tìm tài xế gần bạn…';
        }
        if (hintEl) {
            hintEl.textContent = (wait && wait.hint)
                ? wait.hint
                : 'Hệ thống sẽ tự hủy sau 10 phút nếu không có tài xế nhận.';
        }
        if (cdEl) {
            var text = wait && wait.deadline_at ? formatCountdown(wait.deadline_at) : '';
            if (text) {
                cdEl.textContent = text;
                cdEl.hidden = false;
                cdEl.classList.remove('d-none');
            } else {
                cdEl.textContent = '';
                cdEl.hidden = true;
                cdEl.classList.add('d-none');
            }
        }
    }

    function updateDriverPanel(booking) {
        var panel = document.getElementById('booking-active-driver-panel');
        if (!panel) {
            return;
        }

        var finding = document.getElementById('booking-finding-driver');
        var driver = booking && booking.driver ? booking.driver : null;
        if (!driver || !booking.has_driver) {
            panel.classList.add('d-none');
            if (finding) {
                finding.classList.remove('d-none');
            }
            updateFindingDriverUi(booking);
            return;
        }
        if (finding) {
            finding.classList.add('d-none');
        }

        var nameText = driver.name || '—';
        if (driver.code) {
            nameText += ' · ' + driver.code;
        }

        var statusLines = resolveDriverStatusLines(driver, booking);

        var photoWrap = document.getElementById('booking-active-driver-vehicle-photo-wrap');
        var photoEl = document.getElementById('booking-active-driver-vehicle-photo');
        var vehiclePhoto = driver.vehicle_photo_url || '';
        if (photoWrap && photoEl) {
            if (vehiclePhoto) {
                photoEl.src = vehiclePhoto;
                photoEl.alt = driver.vehicle_name || driver.vehicle_type_label || 'Ảnh xe';
                photoWrap.classList.remove('d-none');
                photoWrap.removeAttribute('aria-hidden');
            } else {
                photoEl.removeAttribute('src');
                photoEl.alt = '';
                photoWrap.classList.add('d-none');
                photoWrap.setAttribute('aria-hidden', 'true');
            }
        }

        setInlineText(document.getElementById('booking-active-driver-name'), nameText, true);
        var vehicleLine = buildVehicleLine(driver);
        setInlineText(
            document.getElementById('booking-active-driver-vehicle-line'),
            vehicleLine,
            !!vehicleLine,
        );
        setInlineText(document.getElementById('booking-active-driver-status'), statusLines.statusLine, !!statusLines.statusLine);
        setInlineText(document.getElementById('booking-active-driver-distance'), statusLines.distanceLine, !!statusLines.distanceLine);
        setInlineText(document.getElementById('booking-active-driver-eta'), statusLines.etaLine, !!statusLines.etaLine);

        panel.classList.remove('d-none');
    }

    function updateBookingSnapshot(booking) {
        updateDriverPanel(booking || null);
    }

    function render(payload) {
        payload = payload || load();
        if (!payload) {
            hide();
            return;
        }

        rootEl = document.getElementById('booking-active-session');
        if (!rootEl) {
            return;
        }

        var tripCodeEl = document.getElementById('booking-active-trip-code');

        if (tripCodeEl) {
            tripCodeEl.textContent = payload.trip_code;
        }

        updateNotes(payload);
        updateBookingSnapshot(payload.booking || null);

        rootEl.classList.remove('d-none');
    }

    function saveFromBooking(booking, extra) {
        if (!booking) {
            return null;
        }

        return save({
            trip_code: booking.trip_code,
            booking_reference: booking.booking_reference || (extra && extra.booking_reference) || '',
            contact_phone: (extra && extra.contact_phone) || booking.contact_phone || '',
            hotline_phone: resolveHotlinePhone() || ((extra && extra.hotline_phone) || ''),
            booking: booking,
        });
    }

    function syncWithCheckResult(data) {
        if (data && data.duplicate && data.reason === 'browser_cancel') {
            return;
        }

        if (data && data.duplicate && data.booking && (data.reason === 'browser' || data.reason === 'phone')) {
            var existing = load() || {};
            saveFromBooking(data.booking, {
                booking_reference: data.booking.booking_reference || existing.booking_reference,
                contact_phone: existing.contact_phone,
            });
            updateBookingSnapshot(data.booking);
            return;
        }

        if (!data || !data.duplicate) {
            var stored = load();
            if (!stored || !stored.booking || stored.booking.can_review !== true) {
                clear();
            }
        }
    }

    function hasActiveSession() {
        return !!load();
    }

    function shouldPollBookingStatus() {
        if (!window.__bookingCheckDuplicateUrl) {
            return false;
        }
        if (hasActiveSession()) {
            return true;
        }
        if (window.BookingBrowserGuard && window.BookingBrowserGuard.hasActiveBlock && window.BookingBrowserGuard.hasActiveBlock()) {
            return true;
        }
        return false;
    }

    function pollActiveBookingStatus() {
        if (!shouldPollBookingStatus()) {
            return;
        }

        var payload = load();

        var params = new URLSearchParams();
        if (payload && payload.contact_phone) {
            params.set('contact_phone', payload.contact_phone);
        }
        if (window.BookingBrowserGuard && window.BookingBrowserGuard.getBrowserSessionId) {
            var browserId = window.BookingBrowserGuard.getBrowserSessionId();
            if (browserId) {
                params.set('booking_browser_id', browserId);
            }
        }

        if (!params.toString()) {
            return;
        }

        fetch(window.__bookingCheckDuplicateUrl + '?' + params.toString(), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (window.BookingBrowserGuard && window.BookingBrowserGuard.applyCheckResult) {
                    window.BookingBrowserGuard.applyCheckResult(data || { duplicate: false });
                    return;
                }

                if (data && data.duplicate && data.booking) {
                    if (data.booking.booking_status === 'cancelled'
                        || data.booking.trip_status === 'cancelled') {
                        clear();
                        return;
                    }
                    updateBookingSnapshot(data.booking);
                    var stored = load();
                    if (stored) {
                        stored.booking = data.booking;
                        stored.driver_distance_label = data.booking.driver_distance_label || null;
                        stored.driver_eta_label = data.booking.driver_eta_label || null;
                        stored.driver_movement_confirmed = !!data.booking.driver_movement_confirmed;
                        stored.driver_status_line = data.booking.driver_status_line || null;
                        stored.driver_distance_line = data.booking.driver_distance_line || null;
                        stored.driver_eta_line = data.booking.driver_eta_line || null;
                        stored.driver_proximity_hint = data.booking.driver_proximity_hint || null;
                        stored.progress_label = data.booking.progress_label || null;
                        try {
                            sessionStorage.setItem(STORAGE_KEY, JSON.stringify(stored));
                        } catch (e) {}
                    }
                    return;
                }

                if (!data || !data.duplicate) {
                    var stored = load();
                    if (!stored || !stored.booking || stored.booking.can_review !== true) {
                        clear();
                    }
                }
            })
            .catch(function () {});
    }

    function init() {
        syncHeroHotline();
        var flashPayload = window.__bookingSuccess;
        if (flashPayload) {
            save(flashPayload);
        } else {
            render();
        }
        syncHeroHotline();

        pollActiveBookingStatus();

        if (window.IdlePoll) {
            window.IdlePoll.create({ onPoll: pollActiveBookingStatus }).start();
        } else {
            window.setInterval(pollActiveBookingStatus, 5000);
        }
    }

    window.BookingActiveSession = {
        STORAGE_KEY: STORAGE_KEY,
        load: load,
        save: save,
        clear: clear,
        render: render,
        hasActiveSession: hasActiveSession,
        syncWithCheckResult: syncWithCheckResult,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
