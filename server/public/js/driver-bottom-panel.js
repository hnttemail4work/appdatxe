/**
 * Bottom panel idle / trip sheet + TEST mock flow.
 */
(function () {
    var panel = document.getElementById('driver-bottom-panel');
    if (!panel) {
        return;
    }

    var idleEl = document.getElementById('driver-bottom-idle');
    var tripSheet = document.getElementById('driver-trip-sheet');
    var liveEl = document.getElementById('driver-trip-live');
    var mockEl = document.getElementById('driver-trip-mock');
    var readyCta = document.getElementById('driver-ready-cta');
    var readyInput = document.getElementById('driver-availability-input');
    var readyLabel = readyCta ? readyCta.querySelector('[data-ready-label]') : null;
    var testFab = document.getElementById('driver-test-trip-fab');
    var mockCta = document.getElementById('driver-mock-primary-cta');
    var page = document.querySelector('.driver-page--map');

    var mockStages = [
        'Nhận chuyến',
        'Đã đến điểm đón',
        'Bắt đầu chuyến',
        'Hoàn tất',
    ];
    var testMode = false;

    function hasLiveTrips() {
        if (panel.dataset.hasLiveTrips === '1') {
            return true;
        }
        return !!(liveEl && liveEl.querySelector('[data-trip-request-id], [data-schedule-id]'));
    }

    function syncReadyCta() {
        if (!readyCta || !readyInput) {
            return;
        }
        var on = !!readyInput.checked;
        readyCta.classList.toggle('is-on', on);
        readyCta.classList.toggle('is-off', !on);
        readyCta.setAttribute('aria-pressed', on ? 'true' : 'false');
        if (readyLabel) {
            readyLabel.textContent = on ? 'TẮT SẴN SÀNG' : 'BẬT SẴN SÀNG';
        }

        if (page) {
            page.classList.toggle('is-duty-on', on);
            page.classList.toggle('is-duty-off', !on);
        }
    }

    function syncMapFabLift() {
        if (!page) {
            return;
        }
        var height = panel.getBoundingClientRect().height || 0;
        var gap = 10;
        page.style.setProperty('--driver-map-fab-lift', Math.round(height + gap) + 'px');
    }

    function setExpanded(expanded, options) {
        options = options || {};
        var overlayOpen = page && page.classList.contains('is-panel-open');
        if (overlayOpen) {
            expanded = false;
        }

        panel.classList.toggle('is-expanded', expanded);
        panel.classList.toggle('is-idle', !expanded);
        panel.classList.toggle('is-trip', expanded);

        if (idleEl) {
            idleEl.hidden = expanded;
        }
        if (tripSheet) {
            tripSheet.hidden = !expanded;
            tripSheet.classList.toggle('is-open', expanded);
            tripSheet.classList.toggle('is-visible', expanded || hasLiveTrips());
        }

        if (expanded) {
            var useMock = options.forceMock || (testMode && !hasLiveTrips());
            if (liveEl) {
                liveEl.hidden = useMock || !hasLiveTrips();
            }
            if (mockEl) {
                mockEl.hidden = !useMock;
            }
        } else if (mockEl) {
            mockEl.hidden = true;
        }

        requestAnimationFrame(function () {
            syncMapFabLift();
            requestAnimationFrame(syncMapFabLift);
        });
    }

    function refreshPanelMode() {
        if (testMode) {
            setExpanded(true, { forceMock: !hasLiveTrips() });
            return;
        }
        setExpanded(hasLiveTrips());
    }

    function setTestMode(on) {
        testMode = !!on;
        if (testFab) {
            testFab.classList.toggle('is-active', testMode);
            testFab.setAttribute('aria-pressed', testMode ? 'true' : 'false');
            testFab.textContent = testMode ? 'TEST: Đóng' : 'TEST: Có cuốc';
        }
        if (testMode && mockEl) {
            mockEl.dataset.mockStage = '0';
            if (mockCta) {
                mockCta.textContent = mockStages[0];
            }
        }
        refreshPanelMode();
    }

    if (readyCta && readyInput) {
        readyCta.addEventListener('click', function () {
            if (readyInput.disabled) {
                return;
            }
            // Trigger existing availability toggle pipeline.
            readyInput.checked = !readyInput.checked;
            readyInput.dispatchEvent(new Event('change', { bubbles: true }));
            syncReadyCta();
        });
        readyInput.addEventListener('change', syncReadyCta);
        syncReadyCta();
    }

    if (testFab) {
        testFab.addEventListener('click', function () {
            setTestMode(!testMode);
        });
    }

    if (mockCta && mockEl) {
        mockCta.addEventListener('click', function () {
            var stage = Number(mockEl.dataset.mockStage || 0);
            if (stage >= mockStages.length - 1) {
                setTestMode(false);
                return;
            }
            stage += 1;
            mockEl.dataset.mockStage = String(stage);
            mockCta.textContent = mockStages[stage];
        });
    }

    document.addEventListener('driver:availability-sync', syncReadyCta);

    // Keep bottom panel in sync after tab changes / live trip badge updates.
    if (page) {
        page.addEventListener('drivertab:changed', function () {
            refreshPanelMode();
        });
    }

    var originalUpdate = window.__driverUpdateTripDockBadge;
    window.__driverUpdateTripDockBadge = function () {
        if (typeof originalUpdate === 'function') {
            originalUpdate();
        }
        panel.dataset.hasLiveTrips = hasLiveTrips() ? '1' : '0';
        if (!testMode) {
            refreshPanelMode();
        }
    };

    window.DriverBottomPanel = {
        refresh: refreshPanelMode,
        setTestMode: setTestMode,
        syncReadyCta: syncReadyCta,
        syncMapFabLift: syncMapFabLift,
        isTestMode: function () {
            return testMode;
        },
    };

    if (typeof ResizeObserver !== 'undefined') {
        var ro = new ResizeObserver(function () {
            syncMapFabLift();
        });
        ro.observe(panel);
    } else {
        window.addEventListener('resize', syncMapFabLift);
    }

    panel.addEventListener('transitionend', function (event) {
        if (event.target === panel && (event.propertyName === 'max-height' || event.propertyName === 'padding')) {
            syncMapFabLift();
        }
    });

    refreshPanelMode();
    syncMapFabLift();
})();
