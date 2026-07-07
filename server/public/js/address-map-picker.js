/**
 * Map picker — search + map pin, preview selection, confirm to apply.
 */
(function () {
    var LEAFLET_CSS = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
    var LEAFLET_JS = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';

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
    var leafletAssetsPromise = null;
    var searchTimer = null;
    var lastSearchQuery = '';
    var provinceChangeHandler = null;

    function loadStylesheet(href) {
        return new Promise(function (resolve, reject) {
            var link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = href;
            link.setAttribute('data-address-map-asset', 'leaflet-css');
            link.onload = function () { resolve(link); };
            link.onerror = reject;
            document.head.appendChild(link);
        });
    }

    function loadScript(src) {
        return new Promise(function (resolve, reject) {
            var script = document.createElement('script');
            script.src = src;
            script.async = true;
            script.setAttribute('data-address-map-asset', 'leaflet-js');
            script.onload = function () { resolve(script); };
            script.onerror = reject;
            document.body.appendChild(script);
        });
    }

    function loadLeafletAssets() {
        if (window.L) {
            return Promise.resolve();
        }
        if (!leafletAssetsPromise) {
            leafletAssetsPromise = loadStylesheet(LEAFLET_CSS).then(function () {
                return loadScript(LEAFLET_JS);
            });
        }
        return leafletAssetsPromise;
    }

    function unloadLeafletAssets() {
        leafletAssetsPromise = null;
        document.querySelectorAll('[data-address-map-asset]').forEach(function (node) {
            node.remove();
        });
        if (window.L) {
            try {
                delete window.L;
            } catch (e) {
                window.L = undefined;
            }
        }
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
        mapInstance.setView(provinceCenter(), focusZoomLevel());
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

    function hideSearchResults() {
        if (!searchResultsEl) return;
        searchResultsEl.innerHTML = '';
        searchResultsEl.classList.add('d-none');
    }

    function destroyMap() {
        if (mapInstance) {
            mapInstance.off();
            mapInstance.remove();
            mapInstance = null;
        }
        marker = null;
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
        unloadLeafletAssets();
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
        if (!mapInstance || !window.L || isResolving) {
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
            marker.setLatLng([lat, lng]);
        } else {
            marker = window.L.marker([lat, lng], { draggable: true }).addTo(mapInstance);
            marker.on('dragend', function () {
                if (isResolving) return;
                var pos = marker.getLatLng();
                placeMarker(pos.lat, pos.lng, false);
            });
        }

        if (moveView) {
            var targetZoom = isDriverMode()
                ? Math.max(mapInstance.getZoom(), DRIVER_FOCUS_ZOOM)
                : Math.max(mapInstance.getZoom(), options.fromSearch ? PICKUP_PIN_ZOOM : PROVINCE_FOCUS_ZOOM);
            mapInstance.setView([lat, lng], targetZoom);
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

    function renderSearchResults(results, query) {
        if (!searchResultsEl) return;

        if (window.GeocodeSearchUi && window.GeocodeSearchUi.renderResults) {
            window.GeocodeSearchUi.renderResults(searchResultsEl, results, query || lastSearchQuery, {
                itemClass: 'address-map-search-item geocode-search-item',
                emptyClass: 'address-map-search-empty',
                emptyText: 'Không thấy địa chỉ phù hợp — thử thêm số nhà, phường hoặc quận.',
                onSelect: function (item) {
                    if (isResolving) return;
                    hideSearchResults();
                    if (item.lat != null && item.lon != null) {
                        placeMarker(item.lat, item.lon, true, { address: item.address, fromSearch: true });
                    } else {
                        pendingLat = null;
                        pendingLng = null;
                        previewResolvedAddress(item.address);
                    }
                },
            });
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
                if (isResolving) return;
                hideSearchResults();
                if (item.lat != null && item.lon != null) {
                    placeMarker(item.lat, item.lon, true, { address: item.address, fromSearch: true });
                } else {
                    pendingLat = null;
                    pendingLng = null;
                    previewResolvedAddress(item.address);
                }
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

        if (searchAbort) {
            searchAbort.abort();
        }
        searchAbort = new AbortController();

        if (window.GeocodeSearchUi && window.GeocodeSearchUi.setLoading) {
            window.GeocodeSearchUi.setLoading(searchResultsEl, 'Đang tìm địa chỉ…');
        }

        var url = searchUrl
            + '?q=' + encodeURIComponent(query)
            + '&province=' + encodeURIComponent(provinceName());

        fetch(url, {
            signal: searchAbort.signal,
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                renderSearchResults((data && data.results) || [], query);
            })
            .catch(function (err) {
                if (err && err.name === 'AbortError') return;
                hideSearchResults();
            });
    }

    function initMap() {
        destroyMap();
        if (!canvasEl || !window.L) {
            return;
        }

        var center = mapCenter();
        mapInstance = window.L.map(canvasEl, {
            zoomControl: true,
            attributionControl: true,
        }).setView(center, isDriverMode() ? DRIVER_FOCUS_ZOOM : 14);

        window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap',
        }).addTo(mapInstance);

        mapInstance.on('click', function (e) {
            placeMarker(e.latlng.lat, e.latlng.lng, false);
        });

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

        loadLeafletAssets()
            .then(function () {
                modal.show();
                window.setTimeout(function () {
                    if (searchInput) {
                        searchInput.focus();
                        if (existingValue.length >= 2) {
                            searchAddress(existingValue);
                        }
                    }
                }, 120);
            })
            .catch(function () {
                teardownPicker();
                if (window.AppFlash && window.AppFlash.show) {
                    window.AppFlash.show('Không tải được bản đồ. Vui lòng nhập địa chỉ bằng tay.', {
                        variant: 'warning',
                        title: 'Không tải được bản đồ',
                    });
                } else if (window.AppDialog) {
                    window.AppDialog.alert('Không tải được bản đồ. Vui lòng nhập địa chỉ bằng tay.');
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

    if (searchInput) {
        searchInput.addEventListener('input', function () {
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
            if (mapInstance) {
                mapInstance.invalidateSize();
            }
        }, 60);
    });

    modalEl.addEventListener('hidden.bs.modal', function () {
        teardownPicker();
        if (searchInput) {
            searchInput.value = '';
        }
        setPreview('Chạm bản đồ hoặc tìm địa chỉ, rồi bấm Xác nhận.', false);
    });
})();
