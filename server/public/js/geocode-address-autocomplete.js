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
        var lockedDetailValue = coordsLocked ? String(detailEl.value || '').trim() : '';
        var suppressSuggestUntilType = coordsLocked;

        function lockCoords(addressText) {
            coordsLocked = true;
            suppressSuggestUntilType = true;
            lockedDetailValue = String(addressText || detailEl.value || '').trim();
            detailEl.setAttribute('data-address-locked', '1');
            hideSuggest();
        }

        function clearCoords() {
            if (latEl) {
                latEl.value = '';
            }
            if (lngEl) {
                lngEl.value = '';
            }
            coordsLocked = false;
            lockedDetailValue = '';
            detailEl.removeAttribute('data-address-locked');
        }

        function showSuggest() {
            if (coordsLocked || suppressSuggestUntilType) {
                return;
            }
            fieldWrap.classList.add('geocode-suggest-open');
            suggestEl.classList.remove('d-none');
        }

        function hideSuggest() {
            suggestEl.innerHTML = '';
            suggestEl.classList.add('d-none');
            fieldWrap.classList.remove('geocode-suggest-open');
        }

        function allowTypingSearch(event, query) {
            if (suppressSuggestUntilType) {
                return false;
            }

            if (coordsLocked) {
                if (!event.isTrusted) {
                    return false;
                }
                if (query === lockedDetailValue) {
                    return false;
                }
                clearCoords();
            }

            return true;
        }

        function runSuggestSearch(query) {
            if (coordsLocked || suppressSuggestUntilType || query.length < 2) {
                hideSuggest();
                return;
            }

            if (timer) {
                window.clearTimeout(timer);
            }

            timer = window.setTimeout(function () {
                if (searchAbort) {
                    searchAbort.abort();
                }
                searchAbort = new AbortController();

                if (window.GeocodeSearchUi && window.GeocodeSearchUi.setLoading) {
                    window.GeocodeSearchUi.setLoading(suggestEl, 'Đang tìm…');
                    showSuggest();
                }

                var url = searchUrl
                    + '?q=' + encodeURIComponent(query)
                    + '&province=' + encodeURIComponent(provinceName(options.provinceInputId, options.defaultProvince || ''));

                fetch(url, {
                    signal: searchAbort.signal,
                    headers: { Accept: 'application/json' },
                })
                    .then(function (r) { return r.ok ? r.json() : { results: [] }; })
                    .then(function (data) {
                        if (coordsLocked || suppressSuggestUntilType) {
                            hideSuggest();
                            return;
                        }

                        var results = (data && data.results) || [];

                        if (window.GeocodeSearchUi && window.GeocodeSearchUi.renderResults) {
                            window.GeocodeSearchUi.renderResults(suggestEl, results, query, {
                                itemClass: 'booking-address-suggest-item geocode-search-item',
                                emptyClass: 'booking-address-suggest-empty',
                                emptyText: 'Không thấy địa chỉ phù hợp — thử thêm quận/huyện hoặc chọn trên bản đồ.',
                                onSelect: function (item) {
                                    applySuggestion(item);
                                },
                            });
                            showSuggest();
                            return;
                        }

                        suggestEl.innerHTML = '';

                        if (!results.length) {
                            var empty = document.createElement('div');
                            empty.className = 'booking-address-suggest-empty';
                            empty.textContent = 'Không thấy địa chỉ phù hợp — thử thêm quận/huyện hoặc chọn trên bản đồ.';
                            suggestEl.appendChild(empty);
                            showSuggest();
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
                        showSuggest();
                    })
                    .catch(function () {});
            }, 400);
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
            lockCoords(item.address);

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

        detailEl.addEventListener('keydown', function (e) {
            if (e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) {
                suppressSuggestUntilType = false;
            }
            if (coordsLocked && e.key !== 'Escape' && e.key !== 'Tab') {
                hideSuggest();
            }
            if (window.GeocodeSearchUi
                && window.GeocodeSearchUi.handleListKeydown
                && window.GeocodeSearchUi.handleListKeydown(e, suggestEl)) {
                return;
            }
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

        detailEl.addEventListener('input', function (e) {
            var q = detailEl.value.trim();

            if (!allowTypingSearch(e, q)) {
                hideSuggest();
                return;
            }

            runSuggestSearch(q);
        });

        detailEl.addEventListener('focus', function () {
            hideSuggest();
        });

        detailEl.addEventListener('blur', function () {
            window.setTimeout(hideSuggest, 150);
        });

        document.addEventListener('addressmap:applied', function (e) {
            if (e.detail && e.detail.targetInputId === options.detailInputId) {
                lockCoords(e.detail.address || detailEl.value);
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

