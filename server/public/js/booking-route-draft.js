/**
 * Giữ điểm đi/đến (+ lịch) qua bước đăng nhập.
 */
(function () {
    var KEY = 'appdatxe:bookingRouteDraft';

    function $(id) {
        return document.getElementById(id);
    }

    function collectFromForm() {
        return {
            pickup_detail: ($('modal-pickup-detail') || {}).value || '',
            pickup_lat: ($('modal-pickup-lat') || {}).value || '',
            pickup_lng: ($('modal-pickup-lng') || {}).value || '',
            pickup_address: ($('modal-pickup-address') || {}).value || '',
            dropoff_detail: ($('modal-dropoff-detail') || {}).value || '',
            dropoff_lat: ($('modal-dropoff-lat') || {}).value || '',
            dropoff_lng: ($('modal-dropoff-lng') || {}).value || '',
            dropoff_address: ($('modal-dropoff-address') || {}).value || '',
            service_date: ($('modal-service-date') || {}).value || '',
            pickup_time: ($('modal-pickup-time') || {}).value || '',
            schedule_later: !!(document.getElementById('booking-schedule-later')
                && document.getElementById('booking-schedule-later').getAttribute('aria-pressed') === 'true'),
            saved_at: Date.now(),
        };
    }

    function save(extra) {
        try {
            var draft = Object.assign(collectFromForm(), extra || {});
            if (!draft.pickup_detail && !draft.dropoff_detail
                && !draft.pickup_lat && !draft.dropoff_lat) {
                return false;
            }
            sessionStorage.setItem(KEY, JSON.stringify(draft));
            return true;
        } catch (e) {
            return false;
        }
    }

    function load() {
        try {
            var raw = sessionStorage.getItem(KEY);
            if (!raw) {
                return null;
            }
            var draft = JSON.parse(raw);
            if (!draft || typeof draft !== 'object') {
                return null;
            }
            // Hết hạn sau 2 giờ
            if (draft.saved_at && Date.now() - Number(draft.saved_at) > 2 * 60 * 60 * 1000) {
                clear();
                return null;
            }
            return draft;
        } catch (e) {
            return null;
        }
    }

    function clear() {
        try {
            sessionStorage.removeItem(KEY);
        } catch (e) {
        }
    }

    function applyToForm(draft) {
        if (!draft) {
            return false;
        }
        var map = {
            pickup_detail: 'modal-pickup-detail',
            pickup_lat: 'modal-pickup-lat',
            pickup_lng: 'modal-pickup-lng',
            pickup_address: 'modal-pickup-address',
            dropoff_detail: 'modal-dropoff-detail',
            dropoff_lat: 'modal-dropoff-lat',
            dropoff_lng: 'modal-dropoff-lng',
            dropoff_address: 'modal-dropoff-address',
            service_date: 'modal-service-date',
            pickup_time: 'modal-pickup-time',
        };
        Object.keys(map).forEach(function (key) {
            var el = $(map[key]);
            if (el && draft[key] != null && draft[key] !== '') {
                el.value = String(draft[key]);
            }
        });

        var homeLabel = document.querySelector('[data-home-dest-label]');
        if (homeLabel && draft.dropoff_detail) {
            homeLabel.textContent = draft.dropoff_detail;
            homeLabel.classList.add('has-value');
        }

        if (draft.schedule_later && window.setScheduleLaterEnabled) {
            /* optional hook */
        }

        ['modal-pickup-lng', 'modal-dropoff-lng'].forEach(function (id) {
            var el = $(id);
            if (el && el.value) {
                el.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });

        return !!(draft.pickup_detail && draft.dropoff_detail
            && draft.pickup_lat && draft.dropoff_lat);
    }

    function bothReady(draft) {
        draft = draft || load();
        return !!(draft
            && draft.pickup_detail && draft.dropoff_detail
            && draft.pickup_lat && draft.dropoff_lat
            && draft.pickup_lng && draft.dropoff_lng);
    }

    window.BookingRouteDraft = {
        KEY: KEY,
        save: save,
        load: load,
        clear: clear,
        applyToForm: applyToForm,
        bothReady: bothReady,
        collectFromForm: collectFromForm,
    };
})();
