/**
 * Map picker — search + map pin, preview selection, confirm to apply.
 */
(function () {
    var GOONG_JS_CSS = 'https://cdn.jsdelivr.net/npm/@goongmaps/goong-js@1.0.9/dist/goong-js.css';
    var GOONG_JS = 'https://cdn.jsdelivr.net/npm/@goongmaps/goong-js@1.0.9/dist/goong-js.js';
    var goongMaptilesKey = String(window.__goongMaptilesKey || '').trim();

    var PROVINCE_CENTERS = (function () {
        var raw = window.__provinceCenters || {};
        var mapped = {};

        Object.keys(raw).forEach(function (name) {
            var point = raw[name];
            if (!point) {
                return;
            }
            if (Array.isArray(point) && point.length >= 2) {
                mapped[name] = point;
                return;
            }
            if (typeof point.lat === 'number' && typeof point.lng === 'number') {
                mapped[name] = [point.lat, point.lng];
            }
        });

        if (Object.keys(mapped).length) {
            return mapped;
        }

        return {
            'TP.HCM': [10.7769, 106.7009],
        };
    })();

    var modalEl = document.getElementById('addressMapPickerModal');
    if (!modalEl || typeof bootstrap === 'undefined') {
        return;
    }

    var modalDialogEl = modalEl.querySelector('.modal-dialog');
    var canvasEl = document.getElementById('address-map-canvas');
    var previewEl = document.getElementById('address-map-preview');
    var titleEl = document.getElementById('addressMapPickerTitle');
    var searchInput = document.getElementById('address-map-search');
    var searchClearBtn = document.getElementById('address-map-search-clear');
    var searchResultsEl = document.getElementById('address-map-search-results');
    var confirmBtn = document.getElementById('address-map-confirm');
    var confirmStatusEl = document.getElementById('address-map-confirm-status');
    var provinceWrapEl = document.getElementById('address-map-province-wrap');
    var modalProvinceEl = document.getElementById('address-map-province');
    var driverToolbarEl = document.getElementById('address-map-driver-toolbar');
    var myLocationBtn = document.getElementById('address-map-my-location');
    var reverseUrl = window.__geocodeReverseUrl || '';
    var searchUrl = window.__geocodeSearchUrl || '';

    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    var mapInstance = null;
    var marker = null;
    var targetInputId = null;
    var provinceInputId = null;
    var sheetProvinceInputId = null;
    var defaultProvince = '';
    var pickerMode = 'default';
    var latInputId = null;
    var lngInputId = null;
    var pendingLat = null;
    var pendingLng = null;
    var pendingAddress = '';
    var pendingProvince = '';
    var reverseAbort = null;
    var searchAbort = null;
    var isResolving = false;
    var needsPinFineTune = false;
    var goongAssetsPromise = null;
    var searchTimer = null;
    var lastSearchQuery = '';
    var searchResultCache = Object.create(null);
    var provinceChangeHandler = null;

    function loadStylesheet(href, assetTag) {
        return new Promise(function (resolve, reject) {
            var link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = href;
            link.setAttribute('data-address-map-asset', assetTag);
            link.onload = function () { resolve(link); };
            link.onerror = reject;
            document.head.appendChild(link);
        });
    }

    function loadScript(src, assetTag) {
        return new Promise(function (resolve, reject) {
            var script = document.createElement('script');
            script.src = src;
            script.async = true;
            script.setAttribute('data-address-map-asset', assetTag);
            script.onload = function () { resolve(script); };
            script.onerror = reject;
            document.body.appendChild(script);
        });
    }

    function goongApi() {
        return window.goongjs || null;
    }

    function loadGoongAssets() {
        if (!goongMaptilesKey) {
            return Promise.reject(new Error('goong_maptiles_key_missing'));
        }
        if (goongApi()) {
            return Promise.resolve();
        }
        if (!goongAssetsPromise) {
            goongAssetsPromise = loadStylesheet(GOONG_JS_CSS, 'goong-js-css').then(function () {
                return loadScript(GOONG_JS, 'goong-js');
            });
        }
        return goongAssetsPromise;
    }

    function loadMapAssets() {
        return loadGoongAssets();
    }

    function unloadMapAssets() {
        goongAssetsPromise = null;
        document.querySelectorAll('[data-address-map-asset]').forEach(function (node) {
            node.remove();
        });
        if (window.goongjs) {
            try {
                delete window.goongjs;
            } catch (e) {
                window.goongjs = undefined;
            }
        }
    }

    function invalidateMapSize() {
        if (mapInstance && typeof mapInstance.resize === 'function') {
            mapInstance.resize();
        }
    }

    function mapZoomLevel(options) {
        options = options || {};
        var base = isDriverMode() ? DRIVER_FOCUS_ZOOM : PROVINCE_FOCUS_ZOOM;
        if (!mapInstance) {
            return options.fromSearch ? PICKUP_PIN_ZOOM : base;
        }
        var current = typeof mapInstance.getZoom === 'function' ? mapInstance.getZoom() : base;
        if (options.fromSearch) {
            return Math.max(current, PICKUP_PIN_ZOOM);
        }
        return Math.max(current, base);
    }

    function mapFlyTo(lat, lng, options) {
        if (!mapInstance) {
            return;
        }
        mapInstance.flyTo({
            center: [lng, lat],
            zoom: mapZoomLevel(options || {}),
        });
    }

    function isDriverMode() {
        return pickerMode === 'driver';
    }

    function activeProvinceInputId() {
        if (isDriverMode() && modalProvinceEl) {
            return 'address-map-province';
        }
        return provinceInputId;
    }

    function provinceName() {
        var id = activeProvinceInputId();
        var provinceEl = id ? document.getElementById(id) : null;
        var fromInput = provinceEl ? String(provinceEl.value || '').trim() : '';
        return fromInput || defaultProvince || '';
    }

    var PROVINCE_FOCUS_ZOOM = 14;
    var DRIVER_FOCUS_ZOOM = 17;
    var PICKUP_PIN_ZOOM = 18;
    var PIN_FINETUNE_HINT = 'Kéo ghim hoặc chạm bản đồ để chỉnh đúng cổng / lối vào, rồi bấm Xác nhận.';

    function focusZoomLevel() {
        return isDriverMode() ? DRIVER_FOCUS_ZOOM : PROVINCE_FOCUS_ZOOM;
    }

    function provinceCenter() {
        return PROVINCE_CENTERS[provinceName()] || PROVINCE_CENTERS['TP.HCM'];
    }

    function focusMapOnProvince() {
        if (!mapInstance) {
            return;
        }
        var center = provinceCenter();
        mapFlyTo(center[0], center[1], {});
    }

    function hideAddressSuggestions() {
        document.querySelectorAll('.booking-address-suggest').forEach(function (el) {
            el.classList.add('d-none');
            el.innerHTML = '';
        });
        document.querySelectorAll('.geocode-suggest-open').forEach(function (el) {
            el.classList.remove('geocode-suggest-open');
        });
    }































    function syncDriverProvinceUi(show) {
        if (provinceWrapEl) {
            provinceWrapEl.classList.toggle('d-none', !show);
        }
        if (driverToolbarEl) {
            driverToolbarEl.classList.toggle('d-none', !show);
        }
        if (modalDialogEl) {
            modalDialogEl.classList.toggle('modal-lg', !!show);
        }
        modalEl.classList.toggle('address-map-picker-modal--driver', !!show);
    }

    function syncModalProvinceFromSheet() {
        if (!isDriverMode() || !modalProvinceEl) {
            return;
        }
        var sheetProvinceEl = sheetProvinceInputId ? document.getElementById(sheetProvinceInputId) : null;
        var value = sheetProvinceEl && sheetProvinceEl.value
            ? sheetProvinceEl.value
            : (defaultProvince || 'TP.HCM');
        modalProvinceEl.value = value;
    }

    function syncSheetProvinceFromModal() {
        if (!isDriverMode() || !modalProvinceEl || !sheetProvinceInputId) {
            return;
        }
        var sheetProvinceEl = document.getElementById(sheetProvinceInputId);
        if (sheetProvinceEl && modalProvinceEl.value) {
            sheetProvinceEl.value = modalProvinceEl.value;
            sheetProvinceEl.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    function unbindProvinceChange() {
        if (!provinceChangeHandler || !modalProvinceEl) {
            provinceChangeHandler = null;
            return;
        }
        modalProvinceEl.removeEventListener('change', provinceChangeHandler);
        provinceChangeHandler = null;
    }

    function bindProvinceChange() {
        unbindProvinceChange();
        if (!isDriverMode() || !modalProvinceEl) {
            return;
        }
        provinceChangeHandler = function () {
            hideSearchResults();
            if (!mapInstance) {
                return;
            }
            if (!marker && pendingLat === null) {
                focusMapOnProvince();
            }
            var q = searchInput ? searchInput.value.trim() : '';
            if (q.length >= 2) {
                searchAddress(q);
            }
        };
        modalProvinceEl.addEventListener('change', provinceChangeHandler);
    }

    function locateMe() {
        if (!navigator.geolocation) {
            setPreview('Trình duyệt không hỗ trợ GPS — chọn thủ công trên bản đồ.', false);
            return;
        }
        if (isResolving) {
            return;
        }
        setPreview('Đang lấy vị trí GPS...', true);
        navigator.geolocation.getCurrentPosition(
            function (pos) {
                placeMarker(pos.coords.latitude, pos.coords.longitude, true);
            },
            function () {
                setPreview('Không lấy được GPS — chọn điểm trên bản đồ hoặc tìm địa chỉ.', false);
            },
            { enableHighAccuracy: true, timeout: 12000, maximumAge: 60000 }
        );
    }



    function mapCenter() {
        var latEl = latInputId ? document.getElementById(latInputId) : null;
        var lngEl = lngInputId ? document.getElementById(lngInputId) : null;
        if (latEl && lngEl) {
            var lat = parseFloat(latEl.value);
            var lng = parseFloat(lngEl.value);
            if (!isNaN(lat) && !isNaN(lng)) {
                return [lat, lng];
            }
        }

        return provinceCenter();
    }

    function setPreview(text, loading) {
        if (!previewEl) return;
        previewEl.textContent = text;
        previewEl.classList.toggle('is-loading', !!loading);
    }

    function requiresCoords() {
        return !!(latInputId && lngInputId);
    }

    function markPinFineTuned() {
        needsPinFineTune = false;
        updateConfirmButton();
    }

    function markPinNeedsFineTune() {
        if (!requiresCoords()) {
            needsPinFineTune = false;
            return;
        }
        needsPinFineTune = true;
        updateConfirmButton();
        if (previewEl && !isResolving) {
            previewEl.textContent = PIN_FINETUNE_HINT;
            previewEl.classList.remove('is-loading');
        }
    }

    function updateConfirmButton() {
        if (!confirmBtn) {
            return;
        }
        var hasAddress = String(pendingAddress || '').trim() !== '';
        var coordsOk = !requiresCoords() || (pendingLat !== null && pendingLng !== null);
        var fineTuneOk = !requiresCoords() || !needsPinFineTune;
        var canConfirm = !isResolving && hasAddress && coordsOk && fineTuneOk;
        confirmBtn.disabled = !canConfirm;

        if (confirmStatusEl) {
            if (isResolving) {
                confirmStatusEl.textContent = 'Đang lấy địa chỉ...';
                confirmStatusEl.classList.remove('d-none');
            } else if (requiresCoords() && needsPinFineTune) {
                confirmStatusEl.textContent = 'Cần chỉnh ghim trên bản đồ trước khi xác nhận.';
                confirmStatusEl.classList.remove('d-none');
            } else {
                confirmStatusEl.textContent = '';
                confirmStatusEl.classList.add('d-none');
            }
        }
    }

    function showSearchResultsPanel() {
        if (searchResultsEl) {
            searchResultsEl.classList.remove('d-none');
        }
        if (searchInput) {
            var wrap = searchInput.closest('.address-map-search-wrap');
            if (wrap) {
                wrap.classList.add('address-map-search-wrap--open');
            }
        }
    }

    function hideSearchResults() {
        if (!searchResultsEl) return;
        searchResultsEl.innerHTML = '';
        searchResultsEl.classList.add('d-none');
        searchResultsEl.classList.remove('geocode-search-results--loading');
        if (searchInput) {
            var wrap = searchInput.closest('.address-map-search-wrap');
            if (wrap) {
                wrap.classList.remove('address-map-search-wrap--open');
            }
        }
        if (mapInstance) {
            window.setTimeout(function () {
                invalidateMapSize();
            }, 30);
        }
    }

    function syncSearchClearButton() {
        if (!searchClearBtn || !searchInput) {
            return;
        }
        searchClearBtn.classList.toggle('d-none', !String(searchInput.value || '').trim());
    }

    function scrollSearchInputToCaret() {
        if (!searchInput) {
            return;
        }
        var len = searchInput.value.length;
        var pos = searchInput.selectionStart;
        if (pos === len || pos === null) {
            searchInput.scrollLeft = searchInput.scrollWidth;
        }
    }

    function moveSearchCaretToEnd() {
        if (!searchInput) {
            return;
        }
        var len = searchInput.value.length;
        window.requestAnimationFrame(function () {
            try {
                searchInput.setSelectionRange(len, len);
            } catch (e) {
            }
            searchInput.scrollLeft = searchInput.scrollWidth;
        });
    }

    function clearSearchInput() {
        if (!searchInput) {
            return;
        }
        searchInput.value = '';
        hideSearchResults();
        syncSearchClearButton();
        searchInput.focus();
        moveSearchCaretToEnd();
    }

    function destroyMap() {
        if (marker && typeof marker.remove === 'function') {
            marker.remove();
            marker = null;
        }
        if (mapInstance && typeof mapInstance.remove === 'function') {
            mapInstance.remove();
            mapInstance = null;
        }
        if (canvasEl) {
            canvasEl.innerHTML = '';
            canvasEl.removeAttribute('style');
        }
    }

    function teardownPicker() {
        if (reverseAbort) {
            reverseAbort.abort();
            reverseAbort = null;
        }
        if (searchAbort) {
            searchAbort.abort();
            searchAbort = null;
        }
        if (searchTimer) {
            window.clearTimeout(searchTimer);
            searchTimer = null;
        }
        destroyMap();
        unloadMapAssets();
        unbindProvinceChange();
        syncDriverProvinceUi(false);
        isResolving = false;
        targetInputId = null;
        provinceInputId = null;
        sheetProvinceInputId = null;
        defaultProvince = '';
        pickerMode = 'default';
        latInputId = null;
        lngInputId = null;
        pendingLat = null;
        pendingLng = null;
        pendingAddress = '';
        pendingProvince = '';
        needsPinFineTune = false;
        hideSearchResults();
        updateConfirmButton();
    }

    function applyCoords(lat, lng) {
        var latEl = latInputId ? document.getElementById(latInputId) : null;
        var lngEl = lngInputId ? document.getElementById(lngInputId) : null;
        if (latEl) {
            latEl.value = String(lat);
        }
        if (lngEl) {
            lngEl.value = String(lng);
            lngEl.dispatchEvent(new Event('change', { bubbles: true }));
        } else if (latEl) {
            latEl.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    function applyAddress(address) {
        var text = String(address || '').trim();
        if (!text) {
            return;
        }

        if (searchInput) {
            searchInput.value = text;
        }

        var input = targetInputId ? document.getElementById(targetInputId) : null;
        if (input) {
            input.value = text;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    function closePicker() {
        modal.hide();
    }

    function finishWithAddress(address) {
        isResolving = false;
        var text = String(address || '').trim();
        if (latInputId && pendingLat !== null && pendingLng !== null) {
            applyCoords(pendingLat, pendingLng);
        }

        syncSheetProvinceFromModal();

        document.dispatchEvent(new CustomEvent('addressmap:applied', {
            bubbles: true,
            detail: {
                targetInputId: targetInputId,
                latInputId: latInputId,
                lngInputId: lngInputId,
                lat: pendingLat,
                lng: pendingLng,
                address: text,
                province: pendingProvince,
            },
        }));

        applyAddress(text);
        closePicker();
    }

    function coordsFallbackLabel() {
        if (pendingLat === null || pendingLng === null) {
            return '';
        }
        return 'Vị trí đã chọn (' + pendingLat.toFixed(5) + ', ' + pendingLng.toFixed(5) + ')';
    }

    function previewResolvedAddress(address) {
        isResolving = false;
        var text = String(address || '').trim();
        if (!text) {
            text = coordsFallbackLabel();
        }
        pendingAddress = text;
        if (searchInput) {
            searchInput.value = text;
        }
        setPreview(text ? text : 'Chưa có địa chỉ — thử chọn điểm khác.', false);
        updateConfirmButton();
    }

    function resolveLocation(lat, lng) {
        if (!reverseUrl) {
            previewResolvedAddress(coordsFallbackLabel());
            return;
        }

        if (isResolving) {
            return;
        }

        isResolving = true;
        pendingAddress = '';
        updateConfirmButton();
        hideSearchResults();
        if (reverseAbort) {
            reverseAbort.abort();
        }
        reverseAbort = new AbortController();
        setPreview('Đang lấy địa chỉ...', true);

        fetch(reverseUrl + '?lat=' + encodeURIComponent(lat) + '&lon=' + encodeURIComponent(lng), {
            signal: reverseAbort.signal,
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then(function (r) {
                if (!r.ok) {
                    throw new Error('geocode_failed');
                }
                return r.json();
            })
            .then(function (data) {
                var address = data && data.address ? String(data.address).trim() : '';
                if (!address) {
                    throw new Error('empty_address');
                }
                pendingProvince = data && data.province ? String(data.province).trim() : '';
                previewResolvedAddress(address);
            })
            .catch(function (err) {
                if (err && err.name === 'AbortError') {
                    return;
                }
                previewResolvedAddress(coordsFallbackLabel());
            });
    }

    function placeMarker(lat, lng, moveView, options) {
        options = options || {};
        var goongjs = goongApi();
        if (!mapInstance || isResolving || !goongjs) {
            return;
        }

        if (options.fromSearch) {
            markPinNeedsFineTune();
        } else if (!options.preserveFineTune) {
            markPinFineTuned();
        }

        pendingLat = lat;
        pendingLng = lng;

        if (marker) {
            marker.setLngLat([lng, lat]);
        } else {
            marker = new goongjs.Marker({ draggable: true })
                .setLngLat([lng, lat])
                .addTo(mapInstance);
            marker.on('dragend', function () {
                if (isResolving) {
                    return;
                }
                var pos = marker.getLngLat();
                placeMarker(pos.lat, pos.lng, false);
            });
        }

        if (moveView) {
            mapFlyTo(lat, lng, options);
        }

        if (options.address) {
            previewResolvedAddress(options.address);
            return;
        }

        resolveLocation(lat, lng);
    }

    function confirmSelection() {
        if (isResolving || confirmBtn?.disabled) {
            return;
        }

        var text = String(pendingAddress || '').trim();
        if (!text && pendingLat !== null && pendingLng !== null) {
            text = coordsFallbackLabel();
        }
        if (!text) {
            setPreview('Chọn điểm trên bản đồ hoặc từ kết quả tìm kiếm trước khi xác nhận.', false);
            return;
        }
        if (requiresCoords() && needsPinFineTune) {
            setPreview(PIN_FINETUNE_HINT, false);
            return;
        }

        if (requiresCoords() && (pendingLat === null || pendingLng === null)) {
            setPreview('Chọn điểm trên bản đồ trước khi xác nhận.', false);
            return;
        }

        finishWithAddress(text);
    }

    function selectSearchItem(item) {
        if (isResolving || !item) {
            return;
        }

        hideSearchResults();

        var applyItem = function (resolved) {
            if (!resolved) {
                return;
            }

            if (resolved.lat != null && resolved.lon != null) {
                placeMarker(resolved.lat, resolved.lon, true, {
                    address: resolved.address,
                    fromSearch: true,
                });
                return;
            }

            pendingLat = null;
            pendingLng = null;
            previewResolvedAddress(resolved.address);
        };

        if (window.GeocodeResolve && window.GeocodeResolve.resolvePlace) {
            window.GeocodeResolve.resolvePlace(item).then(applyItem);
            return;
        }

        applyItem(item);
    }

    function renderSearchResults(results, query) {
        if (!searchResultsEl) return;

        if (window.GeocodeSearchUi && window.GeocodeSearchUi.renderResults) {
            window.GeocodeSearchUi.renderResults(searchResultsEl, results, query || lastSearchQuery, {
                itemClass: 'address-map-search-item geocode-search-item',
                emptyClass: 'address-map-search-empty',
                emptyText: 'Không thấy địa chỉ phù hợp — thử thêm số nhà, phường hoặc quận.',
                onSelect: function (item) {
                    selectSearchItem(item);
                },
            });
            showSearchResultsPanel();
            return;
        }

        searchResultsEl.innerHTML = '';

        if (!results.length) {
            var empty = document.createElement('div');
            empty.className = 'address-map-search-empty';
            empty.textContent = 'Không thấy địa chỉ phù hợp.';
            searchResultsEl.appendChild(empty);
            searchResultsEl.classList.remove('d-none');
            return;
        }

        results.forEach(function (item) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'address-map-search-item';
            btn.textContent = item.address;
            btn.addEventListener('click', function () {
                selectSearchItem(item);
            });
            searchResultsEl.appendChild(btn);
        });
        searchResultsEl.classList.remove('d-none');
    }

    function searchAddress(query) {
        if (!searchUrl || query.length < 2) {
            hideSearchResults();
            return;
        }

        lastSearchQuery = query;
        var cacheKey = query + '\0' + provinceName();
        if (searchResultCache[cacheKey]) {
            renderSearchResults(searchResultCache[cacheKey], query);
            return;
        }

        if (searchAbort) {
            searchAbort.abort();
        }
        searchAbort = new AbortController();

        if (window.GeocodeSearchUi && window.GeocodeSearchUi.setLoading) {
            window.GeocodeSearchUi.setLoading(searchResultsEl, 'Đang tìm địa chỉ…');
        }
        showSearchResultsPanel();

        var url = searchUrl
            + '?q=' + encodeURIComponent(query)
            + '&province=' + encodeURIComponent(provinceName());

        fetch(url, {
            signal: searchAbort.signal,
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then(function (r) {
                if (!r.ok) {
                    throw new Error('search_http_' + r.status);
                }
                return r.json();
            })
            .then(function (data) {
                var results = (data && data.results) || [];
                searchResultCache[cacheKey] = results;
                renderSearchResults(results, query);
            })
            .catch(function (err) {
                if (err && err.name === 'AbortError') {
                    return;
                }
                if (!searchResultsEl) {
                    return;
                }
                searchResultsEl.innerHTML = '';
                var error = document.createElement('div');
                error.className = 'address-map-search-empty';
                error.textContent = 'Không tìm được địa chỉ — thử thêm phường/quận hoặc cấu hình GOONG_API_KEY.';
                searchResultsEl.appendChild(error);
                showSearchResultsPanel();
            });
    }

    function initMap() {
        destroyMap();
        var goongjs = goongApi();
        if (!canvasEl || !goongjs) {
            return;
        }

        goongjs.accessToken = goongMaptilesKey;
        var center = mapCenter();

        mapInstance = new goongjs.Map({
            container: canvasEl,
            style: 'https://tiles.goong.io/assets/goong_map_web.json',
            center: [center[1], center[0]],
            zoom: isDriverMode() ? DRIVER_FOCUS_ZOOM : PROVINCE_FOCUS_ZOOM,
            attributionControl: true,
        });

        mapInstance.on('click', function (e) {
            if (!e.lngLat) {
                return;
            }
            placeMarker(e.lngLat.lat, e.lngLat.lng, false);
        });

        seedExistingMarker();
    }

    function seedExistingMarker() {
        var placedExisting = false;
        if (latInputId && lngInputId) {
            var latEl = document.getElementById(latInputId);
            var lngEl = document.getElementById(lngInputId);
            if (latEl && lngEl && latEl.value && lngEl.value) {
                var existingLat = parseFloat(latEl.value);
                var existingLng = parseFloat(lngEl.value);
                if (!isNaN(existingLat) && !isNaN(existingLng)) {
                    placedExisting = true;
                    var existingAddress = document.getElementById(targetInputId);
                    var existingText = existingAddress ? String(existingAddress.value || '').trim() : '';
                    placeMarker(existingLat, existingLng, false, existingText
                        ? { address: existingText, preserveFineTune: true }
                        : { preserveFineTune: true });
                    markPinFineTuned();
                }
            }
        }

        if (!placedExisting) {
            focusMapOnProvince();
        }
    }

    function openPicker(btn) {
        targetInputId = btn.getAttribute('data-address-map-for') || '';
        provinceInputId = btn.getAttribute('data-address-map-province') || '';
        sheetProvinceInputId = provinceInputId;
        defaultProvince = btn.getAttribute('data-address-map-default-province') || '';
        pickerMode = btn.getAttribute('data-address-map-mode') || 'default';
        latInputId = btn.getAttribute('data-address-map-lat') || '';
        lngInputId = btn.getAttribute('data-address-map-lng') || '';
        if (!targetInputId) {
            return;
        }

        syncDriverProvinceUi(isDriverMode());
        syncModalProvinceFromSheet();
        bindProvinceChange();

        var label = btn.getAttribute('data-address-map-label') || 'Chọn điểm trên bản đồ';
        if (titleEl) {
            titleEl.textContent = label;
        }

        isResolving = false;
        needsPinFineTune = false;
        pendingLat = null;
        pendingLng = null;
        pendingAddress = '';
        updateConfirmButton();
        hideSearchResults();
        hideAddressSuggestions();

        var existing = document.getElementById(targetInputId);
        var existingValue = existing ? String(existing.value || '').trim() : '';
        if (searchInput) {
            searchInput.value = existingValue;
            syncSearchClearButton();
        }

        var provinceLabel = provinceName();
        var provinceHint = provinceLabel
            ? (isDriverMode()
                ? ('Chọn vị trí hoạt động trong khu vực ' + provinceLabel + '.')
                : ('Chọn điểm trong khu vực ' + provinceLabel + '.'))
            : (isDriverMode()
                ? 'Chọn khu vực, ghim vị trí hoạt động hoặc dùng GPS.'
                : (requiresCoords()
                    ? 'Tìm địa chỉ để bay tới khu vực, sau đó kéo ghim đến đúng điểm đón.'
                    : 'Chạm bản đồ hoặc tìm địa chỉ, rồi bấm Xác nhận.'));
        setPreview(existingValue
            ? ('Chỉnh ghim hoặc tìm lại — ' + provinceHint)
            : provinceHint, false);

        loadMapAssets()
            .then(function () {
                modal.show();
                window.setTimeout(function () {
                    if (searchInput) {
                        searchInput.focus({ preventScroll: true });
                        moveSearchCaretToEnd();
                        if (existingValue.length >= 2) {
                            searchAddress(existingValue);
                        }
                    }
                }, 120);
            })
            .catch(function () {
                teardownPicker();
                var mapMsg = 'Không tải được bản đồ Goong. Kiểm tra GOONG_MAPTILES_KEY trong .env.';
                if (window.AppFlash && window.AppFlash.show) {
                    window.AppFlash.show(mapMsg, {
                        variant: 'warning',
                        title: 'Không tải được bản đồ',
                    });
                } else if (window.AppDialog) {
                    window.AppDialog.alert(mapMsg);
                }
            });
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-address-map-for]');
        if (!btn) {
            return;
        }
        e.preventDefault();
        openPicker(btn);
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Enter' && e.key !== ' ') {
            return;
        }
        var trigger = e.target.closest('.address-map-readonly-input[data-address-map-for]');
        if (!trigger) {
            return;
        }
        e.preventDefault();
        openPicker(trigger);
    });

    if (confirmBtn) {
        confirmBtn.addEventListener('click', confirmSelection);
    }

    if (myLocationBtn) {
        myLocationBtn.addEventListener('click', locateMe);
    }

    if (searchClearBtn) {
        searchClearBtn.addEventListener('click', function (e) {
            e.preventDefault();
            clearSearchInput();
        });
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            syncSearchClearButton();
            scrollSearchInputToCaret();
            var q = searchInput.value.trim();
            if (searchTimer) {
                window.clearTimeout(searchTimer);
            }
            if (q.length < 2) {
                hideSearchResults();
                return;
            }
            searchTimer = window.setTimeout(function () {
                searchAddress(q);
            }, 400);
        });

        searchInput.addEventListener('focus', function () {
            moveSearchCaretToEnd();
        });

        searchInput.addEventListener('keydown', function (e) {
            if (window.GeocodeSearchUi
                && window.GeocodeSearchUi.handleListKeydown
                && window.GeocodeSearchUi.handleListKeydown(e, searchResultsEl)) {
                return;
            }
            if (e.key === 'Enter') {
                e.preventDefault();
                var first = searchResultsEl && searchResultsEl.querySelector('.address-map-search-item');
                if (first) {
                    first.click();
                }
            }
        });
    }

    modalEl.addEventListener('shown.bs.modal', function () {
        initMap();
        window.setTimeout(function () {
            invalidateMapSize();
        }, 60);
    });

    modalEl.addEventListener('hidden.bs.modal', function () {
        teardownPicker();
        if (searchInput) {
            searchInput.value = '';
        }
        syncSearchClearButton();
        setPreview('Chạm bản đồ hoặc tìm địa chỉ, rồi bấm Xác nhận.', false);
    });
})();
