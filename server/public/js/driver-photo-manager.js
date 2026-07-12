/**
 * Preview ảnh mới chọn ngay cạnh ảnh hiện tại — driver photo manager.
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
        var wrap = input.closest('.photo-vehicles-upload');
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
            var wrap = document.createElement('div');
            wrap.className = 'photo-vehicle-item pending';
            var img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            img.alt = 'Mới ' + (i + 1);
            var lbl = document.createElement('span');
            lbl.className = 'photo-vehicle-num';
            lbl.textContent = 'Mới';
            wrap.appendChild(img);
            wrap.appendChild(lbl);
            grid.appendChild(wrap);
        });
        grid.classList.remove('d-none');
    }

    function validateSize(input, file) {
        return true;
    }
})();
