(function () {
    var libLoading = false;
    var modalInstance = null;

    function loadLib(cb) {
        if (typeof QRCode !== 'undefined') {
            cb();
            return;
        }
        if (libLoading) {
            var timer = setInterval(function () {
                if (typeof QRCode !== 'undefined') {
                    clearInterval(timer);
                    cb();
                }
            }, 50);
            return;
        }
        libLoading = true;
        var script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js';
        script.onload = cb;
        document.head.appendChild(script);
    }

    function getModal() {
        var el = document.getElementById('booking-qr-modal');
        if (!el || typeof bootstrap === 'undefined') {
            return null;
        }
        if (!modalInstance) {
            modalInstance = new bootstrap.Modal(el);
        }
        return modalInstance;
    }

    function renderThumb(el) {
        var url = el.dataset.url;
        if (!url || el.dataset.rendered === '1') {
            return;
        }
        el.dataset.rendered = '1';
        el.innerHTML = '';
        new QRCode(el, {
            text: url,
            width: 44,
            height: 44,
            colorDark: '#0f172a',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.M,
        });
    }

    function initThumbs() {
        document.querySelectorAll('[data-booking-page-qr]').forEach(function (el) {
            loadLib(function () {
                renderThumb(el);
            });
        });
    }

    function renderQr(url) {
        var canvas = document.getElementById('booking-qr-modal-canvas');
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

    function copyText(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text);
        }
        var input = document.getElementById('booking-qr-modal-url');
        if (input) {
            input.select();
            document.execCommand('copy');
        }
        return Promise.resolve();
    }

    function openModal(url) {
        var modal = getModal();
        if (!modal || !url) {
            return;
        }

        var urlInput = document.getElementById('booking-qr-modal-url');
        var openLink = document.getElementById('booking-qr-modal-open');
        if (urlInput) {
            urlInput.value = url;
        }
        if (openLink) {
            openLink.href = url;
        }

        loadLib(function () {
            renderQr(url);
            modal.show();
        });
    }

    function init() {
        initThumbs();

        document.querySelectorAll('[data-booking-qr-open]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                openModal(btn.dataset.url || '');
            });
        });

        var copyBtn = document.getElementById('booking-qr-modal-copy');
        var urlInput = document.getElementById('booking-qr-modal-url');
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
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
