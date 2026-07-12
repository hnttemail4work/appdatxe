/**
 * Resolve Goong place_id → tọa độ (chỉ gọi khi user chọn gợi ý).
 */
(function () {
    function resolvePlace(item) {
        if (!item) {
            return Promise.resolve(null);
        }

        if (item.lat != null && item.lon != null) {
            return Promise.resolve(item);
        }

        var placeId = item.place_id ? String(item.place_id).trim() : '';
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

                return Object.assign({}, item, data);
            })
            .catch(function () {
                return item;
            });
    }

    window.GeocodeResolve = {
        resolvePlace: resolvePlace,
    };
})();
