/**
 * Gợi ý địa chỉ khi gõ — dùng chung khách đặt xe & tài xế cập nhật vị trí.
 */
(function () {
    var searchUrl = window.__geocodeSearchUrl || '';

    function provinceName(provinceInputId, defaultProvince) {
        var provinceEl = provinceInputId ? document.getElementById(provinceInputId) : null;
        var fromInput = provinceEl ? String(provinceEl.value || '').trim() : '';
        return fromInput || defaultProvince || '';
    }

    function dispatchApplied(detail) {
        document.dispatchEvent(new CustomEvent('addressmap:applied', {
            bubbles: true,
            detail: detail,
        }));
    }

    function attach(options) {
        if (!searchUrl || !options || !options.detailInputId) {
            return;
        }

        var detailEl = document.getElementById(options.detailInputId);
        if (!detailEl) {
            return;
        }

        var latEl = options.latInputId ? document.getElementById(options.latInputId) : null;
        var lngEl = options.lngInputId ? document.getElementById(options.lngInputId) : null;
        var fieldWrap = detailEl.closest('.booking-address-field')
            || detailEl.closest('.driver-location-input-wrap')
            || detailEl.parentElement;

        if (!fieldWrap) {
            return;
        }

        if (window.getComputedStyle(fieldWrap).position === 'static') {
            fieldWrap.classList.add('geocode-address-field');
        }

        var suggestEl = document.createElement('div');
        suggestEl.className = 'booking-address-suggest d-none';
        suggestEl.setAttribute('role', 'listbox');
        fieldWrap.appendChild(suggestEl);

        var timer = null;
        var searchAbort = null;
        var coordsLocked = !!(latEl && latEl.value && lngEl && lngEl.value);

        function clearCoords() {
            if (latEl) {
                latEl.value = '';
            }
            if (lngEl) {
                lngEl.value = '';
            }
            coordsLocked = false;
        }

        function hideSuggest() {
            suggestEl.innerHTML = '';
            suggestEl.classList.add('d-none');
        }

        function applySuggestion(item) {
            detailEl.value = item.address;
            if (latEl && item.lat != null) {
                latEl.value = String(item.lat);
            }
            if (lngEl && item.lon != null) {
                lngEl.value = String(item.lon);
                lngEl.dispatchEvent(new Event('change', { bubbles: true }));
            }
            coordsLocked = true;
            hideSuggest();

            dispatchApplied({
                targetInputId: options.detailInputId,
                latInputId: options.latInputId || null,
                lngInputId: options.lngInputId || null,
                lat: item.lat != null ? Number(item.lat) : null,
                lng: item.lon != null ? Number(item.lon) : null,
                address: item.address,
            });

            if (typeof options.onSelect === 'function') {
                options.onSelect(item);
            }
        }

        detailEl.addEventListener('input', function () {
            if (!coordsLocked) {
                clearCoords();
            }
            coordsLocked = false;

            var q = detailEl.value.trim();
            if (timer) {
                window.clearTimeout(timer);
            }
            if (q.length < 2) {
                hideSuggest();
                return;
            }

            timer = window.setTimeout(function () {
                if (searchAbort) {
                    searchAbort.abort();
                }
                searchAbort = new AbortController();

                var url = searchUrl
                    + '?q=' + encodeURIComponent(q)
                    + '&province=' + encodeURIComponent(provinceName(options.provinceInputId, options.defaultProvince || ''));

                fetch(url, {
                    signal: searchAbort.signal,
                    headers: { Accept: 'application/json' },
                })
                    .then(function (r) { return r.ok ? r.json() : { results: [] }; })
                    .then(function (data) {
                        var results = (data && data.results) || [];
                        suggestEl.innerHTML = '';

                        if (!results.length) {
                            var empty = document.createElement('div');
                            empty.className = 'booking-address-suggest-empty';
                            empty.textContent = 'Không thấy địa chỉ phù hợp — thử thêm quận/huyện hoặc chọn trên bản đồ.';
                            suggestEl.appendChild(empty);
                            suggestEl.classList.remove('d-none');
                            return;
                        }

                        results.forEach(function (item) {
                            var btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'booking-address-suggest-item';
                            btn.textContent = item.address;
                            btn.addEventListener('click', function () {
                                applySuggestion(item);
                            });
                            suggestEl.appendChild(btn);
                        });
                        suggestEl.classList.remove('d-none');
                    })
                    .catch(function () {});
            }, 400);
        });

        detailEl.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                var first = suggestEl.querySelector('.booking-address-suggest-item');
                if (first) {
                    e.preventDefault();
                    first.click();
                }
            }
            if (e.key === 'Escape') {
                hideSuggest();
            }
        });

        document.addEventListener('addressmap:applied', function (e) {
            if (e.detail && e.detail.targetInputId === options.detailInputId) {
                coordsLocked = true;
                hideSuggest();
            }
        });

        document.addEventListener('click', function (e) {
            if (!fieldWrap.contains(e.target)) {
                hideSuggest();
            }
        });
    }

    window.GeocodeAddressAutocomplete = { attach: attach };
})();
