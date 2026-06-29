(function () {
    var quoteUrl = window.tripOfferQuoteUrl || '';
    var departureEl = document.getElementById('offer-departure');
    var destinationEl = document.getElementById('offer-destination');
    var seatsEl = document.getElementById('offer-seats');
    var distanceEl = document.getElementById('offer-distance-km');
    var rateHintEl = document.getElementById('offer-rate-hint');
    var previewEl = document.getElementById('offer-price-preview');
    var priceFields = {
        whole_car_one_way: document.getElementById('offer-whole-car-one-way'),
        seat_one_way: document.getElementById('offer-seat-one-way'),
        whole_car_round: document.getElementById('offer-whole-car-round'),
        seat_round: document.getElementById('offer-seat-round'),
    };
    var fetchTimer = null;
    var lastQuote = null;
    var distanceTouched = false;

    function formatMoney(value) {
        return Number(value || 0).toLocaleString('vi-VN');
    }

    function hasRoute() {
        return departureEl && destinationEl
            && departureEl.value
            && destinationEl.value
            && departureEl.value !== destinationEl.value;
    }

    function parsedDistanceKm() {
        if (!distanceEl) {
            return 0;
        }
        var km = parseInt(String(distanceEl.value || '').trim(), 10);
        return Number.isFinite(km) && km > 0 ? km : 0;
    }

    function setPriceFields(data) {
        if (!data) {
            return;
        }
        Object.keys(priceFields).forEach(function (key) {
            var el = priceFields[key];
            if (el && data[key] !== undefined) {
                el.value = data[key];
            }
        });
    }

    function clearPricing() {
        lastQuote = null;
        if (distanceEl) {
            distanceEl.value = '';
        }
        if (rateHintEl) {
            rateHintEl.textContent = 'Có thể sửa km — giá sẽ tự tính lại.';
        }
        if (previewEl) {
            previewEl.innerHTML = '';
            previewEl.classList.add('d-none');
        }
    }

    function updateRateHint(data) {
        if (!rateHintEl) {
            return;
        }
        if (data && data.distance_km > 0 && data.rate_per_km) {
            rateHintEl.textContent = 'Đơn giá: ' + formatMoney(data.rate_per_km) + ' đ/km';
            return;
        }
        if (hasRoute()) {
            rateHintEl.textContent = 'Chưa có quãng đường cố định — nhập km thủ công hoặc nhờ admin cấu hình.';
            return;
        }
        rateHintEl.textContent = 'Có thể sửa km — giá sẽ tự tính lại.';
    }

    function renderPreview(data) {
        if (!previewEl || !data || !data.prices_by_seats) {
            return;
        }

        var rows = Object.keys(data.prices_by_seats).map(function (cap) {
            var row = data.prices_by_seats[cap];
            return '<tr data-capacity="' + cap + '">' +
                '<td>' + cap + ' chỗ</td>' +
                '<td class="text-end">' + formatMoney(row.whole_car_one_way) + ' đ</td>' +
                '<td class="text-end">' + formatMoney(row.seat_one_way) + ' đ</td>' +
                '<td class="text-end">' + formatMoney(row.whole_car_round) + ' đ</td>' +
                '<td class="text-end">' + formatMoney(row.seat_round) + ' đ</td>' +
                '</tr>';
        }).join('');

        previewEl.innerHTML =
            '<div class="small text-muted mb-2">Bảng giá gợi ý theo loại xe — chọn số chỗ để áp dụng vào form bên dưới.</div>' +
            '<div class="table-responsive">' +
            '<table class="table table-sm table-bordered mb-0">' +
            '<thead><tr>' +
            '<th>Loại xe</th>' +
            '<th class="text-end">Cả xe 1 chiều</th>' +
            '<th class="text-end">Ghế 1 chiều</th>' +
            '<th class="text-end">Cả xe khứ hồi</th>' +
            '<th class="text-end">Ghế khứ hồi</th>' +
            '</tr></thead><tbody>' + rows + '</tbody></table></div>';
        previewEl.classList.remove('d-none');

        if (seatsEl && seatsEl.value) {
            highlightSelectedRow(seatsEl.value);
        }
    }

    function highlightSelectedRow(capacity) {
        if (!previewEl) {
            return;
        }
        previewEl.querySelectorAll('tbody tr').forEach(function (row) {
            row.classList.toggle('table-active', row.getAttribute('data-capacity') === String(capacity));
        });
    }

    function applySelectedSeatPrices() {
        if (!lastQuote || !seatsEl || !seatsEl.value) {
            return;
        }
        var selected = lastQuote.prices_by_seats[String(seatsEl.value)];
        if (selected) {
            setPriceFields(selected);
            highlightSelectedRow(seatsEl.value);
        }
    }

    function applyQuoteData(data, options) {
        options = options || {};
        lastQuote = data;
        if (options.updateDistance && distanceEl && data.distance_km) {
            distanceEl.value = data.distance_km;
        }
        updateRateHint(data);
        renderPreview(data);
        applySelectedSeatPrices();
        if ((!seatsEl || !seatsEl.value) && data.whole_car_one_way) {
            setPriceFields(data);
        }
    }

    function scheduleQuote() {
        window.clearTimeout(fetchTimer);
        fetchTimer = window.setTimeout(function () {
            var km = parsedDistanceKm();
            if (distanceTouched && km > 0) {
                fetchQuoteByDistance(km);
                return;
            }
            if (hasRoute()) {
                fetchQuoteByRoute();
                return;
            }
            if (km > 0) {
                fetchQuoteByDistance(km);
                return;
            }
            clearPricing();
        }, 200);
    }

    function buildQuoteParams() {
        var params = new URLSearchParams();
        if (seatsEl && seatsEl.value) {
            params.set('seats', seatsEl.value);
        }
        return params;
    }

    function fetchQuoteByRoute() {
        if (!quoteUrl || !hasRoute()) {
            return;
        }

        var params = buildQuoteParams();
        params.set('departure', departureEl.value);
        params.set('destination', destinationEl.value);

        fetch(quoteUrl + '?' + params.toString(), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('quote failed');
                }
                return res.json();
            })
            .then(function (data) {
                applyQuoteData(data, { updateDistance: true });
            })
            .catch(function () {
                var km = parsedDistanceKm();
                if (km > 0) {
                    fetchQuoteByDistance(km);
                }
            });
    }

    function fetchQuoteByDistance(km) {
        if (!quoteUrl || !km) {
            return;
        }

        var params = buildQuoteParams();
        params.set('distance_km', String(km));

        fetch(quoteUrl + '?' + params.toString(), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('quote failed');
                }
                return res.json();
            })
            .then(function (data) {
                applyQuoteData(data, { updateDistance: false });
            })
            .catch(function () {
                if (rateHintEl) {
                    rateHintEl.textContent = 'Không tính được giá — kiểm tra lại số km.';
                }
            });
    }

    [departureEl, destinationEl].forEach(function (el) {
        if (el) {
            el.addEventListener('change', function () {
                distanceTouched = false;
                scheduleQuote();
            });
        }
    });

    if (distanceEl) {
        distanceEl.addEventListener('input', function () {
            distanceTouched = true;
            scheduleQuote();
        });
        distanceEl.addEventListener('change', function () {
            distanceTouched = true;
            scheduleQuote();
        });
    }

    if (seatsEl) {
        seatsEl.addEventListener('change', function () {
            if (lastQuote) {
                applySelectedSeatPrices();
                return;
            }
            scheduleQuote();
        });
    }

    if (parsedDistanceKm() > 0) {
        fetchQuoteByDistance(parsedDistanceKm());
    } else if (hasRoute()) {
        fetchQuoteByRoute();
    }
})();
