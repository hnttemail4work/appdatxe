(function () {
    var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    function dismissBanner(button) {
        var banner = button.closest('[data-late-pickup-banner]');
        if (banner) {
            banner.remove();
        }
    }

    function sendContinue(button) {
        var url = button.getAttribute('data-continue-url');
        if (!url || button.disabled) {
            return;
        }

        button.disabled = true;

        fetch(url, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        })
            .then(function (r) {
                return r.json().then(function (data) {
                    return { ok: r.ok, data: data };
                });
            })
            .then(function (result) {
                if (!result.ok) {
                    throw new Error(result.data?.message || 'Không xác nhận được.');
                }
                dismissBanner(button);
            })
            .catch(function (err) {
                button.disabled = false;
                if (window.AppDialog && window.AppDialog.alert) {
                    window.AppDialog.alert(err.message || 'Không xác nhận được.', { variant: 'danger' });
                }
            });
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('.driver-late-pickup-continue');
        if (!button) {
            return;
        }
        event.preventDefault();
        sendContinue(button);
    });

    window.DriverLatePickupUi = {
        renderPrompt: function (scheduleId, prompt) {
            if (!prompt || !prompt.active) {
                var existing = document.querySelector('[data-late-pickup-banner][data-schedule-id="' + scheduleId + '"]');
                if (existing) {
                    existing.remove();
                }
                return;
            }

            var card = document.querySelector('[data-schedule-id="' + scheduleId + '"]');
            if (!card) {
                return;
            }

            var workflow = card.querySelector('.driver-workflow-compact');
            if (!workflow) {
                return;
            }

            var banner = workflow.querySelector('[data-late-pickup-banner]');
            if (!banner) {
                banner = document.createElement('div');
                banner.className = 'driver-late-pickup-banner mb-2';
                banner.setAttribute('data-late-pickup-banner', '');
                banner.setAttribute('data-schedule-id', String(scheduleId));
                workflow.insertBefore(banner, workflow.querySelector('.driver-workflow-compact-steps'));
            }

            banner.innerHTML = ''
                + '<strong>' + prompt.message + '</strong>'
                + '<p class="mb-2 small">' + prompt.hint + '</p>'
                + '<button type="button" class="btn btn-warning btn-sm driver-late-pickup-continue"'
                + ' data-continue-url="' + prompt.continue_url + '">Tiếp tục</button>';
        },
    };
})();
