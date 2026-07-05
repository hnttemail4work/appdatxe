/**
 * Trang Chuyến — theo dõi chuyến đã đặt và đánh giá sau khi hoàn tất.
 */
(function () {
    var POLL_MS = 15000;
    var selectedSentiment = '';
    var currentBooking = null;

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

    function formatSchedule(booking) {
        if (booking.pickup_time_label && booking.service_date) {
            var dateOnly = String(booking.service_date).split(' ')[0];
            return booking.pickup_time_label + ' · ' + dateOnly;
        }
        return booking.service_date || booking.pickup_time_label || '';
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
        if (window.BookingActiveSession && window.BookingActiveSession.load) {
            var stored = window.BookingActiveSession.load();
            if (stored && stored.contact_phone) {
                return String(stored.contact_phone);
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
            contact_phone: stored.contact_phone || getContactPhone(),
            referral_code: stored.referral_code || '',
            referral_url: stored.referral_url || '',
            referral_discount_percent: stored.referral_discount_percent || 0,
        });
    }

    function clearActiveSessionIfDone(booking) {
        if (!booking || booking.is_active) {
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
        setText(qs('[data-field="driver_plate"]', panel), driver.vehicle_plate || '', !!driver.vehicle_plate);

        var statusLine = booking.driver_status_line || driver.status_line || '';
        setText(qs('[data-field="driver_status"]', panel), statusLine, !!statusLine);

        var liveWrap = qs('[data-field="driver_live_wrap"]', panel);
        var distanceLine = booking.driver_distance_line || driver.distance_line || '';
        var etaLine = booking.driver_eta_line || driver.eta_line || '';
        setText(qs('[data-field="driver_distance"]', panel), distanceLine, !!distanceLine);
        setText(qs('[data-field="driver_eta"]', panel), etaLine, !!etaLine);
        if (liveWrap) {
            liveWrap.classList.toggle('d-none', !distanceLine && !etaLine);
        }

        panel.classList.remove('d-none');
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
        currentBooking = booking;
        var empty = document.getElementById('guest-trip-empty');
        var card = document.getElementById('guest-trip-card');
        if (!empty || !card) {
            return;
        }

        if (!booking) {
            empty.classList.remove('d-none');
            card.classList.add('d-none');
            return;
        }

        empty.classList.add('d-none');
        card.classList.remove('d-none');

        setText(qs('[data-field="trip_code"]', card), booking.trip_code || '—', true);

        var routeParts = parseRoute(booking.route);
        setText(qs('[data-field="route_from"]', card), routeParts.from, true);
        setText(qs('[data-field="route_to"]', card), routeParts.to, true);

        var scheduleText = formatSchedule(booking);
        setWrapText(
            qs('[data-field="schedule_wrap"]', card),
            qs('[data-field="schedule_display"]', card),
            scheduleText,
        );
        setText(qs('[data-field="vehicle_label"]', card), booking.vehicle_label || '', !!booking.vehicle_label);

        var distanceKm = Number(booking.distance_km || 0);
        var distanceWrap = qs('[data-field="distance_wrap"]', card);
        var distanceEl = qs('[data-field="distance_km"]', card);
        if (distanceWrap && distanceEl) {
            if (distanceKm > 0) {
                distanceEl.textContent = String(distanceKm);
                distanceWrap.classList.remove('d-none');
            } else {
                distanceEl.textContent = '';
                distanceWrap.classList.add('d-none');
            }
        }

        var priceLabel = booking.total_price_label || '';
        if (!priceLabel && booking.total_price > 0) {
            priceLabel = Number(booking.total_price).toLocaleString('vi-VN') + ' đ';
        }
        var priceWrap = qs('[data-field="total_price_wrap"]', card);
        var priceEl = qs('[data-field="total_price"]', card);
        if (priceWrap && priceEl) {
            if (priceLabel) {
                priceEl.textContent = priceLabel;
                priceWrap.classList.remove('d-none');
            } else {
                priceEl.textContent = '';
                priceWrap.classList.add('d-none');
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
        renderReview(booking);
        syncActiveSession(booking);
        clearActiveSessionIfDone(booking);
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

    function fetchStatus() {
        if (!window.__bookingTripStatusUrl) {
            return Promise.resolve(null);
        }

        var params = buildStatusParams();
        if (!params.toString()) {
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

    function init() {
        bindReview();
        fetchStatus();
        window.setInterval(fetchStatus, POLL_MS);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
