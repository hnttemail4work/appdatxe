/**
 * Scan QR CCCD (ảnh đã upload / file / camera) → fill form admin duyệt.
 * Parser khớp App\Support\CccdQrParser (pipe-separated).
 */
(function () {
    'use strict';

    var JSQR_SRC = 'https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js';
    var jsqrPromise = null;

    function loadJsQR() {
        if (typeof window.jsQR === 'function') {
            return Promise.resolve(window.jsQR);
        }
        if (!jsqrPromise) {
            jsqrPromise = new Promise(function (resolve, reject) {
                var s = document.createElement('script');
                s.src = JSQR_SRC;
                s.async = true;
                s.onload = function () {
                    if (typeof window.jsQR === 'function') {
                        resolve(window.jsQR);
                    } else {
                        reject(new Error('jsQR missing'));
                    }
                };
                s.onerror = function () {
                    reject(new Error('jsQR load failed'));
                };
                document.head.appendChild(s);
            });
        }
        return jsqrPromise;
    }

    function parseCccdQr(raw) {
        var text = String(raw || '').trim();
        if (!text) {
            return null;
        }
        if (text.indexOf('|') === -1) {
            var m = text.match(/\d{9,12}.+\|.+/);
            if (m) {
                text = m[0];
            }
        }
        var parts = text.split('|').map(function (p) {
            return String(p || '').trim();
        });
        if (parts.length < 5) {
            return null;
        }

        var idDigits = (parts[0] || '').replace(/\D+/g, '');
        var idNumber = idDigits.length >= 9 ? idDigits : '';
        var name = normalizeName(parts[2] || '');
        var dob = parseDob(parts[3] || '');
        var gender = parseGender(parts[4] || '');

        if (!name && !dob && !gender && !idNumber) {
            return null;
        }

        return {
            id_number: idNumber || null,
            name: name || null,
            date_of_birth: dob || null,
            gender: gender || null,
        };
    }

    function normalizeName(value) {
        value = String(value || '').replace(/\s+/g, ' ').trim();
        if (!value || /^\d+$/.test(value)) {
            return null;
        }
        if (value === value.toUpperCase()) {
            value = value.toLowerCase().replace(/(^|\s)\S/g, function (c) {
                return c.toUpperCase();
            });
        }
        return value;
    }

    function parseDob(value) {
        value = String(value || '').trim();
        if (!value) {
            return null;
        }
        var m = value.match(/^(\d{2})(\d{2})(\d{4})$/);
        if (m) {
            return m[3] + '-' + m[2] + '-' + m[1];
        }
        m = value.match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if (m) {
            return m[1] + '-' + m[2] + '-' + m[3];
        }
        m = value.match(/^(\d{1,2})[\/\-.](\d{1,2})[\/\-.](\d{4})$/);
        if (m) {
            return m[3] + '-' + pad2(m[2]) + '-' + pad2(m[1]);
        }
        return null;
    }

    function pad2(n) {
        n = String(n);
        return n.length === 1 ? '0' + n : n;
    }

    function parseGender(value) {
        value = String(value || '').toLowerCase().trim();
        if (!value) {
            return null;
        }
        if (value.indexOf('nữ') !== -1 || value.indexOf('nu') !== -1 || value === 'female' || value === 'f') {
            return 'female';
        }
        if (value.indexOf('nam') !== -1 || value === 'male' || value === 'm') {
            return 'male';
        }
        return null;
    }

    function setStatus(root, msg, isError) {
        var el = root.querySelector('[data-idscan-status]');
        if (!el) {
            return;
        }
        el.textContent = msg;
        el.classList.toggle('text-danger', !!isError);
        el.classList.toggle('text-muted', !isError);
    }

    function ageFromDob(ymd) {
        if (!ymd) {
            return null;
        }
        var p = ymd.split('-');
        if (p.length !== 3) {
            return null;
        }
        var birth = new Date(Number(p[0]), Number(p[1]) - 1, Number(p[2]));
        if (isNaN(birth.getTime())) {
            return null;
        }
        var now = new Date();
        var age = now.getFullYear() - birth.getFullYear();
        var md = now.getMonth() - birth.getMonth();
        if (md < 0 || (md === 0 && now.getDate() < birth.getDate())) {
            age -= 1;
        }
        return age > 0 && age < 130 ? age : null;
    }

    function syncAgeHint(root) {
        var dobEl = root.querySelector('[data-idscan-dob]');
        var ageEl = root.querySelector('[data-idscan-age]');
        if (!dobEl || !ageEl) {
            return;
        }
        var age = ageFromDob(dobEl.value);
        ageEl.textContent = age != null ? ('Tuổi: ' + age) : '';
    }

    function fillFields(root, data) {
        if (!data) {
            return false;
        }
        var nameEl = root.querySelector('[data-idscan-name]');
        var dobEl = root.querySelector('[data-idscan-dob]');
        var idEl = root.querySelector('[data-idscan-id]');
        if (data.name && nameEl) {
            nameEl.value = data.name;
        }
        if (data.date_of_birth && dobEl) {
            dobEl.value = data.date_of_birth;
        }
        if (data.id_number && idEl) {
            idEl.value = data.id_number;
        }
        if (data.gender) {
            var radio = root.querySelector('[data-idscan-gender][value="' + data.gender + '"]');
            if (radio) {
                radio.checked = true;
            }
        }
        syncAgeHint(root);
        return !!(data.name || data.date_of_birth || data.gender);
    }

    function decodeImageBitmap(jsQR, bitmap) {
        var canvas = document.createElement('canvas');
        canvas.width = bitmap.width;
        canvas.height = bitmap.height;
        var ctx = canvas.getContext('2d');
        ctx.drawImage(bitmap, 0, 0);
        var imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        var code = jsQR(imageData.data, imageData.width, imageData.height, {
            inversionAttempts: 'attemptBoth',
        });
        return code && code.data ? code.data : null;
    }

    function loadImage(url) {
        return new Promise(function (resolve, reject) {
            var img = new Image();
            img.crossOrigin = 'anonymous';
            img.onload = function () {
                resolve(img);
            };
            img.onerror = function () {
                reject(new Error('image load failed'));
            };
            img.src = url;
        });
    }

    function scanFromUrl(root, url) {
        setStatus(root, 'Đang scan QR…', false);
        return loadJsQR()
            .then(function (jsQR) {
                return loadImage(url).then(function (img) {
                    var toBitmap = (typeof createImageBitmap === 'function')
                        ? createImageBitmap(img)
                        : Promise.resolve(img);
                    return toBitmap.then(function (bitmap) {
                        try {
                            var raw = decodeImageBitmap(jsQR, bitmap);
                            if (!raw) {
                                // thử scale lớn hơn nếu ảnh nhỏ
                                var big = document.createElement('canvas');
                                var scale = Math.max(1, Math.ceil(800 / Math.max(img.width, img.height)));
                                big.width = img.width * scale;
                                big.height = img.height * scale;
                                var bctx = big.getContext('2d');
                                bctx.imageSmoothingEnabled = false;
                                bctx.drawImage(img, 0, 0, big.width, big.height);
                                var data = bctx.getImageData(0, 0, big.width, big.height);
                                var code = jsQR(data.data, data.width, data.height, {
                                    inversionAttempts: 'attemptBoth',
                                });
                                raw = code && code.data ? code.data : null;
                            }
                            var parsed = parseCccdQr(raw || '');
                            if (!parsed || !fillFields(root, parsed)) {
                                setStatus(root, 'Không đọc được QR — nhập tay họ tên / ngày sinh / giới tính.', true);
                                return false;
                            }
                            setStatus(root, 'Đã điền từ QR CCCD. Kiểm tra lại rồi bấm Duyệt.', false);
                            return true;
                        } finally {
                            if (bitmap && bitmap.close) {
                                bitmap.close();
                            }
                        }
                    });
                });
            })
            .catch(function () {
                setStatus(root, 'Scan lỗi — vui lòng nhập tay.', true);
                return false;
            });
    }

    function scanFromFile(root, file) {
        if (!file) {
            return;
        }
        var url = URL.createObjectURL(file);
        scanFromUrl(root, url).finally(function () {
            URL.revokeObjectURL(url);
        });
    }

    function bindRoot(root) {
        if (root.getAttribute('data-idscan-bound') === '1') {
            return;
        }
        root.setAttribute('data-idscan-bound', '1');

        var frontBtn = root.querySelector('[data-idscan-from-front]');
        if (frontBtn) {
            frontBtn.addEventListener('click', function () {
                var url = root.getAttribute('data-front-url') || '';
                if (!url) {
                    setStatus(root, 'Chưa có ảnh CCCD mặt trước.', true);
                    return;
                }
                scanFromUrl(root, url);
            });
        }

        var fileInput = root.querySelector('[data-idscan-file]');
        if (fileInput) {
            fileInput.addEventListener('change', function () {
                var file = fileInput.files && fileInput.files[0];
                scanFromFile(root, file);
                fileInput.value = '';
            });
        }

        var dobEl = root.querySelector('[data-idscan-dob]');
        if (dobEl) {
            dobEl.addEventListener('change', function () {
                syncAgeHint(root);
            });
            syncAgeHint(root);
        }
    }

    function init() {
        document.querySelectorAll('[data-admin-idscan]').forEach(bindRoot);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
