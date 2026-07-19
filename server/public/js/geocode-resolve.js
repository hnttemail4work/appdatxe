/**
 * Resolve Goong place_id → tọa độ.
 * Cache localStorage để không gọi lại API cho địa điểm đã lấy.
 */
(function () {
    var CACHE_KEY = 'appdatxe:placeCoordCache';
    var CACHE_MAX = 80;

    function readCache() {
        try {
            var raw = window.localStorage.getItem(CACHE_KEY);
            var data = raw ? JSON.parse(raw) : {};
            return data && typeof data === 'object' ? data : {};
        } catch (e) {
            return {};
        }
    }

    function writeCache(map) {
        try {
            var keys = Object.keys(map);
            if (keys.length > CACHE_MAX) {
                // Giữ entry mới hơn (có touched)
                keys.sort(function (a, b) {
                    return (map[b].t || 0) - (map[a].t || 0);
                });
                var keep = {};
                keys.slice(0, CACHE_MAX).forEach(function (k) {
                    keep[k] = map[k];
                });
                map = keep;
            }
            window.localStorage.setItem(CACHE_KEY, JSON.stringify(map));
        } catch (e) {
        }
    }

    function getCached(placeId) {
        if (!placeId) {
            return null;
        }
        var map = readCache();
        var hit = map[placeId];
        if (!hit || hit.lat == null || (hit.lng == null && hit.lon == null)) {
            return null;
        }
        return {
            lat: Number(hit.lat),
            lng: Number(hit.lng != null ? hit.lng : hit.lon),
            lon: Number(hit.lng != null ? hit.lng : hit.lon),
            address: hit.address || '',
        };
    }

    function putCache(placeId, payload) {
        if (!placeId || !payload) {
            return;
        }
        var lat = payload.lat != null ? Number(payload.lat) : NaN;
        var lng = payload.lng != null ? Number(payload.lng)
            : (payload.lon != null ? Number(payload.lon) : NaN);
        if (isNaN(lat) || isNaN(lng)) {
            return;
        }
        var map = readCache();
        map[placeId] = {
            lat: lat,
            lng: lng,
            lon: lng,
            address: String(payload.address || '').trim(),
            t: Date.now(),
        };
        writeCache(map);
    }

    function resolvePlace(item) {
        if (!item) {
            return Promise.resolve(null);
        }

        if (item.lat != null && (item.lon != null || item.lng != null)) {
            if (item.place_id) {
                putCache(String(item.place_id).trim(), item);
            }
            return Promise.resolve(item);
        }

        var placeId = item.place_id ? String(item.place_id).trim() : '';
        if (placeId) {
            var cached = getCached(placeId);
            if (cached) {
                return Promise.resolve(Object.assign({}, item, cached));
            }
        }

        var resolveUrl = window.__geocodeResolveUrl || '';
        if (!placeId || !resolveUrl) {
            return Promise.resolve(item);
        }

        return fetch(resolveUrl + '?place_id=' + encodeURIComponent(placeId), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then(function (response) {
                return response.ok ? response.json() : null;
            })
            .then(function (data) {
                if (!data) {
                    return item;
                }
                var merged = Object.assign({}, item, data);
                putCache(placeId, merged);
                return merged;
            })
            .catch(function () {
                return item;
            });
    }

    window.GeocodeResolve = {
        resolvePlace: resolvePlace,
        getCached: getCached,
        putCache: putCache,
    };
})();
