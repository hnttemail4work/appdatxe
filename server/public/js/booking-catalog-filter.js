/**
 * Lọc danh sách xe theo số chỗ và loại xe trên trang đặt vé.
 */
(function () {
    var list = document.getElementById('trips-list');
    var capacityFilter = document.getElementById('booking-filter-capacity');
    var typeFilter = document.getElementById('booking-filter-type');
    var emptyEl = document.getElementById('booking-filter-empty');

    if (!list) {
        return;
    }

    function cards() {
        return list.querySelectorAll('.trip-card-pro[data-driver-profile-id]');
    }

    function applyFilters() {
        var capacity = capacityFilter ? capacityFilter.value : '';
        var type = typeFilter ? typeFilter.value : '';
        var visible = 0;
        var cardNodes = cards();

        cardNodes.forEach(function (card) {
            var matchCapacity = !capacity || String(card.dataset.capacity || '') === capacity;
            var matchType = !type || String(card.dataset.vehicleType || '') === type;
            var show = matchCapacity && matchType;
            card.classList.toggle('d-none', !show);
            if (show) {
                visible += 1;
            }
        });

        if (emptyEl) {
            emptyEl.classList.toggle('d-none', visible > 0 || cardNodes.length === 0);
        }
    }

    [capacityFilter, typeFilter].forEach(function (el) {
        if (!el) {
            return;
        }
        el.addEventListener('change', applyFilters);
    });

    applyFilters();
})();
