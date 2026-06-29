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

    document.querySelectorAll('.driver-wallet-deposit-form').forEach(function (form) {
        var amountInput = form.querySelector('.driver-deposit-amount');
        var minAmount = parseInt(form.dataset.depositMin || '0', 10) || 0;
        var qrSelector = form.dataset.depositQr || '';

        function currentAmount() {
            return amountInput ? (parseInt(amountInput.value, 10) || 0) : 0;
        }

        if (amountInput) {
            amountInput.addEventListener('input', function () {
                if (qrSelector) syncQrAmount(qrSelector, currentAmount());
            });
            if (qrSelector) syncQrAmount(qrSelector, currentAmount());
        }

        form.addEventListener('submit', function (e) {
            var amount = currentAmount();
            if (minAmount > 0 && amount < minAmount) {
                e.preventDefault();
                window.alert('Số tiền tối thiểu ' + minAmount.toLocaleString('vi-VN') + ' đ.');
                amountInput.focus();
            }
        });
    });
})();
