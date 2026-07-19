/**
 * Nhận / từ chối cuốc — AJAX + xác nhận.
 */
(function () {
    var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    function isTripRequestCardForm(form) {
        return form && form.closest('[data-trip-request-id]');
    }

    function isAcceptForm(form) {
        return form
            && form.classList.contains('driver-accept-form')
            && isTripRequestCardForm(form);
    }

    function isRejectForm(form) {
        return form
            && form.classList.contains('driver-reject-form')
            && isTripRequestCardForm(form);
    }

    function setActionBusy(busy) {
        if (busy) {
            document.body.dataset.driverTripActionBusy = '1';
            document.body.dataset.driverRejectBusy = '1';
            return;
        }
        delete document.body.dataset.driverTripActionBusy;
        delete document.body.dataset.driverRejectBusy;
    }

    function removeRequestCard(form) {
        var card = form.closest('[data-trip-request-id]');
        removeRequestCardElement(card);
    }

    /** Gỡ card theo request id khi backend/SW báo offer đã hết hạn/hủy. */
    function removeRequestCardById(requestId) {
        if (!requestId) {
            return;
        }

        var card = document.querySelector('[data-trip-request-id="' + String(requestId) + '"]');
        removeRequestCardElement(card);
    }

    /** Animation xóa card dùng chung cho Reject và revoke realtime. */
    function removeRequestCardElement(card) {
        if (!card) {
            return;
        }
        card.dataset.removing = '1';

        card.querySelectorAll('button, input, select, textarea').forEach(function (el) {
            el.disabled = true;
        });

        card.classList.add('driver-trip-request-card--expired');
        window.setTimeout(function () {
            card.remove();
            var tripList = document.getElementById('driver-trip-requests-list');
            if (tripList && !tripList.querySelector('[data-trip-request-id]') && tripList.children.length === 0) {
                tripList.remove();
            }
            if (window.__driverUpdateTripDockBadge) {
                window.__driverUpdateTripDockBadge();
            }
        }, 280);
    }

    /** Nhận message từ service worker để ẩn offer hết hạn trên tab đang mở. */
    function bindServiceWorkerTripRequestSync() {
        if (!('serviceWorker' in navigator)) {
            return;
        }

        navigator.serviceWorker.addEventListener('message', function (event) {
            var payload = event && event.data ? event.data.payload : null;
            var data = payload && payload.data ? payload.data : null;
            if (!data || data.client_event !== 'driver_trip_request_expired') {
                return;
            }

            removeRequestCardById(data.driver_request_id);
        });
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
            title: form.getAttribute('data-reason-title') || 'Lý do hủy cuốc',
            hint: form.getAttribute('data-reason-hint') || 'Chọn lý do để quản lý nắm thông tin và hỗ trợ khách.',
        }).then(function (result) {
            if (!result || !result.reasonId) {
                return null;
            }
            ensureReasonInput(form, result.reasonId);
            return result.reasonId;
        });
    }

    function postForm(form, submitBtn) {
        if (submitBtn) {
            submitBtn.disabled = true;
        }
        form.dataset.tripActionSubmitting = '1';
        setActionBusy(true);

        return fetch(form.action, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: new FormData(form),
            credentials: 'same-origin',
        })
            .then(function (r) {
                return r.text().then(function (text) {
                    var data = {};
                    if (text) {
                        try {
                            data = JSON.parse(text);
                        } catch (e) {
                            data = {};
                        }
                    }

                    return { ok: r.ok, data: data, status: r.status };
                });
            });
    }

    function sendReject(form, submitBtn) {
        postForm(form, submitBtn)
            .then(function (result) {
                if (!result.ok) {
                    var message = result.data && result.data.message
                        ? result.data.message
                        : (result.status === 419
                            ? 'Phiên đăng nhập hết hạn — tải lại trang rồi thử lại.'
                            : 'Không từ chối được cuốc.');
                    throw new Error(message);
                }
                removeRequestCard(form);
                if (result.data && result.data.message && window.AppFlash && window.AppFlash.show) {
                    window.AppFlash.show(result.data.message, {
                        variant: 'success',
                        title: 'Đã từ chối',
                    });
                }
            })
            .catch(function (err) {
                form.dataset.tripActionSubmitting = '';
                setActionBusy(false);
                if (submitBtn) {
                    submitBtn.disabled = false;
                }
                if (window.AppFlash && window.AppFlash.show) {
                    window.AppFlash.show(err.message || 'Không từ chối được cuốc.', { variant: 'danger', title: 'Không từ chối được cuốc' });
                } else if (window.AppDialog && window.AppDialog.alert) {
                    window.AppDialog.alert(err.message || 'Không từ chối được cuốc.', { variant: 'danger' });
                }
            })
            .finally(function () {
                setActionBusy(false);
            });
    }

    function sendAccept(form, submitBtn) {
        postForm(form, submitBtn)
            .then(function (result) {
                if (!result.ok) {
                    var message = result.data && result.data.message
                        ? result.data.message
                        : (result.status === 419
                            ? 'Phiên đăng nhập hết hạn — tải lại trang rồi thử lại.'
                            : 'Không nhận được cuốc.');
                    throw new Error(message);
                }

                var redirect = (result.data && result.data.redirect)
                    || (window.__driverDashboardUrl || '/driver/dashboard?tab=trips');
                window.location.assign(redirect);
            })
            .catch(function (err) {
                form.dataset.tripActionSubmitting = '';
                setActionBusy(false);
                if (submitBtn) {
                    submitBtn.disabled = false;
                }
                if (window.AppFlash && window.AppFlash.show) {
                    window.AppFlash.show(err.message || 'Không nhận được cuốc.', { variant: 'danger', title: 'Không nhận được cuốc' });
                } else if (window.AppDialog && window.AppDialog.alert) {
                    window.AppDialog.alert(err.message || 'Không nhận được cuốc.', { variant: 'danger' });
                }
            });
    }

    function confirmAction(form, submitBtn, onConfirm) {
        var message = form.getAttribute('data-confirm');
        if (!message) {
            onConfirm();
            return;
        }

        setActionBusy(true);

        var options = {
            title: form.getAttribute('data-confirm-title') || 'Xác nhận',
            message: message,
            confirmText: form.getAttribute('data-confirm-ok') || 'Xác nhận',
            cancelText: form.getAttribute('data-confirm-cancel') || 'Huỷ',
            variant: form.getAttribute('data-confirm-variant') || 'primary',
        };

        var done = function () {
            setActionBusy(false);
        };

        if (window.AppDialog && window.AppDialog.confirm) {
            window.AppDialog.confirm(options).then(function (ok) {
                if (ok) {
                    onConfirm();
                    return;
                }
                done();
            });
            return;
        }

        if (window.confirm(message)) {
            onConfirm();
        } else {
            done();
        }
    }

    function handleTripAction(form, submitBtn) {
        if (!form || form.dataset.tripActionSubmitting === '1') {
            return;
        }

        if (isRejectForm(form)) {
            pickCancelReason(form).then(function (reasonId) {
                if (!reasonId) {
                    return;
                }
                sendReject(form, submitBtn);
            }).catch(function (err) {
                if (window.AppFlash && window.AppFlash.show) {
                    window.AppFlash.show(err.message || 'Không tải được lý do hủy.', {
                        variant: 'danger',
                        title: 'Không tải được lý do hủy',
                    });
                } else if (window.AppDialog && window.AppDialog.alert) {
                    window.AppDialog.alert(err.message || 'Không tải được lý do hủy.', { variant: 'danger' });
                }
            });
            return;
        }

        if (isAcceptForm(form)) {
            confirmAction(form, submitBtn, function () {
                sendAccept(form, submitBtn);
            });
        }
    }

    function bindClick(selector) {
        document.addEventListener('click', function (event) {
            var submitBtn = event.target.closest(selector);
            if (!submitBtn) {
                return;
            }

            var form = submitBtn.closest('form');
            if (!isAcceptForm(form) && !isRejectForm(form)) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();

            handleTripAction(form, submitBtn);
        }, true);
    }

    bindClick('.driver-accept-form button[type="submit"]');
    bindClick('.driver-reject-form button[type="submit"]');
    bindServiceWorkerTripRequestSync();

    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement) || (!isAcceptForm(form) && !isRejectForm(form))) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();

        if (form.dataset.tripActionSubmitting === '1') {
            return;
        }

        handleTripAction(form, form.querySelector('[type="submit"]'));
    }, true);
})();
