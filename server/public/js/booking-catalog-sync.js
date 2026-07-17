/**
 * Làm mới nhãn Đặt ngay / Đặt sau trên danh sách xe (poll khi không tương tác).
 */
(function () {
    var url = window.__bookingDriverOffersUrl;
    var list = document.getElementById('trips-list');

    if (!url || !list || !window.IdlePoll) {
        return;
    }

    // TODO (Update Booking Button Logic): Chỉ "Đặt ngay" khi TX online và còn chia sẻ location hợp lệ.
    function resolveBookingAction(offer) {
        if (!offer) {
            return { label: 'Đặt sau', tone: 'later' };
        }

        if (offer.booking_action_tone === 'now' || offer.booking_action_label === 'Đặt ngay') {
            return { label: 'Đặt ngay', tone: 'now' };
        }

        var isOnline = offer.driver_is_online === true
            || offer.driver_is_online === 1
            || offer.driver_is_online === '1';
        // TODO (Update Booking Button Logic): Mất tọa độ/null location thì luôn fallback sang "Đặt sau".
        var hasLocation = offer.driver_has_location === true
            || offer.driver_has_location === 1
            || offer.driver_has_location === '1';

        return (isOnline && hasLocation)
            ? { label: 'Đặt ngay', tone: 'now' }
            : { label: 'Đặt sau', tone: 'later' };
    }

    function updateHeroCount(count) {
        var stat = document.querySelector('.grab-home-topbar__stat strong');
        if (stat) {
            stat.textContent = String(count);
        }
    }

    function applyOffers(offers) {
        if (!Array.isArray(offers)) {
            return;
        }

        offers.forEach(function (offer) {
            var capacity = offer.capacity;
            if (!capacity) {
                return;
            }

            var selector = '[data-capacity="' + capacity + '"]'
                + (offer.vehicle_type ? '[data-vehicle-type="' + offer.vehicle_type + '"]' : '');
            var card = list.querySelector(selector);
            if (!card) {
                return;
            }

            var btn = card.querySelector('[data-open-booking]');
            if (!btn) {
                return;
            }

            // TODO (Update Booking Button Logic): Frontend tự render theo online + location, không phụ thuộc nhãn cũ từ API.
            var action = resolveBookingAction(offer);
            var label = action.label;
            var tone = action.tone;
            var span = btn.querySelector('span');
            if (span) {
                span.textContent = label;
            }

            btn.classList.remove('vehicle-select-row__cta--now', 'vehicle-select-row__cta--later');
            btn.classList.add('vehicle-select-row__cta--' + tone);
        });
    }

    function pollOffers() {
        fetch(url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data) {
                    return;
                }
                applyOffers(data.offers || []);
                if (typeof data.count === 'number') {
                    updateHeroCount(data.count);
                }
            })
            .catch(function () {});
    }

    window.IdlePoll.create({ onPoll: pollOffers }).start();
})();
