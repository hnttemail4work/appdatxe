/**
 * Wizard đăng ký tài xế — điều hướng từng bước, validate client-side, tóm tắt cuối.
 */
(function () {
    var form = document.getElementById('driver-register-form');
    var wizard = document.getElementById('driver-wizard');
    if (!form || !wizard) return;

    var panels = wizard.querySelectorAll('[data-wizard-step]');
    var stepBtns = wizard.querySelectorAll('[data-goto-step]');
    var btnPrev = wizard.querySelector('[data-wizard-prev]');
    var btnNext = wizard.querySelector('[data-wizard-next]');
    var btnSubmit = wizard.querySelector('[data-wizard-submit]');
    var progressBar = wizard.querySelector('[data-wizard-progress]');
    var reviewBox = wizard.querySelector('[data-review-summary]');
    var stepLabelEl = wizard.querySelector('[data-wizard-step-label]');
    var stepCountEl = wizard.querySelector('[data-wizard-step-count]');
    var current = 1;
    var total = panels.length;
    var vehicleFiles = [];
    var vehicleObjectUrls = [];

    function showStep(n) {
        current = Math.max(1, Math.min(total, n));
        panels.forEach(function (panel) {
            var step = parseInt(panel.dataset.wizardStep, 10);
            panel.classList.toggle('d-none', step !== current);
        });
        var activeLabel = '';
        stepBtns.forEach(function (btn) {
            var step = parseInt(btn.dataset.gotoStep, 10);
            var isActive = step === current;
            btn.classList.toggle('active', isActive);
            btn.classList.toggle('done', step < current);
            if (isActive) activeLabel = btn.getAttribute('data-step-label') || '';
        });
        if (stepLabelEl) stepLabelEl.textContent = activeLabel;
        if (stepCountEl) stepCountEl.textContent = current + '/' + total;
        if (progressBar) {
            progressBar.style.width = Math.round((current / total) * 100) + '%';
        }
        if (btnPrev) btnPrev.disabled = current === 1;
        if (btnNext) btnNext.classList.toggle('d-none', current === total);
        if (btnSubmit) btnSubmit.classList.toggle('d-none', current !== total);
        if (current === total) buildReview();
        window.scrollTo({ top: wizard.offsetTop - 20, behavior: 'smooth' });
    }

    function fieldsInStep(step) {
        var panel = wizard.querySelector('[data-wizard-step="' + step + '"]');
        if (!panel) return [];
        return Array.from(panel.querySelectorAll('input, select, textarea')).filter(function (el) {
            return el.name && !el.disabled && el.type !== 'hidden';
        });
    }

    function fieldLabel(el) {
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

    function truncateFileName(name, maxLen) {
        maxLen = maxLen || 22;
        if (!name || name.length <= maxLen) return name || '';
        var dot = name.lastIndexOf('.');
        var ext = dot > 0 ? name.slice(dot) : '';
        var keep = Math.max(4, maxLen - ext.length - 1);
        return name.slice(0, keep) + '…' + ext;
    }

    function updateFileNameLabel(input) {
        if (!input) return;
        var wrap = fileFieldWrap(input);
        var label = wrap && wrap.querySelector('[data-file-name]');
        if (!label) return;
        if (input.name === 'photo_vehicles[]') {
            label.textContent = vehicleFiles.length ? (vehicleFiles.length + ' ảnh') : 'Chưa chọn';
            return;
        }
        var file = input.files && input.files[0];
        label.textContent = file ? truncateFileName(file.name) : 'Chưa chọn';
        if (wrap && wrap.classList.contains('register-file-tile')) {
            wrap.classList.toggle('has-file', !!file);
        }
    }

    function passwordRuleMessage(value) {
        if (!value) return 'Vui lòng nhập mật khẩu.';
        if (value.length < 8 || !/[a-z]/.test(value) || !/[A-Z]/.test(value) || !/\d/.test(value)) {
            return 'Mật khẩu tối thiểu 8 ký tự, gồm chữ hoa, chữ thường và số.';
        }
        return '';
    }

    function emptyRequiredMessage(el) {
        var label = fieldLabel(el);
        if (el.type === 'checkbox') {
            return 'Vui lòng đồng ý với điều khoản.';
        }
        if (el.tagName === 'SELECT' || el.type === 'file') {
            return 'Vui lòng chọn ' + label + '.';
        }
        return 'Vui lòng điền ' + label + '.';
    }

    function isEmpty(el) {
        if (window.FormFieldValidation && FormFieldValidation.isEmpty) {
            return FormFieldValidation.isEmpty(el);
        }
        if (el.type === 'checkbox') return !el.checked;
        if (el.type === 'file') return !el.files || el.files.length === 0;
        return String(el.value || '').trim() === '';
    }

    function validateStep(step) {
        var fields = fieldsInStep(step);
        var firstInvalid = null;

        fields.forEach(function (el) {
            clearFieldInvalid(el);

            if (el.dataset.wizardSkipValidity === '1' || el.name === 'email') {
                return;
            }

            if (el.name === 'password') {
                var pwdMsg = passwordRuleMessage(el.value);
                if (pwdMsg) {
                    markInvalid(el, pwdMsg);
                    if (!firstInvalid) firstInvalid = el;
                }
                return;
            }

            if (el.name === 'password_confirmation') {
                var pwd = form.querySelector('[name="password"]');
                if (isEmpty(el)) {
                    markInvalid(el, 'Vui lòng nhập lại mật khẩu.');
                    if (!firstInvalid) firstInvalid = el;
                } else if (pwd && el.value !== pwd.value) {
                    markInvalid(el, 'Mật khẩu không khớp.');
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

        if (step === total) {
            var terms = form.querySelector('#termsCheck');
            if (terms && !terms.checked) {
                markInvalid(terms, 'Vui lòng đồng ý với điều khoản.');
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

    function reviewRow(label, value) {
        return '<div class="driver-review-row">'
            + '<span class="review-k">' + escapeHtml(label) + '</span>'
            + '<span class="review-v">' + escapeHtml(value) + '</span>'
            + '</div>';
    }

    function buildReview() {
        if (!reviewBox) return;
        var labels = {
            name: 'Họ tên', email: 'Email', phone: 'SĐT',
            vehicle_license_plate: 'Biển số', vehicle_type: 'Loại xe',
        };
        var html = '';
        Object.keys(labels).forEach(function (name) {
            var el = form.querySelector('[name="' + name + '"]');
            if (!el || el.type === 'password') return;
            var val = el.value.trim();
            if (!val) return;
            if (name === 'vehicle_type' && el.selectedOptions && el.selectedOptions[0]) {
                val = el.selectedOptions[0].textContent.trim();
            }
            html += reviewRow(labels[name], val);
        });
        var bankEl = form.querySelector('[name="bank_name"]');
        var stkEl = form.querySelector('[name="bank_account"]');
        var bankName = bankEl ? bankEl.value.trim() : '';
        var stk = stkEl ? stkEl.value.trim() : '';
        if (bankName || stk) {
            html += reviewRow('Ngân hàng', [bankName, stk].filter(Boolean).join(' - '));
        }
        var files = ['photo_portrait', 'photo_id_card', 'photo_id_card_back', 'photo_license_front', 'photo_license_back'];
        var uploaded = files.filter(function (n) {
            var f = form.querySelector('[name="' + n + '"]');
            return f && f.files && f.files.length > 0;
        });
        html += reviewRow('Ảnh đã chọn', uploaded.length + ' giấy tờ + ' + vehicleFiles.length + ' ảnh xe');
        reviewBox.innerHTML = html;
    }

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    if (btnNext) {
        btnNext.addEventListener('click', function () {
            if (!validateStep(current)) return;
            showStep(current + 1);
        });
    }
    if (btnPrev) {
        btnPrev.addEventListener('click', function () { showStep(current - 1); });
    }
    stepBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var target = parseInt(btn.dataset.gotoStep, 10);
            if (target <= current) {
                showStep(target);
                return;
            }
            for (var s = current; s < target; s++) {
                if (!validateStep(s)) {
                    showStep(s);
                    return;
                }
            }
            showStep(target);
        });
    });

    form.addEventListener('submit', function (e) {
        for (var s = 1; s <= total; s++) {
            if (!validateStep(s)) {
                e.preventDefault();
                showStep(s);
                return;
            }
        }
    });

    // Nhập/chọn xong → bỏ viền đỏ + message
    form.addEventListener('input', function (e) {
        var el = e.target;
        if (!el || !el.name) return;
        if (el.name === 'password') {
            if (!passwordRuleMessage(el.value)) clearFieldInvalid(el);
            return;
        }
        if (el.name === 'password_confirmation') {
            var pwd = form.querySelector('[name="password"]');
            if (el.value && pwd && el.value === pwd.value) clearFieldInvalid(el);
            return;
        }
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

    // Format biển số cơ bản
    form.querySelectorAll('[data-plate-format]').forEach(function (input) {
        input.addEventListener('blur', function () {
            var v = input.value.toUpperCase().replace(/\s+/g, '');
            if (v.length >= 7 && v.indexOf('-') === -1) {
                input.value = v.slice(0, 3) + '-' + v.slice(3);
            }
        });
    });

    // Nút chọn ảnh tùy chỉnh — ẩn text native "Không có tệp..."
    form.querySelectorAll('[data-file-trigger]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var input = btn.closest('.register-file-field') && btn.closest('.register-file-field').querySelector('.register-file-input');
            if (input) input.click();
        });
    });

    // Ảnh xe — gom nhiều lần chọn, xem trước + xóa từng ảnh
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
            btn.setAttribute('aria-label', 'Xóa ảnh ' + file.name);
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

    // Xem trước ảnh giấy tờ — chọn file thì bỏ viền đỏ
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

    // Hiện feedback server + mở đúng bước lỗi (vd. SĐT trùng → bước Tài khoản)
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
            if (key) {
                errorField = form.querySelector('[name="' + key + '"]');
            }
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
