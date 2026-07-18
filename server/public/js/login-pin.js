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

  function setTitle(text) {
    if (titleEl) titleEl.textContent = text;
  }

  function showPhone() {
    if (phoneStep) phoneStep.hidden = false;
    if (pinStep) pinStep.hidden = true;
    setTitle('Đăng nhập');
    if (backLink) {
      backLink.setAttribute('href', homeUrl);
      backLink.onclick = null;
    }
    if (phoneInput) phoneInput.focus();
  }

  function showPin() {
    if (phoneStep) phoneStep.hidden = true;
    if (pinStep) pinStep.hidden = false;
    setTitle('Nhập PIN');
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

  if (btnContinue) {
    btnContinue.addEventListener('click', function () {
      if (!phoneOk()) return;
      showPin();
    });
  }

  if (phoneInput) {
    phoneInput.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        if (btnContinue) btnContinue.click();
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
  }
})();
