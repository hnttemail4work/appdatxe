/**
 * QR mã giới thiệu — thumbnail + modal xem / sao chép / chia sẻ
 */
(function () {
    var libLoading = false;
    var modalInstance = null;
    var modalQr = null;

    function loadLib(cb) {
        if (typeof QRCode !== 'undefined') {
            cb();
            return;
        }
        if (libLoading) {
            var t = setInterval(function () {
                if (typeof QRCode !== 'undefined') {
                    clearInterval(t);
                    cb();
                }
            }, 50);
            return;
        }
        libLoading = true;
        var s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js';
        s.onload = cb;
        document.head.appendChild(s);
    }

    function renderThumb(el) {
        var url = el.dataset.url;
        if (!url || el.dataset.rendered === '1') return;
        el.dataset.rendered = '1';
        el.innerHTML = '';
        new QRCode(el, {
            text: url,
            width: 72,
            height: 72,
            colorDark: '#0f172a',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.M,
        });
    }

    function renderModalQr(url) {
        var canvas = document.getElementById('referral-qr-modal-canvas');
        if (!canvas) return;
        canvas.innerHTML = '';
        modalQr = new QRCode(canvas, {
            text: url,
            width: 220,
            height: 220,
            colorDark: '#0f172a',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.M,
        });
    }

    function getModal() {
        var el = document.getElementById('referral-qr-modal');
        if (!el || typeof bootstrap === 'undefined') return null;
        if (!modalInstance) {
            modalInstance = new bootstrap.Modal(el);
        }
        return modalInstance;
    }

    function copyText(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text);
        }
        var input = document.getElementById('referral-qr-modal-url');
        if (input) {
            input.select();
            document.execCommand('copy');
        }
        return Promise.resolve();
    }

    function openModal(url, code) {
        var modal = getModal();
        if (!modal) return;

        document.getElementById('referral-qr-modal-code').textContent = code || '—';
        document.getElementById('referral-qr-modal-url').value = url;
        document.getElementById('referral-qr-modal-title').textContent = code ? 'QR ' + code : 'Mã giới thiệu';

        loadLib(function () {
            renderModalQr(url);
            modal.show();
        });
    }

    function initThumbs() {
        document.querySelectorAll('[data-referral-qr]').forEach(function (el) {
            loadLib(function () {
                renderThumb(el);
            });
        });
    }

    function initOpeners() {
        document.querySelectorAll('[data-referral-qr-open]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openModal(btn.dataset.url || '', btn.dataset.code || '');
            });
        });
    }

    function initModalActions() {
        var copyBtn = document.getElementById('referral-qr-modal-copy');
        var shareBtn = document.getElementById('referral-qr-modal-share');
        var urlInput = document.getElementById('referral-qr-modal-url');

        if (copyBtn && urlInput) {
            copyBtn.addEventListener('click', function () {
                copyText(urlInput.value).then(function () {
                    copyBtn.textContent = 'Đã chép';
                    setTimeout(function () {
                        copyBtn.textContent = 'Sao chép';
                    }, 1500);
                });
            });
        }

        if (shareBtn && urlInput) {
            shareBtn.addEventListener('click', function () {
                var url = urlInput.value;
                var code = document.getElementById('referral-qr-modal-code').textContent;
                if (navigator.share) {
                    navigator.share({
                        title: 'Mã giới thiệu ' + code,
                        text: 'Đặt xe qua mã ' + code,
                        url: url,
                    }).catch(function () {});
                    return;
                }
                copyText(url).then(function () {
                    shareBtn.textContent = 'Đã sao chép link';
                    setTimeout(function () {
                        shareBtn.textContent = 'Chia sẻ link';
                    }, 1500);
                });
            });
        }
    }

    function init() {
        initThumbs();
        initOpeners();
        initModalActions();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
