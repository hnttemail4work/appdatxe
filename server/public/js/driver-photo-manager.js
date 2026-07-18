/**
 * Preview ảnh mới + lightbox phóng to thumbnail (admin/driver photo manager).
 */
(function () {
    document.querySelectorAll('.driver-photo-manager').forEach(function (form) {
        form.querySelectorAll('[data-photo-input]').forEach(function (input) {
            input.addEventListener('change', function () {
                var field = input.dataset.photoInput;
                var isMultiple = input.hasAttribute('data-multiple');

                if (isMultiple) {
                    handleVehicleFiles(input);
                    return;
                }

                var slot = form.querySelector('[data-photo-slot="' + field + '"]');
                if (!slot || !input.files || !input.files[0]) {
                    return;
                }

                var file = input.files[0];
                if (!validateSize(input, file)) {
                    return;
                }

                var newWrap = slot.querySelector('[data-new-wrap]');
                var newImg = slot.querySelector('[data-new-img]');
                var fileLabel = slot.querySelector('[data-file-label]');

                if (newWrap && newImg) {
                    newImg.src = URL.createObjectURL(file);
                    newWrap.classList.remove('d-none');
                    slot.classList.add('has-pending');
                }
                if (fileLabel) {
                    fileLabel.textContent = 'Đã chọn — đổi lại';
                }
            });
        });
    });

    function handleVehicleFiles(input) {
        var wrap = input.closest('.photo-vehicles-section')
            || input.closest('.photo-vehicles-block')
            || input.closest('.driver-photo-manager');
        var grid = wrap ? wrap.querySelector('[data-vehicle-new-grid]') : null;
        if (!grid) return;

        grid.innerHTML = '';
        var valid = [];
        Array.from(input.files || []).forEach(function (file) {
            if (validateSize(input, file)) {
                valid.push(file);
            }
        });

        if (valid.length === 0) {
            grid.classList.add('d-none');
            return;
        }

        valid.forEach(function (file, i) {
            var item = document.createElement('div');
            item.className = 'photo-vehicle-item pending';
            var img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            img.alt = 'Mới ' + (i + 1);
            var lbl = document.createElement('span');
            lbl.className = 'photo-vehicle-num';
            lbl.textContent = 'Mới';
            item.appendChild(img);
            item.appendChild(lbl);
            grid.appendChild(item);
        });
        grid.classList.remove('d-none');
    }

    function validateSize() {
        return true;
    }

    // Lightbox — thumbnail nhỏ, bấm mới phóng to
    var overlay = null;
    var overlayImg = null;

    function ensureOverlay() {
        if (overlay) return overlay;
        overlay = document.createElement('div');
        overlay.className = 'photo-zoom-overlay';
        overlay.hidden = true;
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', 'Xem ảnh phóng to');

        var closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'photo-zoom-overlay-close';
        closeBtn.setAttribute('aria-label', 'Đóng');
        closeBtn.textContent = '×';

        overlayImg = document.createElement('img');
        overlayImg.alt = 'Ảnh phóng to';

        overlay.appendChild(closeBtn);
        overlay.appendChild(overlayImg);
        document.body.appendChild(overlay);

        function close() {
            overlay.hidden = true;
            overlayImg.removeAttribute('src');
            document.body.style.overflow = '';
        }

        closeBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            close();
        });
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) close();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !overlay.hidden) close();
        });

        return overlay;
    }

    function openZoom(url, alt) {
        if (!url) return;
        ensureOverlay();
        overlayImg.src = url;
        overlayImg.alt = alt || 'Ảnh phóng to';
        overlay.hidden = false;
        document.body.style.overflow = 'hidden';
    }

    document.addEventListener('click', function (e) {
        var link = e.target.closest(
            '.driver-photo-manager a.photo-current-link, .photo-vehicles-block .photo-vehicle-item > a, .driver-edit-identity a[href]'
        );
        if (!link) return;

        var href = link.getAttribute('href');
        if (!href || href === '#') return;

        e.preventDefault();
        var img = link.querySelector('img');
        openZoom(href, img ? img.alt : '');
    });
})();
