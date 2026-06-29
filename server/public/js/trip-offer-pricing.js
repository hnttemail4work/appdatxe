(function () {
    var quoteUrl = window.tripOfferQuoteUrl || '';
    var departureEl = document.getElementById('offer-departure');
    var destinationEl = document.getElementById('offer-destination');
    var seatsEl = document.getElementById('offer-seats');
    var distanceEl = document.getElementById('offer-distance-km');
    var rateHintEl = document.getElementById('offer-rate-hint');
    var priceFields = {
        whole_car_one_way: document.getElementById('offer-whole-car-one-way'),
        seat_one_way: document.getElementById('offer-seat-one-way'),
        whole_car_round: document.getElementById('offer-whole-car-round'),
        seat_round: document.getElementById('offer-seat-round'),
    };
    var fetchTimer = null;

    function canQuote() {
        return quoteUrl
            && departureEl && departureEl.value
            && destinationEl && destinationEl.value
            && departureEl.value !== destinationEl.value
            && seatsEl && seatsEl.value;
    }

    function setPriceFields(data) {
        Object.keys(priceFields).forEach(function (key) {
            var el = priceFields[key];
            if (el && data[key] !== undefined) {
                el.value = data[key];
            }
        });
    }

    function clearPricing() {
        if (distanceEl) {
            distanceEl.value = '';
        }
        if (rateHintEl) {
            rateHintEl.textContent = '';
        }
    }

    function scheduleQuote() {
        if (!canQuote()) {
            clearPricing();
            return;
        }
        window.clearTimeout(fetchTimer);
        fetchTimer = window.setTimeout(fetchQuote, 200);
    }

    function fetchQuote() {
        if (!canQuote()) {
            return;
        }
        var params = new URLSearchParams({
            departure: departureEl.value,
            destination: destinationEl.value,
            seats: seatsEl.value,
        });
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
                if (distanceEl) {
                    distanceEl.value = data.distance_km || '';
                }
                if (rateHintEl && data.rate_per_km) {
                    rateHintEl.textContent = 'Đơn giá: ' + Number(data.rate_per_km).toLocaleString('vi-VN') + ' đ/km';
                }
                setPriceFields(data);
            })
            .catch(function () {
                clearPricing();
            });
    }

    [departureEl, destinationEl, seatsEl].forEach(function (el) {
        if (el) {
            el.addEventListener('change', scheduleQuote);
        }
    });

    if (canQuote()) {
        fetchQuote();
    }
})();
