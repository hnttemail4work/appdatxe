/**
 * Popup QR mã giới thiệu sau khi khách đặt chuyến thành công.
 */
(function () {
    var payload = window.__bookingReferralSuccess;
    if (!payload || !payload.code) {
        return;
    }

    if (!payload.url) {
        payload.url = window.location.origin + '/?ref=' + encodeURIComponent(payload.code);
    }

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
        document.head.appendChild(s);
    }

    function getModal() {
        var el = document.getElementById('booking-referral-success-modal');
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
        var input = document.getElementById('booking-referral-success-url');
        if (input) {
            input.select();
            document.execCommand('copy');
        }
        return Promise.resolve();
    }

    function renderQr(url) {
        var canvas = document.getElementById('booking-referral-success-qr');
        if (!canvas) return;
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

    function downloadQr() {
        var wrap = document.getElementById('booking-referral-success-qr');
        if (!wrap) return;
        var img = wrap.querySelector('img');
        var canvas = wrap.querySelector('canvas');
        var dataUrl = '';
        if (img && img.src) {
            dataUrl = img.src;
        } else if (canvas && canvas.toDataURL) {
            dataUrl = canvas.toDataURL('image/png');
        }
        if (!dataUrl) return;
        var link = document.createElement('a');
        link.download = 'ma-gioi-thieu-' + (payload.code || 'qr') + '.png';
        link.href = dataUrl;
        link.click();
    }

    function showModal() {
        loadLib(function () {
            renderQr(payload.url);
            var modal = getModal();
            if (modal) {
                modal.show();
            }
        });
    }

    function init() {
        document.getElementById('booking-referral-success-code').textContent = payload.code;
        document.getElementById('booking-referral-success-url').value = payload.url;
        if (payload.discount_percent) {
            document.getElementById('booking-referral-success-percent').textContent = String(payload.discount_percent);
        }

        var copyBtn = document.getElementById('booking-referral-success-copy');
        var shareBtn = document.getElementById('booking-referral-success-share');
        var downloadBtn = document.getElementById('booking-referral-success-download');
        var urlInput = document.getElementById('booking-referral-success-url');

        if (copyBtn && urlInput) {
            copyBtn.addEventListener('click', function () {
                copyText(urlInput.value).then(function () {
                    copyBtn.textContent = 'Đã chép';
                    setTimeout(function () { copyBtn.textContent = 'Sao chép'; }, 1500);
                });
            });
        }

        if (shareBtn && urlInput) {
            shareBtn.addEventListener('click', function () {
                if (navigator.share) {
                    navigator.share({
                        title: 'Mã giới thiệu ' + payload.code,
                        text: 'Đặt xe giảm giá qua mã ' + payload.code,
                        url: urlInput.value,
                    }).catch(function () {});
                    return;
                }
                copyText(urlInput.value).then(function () {
                    shareBtn.textContent = 'Đã sao chép link';
                    setTimeout(function () { shareBtn.textContent = 'Chia sẻ link'; }, 1500);
                });
            });
        }

        if (downloadBtn) {
            downloadBtn.addEventListener('click', downloadQr);
        }

        var reopenBtn = document.getElementById('booking-show-referral-qr');
        if (reopenBtn) {
            reopenBtn.addEventListener('click', showModal);
        }

        showModal();
    }

    window.BookingReferralSuccess = {
        show: showModal,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
