/**
 * Nạp ví — nhập số tiền, sinh QR VietQR, bắt buộc ảnh CK, gửi POST.
 */
(function () {
    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.content : '';
    }

    function syncFormCsrf(form) {
        var token = csrfToken();
        if (!token) {
            return;
        }

        var hidden = form.querySelector('input[name="_token"]');
        if (hidden) {
            hidden.value = token;
        }
    }

    function formatVnd(amount) {
        return new Intl.NumberFormat('vi-VN').format(amount) + ' đ';
    }

    function readAmount(form) {
        var amountInput = form.querySelector('.driver-deposit-amount');
        if (!amountInput) {
            return 0;
        }

        var raw = String(amountInput.value || '').replace(/[^\d]/g, '');
        return parseInt(raw, 10) || 0;
    }

    function minAmount(form) {
        return parseInt(form.dataset.depositMin || '0', 10) || 0;
    }

    function syncQrAmount(qr, amount) {
        if (!qr) {
            return;
        }

        var bin = qr.dataset.bankBin;
        var account = qr.dataset.account;
        if (!bin || !account || amount <= 0) {
            return;
        }

        var params = new URLSearchParams();
        params.set('amount', String(amount));
        if (qr.dataset.addInfo) {
            params.set('addInfo', qr.dataset.addInfo);
        }
        if (qr.dataset.accountName) {
            params.set('accountName', qr.dataset.accountName);
        }

        qr.src = 'https://img.vietqr.io/image/' + bin + '-' + account + '-compact2.jpg?' + params.toString();
    }

    function setQrVisible(form, visible) {
        var qrSelector = form.dataset.depositQr || '';
        var qr = qrSelector ? document.querySelector(qrSelector) : null;
        var placeholder = form.querySelector('[data-deposit-qr-placeholder]');
        var amountLabel = form.querySelector('[data-deposit-amount-label]');

        if (qr) {
            qr.hidden = !visible;
            qr.classList.toggle('is-hidden', !visible);
        }

        if (placeholder) {
            placeholder.hidden = visible;
            placeholder.classList.toggle('is-hidden', visible);
        }

        if (amountLabel) {
            amountLabel.hidden = !visible;
        }
    }

    function amountValidationMessage(form, amount) {
        var min = minAmount(form);

        if (!amount || amount <= 0) {
            return 'Vui lòng nhập số tiền nạp.';
        }

        if (amount < min) {
            return 'Số tiền nạp tối thiểu ' + formatVnd(min) + '.';
        }

        return '';
    }

    function proofValidationMessage(form) {
        var proofInput = form.querySelector('.driver-deposit-proof');
        if (!proofInput) {
            return '';
        }

        if (!proofInput.files || proofInput.files.length === 0) {
            return 'Vui lòng đính kèm ảnh chụp chuyển khoản.';
        }

        return '';
    }

    function showAmountError(form, message) {
        var amountError = form.querySelector('[data-deposit-amount-error]');
        if (!amountError) {
            return;
        }

        if (message) {
            amountError.textContent = message;
            amountError.classList.remove('d-none');
            amountError.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            return;
        }

        amountError.textContent = '';
        amountError.classList.add('d-none');
    }

    function updateProofPreview(form) {
        var proofInput = form.querySelector('.driver-deposit-proof');
        var preview = form.querySelector('[data-deposit-proof-preview]');
        var previewImg = form.querySelector('[data-deposit-proof-preview-img]');
        if (!proofInput || !preview || !previewImg) {
            return;
        }

        if (!proofInput.files || proofInput.files.length === 0) {
            preview.hidden = true;
            preview.classList.add('d-none');
            previewImg.removeAttribute('src');
            return;
        }

        var file = proofInput.files[0];
        if (!file || !String(file.type || '').startsWith('image/')) {
            preview.hidden = true;
            preview.classList.add('d-none');
            previewImg.removeAttribute('src');
            return;
        }

        previewImg.src = URL.createObjectURL(file);
        preview.hidden = false;
        preview.classList.remove('d-none');
    }

    function setSubmitState(form, submitting) {
        var submitBtn = form.querySelector('.driver-deposit-submit-btn');
        if (!submitBtn) {
            return;
        }

        submitBtn.disabled = !!submitting;
        submitBtn.textContent = submitting ? 'Đang gửi...' : 'Gửi yêu cầu nạp';
    }

    function updateDepositUi(form) {
        var amount = readAmount(form);
        var min = minAmount(form);
        var valid = amount >= min;
        var qrSelector = form.dataset.depositQr || '';
        var qr = qrSelector ? document.querySelector(qrSelector) : null;
        var amountLabel = form.querySelector('[data-deposit-amount-label]');
        var message = amountValidationMessage(form, amount);

        setQrVisible(form, valid);

        if (valid) {
            syncQrAmount(qr, amount);
            if (amountLabel) {
                amountLabel.textContent = 'Chuyển khoản: ' + formatVnd(amount);
            }
        } else if (amountLabel) {
            amountLabel.textContent = '';
        }

        if (form.dataset.depositAttempted === '1') {
            showAmountError(form, message || proofValidationMessage(form));
        } else {
            showAmountError(form, '');
        }

        form.querySelectorAll('.driver-deposit-preset').forEach(function (btn) {
            var preset = parseInt(btn.getAttribute('data-amount') || '0', 10);
            btn.classList.toggle('is-active', valid && preset === amount);
        });
    }

    function submitDepositForm(form, event) {
        if (!form || form.dataset.depositSubmitting === '1') {
            return;
        }

        var amountInput = form.querySelector('.driver-deposit-amount');
        if (!amountInput) {
            return;
        }

        form.dataset.depositAttempted = '1';

        var amount = readAmount(form);
        var amountMessage = amountValidationMessage(form, amount);
        var proofMessage = proofValidationMessage(form);

        updateDepositUi(form);

        if (amountMessage || proofMessage) {
            if (event) {
                event.preventDefault();
                event.stopPropagation();
            }
            showAmountError(form, amountMessage || proofMessage);
            if (amountMessage) {
                amountInput.focus();
            }
            return;
        }

        if (event) {
            event.preventDefault();
            event.stopPropagation();
        }

        amountInput.value = String(amount);
        syncFormCsrf(form);
        form.dataset.depositSubmitting = '1';
        setSubmitState(form, true);
        showAmountError(form, '');
        form.submit();
    }

    function bindDepositForm(form) {
        if (!form || form.dataset.depositQrBound === '1') {
            return;
        }

        form.dataset.depositQrBound = '1';

        var amountInput = form.querySelector('.driver-deposit-amount');
        if (!amountInput) {
            return;
        }

        amountInput.addEventListener('input', function () {
            updateDepositUi(form);
        });

        amountInput.addEventListener('change', function () {
            updateDepositUi(form);
        });

        form.querySelectorAll('.driver-deposit-preset').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var preset = parseInt(btn.getAttribute('data-amount') || '0', 10);
                if (!preset) {
                    return;
                }

                amountInput.value = String(preset);
                updateDepositUi(form);
                amountInput.focus();
            });
        });

        var proofInput = form.querySelector('.driver-deposit-proof');
        if (proofInput) {
            proofInput.addEventListener('change', function () {
                updateProofPreview(form);
                showAmountError(form, '');
            });
        }

        updateDepositUi(form);
    }

    function init() {
        document.querySelectorAll('.driver-wallet-deposit-form').forEach(bindDepositForm);
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.classList.contains('driver-wallet-deposit-form')) {
            return;
        }

        submitDepositForm(form, event);
    }, true);

    document.addEventListener('drivertab:changed', function (event) {
        if (!event.detail || event.detail.tab !== 'wallet') {
            return;
        }

        document.querySelectorAll('.driver-wallet-deposit-form').forEach(function (form) {
            bindDepositForm(form);
            delete form.dataset.depositSubmitting;
            delete form.dataset.depositAttempted;
            setSubmitState(form, false);
            updateDepositUi(form);
        });
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
