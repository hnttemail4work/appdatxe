/**
 * Customer booking — thanh điều hướng cuộn trên trang đặt vé.
 */
(function () {
    var root = document.querySelector('.customer-page');
    var dock = document.querySelector('.customer-scroll-dock');
    if (!root || !dock) {
        return;
    }

    var sectionIds = ['booking-page-top', 'booking-results-main'];
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

    if (window.location.hash === '#booking-results-main') {
        window.setTimeout(function () {
            scrollToSection('booking-results-main');
            setActiveItem('booking-results-main');
        }, 80);
    }

    window.CustomerScrollDock = {
        scrollTo: scrollToSection,
        scrollToResults: function () {
            scrollToSection('booking-results-main');
            setActiveItem('booking-results-main');
        },
    };
})();
