(function () {
    function syncQrAmount(qrSelector, amount) {
        var qr = document.querySelector(qrSelector);
        if (!qr) return;
        var bin = qr.dataset.bankBin;
        var account = qr.dataset.account;
        if (!bin || !account) return;
        var params = new URLSearchParams();
        if (amount > 0) params.set('amount', String(amount));
        if (qr.dataset.addInfo) params.set('addInfo', qr.dataset.addInfo);
        if (qr.dataset.accountName) params.set('accountName', qr.dataset.accountName);
        qr.src = 'https://img.vietqr.io/image/' + bin + '-' + account + '-compact2.jpg?' + params.toString();
    }

    function initTransferForm(form) {
        var startBtn = form.querySelector('[data-transfer-start]');
        var confirmBtn = form.querySelector('[data-transfer-confirm]');
        var refStep = form.querySelector('[data-transfer-ref-step]');
        var amountInput = form.querySelector('[data-transfer-amount]');
        var refInput = refStep ? refStep.querySelector('input[name="transfer_ref"]') : null;
        var minAmount = parseInt(form.dataset.transferMin || '0', 10) || 0;
        var qrSelector = form.dataset.transferQr || '';

        function currentAmount() {
            if (!amountInput) return 0;
            return parseInt(amountInput.value, 10) || 0;
        }

        function showRefStep() {
            if (refStep) refStep.classList.remove('d-none');
            if (refInput) refInput.disabled = false;
            if (startBtn) startBtn.classList.add('d-none');
            if (confirmBtn) confirmBtn.classList.remove('d-none');
            if (refInput) refInput.focus();
        }

        if (form.dataset.transferOpen === '1') {
            showRefStep();
        }

        if (amountInput && amountInput.type !== 'hidden') {
            amountInput.addEventListener('input', function () {
                if (qrSelector) syncQrAmount(qrSelector, currentAmount());
            });
            if (qrSelector) syncQrAmount(qrSelector, currentAmount());
        }

        if (startBtn) {
            startBtn.addEventListener('click', function () {
                if (amountInput && amountInput.type !== 'hidden') {
                    var val = currentAmount();
                    if (minAmount > 0 && val < minAmount) {
                        window.alert('Số tiền tối thiểu ' + minAmount.toLocaleString('vi-VN') + ' đ.');
                        amountInput.focus();
                        return;
                    }
                    if (qrSelector) syncQrAmount(qrSelector, val);
                }
                showRefStep();
            });
        }
    }

    document.querySelectorAll('.transfer-confirm-form').forEach(initTransferForm);
})();
