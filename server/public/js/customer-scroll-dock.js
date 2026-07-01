/**
 * Customer booking — thanh điều hướng (cuộn trên trang đặt vé, chuyển trang cho Đơn đặt).
 */
(function () {
    var root = document.querySelector('.customer-page');
    var dock = document.querySelector('.customer-scroll-dock');
    if (!root || !dock) {
        return;
    }

    var isOrdersPage = root.classList.contains('customer-page--orders');
    var sectionIds = ['booking-search-block', 'booking-results-main'];
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

    if (!isOrdersPage) {
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

        if (window.location.hash === '#booking-results-main') {
            window.setTimeout(function () {
                scrollToSection('booking-results-main');
                setActiveItem('booking-results-main');
            }, 80);
        }
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

    document.addEventListener('guesttrips:updated', function (event) {
        var detail = event.detail || {};
        updateTrackBadge(detail.count);
    });

    if (!isOrdersPage) {
        updateTrackBadge(window.__guestActiveOrdersCount);
    }

    window.CustomerScrollDock = {
        scrollTo: scrollToSection,
        scrollToTrack: function () {
            var url = window.__guestOrdersUrl;
            if (url) {
                window.location.href = url;
            }
        },
        scrollToResults: function () {
            scrollToSection('booking-results-main');
            setActiveItem('booking-results-main');
        },
    };
})();
