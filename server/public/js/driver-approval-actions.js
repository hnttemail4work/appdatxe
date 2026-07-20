(function () {
    function setRejectOpen(panel, open) {
        if (!panel) return;
        panel.classList.toggle('d-none', !open);

        var form = panel.closest('[data-admin-pending-form]') || panel.closest('.admin-approve-form') || panel.parentElement;
        if (!form) return;

        form.querySelectorAll('[data-driver-reject-toggle]').forEach(function (btn) {
            btn.classList.toggle('d-none', open);
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        form.querySelectorAll('[data-reject-submit], [data-driver-reject-cancel]').forEach(function (btn) {
            btn.classList.toggle('d-none', !open);
        });
        form.querySelectorAll('[data-approve-submit]').forEach(function (btn) {
            btn.classList.toggle('d-none', open);
        });

        if (open) {
            var textarea = panel.querySelector('textarea[name="rejection_reason"]');
            if (textarea) {
                textarea.focus();
            }
        }
    }

    document.querySelectorAll('[data-driver-reject-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var targetId = btn.getAttribute('data-target');
            var panel = targetId ? document.getElementById(targetId) : null;
            if (!panel) return;

            document.querySelectorAll('[data-driver-reject-form]').forEach(function (other) {
                if (other !== panel) {
                    setRejectOpen(other, false);
                }
            });

            setRejectOpen(panel, panel.classList.contains('d-none'));
        });
    });

    document.querySelectorAll('[data-driver-reject-cancel]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var targetId = btn.getAttribute('data-target');
            var panel = targetId
                ? document.getElementById(targetId)
                : btn.closest('[data-driver-reject-form]');
            setRejectOpen(panel, false);
        });
    });

    document.querySelectorAll('[data-driver-reject-form] textarea.is-invalid, [data-driver-reject-form]:not(.d-none)').forEach(function (el) {
        var panel = el.matches('[data-driver-reject-form]') ? el : el.closest('[data-driver-reject-form]');
        if (panel && (el.classList.contains('is-invalid') || !panel.classList.contains('d-none'))) {
            setRejectOpen(panel, true);
        }
    });

    // Radio lý do từ chối → đồng bộ vào textarea rejection_reason.
    document.querySelectorAll('[data-reject-preset]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            if (!radio.checked) {
                return;
            }
            var panel = radio.closest('[data-driver-reject-form]');
            var textarea = panel ? panel.querySelector('[data-reject-reason-text]') : null;
            if (!textarea) {
                return;
            }
            var value = radio.value || '';
            if (value === 'Khác') {
                if (!textarea.value || textarea.value === textarea.dataset.lastPreset) {
                    textarea.value = '';
                }
                textarea.focus();
            } else {
                textarea.value = value;
                textarea.dataset.lastPreset = value;
            }
        });
    });
})();
