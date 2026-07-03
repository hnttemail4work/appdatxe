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

    function syncOffDuty(message) {
        document.dispatchEvent(new CustomEvent('driver:availability-sync', {
            detail: {
                availability: 'off_duty',
                message: message || 'Đã từ chối cuốc — bật Hoạt động lại khi muốn nhận chuyến.',
            },
        }));
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
                if (form.closest('[data-trip-request-id]')) {
                    syncOffDuty(result.data && result.data.message);
                }
            })
            .catch(function (err) {
                form.dataset.tripActionSubmitting = '';
                setActionBusy(false);
                if (submitBtn) {
                    submitBtn.disabled = false;
                }
                if (window.AppDialog && window.AppDialog.alert) {
                    window.AppDialog.alert(err.message || 'Không từ chối được cuốc.', { variant: 'danger' });
                } else {
                    window.alert(err.message || 'Không từ chối được cuốc.');
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
                if (window.AppDialog && window.AppDialog.alert) {
                    window.AppDialog.alert(err.message || 'Không nhận được cuốc.', { variant: 'danger' });
                } else {
                    window.alert(err.message || 'Không nhận được cuốc.');
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
            confirmAction(form, submitBtn, function () {
                sendReject(form, submitBtn);
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
