/**
 * Camera sheet dùng chung: kéo popup lên/xuống → căn focus vào giữa
 * vùng map lộ (mép trên màn/banner ↔ mép trên popup).
 *
 * Dùng project/unproject (ổn định trên Goong) thay vì chỉ dựa padding easeTo.
 * Màn nối đón↔trả: truyền mode: 'pickup-dropoff-route' / skip: true.
 */
(function (global) {
    'use strict';

    var DEFAULT_SIDE = 28;
    var DEFAULT_MIN_VISIBLE = 120;
    var DEFAULT_TOP = 16;

    /**
     * @param {object} options
     * @param {Element} options.mapEl
     * @param {Element|null} [options.sheetEl]
     * @param {number|null} [options.sheetTop]
     * @param {Element|null} [options.topObstacleEl]
     * @param {number} [options.minVisible]
     * @param {number} [options.side]
     * @param {number} [options.topExtra]
     */
    function paddingFromEdges(options) {
        options = options || {};
        var mapEl = options.mapEl;
        if (!mapEl) {
            return { top: DEFAULT_TOP, bottom: 0, left: DEFAULT_SIDE, right: DEFAULT_SIDE };
        }

        var mapRect = mapEl.getBoundingClientRect();
        var mapH = mapRect.height || global.innerHeight || 600;
        var sheetTop = options.sheetTop;
        if (sheetTop == null && options.sheetEl) {
            sheetTop = options.sheetEl.getBoundingClientRect().top;
        }
        if (sheetTop == null) {
            sheetTop = mapRect.top + mapH;
        }

        var visibleH = Math.max(0, Math.round(sheetTop - mapRect.top));
        var bottom = Math.max(0, Math.round(mapH - visibleH));

        var topPad = options.topExtra != null ? options.topExtra : DEFAULT_TOP;
        var obstacle = options.topObstacleEl;
        if (obstacle
            && !obstacle.hidden
            && !obstacle.classList.contains('d-none')
            && obstacle.offsetParent !== null) {
            var obstBottom = obstacle.getBoundingClientRect().bottom;
            topPad = Math.max(topPad, Math.round(obstBottom - mapRect.top) + 10);
        }

        var minVisible = options.minVisible != null ? options.minVisible : DEFAULT_MIN_VISIBLE;
        if (mapH - bottom - topPad < minVisible) {
            bottom = Math.max(0, Math.round(mapH - minVisible - topPad));
        }

        var side = options.side != null ? options.side : DEFAULT_SIDE;
        return { top: topPad, bottom: bottom, left: side, right: side };
    }

    function mapSize(map, mapEl) {
        var el = mapEl || (map && typeof map.getContainer === 'function' ? map.getContainer() : null);
        if (!el) {
            return { w: 0, h: 0 };
        }
        return {
            w: el.clientWidth || Math.round(el.getBoundingClientRect().width) || 0,
            h: el.clientHeight || Math.round(el.getBoundingClientRect().height) || 0,
        };
    }

    /** Điểm giữa vùng map còn lộ (trên ↔ dưới popup). */
    function visibleAnchor(pad, size) {
        var left = pad.left || 0;
        var right = pad.right || 0;
        var top = pad.top || 0;
        var bottom = pad.bottom || 0;
        return {
            x: left + Math.max(0, size.w - left - right) / 2,
            y: top + Math.max(0, size.h - top - bottom) / 2,
        };
    }

    /**
     * Tính center địa lý để `focus` hiện đúng tại pixel `anchor`.
     * Không phụ thuộc padding easeTo (Goong đôi khi bỏ qua).
     */
    function centerForAnchor(map, focusLngLat, anchor) {
        if (!map || typeof map.project !== 'function' || typeof map.unproject !== 'function') {
            return null;
        }
        try {
            var focusPx = map.project(focusLngLat);
            var centerPx = map.project(map.getCenter());
            if (!focusPx || !centerPx) {
                return null;
            }
            var next = map.unproject([
                centerPx.x + (focusPx.x - anchor.x),
                centerPx.y + (focusPx.y - anchor.y),
            ]);
            if (!next || next.lng == null || next.lat == null) {
                return null;
            }
            return { lng: next.lng, lat: next.lat };
        } catch (e) {
            return null;
        }
    }

    /**
     * @param {object} map — goong/mapbox map
     * @param {{lng:number,lat:number}|[number,number]} focus
     * @param {object} [options]
     */
    function easeToFocus(map, focus, options) {
        options = options || {};
        if (!map || !focus || options.skip) {
            return false;
        }
        if (options.mode === 'pickup-dropoff-route') {
            return false;
        }

        var lng;
        var lat;
        if (Array.isArray(focus)) {
            lng = Number(focus[0]);
            lat = Number(focus[1]);
        } else {
            lng = Number(focus.lng);
            lat = Number(focus.lat);
        }
        if (!Number.isFinite(lng) || !Number.isFinite(lat)) {
            return false;
        }

        var pad = options.padding || paddingFromEdges(options);
        var size = mapSize(map, options.mapEl);
        var anchor = size.w > 0 && size.h > 0
            ? visibleAnchor(pad, size)
            : { x: 0, y: 0 };

        var zoom = options.zoom;
        if (zoom == null && typeof map.getZoom === 'function') {
            zoom = map.getZoom();
        }

        var ease = {
            center: [lng, lat],
            duration: options.duration != null ? options.duration : 300,
        };
        if (zoom != null) {
            ease.zoom = zoom;
        }
        if (options.pitch != null) {
            ease.pitch = options.pitch;
        }
        if (options.bearing != null) {
            ease.bearing = options.bearing;
        }

        // Offset theo pixel vùng lộ — đúng cả khi đổi zoom/pitch (nav peek↔full).
        // Không gộp padding (tránh lệch kép trên một số bản Goong).
        if (size.w > 0 && size.h > 0) {
            ease.offset = [anchor.x - size.w / 2, anchor.y - size.h / 2];
        } else {
            var focusLngLat = { lng: lng, lat: lat };
            var adjusted = centerForAnchor(map, focusLngLat, anchor);
            if (adjusted) {
                ease.center = [adjusted.lng, adjusted.lat];
            } else {
                ease.padding = pad;
            }
        }

        if (typeof map.easeTo === 'function') {
            map.easeTo(ease);
            return true;
        }
        return false;
    }

    global.MapSheetCamera = {
        paddingFromEdges: paddingFromEdges,
        easeToFocus: easeToFocus,
        visibleAnchor: visibleAnchor,
        centerForAnchor: centerForAnchor,
    };
})(window);
