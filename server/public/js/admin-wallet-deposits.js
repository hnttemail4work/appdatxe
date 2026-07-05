(function () {
    function formatVnd(amount) {
        return new Intl.NumberFormat('vi-VN').format(amount) + ' đ';
    }

    function bindWalletDepositBulkControls() {
        var selectAll = document.getElementById('wallet-deposit-select-all');
        var bulkBtn = document.getElementById('wallet-deposit-bulk-btn');
        var summary = document.getElementById('wallet-deposit-bulk-summary');
        var countEl = document.getElementById('wallet-deposit-bulk-count');
        var amountEl = document.getElementById('wallet-deposit-bulk-amount');
        var rowChecks = document.querySelectorAll('.wallet-deposit-row-check');

        if (!bulkBtn || rowChecks.length === 0) {
            return;
        }

        function syncBulkState() {
            var checked = Array.prototype.filter.call(rowChecks, function (el) {
                return el.checked;
            });
            var total = checked.reduce(function (sum, el) {
                return sum + (parseInt(el.getAttribute('data-amount'), 10) || 0);
            }, 0);

            bulkBtn.disabled = checked.length < 1;

            if (summary && countEl && amountEl) {
                if (checked.length > 0) {
                    summary.hidden = false;
                    countEl.textContent = String(checked.length);
                    amountEl.textContent = formatVnd(total);
                } else {
                    summary.hidden = true;
                }
            }

            if (selectAll) {
                selectAll.indeterminate = checked.length > 0 && checked.length < rowChecks.length;
                selectAll.checked = checked.length === rowChecks.length;
            }
        }

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                var checked = selectAll.checked;
                rowChecks.forEach(function (el) {
                    el.checked = checked;
                });
                syncBulkState();
            });
        }

        rowChecks.forEach(function (el) {
            el.addEventListener('change', syncBulkState);
        });

        syncBulkState();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindWalletDepositBulkControls);
    } else {
        bindWalletDepositBulkControls();
    }
})();
