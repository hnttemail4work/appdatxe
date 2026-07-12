/**
 * Mở bản đồ chỉ đường trên điện thoại (geo: — không dùng Google Maps).
 */
(function () {
    document.addEventListener('click', function (event) {
        var link = event.target.closest('[data-driver-map-nav]');
        if (!link || !link.href) {
            return;
        }

        if (window.innerWidth >= 768 && !window.matchMedia('(pointer: coarse)').matches) {
            return;
        }

        event.preventDefault();
        window.location.href = link.href;
    });
})();
