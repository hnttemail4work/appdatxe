/**
 * Lưu phiên đặt chuyến (sessionStorage): mã chuyến + QR giới thiệu.
 * Còn section này = chưa đặt được cuốc mới; mất section = có thể đặt lại.
 */
(function () {
    var STORAGE_KEY = 'guest_booking_session';
    var QR_SMALL = 72;
    var QR_LARGE = 220;
    var qrLibLoading = false;
    var rootEl = null;
    var currentReferralUrl = '';
    var overlayBound = false;

    function normalizePayload(raw) {
        if (!raw || !raw.trip_code) {
            return null;
        }

        var referralCode = raw.referral_code || raw.code || '';
        var referralUrl = raw.referral_url || raw.url || '';

        if (!referralUrl && referralCode) {
            referralUrl = window.location.origin + '/?ref=' + encodeURIComponent(referralCode);
        }

        return {
            trip_code: String(raw.trip_code),
            booking_reference: raw.booking_reference ? String(raw.booking_reference) : '',
            contact_phone: raw.contact_phone ? String(raw.contact_phone) : '',
            hotline_phone: raw.hotline_phone ? String(raw.hotline_phone) : '',
            referral_code: referralCode ? String(referralCode) : '',
            referral_url: referralUrl,
            referral_discount_percent: Number(raw.referral_discount_percent || raw.discount_percent) || 0,
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
        closeQrOverlay();
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

    function loadQrLib(cb) {
        if (typeof QRCode !== 'undefined') {
            cb();
            return;
        }
        if (qrLibLoading) {
            var timer = setInterval(function () {
                if (typeof QRCode !== 'undefined') {
                    clearInterval(timer);
                    cb();
                }
            }, 50);
            return;
        }
        qrLibLoading = true;
        var script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js';
        script.onload = cb;
        document.head.appendChild(script);
    }

    function drawQr(container, url, size) {
        if (!container || !url) {
            return;
        }
        container.innerHTML = '';
        new QRCode(container, {
            text: url,
            width: size,
            height: size,
            colorDark: '#0f172a',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.M,
        });
    }

    function renderQr(url) {
        var wrap = document.getElementById('booking-active-referral-qr');
        if (!wrap || !url) {
            return;
        }

        currentReferralUrl = url;
        loadQrLib(function () {
            drawQr(wrap, url, QR_SMALL);
        });
    }

    function renderLargeQr(url) {
        var wrap = document.getElementById('booking-active-referral-qr-large');
        if (!wrap || !url) {
            return;
        }

        loadQrLib(function () {
            drawQr(wrap, url, QR_LARGE);
        });
    }

    function openQrOverlay() {
        if (!currentReferralUrl) {
            return;
        }

        var overlay = document.getElementById('booking-active-referral-qr-overlay');
        if (!overlay) {
            return;
        }

        renderLargeQr(currentReferralUrl);
        overlay.classList.remove('d-none');
        overlay.removeAttribute('hidden');
        document.body.classList.add('booking-active-referral-qr-open');
    }

    function closeQrOverlay() {
        var overlay = document.getElementById('booking-active-referral-qr-overlay');
        if (!overlay) {
            return;
        }

        overlay.classList.add('d-none');
        overlay.setAttribute('hidden', 'hidden');
        document.body.classList.remove('booking-active-referral-qr-open');

        var large = document.getElementById('booking-active-referral-qr-large');
        if (large) {
            large.innerHTML = '';
        }
    }

    function bindOverlay() {
        if (overlayBound) {
            return;
        }
        overlayBound = true;

        var openBtn = document.getElementById('booking-active-referral-qr-btn');
        if (openBtn) {
            openBtn.addEventListener('click', openQrOverlay);
        }

        document.querySelectorAll('[data-close-referral-qr]').forEach(function (el) {
            el.addEventListener('click', closeQrOverlay);
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeQrOverlay();
            }
        });
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
        var locationShared = !!(driver && driver.location_shared)
            || !!(booking && booking.driver_location_shared);

        if (stage !== 'assigned' || !locationShared) {
            distanceLine = '';
            etaLine = '';
        } else if (!distanceLine && driver && driver.distance_label) {
            distanceLine = 'Tài xế cách bạn ' + driver.distance_label;
        } else if (!distanceLine && booking && booking.driver_distance_label) {
            distanceLine = 'Tài xế cách bạn ' + booking.driver_distance_label;
        }

        if (stage !== 'assigned' || !locationShared) {
            etaLine = '';
        }

        return {
            statusLine: statusLine,
            distanceLine: distanceLine,
            etaLine: etaLine,
        };
    }

    function updateDriverPanel(booking) {
        var panel = document.getElementById('booking-active-driver-panel');
        if (!panel) {
            return;
        }

        var driver = booking && booking.driver ? booking.driver : null;
        if (!driver || !booking.has_driver) {
            panel.classList.add('d-none');
            return;
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
        var referralWrap = document.getElementById('booking-active-referral-wrap');

        if (tripCodeEl) {
            tripCodeEl.textContent = payload.trip_code;
        }

        updateNotes(payload);
        updateBookingSnapshot(payload.booking || null);

        if (payload.referral_code && payload.referral_url && referralWrap) {
            referralWrap.classList.remove('d-none');
            renderQr(payload.referral_url);
            bindOverlay();
        } else if (referralWrap) {
            referralWrap.classList.add('d-none');
        }

        rootEl.classList.remove('d-none');
    }

    function saveFromBooking(booking, extra) {
        if (!booking) {
            return null;
        }

        return save({
            trip_code: booking.trip_code,
            booking_reference: booking.booking_reference || (extra && extra.booking_reference) || '',
            contact_phone: (extra && extra.contact_phone) || '',
            hotline_phone: resolveHotlinePhone() || ((extra && extra.hotline_phone) || ''),
            referral_code: (extra && extra.referral_code) || '',
            referral_url: (extra && extra.referral_url) || '',
            referral_discount_percent: (extra && extra.referral_discount_percent) || 0,
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
                referral_code: existing.referral_code,
                referral_url: existing.referral_url,
                referral_discount_percent: existing.referral_discount_percent,
            });
            updateBookingSnapshot(data.booking);
            return;
        }

        if (!data || !data.duplicate) {
            clear();
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
                    updateBookingSnapshot(data.booking);
                    var stored = load();
                    if (stored) {
                        stored.booking = data.booking;
                        stored.driver_distance_label = data.booking.driver_distance_label || null;
                        stored.driver_eta_label = data.booking.driver_eta_label || null;
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
                    clear();
                }
            })
            .catch(function () {});
    }

    function init() {
        bindOverlay();
        syncHeroHotline();
        var flashPayload = window.__bookingSuccess;
        if (flashPayload) {
            save(flashPayload);
        } else {
            render();
        }
        syncHeroHotline();

        pollActiveBookingStatus();
        window.setInterval(pollActiveBookingStatus, 15000);
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
