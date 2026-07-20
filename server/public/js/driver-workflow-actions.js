/**
 * Tiến trình chuyến tài xế — Đến điểm đón / Hủy / Hoàn thành qua AJAX.
 */
(function () {
    var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    function isWorkflowForm(form) {
        return form
            && form.classList.contains('driver-workflow-compact-action')
            && !form.classList.contains('driver-reject-form')
            && !form.classList.contains('driver-accept-form');
    }

    function setWorkflowBusy(busy) {
        if (busy) {
            document.body.dataset.driverWorkflowBusy = '1';
            document.body.dataset.driverTripActionBusy = '1';
            return;
        }
        delete document.body.dataset.driverWorkflowBusy;
        if (!document.body.dataset.driverRejectBusy) {
            delete document.body.dataset.driverTripActionBusy;
        }
    }

    function resetFormState(form, submitBtn) {
        form.dataset.workflowSubmitting = '';
        setWorkflowBusy(false);
        if (submitBtn) {
            submitBtn.disabled = false;
        }
    }

    function showError(message) {
        if (window.AppFlash && window.AppFlash.show) {
            window.AppFlash.show(message || 'Không thực hiện được.', { variant: 'danger', title: 'Không thực hiện được' });
            return;
        }
        if (window.AppDialog && window.AppDialog.alert) {
            window.AppDialog.alert(message || 'Không thực hiện được.', { variant: 'danger' });
        }
    }

    function ensureReasonInput(form, reasonId) {
        var input = form.querySelector('input[name="cancellation_reason_id"]');
        if (!input) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'cancellation_reason_id';
            form.appendChild(input);
        }
        input.value = String(reasonId || '');
    }

    function pickCancelReason(form) {
        if (!window.CancellationReasonModal || !window.CancellationReasonModal.pick) {
            return Promise.reject(new Error('Không tải được lý do hủy.'));
        }

        return window.CancellationReasonModal.pick({
            audience: form.getAttribute('data-audience') || 'driver',
            title: form.getAttribute('data-reason-title') || 'Chọn lý do hủy chuyến',
            hint: form.getAttribute('data-reason-hint') || 'Quản lý sẽ được thông báo lý do bạn chọn.',
        }).then(function (result) {
            if (!result || !result.reasonId) {
                return null;
            }
            ensureReasonInput(form, result.reasonId);
            return result.reasonId;
        });
    }

    function confirmWorkflow(form) {
        var message = form.getAttribute('data-confirm');
        if (!message) {
            return Promise.resolve(true);
        }

        var options = {
            title: form.getAttribute('data-confirm-title') || 'Xác nhận',
            message: message,
            confirmText: form.getAttribute('data-confirm-ok') || 'Xác nhận',
            cancelText: form.getAttribute('data-confirm-cancel') || 'Huỷ',
            variant: form.getAttribute('data-confirm-variant') || 'primary',
        };

        if (window.AppDialog && window.AppDialog.confirm) {
            return window.AppDialog.confirm(options);
        }

        return Promise.resolve(window.confirm(message));
    }

    function postWorkflowForm(form, submitBtn) {
        return fetch(form.action, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: new FormData(form),
            credentials: 'same-origin',
        }).then(function (response) {
            return response.text().then(function (text) {
                var data = {};
                if (text) {
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        data = {};
                    }
                }

                return { ok: response.ok, data: data, status: response.status };
            });
        }).then(function (result) {
            if (!result.ok) {
                var message = result.data && result.data.message
                    ? result.data.message
                    : (result.status === 419
                        ? 'Phiên đăng nhập hết hạn — tải lại trang rồi thử lại.'
                        : 'Không thực hiện được.');
                throw new Error(message);
            }

            var redirect = (result.data && result.data.redirect)
                || (window.__driverDashboardUrl || '/driver/dashboard?tab=trips');

            // Chỉ popup khi bị khóa tài khoản; hủy thành công thì về dashboard im lặng.
            if (result.data && result.data.account_locked && result.data.message) {
                var go = function () { window.location.assign(redirect); };
                if (window.AppDialog && window.AppDialog.alert) {
                    window.AppDialog.alert(result.data.message, {
                        title: 'Tài khoản bị khóa',
                        variant: 'danger',
                    }).then(go).catch(go);
                    return;
                }
                window.alert(result.data.message);
            }

            window.location.assign(redirect);
        }).catch(function (err) {
            resetFormState(form, submitBtn);
            showError(err.message);
        });
    }

    function runWorkflow(form, submitBtn) {
        if (!form || form.dataset.workflowSubmitting === '1') {
            return;
        }

        var chain = Promise.resolve();

        if (form.classList.contains('cancel-reason-form')) {
            var existingReason = form.querySelector('input[name="cancellation_reason_id"]');
            if (!existingReason || !existingReason.value) {
                chain = pickCancelReason(form).then(function (reasonId) {
                    if (!reasonId) {
                        return null;
                    }
                });
            }
        }

        chain.then(function (picked) {
            if (picked === null) {
                return;
            }

            return confirmWorkflow(form).then(function (ok) {
                if (!ok) {
                    return;
                }

                form.dataset.workflowSubmitting = '1';
                setWorkflowBusy(true);
                if (submitBtn) {
                    submitBtn.disabled = true;
                }

                return postWorkflowForm(form, submitBtn);
            });
        }).catch(function (err) {
            resetFormState(form, submitBtn);
            showError(err.message);
        });
    }

    document.addEventListener('click', function (event) {
        var submitBtn = event.target.closest('.driver-workflow-compact-action button[type="submit"], .driver-workflow-compact-action button:not([type])');
        if (!submitBtn) {
            return;
        }

        var form = submitBtn.closest('form');
        if (!isWorkflowForm(form)) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();
        runWorkflow(form, submitBtn);
    }, true);

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!isWorkflowForm(form)) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();

        if (form.dataset.workflowSubmitting === '1') {
            return;
        }

        runWorkflow(form, form.querySelector('[type="submit"], button:not([type])'));
    }, true);
})();
