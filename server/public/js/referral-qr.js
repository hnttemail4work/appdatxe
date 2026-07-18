/**
 * QR mã giới thiệu — thumbnail + modal xem / sao chép / chia sẻ
 */
(function () {
    var libLoading = false;
    var modalInstance = null;

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
        s.onerror = function () {
            libLoading = false;
        };
        document.head.appendChild(s);
    }

    function renderThumb(el, force) {
        var url = el.dataset.url;
        if (!url) {
            return;
        }

        var hasImage = el.querySelector('img, canvas');
        if (!force && el.dataset.rendered === '1' && hasImage) {
            return;
        }

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
        if (!canvas) {
            return;
        }
        canvas.innerHTML = '';
        new QRCode(canvas, {
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
        if (!el || typeof bootstrap === 'undefined') {
            return null;
        }
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
        if (!modal) {
            return;
        }

        document.getElementById('referral-qr-modal-code').textContent = code || '—';
        document.getElementById('referral-qr-modal-url').value = url;
        document.getElementById('referral-qr-modal-title').textContent = code ? 'QR ' + code : 'Giới thiệu';

        loadLib(function () {
            renderModalQr(url);
            modal.show();
        });
    }

    function initThumbs(root, force) {
        var scope = root || document;
        scope.querySelectorAll('[data-referral-qr]').forEach(function (el) {
            loadLib(function () {
                renderThumb(el, !!force);
            });
        });
    }

    function initOpeners(root) {
        var scope = root || document;
        scope.querySelectorAll('[data-referral-qr-open]').forEach(function (btn) {
            if (btn.dataset.referralQrBound === '1') {
                return;
            }
            btn.dataset.referralQrBound = '1';
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
                        title: 'Giới thiệu ' + code,
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

    function bindTabRefresh() {
        document.addEventListener('shown.bs.tab', function (event) {
            var target = event.target;
            if (!target || !target.getAttribute) {
                return;
            }
            var paneSelector = target.getAttribute('data-bs-target');
            if (!paneSelector) {
                return;
            }
            var pane = document.querySelector(paneSelector);
            if (!pane) {
                return;
            }
            initThumbs(pane, true);
            initOpeners(pane);
        });
    }

    function init() {
        initThumbs();
        initOpeners();
        initModalActions();
        bindTabRefresh();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
