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
        stepBtns.forEach(function (btn) {
            var step = parseInt(btn.dataset.gotoStep, 10);
            btn.classList.toggle('active', step === current);
            btn.classList.toggle('done', step < current);
        });
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

    function validateStep(step) {
        var fields = fieldsInStep(step);
        var firstInvalid = null;

        fields.forEach(function (el) {
            el.classList.remove('is-invalid');
            if (!el.checkValidity()) {
                el.classList.add('is-invalid');
                if (!firstInvalid) firstInvalid = el;
            }
        });

        if (step === 1) {
            var vehicleInput = form.querySelector('[name="photo_vehicles[]"]');
            if (vehicleFiles.length === 0) {
                if (vehicleInput) vehicleInput.classList.add('is-invalid');
                if (!firstInvalid) firstInvalid = vehicleInput;
            }
            var requiredFiles = ['photo_id_card', 'photo_id_card_back', 'photo_portrait', 'photo_license_front'];
            requiredFiles.forEach(function (name) {
                var inp = form.querySelector('[name="' + name + '"]');
                if (inp && (!inp.files || inp.files.length === 0)) {
                    inp.classList.add('is-invalid');
                    if (!firstInvalid) firstInvalid = inp;
                }
            });
        }

        if (step === 2) {
            var pwd = form.querySelector('[name="password"]');
            var pwd2 = form.querySelector('[name="password_confirmation"]');
            if (pwd && pwd2 && pwd.value !== pwd2.value) {
                pwd2.classList.add('is-invalid');
                pwd2.setCustomValidity('Mật khẩu không khớp');
                if (!firstInvalid) firstInvalid = pwd2;
            } else if (pwd2) {
                pwd2.setCustomValidity('');
            }
        }

        if (firstInvalid) {
            firstInvalid.focus();
            if (firstInvalid.type === 'file') {
                var fileMsg = step === 1
                    ? 'Vui lòng upload đủ giấy tờ, chân dung và ít nhất 1 ảnh xe.'
                    : 'Vui lòng hoàn thành các trường bắt buộc ở bước này.';
                if (window.AppFlash && window.AppFlash.show) {
                    window.AppFlash.show(fileMsg, { variant: 'warning', title: 'Chưa đủ thông tin' });
                } else if (window.AppDialog) {
                    window.AppDialog.alert(fileMsg);
                }
            }
            return false;
        }
        return true;
    }

    function buildReview() {
        if (!reviewBox) return;
        var labels = {
            name: 'Họ và tên', email: 'Email', phone: 'Điện thoại',
            vehicle_license_plate: 'Biển số', vehicle_type: 'Loại xe', vehicle_seats: 'Số ghế',
            bank_name: 'Ngân hàng', bank_account: 'STK',
        };
        var vehicleTypeLabels = { limousine: 'Limousine', sedan: 'Sedan', suv: 'SUV' };
        var html = '';
        Object.keys(labels).forEach(function (name) {
            var el = form.querySelector('[name="' + name + '"]');
            if (!el || el.type === 'password') return;
            var val = el.value.trim();
            if (!val) return;
            if (name === 'vehicle_type') val = vehicleTypeLabels[val] || val;
            html += '<dt class="col-sm-4 text-muted">' + labels[name] + '</dt>';
            html += '<dd class="col-sm-8 mb-2">' + escapeHtml(val) + '</dd>';
        });
        var files = ['photo_id_card', 'photo_portrait', 'photo_license_front'];
        var uploaded = files.filter(function (n) {
            var f = form.querySelector('[name="' + n + '"]');
            return f && f.files && f.files.length > 0;
        });
        html += '<dt class="col-sm-4 text-muted">Ảnh đã chọn</dt>';
        html += '<dd class="col-sm-8 mb-0">' + uploaded.length + ' giấy tờ + ' + vehicleFiles.length + ' ảnh xe</dd>';
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
        for (var s = 1; s <= total - 1; s++) {
            if (!validateStep(s)) {
                e.preventDefault();
                showStep(s);
                return;
            }
        }
        var terms = form.querySelector('#termsCheck');
        if (terms && !terms.checked) {
            e.preventDefault();
            showStep(total);
            terms.focus();
        }
    });

    // Gợi ý số ghế theo loại xe
    var typeSelect = form.querySelector('[data-seats-hint]');
    var seatsInput = form.querySelector('#vehicle_seats_input');
    if (typeSelect && seatsInput) {
        typeSelect.addEventListener('change', function () {
            var opt = typeSelect.selectedOptions[0];
            if (opt && opt.dataset.defaultSeats && (!seatsInput.value || seatsInput.value === '9')) {
                seatsInput.value = opt.dataset.defaultSeats;
            }
        });
    }

    // Format biển số cơ bản
    form.querySelectorAll('[data-plate-format]').forEach(function (input) {
        input.addEventListener('blur', function () {
            var v = input.value.toUpperCase().replace(/\s+/g, '');
            if (v.length >= 7 && v.indexOf('-') === -1) {
                input.value = v.slice(0, 3) + '-' + v.slice(3);
            }
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
                if (vehicleInput) vehicleInput.classList.remove('is-invalid');
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
            vehicleInput.classList.remove('is-invalid');
            renderVehiclePreview();
        });
    }

    // Xem trước ảnh giấy tờ
    form.querySelectorAll('[data-field-section="documents"] input[type="file"]').forEach(function (input) {
        if (input.name === 'photo_vehicles[]') return;
        var preview = input.closest('.col-md-6, .col-lg-4')?.querySelector('[data-doc-preview]');
        if (!preview) return;
        input.addEventListener('change', function () {
            if (!input.files || !input.files[0]) {
                preview.classList.add('d-none');
                preview.removeAttribute('src');
                return;
            }
            preview.src = URL.createObjectURL(input.files[0]);
            preview.classList.remove('d-none');
        });
    });

    // Khôi phục bước khi validation server fail
    var hasErrors = form.querySelector('.is-invalid, .invalid-feedback:not(:empty)');
    if (hasErrors) {
        var errorPanel = form.querySelector('.is-invalid');
        if (errorPanel) {
            var panel = errorPanel.closest('[data-wizard-step]');
            if (panel) showStep(parseInt(panel.dataset.wizardStep, 10));
        }
    } else {
        showStep(1);
    }
})();
