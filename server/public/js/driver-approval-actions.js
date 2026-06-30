(function () {
    document.querySelectorAll('[data-driver-reject-toggle]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var targetId = btn.getAttribute('data-target');
            var form = targetId ? document.getElementById(targetId) : null;
            if (!form) return;

            var open = form.classList.contains('d-none');
            document.querySelectorAll('[data-driver-reject-form]').forEach(function (other) {
                if (other !== form) {
                    other.classList.add('d-none');
                }
            });
            form.classList.toggle('d-none', !open);
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');

            if (open) {
                var textarea = form.querySelector('textarea[name="rejection_reason"]');
                if (textarea) {
                    textarea.focus();
                }
            }
        });
    });

    document.querySelectorAll('[data-driver-reject-cancel]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var targetId = btn.getAttribute('data-target');
            var form = targetId ? document.getElementById(targetId) : btn.closest('[data-driver-reject-form]');
            if (form) {
                form.classList.add('d-none');
            }
        });
    });

    document.querySelectorAll('[data-driver-reject-form] textarea.is-invalid').forEach(function (textarea) {
        var form = textarea.closest('[data-driver-reject-form]');
        if (form) {
            form.classList.remove('d-none');
        }
    });
})();
