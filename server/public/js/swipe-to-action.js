/**
 * Vuốt trái → phải để xác nhận thao tác (tài xế).
 * Form có [data-swipe-action] — giữ submit thường cho Nhận chuyến / Xác nhận.
 */
(function () {
    'use strict';

    function enhanceForm(form) {
        if (!form || form.getAttribute('data-swipe-bound') === '1') {
            return;
        }
        var btn = form.querySelector('button[type="submit"]');
        if (!btn) {
            return;
        }
        form.setAttribute('data-swipe-bound', '1');

        var label = (btn.textContent || '').trim() || 'Vuốt để xác nhận';
        var wrap = document.createElement('div');
        wrap.className = 'swipe-action';
        wrap.setAttribute('role', 'button');
        wrap.setAttribute('tabindex', '0');
        wrap.setAttribute('aria-label', label);

        wrap.innerHTML = ''
            + '<div class="swipe-action__track">'
            +   '<span class="swipe-action__hint">Vuốt để ' + escapeHtml(label) + '</span>'
            +   '<div class="swipe-action__fill"></div>'
            +   '<div class="swipe-action__thumb" aria-hidden="true">'
            +     '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4">'
            +       '<path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/>'
            +     '</svg>'
            +   '</div>'
            + '</div>';

        btn.classList.add('d-none');
        btn.setAttribute('tabindex', '-1');
        form.insertBefore(wrap, btn);

        var track = wrap.querySelector('.swipe-action__track');
        var thumb = wrap.querySelector('.swipe-action__thumb');
        var fill = wrap.querySelector('.swipe-action__fill');
        var dragging = false;
        var startX = 0;
        var maxX = 0;
        var currentX = 0;
        var done = false;

        function measure() {
            maxX = Math.max(0, track.clientWidth - thumb.clientWidth - 8);
        }

        function setX(x) {
            currentX = Math.max(0, Math.min(maxX, x));
            thumb.style.transform = 'translateX(' + currentX + 'px)';
            fill.style.width = (currentX + thumb.clientWidth / 2) + 'px';
        }

        function reset() {
            done = false;
            wrap.classList.remove('is-complete');
            setX(0);
        }

        function complete() {
            if (done) {
                return;
            }
            done = true;
            wrap.classList.add('is-complete');
            setX(maxX);
            window.setTimeout(function () {
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit(btn);
                } else {
                    btn.click();
                }
            }, 120);
        }

        function onStart(clientX) {
            if (done || form.getAttribute('data-submitting') === '1') {
                return;
            }
            dragging = true;
            measure();
            startX = clientX - currentX;
            wrap.classList.add('is-dragging');
        }

        function onMove(clientX) {
            if (!dragging) {
                return;
            }
            setX(clientX - startX);
        }

        function onEnd() {
            if (!dragging) {
                return;
            }
            dragging = false;
            wrap.classList.remove('is-dragging');
            if (currentX >= maxX * 0.85) {
                complete();
            } else {
                reset();
            }
        }

        thumb.addEventListener('pointerdown', function (e) {
            e.preventDefault();
            thumb.setPointerCapture(e.pointerId);
            onStart(e.clientX);
        });
        thumb.addEventListener('pointermove', function (e) {
            onMove(e.clientX);
        });
        thumb.addEventListener('pointerup', onEnd);
        thumb.addEventListener('pointercancel', onEnd);

        wrap.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                measure();
                complete();
            }
        });

        window.addEventListener('resize', function () {
            if (!done) {
                measure();
                setX(0);
            }
        });
    }

    function escapeHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function init(root) {
        (root || document).querySelectorAll('form[data-swipe-action]').forEach(enhanceForm);
    }

    window.SwipeToAction = { init: init, enhanceForm: enhanceForm };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { init(); });
    } else {
        init();
    }
})();
