/**
 * QR code share for guest booking links
 */
(function () {
    var loaded = false;
    var queue = [];

    function loadQrLib(cb) {
        if (typeof QRCode !== 'undefined') {
            cb();
            return;
        }
        if (loaded) {
            queue.push(cb);
            return;
        }
        loaded = true;
        var s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js';
        s.onload = function () {
            queue.forEach(function (fn) { fn(); });
            queue = [];
            cb();
        };
        s.onerror = function () {
            loaded = false;
        };
        document.head.appendChild(s);
    }

    function renderQr(el) {
        var url = el.getAttribute('data-url');
        if (!url || el.dataset.qrRendered === '1') return;
        el.dataset.qrRendered = '1';
        el.innerHTML = '';
        new QRCode(el, {
            text: url,
            width: 180,
            height: 180,
            colorDark: '#1e40af',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.M,
        });
    }

    function mountShareModals() {
        document.querySelectorAll('.share-qr-modal').forEach(function (modal) {
            if (modal.parentElement !== document.body) {
                document.body.appendChild(modal);
            }
        });
    }

    function copyShareUrl(btn) {
        var input = btn.closest('.input-group')?.querySelector('.share-url-input');
        if (!input || !input.value) return;

        function showCopied() {
            var old = btn.textContent;
            btn.textContent = 'Đã copy!';
            btn.disabled = true;
            setTimeout(function () {
                btn.textContent = old;
                btn.disabled = false;
            }, 1500);
        }

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(input.value).then(showCopied).catch(function () {
                input.focus();
                input.select();
                try {
                    document.execCommand('copy');
                    showCopied();
                } catch (e) {}
            });
            return;
        }

        input.focus();
        input.select();
        try {
            document.execCommand('copy');
            showCopied();
        } catch (e) {}
    }

    function initShareQr() {
        mountShareModals();

        document.querySelectorAll('[data-share-qr]').forEach(function (el) {
            var modal = el.closest('.modal');
            if (!modal) return;
            modal.addEventListener('show.bs.modal', function () {
                loadQrLib(function () { renderQr(el); });
            });
        });

        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.share-copy-btn');
            if (!btn) return;
            e.preventDefault();
            copyShareUrl(btn);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initShareQr);
    } else {
        initShareQr();
    }
})();
