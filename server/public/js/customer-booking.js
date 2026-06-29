/**
 * Guest booking — đặt cả xe / ghép xe (chọn số ghế), chọn xe theo ảnh, 2 bước modal
 */
(function () {
    var form = document.getElementById('trip-filter-form');
    var syncUrl = window.__customerSyncUrl;
    var seatAvailabilityUrl = window.__seatAvailabilityUrl;
    var quotePriceUrl = window.__quotePriceUrl;
    var basePrice = window.__customerBasePrices || {};
    var bookingTemplates = window.__bookingTemplates || [];
    var presetDriver = window.__presetDriver || null;
    var weekdayNames = ['Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'];

    window.__seatPicks = window.__seatPicks || {};
    var activeTemplateId = null;
    var activeRoute = '';
    var activeSelectedCapacity = 0;
    var activeCapacity = 0;
    var activeOccupied = {};
    var activeSeatsPartiallyBooked = false;
    var activeUnitPrice = 0;
    var activeVehicleLabel = '';
    var currentStep = 1;
    var seatPollTimer = null;
    var quoteFetchSeq = 0;
    var roundTripMultiplier = Number(window.__roundTripMultiplier) || 1.7;

    function formatMoney(n) {
        return new Intl.NumberFormat('vi-VN').format(n) + ' đ';
    }

    function formatDateLabel(isoDate) {
        if (!isoDate) {
            return { weekday: '—', short: '—', label: '—' };
        }
        var d = new Date(isoDate + 'T00:00:00');
        var weekday = weekdayNames[d.getDay()];
        var short = d.toLocaleDateString('vi-VN');
        return { weekday: weekday, short: short, label: weekday + ', ' + short };
    }

    function getFilterServiceDate() {
        var input = document.getElementById('filter-service-date');
        if (input && input.value) return input.value;
        return window.__filterServiceDate || '';
    }

    function updateModalTripBanner(routeText) {
        var routeEl = document.getElementById('modal-route');
        if (routeEl && routeText) routeEl.textContent = routeText;
    }

    function getSelectedTripType() {
        var checked = document.querySelector('input[name="trip_type"]:checked');
        return checked ? checked.value : 'one_way';
    }

    function findTemplate(id) {
        return bookingTemplates.find(function (t) { return String(t.id) === String(id); }) || null;
    }

    function setSelectedCapacity(capacity) {
        activeSelectedCapacity = parseInt(capacity, 10) || activeSelectedCapacity || 0;
        var capInput = document.getElementById('modal-vehicle-capacity');
        if (capInput) capInput.value = String(activeSelectedCapacity);
    }

    function getDriverLockedCapacity() {
        if (!presetDriver) {
            return 0;
        }
        return parseInt(presetDriver.vehicle_seats, 10) || 0;
    }

    function vehicleLabelForGuest(capacity, template) {
        if (template && template.vehicle_label) {
            return template.vehicle_label;
        }
        return capacity + ' chỗ';
    }

    function syncTemplatePrices(template) {
        if (!template) return;
        basePrice[template.id] = {
            one_way: template.one_way_price || template.price,
            round_trip: template.seat_round_trip_price || template.round_trip_price || template.price,
            whole_car_round: template.whole_car_round_trip_price || null,
        };
    }

    function roundToThousand(amount) {
        return Math.round(amount / 1000) * 1000;
    }

    function priceFromTemplate(template, bookingMode, tripType) {
        if (!template) {
            return 0;
        }
        var whole = Number(template.whole_car_price) || 0;
        var shared = Number(template.one_way_price) || Number(template.price) || 0;
        var oneWay = bookingMode === 'whole_car' ? whole : shared;

        if (tripType === 'round_trip') {
            if (bookingMode === 'whole_car') {
                if (template.whole_car_round_trip_price) {
                    return Number(template.whole_car_round_trip_price);
                }
                if (whole > 0 && shared > 0 && template.round_trip_price) {
                    return roundToThousand(Math.round(whole * (Number(template.round_trip_price) / shared)));
                }
                return roundToThousand(Math.round(oneWay * roundTripMultiplier));
            }
            if (template.seat_round_trip_price) {
                return Number(template.seat_round_trip_price);
            }
            return Number(template.round_trip_price) || shared;
        }

        return oneWay;
    }

    function syncActiveUnitPriceFromTemplate(template) {
        activeUnitPrice = priceFromTemplate(template, getBookingMode(), getSelectedTripType());
    }

    function getUnitPrice(templateId) {
        if (activeUnitPrice > 0) {
            return activeUnitPrice;
        }
        var tpl = findTemplate(templateId);
        if (tpl) {
            return priceFromTemplate(tpl, getBookingMode(), getSelectedTripType());
        }
        var prices = basePrice[templateId];
        if (!prices) {
            return 0;
        }
        if (typeof prices === 'object') {
            return getSelectedTripType() === 'round_trip'
                ? (Number(prices.round_trip) || 0)
                : (Number(prices.one_way) || 0);
        }
        return Number(prices) || 0;
    }

    function fetchQuotePrice() {
        if (!quotePriceUrl || !activeTemplateId) {
            return;
        }
        var pickup = document.getElementById('modal-pickup');
        var dropoff = document.getElementById('modal-dropoff');
        if (!pickup || !dropoff || !pickup.value || !dropoff.value) {
            return;
        }

        var seq = ++quoteFetchSeq;
        var params = new URLSearchParams({
            template_id: activeTemplateId,
            trip_type: getSelectedTripType(),
            booking_mode: getBookingMode(),
            pickup_address: pickup.value,
            dropoff_address: dropoff.value,
        });

        fetch(quotePriceUrl + '?' + params.toString(), { headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (seq !== quoteFetchSeq) {
                    return;
                }
                activeUnitPrice = Number(data.seat_price) || 0;
                var typeSummary = document.getElementById('modal-trip-type-summary');
                if (typeSummary) {
                    typeSummary.textContent = data.trip_type === 'round_trip' ? 'Khứ hồi' : 'Một chiều';
                }
                var unitEl = document.getElementById('modal-price-unit');
                if (unitEl && getBookingMode() !== 'whole_car') {
                    unitEl.textContent = formatMoney(activeUnitPrice);
                }
                updateSummary(activeTemplateId);
            })
            .catch(function () {});
    }

    function getBookingMode() {
        var checked = document.querySelector('input[name="booking_mode"]:checked');
        return checked ? checked.value : 'whole_car';
    }

    function getAvailableSeatCount() {
        var cap = activeCapacity || 0;
        var taken = Object.keys(activeOccupied || {}).length;
        return Math.max(cap - taken, 0);
    }

    function getSharedSeatCount() {
        var input = document.getElementById('modal-seat-count');
        var count = parseInt(input && input.value, 10) || 1;
        return Math.max(1, count);
    }

    function clampSharedSeatCount() {
        var input = document.getElementById('modal-seat-count');
        if (!input) return 1;
        var free = getAvailableSeatCount();
        var count = getSharedSeatCount();
        if (free > 0 && count > free) {
            count = free;
            input.value = String(count);
        }
        if (count < 1) {
            count = 1;
            input.value = '1';
        }
        input.max = free > 0 ? String(free) : '';
        return count;
    }

    function selectAllFreeSeats(templateId) {
        if (!templateId) return;
        window.__seatPicks[templateId] = new Set();
        for (var i = 1; i <= activeCapacity; i++) {
            var key = String(i);
            if (!activeOccupied[key] && !activeOccupied[i]) {
                window.__seatPicks[templateId].add(key);
            }
        }
        updateSummary(templateId);
    }

    function isStep1SeatsReady() {
        if (!activeTemplateId) {
            return false;
        }
        if (!canLoadSeats()) {
            return true;
        }
        if (getBookingMode() === 'whole_car') {
            var picks = activeTemplateId ? (window.__seatPicks[activeTemplateId] || new Set()) : new Set();
            return picks.size > 0;
        }
        return getAvailableSeatCount() > 0 && clampSharedSeatCount() >= 1;
    }

    function validateStep1Form() {
        var step1 = document.getElementById('booking-step-1');
        if (!step1 || !window.FormFieldValidation) {
            return true;
        }
        return FormFieldValidation.validateFirst(step1) && validatePickupTimeField();
    }

    function validateStep2Form() {
        var step2 = document.getElementById('booking-step-2');
        if (!step2 || !window.FormFieldValidation) {
            return true;
        }
        return FormFieldValidation.validateFirst(step2);
    }

    function parseViTimeTo24h(raw) {
        var s = String(raw || '').trim();
        if (s === '') {
            return '';
        }
        var m = s.match(/^(\d{1,2}):(\d{2})(?::\d{2})?\s*(SA|CH|PM|sáng|sang|chiều|chieu|tối|toi)?\s*$/iu);
        if (!m) {
            return normalizeTime24h(s);
        }
        var hour = parseInt(m[1], 10);
        var minute = parseInt(m[2], 10);
        if (isNaN(hour) || isNaN(minute) || minute > 59 || hour > 23) {
            return '';
        }
        var suffix = (m[3] || 'SA').toLowerCase();
        if (suffix === 'pm' || suffix === 'ch' || suffix === 'chiều' || suffix === 'chieu') {
            if (hour < 12) {
                hour += 12;
            }
        } else if (suffix === 'sa' || suffix === 'sáng' || suffix === 'sang') {
            if (hour === 12) {
                hour = 0;
            }
        } else if (suffix === 'tối' || suffix === 'toi') {
            if (hour < 12) {
                hour += 12;
            }
            if (hour < 18) {
                hour += 12;
            }
        }
        if (hour > 23) {
            return '';
        }
        return String(hour).padStart(2, '0') + ':' + String(minute).padStart(2, '0');
    }

    function formatViPickupTime(raw) {
        var clock = parseViTimeTo24h(raw);
        if (!clock) {
            return String(raw || '').trim();
        }
        var parts = clock.split(':');
        var hour = parseInt(parts[0], 10);
        var minute = parts[1];
        var period = 'SA';
        var displayHour = hour;
        if (hour === 0) {
            displayHour = 12;
            period = 'SA';
        } else if (hour < 12) {
            displayHour = hour;
            period = 'SA';
        } else if (hour === 12) {
            displayHour = 12;
            period = 'CH';
        } else if (hour < 18) {
            displayHour = hour - 12;
            period = 'CH';
        } else {
            displayHour = hour > 12 ? hour - 12 : hour;
            period = 'Tối';
        }
        return String(displayHour).padStart(2, '0') + ':' + minute + ' ' + period;
    }

    function normalizeTime24h(raw) {
        var s = String(raw || '').trim().replace(/[^\d:]/g, '');
        if (s === '') {
            return '';
        }
        if (/^\d{4}$/.test(s)) {
            s = s.slice(0, 2) + ':' + s.slice(2);
        } else if (/^\d{1,2}$/.test(s)) {
            s = String(parseInt(s, 10)).padStart(2, '0') + ':00';
        } else if (/^\d{1,2}:\d{1,2}$/.test(s)) {
            var bits = s.split(':');
            s = String(parseInt(bits[0], 10)).padStart(2, '0') + ':' + String(parseInt(bits[1], 10) || 0).padStart(2, '0');
        }
        if (!/^([01][0-9]|2[0-3]):[0-5][0-9]$/.test(s)) {
            return '';
        }
        return s;
    }

    function normalizePickupTimeField() {
        var input = document.getElementById('modal-pickup-time');
        if (!input) {
            return '';
        }
        var formatted = formatViPickupTime(input.value);
        if (formatted) {
            input.value = formatted;
        }
        return parseViTimeTo24h(input.value);
    }

    function setPickupTimeForSubmit() {
        var input = document.getElementById('modal-pickup-time');
        if (!input) {
            return '';
        }
        var clock24 = parseViTimeTo24h(input.value);
        if (clock24) {
            input.value = clock24;
        }
        return clock24;
    }

    function setDefaultPickupTime() {
        var input = document.getElementById('modal-pickup-time');
        if (!input) {
            return;
        }
        input.value = '06:00 SA';
    }

    function validatePickupTimeField() {
        var input = document.getElementById('modal-pickup-time');
        if (!input) {
            return true;
        }
        var pickup = parseViTimeTo24h(input.value);
        if (!pickup) {
            if (window.AppDialog) {
                window.AppDialog.alert('Vui lòng nhập giờ đón hợp lệ (ví dụ: 06:00 SA).');
            }
            input.focus();
            return false;
        }
        input.value = formatViPickupTime(pickup);
        return true;
    }

    function focusPassengerNameField() {
        window.setTimeout(function () {
            var nameInput = document.getElementById('modal-passenger-name');
            if (!nameInput || nameInput.closest('.d-none')) {
                return;
            }
            nameInput.focus({ preventScroll: false });
            if (typeof nameInput.select === 'function') {
                nameInput.select();
            }
        }, 220);
    }

    function isWholeCarAvailable() {
        var cap = activeCapacity || 0;
        if (cap <= 0) {
            return false;
        }
        if (canLoadSeats()) {
            return getAvailableSeatCount() === cap;
        }
        return !activeSeatsPartiallyBooked;
    }

    function syncWholeCarOption() {
        var wholeRadio = document.getElementById('booking-mode-whole-car');
        var sharedRadio = document.getElementById('booking-mode-shared');
        var wholeWrap = document.getElementById('booking-mode-whole-car-wrap');
        var hint = document.getElementById('modal-whole-car-unavailable-hint');
        var available = isWholeCarAvailable();
        var switchedMode = false;

        if (wholeRadio) {
            wholeRadio.disabled = !available;
        }
        if (wholeWrap) {
            wholeWrap.classList.toggle('is-disabled', !available);
            wholeWrap.setAttribute('aria-disabled', available ? 'false' : 'true');
        }
        if (hint) {
            hint.classList.toggle('d-none', available);
        }
        if (!available && wholeRadio && wholeRadio.checked && sharedRadio) {
            sharedRadio.checked = true;
            switchedMode = true;
        }
        updateBookingModeUi();
        if (switchedMode) {
            syncActiveUnitPriceFromTemplate(findTemplate(activeTemplateId));
            fetchQuotePrice();
        }
        updateSummary(activeTemplateId);
    }

    function setPartialBookingFromCard(capacity, seatsFree) {
        var cap = parseInt(capacity, 10) || 0;
        if (seatsFree === undefined || seatsFree === null || seatsFree === '') {
            activeSeatsPartiallyBooked = false;
            return;
        }
        var free = parseInt(seatsFree, 10);
        if (cap <= 0 || isNaN(free)) {
            activeSeatsPartiallyBooked = false;
            return;
        }
        activeSeatsPartiallyBooked = free < cap;
    }

    function updateBookingModeUi() {
        var isWhole = getBookingMode() === 'whole_car';
        var wrap = document.getElementById('modal-seat-count-wrap');
        var spotSummary = document.getElementById('modal-spot-summary');
        var unitWrap = document.getElementById('modal-price-unit-wrap');
        if (wrap) {
            wrap.classList.toggle('d-none', isWhole || !canLoadSeats());
        }
        if (spotSummary) spotSummary.classList.toggle('d-none', isWhole);
        if (unitWrap) unitWrap.classList.toggle('d-none', isWhole);
    }

    function bookingModeLabel(mode) {
        return mode === 'whole_car' ? 'Đặt cả xe' : 'Ghép xe';
    }

    function seatCountLabel(count) {
        return count + ' ghế';
    }

    function updateVehicleDisplay() {
        var step2 = document.getElementById('modal-vehicle');
        var tpl = findTemplate(activeTemplateId);
        var label;
        var locked = getDriverLockedCapacity();
        if (locked > 0 && presetDriver) {
            label = presetDriver.vehicle_label || (locked + ' chỗ');
        } else {
            label = vehicleLabelForGuest(activeCapacity, tpl);
        }
        if (step2) step2.textContent = label;
    }

    function applyTemplate(template) {
        if (!template) return;

        activeTemplateId = String(template.id);
        activeRoute = template.route;
        var locked = getDriverLockedCapacity();
        if (locked > 0) {
            setSelectedCapacity(locked);
            activeCapacity = locked;
            activeVehicleLabel = presetDriver.vehicle_label || (locked + ' chỗ');
        } else {
            activeCapacity = parseInt(template.capacity, 10) || 0;
            setSelectedCapacity(activeCapacity);
            activeVehicleLabel = template.vehicle_label || (activeCapacity + ' chỗ');
        }
        syncActiveUnitPriceFromTemplate(template);

        var templateInput = document.getElementById('modal-template-id');
        if (templateInput) templateInput.value = activeTemplateId;

        syncTemplatePrices(template);

        var priceUnit = document.getElementById('modal-price-unit');
        if (priceUnit && getBookingMode() !== 'whole_car') {
            priceUnit.textContent = formatMoney(activeUnitPrice);
        }

        setDefaultPickupTime();

        if (!window.__seatPicks[activeTemplateId]) {
            window.__seatPicks[activeTemplateId] = new Set();
        }

        updateVehicleDisplay();

        fetchQuotePrice();
        loadSeatAvailability();
        syncWholeCarOption();
        updateSummary(activeTemplateId);
    }

    function canLoadSeats() {
        var dateInput = document.getElementById('modal-service-date');
        var pickup = document.getElementById('modal-pickup');
        var dropoff = document.getElementById('modal-dropoff');
        return !!(activeTemplateId && dateInput && dateInput.value && pickup && pickup.value && dropoff && dropoff.value);
    }

    function isStep1Ready() {
        return isStep1SeatsReady();
    }

    function loadSeatAvailability() {
        if (!canLoadSeats() || !seatAvailabilityUrl) {
            activeOccupied = {};
            if (activeTemplateId && window.__seatPicks[activeTemplateId]) {
                window.__seatPicks[activeTemplateId].clear();
            }
            syncWholeCarOption();
            updateSummary(activeTemplateId);
            return;
        }

        var dateInput = document.getElementById('modal-service-date');
        var params = new URLSearchParams({
            template_id: activeTemplateId,
            service_date: dateInput.value,
        });
        if (presetDriver && presetDriver.code) {
            params.set('driver_code', presetDriver.code);
        }

        fetch(seatAvailabilityUrl + '?' + params.toString(), { headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                activeOccupied = data.occupied_map || {};
                if (data.capacity) activeCapacity = data.capacity;
                activeSeatsPartiallyBooked = activeCapacity > 0 && getAvailableSeatCount() < activeCapacity;
                clampSharedSeatCount();
                syncWholeCarOption();
                if (getBookingMode() === 'whole_car') {
                    selectAllFreeSeats(activeTemplateId);
                }
            })
            .catch(function () {
                updateSummary(activeTemplateId);
            });
    }

    function updateSummary(templateId) {
        var picks = window.__seatPicks[templateId] || new Set();
        var list = Array.from(picks).sort(function (a, b) { return Number(a) - Number(b); });
        var totalEl = document.getElementById('modal-total-price');
        var nextBtn = document.getElementById('modal-next-btn');
        var listStep1 = document.getElementById('modal-seat-list-step1');
        var totalStep1 = document.getElementById('modal-total-price-step1');
        var footerStep1 = document.getElementById('modal-footer-step1');
        var seatSummaryText = document.getElementById('modal-seat-summary-text');
        var price = getUnitPrice(templateId);
        var isWhole = getBookingMode() === 'whole_car';
        var seatCount = isWhole ? list.length : clampSharedSeatCount();
        var hasSeats = isStep1Ready();
        var total = isWhole ? price : (seatCount * price);

        if (seatSummaryText) {
            seatSummaryText.textContent = isWhole ? bookingModeLabel('whole_car') : seatCountLabel(seatCount);
        }
        if (totalEl) totalEl.textContent = formatMoney(total);
        if (listStep1) {
            listStep1.textContent = isWhole
                ? bookingModeLabel('whole_car')
                : ('Số ghế: ' + seatCountLabel(seatCount));
        }
        if (totalStep1) totalStep1.textContent = formatMoney(total);
        var unitEl = document.getElementById('modal-price-unit');
        if (unitEl && price > 0 && !isWhole) unitEl.textContent = formatMoney(price);
        if (nextBtn) {
            nextBtn.disabled = false;
            nextBtn.textContent = 'Tiếp tục';
        }
        if (footerStep1) footerStep1.classList.toggle('is-ready', !!activeTemplateId && isStep1SeatsReady());
    }

    function updateDriverSummary() {
        var el = document.getElementById('modal-driver-summary');
        if (!el) return;
        el.textContent = 'Hình thức: ' + bookingModeLabel(getBookingMode());
    }

    function setBookingStep(step) {
        currentStep = step;
        var step1 = document.getElementById('booking-step-1');
        var step2 = document.getElementById('booking-step-2');
        var footer1 = document.getElementById('modal-footer-step1');
        var footer2 = document.getElementById('modal-footer-step2');
        var steps = document.querySelectorAll('.booking-step');
        var body = document.querySelector('#bookingModal .booking-modal-body');

        if (step1) step1.classList.toggle('d-none', step !== 1);
        if (step2) step2.classList.toggle('d-none', step !== 2);
        if (footer1) footer1.classList.toggle('d-none', step !== 1);
        if (footer2) footer2.classList.toggle('d-none', step !== 2);

        steps.forEach(function (el) {
            var n = Number(el.dataset.step);
            el.classList.remove('active', 'done');
            if (n === step) el.classList.add('active');
            else if (n < step) el.classList.add('done');
        });

        if (body) body.scrollTop = 0;

        if (step === 2) {
            updateVehicleDisplay();
            updateDriverSummary();
            updateSummary(activeTemplateId);
            focusPassengerNameField();
        }
    }

    function onTripContextChange() {
        if (activeTemplateId && window.__seatPicks[activeTemplateId]) {
            window.__seatPicks[activeTemplateId].clear();
        }
        syncActiveUnitPriceFromTemplate(findTemplate(activeTemplateId));
        fetchQuotePrice();
        loadSeatAvailability();
        updateSummary(activeTemplateId);
    }

    function startSeatPolling() {
        stopSeatPolling();
        seatPollTimer = setInterval(function () {
            if (canLoadSeats()) loadSeatAvailability();
        }, 8000);
    }

    function stopSeatPolling() {
        if (seatPollTimer) {
            clearInterval(seatPollTimer);
            seatPollTimer = null;
        }
    }

    function resetBookingModal() {
        stopSeatPolling();
        activeUnitPrice = 0;
        activeSeatsPartiallyBooked = false;
        activeOccupied = {};
        setBookingStep(1);
        syncWholeCarOption();
    }

    function setRouteEndpoints(pickup, dropoff) {
        var pickupEl = document.getElementById('modal-pickup');
        var dropoffEl = document.getElementById('modal-dropoff');
        if (pickupEl) pickupEl.value = pickup || '';
        if (dropoffEl) dropoffEl.value = dropoff || '';
    }

    function adjustSeatCount(delta) {
        var input = document.getElementById('modal-seat-count');
        if (!input) return;
        var next = getSharedSeatCount() + delta;
        var free = getAvailableSeatCount();
        if (free > 0) next = Math.min(next, free);
        next = Math.max(1, next);
        input.value = String(next);
        updateSummary(activeTemplateId);
    }

    document.querySelectorAll('[data-open-booking]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var id = btn.dataset.templateId;
            var modal = document.getElementById('bookingModal');
            if (!modal) return;

            resetBookingModal();

            var routeText = btn.dataset.route || '';
            activeRoute = routeText;
            var serviceDate = btn.dataset.serviceDate || getFilterServiceDate();

            document.getElementById('modal-route').textContent = routeText;
            document.getElementById('modal-route-step2').textContent = routeText;
            updateModalTripBanner(routeText);

            var dateInput = document.getElementById('modal-service-date');
            if (dateInput && serviceDate) {
                dateInput.value = serviceDate;
            }

            setRouteEndpoints(btn.dataset.pickupDefault || '', btn.dataset.dropoffDefault || '');
            onTripContextChange();

            var seatCountInput = document.getElementById('modal-seat-count');
            if (seatCountInput) seatCountInput.value = '1';

            setPartialBookingFromCard(btn.dataset.capacity, btn.dataset.seatsFree);

            var template = findTemplate(id);
            if (template) {
                applyTemplate(template);
            } else {
                setDefaultPickupTime();
            }

            startSeatPolling();
            bootstrap.Modal.getOrCreateInstance(modal).show();
        });
    });

    var dateInput = document.getElementById('modal-service-date');
    if (dateInput) {
        dateInput.addEventListener('change', onTripContextChange);
    }

    var filterDateInput = document.getElementById('filter-service-date');
    if (filterDateInput) {
        filterDateInput.addEventListener('change', function () {
            var info = formatDateLabel(filterDateInput.value);
            var listDate = document.getElementById('list-service-date-label');
            if (listDate) listDate.textContent = info.label;
        });
    }

    var pickupTimeInput = document.getElementById('modal-pickup-time');
    if (pickupTimeInput) {
        if (pickupTimeInput.value) {
            pickupTimeInput.value = formatViPickupTime(pickupTimeInput.value);
        }
        pickupTimeInput.addEventListener('blur', function () {
            normalizePickupTimeField();
            onTripContextChange();
        });
        pickupTimeInput.addEventListener('change', function () {
            normalizePickupTimeField();
            onTripContextChange();
        });
    }

    ['modal-pickup', 'modal-dropoff', 'modal-pickup-detail', 'modal-dropoff-detail'].forEach(function (id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('input', function () {
            updateSummary(activeTemplateId);
        });
        el.addEventListener('change', function () {
            if (id === 'modal-pickup' || id === 'modal-dropoff') {
                onTripContextChange();
            } else {
                updateSummary(activeTemplateId);
            }
        });
    });

    document.querySelectorAll('input[name="trip_type"]').forEach(function (radio) {
        radio.addEventListener('change', onTripContextChange);
    });

    document.querySelectorAll('input[name="booking_mode"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            if (radio.value === 'whole_car' && !isWholeCarAvailable()) {
                var sharedRadio = document.getElementById('booking-mode-shared');
                if (sharedRadio) sharedRadio.checked = true;
                syncWholeCarOption();
                return;
            }
            if (activeTemplateId && window.__seatPicks[activeTemplateId]) {
                window.__seatPicks[activeTemplateId].clear();
            }
            var seatCountInput = document.getElementById('modal-seat-count');
            if (seatCountInput) seatCountInput.value = '1';
            updateBookingModeUi();
            syncActiveUnitPriceFromTemplate(findTemplate(activeTemplateId));
            updateSummary(activeTemplateId);
            fetchQuotePrice();
            if (canLoadSeats()) {
                loadSeatAvailability();
            }
            updateDriverSummary();
        });
    });

    var seatCountInput = document.getElementById('modal-seat-count');
    if (seatCountInput) {
        seatCountInput.addEventListener('input', function () {
            clampSharedSeatCount();
            updateSummary(activeTemplateId);
        });
    }

    var seatMinus = document.getElementById('modal-seat-count-minus');
    if (seatMinus) seatMinus.addEventListener('click', function () { adjustSeatCount(-1); });

    var seatPlus = document.getElementById('modal-seat-count-plus');
    if (seatPlus) seatPlus.addEventListener('click', function () { adjustSeatCount(1); });

    var nextBtn = document.getElementById('modal-next-btn');
    if (nextBtn) {
        nextBtn.addEventListener('click', function () {
            if (!activeTemplateId) {
                return;
            }
            if (!validateStep1Form()) {
                return;
            }
            if (canLoadSeats() && !isStep1SeatsReady()) {
                loadSeatAvailability();
                return;
            }
            updateSummary(activeTemplateId);
            setBookingStep(2);
        });
    }

    var backBtn = document.getElementById('modal-back-btn');
    if (backBtn) {
        backBtn.addEventListener('click', function () {
            setBookingStep(1);
        });
    }

    var bookingModal = document.getElementById('bookingModal');
    if (bookingModal) {
        bookingModal.addEventListener('hidden.bs.modal', resetBookingModal);
    }

    function refreshOccupiedBeforeSubmit(callback) {
        if (!canLoadSeats() || !seatAvailabilityUrl) {
            callback(activeOccupied);
            return;
        }
        var dateInput = document.getElementById('modal-service-date');
        var params = new URLSearchParams({
            template_id: activeTemplateId,
            service_date: dateInput.value,
        });
        if (presetDriver && presetDriver.code) params.set('driver_code', presetDriver.code);

        fetch(seatAvailabilityUrl + '?' + params.toString(), { headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                activeOccupied = data.occupied_map || {};
                callback(activeOccupied);
            })
            .catch(function () {
                callback(activeOccupied);
            });
    }

    var allowNativeSubmit = false;
    var bookingForm = document.getElementById('booking-form');

    if (bookingForm && window.FormFieldValidation) {
        FormFieldValidation.bindClearOnInput(bookingForm);
    }

    if (bookingForm) {
        bookingForm.addEventListener('submit', function (e) {
            if (allowNativeSubmit) {
                return;
            }

            e.preventDefault();

            if (currentStep !== 2) {
                if (!validateStep1Form()) {
                    setBookingStep(1);
                    return;
                }
                if (canLoadSeats() && !isStep1SeatsReady()) {
                    loadSeatAvailability();
                    return;
                }
                setBookingStep(2);
                return;
            }

            if (!validateStep1Form()) {
                setBookingStep(1);
                return;
            }

            if (!validateStep2Form()) {
                return;
            }

            if (!isStep1SeatsReady()) {
                setBookingStep(1);
                loadSeatAvailability();
                return;
            }

            var submitBtn = document.getElementById('modal-submit-btn');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Đang kiểm tra...';
            }

            var isWhole = getBookingMode() === 'whole_car';
            var seatCount = isWhole ? (window.__seatPicks[activeTemplateId] || new Set()).size : clampSharedSeatCount();

            refreshOccupiedBeforeSubmit(function () {
                var free = getAvailableSeatCount();
                if (isWhole && free !== activeCapacity) {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Đặt vé';
                    }
                    if (window.AppDialog) {
                        window.AppDialog.alert('Đặt cả xe chỉ khả dụng khi chuyến còn trống toàn bộ.');
                    }
                    setBookingStep(1);
                    loadSeatAvailability();
                    return;
                }
                if (!isWhole && seatCount > free) {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Đặt vé';
                    }
                    if (window.AppDialog) {
                        window.AppDialog.alert('Không đủ ghế trống. Vui lòng giảm số ghế.');
                    }
                    setBookingStep(1);
                    loadSeatAvailability();
                    return;
                }

                if (submitBtn) submitBtn.textContent = 'Đang gửi...';
                setPickupTimeForSubmit();
                allowNativeSubmit = true;
                if (typeof bookingForm.requestSubmit === 'function') {
                    bookingForm.requestSubmit();
                } else {
                    bookingForm.submit();
                }
            });
        });
    }

    function upsertBookingTemplate(trip) {
        var idx = bookingTemplates.findIndex(function (t) { return String(t.id) === String(trip.id); });
        var entry = {
            id: trip.id,
            route: trip.route_line || trip.route,
            departure: trip.departure,
            destination: trip.destination,
            capacity: trip.capacity,
            capacity_label: trip.capacity ? (trip.capacity + ' chỗ') : '',
            capacity_sort: trip.capacity_sort || trip.capacity || 0,
            vehicle_photo: trip.vehicle_photo_url || '',
            vehicle_type: trip.vehicle_type || 'sedan',
            vehicle_label: trip.vehicle_label || '',
            price: trip.price_raw || trip.one_way_price,
            one_way_price: trip.one_way_price || trip.price_raw,
            whole_car_price: trip.whole_car_price || null,
            whole_car_round_trip_price: trip.whole_car_round_trip_price || null,
            seat_round_trip_price: trip.seat_round_trip_price || null,
            round_trip_price: trip.round_trip_price || trip.price_raw,
            service_date: trip.service_date,
            pickup_default: trip.pickup_default,
            dropoff_default: trip.dropoff_default,
        };
        if (idx >= 0) {
            bookingTemplates[idx] = Object.assign({}, bookingTemplates[idx], entry);
        } else if (entry.route) {
            bookingTemplates.push(entry);
        }
    }

    function updateTripCard(card, trip) {
        upsertBookingTemplate(trip);

        var thumbWrap = card.querySelector('.trip-vehicle-thumb');
        if (thumbWrap && trip.vehicle_photo_url) {
            thumbWrap.classList.remove('trip-route-thumb');
            var existing = thumbWrap.querySelector('.trip-vehicle-photo');
            if (existing && existing.tagName === 'IMG') {
                if (existing.src !== trip.vehicle_photo_url) {
                    existing.src = trip.vehicle_photo_url;
                }
            } else {
                thumbWrap.innerHTML = '<img src="' + trip.vehicle_photo_url + '" alt="" class="trip-vehicle-photo" loading="lazy" decoding="async">';
            }
        }
        var priceAmounts = card.querySelectorAll('.trip-price-amount');
        if (priceAmounts.length && trip.price) {
            priceAmounts.forEach(function (el) {
                el.textContent = trip.price;
            });
        }
        if (trip.one_way_price) {
            basePrice[trip.id] = {
                one_way: trip.one_way_price,
                round_trip: trip.seat_round_trip_price || trip.round_trip_price || trip.one_way_price,
                whole_car_round: trip.whole_car_round_trip_price || null,
            };
        } else if (trip.price_raw) {
            basePrice[trip.id] = {
                one_way: trip.price_raw,
                round_trip: trip.seat_round_trip_price || trip.round_trip_price || trip.price_raw,
                whole_car_round: trip.whole_car_round_trip_price || null,
            };
        }
        var btn = card.querySelector('[data-open-booking]');
        if (btn) {
            btn.dataset.serviceDate = trip.service_date || btn.dataset.serviceDate;
            btn.dataset.dateLabel = trip.date_label || btn.dataset.dateLabel;
            btn.dataset.weekday = trip.weekday || btn.dataset.weekday;
            btn.dataset.dateShort = trip.date_short || btn.dataset.dateShort;
            btn.dataset.price = trip.price_raw || btn.dataset.price;
            btn.dataset.oneWayPrice = trip.one_way_price || trip.price_raw || btn.dataset.oneWayPrice;
            btn.dataset.roundTripPrice = trip.round_trip_price || btn.dataset.roundTripPrice;
            btn.dataset.capacity = trip.capacity || btn.dataset.capacity;
            btn.dataset.seatsFree = trip.seats_free != null ? trip.seats_free : btn.dataset.seatsFree;
            btn.dataset.pickupDefault = trip.pickup_default || btn.dataset.pickupDefault;
            btn.dataset.dropoffDefault = trip.dropoff_default || btn.dataset.dropoffDefault;
            if (trip.vehicle_label) {
                btn.dataset.vehicleLabel = trip.vehicle_label;
            }
            if (trip.vehicle_photo_url) {
                btn.dataset.vehiclePhoto = trip.vehicle_photo_url;
            }
        }

        if (activeTemplateId && String(activeTemplateId) === String(trip.id)) {
            activeVehicleLabel = trip.vehicle_label || activeVehicleLabel;
            updateVehicleDisplay();
            if (trip.capacity != null && trip.seats_free != null) {
                setPartialBookingFromCard(trip.capacity, trip.seats_free);
                syncWholeCarOption();
            }
        }
    }

    function poll() {
        if (!form || !syncUrl) return;
        var params = new URLSearchParams(new FormData(form));
        var page = new URLSearchParams(window.location.search).get('page');
        if (page) params.set('page', page);
        fetch(syncUrl + '?' + params.toString(), { headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var cnt = document.getElementById('trip-count');
                if (cnt) {
                    cnt.textContent = (data.pagination && data.pagination.total != null)
                        ? data.pagination.total
                        : data.trips.length;
                }
                if (data.service_date) {
                    var listDate = document.getElementById('list-service-date-label');
                    if (listDate) {
                        var info = formatDateLabel(data.service_date);
                        listDate.textContent = info.label;
                    }
                }
                var visibleIds = data.trips.map(function (trip) { return String(trip.id); });
                document.querySelectorAll('.trip-card-pro[data-template-id]').forEach(function (card) {
                    if (visibleIds.indexOf(card.getAttribute('data-template-id')) === -1) {
                        card.remove();
                    }
                });
                data.trips.forEach(function (trip) {
                    var card = document.querySelector('.trip-card-pro[data-template-id="' + trip.id + '"]');
                    if (card) updateTripCard(card, trip);
                });
                var list = document.getElementById('trips-list');
                var emptyMsg = document.getElementById('no-trips-msg');
                if (list && emptyMsg && !list.querySelector('.trip-card-pro') && !emptyMsg.parentElement) {
                    list.innerHTML = emptyMsg.outerHTML;
                }
            }).catch(function () {});
    }

    if (form && syncUrl) {
        poll();
        setInterval(poll, 12000);
    }

    document.querySelectorAll('.swap-route-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var dep = form.querySelector('[name="departure"]');
            var dest = form.querySelector('[name="destination"]');
            if (dep && dest) {
                var t = dep.value;
                dep.value = dest.value;
                dest.value = t;
            }
        });
    });

    function scrollToBookingResult() {
        var target = document.getElementById('booking-result-banner')
            || document.getElementById('booking-form-errors')
            || document.getElementById('booking-page-top');
        if (!target) return;
        window.scrollTo({ top: 0, left: 0, behavior: 'instant' });
        window.setTimeout(function () {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            if (target.focus) {
                target.focus({ preventScroll: true });
            }
        }, 80);
    }

    function restoreBookingModalFromValidation() {
        var restore = window.__bookingRestoreModal;
        if (!restore || !restore.template_id) return;

        scrollToBookingResult();

        var btn = document.querySelector('[data-open-booking][data-template-id="' + restore.template_id + '"]');
        var modal = document.getElementById('bookingModal');
        if (!btn || !modal) return;

        btn.click();

        window.setTimeout(function () {
            if (restore.seat_count) {
                var seatCountInput = document.getElementById('modal-seat-count');
                if (seatCountInput) seatCountInput.value = String(restore.seat_count);
            }
            updateSummary(String(restore.template_id));
            setBookingStep(restore.step || 2);
        }, 350);
    }

    if (presetDriver && getDriverLockedCapacity()) {
        setSelectedCapacity(getDriverLockedCapacity());
    }

    restoreBookingModalFromValidation();

    if (window.__bookingShowResult && !window.__bookingRestoreModal) {
        scrollToBookingResult();
    }
})();
