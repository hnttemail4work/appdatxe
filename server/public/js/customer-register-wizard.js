/**
 * Wizard đăng ký khách — SĐT → CCCD + điều khoản → PIN → xác nhận PIN.
 * PIN đủ 6 số tự chuyển bước / gửi form (không mũi tên).
 */
(function () {
    var root = document.querySelector('[data-customer-wizard-root]');
    var form = document.getElementById('customer-register-form');
    var wizard = document.getElementById('customer-wizard');
    if (!form || !wizard) return;

    var panels = wizard.querySelectorAll('[data-wizard-step]');
    var backLink = (root || document).querySelector('[data-auth-back]');
    var titleEl = (root || document).querySelector('[data-auth-title]');
    var homeUrl = (root && root.dataset.homeUrl) || (backLink && backLink.getAttribute('href')) || '/';
    var stepTitles = {};
    try {
        stepTitles = JSON.parse((root && root.dataset.stepTitles) || '{}');
    } catch (e) {
        stepTitles = {};
    }

    var passwordInput = form.querySelector('[data-register-password]');
    var passwordConfirmInput = form.querySelector('[data-register-password-confirm]');
    var draftPin = '';
    var current = 1;
    var total = panels.length;

    function pinWrap(step) {
        var panel = wizard.querySelector('[data-wizard-step="' + step + '"]');
        return panel ? panel.querySelector('[data-pin-boxes]') : null;
    }

    function pinValue(step) {
        var wrap = pinWrap(step);
        if (!wrap || !window.PinInput) return '';
        return PinInput.value(wrap);
    }

    function syncChrome() {
        if (titleEl) {
            titleEl.textContent = stepTitles[current] || ('Bước ' + current);
        }
        if (!backLink) return;
        if (current <= 1) {
            backLink.setAttribute('href', homeUrl);
            backLink.onclick = null;
        } else {
            backLink.setAttribute('href', '#');
            backLink.onclick = function (e) {
                e.preventDefault();
                if (current === 4) draftPin = '';
                showStep(current - 1);
            };
        }
    }

    function showStep(n) {
        current = Math.max(1, Math.min(total, n));
        panels.forEach(function (panel) {
            var step = parseInt(panel.dataset.wizardStep, 10);
            panel.hidden = step !== current;
            panel.classList.toggle('d-none', step !== current);
        });
        syncChrome();
        if ((current === 3 || current === 4) && window.PinInput) {
            var wrap = pinWrap(current);
            if (wrap) {
                PinInput.clear(wrap);
                var first = wrap.querySelector('.pin-box');
                if (first) setTimeout(function () { first.focus(); }, 50);
            }
        }
        if (current === 1) {
            var phone = form.querySelector('#customer-register-phone');
            if (phone) setTimeout(function () { phone.focus(); }, 50);
        }
    }

    function fieldsInStep(step) {
        var panel = wizard.querySelector('[data-wizard-step="' + step + '"]');
        if (!panel) return [];
        return Array.from(panel.querySelectorAll('input, select, textarea')).filter(function (el) {
            return el.name && !el.disabled && el.type !== 'hidden' && !el.classList.contains('pin-box');
        });
    }

    var V = window.AuthFieldValidation;

    function fieldLabel(el) {
        if (V && V.fieldLabel) return V.fieldLabel(el);
        var wrap = el.closest('.register-doc-item, .mb-3, .form-check, .register-section, .auth-field-block');
        var label = wrap && (wrap.querySelector('.register-file-tile-label') || wrap.querySelector('label') || wrap.querySelector('.auth-field-label'));
        if (label) {
            return label.textContent.replace(/\*/g, '').trim();
        }
        return el.name || 'trường này';
    }

    function feedbackEl(el) {
        if (!el) return null;
        var key = el.name || el.id || '';
        var fb = key ? form.querySelector('[data-client-feedback="' + key + '"]') : null;
        if (fb) return fb;
        var parent = el.closest('.auth-field-block') || el.closest('.mb-3') || el.parentElement;
        return (parent && parent.querySelector('.auth-field-error, .invalid-feedback')) || null;
    }

    function fileFieldWrap(el) {
        return el && el.closest ? el.closest('.register-file-field') : null;
    }

    function clearFieldInvalid(el) {
        if (!el) return;
        el.classList.remove('is-invalid');
        var wrap = fileFieldWrap(el);
        if (wrap) wrap.classList.remove('is-invalid');
        el.setCustomValidity('');
        var fb = feedbackEl(el);
        if (fb) {
            fb.textContent = '';
            fb.classList.remove('d-block');
        }
    }

    function markInvalid(el, message) {
        if (!el) return;
        el.classList.add('is-invalid');
        var wrap = fileFieldWrap(el);
        if (wrap) wrap.classList.add('is-invalid');
        var fb = feedbackEl(el);
        if (fb) {
            fb.textContent = message || '';
            if (message) fb.classList.add('d-block');
        }
        if (message) el.setCustomValidity(message);
    }

    function updateFileNameLabel(input) {
        if (!input) return;
        var wrap = fileFieldWrap(input);
        var label = wrap && wrap.querySelector('[data-file-name]');
        if (!label) return;
        var file = input.files && input.files[0];
        label.textContent = file ? 'Đã chọn' : 'Chưa chọn';
        if (wrap && wrap.classList.contains('register-file-tile')) {
            wrap.classList.toggle('has-file', !!file);
        }
        var preview = wrap && wrap.querySelector('[data-doc-preview]');
        if (preview) {
            if (preview.dataset.objectUrl) {
                URL.revokeObjectURL(preview.dataset.objectUrl);
                delete preview.dataset.objectUrl;
            }
            if (file && file.type.indexOf('image/') === 0) {
                var url = URL.createObjectURL(file);
                preview.dataset.objectUrl = url;
                preview.src = url;
                preview.classList.remove('d-none');
            } else {
                preview.removeAttribute('src');
                preview.classList.add('d-none');
            }
        }
    }

    function isEmpty(el) {
        if (V && V.isEmpty) return V.isEmpty(el);
        if (el.type === 'checkbox') return !el.checked;
        if (el.type === 'file') return !el.files || el.files.length === 0;
        return String(el.value || '').trim() === '';
    }

    function validateStep(step) {
        if (step === 3) {
            var wrap3 = pinWrap(3);
            if (V && wrap3) {
                if (!V.validatePinWrap(wrap3)) return false;
                draftPin = V.pinValueFrom(wrap3);
                return true;
            }
            var pin = pinValue(3);
            if (!/^\d{6}$/.test(pin)) {
                if (wrap3 && window.PinInput) PinInput.clear(wrap3);
                return false;
            }
            draftPin = pin;
            return true;
        }

        if (step === 4) {
            var wrap4 = pinWrap(4);
            var confirm = V && wrap4 ? V.pinValueFrom(wrap4) : pinValue(4);
            var mismatch = V ? V.pinsMatch(draftPin, confirm) : '';
            if (mismatch || !/^\d{6}$/.test(confirm) || confirm !== draftPin) {
                if (V && wrap4) V.showPinError(wrap4, (V && V.MSG.pinMismatch) || 'Nhập lại PIN không khớp.');
                draftPin = '';
                showStep(3);
                return false;
            }
            if (passwordInput) passwordInput.value = draftPin;
            if (passwordConfirmInput) passwordConfirmInput.value = confirm;
            return true;
        }

        var fields = fieldsInStep(step);
        var firstInvalid = null;
        var termsMsg = (V && V.MSG.terms) || 'Vui lòng đồng ý với điều khoản.';

        fields.forEach(function (el) {
            clearFieldInvalid(el);
            if (el.name === 'phone') {
                var phoneMsg = V ? V.validatePhone(el.value) : '';
                if (!phoneMsg && !V) {
                    var digits = String(el.value || '').replace(/\D/g, '');
                    if (!digits) phoneMsg = 'Vui lòng nhập số điện thoại.';
                    else if (!/^0\d{8,10}$/.test(digits)) phoneMsg = 'Số điện thoại không đúng định dạng Việt Nam.';
                }
                if (phoneMsg) {
                    markInvalid(el, phoneMsg);
                    if (!firstInvalid) firstInvalid = el;
                }
                return;
            }
            if (el.required && isEmpty(el)) {
                var msg = el.type === 'checkbox'
                    ? termsMsg
                    : (el.type === 'file' ? 'Vui lòng chọn ' + fieldLabel(el) + '.' : 'Vui lòng điền ' + fieldLabel(el) + '.');
                markInvalid(el, msg);
                if (!firstInvalid) firstInvalid = el;
            }
        });

        if (step === 2) {
            ['photo_id_card', 'photo_id_card_back'].forEach(function (name) {
                var inp = form.querySelector('[name="' + name + '"]');
                if (inp && isEmpty(inp)) {
                    markInvalid(inp, 'Vui lòng chọn ' + fieldLabel(inp) + '.');
                    if (!firstInvalid) firstInvalid = inp;
                }
            });
            var terms = form.querySelector('#customerTermsCheck');
            if (terms && !terms.checked) {
                markInvalid(terms, termsMsg);
                if (!firstInvalid) firstInvalid = terms;
            }
        }

        if (firstInvalid) {
            firstInvalid.focus({ preventScroll: true });
            firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return false;
        }
        return true;
    }

    function goNext() {
        if (!validateStep(current)) return;
        if (current < total) showStep(current + 1);
    }

    wizard.querySelectorAll('[data-wizard-next]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            goNext();
        });
    });

    // PIN đủ 6 số: bước tạo PIN → tiếp; bước xác nhận → submit form.
    wizard.addEventListener('pin:change', function (e) {
        var value = (e.detail && e.detail.value) || '';
        if (!/^\d{6}$/.test(value)) return;
        if (current === 3) {
            goNext();
            return;
        }
        if (current === 4 && validateStep(4)) {
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        }
    });

    form.querySelectorAll('[data-file-trigger]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var wrap = btn.closest('.register-file-field');
            var input = wrap && wrap.querySelector('input[type="file"]');
            if (input) input.click();
        });
    });

    form.querySelectorAll('input[type="file"]').forEach(function (input) {
        input.addEventListener('change', function () {
            clearFieldInvalid(input);
            updateFileNameLabel(input);
        });
        updateFileNameLabel(input);
    });

    var phoneEl = form.querySelector('#customer-register-phone');
    if (phoneEl) {
        phoneEl.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                goNext();
            }
        });
    }

    var termsEl = form.querySelector('#customerTermsCheck');
    if (termsEl) {
        termsEl.addEventListener('change', function () {
            clearFieldInvalid(termsEl);
        });
    }

    form.addEventListener('submit', function (event) {
        for (var s = 1; s <= total; s++) {
            if (!validateStep(s)) {
                event.preventDefault();
                if (s !== 4) showStep(s);
                return;
            }
        }
    });

    // Start: nếu SĐT đã có từ login (query/old) thì bỏ qua bước nhập lại.
    var startStep = 1;
    var phoneElInit = form.querySelector('#customer-register-phone');
    if (phoneElInit) {
        var phoneMsg = V ? V.validatePhone(phoneElInit.value) : '';
        var digits = String(phoneElInit.value || '').replace(/\D/g, '');
        var phoneOk = V ? !phoneMsg : /^0\d{8,10}$/.test(digits);
        if (phoneOk) {
            startStep = 2;
        }
    }
    showStep(startStep);
})();
