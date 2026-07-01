/**
 * Customer booking — thanh điều hướng cuộn (không chuyển tab / reload).
 */
(function () {
    var root = document.querySelector('.customer-page');
    var dock = document.querySelector('.customer-scroll-dock');
    if (!root || !dock) {
        return;
    }

    var sectionIds = ['booking-search-block', 'booking-results-main', 'guest-trip-watch-section'];
    var scrollItems = dock.querySelectorAll('[data-scroll-target]');

    function scrollToSection(id) {
        var el = document.getElementById(id);
        if (!el) {
            return;
        }
        var offset = 12;
        var top = el.getBoundingClientRect().top + window.scrollY - offset;
        window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
    }

    function setActiveItem(targetId) {
        scrollItems.forEach(function (item) {
            var isActive = item.dataset.scrollTarget === targetId;
            item.classList.toggle('is-active', isActive);
        });
    }

    scrollItems.forEach(function (item) {
        item.addEventListener('click', function (event) {
            event.preventDefault();
            var targetId = item.dataset.scrollTarget;
            if (!targetId) {
                return;
            }
            scrollToSection(targetId);
            setActiveItem(targetId);
        });
    });

    if ('IntersectionObserver' in window) {
        var observer = new IntersectionObserver(function (entries) {
            var visible = entries
                .filter(function (entry) { return entry.isIntersecting; })
                .sort(function (a, b) { return b.intersectionRatio - a.intersectionRatio; });
            if (visible.length && visible[0].target.id) {
                setActiveItem(visible[0].target.id);
            }
        }, { root: null, rootMargin: '-40% 0px -45% 0px', threshold: [0, 0.15, 0.35, 0.55] });

        sectionIds.forEach(function (id) {
            var el = document.getElementById(id);
            if (el) {
                observer.observe(el);
            }
        });
    }

    function updateTrackBadge(count) {
        var badge = dock.querySelector('[data-scroll-dock-badge="track"]');
        if (!badge) {
            return;
        }
        var n = Number(count) || 0;
        if (n > 0) {
            badge.textContent = String(n);
            badge.classList.remove('d-none');
            badge.classList.add('is-hot');
        } else {
            badge.textContent = '';
            badge.classList.add('d-none');
            badge.classList.remove('is-hot');
        }
    }

    function shouldShowTrackTab(tripCount, watchlistCount) {
        var trips = Number(tripCount) || 0;
        var watchlist = Number(watchlistCount);
        if (Number.isNaN(watchlist)) {
            watchlist = Number(window.__guestWatchlistCount) || 0;
        }
        return trips > 0 || watchlist > 0 || window.__bookingSuccessActive === true || window.__guestShowTrackTab === true;
    }

    function updateTrackVisibility(tripCount, watchlistCount) {
        var show = shouldShowTrackTab(tripCount, watchlistCount);
        var trackBtn = dock.querySelector('.customer-scroll-dock-item--track');
        var sectionEl = document.getElementById('guest-trip-watch-section');

        if (trackBtn) {
            trackBtn.classList.toggle('d-none', !show);
        }
        dock.classList.toggle('customer-scroll-dock--two-tabs', !show);
        if (sectionEl) {
            sectionEl.classList.toggle('d-none', !show);
        }
        updateTrackBadge(tripCount);
    }

    document.addEventListener('guesttrips:updated', function (event) {
        var detail = event.detail || {};
        updateTrackVisibility(detail.count, detail.watchlist_count);
    });

    var viewOrderBtn = document.getElementById('booking-flash-view-order-btn');
    if (viewOrderBtn) {
        viewOrderBtn.addEventListener('click', function () {
            scrollToSection('guest-trip-watch-section');
            setActiveItem('guest-trip-watch-section');
        });
    }

    updateTrackVisibility(window.__guestActiveOrdersCount, window.__guestWatchlistCount);

    window.CustomerScrollDock = {
        scrollTo: scrollToSection,
        scrollToTrack: function () {
            scrollToSection('guest-trip-watch-section');
            setActiveItem('guest-trip-watch-section');
        },
        scrollToResults: function () {
            scrollToSection('booking-results-main');
            setActiveItem('booking-results-main');
        },
    };
})();
