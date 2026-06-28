/** Gợi ý số ghế + format biển số trên trang hồ sơ tài xế */
(function () {
    var form = document.getElementById('driver-profile-form');
    if (!form) return;

    var typeSelect = form.querySelector('[data-seats-hint]');
    var seatsInput = form.querySelector('#vehicle_seats_input');
    if (typeSelect && seatsInput) {
        typeSelect.addEventListener('change', function () {
            var opt = typeSelect.selectedOptions[0];
            if (opt && opt.dataset.defaultSeats && !seatsInput.value) {
                seatsInput.value = opt.dataset.defaultSeats;
            }
        });
    }

    form.querySelectorAll('[data-plate-format]').forEach(function (input) {
        input.addEventListener('blur', function () {
            var v = input.value.toUpperCase().replace(/\s+/g, '');
            if (v.length >= 7 && v.indexOf('-') === -1) {
                input.value = v.slice(0, 3) + '-' + v.slice(3);
            }
        });
    });
})();
