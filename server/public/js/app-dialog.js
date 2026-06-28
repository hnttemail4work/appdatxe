/**
 * Custom confirm / alert — thay cho confirm() và alert() của trình duyệt.
 * Form: data-confirm="..." data-confirm-title data-confirm-ok data-confirm-variant
 */
(function () {
    var modalEl = document.getElementById('appDialogModal');
    if (!modalEl || typeof bootstrap === 'undefined') {
        return;
    }

    var titleEl = document.getElementById('appDialogTitle');
    var bodyEl = document.getElementById('appDialogBody');
    var confirmBtn = document.getElementById('appDialogConfirm');
    var cancelBtn = document.getElementById('appDialogCancel');
    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    var pendingResolve = null;
    var settled = false;

    var variantClass = {
        primary: 'btn-primary',
        danger: 'btn-danger',
        success: 'btn-success',
        warning: 'btn-warning',
    };

    function finish(result) {
        if (settled || !pendingResolve) {
            return;
        }
        settled = true;
        var resolve = pendingResolve;
        pendingResolve = null;
        resolve(result);
    }

    function setConfirmVariant(variant) {
        confirmBtn.className = 'btn ' + (variantClass[variant] || variantClass.primary);
    }

    function openDialog(options) {
        var opts = options || {};
        var isAlert = !!opts.alert;

        settled = false;
        titleEl.textContent = opts.title || (isAlert ? 'Thông báo' : 'Xác nhận');
        bodyEl.textContent = opts.message || '';

        confirmBtn.textContent = opts.confirmText || (isAlert ? 'Đóng' : 'Xác nhận');
        cancelBtn.textContent = opts.cancelText || 'Huỷ';
        cancelBtn.classList.toggle('d-none', isAlert);
        setConfirmVariant(opts.variant || (isAlert ? 'primary' : 'primary'));

        return new Promise(function (resolve) {
            pendingResolve = resolve;
            modal.show();
        });
    }

    function confirm(options) {
        return openDialog(options || {});
    }

    function alert(message, options) {
        var opts = options || {};
        return openDialog({
            alert: true,
            title: opts.title || 'Thông báo',
            message: message,
            confirmText: opts.okText || opts.confirmText || 'Đóng',
            variant: opts.variant || 'primary',
        });
    }

    confirmBtn.addEventListener('click', function () {
        finish(true);
        modal.hide();
    });

    cancelBtn.addEventListener('click', function () {
        finish(false);
    });

    modalEl.addEventListener('hidden.bs.modal', function () {
        finish(false);
    });

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }
        var message = form.getAttribute('data-confirm');
        if (!message || form.dataset.confirmBypass === '1') {
            return;
        }

        e.preventDefault();
        e.stopPropagation();

        confirm({
            title: form.getAttribute('data-confirm-title') || 'Xác nhận',
            message: message,
            confirmText: form.getAttribute('data-confirm-ok') || 'Xác nhận',
            cancelText: form.getAttribute('data-confirm-cancel') || 'Huỷ',
            variant: form.getAttribute('data-confirm-variant') || 'primary',
        }).then(function (ok) {
            if (!ok) {
                return;
            }
            form.dataset.confirmBypass = '1';
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        });
    }, true);

    window.AppDialog = {
        confirm: confirm,
        alert: alert,
    };
})();
