(function () {
    var root = document.querySelector('.customer-account-page[data-customer-tabs]');
    if (!root) {
        return;
    }

    var tabs = root.querySelectorAll('[data-customer-tab]');
    var panels = root.querySelectorAll('[data-customer-panel]');

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function (event) {
            var key = tab.dataset.customerTab;
            if (!key || tab.classList.contains('is-active')) {
                return;
            }
            event.preventDefault();
            tabs.forEach(function (item) {
                item.classList.toggle('is-active', item.dataset.customerTab === key);
            });
            panels.forEach(function (panel) {
                panel.classList.toggle('is-active', panel.dataset.customerPanel === key);
            });
            var url = new URL(window.location.href);
            if (key === 'profile') {
                url.searchParams.delete('tab');
            } else {
                url.searchParams.set('tab', key);
            }
            window.history.replaceState({}, '', url.toString());
        });
    });
})();
