/**
 * WebAuthn — đăng ký / xác thực sinh trắc học sau khi nhập SĐT + mật khẩu.
 */
(function () {
    var root = document.getElementById('customer-biometric-app');
    if (!root || !window.PublicKeyCredential) {
        if (root) {
            showSkipOnly();
        }
        return;
    }

    var hasCredentials = root.dataset.hasCredentials === '1';
    var csrf = document.querySelector('meta[name="csrf-token"]');
    var csrfToken = csrf ? csrf.getAttribute('content') : '';
    var alertEl = document.getElementById('customer-biometric-alert');
    var startBtn = document.getElementById('customer-biometric-start');
    var skipBtn = document.getElementById('customer-biometric-skip');

    function showAlert(message, type) {
        if (!alertEl) {
            return;
        }
        alertEl.textContent = message;
        alertEl.className = 'alert alert-' + (type || 'danger') + ' mb-3';
        alertEl.classList.remove('d-none');
    }

    function showSkipOnly() {
        if (skipBtn) {
            skipBtn.classList.remove('d-none');
        }
        if (startBtn) {
            startBtn.classList.add('d-none');
        }
        showAlert('Thiết bị hoặc trình duyệt không hỗ trợ quét khuôn mặt/vân tay.', 'warning');
    }

    function bufferToBase64url(buffer) {
        var bytes = new Uint8Array(buffer);
        var binary = '';
        bytes.forEach(function (b) { binary += String.fromCharCode(b); });
        return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
    }

    function base64urlToBuffer(value) {
        var base64 = value.replace(/-/g, '+').replace(/_/g, '/');
        while (base64.length % 4) {
            base64 += '=';
        }
        var binary = atob(base64);
        var bytes = new Uint8Array(binary.length);
        for (var i = 0; i < binary.length; i++) {
            bytes[i] = binary.charCodeAt(i);
        }
        return bytes.buffer;
    }

    function prepareOptions(options) {
        var copy = JSON.parse(JSON.stringify(options));
        copy.challenge = base64urlToBuffer(copy.challenge);
        if (copy.user && copy.user.id) {
            copy.user.id = base64urlToBuffer(copy.user.id);
        }
        ['excludeCredentials', 'allowCredentials'].forEach(function (key) {
            if (!copy[key]) {
                return;
            }
            copy[key] = copy[key].map(function (item) {
                return Object.assign({}, item, { id: base64urlToBuffer(item.id) });
            });
        });
        return copy;
    }

    function serializeCredential(credential) {
        var response = credential.response;
        var payload = {
            id: credential.id,
            rawId: bufferToBase64url(credential.rawId),
            type: credential.type,
            response: {
                clientDataJSON: bufferToBase64url(response.clientDataJSON),
            },
        };

        if (response.attestationObject) {
            payload.response.attestationObject = bufferToBase64url(response.attestationObject);
        }
        if (response.authenticatorData) {
            payload.response.authenticatorData = bufferToBase64url(response.authenticatorData);
        }
        if (response.signature) {
            payload.response.signature = bufferToBase64url(response.signature);
        }
        if (response.userHandle) {
            payload.response.userHandle = bufferToBase64url(response.userHandle);
        }

        return JSON.stringify(payload);
    }

    function postJson(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(body || {}),
        }).then(function (res) {
            return res.json().then(function (data) {
                if (!res.ok) {
                    throw new Error(data.message || 'Yêu cầu thất bại.');
                }
                return data;
            });
        });
    }

    function finishLogin(data) {
        if (data.redirect) {
            window.location.href = data.redirect;
        }
    }

    function runRegister() {
        return postJson(root.dataset.registerOptionsUrl, {})
            .then(function (options) {
                return navigator.credentials.create({ publicKey: prepareOptions(options) });
            })
            .then(function (credential) {
                return postJson(root.dataset.registerVerifyUrl, {
                    credential: serializeCredential(credential),
                });
            })
            .then(finishLogin);
    }

    function runLogin() {
        return postJson(root.dataset.loginOptionsUrl, {})
            .then(function (options) {
                return navigator.credentials.get({ publicKey: prepareOptions(options) });
            })
            .then(function (credential) {
                return postJson(root.dataset.loginVerifyUrl, {
                    credential: serializeCredential(credential),
                });
            })
            .then(finishLogin);
    }

    function runFlow() {
        if (startBtn) {
            startBtn.disabled = true;
        }
        var flow = hasCredentials ? runLogin() : runRegister();
        flow.catch(function (err) {
            showAlert(err.message || 'Không thể xác thực sinh trắc học.', 'danger');
            if (skipBtn) {
                skipBtn.classList.remove('d-none');
            }
        }).finally(function () {
            if (startBtn) {
                startBtn.disabled = false;
            }
        });
    }

    if (startBtn) {
        startBtn.addEventListener('click', runFlow);
    }

    if (skipBtn) {
        skipBtn.addEventListener('click', function () {
            skipBtn.disabled = true;
            postJson(root.dataset.skipUrl, {})
                .then(finishLogin)
                .catch(function (err) {
                    showAlert(err.message || 'Không thể tiếp tục.', 'danger');
                    skipBtn.disabled = false;
                });
        });
    }

    PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable()
        .then(function (available) {
            if (!available && skipBtn) {
                skipBtn.classList.remove('d-none');
            }
        })
        .catch(function () {
            if (skipBtn) {
                skipBtn.classList.remove('d-none');
            }
        });

    setTimeout(runFlow, 400);
})();
