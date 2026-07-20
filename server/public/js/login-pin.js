(function () {
  'use strict';

  var root = document.querySelector('[data-login-pin]');
  if (!root) return;

  var V = window.AuthFieldValidation;
  var form = root.querySelector('#login-pin-form') || root.querySelector('form');
  var phoneStep = root.querySelector('[data-login-step="phone"]');
  var pinStep = root.querySelector('[data-login-step="pin"]');
  var phoneInput = root.querySelector('#login-phone');
  var btnContinue = root.querySelector('[data-login-continue]');
  var backLink = root.querySelector('[data-auth-back]');
  var titleEl = root.querySelector('[data-auth-title]');
  var homeUrl = backLink ? backLink.getAttribute('href') : '/';
  var checkUrl = root.getAttribute('data-check-phone-url') || '/login/check-phone';
  var csrf = (form && form.querySelector('input[name="_token"]'))
    ? form.querySelector('input[name="_token"]').value
    : (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
  var checking = false;

  function setTitle(text) {
    if (titleEl) titleEl.textContent = text;
  }

  var forDriver = root.getAttribute('data-for-driver') === '1';
  var loginTitle = forDriver ? 'Đăng nhập tài xế' : 'Đăng nhập';

  function showPhone() {
    if (phoneStep) phoneStep.hidden = false;
    if (pinStep) pinStep.hidden = true;
    setTitle(loginTitle);
    if (backLink) {
      backLink.setAttribute('href', homeUrl);
      backLink.onclick = null;
    }
    if (phoneInput) {
      phoneInput.required = true;
      phoneInput.removeAttribute('tabindex');
      phoneInput.focus();
    }
  }

  function showPin() {
    if (phoneStep) phoneStep.hidden = true;
    if (pinStep) pinStep.hidden = false;
    setTitle('Nhập PIN');
    if (phoneInput) {
      // Tránh browser/autofill kéo lại ô SĐT khi đang ở bước PIN.
      phoneInput.required = false;
      phoneInput.setAttribute('tabindex', '-1');
      phoneInput.blur();
    }
    if (backLink) {
      backLink.setAttribute('href', '#');
      backLink.onclick = function (e) {
        e.preventDefault();
        showPhone();
      };
    }
    var wrap = root.querySelector('[data-pin-boxes]');
    if (wrap && window.PinInput) {
      PinInput.clear(wrap);
    } else {
      var first = root.querySelector('.pin-box');
      if (first) first.focus();
    }
  }

  function phoneOk() {
    if (!phoneInput) return false;
    if (V) {
      V.clearError(phoneInput);
      var msg = V.validatePhone(phoneInput.value);
      if (msg) {
        V.showError(phoneInput, msg);
        phoneInput.focus();
        return false;
      }
      return true;
    }
    var digits = String(phoneInput.value || '').replace(/\D/g, '');
    return /^0\d{8,10}$/.test(digits);
  }

  function continueAfterPhoneCheck() {
    if (!phoneOk() || checking) return;
    checking = true;
    if (btnContinue) btnContinue.disabled = true;

    var body = new URLSearchParams();
    body.set('phone', phoneInput.value || '');
    body.set('_token', csrf);
    if (forDriver) {
      body.set('for_driver', '1');
    }

    fetch(checkUrl, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrf,
      },
      body: body,
      credentials: 'same-origin',
    })
      .then(function (res) {
        return res.json().then(function (data) {
          return { ok: res.ok, status: res.status, data: data || {} };
        });
      })
      .then(function (result) {
        var data = result.data;
        if (data.status === 'missing' && data.register_url) {
          window.location.href = data.register_url;
          return;
        }
        if (data.status === 'needs_otp' && data.otp_url) {
          window.location.href = data.otp_url;
          return;
        }
        if (data.status === 'inactive') {
          if (V) {
            V.showError(phoneInput, data.message || 'Tài khoản đang bị khóa.');
          }
          phoneInput.focus();
          return;
        }
        if (data.status === 'active') {
          showPin();
          return;
        }
        if (V) {
          V.showError(phoneInput, data.message || 'Không kiểm tra được số điện thoại.');
        }
      })
      .catch(function () {
        if (V) {
          V.showError(phoneInput, 'Không kết nối được máy chủ. Thử lại.');
        }
      })
      .finally(function () {
        checking = false;
        if (btnContinue) btnContinue.disabled = false;
      });
  }

  if (btnContinue) {
    btnContinue.addEventListener('click', function () {
      continueAfterPhoneCheck();
    });
  }

  if (phoneInput) {
    phoneInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        continueAfterPhoneCheck();
      }
    });
    if (V) V.bindClearOnInput(phoneStep || root);
  }

  if (form) {
    form.addEventListener('submit', function (e) {
      if (!phoneOk()) {
        e.preventDefault();
        showPhone();
        return;
      }
      var wrap = root.querySelector('[data-pin-boxes]');
      if (V && wrap) {
        if (!V.validatePinWrap(wrap)) {
          e.preventDefault();
          showPin();
        }
        return;
      }
      var value = wrap && window.PinInput ? PinInput.value(wrap) : '';
      if (!/^\d{6}$/.test(value)) {
        e.preventDefault();
        showPin();
      }
    });
  }

  if (pinStep && !pinStep.hidden) {
    showPin();
  } else if (root.getAttribute('data-auto-check-phone') === '1' && phoneInput && phoneOk()) {
    continueAfterPhoneCheck();
  }
})();
