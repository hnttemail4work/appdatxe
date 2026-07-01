/**
 * Guest booking — đặt cả xe / ghép xe (chọn số ghế), chọn xe theo ảnh, 2 bước modal
 */
(function () {
    var form = document.getElementById('trip-filter-form');
    var syncUrl = window.__customerSyncUrl;
    var seatAvailabilityUrl = window.__seatAvailabilityUrl;
    var quotePriceUrl = window.__quotePriceUrl;
    var resolveRouteUrl = window.__bookingResolveRouteUrl;
    var checkDuplicateUrl = window.__bookingCheckDuplicateUrl || '';
    var contactPhone = window.__contactPhone || '';
    var basePrice = window.__customerBasePrices || {};
    var bookingTemplates = window.__bookingTemplates || [];
    var referralPrefill = (window.__referralPrefill || '').toUpperCase();
    var referralHasCode = !!window.__referralHasCode;
    var lastQuoteReferral = null;
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
    var allowNativeSubmit = false;
    var seatPollTimer = null;
    var quoteFetchSeq = 0;
    var roundTripMultiplier = Number(window.__roundTripMultiplier) || 1.7;
    var OFFER_FILTER_OPTIONS = {
        one_way_whole: { tripType: 'one_way', bookingMode: 'whole_car' },
        one_way_shared: { tripType: 'one_way', bookingMode: 'shared' },
        round_trip_whole: { tripType: 'round_trip', bookingMode: 'whole_car' },
        round_trip_shared: { tripType: 'round_trip', bookingMode: 'shared' },
    };
    var OFFER_FILTER_STORAGE_KEY = 'bookingOfferFilter';
    var activeOfferFilterId = loadOfferFilterId();

    function loadOfferFilterId() {
        try {
            var stored = localStorage.getItem(OFFER_FILTER_STORAGE_KEY);
            if (stored && OFFER_FILTER_OPTIONS[stored]) {
                return stored;
            }
        } catch (e) {
            // localStorage có thể bị chặn
        }

        return 'one_way_whole';
    }

    function getActiveOfferFilter() {
        return OFFER_FILTER_OPTIONS[activeOfferFilterId] || OFFER_FILTER_OPTIONS.one_way_whole;
    }

    function wantsWholeCarFromSearch() {
        return getActiveOfferFilter().bookingMode === 'whole_car';
    }

    function getEffectiveOfferFilter() {
        return getActiveOfferFilter();
    }

    function isWholeCarBooking() {
        return wantsWholeCarFromSearch();
    }

    function syncHiddenOfferRadiosFromFilter() {
        if (window.__bookingRestoreModal && window.__bookingRestoreModal.template_id) {
            return;
        }
        var filter = getActiveOfferFilter();
        var tripRadio = document.querySelector('input[name="trip_type"][value="' + filter.tripType + '"]');
        var modeRadio = document.querySelector('input[name="booking_mode"][value="' + filter.bookingMode + '"]');
        if (tripRadio) {
            tripRadio.checked = true;
        }
        if (modeRadio) {
            modeRadio.checked = true;
        }
    }

    function resetVehicleCountIfNeeded(previousMode) {
        if (previousMode === 'whole_car' && !isWholeCarBooking()) {
            var vehicleCountInput = document.getElementById('modal-vehicle-count');
            if (vehicleCountInput) {
                vehicleCountInput.value = '1';
            }
        }
    }

    function tripTypeLabel(type) {
        return type === 'round_trip' ? 'Khứ hồi' : 'Một chiều';
    }

    function bookingModeDisplayLabel(mode) {
        return mode === 'whole_car' ? 'Cả xe' : 'Ghép xe';
    }

    var VI_WEEKDAYS = ['Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'];

    function syncRouteSheetDateDisplay(isoDate) {
        if (!isoDate) {
            return;
        }
        var parts = isoDate.split('-');
        if (parts.length !== 3) {
            return;
        }
        var date = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
        var weekdayEl = document.getElementById('filter-date-weekday');
        var labelEl = document.getElementById('filter-date-label');
        if (weekdayEl) {
            weekdayEl.textContent = VI_WEEKDAYS[date.getDay()];
        }
        if (labelEl) {
            labelEl.textContent = ('0' + date.getDate()).slice(-2) + '/' + ('0' + (date.getMonth() + 1)).slice(-2) + '/' + date.getFullYear();
        }
    }

    function offerFilterIdFromParts(tripType, bookingMode) {
        if (tripType === 'round_trip') {
            return bookingMode === 'whole_car' ? 'round_trip_whole' : 'round_trip_shared';
        }

        return bookingMode === 'whole_car' ? 'one_way_whole' : 'one_way_shared';
    }

    function syncRouteSheetSegments(filterId) {
        var filter = OFFER_FILTER_OPTIONS[filterId];
        if (!filter) {
            return;
        }

        document.querySelectorAll('.route-sheet-seg-btn[data-trip-type]').forEach(function (btn) {
            var active = btn.dataset.tripType === filter.tripType;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        document.querySelectorAll('.route-sheet-seg-btn[data-booking-mode]').forEach(function (btn) {
            var active = btn.dataset.bookingMode === filter.bookingMode;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }

    function setOfferFilter(filterId) {
        if (!OFFER_FILTER_OPTIONS[filterId]) {
            return;
        }
        var previousMode = getActiveOfferFilter().bookingMode;
        activeOfferFilterId = filterId;
        try {
            localStorage.setItem(OFFER_FILTER_STORAGE_KEY, filterId);
        } catch (e) {
            // localStorage có thể bị chặn
        }
        syncRouteSheetSegments(filterId);
        applyOfferFilterToAllCards();
        syncHiddenOfferRadiosFromFilter();
        resetVehicleCountIfNeeded(previousMode);
        updateModalOfferPresetSummary();
        updateBookingModeUi();
        if (activeTemplateId) {
            updateSummary(activeTemplateId);
        }
    }

    function syncOfferFilterToOpenModal() {
        var modal = document.getElementById('bookingModal');
        if (!modal || !modal.classList.contains('show')) {
            return;
        }
        applyOfferFilterToModalRadios();
        if (activeTemplateId) {
            onTripContextChange();
        }
    }

    function initRouteSheetOfferFilters() {
        var container = document.getElementById('booking-offer-filters');
        if (!container || container.dataset.offerFilterBound === '1') {
            return;
        }
        container.dataset.offerFilterBound = '1';
        container.addEventListener('click', function (event) {
            var filter = getActiveOfferFilter();
            var tripBtn = event.target.closest('.route-sheet-seg-btn[data-trip-type]');
            if (tripBtn) {
                var tripType = tripBtn.getAttribute('data-trip-type');
                if (!tripType) {
                    return;
                }
                setOfferFilter(offerFilterIdFromParts(tripType, filter.bookingMode));
                syncOfferFilterToOpenModal();
                return;
            }
            var modeBtn = event.target.closest('.route-sheet-seg-btn[data-booking-mode]');
            if (!modeBtn) {
                return;
            }
            var bookingMode = modeBtn.getAttribute('data-booking-mode');
            if (!bookingMode) {
                return;
            }
            setOfferFilter(offerFilterIdFromParts(filter.tripType, bookingMode));
            syncOfferFilterToOpenModal();
        });
    }

    function applyOfferFilterToCard(card) {
        var id = card.getAttribute('data-template-id');
        var tpl = findTemplate(id);
        if (!tpl) {
            return;
        }
        var filter = getActiveOfferFilter();
        var price = priceFromTemplate(tpl, filter.bookingMode, filter.tripType);
        var amountEl = card.querySelector('.trip-price-amount');
        var unitEl = card.querySelector('.trip-price-unit');
        if (amountEl) {
            amountEl.textContent = formatMoney(price);
        }
        if (unitEl) {
            unitEl.textContent = filter.bookingMode === 'whole_car' ? '/cả xe' : '/ghế';
        }
    }

    function applyOfferFilterToAllCards() {
        document.querySelectorAll('.trip-card-pro[data-template-id]').forEach(applyOfferFilterToCard);
    }

    function applyOfferFilterToModalRadios() {
        if (window.__bookingRestoreModal && window.__bookingRestoreModal.template_id) {
            updateModalOfferPresetSummary();
            updateBookingModeUi();
            return;
        }
        syncHiddenOfferRadiosFromFilter();
        updateModalOfferPresetSummary();
        updateBookingModeUi();
    }

    function updateModalOfferPresetSummary() {
        var tripEl = document.getElementById('modal-preset-trip-type');
        var modeEl = document.getElementById('modal-preset-booking-mode');
        var filter = getActiveOfferFilter();
        if (tripEl) {
            tripEl.textContent = tripTypeLabel(filter.tripType);
        }
        if (modeEl) {
            modeEl.textContent = bookingModeDisplayLabel(filter.bookingMode);
        }
    }

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
        return getEffectiveOfferFilter().tripType;
    }

    function findTemplate(id) {
        return bookingTemplates.find(function (t) { return String(t.id) === String(id); }) || null;
    }

    function setSelectedCapacity(capacity) {
        activeSelectedCapacity = parseInt(capacity, 10) || activeSelectedCapacity || 0;
        var capInput = document.getElementById('modal-vehicle-capacity');
        if (capInput) capInput.value = String(activeSelectedCapacity);
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
        if (!amount || amount <= 0) {
            return 0;
        }
        return Math.ceil(amount / 10000) * 10000;
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
        if (getBookingMode() !== 'whole_car') {
            params.set('seat_count', String(clampSharedSeatCount()));
        } else {
            params.set('vehicle_count', String(clampVehicleCount()));
        }
        if (activeSelectedCapacity) {
            params.set('vehicle_capacity', String(activeSelectedCapacity));
        }
        var phoneInput = document.getElementById('modal-contact-phone');
        if (phoneInput && phoneInput.value.trim()) {
            params.set('contact_phone', phoneInput.value.trim());
        }

        fetch(quotePriceUrl + '?' + params.toString(), { headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (seq !== quoteFetchSeq) {
                    return;
                }
                activeUnitPrice = Number(data.unit_price || data.seat_price) || 0;
                if (data.template_id) {
                    activeTemplateId = String(data.template_id);
                    var templateInput = document.getElementById('modal-template-id');
                    if (templateInput) templateInput.value = activeTemplateId;
                }
                if (data.vehicle_capacity) {
                    setSelectedCapacity(data.vehicle_capacity);
                }
                lastQuoteReferral = {
                    percent: Number(data.referral_discount_percent) || 0,
                    amount: Number(data.referral_discount_amount) || 0,
                    eligible: !!data.referral_eligible,
                    attribution_only: !!data.referral_attribution_only,
                    reason: data.referral_ineligible_reason || null,
                    subtotal: Number(data.subtotal) || 0,
                    total: Number(data.total_after_discount) || 0,
                };
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
        return getEffectiveOfferFilter().bookingMode;
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
        if (getBookingMode() === 'whole_car' || isWholeCarBooking()) {
            return clampVehicleCount() >= 1;
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
        if (!FormFieldValidation.validateFirst(step2)) {
            return false;
        }

        var latEl = document.getElementById('modal-pickup-lat');
        var lngEl = document.getElementById('modal-pickup-lng');
        var detailEl = document.getElementById('modal-pickup-detail');
        if (!latEl || !lngEl || !latEl.value || !lngEl.value) {
            if (detailEl && window.FormFieldValidation.markInvalid) {
                FormFieldValidation.markInvalid(detailEl, 'Chọn điểm đón trên bản đồ hoặc tìm địa chỉ để lấy tọa độ.');
            } else if (window.AppDialog) {
                window.AppDialog.alert('Chọn điểm đón trên bản đồ hoặc tìm địa chỉ để lấy tọa độ.');
            }
            return false;
        }

        return true;
    }

    function parseViTimeTo24h(raw) {
        var s = String(raw || '').trim();
        if (s === '') {
            return '';
        }
        var m = s.match(/^(\d{1,2}):(\d{2})(?::\d{2})?\s*(dem|đêm|sang|chieu|toi|sa|ch|pm|tối|sáng|chiều)?\s*$/iu);
        if (!m) {
            return normalizeTime24h(s);
        }
        var hour = parseInt(m[1], 10);
        var minute = parseInt(m[2], 10);
        if (isNaN(hour) || isNaN(minute) || minute > 59) {
            return '';
        }
        var suffixRaw = (m[3] || '').toLowerCase();
        if (!suffixRaw && hour >= 13 && hour <= 23) {
            return String(hour).padStart(2, '0') + ':' + String(minute).padStart(2, '0');
        }
        if (!suffixRaw) {
            return normalizeTime24h(s) || '';
        }
        if (suffixRaw === 'dem' || suffixRaw === 'đêm') {
            if (hour === 12) {
                hour = 0;
            }
        } else if (suffixRaw === 'chieu' || suffixRaw === 'ch' || suffixRaw === 'chiều' || suffixRaw === 'pm') {
            if (hour < 12) {
                hour += 12;
            }
        } else if (suffixRaw === 'toi' || suffixRaw === 'tối') {
            if (hour < 12) {
                hour += 12;
            }
        }
        if (hour > 23 || minute > 59) {
            return '';
        }
        return String(hour).padStart(2, '0') + ':' + String(minute).padStart(2, '0');
    }

    var PICKUP_PERIOD_HOURS = {
        dem: [12, 1, 2, 3, 4],
        sang: [5, 6, 7, 8, 9, 10, 11],
        chieu: [12, 1, 2, 3, 4, 5],
        toi: [6, 7, 8, 9, 10, 11],
    };

    function getPickupTimeWidgetRoot() {
        return document.querySelector('[data-vi-pickup-time-root]');
    }

    function getActivePickupPeriod(root) {
        root = root || getPickupTimeWidgetRoot();
        if (!root) {
            return 'sang';
        }
        var btn = root.querySelector('.vi-pickup-period-btn.is-active');
        return (btn && btn.dataset.viPeriod) || 'sang';
    }

    function clock24FromWidgetParts(period, hour12, minute) {
        if (isNaN(hour12) || isNaN(minute)) {
            return '';
        }
        var hour24 = hour12;
        if (period === 'dem') {
            hour24 = hour12 === 12 ? 0 : hour12;
        } else if (period === 'sang') {
            hour24 = hour12;
        } else if (period === 'chieu') {
            hour24 = hour12 === 12 ? 12 : hour12 + 12;
        } else if (period === 'toi') {
            hour24 = hour12 + 12;
        }
        return String(hour24).padStart(2, '0') + ':' + String(minute).padStart(2, '0');
    }

    function rebuildPickupHourOptions(root, period, preferredHour) {
        var hourEl = root.querySelector('[data-vi-hour]');
        if (!hourEl) {
            return null;
        }
        var hours = PICKUP_PERIOD_HOURS[period] || PICKUP_PERIOD_HOURS.sang;
        var current = preferredHour != null ? parseInt(preferredHour, 10) : parseInt(hourEl.value, 10);
        hourEl.innerHTML = hours.map(function (h) {
            var label = String(h).padStart(2, '0') + 'h';
            return '<option value="' + h + '">' + label + '</option>';
        }).join('');
        if (hours.indexOf(current) === -1) {
            current = hours[0];
        }
        hourEl.value = String(current);
        return current;
    }

    function periodFromHour24(hour) {
        if (hour === 0) {
            return { displayHour: 12, period: 'dem' };
        }
        if (hour >= 1 && hour <= 4) {
            return { displayHour: hour, period: 'dem' };
        }
        if (hour >= 5 && hour <= 11) {
            return { displayHour: hour, period: 'sang' };
        }
        if (hour === 12) {
            return { displayHour: 12, period: 'chieu' };
        }
        if (hour < 18) {
            return { displayHour: hour - 12, period: 'chieu' };
        }
        return { displayHour: hour - 12, period: 'toi' };
    }

    function periodDisplayLabel(period) {
        if (period === 'dem') {
            return 'đêm';
        }
        if (period === 'chieu') {
            return 'chiều';
        }
        if (period === 'toi') {
            return 'tối';
        }
        return 'sáng';
    }

    function updatePickupTimePreview() {
        /* Không hiển thị dòng preview — giờ lưu qua hidden 24h. */
    }

    function readPickupTimeWidget24h() {
        var root = getPickupTimeWidgetRoot();
        if (!root) {
            return '';
        }
        var hourEl = root.querySelector('[data-vi-hour]');
        var minuteEl = root.querySelector('[data-vi-minute]');
        if (!hourEl || !minuteEl) {
            return '';
        }
        var hour = parseInt(hourEl.value, 10);
        var minute = parseInt(minuteEl.value, 10);
        var period = getActivePickupPeriod(root);
        if (isNaN(hour) || isNaN(minute)) {
            return '';
        }
        return clock24FromWidgetParts(period, hour, minute);
    }

    function writePickupTimeWidget(clock24) {
        var root = getPickupTimeWidgetRoot();
        if (!root || !clock24) {
            return;
        }
        var parts = String(clock24).split(':');
        var hour24 = parseInt(parts[0], 10);
        var minute = parseInt(parts[1], 10);
        if (isNaN(hour24) || isNaN(minute)) {
            return;
        }
        var mapped = periodFromHour24(hour24);
        var minuteEl = root.querySelector('[data-vi-minute]');
        root.querySelectorAll('.vi-pickup-period-btn').forEach(function (btn) {
            btn.classList.toggle('is-active', btn.dataset.viPeriod === mapped.period);
        });
        rebuildPickupHourOptions(root, mapped.period, mapped.displayHour);
        if (minuteEl) {
            var roundedMinute = Math.round(minute / 5) * 5;
            if (roundedMinute > 55) {
                roundedMinute = 55;
            }
            minuteEl.value = String(roundedMinute);
        }
        syncPickupTimeHiddenField();
    }

    function syncPickupTimeHiddenField() {
        var hidden = document.getElementById('modal-pickup-time');
        var clock24 = readPickupTimeWidget24h();
        if (hidden && clock24) {
            hidden.value = clock24;
        }
        updatePickupTimePreview();
        return clock24;
    }

    function getPickupTime24h() {
        var widgetValue = readPickupTimeWidget24h();
        if (widgetValue) {
            return widgetValue;
        }
        var input = document.getElementById('modal-pickup-time');
        return input ? parseViTimeTo24h(input.value) : '';
    }

    function formatViPickupTime(raw) {
        var clock = parseViTimeTo24h(raw) || normalizeTime24h(raw);
        if (!clock) {
            return String(raw || '').trim();
        }
        var parts = clock.split(':');
        var mapped = periodFromHour24(parseInt(parts[0], 10));
        return String(mapped.displayHour).padStart(2, '0') + ':' + parts[1] + ' ' + periodDisplayLabel(mapped.period);
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
        var clock24 = getPickupTime24h();
        if (!clock24) {
            return '';
        }
        writePickupTimeWidget(clock24);
        return clock24;
    }

    function setPickupTimeForSubmit() {
        var clock24 = syncPickupTimeHiddenField() || getPickupTime24h();
        var input = document.getElementById('modal-pickup-time');
        if (input && clock24) {
            input.value = clock24;
        }
        return clock24;
    }

    function setDefaultPickupTime() {
        var pickupAt = new Date();
        pickupAt.setMinutes(pickupAt.getMinutes() + 30);
        var clock24 = String(pickupAt.getHours()).padStart(2, '0') + ':'
            + String(pickupAt.getMinutes()).padStart(2, '0');
        writePickupTimeWidget(clock24);
    }

    function validatePickupTimeField() {
        var hidden = document.getElementById('modal-pickup-time');
        if (!hidden || hidden.dataset.viRequired !== '1') {
            return true;
        }
        var pickup = getPickupTime24h();
        if (!pickup) {
            if (window.AppDialog) {
                window.AppDialog.alert('Vui lòng chọn giờ đón.');
            }
            var hourEl = document.querySelector('[data-vi-hour]');
            if (hourEl) {
                hourEl.focus();
            }
            return false;
        }
        syncPickupTimeHiddenField();

        var dateInput = document.getElementById('modal-service-date');
        var serviceDate = dateInput ? String(dateInput.value || '').trim() : '';
        var now = new Date();
        var todayStr = now.getFullYear() + '-'
            + String(now.getMonth() + 1).padStart(2, '0') + '-'
            + String(now.getDate()).padStart(2, '0');

        if (serviceDate === todayStr) {
            var timeParts = pickup.split(':');
            var pickupAt = new Date(
                now.getFullYear(),
                now.getMonth(),
                now.getDate(),
                parseInt(timeParts[0], 10),
                parseInt(timeParts[1], 10),
                0,
            );
            if (pickupAt <= now) {
                var pastMsg = 'Giờ đón phải sau thời gian hiện tại.';
                var hourEl = document.querySelector('[data-vi-hour]');
                if (window.FormFieldValidation && window.FormFieldValidation.markInvalid && hourEl) {
                    FormFieldValidation.markInvalid(hourEl, pastMsg);
                } else if (window.AppDialog) {
                    window.AppDialog.alert(pastMsg);
                }
                if (hourEl) {
                    hourEl.focus();
                }
                return false;
            }
        }

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

    function syncWholeCarOption() {
        syncHiddenOfferRadiosFromFilter();
        updateBookingModeUi();
        updateModalOfferPresetSummary();
        if (activeTemplateId) {
            syncActiveUnitPriceFromTemplate(findTemplate(activeTemplateId));
            fetchQuotePrice();
            updateSummary(activeTemplateId);
        }
    }

    function setPartialBookingFromCard(capacity, seatsFree) {
        if (wantsWholeCarFromSearch()) {
            activeSeatsPartiallyBooked = false;
            return;
        }
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

    function syncFilterCapacityFromResponse(data) {
        if (!data || !data.filters) {
            return;
        }
        var cap = data.filters.vehicle_capacity;
        var select = document.getElementById('filter-vehicle-capacity');
        if (!select) {
            return;
        }
        select.value = cap ? String(cap) : '';
    }

    function updateBookingModeUi() {
        var wantsWhole = wantsWholeCarFromSearch();
        var wrap = document.getElementById('modal-seat-count-wrap');
        var vehicleCountWrap = document.getElementById('modal-vehicle-count-wrap');
        var spotSummary = document.getElementById('modal-spot-summary');
        var unitWrap = document.getElementById('modal-price-unit-wrap');
        if (wrap) {
            wrap.classList.toggle('d-none', wantsWhole || !canLoadSeats());
        }
        if (vehicleCountWrap) {
            vehicleCountWrap.classList.toggle('d-none', !wantsWhole);
        }
        if (spotSummary) spotSummary.classList.toggle('d-none', wantsWhole);
        if (unitWrap) unitWrap.classList.toggle('d-none', wantsWhole);
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
        var label = vehicleLabelForGuest(activeCapacity, tpl);
        if (step2) step2.textContent = label;
    }

    function applyTemplate(template) {
        if (!template) return;

        activeTemplateId = String(template.id);
        activeRoute = template.route;
        activeCapacity = parseInt(template.capacity, 10) || 0;
        setSelectedCapacity(getFilterCapacity() || activeCapacity);
        activeVehicleLabel = template.vehicle_label || (activeCapacity + ' chỗ');
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
        updateBookingModeUi();
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
        if (wantsWholeCarFromSearch()) {
            syncWholeCarOption();
            updateSummary(activeTemplateId);
            return;
        }
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

    function renderReferralDiscount(subtotal, total) {
        var html = '';
        var ref = lastQuoteReferral;
        if (referralHasCode && ref && ref.eligible && ref.percent > 0 && ref.amount > 0) {
            html = '<div class="small text-success fw-semibold">Giảm GT −' + ref.percent + '% (−' + formatMoney(ref.amount) + ')</div>'
                + '<div class="small text-muted text-decoration-line-through">' + formatMoney(subtotal) + '</div>';
        } else if (referralHasCode && ref && ref.reason) {
            html = '<div class="small text-warning">' + ref.reason + '</div>';
        } else if (referralHasCode && (window.__referralAttributionOnly || (ref && ref.attribution_only))) {
            html = '<div class="small text-muted">Mã người giới thiệu — không giảm giá vé</div>';
        } else if (referralHasCode && window.__referralDiscountPercent > 0 && !ref) {
            html = '<div class="small text-muted">Mã GT: giảm đến ' + window.__referralDiscountPercent + '%</div>';
        }
        ['modal-referral-discount-step1', 'modal-referral-discount-step2'].forEach(function (id) {
            var el = document.getElementById(id);
            if (!el) return;
            if (html) {
                el.innerHTML = html;
                el.classList.remove('d-none');
            } else {
                el.innerHTML = '';
                el.classList.add('d-none');
            }
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
        var isWhole = isWholeCarBooking();
        var seatCount = isWhole ? list.length : clampSharedSeatCount();
        var vehicleCount = isWhole ? clampVehicleCount() : 1;
        var hasSeats = isStep1Ready();
        var subtotal = isWhole ? (price * vehicleCount) : (seatCount * price);
        subtotal = roundToThousand(subtotal);
        var total = subtotal;
        if (lastQuoteReferral && lastQuoteReferral.eligible && lastQuoteReferral.total > 0) {
            total = lastQuoteReferral.total;
            subtotal = lastQuoteReferral.subtotal || subtotal;
        } else {
            total = subtotal;
        }

        if (seatSummaryText) {
            seatSummaryText.textContent = isWhole
                ? (vehicleCount > 1 ? (vehicleCount + ' xe · ' + bookingModeLabel('whole_car')) : bookingModeLabel('whole_car'))
                : seatCountLabel(seatCount);
        }
        if (totalEl) totalEl.textContent = formatMoney(total);
        if (listStep1) {
            if (wantsWholeCarFromSearch()) {
                listStep1.textContent = vehicleCount > 1
                    ? (vehicleCount + ' xe · ' + vehicleLabelForGuest(activeCapacity, findTemplate(templateId)))
                    : 'Cả xe';
            } else {
                listStep1.textContent = 'Số ghế: ' + seatCountLabel(seatCount);
            }
        }
        if (totalStep1) totalStep1.textContent = formatMoney(total);
        renderReferralDiscount(subtotal, total);
        var unitEl = document.getElementById('modal-price-unit');
        if (unitEl && price > 0 && !isWhole) unitEl.textContent = formatMoney(price);
        if (nextBtn) {
            nextBtn.disabled = false;
            nextBtn.textContent = 'Tiếp tục';
        }
        var submitBtn = document.getElementById('modal-submit-btn');
        if (submitBtn) {
            submitBtn.textContent = 'Xác nhận đặt vé';
        }
        if (footerStep1) footerStep1.classList.toggle('is-ready', !!activeTemplateId && isStep1SeatsReady());
    }

    function updateDriverSummary() {
        var el = document.getElementById('modal-driver-summary');
        if (!el) return;
        var parts = ['Hình thức: ' + bookingModeLabel(getBookingMode())];
        if (referralPrefill) {
            parts.push('Mã GT: ' + referralPrefill);
        }
        el.textContent = parts.join(' · ');
    }

    function applyReferralPrefill() {
        // Mã GT chỉ từ link ?ref= — hiển thị readonly trên form, không chỉnh tay.
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
            var phoneInput = document.getElementById('modal-contact-phone');
            if (phoneInput && !phoneInput.dataset.referralQuoteBound) {
                phoneInput.dataset.referralQuoteBound = '1';
                phoneInput.addEventListener('input', function () {
                    fetchQuotePrice();
                });
                phoneInput.addEventListener('change', function () {
                    guardActiveBookingForPhone(phoneInput.value, null, function (info) {
                        blockActiveBooking(null, info);
                    });
                });
            }
            fetchQuotePrice();
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
        activeCapacity = 0;
        allowNativeSubmit = false;
        setBookingStep(1);
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

    function findTemplateByRoute(departure, destination) {
        var dep = (departure || '').trim();
        var dest = (destination || '').trim();
        if (!dep || !dest) {
            return null;
        }
        var cap = getSelectedCapacityValue();
        return findTemplateByRouteAndCapacity(dep, dest, cap);
    }

    function findTemplateByRouteAndCapacity(departure, destination, capacity) {
        var dep = (departure || '').trim();
        var dest = (destination || '').trim();
        if (!dep || !dest) {
            return null;
        }
        var matches = bookingTemplates.filter(function (t) {
            return String(t.departure || '') === dep && String(t.destination || '') === dest;
        });
        if (!matches.length) {
            return null;
        }
        var cap = parseInt(capacity, 10) || 0;
        if (cap > 0) {
            var exact = matches.filter(function (t) {
                return Number(t.capacity) === cap;
            });
            if (exact.length) {
                exact.sort(function (a, b) {
                    return (Number(a.capacity_sort) || Number(a.capacity) || 0) - (Number(b.capacity_sort) || Number(b.capacity) || 0);
                });
                return exact[0];
            }
            return null;
        }
        matches.sort(function (a, b) {
            return (Number(a.capacity_sort) || Number(a.capacity) || 0) - (Number(b.capacity_sort) || Number(b.capacity) || 0);
        });
        return matches[0];
    }

    function getFilterCapacity() {
        var el = document.getElementById('filter-vehicle-capacity');
        if (!el || !el.value) {
            return 0;
        }
        return parseInt(el.value, 10) || 0;
    }

    function getSelectedCapacityValue() {
        var filterCap = getFilterCapacity();
        if (filterCap > 0) {
            return filterCap;
        }
        return activeSelectedCapacity || activeCapacity || 0;
    }

    function getVehicleCount() {
        var input = document.getElementById('modal-vehicle-count');
        var count = parseInt(input && input.value, 10) || 1;
        return Math.max(1, Math.min(count, 10));
    }

    function clampVehicleCount() {
        var input = document.getElementById('modal-vehicle-count');
        if (!input) {
            return 1;
        }
        input.value = String(getVehicleCount());
        return getVehicleCount();
    }

    function adjustVehicleCount(delta) {
        var input = document.getElementById('modal-vehicle-count');
        if (!input) {
            return;
        }
        var next = getVehicleCount() + delta;
        next = Math.max(1, Math.min(next, 10));
        input.value = String(next);
        fetchQuotePrice();
        updateSummary(activeTemplateId);
    }

    function getSearchRouteSelection() {
        if (!form) {
            return { departure: '', destination: '' };
        }
        var depEl = form.querySelector('[name="departure"]');
        var destEl = form.querySelector('[name="destination"]');
        return {
            departure: depEl ? String(depEl.value || '').trim() : '',
            destination: destEl ? String(destEl.value || '').trim() : '',
        };
    }

    function openBookingModalFromContext(ctx) {
        var modal = document.getElementById('bookingModal');
        if (!modal) {
            return;
        }

        resetBookingModal();
        applyReferralPrefill();

        var routeText = ctx.routeText || '';
        activeRoute = routeText;
        var serviceDate = ctx.serviceDate || getFilterServiceDate();

        var routeEl = document.getElementById('modal-route');
        var routeStep2 = document.getElementById('modal-route-step2');
        if (routeEl) routeEl.textContent = routeText;
        if (routeStep2) routeStep2.textContent = routeText;
        updateModalTripBanner(routeText);

        var dateInput = document.getElementById('modal-service-date');
        if (dateInput && serviceDate) {
            dateInput.value = serviceDate;
        }

        setRouteEndpoints(ctx.pickupDefault || '', ctx.dropoffDefault || '');

        var seatCountInput = document.getElementById('modal-seat-count');
        if (seatCountInput) seatCountInput.value = '1';

        var vehicleCountInput = document.getElementById('modal-vehicle-count');
        if (vehicleCountInput) vehicleCountInput.value = '1';

        setPartialBookingFromCard(ctx.capacity, ctx.seatsFree);

        var template = ctx.template || findTemplate(ctx.templateId);
        if (template) {
            applyTemplate(template);
        } else if (ctx.templateId) {
            activeTemplateId = String(ctx.templateId);
            activeCapacity = parseInt(ctx.capacity, 10) || 0;
            var templateInput = document.getElementById('modal-template-id');
            if (templateInput) templateInput.value = activeTemplateId;
            var cap = getFilterCapacity() || activeCapacity || 0;
            if (cap) {
                setSelectedCapacity(cap);
                updateVehicleDisplay();
            }
            setDefaultPickupTime();
        } else {
            setDefaultPickupTime();
        }

        applyOfferFilterToModalRadios();
        syncWholeCarOption();
        onTripContextChange();
        updateBookingModeUi();
        startSeatPolling();
        bootstrap.Modal.getOrCreateInstance(modal).show();
    }

    function openCustomBookingModal() {
        var route = getSearchRouteSelection();
        if (!route.departure || !route.destination) {
            if (window.AppDialog) {
                window.AppDialog.alert('Vui lòng chọn điểm đi và điểm đến ở form tìm chuyến phía trên.');
            }
            return;
        }
        if (route.departure === route.destination) {
            if (window.AppDialog) {
                window.AppDialog.alert('Điểm đến phải khác điểm đi.');
            }
            return;
        }

        var serviceDate = getFilterServiceDate();
        var routeText = route.departure + ' → ' + route.destination;
        var localTemplate = findTemplateByRoute(route.departure, route.destination);

        function launchWithTrip(trip) {
            if (trip) {
                upsertBookingTemplate(trip);
            }
            var template = trip ? findTemplate(trip.id) : localTemplate;
            openBookingModalFromContext({
                templateId: template ? template.id : null,
                template: template,
                routeText: routeText,
                serviceDate: serviceDate,
                pickupDefault: route.departure,
                dropoffDefault: route.destination,
                capacity: trip ? trip.capacity : (template ? template.capacity : null),
                seatsFree: trip && trip.seats_free != null ? trip.seats_free : undefined,
            });
        }

        if (localTemplate) {
            launchWithTrip(null);
            return;
        }

        if (!resolveRouteUrl) {
            if (window.AppDialog) {
                window.AppDialog.alert('Chưa có tuyến cho cặp điểm này — vui lòng gọi tổng đài ' + contactPhone + '.');
            }
            return;
        }

        var params = new URLSearchParams({
            departure: route.departure,
            destination: route.destination,
            service_date: serviceDate,
        });
        var filterCap = getFilterCapacity();
        if (filterCap > 0) {
            params.set('vehicle_capacity', String(filterCap));
        }

        fetch(resolveRouteUrl + '?' + params.toString(), { headers: { Accept: 'application/json' } })
            .then(function (r) {
                return r.json().then(function (data) {
                    if (!r.ok) {
                        throw new Error(data.message || 'Không tìm được tuyến phù hợp.');
                    }
                    return data;
                });
            })
            .then(function (data) {
                launchWithTrip(data.trip || null);
            })
            .catch(function (err) {
                if (window.AppDialog) {
                    window.AppDialog.alert(err.message || ('Chưa có tuyến — gọi tổng đài ' + contactPhone + '.'));
                }
            });
    }

    document.querySelectorAll('[data-open-booking]').forEach(function (btn) {
        btn.setAttribute('data-booking-bound', '1');
        btn.addEventListener('click', function () {
            openBookingModalFromContext({
                templateId: btn.dataset.templateId,
                routeText: btn.dataset.route || '',
                serviceDate: btn.dataset.serviceDate || getFilterServiceDate(),
                pickupDefault: btn.dataset.pickupDefault || '',
                dropoffDefault: btn.dataset.dropoffDefault || '',
                capacity: btn.dataset.capacity,
                seatsFree: btn.dataset.seatsFree,
            });
        });
    });

    function bindOpenBookingButtons(scope) {
        (scope || document).querySelectorAll('[data-open-booking]:not([data-booking-bound])').forEach(function (btn) {
            btn.setAttribute('data-booking-bound', '1');
            btn.addEventListener('click', function () {
                openBookingModalFromContext({
                    templateId: btn.dataset.templateId,
                    routeText: btn.dataset.route || '',
                    serviceDate: btn.dataset.serviceDate || getFilterServiceDate(),
                    pickupDefault: btn.dataset.pickupDefault || '',
                    dropoffDefault: btn.dataset.dropoffDefault || '',
                    capacity: btn.dataset.capacity,
                    seatsFree: btn.dataset.seatsFree,
                });
            });
        });
    }

    var customBookingBtns = document.querySelectorAll('.js-open-custom-booking');
    customBookingBtns.forEach(function (btn) {
        btn.addEventListener('click', openCustomBookingModal);
    });

    var dateInput = document.getElementById('modal-service-date');
    if (dateInput) {
        dateInput.addEventListener('change', onTripContextChange);
    }

    var filterDateInput = document.getElementById('filter-service-date');
    if (filterDateInput) {
        filterDateInput.addEventListener('change', function () {
            syncRouteSheetDateDisplay(filterDateInput.value);
            var modalDate = document.getElementById('modal-service-date');
            if (modalDate && !modalDate.value) {
                modalDate.value = filterDateInput.value;
            }
        });
    }

    function initViPickupTimeWidget() {
        var root = getPickupTimeWidgetRoot();
        if (!root) {
            return;
        }
        var hidden = document.getElementById('modal-pickup-time');
        if (hidden && hidden.value) {
            var initial = parseViTimeTo24h(hidden.value) || normalizeTime24h(hidden.value);
            if (initial) {
                writePickupTimeWidget(initial);
            } else {
                rebuildPickupHourOptions(root, getActivePickupPeriod(root));
            }
        } else {
            rebuildPickupHourOptions(root, getActivePickupPeriod(root));
        }
        root.querySelectorAll('[data-vi-hour], [data-vi-minute]').forEach(function (el) {
            el.addEventListener('change', function () {
                syncPickupTimeHiddenField();
                onTripContextChange();
            });
        });
        root.querySelectorAll('.vi-pickup-period-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var nextPeriod = btn.dataset.viPeriod || 'sang';
                var hourEl = root.querySelector('[data-vi-hour]');
                var preferredHour = hourEl ? parseInt(hourEl.value, 10) : null;
                root.querySelectorAll('.vi-pickup-period-btn').forEach(function (other) {
                    other.classList.remove('is-active');
                });
                btn.classList.add('is-active');
                rebuildPickupHourOptions(root, nextPeriod, preferredHour);
                syncPickupTimeHiddenField();
                onTripContextChange();
            });
        });
        syncPickupTimeHiddenField();
    }

    initViPickupTimeWidget();

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

    var vehicleCountInput = document.getElementById('modal-vehicle-count');
    if (vehicleCountInput) {
        vehicleCountInput.addEventListener('input', function () {
            clampVehicleCount();
            fetchQuotePrice();
            updateSummary(activeTemplateId);
        });
    }

    var vehicleMinus = document.getElementById('modal-vehicle-count-minus');
    if (vehicleMinus) vehicleMinus.addEventListener('click', function () { adjustVehicleCount(-1); });

    var vehiclePlus = document.getElementById('modal-vehicle-count-plus');
    if (vehiclePlus) vehiclePlus.addEventListener('click', function () { adjustVehicleCount(1); });

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
        bookingModal.addEventListener('shown.bs.modal', function () {
            updateBookingModeUi();
            if (activeTemplateId) {
                fetchQuotePrice();
                updateSummary(activeTemplateId);
            }
            var phoneInput = document.getElementById('modal-contact-phone');
            if (phoneInput && phoneInput.value.trim()) {
                guardActiveBookingForPhone(phoneInput.value, null, function (info) {
                    blockActiveBooking(null, info);
                });
            }
        });
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

            checkDuplicateBeforeSubmit(function (isBlocked, bookingInfo) {
                if (isBlocked === null) {
                    if (window.AppDialog) {
                        window.AppDialog.alert('Không kiểm tra được đơn đang chạy. Vui lòng thử lại sau vài giây.');
                    }
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Xác nhận đặt vé';
                    }
                    return;
                }
                if (isBlocked) {
                    blockActiveBooking(submitBtn, bookingInfo);
                    return;
                }
                finalizeBookingSubmit(submitBtn);
            });
        });
    }

    function checkDuplicateBeforeSubmit(callback, phoneOverride) {
        var phoneInput = document.getElementById('modal-contact-phone');
        var phone = phoneOverride != null
            ? String(phoneOverride).trim()
            : (phoneInput ? phoneInput.value.trim() : '');
        if (!checkDuplicateUrl || !phone) {
            callback(false, null);
            return;
        }
        var params = new URLSearchParams({ contact_phone: phone });
        if (activeTemplateId) {
            params.set('template_id', activeTemplateId);
        }
        fetch(checkDuplicateUrl + '?' + params.toString(), { headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                callback(!!(data && (data.active_booking || data.duplicate)), data && data.booking ? data.booking : null);
            })
            .catch(function () {
                callback(null, null);
            });
    }

    function guardActiveBookingForPhone(phone, onClear, onBlocked) {
        var trimmed = String(phone || '').trim();
        if (!trimmed) {
            if (typeof onClear === 'function') {
                onClear();
            }
            return;
        }

        checkDuplicateBeforeSubmit(function (isBlocked, bookingInfo) {
            if (isBlocked === null) {
                return;
            }
            if (isBlocked) {
                if (typeof onBlocked === 'function') {
                    onBlocked(bookingInfo);
                } else {
                    blockActiveBooking(null, bookingInfo);
                }
                return;
            }
            if (typeof onClear === 'function') {
                onClear();
            }
        }, trimmed);
    }

    function finalizeBookingSubmit(submitBtn) {
        refreshOccupiedBeforeSubmit(function () {
            var isWhole = getBookingMode() === 'whole_car';
            var seatCount = isWhole ? clampVehicleCount() : clampSharedSeatCount();
            var free = getAvailableSeatCount();
            if (!isWhole && seatCount > free) {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Xác nhận đặt vé';
                }
                updateSummary(activeTemplateId);
                if (window.AppDialog) {
                    window.AppDialog.alert('Không đủ ghế trống. Vui lòng giảm số ghế.');
                }
                setBookingStep(1);
                loadSeatAvailability();
                return;
            }

            if (submitBtn) submitBtn.textContent = 'Đang gửi...';
            setSelectedCapacity(getSelectedCapacityValue());
            var templateInput = document.getElementById('modal-template-id');
            if (templateInput && activeTemplateId) {
                templateInput.value = activeTemplateId;
            }
            syncHiddenOfferRadiosFromFilter();
            setPickupTimeForSubmit();
            allowNativeSubmit = true;
            if (typeof bookingForm.requestSubmit === 'function') {
                bookingForm.requestSubmit();
            } else {
                bookingForm.submit();
            }
        });
    }

    function blockActiveBooking(submitBtn, bookingInfo) {
        var routeLabel = bookingInfo && bookingInfo.route ? bookingInfo.route : 'cuốc trước';
        var tripCode = bookingInfo && bookingInfo.trip_code ? bookingInfo.trip_code : '';
        var statusLabel = bookingInfo && bookingInfo.progress_label ? bookingInfo.progress_label : '';
        var ordersUrl = window.__guestOrdersUrl || '/guest/orders';
        var message = 'Bạn đang có một cuốc chưa hoàn thành (' + routeLabel;
        if (tripCode) {
            message += ', mã ' + tripCode;
        }
        if (statusLabel) {
            message += ' — ' + statusLabel;
        }
        message += '). Hoàn thành hoặc hủy cuốc đó trước khi đặt cuốc mới.';

        if (window.AppDialog && window.AppDialog.confirm) {
            window.AppDialog.confirm({
                title: 'Chưa thể đặt thêm',
                message: message,
                confirmText: 'Xem đơn của tôi',
                cancelText: 'Đóng',
            }).then(function (ok) {
                if (ok) {
                    window.location.href = ordersUrl;
                }
            });
        } else {
            window.alert(message);
        }

        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Xác nhận đặt vé';
        }
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function formatVnd(amount) {
        return Number(amount || 0).toLocaleString('vi-VN') + ' đ';
    }

    function hasActiveSearchFilters() {
        if (!form) {
            return false;
        }
        var departure = form.querySelector('[name="departure"]');
        var destination = form.querySelector('[name="destination"]');
        var urlParams = new URLSearchParams(window.location.search);
        var capActive = urlParams.has('vehicle_capacity') && urlParams.get('vehicle_capacity') !== '';
        return !!((departure && departure.value) || (destination && destination.value) || capActive);
    }

    function updateListHead() {
        var textEl = document.querySelector('.booking-list-title-text');
        if (textEl) {
            textEl.textContent = hasActiveSearchFilters() ? 'Kết quả' : 'Tuyến cố định';
        }
        updateClearSearchButton();
    }

    function updateClearSearchButton() {
        var row = document.querySelector('.booking-list-head-row');
        var btn = document.getElementById('booking-clear-search');
        var show = hasActiveSearchFilters();
        if (show && !btn && row) {
            btn = document.createElement('button');
            btn.type = 'button';
            btn.id = 'booking-clear-search';
            btn.className = 'btn btn-sm btn-outline-secondary booking-clear-search-btn';
            btn.textContent = 'Xóa tìm kiếm';
            btn.addEventListener('click', clearSearchFilters);
            row.appendChild(btn);
        } else if (!show && btn) {
            btn.remove();
        }
    }

    function updateTripCount(pagination, tripsLength) {
        var cnt = document.getElementById('trip-count');
        if (!cnt) {
            return;
        }
        cnt.textContent = (pagination && pagination.total != null)
            ? pagination.total
            : (tripsLength || 0);
    }

    function renderTripCard(trip) {
        var vehicleType = String(trip.vehicle_type || 'sedan').charAt(0).toUpperCase();
        var priceLabel = formatVnd(trip.whole_car_price || trip.price_raw);
        var photoHtml = trip.vehicle_photo_url
            ? '<img src="' + escapeHtml(trip.vehicle_photo_url) + '" alt="" class="trip-vehicle-photo" loading="lazy" decoding="async">'
            : '<div class="trip-vehicle-photo trip-vehicle-photo--empty">' + escapeHtml(vehicleType) + '</div>';
        var routeLine = trip.route_line || ((trip.departure || '') + ' → ' + (trip.destination || ''));

        return ''
            + '<article class="trip-card-pro" data-template-id="' + escapeHtml(trip.id) + '">'
            + '<div class="trip-card-layout">'
            + '<div class="trip-vehicle-thumb" aria-hidden="true">' + photoHtml + '</div>'
            + '<div class="trip-card-body">'
            + '<div class="trip-route-line">'
            + '<span class="city">' + escapeHtml(trip.departure) + '</span>'
            + '<span class="arrow">→</span>'
            + '<span class="city">' + escapeHtml(trip.destination) + '</span>'
            + '</div>'
            + '<div class="trip-card-prices">'
            + '<span class="trip-price-inline">'
            + '<span class="trip-price-amount">' + escapeHtml(priceLabel) + '</span>'
            + '<span class="trip-price-unit">/cả xe</span>'
            + '</span>'
            + '</div>'
            + '</div>'
            + '<div class="trip-card-side">'
            + '<button type="button" class="btn btn-outline-primary btn-book fw-semibold"'
            + ' data-open-booking'
            + ' data-template-id="' + escapeHtml(trip.id) + '"'
            + ' data-route="' + escapeHtml(routeLine) + '"'
            + ' data-service-date="' + escapeHtml(trip.service_date || '') + '"'
            + ' data-date-label="' + escapeHtml(trip.date_label || '') + '"'
            + ' data-weekday="' + escapeHtml(trip.weekday || '') + '"'
            + ' data-date-short="' + escapeHtml(trip.date_short || '') + '"'
            + ' data-price="' + escapeHtml(trip.whole_car_price || trip.price_raw || '') + '"'
            + ' data-one-way-price="' + escapeHtml(trip.one_way_price || trip.price_raw || '') + '"'
            + ' data-whole-car-price="' + escapeHtml(trip.whole_car_price || '') + '"'
            + ' data-whole-car-round-price="' + escapeHtml(trip.whole_car_round_trip_price || '') + '"'
            + ' data-seat-round-trip-price="' + escapeHtml(trip.seat_round_trip_price || trip.round_trip_price || '') + '"'
            + ' data-round-trip-price="' + escapeHtml(trip.round_trip_price || '') + '"'
            + ' data-capacity="' + escapeHtml(trip.capacity || '') + '"'
            + ' data-vehicle-photo="' + escapeHtml(trip.vehicle_photo_url || '') + '"'
            + ' data-vehicle-label="' + escapeHtml(trip.vehicle_label || '') + '"'
            + ' data-pickup-default="' + escapeHtml(trip.pickup_default || '') + '"'
            + ' data-dropoff-default="' + escapeHtml(trip.dropoff_default || '') + '"'
            + ' data-seats-free="' + escapeHtml(trip.seats_free != null ? trip.seats_free : '') + '"'
            + '>Đặt chuyến</button>'
            + '</div>'
            + '</div>'
            + '</article>';
    }

    function renderEmptyTripsHtml() {
        return ''
            + '<div class="booking-empty-state" id="no-trips-msg">'
            + '<h3 class="h6 fw-bold">Không có chuyến phù hợp</h3>'
            + '<p class="text-muted small mb-3">Đổi ngày hoặc bộ lọc.</p>'
            + '<button type="button" class="btn btn-sm btn-outline-primary" id="booking-show-all-trips">Xem tất cả chuyến</button>'
            + '</div>';
    }

    function renderPaginationHtml(pagination) {
        if (!pagination || pagination.last_page <= 1) {
            return '';
        }
        var current = pagination.current_page || 1;
        var last = pagination.last_page || 1;
        var html = '<nav class="app-pagination mt-3" aria-label="Phân trang"><ul class="pagination pagination-sm justify-content-center mb-0">';

        if (current <= 1) {
            html += '<li class="page-item disabled" aria-disabled="true"><span class="page-link">Trước</span></li>';
        } else {
            html += '<li class="page-item"><a class="page-link" href="#" data-page="' + (current - 1) + '" rel="prev">Trước</a></li>';
        }

        var start = Math.max(1, current - 2);
        var end = Math.min(last, current + 2);
        for (var page = start; page <= end; page++) {
            if (page === current) {
                html += '<li class="page-item active" aria-current="page"><span class="page-link">' + page + '</span></li>';
            } else {
                html += '<li class="page-item"><a class="page-link" href="#" data-page="' + page + '">' + page + '</a></li>';
            }
        }

        if (current >= last) {
            html += '<li class="page-item disabled" aria-disabled="true"><span class="page-link">Sau</span></li>';
        } else {
            html += '<li class="page-item"><a class="page-link" href="#" data-page="' + (current + 1) + '" rel="next">Sau</a></li>';
        }

        html += '</ul></nav>';
        return html;
    }

    function updatePagination(pagination) {
        var wrap = document.getElementById('trips-pagination');
        if (!wrap) {
            return;
        }
        wrap.innerHTML = renderPaginationHtml(pagination);
    }

    function syncTripBasePrices(trip) {
        basePrice[trip.id] = {
            one_way: trip.one_way_price || trip.price_raw,
            round_trip: trip.seat_round_trip_price || trip.round_trip_price || trip.one_way_price || trip.price_raw,
            whole_car_round: trip.whole_car_round_trip_price || null,
        };
    }

    function fullRenderTripsList(data) {
        var list = document.getElementById('trips-list');
        if (!list) {
            return;
        }
        var trips = Array.isArray(data.trips) ? data.trips : [];
        if (!trips.length) {
            list.innerHTML = renderEmptyTripsHtml();
            var showAllBtn = document.getElementById('booking-show-all-trips');
            if (showAllBtn) {
                showAllBtn.addEventListener('click', clearSearchFilters);
            }
        } else {
            list.innerHTML = trips.map(renderTripCard).join('');
            bindOpenBookingButtons(list);
            trips.forEach(function (trip) {
                upsertBookingTemplate(trip);
                syncTripBasePrices(trip);
            });
            applyOfferFilterToAllCards();
        }
        updateTripCount(data.pagination, trips.length);
        updatePagination(data.pagination);
        updateListHead();
    }

    function buildFilterParams(page, options) {
        options = options || {};
        var params = new URLSearchParams(new FormData(form));
        if (options.useDefaultCapacity) {
            params.delete('vehicle_capacity');
        }
        if (page && page > 1) {
            params.set('page', String(page));
        } else {
            params.delete('page');
        }
        var ref = new URLSearchParams(window.location.search).get('ref');
        if (ref) {
            params.set('ref', ref);
        }
        return params;
    }

    function updateBrowserUrl(params) {
        var homeUrl = window.__customerHomeUrl || window.location.pathname;
        var query = params.toString();
        var next = query ? (homeUrl + '?' + query) : homeUrl;
        window.history.pushState({ customerFilters: true }, '', next);
    }

    function loadTripsList(page, options) {
        options = options || {};
        if (!form || !syncUrl) {
            return Promise.resolve();
        }

        var params = buildFilterParams(page, options);
        var submitBtn = document.getElementById('filter-search-submit');

        if (options.showLoading && submitBtn) {
            submitBtn.disabled = true;
            if (!submitBtn.dataset.prevLabel) {
                submitBtn.dataset.prevLabel = submitBtn.textContent;
            }
            submitBtn.textContent = 'Đang tìm...';
        }

        return fetch(syncUrl + '?' + params.toString(), { headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                fullRenderTripsList(data);
                syncFilterCapacityFromResponse(data);
                if (options.updateUrl) {
                    updateBrowserUrl(params);
                }
                if (options.scrollToResults && window.CustomerScrollDock) {
                    window.CustomerScrollDock.scrollToResults();
                }
                return data;
            })
            .catch(function () {})
            .finally(function () {
                if (options.showLoading && submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = submitBtn.dataset.prevLabel || 'Tìm chuyến';
                }
            });
    }

    function clearSearchFilters() {
        var dep = document.getElementById('filter-departure');
        var dest = document.getElementById('filter-destination');
        if (dep) {
            dep.value = '';
        }
        if (dest) {
            dest.value = '';
        }
        var capFilter = document.getElementById('filter-vehicle-capacity');
        if (capFilter) {
            capFilter.value = '';
        }
        if (typeof syncRouteSheetPickerTrigger === 'function') {
            syncRouteSheetPickerTrigger(dep);
            syncRouteSheetPickerTrigger(dest);
        }
        loadTripsList(1, { updateUrl: true, scrollToResults: true, useDefaultCapacity: true });
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
        var btn = card.querySelector('[data-open-booking]');
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
        syncTripBasePrices(trip);
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

        applyOfferFilterToCard(card);

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
        var page = parseInt(new URLSearchParams(window.location.search).get('page') || '1', 10);
        var params = buildFilterParams(page, {});
        fetch(syncUrl + '?' + params.toString(), { headers: { Accept: 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                updateTripCount(data.pagination, data.trips.length);
                var visibleIds = data.trips.map(function (trip) { return String(trip.id); });
                document.querySelectorAll('.trip-card-pro[data-template-id]').forEach(function (card) {
                    if (visibleIds.indexOf(card.getAttribute('data-template-id')) === -1) {
                        card.remove();
                    }
                });
                var list = document.getElementById('trips-list');
                data.trips.forEach(function (trip) {
                    var card = document.querySelector('.trip-card-pro[data-template-id="' + trip.id + '"]');
                    if (card) {
                        updateTripCard(card, trip);
                    } else if (list && !list.querySelector('#no-trips-msg')) {
                        list.insertAdjacentHTML('beforeend', renderTripCard(trip));
                        bindOpenBookingButtons(list);
                        upsertBookingTemplate(trip);
                        syncTripBasePrices(trip);
                        applyOfferFilterToCard(list.lastElementChild);
                    }
                });
                if (list && !list.querySelector('.trip-card-pro') && !list.querySelector('#no-trips-msg')) {
                    list.innerHTML = renderEmptyTripsHtml();
                    var showAllBtn = document.getElementById('booking-show-all-trips');
                    if (showAllBtn) {
                        showAllBtn.addEventListener('click', clearSearchFilters);
                    }
                }
            }).catch(function () {});
    }

    if (form && syncUrl) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            loadTripsList(1, { updateUrl: true, scrollToResults: true, showLoading: true });
        });

        var clearBtn = document.getElementById('booking-clear-search');
        if (clearBtn) {
            clearBtn.addEventListener('click', clearSearchFilters);
        }
        var showAllBtn = document.getElementById('booking-show-all-trips');
        if (showAllBtn) {
            showAllBtn.addEventListener('click', clearSearchFilters);
        }

        var paginationWrap = document.getElementById('trips-pagination');
        if (paginationWrap) {
            paginationWrap.addEventListener('click', function (event) {
                var link = event.target.closest('a.page-link[data-page], a.page-link[href]');
                if (!link || link.classList.contains('disabled')) {
                    return;
                }
                event.preventDefault();
                var page = parseInt(link.dataset.page || new URL(link.href, window.location.origin).searchParams.get('page') || '1', 10);
                loadTripsList(page, { updateUrl: true, scrollToResults: true });
            });
        }

        window.addEventListener('popstate', function () {
            var params = new URLSearchParams(window.location.search);
            var dep = document.getElementById('filter-departure');
            var dest = document.getElementById('filter-destination');
            var date = document.getElementById('filter-service-date');
            if (dep) {
                dep.value = params.get('departure') || '';
            }
            if (dest) {
                dest.value = params.get('destination') || '';
            }
            if (date && params.has('service_date')) {
                date.value = params.get('service_date') || date.value;
                syncRouteSheetDateDisplay(date.value);
            }
            if (typeof syncRouteSheetPickerTrigger === 'function') {
                syncRouteSheetPickerTrigger(dep);
                syncRouteSheetPickerTrigger(dest);
            }
            var page = parseInt(params.get('page') || '1', 10);
            loadTripsList(page, { updateUrl: false });
        });

        poll();
        setInterval(poll, 12000);
    }

    if (window.__guestTripSearchingReload && window.CustomerScrollDock) {
        window.setTimeout(function () {
            window.CustomerScrollDock.scrollToTrack();
        }, 400);
    }

    initRouteSheetOfferFilters();
    setOfferFilter(activeOfferFilterId);

    function syncRouteSheetPickerTrigger(select) {
        if (!select || !select.id) {
            return;
        }
        var trigger = document.querySelector('.route-sheet-picker-trigger[data-picker-for="' + select.id + '"]');
        if (!trigger) {
            return;
        }
        var textEl = trigger.querySelector('.route-sheet-picker-text');
        var placeholder = trigger.dataset.placeholder || 'Chọn';
        var option = select.options[select.selectedIndex];
        var hasValue = !!(select.value && option);
        var label = hasValue ? option.textContent : placeholder;
        if (textEl) {
            textEl.textContent = label;
        }
        trigger.classList.toggle('is-placeholder', !hasValue);
    }

    function initRouteSheetPickers() {
        var sheet = document.getElementById('route-sheet-province-picker');
        var body = document.getElementById('route-sheet-picker-body');
        var titleEl = document.getElementById('route-sheet-picker-title');
        if (!sheet || !body) {
            return;
        }

        var activeSelect = null;
        var activeTrigger = null;

        document.querySelectorAll('.route-sheet-stop-native').forEach(syncRouteSheetPickerTrigger);

        function closePicker() {
            sheet.hidden = true;
            document.body.classList.remove('route-sheet-picker-open');
            if (activeTrigger) {
                activeTrigger.setAttribute('aria-expanded', 'false');
            }
            activeSelect = null;
            activeTrigger = null;
        }

        function appendPickerItem(container, label, value, selectedValue, isPlaceholder) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'route-sheet-picker-item'
                + (value === selectedValue ? ' is-selected' : '')
                + (isPlaceholder ? ' route-sheet-picker-item--placeholder' : '');
            btn.textContent = label;
            btn.dataset.value = value;
            btn.setAttribute('role', 'option');
            if (value === selectedValue) {
                btn.setAttribute('aria-selected', 'true');
            }
            container.appendChild(btn);
        }

        function openPicker(select, trigger) {
            activeSelect = select;
            activeTrigger = trigger;
            titleEl.textContent = select.id === 'filter-destination' ? 'Chọn điểm đến' : 'Chọn điểm đi';
            body.innerHTML = '';

            appendPickerItem(body, trigger.dataset.placeholder || 'Tất cả', '', select.value, true);

            Array.from(select.children).forEach(function (node) {
                if (node.tagName === 'OPTGROUP') {
                    var group = document.createElement('div');
                    group.className = 'route-sheet-picker-group';
                    var groupLabel = document.createElement('div');
                    groupLabel.className = 'route-sheet-picker-group-label';
                    groupLabel.textContent = node.label || '';
                    group.appendChild(groupLabel);
                    Array.from(node.children).forEach(function (opt) {
                        if (opt.tagName !== 'OPTION' || !opt.value) {
                            return;
                        }
                        appendPickerItem(group, opt.textContent, opt.value, select.value, false);
                    });
                    body.appendChild(group);
                    return;
                }
                if (node.tagName === 'OPTION' && node.value) {
                    appendPickerItem(body, node.textContent, node.value, select.value, false);
                }
            });

            sheet.hidden = false;
            document.body.classList.add('route-sheet-picker-open');
            trigger.setAttribute('aria-expanded', 'true');
        }

        document.querySelectorAll('.route-sheet-picker-trigger').forEach(function (trigger) {
            trigger.addEventListener('click', function () {
                var select = document.getElementById(trigger.dataset.pickerFor);
                if (!select) {
                    return;
                }
                if (!sheet.hidden && activeSelect === select) {
                    closePicker();
                    return;
                }
                openPicker(select, trigger);
            });
        });

        body.addEventListener('click', function (event) {
            var item = event.target.closest('.route-sheet-picker-item');
            if (!item || !activeSelect) {
                return;
            }
            activeSelect.value = item.dataset.value;
            syncRouteSheetPickerTrigger(activeSelect);
            activeSelect.dispatchEvent(new Event('change', { bubbles: true }));
            closePicker();
        });

        sheet.querySelectorAll('[data-picker-close]').forEach(function (el) {
            el.addEventListener('click', closePicker);
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !sheet.hidden) {
                closePicker();
            }
        });
    }

    initRouteSheetPickers();

    document.querySelectorAll('.swap-route-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var dep = form.querySelector('[name="departure"]');
            var dest = form.querySelector('[name="destination"]');
            if (dep && dest) {
                var t = dep.value;
                dep.value = dest.value;
                dest.value = t;
                syncRouteSheetPickerTrigger(dep);
                syncRouteSheetPickerTrigger(dest);
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
            if (restore.vehicle_capacity) {
                setSelectedCapacity(restore.vehicle_capacity);
            }
            if (restore.vehicle_count) {
                var vehicleCountInput = document.getElementById('modal-vehicle-count');
                if (vehicleCountInput) vehicleCountInput.value = String(restore.vehicle_count);
            }
            if (restore.seat_count) {
                var seatCountInput = document.getElementById('modal-seat-count');
                if (seatCountInput) seatCountInput.value = String(restore.seat_count);
            }
            updateSummary(String(restore.template_id));
            setBookingStep(restore.step || 2);
            if (restore.duplicate_route && window.AppDialog) {
                window.AppDialog.alert('Bạn đang có cuốc chưa hoàn thành. Hoàn thành hoặc hủy cuốc đó trước khi đặt cuốc mới.');
            }
        }, 350);
    }

    restoreBookingModalFromValidation();

    function initPickupAddressAutocomplete() {
        if (!window.GeocodeAddressAutocomplete) {
            return;
        }

        window.GeocodeAddressAutocomplete.attach({
            detailInputId: 'modal-pickup-detail',
            latInputId: 'modal-pickup-lat',
            lngInputId: 'modal-pickup-lng',
            provinceInputId: 'modal-pickup',
            onSelect: function () {
                var detailEl = document.getElementById('modal-pickup-detail');
                if (detailEl && window.FormFieldValidation && FormFieldValidation.clearInvalid) {
                    FormFieldValidation.clearInvalid(detailEl);
                }
                updateSummary(activeTemplateId);
            },
        });

        document.addEventListener('addressmap:applied', function (e) {
            if (!e.detail || e.detail.targetInputId !== 'modal-pickup-detail') {
                return;
            }
            var detailEl = document.getElementById('modal-pickup-detail');
            if (detailEl && window.FormFieldValidation && FormFieldValidation.clearInvalid) {
                FormFieldValidation.clearInvalid(detailEl);
            }
            updateSummary(activeTemplateId);
        });
    }

    initPickupAddressAutocomplete();

    applyReferralPrefill();
    if (referralPrefill) {
        updateDriverSummary();
    }

    if (window.__bookingShowResult && !window.__bookingRestoreModal) {
        scrollToBookingResult();
    }
})();
