(function () {
    var modalEl = document.getElementById('cancellationReasonModal');
    if (!modalEl || typeof bootstrap === 'undefined') {
        return;
    }

    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    var titleEl = document.getElementById('cancellationReasonModalTitle');
    var hintEl = document.getElementById('cancellationReasonModalHint');
    var listEl = document.getElementById('cancellationReasonModalList');
    var errorEl = document.getElementById('cancellationReasonModalError');
    var confirmBtn = document.getElementById('cancellationReasonModalConfirm');
    var reasonsUrl = window.__cancellationReasonsUrl || '/cancellation-reasons';
    var pendingResolve = null;
    var selectedId = null;
    var reasonsCache = {};

    function escapeHtml(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function fetchReasons(audience, contactPhone) {
        var key = audience + ':' + (contactPhone || '');
        if (reasonsCache[key]) {
            return Promise.resolve(reasonsCache[key]);
        }
        var url = new URL(reasonsUrl, window.location.origin);
        url.searchParams.set('audience', audience);
        if (contactPhone) {
            url.searchParams.set('contact_phone', contactPhone);
        }
        return fetch(url.toString(), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                reasonsCache[key] = data;
                return data;
            });
    }

    function renderReasons(reasons) {
        listEl.innerHTML = '';
        selectedId = null;
        confirmBtn.disabled = true;
        if (!reasons.length) {
            listEl.innerHTML = '<p class="small text-muted mb-0">Chưa có lý do hủy — vui lòng liên hệ quản lý.</p>';
            return;
        }
        reasons.forEach(function (reason) {
            var id = 'cancel-reason-' + reason.id;
            var wrap = document.createElement('div');
            wrap.className = 'form-check cancellation-reason-item mb-2';
            wrap.innerHTML =
                '<input class="form-check-input" type="radio" name="cancellation_reason_pick" id="' + id + '" value="' + reason.id + '">' +
                '<label class="form-check-label" for="' + id + '">' + escapeHtml(reason.label) + '</label>';
            listEl.appendChild(wrap);
        });
        listEl.querySelectorAll('input[type="radio"]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                selectedId = parseInt(radio.value, 10);
                confirmBtn.disabled = !selectedId;
                errorEl.classList.add('d-none');
            });
        });
    }

    function pick(options) {
        var opts = options || {};
        return fetchReasons(opts.audience || 'customer', opts.contactPhone || null, opts.location || null).then(function (data) {
            if (opts.audience === 'customer' && data.requires_reason === false) {
                return { skipped: true, reasonId: null };
            }
            if (!data.reasons || !data.reasons.length) {
                return Promise.reject(new Error('Hệ thống chưa cấu hình lý do hủy. Vui lòng liên hệ quản lý.'));
            }
            return new Promise(function (resolve) {
                pendingResolve = resolve;
                titleEl.textContent = opts.title || 'Chọn lý do hủy';
                hintEl.textContent = opts.hint || 'Vui lòng chọn một lý do trước khi hủy chuyến.';
                errorEl.classList.add('d-none');
                renderReasons(data.reasons);
                modal.show();
            });
        });
    }

    confirmBtn.addEventListener('click', function () {
        if (!selectedId || !pendingResolve) {
            errorEl.textContent = 'Vui lòng chọn lý do hủy.';
            errorEl.classList.remove('d-none');
            return;
        }
        var resolve = pendingResolve;
        pendingResolve = null;
        resolve({ skipped: false, reasonId: selectedId });
        modal.hide();
    });

    modalEl.addEventListener('hidden.bs.modal', function () {
        if (pendingResolve) {
            var resolve = pendingResolve;
            pendingResolve = null;
            resolve(null);
        }
    });

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement) || !form.classList.contains('cancel-reason-form')) {
            return;
        }
        if (form.dataset.reasonBypass === '1') {
            form.dataset.reasonBypass = '';
            return;
        }
        e.preventDefault();
        e.stopPropagation();

        var audience = form.getAttribute('data-audience') || 'driver';
        pick({
            audience: audience,
            title: form.getAttribute('data-reason-title') || 'Chọn lý do hủy chuyến',
            hint: form.getAttribute('data-reason-hint') || 'Quản lý sẽ được thông báo lý do bạn chọn.',
        }).then(function (result) {
            if (!result) {
                return;
            }
            var input = form.querySelector('input[name="cancellation_reason_id"]');
            if (!input) {
                input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'cancellation_reason_id';
                form.appendChild(input);
            }
            input.value = result.reasonId || '';
            form.dataset.reasonBypass = '1';
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        }).catch(function (err) {
            if (window.AppFlash && window.AppFlash.show) {
                window.AppFlash.show(err.message || 'Không tải được lý do hủy.', { variant: 'danger', title: 'Không tải được lý do hủy' });
            } else if (window.AppDialog) {
                window.AppDialog.alert(err.message || 'Không tải được lý do hủy.', { variant: 'danger' });
            }
        });
    }, true);

    window.CancellationReasonModal = { pick: pick, clearCache: function () { reasonsCache = {}; } };
})();
