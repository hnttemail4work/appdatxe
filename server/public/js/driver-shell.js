/**
 * Driver map shell — drawer menu, inbox modal, overlay panels.
 */
(function () {
    var page = document.querySelector('.driver-page--map');
    if (!page) {
        return;
    }

    var drawer = document.getElementById('driver-drawer');
    var drawerBackdrop = document.getElementById('driver-drawer-backdrop');
    var drawerOpenBtn = document.getElementById('driver-drawer-open');
    var drawerCloseBtn = document.getElementById('driver-drawer-close');

    var inbox = document.getElementById('driver-inbox-modal');
    var inboxBackdrop = document.getElementById('driver-inbox-backdrop');
    var inboxCloseBtn = document.getElementById('driver-inbox-close');

    var overlay = document.getElementById('driver-overlay-panels');
    var overlayTitle = document.getElementById('driver-overlay-title');
    var tripSheet = document.getElementById('driver-trip-sheet');

    function setDrawerOpen(open) {
        if (!drawer || !drawerBackdrop) {
            return;
        }
        drawer.hidden = !open;
        drawerBackdrop.hidden = !open;
        drawer.setAttribute('aria-hidden', open ? 'false' : 'true');
        document.body.classList.toggle('driver-drawer-open', open);
        if (drawerOpenBtn) {
            drawerOpenBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
    }

    function setInboxOpen(open) {
        if (!inbox || !inboxBackdrop) {
            return;
        }
        inbox.hidden = !open;
        inboxBackdrop.hidden = !open;
    }

    var TAB_TITLES = {
        history: 'Lịch sử chạy',
        earnings: 'Thu nhập',
        wallet: 'Ví tài xế',
        invite: 'Mời bạn bè',
        customers: 'Khách của tôi',
        inbox: 'Hộp thư',
        account: 'Thông tin cá nhân',
        'account-update': 'Hồ sơ tài xế',
        settings: 'Cài đặt',
    };

    function syncOverlay(tab) {
        var showOverlay = tab && tab !== 'trips';
        var backTab = (tab && tab.indexOf('account-') === 0) ? 'account' : 'trips';

        if (overlay) {
            overlay.hidden = !showOverlay;
            overlay.classList.toggle('is-open', showOverlay);
        }

        var overlayBack = overlay && overlay.querySelector('.driver-overlay-panels__back');
        if (overlayBack) {
            overlayBack.dataset.driverTab = backTab;
            overlayBack.setAttribute('aria-label', 'Quay lại');
        }
        if (overlayTitle) {
            overlayTitle.textContent = TAB_TITLES[tab] || 'Menu';
            overlayTitle.removeAttribute('data-driver-tab');
            overlayTitle.removeAttribute('role');
            overlayTitle.removeAttribute('tabindex');
        }

        var settingsActions = overlay && overlay.querySelector('[data-settings-header-actions]');
        var overlaySpacer = overlay && overlay.querySelector('[data-driver-overlay-spacer]');
        var onSettings = tab === 'settings';
        if (settingsActions) {
            settingsActions.hidden = !onSettings;
        }
        if (overlaySpacer) {
            overlaySpacer.hidden = onSettings;
        }

        page.classList.toggle('is-panel-open', showOverlay);

        if (showOverlay && window.TripChat && typeof window.TripChat.closeAllOpen === 'function') {
            window.TripChat.closeAllOpen();
        }

        if (window.DriverBottomPanel && window.DriverBottomPanel.refresh) {
            window.DriverBottomPanel.refresh();
            return;
        }

        if (tripSheet) {
            var hasContent = tripSheet.classList.contains('is-visible')
                || tripSheet.querySelector('[data-trip-request-id], [data-schedule-id]');
            var showSheet = !showOverlay && hasContent;
            tripSheet.hidden = !showSheet;
            tripSheet.classList.toggle('is-open', showSheet);
        }
    }

    if (drawerOpenBtn) {
        drawerOpenBtn.addEventListener('click', function () {
            setInboxOpen(false);
            setDrawerOpen(true);
        });
    }
    if (drawerCloseBtn) {
        drawerCloseBtn.addEventListener('click', function () {
            setDrawerOpen(false);
        });
    }
    if (drawerBackdrop) {
        drawerBackdrop.addEventListener('click', function () {
            setDrawerOpen(false);
        });
    }
    document.querySelectorAll('[data-driver-drawer-close]').forEach(function (el) {
        el.addEventListener('click', function () {
            setDrawerOpen(false);
        });
    });

    if (inboxCloseBtn) {
        inboxCloseBtn.addEventListener('click', function () {
            setInboxOpen(false);
        });
    }
    if (inboxBackdrop) {
        inboxBackdrop.addEventListener('click', function () {
            setInboxOpen(false);
        });
    }
    document.querySelectorAll('[data-driver-inbox-close]').forEach(function (el) {
        el.addEventListener('click', function () {
            setInboxOpen(false);
        });
    });

    document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') {
            return;
        }
        setDrawerOpen(false);
        setInboxOpen(false);
    });

    page.addEventListener('drivertab:changed', function (event) {
        var tab = (event.detail && event.detail.tab) || 'trips';
        setDrawerOpen(false);
        setInboxOpen(false);
        syncOverlay(tab);
    });

    document.addEventListener('click', function (event) {
        var btn = event.target.closest('[data-driver-open-chat]');
        if (!btn) {
            return;
        }
        event.preventDefault();
        var ref = btn.getAttribute('data-driver-open-chat');
        // Ưu tiên panel chat trên chuyến (full-screen giống khách).
        var livePanel = null;
        if (ref) {
            document.querySelectorAll('.trip-chat-panel:not(.trip-chat-panel--embed)').forEach(function (el) {
                if (el.getAttribute('data-booking-reference') === ref) {
                    livePanel = el;
                }
            });
        }
        if (livePanel && window.TripChat && window.TripChat.setPanelOpen) {
            window.TripChat.setPanelOpen(livePanel, true);
            return;
        }
        if (window.DriverInbox && window.DriverInbox.openChat) {
            window.DriverInbox.openChat(btn);
            return;
        }
        if (!ref) {
            return;
        }
        var panel = document.querySelector('.trip-chat-panel[data-booking-reference="' + ref + '"]');
        if (!panel) {
            return;
        }
        if (window.TripChat && window.TripChat.setPanelOpen) {
            window.TripChat.setPanelOpen(panel, true);
            return;
        }
        var toggle = panel.querySelector('[data-chat-toggle]');
        if (toggle) {
            toggle.click();
            panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    });

    var initialTab = page.dataset.driverTabsActive || 'trips';
    syncOverlay(initialTab);

    document.querySelectorAll('[data-driver-file-field]').forEach(function (wrap) {
        var input = wrap.querySelector('input[type="file"]');
        var nameEl = wrap.querySelector('[data-file-name]');
        var trigger = wrap.querySelector('[data-file-trigger]');
        if (!input || !nameEl) {
            return;
        }

        var fallback = input.getAttribute('data-file-default') || 'Chưa có ảnh';
        var isVehicleMulti = wrap.hasAttribute('data-vehicle-multi-field');
        var vehicleFiles = [];
        var vehicleObjectUrls = [];
        var previewRow = wrap.parentElement
            ? wrap.parentElement.querySelector('[data-vehicle-new-preview]')
            : null;
        var maxVehicles = Math.max(1, parseInt(wrap.getAttribute('data-max-vehicles') || '6', 10) || 6);
        var existingCount = Math.max(0, parseInt(wrap.getAttribute('data-existing-count') || '0', 10) || 0);

        if (trigger) {
            trigger.addEventListener('click', function () {
                input.click();
            });
        }

        function revokeVehicleUrls() {
            vehicleObjectUrls.forEach(function (url) {
                URL.revokeObjectURL(url);
            });
            vehicleObjectUrls = [];
        }

        function syncVehicleInputFiles() {
            var dt = new DataTransfer();
            vehicleFiles.forEach(function (file) {
                dt.items.add(file);
            });
            input.files = dt.files;
            if (vehicleFiles.length > 0) {
                nameEl.textContent = 'Sẽ thêm ' + vehicleFiles.length + ' ảnh';
            } else {
                nameEl.textContent = fallback;
            }
        }

        function renderVehiclePreview() {
            if (!previewRow) {
                syncVehicleInputFiles();
                return;
            }
            revokeVehicleUrls();
            previewRow.innerHTML = '';
            if (!vehicleFiles.length) {
                previewRow.classList.add('d-none');
                syncVehicleInputFiles();
                return;
            }
            previewRow.classList.remove('d-none');
            vehicleFiles.forEach(function (file, index) {
                var wrapThumb = document.createElement('div');
                wrapThumb.className = 'driver-doc-thumb driver-doc-thumb--new';
                var img = document.createElement('img');
                var url = URL.createObjectURL(file);
                vehicleObjectUrls.push(url);
                img.src = url;
                img.alt = file.name;
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'driver-doc-thumb__remove';
                btn.setAttribute('aria-label', 'Bỏ ảnh này');
                btn.textContent = '×';
                btn.addEventListener('click', function () {
                    vehicleFiles.splice(index, 1);
                    syncVehicleInputFiles();
                    renderVehiclePreview();
                });
                wrapThumb.appendChild(img);
                wrapThumb.appendChild(btn);
                previewRow.appendChild(wrapThumb);
            });
            syncVehicleInputFiles();
        }

        input.addEventListener('change', function () {
            if (!isVehicleMulti) {
                var files = input.files;
                if (files && files.length > 1) {
                    nameEl.textContent = files.length + ' ảnh';
                    return;
                }
                if (files && files[0]) {
                    nameEl.textContent = files[0].name;
                    return;
                }
                nameEl.textContent = fallback;
                return;
            }

            var room = Math.max(0, maxVehicles - existingCount - vehicleFiles.length);
            Array.from(input.files || []).forEach(function (file) {
                if (room <= 0) {
                    return;
                }
                var dup = vehicleFiles.some(function (f) {
                    return f.name === file.name && f.size === file.size && f.lastModified === file.lastModified;
                });
                if (dup) {
                    return;
                }
                vehicleFiles.push(file);
                room -= 1;
            });
            input.value = '';
            renderVehiclePreview();
        });
    });

    window.DriverShell = {
        setDrawerOpen: setDrawerOpen,
        setInboxOpen: setInboxOpen,
        syncOverlay: syncOverlay,
    };
})();
