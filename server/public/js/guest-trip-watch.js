(function () {
    var root = document.getElementById('guest-trip-watch-root');
    var listEl = document.getElementById('guest-trip-watch-list');
    var template = document.getElementById('guest-trip-watch-card-template');
    var watchUrl = window.__guestTripWatchUrl;
    var reviewUrl = window.__guestTripReviewUrl;
    var cancelUrl = window.__guestTripCancelUrl;

    if (!root || !listEl || !template || !watchUrl) {
        return;
    }

    var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    var pollTimer = null;
    var reloadTimer = null;
    var REFRESH_MS = Number(window.__guestTripReloadMs) > 0 ? Number(window.__guestTripReloadMs) : 180000;

    function hasSearchingTrip(trips) {
        return trips.some(function (trip) {
            return trip.progress === 'searching_driver' || trip.driver_pending === true;
        });
    }

    function clearReloadTimer() {
        if (reloadTimer) {
            window.clearInterval(reloadTimer);
            reloadTimer = null;
        }
    }

    function clearPollTimer() {
        if (pollTimer) {
            window.clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    function startReloadLoop() {
        clearReloadTimer();
        reloadTimer = window.setInterval(function () {
            window.location.reload();
        }, REFRESH_MS);
    }

    function syncAutoRefresh(trips) {
        clearReloadTimer();
        clearPollTimer();

        if (!trips.length) {
            return;
        }

        if (hasSearchingTrip(trips)) {
            startReloadLoop();
            return;
        }

        pollTimer = window.setInterval(loadTrips, REFRESH_MS);
    }

    function progressHtml(activeKey) {
        var steps = [
            { key: 'booked', label: 'Đã đặt' },
            { key: 'searching_driver', label: 'Đang tìm tài xế' },
            { key: 'driver_assigned', label: 'Đã có tài xế' },
            { key: 'running', label: 'Đang chạy' },
            { key: 'completed', label: 'Hoàn thành' },
        ];
        var order = { booked: 0, searching_driver: 1, driver_assigned: 2, running: 3, completed: 4 };
        var activeIdx = order[activeKey] ?? 0;
        var html = '<div class="guest-trip-progress-track">';
        steps.forEach(function (step, idx) {
            var state = idx < activeIdx ? 'done' : (idx === activeIdx ? 'active' : 'pending');
            html += '<div class="guest-trip-progress-step guest-trip-progress-step--' + state + '">';
            html += '<span class="guest-trip-progress-dot" aria-hidden="true"></span>';
            html += '<span class="guest-trip-progress-label">' + step.label + '</span>';
            html += '</div>';
            if (idx < steps.length - 1) {
                html += '<div class="guest-trip-progress-line guest-trip-progress-line--' + (idx < activeIdx ? 'done' : 'pending') + '" aria-hidden="true"></div>';
            }
        });
        html += '</div>';
        return html;
    }

    function bindCard(card, trip) {
        var sentiment = null;
        var submitBtn = card.querySelector('.guest-review-submit');
        var errorEl = card.querySelector('.guest-review-error');
        var reviewForm = card.querySelector('[data-field="review_form"]');
        var thanksEl = card.querySelector('[data-field="thanks"]');
        var sentimentBtns = card.querySelectorAll('.guest-sentiment-btn');

        function setSentiment(value) {
            sentiment = value;
            sentimentBtns.forEach(function (btn) {
                var active = btn.getAttribute('data-sentiment') === value;
                btn.classList.toggle('active', active);
                btn.classList.toggle('btn-success', active && value === 'like');
                btn.classList.toggle('btn-danger', active && value === 'dislike');
                btn.classList.toggle('btn-outline-success', !active && btn.getAttribute('data-sentiment') === 'like');
                btn.classList.toggle('btn-outline-danger', !active && btn.getAttribute('data-sentiment') === 'dislike');
            });
            if (submitBtn) {
                submitBtn.disabled = !sentiment;
            }
        }

        sentimentBtns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                setSentiment(btn.getAttribute('data-sentiment'));
            });
        });

        if (submitBtn) {
            submitBtn.addEventListener('click', function () {
                if (!sentiment || !reviewUrl) return;
                submitBtn.disabled = true;
                if (errorEl) {
                    errorEl.classList.add('d-none');
                    errorEl.textContent = '';
                }

                fetch(reviewUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        booking_ref: trip.booking_ref,
                        contact_phone: trip.contact_phone,
                        sentiment: sentiment,
                        comment: card.querySelector('.guest-review-comment')?.value || '',
                    }),
                })
                    .then(function (res) {
                        return res.json().then(function (data) {
                            return { ok: res.ok, data: data };
                        });
                    })
                    .then(function (result) {
                        if (!result.ok) {
                            throw new Error(result.data?.message || 'Không gửi được phản hồi.');
                        }
                        if (reviewForm) reviewForm.classList.add('d-none');
                        if (thanksEl) thanksEl.classList.remove('d-none');
                        window.setTimeout(function () {
                            card.classList.add('guest-trip-card--fade-out');
                            window.setTimeout(function () {
                                card.remove();
                                loadTrips();
                            }, 400);
                        }, 2500);
                    })
                    .catch(function (err) {
                        if (errorEl) {
                            errorEl.textContent = err.message || 'Lỗi gửi phản hồi.';
                            errorEl.classList.remove('d-none');
                        }
                        submitBtn.disabled = !sentiment;
                    });
            });
        }

        var cancelBtn = card.querySelector('.guest-trip-cancel-btn');
        if (cancelBtn && cancelUrl) {
            cancelBtn.addEventListener('click', function () {
                var confirmCancel = window.AppDialog && window.AppDialog.confirm
                    ? window.AppDialog.confirm({
                        title: 'Hủy chuyến',
                        message: 'Bạn chắc chắn muốn hủy chuyến này?',
                        confirmText: 'Hủy chuyến',
                        cancelText: 'Không',
                        variant: 'danger',
                    })
                    : Promise.resolve(window.confirm('Bạn chắc chắn muốn hủy chuyến này?'));

                confirmCancel.then(function (ok) {
                    if (!ok) {
                        return;
                    }

                    function submitCancel(reasonId) {
                        cancelBtn.disabled = true;
                        var payload = {
                            booking_ref: trip.booking_ref,
                            contact_phone: trip.contact_phone,
                        };
                        if (reasonId) {
                            payload.cancellation_reason_id = reasonId;
                        }
                        fetch(cancelUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: JSON.stringify(payload),
                        })
                            .then(function (res) {
                                return res.json().then(function (data) {
                                    return { ok: res.ok, data: data };
                                });
                            })
                            .then(function (result) {
                                if (!result.ok) {
                                    throw new Error(result.data?.message || 'Không hủy được chuyến.');
                                }
                                if (window.CancellationReasonModal && window.CancellationReasonModal.clearCache) {
                                    window.CancellationReasonModal.clearCache();
                                }
                                card.classList.add('guest-trip-card--fade-out');
                                window.setTimeout(function () {
                                    card.remove();
                                    loadTrips();
                                }, 400);
                            })
                            .catch(function (err) {
                                if (window.AppDialog && window.AppDialog.alert) {
                                    window.AppDialog.alert(err.message || 'Không hủy được chuyến.', { variant: 'danger' });
                                } else {
                                    window.alert(err.message || 'Không hủy được chuyến.');
                                }
                                cancelBtn.disabled = false;
                            });
                    }

                    if (window.CancellationReasonModal && window.CancellationReasonModal.pick) {
                        window.CancellationReasonModal.pick({
                            audience: 'customer',
                            contactPhone: trip.contact_phone,
                            title: 'Lý do hủy chuyến',
                            hint: trip.requires_cancel_reason
                                ? 'Bạn đã hủy nhiều lần — vui lòng chọn lý do trước khi hủy.'
                                : 'Vui lòng chọn lý do hủy chuyến.',
                        }).then(function (reasonResult) {
                            if (!reasonResult) {
                                return;
                            }
                            if (reasonResult.skipped) {
                                submitCancel(null);
                                return;
                            }
                            submitCancel(reasonResult.reasonId);
                        }).catch(function (err) {
                            if (window.AppDialog && window.AppDialog.alert) {
                                window.AppDialog.alert(err.message || 'Không tải được lý do hủy.', { variant: 'danger' });
                            }
                        });
                    } else {
                        submitCancel(null);
                    }
                });
            });
        }
    }

    function renderCard(trip) {
        var node = template.content.cloneNode(true);
        var card = node.querySelector('.guest-trip-card');
        card.dataset.bookingRef = trip.booking_ref;

        card.querySelector('[data-field="trip_code"]').textContent = trip.trip_code || '—';
        card.querySelector('[data-field="route"]').textContent = trip.route || '';
        card.querySelector('[data-field="service_date"]').textContent = trip.service_date ? 'Khởi hành: ' + trip.service_date : '';

        var driverEl = card.querySelector('[data-field="driver_name"]');
        var vehicleEl = card.querySelector('[data-field="vehicle_info"]');
        if (trip.driver_pending) {
            driverEl.textContent = 'Đang tìm kiếm tài xế';
            driverEl.classList.add('text-muted');
            if (vehicleEl) {
                vehicleEl.classList.add('d-none');
                vehicleEl.textContent = '';
            }
        } else {
            driverEl.textContent = trip.driver_name || '—';
            driverEl.classList.remove('text-muted');
            if (vehicleEl && (trip.vehicle_type || trip.vehicle_plate || trip.vehicle_seats)) {
                var parts = [];
                if (trip.vehicle_type) parts.push(trip.vehicle_type);
                if (trip.vehicle_seats) parts.push(trip.vehicle_seats + ' chỗ');
                if (trip.vehicle_plate) parts.push(trip.vehicle_plate);
                vehicleEl.textContent = parts.join(' · ');
                vehicleEl.classList.remove('d-none');
            } else if (vehicleEl) {
                vehicleEl.classList.add('d-none');
            }
        }

        card.querySelector('[data-field="progress_steps"]').innerHTML = progressHtml(trip.progress);

        var reviewForm = card.querySelector('[data-field="review_form"]');
        if (trip.can_review && reviewForm) {
            reviewForm.classList.remove('d-none');
        }

        var cancelWrap = card.querySelector('[data-field="cancel_wrap"]');
        if (cancelWrap) {
            if (trip.can_cancel) {
                cancelWrap.classList.remove('d-none');
            } else {
                cancelWrap.classList.add('d-none');
            }
        }

        bindCard(card, trip);
        return card;
    }

    function loadTrips() {
        fetch(watchUrl, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            cache: 'no-store',
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                var trips = Array.isArray(data.trips) ? data.trips : [];
                listEl.innerHTML = '';
                if (!trips.length) {
                    root.classList.add('d-none');
                    clearReloadTimer();
                    clearPollTimer();
                    return;
                }
                root.classList.remove('d-none');
                trips.forEach(function (trip) {
                    listEl.appendChild(renderCard(trip));
                });
                syncAutoRefresh(trips);
            })
            .catch(function () {
                /* Giữ section ẩn nếu lỗi mạng — không ảnh hưởng đặt vé */
            });
    }

    loadTrips();

    if (window.__guestTripSearchingReload) {
        startReloadLoop();
    }

    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) {
            loadTrips();
        }
    });
})();
