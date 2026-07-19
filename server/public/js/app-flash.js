/**
 * Thông báo inline — thay cho alert() của trình duyệt.
 */
(function () {
    var DEFAULT_STACK = '#app-flash-stack';

    var variantClass = {
        warning: 'app-flash-banner--warning',
        danger: 'app-flash-banner--danger',
        success: 'app-flash-banner--success',
        info: 'app-flash-banner--info',
    };

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = String(text || '');
        return div.innerHTML;
    }

    function resolveTarget(target) {
        if (!target) {
            return document.querySelector(DEFAULT_STACK);
        }
        if (typeof target === 'string') {
            return document.querySelector(target);
        }
        return target;
    }

    function dismissFlash(el) {
        if (!el || el.classList.contains('is-hiding')) {
            return;
        }
        el.classList.add('is-hiding');
        window.setTimeout(function () {
            if (el.parentNode) {
                el.parentNode.removeChild(el);
            }
        }, 260);
    }

    function bindFlash(el, autoDismiss) {
        var hideTimer = null;
        var closeBtn = el.querySelector('[data-flash-close]');

        if (closeBtn) {
            closeBtn.addEventListener('click', function () {
                if (hideTimer) {
                    window.clearTimeout(hideTimer);
                }
                dismissFlash(el);
            });
        }

        if (autoDismiss > 0) {
            hideTimer = window.setTimeout(function () {
                dismissFlash(el);
            }, autoDismiss);
        }
    }

    function clearTarget(target) {
        if (!target) {
            return;
        }
        target.querySelectorAll('.app-flash-banner').forEach(function (el) {
            dismissFlash(el);
        });
    }

    function show(message, options) {
        options = options || {};
        var target = resolveTarget(options.target);
        if (!target) {
            return null;
        }

        if (options.clear !== false) {
            clearTarget(target);
        }

        var variant = options.variant || 'warning';
        var icon = variant === 'success' ? '✓' : '!';
        var el = document.createElement('div');
        el.className = 'app-flash-banner app-flash ' + (variantClass[variant] || variantClass.warning);
        el.setAttribute('role', 'alert');

        var bodyHtml = '';
        if (options.title) {
            bodyHtml += '<strong class="app-flash-banner-title">' + escapeHtml(options.title) + '</strong>';
        }
        bodyHtml += '<p class="app-flash-banner-text mb-0">' + escapeHtml(message) + '</p>';

        el.innerHTML =
            '<div class="app-flash-banner-icon" aria-hidden="true">' + icon + '</div>'
            + '<div class="app-flash-banner-body">' + bodyHtml + '</div>'
            + '<button type="button" class="app-flash-close" data-flash-close aria-label="Đóng thông báo" title="Đóng">×</button>';

        target.appendChild(el);
        target.classList.remove('d-none');

        var autoDismiss = options.autoDismiss;
        if (autoDismiss == null) {
            autoDismiss = 8000;
        }
        bindFlash(el, autoDismiss);

        if (options.scroll !== false) {
            window.requestAnimationFrame(function () {
                el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            });
        }

        return el;
    }

    window.AppFlash = {
        show: show,
        dismiss: dismissFlash,
        clear: clearTarget,
    };
})();
