/**
 * Wizard đăng ký tài xế — hybrid: nhóm giấy tờ/xe/NH + PIN/OTP chrome tối giản.
 */
(function () {
    var root = document.querySelector('[data-driver-wizard-root]');
    var form = document.getElementById('driver-register-form');
    var wizard = document.getElementById('driver-wizard');
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

    var current = 1;
    var total = panels.length;
    var vehicleFiles = [];
    var vehicleObjectUrls = [];
    var draftPin = '';
    var passwordInput = form.querySelector('[data-register-password]');
    var passwordConfirmInput = form.querySelector('[data-register-password-confirm]');

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
                if (current === 7) draftPin = '';
                showStep(current - 1);
            };
        }
    }

    function showStep(n) {
        current = Math.max(1, Math.min(total, n));
        panels.forEach(function (panel) {
            var step = parseInt(panel.dataset.wizardStep, 10);
            var active = step === current;
            panel.hidden = !active;
            panel.classList.toggle('d-none', !active);
        });
        syncChrome();
        if ((current === 6 || current === 7) && window.PinInput) {
            var wrap = pinWrap(current);
            if (wrap) {
                PinInput.clear(wrap);
                var first = wrap.querySelector('.pin-box');
                if (first) setTimeout(function () { first.focus(); }, 50);
            }
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
        if (window.FormFieldValidation && FormFieldValidation.label) {
            return FormFieldValidation.label(el);
        }
        var wrap = el.closest('.register-doc-item, .col-12, .col-md-6, .mb-3, .form-check, .register-section');
        var label = wrap && (wrap.querySelector('.register-file-tile-label') || wrap.querySelector('label'));
        if (label) {
            return label.textContent.replace(/\*/g, '').replace(/\(tùy chọn\)/gi, '').trim();
        }
        if (el.name === 'photo_vehicles[]') return 'ảnh xe';
        return el.name || 'trường này';
    }

    function feedbackEl(el) {
        if (!el) return null;
        var key = el.name || el.id || '';
        var fb = key ? form.querySelector('[data-client-feedback="' + key + '"]') : null;
        if (fb) return fb;
        var parent = el.closest('.form-check') || el.closest('.col-12') || el.parentElement;
        fb = parent && parent.querySelector('.invalid-feedback');
        if (fb) return fb;
        fb = document.createElement('div');
        fb.className = 'invalid-feedback';
        if (key) fb.setAttribute('data-client-feedback', key);
        if (el.type === 'checkbox' && parent) {
            parent.appendChild(fb);
        } else {
            var anchor = el.closest('.register-file-field') || el.closest('.input-group') || el;
            anchor.insertAdjacentElement('afterend', fb);
        }
        return fb;
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
            else fb.classList.remove('d-block');
        }
        if (message) el.setCustomValidity(message);
    }

    function updateFileNameLabel(input) {
        if (!input) return;
        var wrap = fileFieldWrap(input);
        var label = wrap && wrap.querySelector('[data-file-name]');
        if (!label) return;
        var file = input.files && input.files[0];
        if (input.name === 'photo_vehicles[]') {
            label.textContent = vehicleFiles.length ? (vehicleFiles.length + ' ảnh') : 'Chưa chọn';
        } else {
            label.textContent = file ? 'Đã chọn' : 'Chưa chọn';
        }
        if (wrap && wrap.classList.contains('register-file-tile')) {
            wrap.classList.toggle('has-file', !!(file || (input.name === 'photo_vehicles[]' && vehicleFiles.length)));
        }
    }

    function emptyRequiredMessage(el) {
        var label = fieldLabel(el);
        if (el.type === 'checkbox') return (V && V.MSG.terms) || 'Vui lòng đồng ý với điều khoản.';
        if (el.tagName === 'SELECT' || el.type === 'file') return 'Vui lòng chọn ' + label + '.';
        return 'Vui lòng điền ' + label + '.';
    }

    function isEmpty(el) {
        if (V && V.isEmpty) return V.isEmpty(el);
        if (window.FormFieldValidation && FormFieldValidation.isEmpty) {
            return FormFieldValidation.isEmpty(el);
        }
        if (el.type === 'checkbox') return !el.checked;
        if (el.type === 'file') return !el.files || el.files.length === 0;
        return String(el.value || '').trim() === '';
    }

    function validateStep(step) {
        if (step === 6) {
            var wrap6 = pinWrap(6);
            if (V && wrap6) {
                if (!V.validatePinWrap(wrap6)) return false;
                draftPin = V.pinValueFrom(wrap6);
                return true;
            }
            var pin = pinValue(6);
            if (!/^\d{6}$/.test(pin)) {
                if (wrap6 && window.PinInput) PinInput.clear(wrap6);
                return false;
            }
            draftPin = pin;
            return true;
        }

        if (step === 7) {
            var wrap7 = pinWrap(7);
            var confirm = V && wrap7 ? V.pinValueFrom(wrap7) : pinValue(7);
            if (!/^\d{6}$/.test(confirm) || confirm !== draftPin) {
                if (V && wrap7) V.showPinError(wrap7, (V && V.MSG.pinMismatch) || 'Nhập lại PIN không khớp.');
                draftPin = '';
                showStep(6);
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
            if (el.dataset.wizardSkipValidity === '1') return;
            if (el.name === 'password' || el.name === 'password_confirmation' || el.name === 'pin_draft' || el.name === 'pin_confirm_draft') {
                return;
            }
            if (el.name === 'phone') {
                var phoneMsg = V ? V.validatePhone(el.value) : '';
                if (phoneMsg) {
                    markInvalid(el, phoneMsg);
                    if (!firstInvalid) firstInvalid = el;
                }
                return;
            }
            if (el.name === 'email') {
                var emailMsg = V ? V.validateEmailOptional(el.value) : '';
                if (emailMsg) {
                    markInvalid(el, emailMsg);
                    if (!firstInvalid) firstInvalid = el;
                }
                return;
            }
            if (el.required && isEmpty(el)) {
                markInvalid(el, emptyRequiredMessage(el));
                if (!firstInvalid) firstInvalid = el;
                return;
            }
            if (!isEmpty(el) && el.type !== 'file' && !el.checkValidity()) {
                markInvalid(el, el.validationMessage || emptyRequiredMessage(el));
                if (!firstInvalid) firstInvalid = el;
            }
        });

        if (step === 1) {
            var vehicleInput = form.querySelector('[name="photo_vehicles[]"]');
            if (vehicleFiles.length === 0) {
                if (vehicleInput) {
                    markInvalid(vehicleInput, 'Vui lòng chọn ít nhất 1 ảnh xe.');
                    if (!firstInvalid) firstInvalid = vehicleInput;
                }
            }
            ['photo_id_card', 'photo_id_card_back', 'photo_portrait', 'photo_license_front'].forEach(function (name) {
                var inp = form.querySelector('[name="' + name + '"]');
                if (inp && isEmpty(inp)) {
                    markInvalid(inp, emptyRequiredMessage(inp));
                    if (!firstInvalid) firstInvalid = inp;
                }
            });
        }

        if (step === 5) {
            var terms = form.querySelector('#termsCheck');
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

    form.addEventListener('submit', function (e) {
        for (var s = 1; s <= total; s++) {
            if (!validateStep(s)) {
                e.preventDefault();
                if (s !== 7) showStep(s);
                return;
            }
        }
    });

    form.addEventListener('input', function (e) {
        var el = e.target;
        if (!el || !el.name) return;
        if (el.classList && el.classList.contains('pin-box')) return;
        if (!isEmpty(el)) clearFieldInvalid(el);
    });

    form.addEventListener('change', function (e) {
        var el = e.target;
        if (!el || !el.name) return;
        if (el.type === 'file') {
            if (!isEmpty(el) || (el.name === 'photo_vehicles[]' && vehicleFiles.length > 0)) {
                clearFieldInvalid(el);
            }
            return;
        }
        if (el.type === 'checkbox') {
            if (el.checked) clearFieldInvalid(el);
            return;
        }
        if (!isEmpty(el)) clearFieldInvalid(el);
    });

    form.querySelectorAll('[data-plate-format]').forEach(function (input) {
        input.addEventListener('blur', function () {
            var v = input.value.toUpperCase().replace(/\s+/g, '');
            if (v.length >= 7 && v.indexOf('-') === -1) {
                input.value = v.slice(0, 3) + '-' + v.slice(3);
            }
        });
    });

    form.querySelectorAll('[data-file-trigger]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = btn.closest('.register-file-field') && btn.closest('.register-file-field').querySelector('.register-file-input');
            if (input) input.click();
        });
    });

    var vehicleInput = form.querySelector('[name="photo_vehicles[]"]');
    var vehiclePreview = form.querySelector('[data-vehicle-preview]');

    function revokeVehicleUrls() {
        vehicleObjectUrls.forEach(function (url) { URL.revokeObjectURL(url); });
        vehicleObjectUrls = [];
    }

    function syncVehicleInputFiles() {
        if (!vehicleInput) return;
        var dt = new DataTransfer();
        vehicleFiles.forEach(function (file) { dt.items.add(file); });
        vehicleInput.files = dt.files;
        updateFileNameLabel(vehicleInput);
    }

    function renderVehiclePreview() {
        if (!vehiclePreview) return;
        revokeVehicleUrls();
        vehiclePreview.innerHTML = '';
        vehicleFiles.forEach(function (file, index) {
            var wrap = document.createElement('div');
            wrap.className = 'register-vehicle-thumb';
            var img = document.createElement('img');
            var url = URL.createObjectURL(file);
            vehicleObjectUrls.push(url);
            img.src = url;
            img.alt = file.name;
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'register-vehicle-thumb-remove';
            btn.setAttribute('aria-label', 'Xóa ảnh');
            btn.textContent = '×';
            btn.addEventListener('click', function () {
                vehicleFiles.splice(index, 1);
                syncVehicleInputFiles();
                renderVehiclePreview();
                if (vehicleInput && vehicleFiles.length > 0) clearFieldInvalid(vehicleInput);
            });
            wrap.appendChild(img);
            wrap.appendChild(btn);
            vehiclePreview.appendChild(wrap);
        });
        syncVehicleInputFiles();
    }

    if (vehicleInput && vehiclePreview) {
        vehicleInput.addEventListener('change', function () {
            Array.from(vehicleInput.files || []).forEach(function (file) {
                var dup = vehicleFiles.some(function (f) {
                    return f.name === file.name && f.size === file.size && f.lastModified === file.lastModified;
                });
                if (!dup) vehicleFiles.push(file);
            });
            vehicleInput.value = '';
            clearFieldInvalid(vehicleInput);
            renderVehiclePreview();
        });
    }

    form.querySelectorAll('[data-field-section="documents"] input[type="file"]').forEach(function (input) {
        if (input.name === 'photo_vehicles[]') return;
        var wrap = fileFieldWrap(input);
        var preview = wrap && wrap.querySelector('[data-doc-preview]');
        input.addEventListener('change', function () {
            updateFileNameLabel(input);
            if (input.files && input.files[0]) clearFieldInvalid(input);
            if (!preview) return;
            if (!input.files || !input.files[0]) {
                preview.classList.add('d-none');
                preview.removeAttribute('src');
                return;
            }
            preview.src = URL.createObjectURL(input.files[0]);
            preview.classList.remove('d-none');
        });
    });

    form.querySelectorAll('.invalid-feedback').forEach(function (fb) {
        if (fb.textContent && fb.textContent.trim()) {
            fb.classList.add('d-block');
        }
    });

    var errorField = form.querySelector('.is-invalid');
    if (!errorField) {
        form.querySelectorAll('.invalid-feedback.d-block').forEach(function (fb) {
            if (errorField) return;
            var key = fb.getAttribute('data-client-feedback');
            if (key) errorField = form.querySelector('[name="' + key + '"]');
        });
    }

    if (errorField) {
        var panel = errorField.closest('[data-wizard-step]');
        showStep(panel ? parseInt(panel.dataset.wizardStep, 10) : 1);
        try {
            errorField.focus({ preventScroll: true });
            errorField.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } catch (e) { /* ignore */ }
    } else {
        showStep(1);
    }
})();
