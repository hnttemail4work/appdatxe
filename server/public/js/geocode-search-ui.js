/**
 * UI gợi ý tìm địa chỉ — dùng chung map picker & autocomplete.
 */
(function () {
    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function foldText(value) {
        return String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase();
    }

    function highlightQuery(text, query) {
        var source = String(text || '');
        var needle = String(query || '').trim();
        if (!source || !needle || needle.length < 2) {
            return escapeHtml(source);
        }

        var foldedSource = foldText(source);
        var foldedNeedle = foldText(needle);
        var start = foldedSource.indexOf(foldedNeedle);
        if (start < 0) {
            var tokens = needle.split(/\s+/).filter(function (part) { return part.length >= 2; });
            if (!tokens.length) {
                return escapeHtml(source);
            }
            var html = escapeHtml(source);
            tokens.forEach(function (token) {
                var re = new RegExp('(' + token.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
                html = html.replace(re, '<mark>$1</mark>');
            });
            return html;
        }

        var end = start + foldedNeedle.length;
        return escapeHtml(source.slice(0, start))
            + '<mark>' + escapeHtml(source.slice(start, end)) + '</mark>'
            + escapeHtml(source.slice(end));
    }

    function kindIcon(kind) {
        switch (kind) {
            case 'address': return '📍';
            case 'road': return '🛣️';
            case 'area': return '🗺️';
            default: return '🏢';
        }
    }

    function haversineKm(lat1, lng1, lat2, lng2) {
        var toRad = function (d) { return d * Math.PI / 180; };
        var R = 6371;
        var dLat = toRad(lat2 - lat1);
        var dLng = toRad(lng2 - lng1);
        var a = Math.sin(dLat / 2) * Math.sin(dLat / 2)
            + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2))
            * Math.sin(dLng / 2) * Math.sin(dLng / 2);
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    function formatDistanceKm(km) {
        if (!(km >= 0) || isNaN(km)) {
            return '';
        }
        if (km < 0.1) {
            return 'Gần đây';
        }
        if (km < 1) {
            return Math.round(km * 1000) + ' m';
        }
        return (Math.round(km * 10) / 10).toFixed(1).replace('.', ',') + ' km';
    }

    function distanceLabelForItem(item, origin) {
        if (!origin || origin.lat == null || origin.lng == null) {
            return '';
        }
        var lat = item.lat != null ? Number(item.lat) : NaN;
        var lng = item.lng != null ? Number(item.lng)
            : (item.lon != null ? Number(item.lon) : NaN);
        if (isNaN(lat) || isNaN(lng)) {
            return '';
        }
        return formatDistanceKm(haversineKm(
            Number(origin.lat),
            Number(origin.lng),
            lat,
            lng
        ));
    }

    function buildResultButton(item, query, options) {
        var opts = options || {};
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = opts.itemClass || 'geocode-search-item';
        btn.setAttribute('role', 'option');

        var title = item.title || item.address || '';
        var subtitle = item.subtitle || '';
        if (!subtitle && item.address && item.address !== title) {
            subtitle = item.address;
        }

        var kindLabel = item.kind_label || '';
        var icon = kindIcon(item.kind);
        var kmLabel = typeof opts.distanceLabel === 'function'
            ? opts.distanceLabel(item)
            : distanceLabelForItem(item, opts.distanceOrigin);
        var rightMeta = kmLabel || kindLabel;

        btn.innerHTML =
            '<span class="geocode-search-item__row">'
            + '<span class="geocode-search-item__icon" aria-hidden="true">' + icon + '</span>'
            + '<span class="geocode-search-item__copy">'
            + '<span class="geocode-search-item__title">' + highlightQuery(title, query) + '</span>'
            + (subtitle
                ? '<span class="geocode-search-item__subtitle">' + highlightQuery(subtitle, query) + '</span>'
                : '')
            + '</span>'
            + (rightMeta
                ? '<span class="geocode-search-item__meta' + (kmLabel ? ' geocode-search-item__meta--km' : '') + '">'
                + escapeHtml(rightMeta)
                + '</span>'
                : '')
            + '</span>';

        btn.addEventListener('click', function () {
            if (typeof opts.onSelect === 'function') {
                opts.onSelect(item, btn);
            }
        });

        return btn;
    }

    function renderResults(container, results, query, options) {
        var opts = options || {};
        if (!container) {
            return;
        }

        container.innerHTML = '';
        container.classList.remove('geocode-search-results--loading');

        if (!results || !results.length) {
            var empty = document.createElement('div');
            empty.className = opts.emptyClass || 'geocode-search-empty';
            empty.textContent = opts.emptyText
                || 'Không thấy địa chỉ phù hợp — thử thêm số nhà, phường hoặc quận.';
            container.appendChild(empty);
            container.classList.remove('d-none');
            return;
        }

        results.forEach(function (item, index) {
            var btn = buildResultButton(item, query, opts);
            btn.setAttribute('data-index', String(index));
            container.appendChild(btn);
        });
        container.classList.remove('d-none');
        setActiveIndex(container, -1);
    }

    function setLoading(container, loadingText) {
        if (!container) {
            return;
        }
        if (!loadingText) {
            container.classList.remove('geocode-search-results--loading');
            return;
        }
        container.innerHTML = '<div class="geocode-search-loading">' + escapeHtml(loadingText) + '</div>';
        container.classList.add('geocode-search-results--loading');
        container.classList.remove('d-none');
    }

    function itemSelector(container) {
        return container
            ? container.querySelectorAll('.' + (container.getAttribute('data-item-class') || 'geocode-search-item'))
            : [];
    }

    function setActiveIndex(container, index) {
        if (!container) {
            return -1;
        }
        var items = container.querySelectorAll('.geocode-search-item');
        var active = -1;
        items.forEach(function (node, i) {
            var isActive = index >= 0 && i === index;
            node.classList.toggle('is-active', isActive);
            if (isActive) {
                active = i;
                node.scrollIntoView({ block: 'nearest' });
            }
        });
        return active;
    }

    function handleListKeydown(event, container, onSelect) {
        if (!container || container.classList.contains('d-none')) {
            return false;
        }

        var items = container.querySelectorAll('.geocode-search-item');
        if (!items.length) {
            return false;
        }

        var current = Array.prototype.findIndex.call(items, function (node) {
            return node.classList.contains('is-active');
        });

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            var next = current < items.length - 1 ? current + 1 : 0;
            setActiveIndex(container, next);
            return true;
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();
            var prev = current > 0 ? current - 1 : items.length - 1;
            setActiveIndex(container, prev);
            return true;
        }

        if (event.key === 'Enter') {
            var target = current >= 0 ? items[current] : items[0];
            if (target) {
                event.preventDefault();
                target.click();
                if (typeof onSelect === 'function') {
                    onSelect();
                }
            }
            return true;
        }

        return false;
    }

    window.GeocodeSearchUi = {
        renderResults: renderResults,
        setLoading: setLoading,
        handleListKeydown: handleListKeydown,
        highlightQuery: highlightQuery,
    };
})();
