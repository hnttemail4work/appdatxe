/**
 * Chặn sớm SĐT trên form đăng ký — tái dùng POST login.checkPhone.
 * missing → tiếp tục; needs_otp → OTP; inactive → lỗi; active → login.
 */
(function () {
    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') || '' : '';
    }

    /**
     * @param {{
     *   phone: string,
     *   checkUrl: string,
     *   forDriver?: boolean,
     *   onMissing: function(): void,
     *   onInactive?: function(string): void,
     *   onError?: function(string): void
     * }} opts
     */
    function check(opts) {
        var phone = String(opts.phone || '').trim();
        var checkUrl = opts.checkUrl;
        if (!checkUrl) {
            if (opts.onError) opts.onError('Thiếu URL kiểm tra số điện thoại.');
            return Promise.resolve();
        }

        var body = new URLSearchParams();
        body.set('phone', phone);
        body.set('_token', csrfToken());
        if (opts.forDriver) {
            body.set('for_driver', '1');
        }

        return fetch(checkUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: body,
            credentials: 'same-origin',
        })
            .then(function (res) {
                return res.json().then(function (data) {
                    return { ok: res.ok, data: data || {} };
                });
            })
            .then(function (result) {
                var data = result.data;
                if (data.status === 'needs_otp' && data.otp_url) {
                    window.location.href = data.otp_url;
                    return;
                }
                if (data.status === 'active' && data.login_url) {
                    window.location.href = data.login_url;
                    return;
                }
                if (data.status === 'inactive') {
                    if (opts.onInactive) {
                        opts.onInactive(data.message || 'Tài khoản đang bị khóa.');
                    } else if (opts.onError) {
                        opts.onError(data.message || 'Tài khoản đang bị khóa.');
                    }
                    return;
                }
                if (data.status === 'missing') {
                    opts.onMissing();
                    return;
                }
                if (opts.onError) {
                    opts.onError(data.message || 'Không kiểm tra được số điện thoại.');
                }
            })
            .catch(function () {
                if (opts.onError) {
                    opts.onError('Không kết nối được máy chủ. Thử lại.');
                }
            });
    }

    window.AuthPhoneGate = { check: check };
})();
