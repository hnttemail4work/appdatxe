/**
 * Màn hình Mời bạn bè — render mọi khối QR (giảm giá + hoa hồng nếu có).
 */
(function () {
    var nodes = Array.prototype.slice.call(document.querySelectorAll('.driver-invite-qr[data-invite-url]'));
    if (!nodes.length) {
        return;
    }

    var libLoading = false;
    var libReady = typeof QRCode !== 'undefined';
    var renderedMap = new WeakMap();

    function renderImgFallback(qrEl, url, alt) {
        if (!url) {
            return;
        }
        qrEl.innerHTML = '';
        var img = document.createElement('img');
        img.src = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&margin=10&data='
            + encodeURIComponent(url);
        img.width = 180;
        img.height = 180;
        img.alt = alt || 'Mã QR mời bạn bè';
        img.decoding = 'async';
        qrEl.appendChild(img);
        renderedMap.set(qrEl, true);
    }

    function loadLib(cb) {
        if (typeof QRCode !== 'undefined') {
            libReady = true;
            cb();
            return;
        }
        if (libLoading) {
            var t = setInterval(function () {
                if (typeof QRCode !== 'undefined') {
                    clearInterval(t);
                    libReady = true;
                    cb();
                }
            }, 50);
            setTimeout(function () {
                clearInterval(t);
                if (typeof QRCode === 'undefined') {
                    nodes.forEach(function (el) {
                        renderImgFallback(el, el.dataset.inviteUrl || '', el.getAttribute('aria-label'));
                    });
                }
            }, 4000);
            return;
        }
        libLoading = true;
        var s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js';
        s.onload = function () {
            libReady = true;
            cb();
        };
        s.onerror = function () {
            libLoading = false;
            nodes.forEach(function (el) {
                renderImgFallback(el, el.dataset.inviteUrl || '', el.getAttribute('aria-label'));
            });
        };
        document.head.appendChild(s);
    }

    function renderQr(qrEl) {
        var url = qrEl.dataset.inviteUrl || '';
        if (!url) {
            return;
        }
        if (typeof QRCode === 'undefined') {
            if (!qrEl.querySelector('img')) {
                renderImgFallback(qrEl, url, qrEl.getAttribute('aria-label'));
            }
            return;
        }
        try {
            qrEl.innerHTML = '';
            new QRCode(qrEl, {
                text: url,
                width: 180,
                height: 180,
                colorDark: '#0a0a0a',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.M,
            });
            renderedMap.set(qrEl, true);
        } catch (e) {
            renderImgFallback(qrEl, url, qrEl.getAttribute('aria-label'));
        }
    }

    function ensureAll() {
        nodes = Array.prototype.slice.call(document.querySelectorAll('.driver-invite-qr[data-invite-url]'));
        var needsLib = false;
        nodes.forEach(function (qrEl) {
            if (renderedMap.get(qrEl) && qrEl.querySelector('img, canvas, table')) {
                return;
            }
            needsLib = true;
        });
        if (!needsLib) {
            return;
        }
        loadLib(function () {
            nodes.forEach(function (qrEl) {
                if (renderedMap.get(qrEl) && qrEl.querySelector('img, canvas, table')) {
                    return;
                }
                renderQr(qrEl);
            });
        });
    }

    document.addEventListener('drivertab:changed', function (event) {
        if (event.detail && event.detail.tab === 'invite') {
            nodes.forEach(function (el) {
                renderedMap.set(el, false);
            });
            ensureAll();
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', ensureAll);
    } else {
        ensureAll();
    }
})();
