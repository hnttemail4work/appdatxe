/**
 * Panel duyệt CCCD: xoay / cắt ảnh + form nhập tay (không scan).
 */
(function () {
    'use strict';

    function setStatus(root, msg, isError) {
        var el = root && root.querySelector('[data-idscan-status]');
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
        var label = age != null ? (age + ' tuổi') : '—';
        if ('value' in ageEl) {
            ageEl.value = label;
        } else {
            ageEl.textContent = label;
        }
    }

    function loadImage(url) {
        return new Promise(function (resolve, reject) {
            var img = new Image();
            // Cùng origin — không ép CORS (tránh canvas tainted).
            if (/^https?:\/\//i.test(url) && url.indexOf(window.location.origin) !== 0) {
                img.crossOrigin = 'anonymous';
            }
            img.onload = function () {
                resolve(img);
            };
            img.onerror = function () {
                reject(new Error('image load failed'));
            };
            img.src = url;
        });
    }

    function waitImgReady(imgEl) {
        if (imgEl && imgEl.complete && imgEl.naturalWidth > 0) {
            return Promise.resolve(imgEl);
        }
        return new Promise(function (resolve, reject) {
            if (!imgEl) {
                reject(new Error('no img'));
                return;
            }
            var done = false;
            function ok() {
                if (done) return;
                done = true;
                resolve(imgEl);
            }
            function fail() {
                if (done) return;
                done = true;
                reject(new Error('img load'));
            }
            imgEl.addEventListener('load', ok, { once: true });
            imgEl.addEventListener('error', fail, { once: true });
            if (imgEl.complete && imgEl.naturalWidth > 0) {
                ok();
            }
        });
    }

    function canvasToBlob(canvas, quality) {
        quality = quality == null ? 0.92 : quality;
        return new Promise(function (resolve, reject) {
            if (!canvas.toBlob) {
                try {
                    var dataUrl = canvas.toDataURL('image/jpeg', quality);
                    var bin = atob(dataUrl.split(',')[1] || '');
                    var arr = new Uint8Array(bin.length);
                    for (var i = 0; i < bin.length; i++) {
                        arr[i] = bin.charCodeAt(i);
                    }
                    resolve(new Blob([arr], { type: 'image/jpeg' }));
                } catch (err) {
                    reject(err);
                }
                return;
            }
            canvas.toBlob(function (blob) {
                if (!blob) {
                    reject(new Error('toBlob failed'));
                    return;
                }
                resolve(blob);
            }, 'image/jpeg', quality);
        });
    }

    function rotateCanvas90(source, clockwise) {
        var canvas = document.createElement('canvas');
        canvas.width = source.height;
        canvas.height = source.width;
        var ctx = canvas.getContext('2d');
        ctx.translate(canvas.width / 2, canvas.height / 2);
        ctx.rotate((clockwise ? 90 : -90) * Math.PI / 180);
        ctx.drawImage(source, -source.width / 2, -source.height / 2);
        return canvas;
    }

    function cropCanvasNorm(source, crop) {
        var x = Math.max(0, Math.min(0.98, crop.x));
        var y = Math.max(0, Math.min(0.98, crop.y));
        var w = Math.max(0.05, Math.min(1 - x, crop.w));
        var h = Math.max(0.05, Math.min(1 - y, crop.h));
        var sx = Math.round(source.width * x);
        var sy = Math.round(source.height * y);
        var sw = Math.max(20, Math.round(source.width * w));
        var sh = Math.max(20, Math.round(source.height * h));
        if (sx + sw > source.width) {
            sw = source.width - sx;
        }
        if (sy + sh > source.height) {
            sh = source.height - sy;
        }
        var out = document.createElement('canvas');
        out.width = sw;
        out.height = sh;
        out.getContext('2d').drawImage(source, sx, sy, sw, sh, 0, 0, sw, sh);
        return out;
    }

    function workSrc(shot) {
        return shot.getAttribute('data-work-url') || shot.getAttribute('data-src') || '';
    }

    function revokeWork(shot) {
        var prev = shot.getAttribute('data-work-url') || '';
        if (prev.indexOf('blob:') === 0) {
            URL.revokeObjectURL(prev);
        }
        shot.removeAttribute('data-work-url');
        try {
            delete shot._workBlob;
        } catch (err) {
            shot._workBlob = null;
        }
    }

    function showOnShot(shot, url) {
        var imgEl = shot.querySelector('[data-cccd-img]');
        var link = shot.querySelector('a.admin-pending-review__img-link');
        if (imgEl) {
            imgEl.src = url;
            imgEl.style.transform = '';
        }
        if (link) {
            link.href = url;
        }
    }

    function setWorkFromCanvas(shot, canvas) {
        return canvasToBlob(canvas, 0.93).then(function (blob) {
            var url = URL.createObjectURL(blob);
            revokeWork(shot);
            shot._workBlob = blob;
            shot.setAttribute('data-work-url', url);
            shot.setAttribute('data-dirty', '1');
            showOnShot(shot, url);
            return url;
        });
    }

    /** Vùng ảnh thật trong stage (object-fit: contain) — logic Cắt QR cũ. */
    function containedImageRect(shot) {
        var stage = shot.querySelector('.admin-pending-review__stage');
        var img = shot.querySelector('[data-cccd-img]');
        if (!stage || !img) {
            return null;
        }
        var cw = stage.clientWidth || 0;
        var ch = stage.clientHeight || 0;
        var nw = img.naturalWidth || 0;
        var nh = img.naturalHeight || 0;
        if (cw < 2 || ch < 2 || nw < 2 || nh < 2) {
            return null;
        }
        var scale = Math.min(cw / nw, ch / nh);
        var w = nw * scale;
        var h = nh * scale;
        return {
            x: (cw - w) / 2,
            y: (ch - h) / 2,
            w: w,
            h: h,
            stageW: cw,
            stageH: ch,
        };
    }

    function stageCropToImageCrop(shot, stageCrop) {
        var rect = containedImageRect(shot);
        if (!rect || !stageCrop) {
            return null;
        }
        var bx1 = stageCrop.x * rect.stageW;
        var by1 = stageCrop.y * rect.stageH;
        var bx2 = bx1 + stageCrop.w * rect.stageW;
        var by2 = by1 + stageCrop.h * rect.stageH;

        var ix1 = Math.max(bx1, rect.x);
        var iy1 = Math.max(by1, rect.y);
        var ix2 = Math.min(bx2, rect.x + rect.w);
        var iy2 = Math.min(by2, rect.y + rect.h);

        var w = ix2 - ix1;
        var h = iy2 - iy1;
        if (w < 8 || h < 8) {
            return null;
        }

        return {
            x: (ix1 - rect.x) / rect.w,
            y: (iy1 - rect.y) / rect.h,
            w: w / rect.w,
            h: h / rect.h,
        };
    }

    /** Khung mặc định góc dưới-phải (như Cắt QR cũ) — dễ kéo ôm thẻ/QR. */
    function defaultStageCrop() {
        return { x: 0.55, y: 0.55, w: 0.4, h: 0.4 };
    }

    function withStageBusy(shot, promise) {
        var link = shot.querySelector('.admin-pending-review__img-link');
        if (link) {
            link.classList.add('is-rotating');
        }
        return Promise.resolve(promise).finally(function () {
            if (link) {
                link.classList.remove('is-rotating');
            }
        });
    }

    function rotateShot(shot, clockwise) {
        var src = workSrc(shot);
        if (!src) {
            return Promise.resolve();
        }
        return withStageBusy(shot, loadImage(src).then(function (img) {
            return setWorkFromCanvas(shot, rotateCanvas90(img, clockwise));
        }));
    }

    function readCropFromBox(shot) {
        var box = shot.querySelector('[data-cccd-crop-box]');
        if (!box) {
            return null;
        }
        return {
            x: (parseFloat(box.style.left) || 0) / 100,
            y: (parseFloat(box.style.top) || 0) / 100,
            w: (parseFloat(box.style.width) || 0) / 100,
            h: (parseFloat(box.style.height) || 0) / 100,
        };
    }

    function applyCropBoxPercent(shot, crop) {
        var box = shot.querySelector('[data-cccd-crop-box]');
        if (!box || !crop) {
            return;
        }
        box.style.left = (crop.x * 100) + '%';
        box.style.top = (crop.y * 100) + '%';
        box.style.width = (crop.w * 100) + '%';
        box.style.height = (crop.h * 100) + '%';
    }

    function writeCropNorm(shot, crop) {
        if (!crop) {
            shot.removeAttribute('data-crop');
            return;
        }
        shot.setAttribute(
            'data-crop',
            [crop.x, crop.y, crop.w, crop.h].map(function (n) {
                return Math.round(n * 1000) / 1000;
            }).join(',')
        );
    }

    function syncCropClearBtn(shot) {
        var clearBtn = shot.querySelector('[data-cccd-crop-clear]');
        if (!clearBtn) {
            return;
        }
        var hasCrop = shot.getAttribute('data-dirty') === '1' || !!shot.getAttribute('data-crop');
        clearBtn.classList.toggle('d-none', !hasCrop && !shot.classList.contains('is-cropping'));
    }

    function setCropMode(shot, on) {
        var layer = shot.querySelector('[data-cccd-crop-layer]');
        var toggle = shot.querySelector('[data-cccd-crop-toggle]');
        if (!layer) {
            return;
        }
        layer.classList.toggle('d-none', !on);
        shot.classList.toggle('is-cropping', !!on);
        if (toggle) {
            toggle.classList.toggle('is-active', !!on);
            toggle.textContent = on ? 'Xong cắt' : 'Cắt';
        }
        if (on) {
            var saved = shot.getAttribute('data-crop');
            var crop = defaultStageCrop();
            if (saved) {
                var parts = saved.split(',').map(Number);
                if (parts.length === 4 && parts.every(isFinite)) {
                    crop = { x: parts[0], y: parts[1], w: parts[2], h: parts[3] };
                }
            }
            applyCropBoxPercent(shot, crop);
            writeCropNorm(shot, crop);
        }
        syncCropClearBtn(shot);
    }

    function resetShot(shot) {
        revokeWork(shot);
        shot.removeAttribute('data-dirty');
        writeCropNorm(shot, null);
        var origin = shot.getAttribute('data-src') || '';
        if (origin) {
            showOnShot(shot, origin);
        }
        setCropMode(shot, false);
        applyCropBoxPercent(shot, defaultStageCrop());
        syncCropClearBtn(shot);
    }

    /** Áp cắt lên đúng mặt đang chỉnh (logic Cắt QR cũ). */
    function applyCrop(shot) {
        var imgEl = shot.querySelector('[data-cccd-img]');
        var stageCrop = readCropFromBox(shot);
        if (!stageCrop || stageCrop.w < 0.04 || stageCrop.h < 0.04) {
            setCropMode(shot, false);
            return Promise.resolve(false);
        }

        writeCropNorm(shot, stageCrop);

        return waitImgReady(imgEl)
            .then(function () {
                var imageCrop = stageCropToImageCrop(shot, stageCrop);
                if (!imageCrop) {
                    throw new Error('crop map failed');
                }
                var src = workSrc(shot);
                if (!src) {
                    throw new Error('no src');
                }
                return withStageBusy(shot, loadImage(src).then(function (img) {
                    return setWorkFromCanvas(shot, cropCanvasNorm(img, imageCrop));
                }));
            })
            .then(function () {
                writeCropNorm(shot, null);
                setCropMode(shot, false);
                syncCropClearBtn(shot);
                return true;
            })
            .catch(function () {
                setCropMode(shot, false);
                var review = shot.closest('.admin-pending-review');
                var idscan = review && review.querySelector('[data-admin-idscan]');
                setStatus(idscan || review, 'Không cắt được — thử xoay ảnh rồi cắt lại.', true);
                return false;
            });
    }

    function bindCropLayer(shot) {
        var layer = shot.querySelector('[data-cccd-crop-layer]');
        var box = shot.querySelector('[data-cccd-crop-box]');
        if (!layer || !box || layer.getAttribute('data-bound') === '1') {
            return;
        }
        layer.setAttribute('data-bound', '1');
        var drag = null;

        function pctFromEvent(ev) {
            var rect = layer.getBoundingClientRect();
            return {
                x: Math.max(0, Math.min(1, (ev.clientX - rect.left) / Math.max(1, rect.width))),
                y: Math.max(0, Math.min(1, (ev.clientY - rect.top) / Math.max(1, rect.height))),
            };
        }

        layer.addEventListener('pointerdown', function (ev) {
            if (layer.classList.contains('d-none')) {
                return;
            }
            ev.preventDefault();
            layer.setPointerCapture(ev.pointerId);
            var p = pctFromEvent(ev);
            var onBox = ev.target === box || box.contains(ev.target);
            if (onBox) {
                var cur = readCropFromBox(shot) || defaultStageCrop();
                drag = { mode: 'move', ox: p.x - cur.x, oy: p.y - cur.y, w: cur.w, h: cur.h };
            } else {
                drag = { mode: 'draw', x0: p.x, y0: p.y };
            }
        });

        layer.addEventListener('pointermove', function (ev) {
            if (!drag) {
                return;
            }
            var p = pctFromEvent(ev);
            var crop;
            if (drag.mode === 'move') {
                crop = {
                    x: Math.max(0, Math.min(1 - drag.w, p.x - drag.ox)),
                    y: Math.max(0, Math.min(1 - drag.h, p.y - drag.oy)),
                    w: drag.w,
                    h: drag.h,
                };
            } else {
                var x1 = Math.min(drag.x0, p.x);
                var y1 = Math.min(drag.y0, p.y);
                var x2 = Math.max(drag.x0, p.x);
                var y2 = Math.max(drag.y0, p.y);
                crop = {
                    x: x1,
                    y: y1,
                    w: Math.max(0.1, x2 - x1),
                    h: Math.max(0.1, y2 - y1),
                };
                if (crop.x + crop.w > 1) {
                    crop.w = 1 - crop.x;
                }
                if (crop.y + crop.h > 1) {
                    crop.h = 1 - crop.y;
                }
            }
            applyCropBoxPercent(shot, crop);
            writeCropNorm(shot, crop);
        });

        function endDrag() {
            drag = null;
        }
        layer.addEventListener('pointerup', endDrag);
        layer.addEventListener('pointercancel', endDrag);
    }

    function assignFileInput(input, blob, filename) {
        if (!input || !blob) {
            return;
        }
        try {
            var file = new File([blob], filename, { type: blob.type || 'image/jpeg' });
            var dt = new DataTransfer();
            dt.items.add(file);
            input.files = dt.files;
        } catch (err) {
            // ignore
        }
    }

    function blobFromUrl(url) {
        return fetch(url).then(function (r) {
            return r.blob();
        });
    }

    function attachAdjustedIdCardFiles(form) {
        var review = form.closest('.admin-pending-review');
        if (!review) {
            return Promise.resolve();
        }
        var jobs = [];
        review.querySelectorAll('[data-cccd-preview][data-dirty="1"]').forEach(function (shot) {
            var side = shot.getAttribute('data-cccd-preview') || '';
            var field = shot.getAttribute('data-photo-field') || '';
            var input = form.querySelector('[data-idcard-file="' + side + '"]');
            if (!input || !field) {
                return;
            }
            var filename = field.replace(/_/g, '-') + '.jpg';
            var cached = shot._workBlob;
            if (cached) {
                assignFileInput(input, cached, filename);
                return;
            }
            var url = workSrc(shot);
            if (!url) {
                return;
            }
            jobs.push(blobFromUrl(url).then(function (blob) {
                shot._workBlob = blob;
                assignFileInput(input, blob, filename);
            }));
        });
        return Promise.all(jobs);
    }

    function needsPhotoAttach(review) {
        if (!review) {
            return false;
        }
        return !!review.querySelector(
            '[data-cccd-preview][data-dirty="1"], [data-cccd-preview].is-cropping'
        );
    }

    function bindApprovePhotoSubmit(form) {
        if (!form || form.getAttribute('data-idcard-submit-bound') === '1') {
            return;
        }
        form.setAttribute('data-idcard-submit-bound', '1');

        form.addEventListener('submit', function (e) {
            var submitter = e.submitter;
            if (submitter && submitter.hasAttribute('data-reject-submit')) {
                return;
            }
            if (form.getAttribute('data-idcard-ready') === '1') {
                form.removeAttribute('data-idcard-ready');
                return;
            }

            var review = form.closest('.admin-pending-review');
            // Không xoay/cắt → duyệt thẳng, không bước “lưu ảnh”.
            if (!needsPhotoAttach(review)) {
                return;
            }

            e.preventDefault();
            e.stopPropagation();

            var approveBtn = form.querySelector('[data-approve-submit]');
            var idscan = form.querySelector('[data-admin-idscan]');
            if (approveBtn) {
                approveBtn.disabled = true;
            }
            setStatus(idscan || form, 'Đang duyệt…', false);

            var pending = Promise.resolve();
            review.querySelectorAll('[data-cccd-preview].is-cropping').forEach(function (shot) {
                pending = pending.then(function () {
                    return applyCrop(shot);
                });
            });

            pending
                .then(function () {
                    return attachAdjustedIdCardFiles(form);
                })
                .then(function () {
                    form.setAttribute('data-idcard-ready', '1');
                    if (approveBtn) {
                        approveBtn.disabled = false;
                    }
                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit(approveBtn || undefined);
                    } else {
                        form.submit();
                    }
                })
                .catch(function () {
                    if (approveBtn) {
                        approveBtn.disabled = false;
                    }
                    setStatus(
                        idscan || form,
                        'Không gửi được ảnh đã chỉnh. Thử xoay/cắt lại rồi bấm Duyệt.',
                        true
                    );
                });
        });
    }

    function bindPreviewTools(review) {
        if (!review || review.getAttribute('data-cccd-tools-bound') === '1') {
            return;
        }
        review.setAttribute('data-cccd-tools-bound', '1');

        review.querySelectorAll('[data-cccd-preview]').forEach(function (shot) {
            bindCropLayer(shot);
            applyCropBoxPercent(shot, defaultStageCrop());
            syncCropClearBtn(shot);
        });

        var form = review.querySelector('[data-admin-pending-form]');
        if (form) {
            bindApprovePhotoSubmit(form);
        }

        review.addEventListener('click', function (e) {
            var shot = e.target.closest('[data-cccd-preview]');
            if (!shot) {
                return;
            }

            var rotateBtn = e.target.closest('[data-cccd-rotate]');
            var resetBtn = e.target.closest('[data-cccd-rotate-reset]');
            var cropToggle = e.target.closest('[data-cccd-crop-toggle]');
            var cropClear = e.target.closest('[data-cccd-crop-clear]');

            if (cropClear) {
                e.preventDefault();
                resetShot(shot);
                return;
            }

            if (cropToggle) {
                e.preventDefault();
                if (shot.classList.contains('is-cropping')) {
                    applyCrop(shot);
                } else {
                    setCropMode(shot, true);
                }
                return;
            }

            if (resetBtn) {
                e.preventDefault();
                resetShot(shot);
                return;
            }

            if (rotateBtn) {
                e.preventDefault();
                if (shot.classList.contains('is-cropping')) {
                    setCropMode(shot, false);
                }
                var delta = Number(rotateBtn.getAttribute('data-cccd-rotate') || 0);
                rotateShot(shot, delta > 0).catch(function () { /* ignore */ });
            }
        });
    }

    function bindRoot(root) {
        if (root.getAttribute('data-idscan-bound') === '1') {
            return;
        }
        root.setAttribute('data-idscan-bound', '1');

        var review = root.closest('.admin-pending-review');
        if (review) {
            bindPreviewTools(review);
        }

        var dobEl = root.querySelector('[data-idscan-dob]');
        if (dobEl) {
            dobEl.addEventListener('change', function () {
                syncAgeHint(root);
            });
            dobEl.addEventListener('input', function () {
                syncAgeHint(root);
            });
            syncAgeHint(root);
        }
    }

    function init() {
        document.querySelectorAll('[data-admin-idscan]').forEach(bindRoot);
        document.querySelectorAll('.admin-pending-review').forEach(bindPreviewTools);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
