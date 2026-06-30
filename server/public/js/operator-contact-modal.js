(function () {
    var modalEl = document.getElementById('operatorContactModal');
    if (!modalEl || typeof bootstrap === 'undefined') {
        return;
    }

    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    var roleEl = document.getElementById('operatorContactModalRole');
    var nameEl = document.getElementById('operatorContactModalName');
    var phoneEl = document.getElementById('operatorContactModalPhone');

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.operator-contact-btn');
        if (!btn) {
            return;
        }
        var name = btn.getAttribute('data-contact-name') || '—';
        var phone = btn.getAttribute('data-contact-phone') || '';
        var role = btn.getAttribute('data-contact-role') || 'Liên hệ';

        roleEl.textContent = role;
        nameEl.textContent = name;
        phoneEl.textContent = phone || '—';
        phoneEl.href = phone ? 'tel:' + phone.replace(/\s+/g, '') : '#';
        modal.show();
    });
})();
