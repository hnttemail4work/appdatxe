/**
 * Map picker — search + map pin, sync search field, fill detail input, close & unload.
 */
(function () {
    var LEAFLET_CSS = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
    var LEAFLET_JS = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';

    var PROVINCE_CENTERS = {
        'TP.HCM': [10.7769, 106.7009],
        'Bình Dương': [11.3254, 106.4770],
        'Đồng Nai': [10.9574, 106.8427],
        'Long An': [10.5339, 106.4132],
        'Tây Ninh': [11.3359, 106.1093],
        'Vũng Tàu': [10.3460, 107.0843],
        'Bà Rịa': [10.4963, 107.1684],
        'Phan Thiết': [10.9289, 108.1021],
        'Mũi Né': [10.9558, 108.2100],
        'Đà Lạt': [11.9404, 108.4583],
        'Mỹ Tho': [10.3600, 106.3600],
        'Bến Tre': [10.2434, 106.3757],
        'Vĩnh Long': [10.2537, 105.9722],
        'Cần Thơ': [10.0452, 105.7469],
        'Long Xuyên': [10.3866, 105.4352],
        'Châu Đốc': [10.7047, 105.1200],
    };

    var modalEl = document.getElementById('addressMapPickerModal');
    if (!modalEl || typeof bootstrap === 'undefined') {
        return;
    }

    var canvasEl = document.getElementById('address-map-canvas');
    var previewEl = document.getElementById('address-map-preview');
    var titleEl = document.getElementById('addressMapPickerTitle');
    var searchInput = document.getElementById('address-map-search');
    var searchResultsEl = document.getElementById('address-map-search-results');
    var reverseUrl = window.__geocodeReverseUrl || '';
    var searchUrl = window.__geocodeSearchUrl || '';

    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    var mapInstance = null;
    var marker = null;
    var targetInputId = null;
    var provinceInputId = null;
    var defaultProvince = '';
    var latInputId = null;
    var lngInputId = null;
    var pendingLat = null;
    var pendingLng = null;
    var reverseAbort = null;
    var searchAbort = null;
    var isResolving = false;
    var leafletAssetsPromise = null;
    var searchTimer = null;

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

    function provinceName() {
        var provinceEl = provinceInputId ? document.getElementById(provinceInputId) : null;
        var fromInput = provinceEl ? String(provinceEl.value || '').trim() : '';
        return fromInput || defaultProvince || '';
    }

    function provinceCenter() {
        return PROVINCE_CENTERS[provinceName()] || PROVINCE_CENTERS['TP.HCM'];
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
        isResolving = false;
        targetInputId = null;
        provinceInputId = null;
        defaultProvince = '';
        latInputId = null;
        lngInputId = null;
        pendingLat = null;
        pendingLng = null;
        hideSearchResults();
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
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    function closePicker() {
        modal.hide();
    }

    function finishWithAddress(address) {
        var text = String(address || '').trim();
        if (latInputId && pendingLat !== null && pendingLng !== null) {
            applyCoords(pendingLat, pendingLng);
        }
        applyAddress(text);
        document.dispatchEvent(new CustomEvent('addressmap:applied', {
            bubbles: true,
            detail: {
                targetInputId: targetInputId,
                latInputId: latInputId,
                lngInputId: lngInputId,
                lat: pendingLat,
                lng: pendingLng,
                address: text,
            },
        }));
        closePicker();
    }

    function resolveLocation(lat, lng) {
        if (!reverseUrl || isResolving) {
            return;
        }

        isResolving = true;
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
                finishWithAddress(address);
            })
            .catch(function (err) {
                if (err && err.name === 'AbortError') {
                    return;
                }
                isResolving = false;
                setPreview('Không đọc được địa chỉ. Thử tìm bằng ô trên hoặc chạm điểm khác.', false);
            });
    }

    function placeMarker(lat, lng, moveView) {
        if (!mapInstance || !window.L || isResolving) {
            return;
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
                resolveLocation(pos.lat, pos.lng);
            });
        }

        if (moveView) {
            mapInstance.setView([lat, lng], Math.max(mapInstance.getZoom(), 16));
        }

        resolveLocation(lat, lng);
    }

    function renderSearchResults(results) {
        if (!searchResultsEl) return;
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
                if (item.lat != null && item.lon != null) {
                    pendingLat = item.lat;
                    pendingLng = item.lon;
                }
                finishWithAddress(item.address);
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

        if (searchAbort) {
            searchAbort.abort();
        }
        searchAbort = new AbortController();

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
                renderSearchResults((data && data.results) || []);
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
        }).setView(center, 14);

        window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap',
        }).addTo(mapInstance);

        mapInstance.on('click', function (e) {
            placeMarker(e.latlng.lat, e.latlng.lng, false);
        });

        if (latInputId && lngInputId) {
            var latEl = document.getElementById(latInputId);
            var lngEl = document.getElementById(lngInputId);
            if (latEl && lngEl && latEl.value && lngEl.value) {
                var existingLat = parseFloat(latEl.value);
                var existingLng = parseFloat(lngEl.value);
                if (!isNaN(existingLat) && !isNaN(existingLng)) {
                    pendingLat = existingLat;
                    pendingLng = existingLng;
                    marker = window.L.marker([existingLat, existingLng], { draggable: true }).addTo(mapInstance);
                    marker.on('dragend', function () {
                        if (isResolving) return;
                        var pos = marker.getLatLng();
                        resolveLocation(pos.lat, pos.lng);
                    });
                }
            }
        }
    }

    function openPicker(btn) {
        targetInputId = btn.getAttribute('data-address-map-for') || '';
        provinceInputId = btn.getAttribute('data-address-map-province') || '';
        defaultProvince = btn.getAttribute('data-address-map-default-province') || '';
        latInputId = btn.getAttribute('data-address-map-lat') || '';
        lngInputId = btn.getAttribute('data-address-map-lng') || '';
        if (!targetInputId) {
            return;
        }

        var label = btn.getAttribute('data-address-map-label') || 'Chọn điểm trên bản đồ';
        if (titleEl) {
            titleEl.textContent = label;
        }

        isResolving = false;
        hideSearchResults();

        var existing = document.getElementById(targetInputId);
        var existingValue = existing ? String(existing.value || '').trim() : '';
        if (searchInput) {
            searchInput.value = existingValue;
        }

        setPreview(existingValue
            ? 'Chỉnh địa chỉ hoặc chạm bản đồ để chọn lại.'
            : 'Gõ tìm hoặc chạm bản đồ — địa chỉ tự điền và đóng.', false);

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
                if (window.AppDialog) {
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
        setPreview('Gõ tìm hoặc chạm bản đồ — địa chỉ tự điền và đóng.', false);
    });
})();
