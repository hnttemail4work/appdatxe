/**
 * Đặt xe — chọn xe, mở modal 2 bước, báo giá cả xe.
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

    function $(id) { return document.getElementById(id); }

    function formatMoney(n) {
        return (Number(n) || 0).toLocaleString('vi-VN') + ' đ';
    }

    function setStep(step) {
        var s1 = $('booking-step-1');
        var s2 = $('booking-step-2');
        var f1 = $('modal-footer-step1');
        var f2 = $('modal-footer-step2');
        if (s1) s1.classList.toggle('d-none', step !== 1);
        if (s2) s2.classList.toggle('d-none', step !== 2);
        if (f1) f1.classList.toggle('d-none', step !== 1);
        if (f2) f2.classList.toggle('d-none', step !== 2);
        document.querySelectorAll('.booking-step').forEach(function (el) {
            el.classList.toggle('active', Number(el.dataset.step) === step);
        });
    }

    function openFromButton(btn) {
        ctx = {
            templateId: btn.dataset.templateId,
            route: btn.dataset.route || '—',
            vehicleLabel: btn.dataset.vehicleLabel || '',
            vehiclePhoto: btn.dataset.vehiclePhoto || '',
            price: Number(btn.dataset.price) || 0,
            pickupDefault: btn.dataset.pickupDefault || '',
            dropoffDefault: btn.dataset.dropoffDefault || '',
        };

        $('modal-template-id').value = ctx.templateId;
        $('modal-route').textContent = ctx.route;
        $('modal-route-step2').textContent = ctx.route;
        $('modal-vehicle-label').textContent = ctx.vehicleLabel;
        $('modal-vehicle-step2').textContent = ctx.vehicleLabel;

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

        if ($('modal-pickup') && ! $('modal-pickup').value) {
            $('modal-pickup').value = ctx.pickupDefault;
        }
        if ($('modal-dropoff') && ! $('modal-dropoff').value) {
            $('modal-dropoff').value = ctx.dropoffDefault;
        }
        if ($('modal-service-date') && window.__defaultServiceDate) {
            $('modal-service-date').value = window.__defaultServiceDate;
        }

        setStep(1);
        refreshQuote();
        if (modal) modal.show();
    }

    function quoteParams() {
        return new URLSearchParams({
            template_id: ctx.templateId || $('modal-template-id').value,
            pickup_address: $('modal-pickup') ? $('modal-pickup').value : '',
            dropoff_address: $('modal-dropoff') ? $('modal-dropoff').value : '',
            contact_phone: $('modal-contact-phone') ? $('modal-contact-phone').value : '',
        });
    }

    function refreshQuote() {
        if (!window.__quotePriceUrl || !ctx.templateId) {
            updatePriceDisplay(ctx.price);
            return;
        }
        clearTimeout(quoteTimer);
        quoteTimer = setTimeout(function () {
            fetch(window.__quotePriceUrl + '?' + quoteParams().toString(), {
                headers: { Accept: 'application/json' },
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var total = data.total_after_discount != null ? data.total_after_discount : data.whole_car_price;
                    updatePriceDisplay(total, data);
                })
                .catch(function () {
                    updatePriceDisplay(ctx.price);
                });
        }, 200);
    }

    function updatePriceDisplay(total, data) {
        var label = formatMoney(total);
        ['modal-total-price', 'modal-total-price-step1'].forEach(function (id) {
            var el = $(id);
            if (el) el.textContent = label;
        });
        var disc = $('modal-referral-discount');
        if (disc && data && data.referral_eligible && data.referral_discount_amount > 0) {
            disc.textContent = 'Giảm ' + formatMoney(data.referral_discount_amount) + ' (mã GT)';
            disc.classList.remove('d-none');
        } else if (disc) {
            disc.classList.add('d-none');
        }
    }

    function validateStep1() {
        var date = $('modal-service-date');
        var pickupDetail = $('modal-pickup-detail');
        var pickup = $('modal-pickup');
        if (!date || !date.value) {
            alert('Vui lòng chọn ngày đi.');
            return false;
        }
        if (!pickupDetail || !pickupDetail.value.trim()) {
            alert('Vui lòng nhập điểm đón.');
            return false;
        }
        if (!pickup || !pickup.value) {
            alert('Vui lòng chọn tỉnh đón (qua gợi ý địa chỉ hoặc bản đồ).');
            return false;
        }
        return true;
    }

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

    ['modal-pickup', 'modal-dropoff', 'modal-pickup-detail', 'modal-dropoff-detail'].forEach(function (id) {
        var el = $(id);
        if (el) el.addEventListener('change', refreshQuote);
        if (el) el.addEventListener('blur', refreshQuote);
    });

    if (window.__bookingRestoreModal && window.__bookingRestoreModal.template_id) {
        var restoreBtn = document.querySelector('[data-open-booking][data-template-id="' + window.__bookingRestoreModal.template_id + '"]');
        if (restoreBtn) {
            openFromButton(restoreBtn);
            if (window.__bookingRestoreModal.step === 2) setStep(2);
        }
    }

    modalEl.addEventListener('hidden.bs.modal', function () {
        setStep(1);
    });
})();
