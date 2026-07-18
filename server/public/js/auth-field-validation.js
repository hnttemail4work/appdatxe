/**
 * Validation auth dùng chung — SĐT VN, PIN, mã 6 số, required, email optional.
 * Hiện lỗi dưới field (.auth-field-error / .invalid-feedback).
 */
(function (global) {
  'use strict';

  var MSG = {
    phoneRequired: 'Vui lòng nhập số điện thoại.',
    phoneInvalid: 'Số điện thoại không đúng định dạng Việt Nam.',
    pinRequired: 'Vui lòng nhập PIN 6 số.',
    pinDigits: 'PIN phải gồm đúng 6 chữ số.',
    pinMismatch: 'Nhập lại PIN không khớp.',
    codeRequired: 'Vui lòng nhập mã 6 số.',
    codeDigits: 'Mã phải gồm đúng 6 chữ số.',
    terms: 'Vui lòng đồng ý với điều khoản.',
    emailInvalid: 'Email không đúng định dạng.',
    requiredFill: 'Vui lòng điền ',
    requiredChoose: 'Vui lòng chọn ',
  };

  function normalizePhone(value) {
    var digits = String(value || '').replace(/\D/g, '');
    if (digits.indexOf('84') === 0 && digits.length >= 11) {
      return '0' + digits.slice(2);
    }
    return digits;
  }

  function validatePhone(value) {
    var digits = normalizePhone(value);
    if (!digits) return MSG.phoneRequired;
    if (!/^0\d{8,10}$/.test(digits)) return MSG.phoneInvalid;
    return '';
  }

  function validatePin(value) {
    var v = String(value || '').replace(/\D/g, '');
    if (!v) return MSG.pinRequired;
    if (!/^\d{6}$/.test(v)) return MSG.pinDigits;
    return '';
  }

  function pinsMatch(a, b) {
    var pa = String(a || '').replace(/\D/g, '');
    var pb = String(b || '').replace(/\D/g, '');
    if (!/^\d{6}$/.test(pa)) return validatePin(pa);
    if (pa !== pb) return MSG.pinMismatch;
    return '';
  }

  function validateCode(value) {
    var v = String(value || '').replace(/\D/g, '');
    if (!v) return MSG.codeRequired;
    if (!/^\d{6}$/.test(v)) return MSG.codeDigits;
    return '';
  }

  function validateEmailOptional(value) {
    var v = String(value || '').trim();
    if (!v) return '';
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) return MSG.emailInvalid;
    return '';
  }

  function fieldLabel(el) {
    if (!el) return 'trường này';
    if (el.dataset && el.dataset.validateLabel) return el.dataset.validateLabel.trim();
    var block = el.closest('.auth-field-block, .register-file-field, .mb-3, .col-12, .col-md-6, .auth-terms-row');
    var label = block && (
      block.querySelector('.auth-field-label') ||
      block.querySelector('.register-file-tile-label') ||
      block.querySelector('label')
    );
    if (label) return label.textContent.replace(/\*/g, '').trim();
    return el.name || 'trường này';
  }

  function errorNode(el) {
    if (!el) return null;
    var block = el.closest('.auth-field-block, .auth-pin-row, [data-auth-pin-block], .register-file-field, .mb-3, .col-12, .auth-terms-row');
    if (block) {
      var existing = block.querySelector('.auth-field-error, .invalid-feedback');
      if (existing) return existing;
      var node = document.createElement('div');
      node.className = 'auth-field-error';
      block.appendChild(node);
      return node;
    }
    var next = el.parentElement && el.parentElement.querySelector('.auth-field-error, .invalid-feedback');
    return next || null;
  }

  function showError(el, message) {
    if (!el) return;
    el.classList.add('is-invalid');
    var wrap = el.closest('.register-file-field');
    if (wrap) wrap.classList.add('is-invalid');
    var node = errorNode(el);
    if (node) {
      node.textContent = message || '';
      node.classList.add('d-block');
      node.hidden = false;
      node.removeAttribute('hidden');
    }
  }

  function clearError(el) {
    if (!el) return;
    el.classList.remove('is-invalid');
    var wrap = el.closest('.register-file-field');
    if (wrap) wrap.classList.remove('is-invalid');
    var node = errorNode(el);
    if (node) {
      node.textContent = '';
      node.classList.remove('d-block');
      node.hidden = true;
    }
  }

  function isEmpty(el) {
    if (!el || el.disabled) return false;
    if (el.type === 'checkbox') return !el.checked;
    if (el.type === 'file') return !el.files || el.files.length === 0;
    if (el.tagName === 'SELECT') return String(el.value || '').trim() === '';
    return String(el.value || '').trim() === '';
  }

  function validateRequired(el) {
    if (!el || !el.required) return '';
    if (!isEmpty(el)) return '';
    if (el.type === 'checkbox') return MSG.terms;
    if (el.type === 'file' || el.tagName === 'SELECT') return MSG.requiredChoose + fieldLabel(el) + '.';
    return MSG.requiredFill + fieldLabel(el) + '.';
  }

  /**
   * Validate một panel/step. Trả về first invalid element hoặc null.
   * options.extraFiles: { name: FileList|Array|number } cho photo_vehicles[]
   */
  function validatePanel(panel, options) {
    options = options || {};
    if (!panel) return null;
    var firstInvalid = null;

    var fields = panel.querySelectorAll('input, select, textarea');
    Array.prototype.forEach.call(fields, function (el) {
      if (!el.name || el.disabled || el.type === 'hidden' || el.classList.contains('pin-box')) return;
      if (el.name === 'pin_draft' || el.name === 'pin_confirm_draft') return;
      if (el.dataset.wizardSkipValidity === '1' && el.name !== 'email') return;

      clearError(el);
      var msg = '';

      if (el.name === 'phone' || el.dataset.validate === 'phone') {
        msg = validatePhone(el.value);
      } else if (el.name === 'email' || el.dataset.validate === 'email') {
        msg = validateEmailOptional(el.value);
      } else if (el.name === 'password' || el.dataset.validate === 'pin') {
        msg = validatePin(el.value);
      } else if (el.name === 'code' || el.dataset.validate === 'code') {
        msg = validateCode(el.value);
      } else if (el.name === 'terms' || el.id === 'termsCheck' || el.id === 'customerTermsCheck') {
        if (!el.checked) msg = MSG.terms;
      } else if (el.required) {
        msg = validateRequired(el);
      }

      if (msg) {
        showError(el, msg);
        if (!firstInvalid) firstInvalid = el;
      }
    });

    return firstInvalid;
  }

  function pinBlock(wrap) {
    return wrap && wrap.closest ? wrap.closest('[data-auth-pin-block], .auth-field-block') : null;
  }

  function showPinError(wrap, message) {
    var block = pinBlock(wrap);
    if (!block) return;
    var boxes = block.querySelectorAll('.pin-box');
    Array.prototype.forEach.call(boxes, function (b) { b.classList.add('is-invalid'); });
    var node = block.querySelector('.auth-field-error, .invalid-feedback');
    if (!node) {
      node = document.createElement('div');
      node.className = 'auth-field-error';
      block.appendChild(node);
    }
    node.textContent = message || '';
    node.classList.add('d-block');
    node.hidden = false;
  }

  function clearPinError(wrap) {
    var block = pinBlock(wrap);
    if (!block) return;
    var boxes = block.querySelectorAll('.pin-box');
    Array.prototype.forEach.call(boxes, function (b) { b.classList.remove('is-invalid'); });
    var node = block.querySelector('.auth-field-error, .invalid-feedback');
    if (node) {
      node.textContent = '';
      node.classList.remove('d-block');
      node.hidden = true;
    }
  }

  function pinValueFrom(wrap) {
    if (!wrap) return '';
    if (global.PinInput && typeof PinInput.value === 'function') return PinInput.value(wrap);
    var hidden = wrap.querySelector('[data-pin-value]');
    return hidden ? String(hidden.value || '') : '';
  }

  function validatePinWrap(wrap, asCode) {
    var value = pinValueFrom(wrap);
    var msg = asCode ? validateCode(value) : validatePin(value);
    if (msg) {
      showPinError(wrap, msg);
      return false;
    }
    clearPinError(wrap);
    return true;
  }

  function bindClearOnInput(scope) {
    var root = scope || document;
    root.addEventListener('input', function (e) {
      var el = e.target;
      if (!el) return;
      if (el.classList && el.classList.contains('pin-box')) {
        clearPinError(el.closest('[data-pin-boxes]'));
        return;
      }
      if (!el.name) return;
      if (!isEmpty(el) || el.name === 'email') clearError(el);
    });
    root.addEventListener('change', function (e) {
      var el = e.target;
      if (!el || !el.name) return;
      if (el.type === 'checkbox' && el.checked) clearError(el);
      if (el.type === 'file' && el.files && el.files.length) clearError(el);
      if (el.tagName === 'SELECT' && !isEmpty(el)) clearError(el);
    });
  }

  function bindPhoneSubmit(form) {
    if (!form) return;
    bindClearOnInput(form);
    form.addEventListener('submit', function (e) {
      var phone = form.querySelector('[name="phone"]');
      if (!phone) return;
      clearError(phone);
      var msg = validatePhone(phone.value);
      if (msg) {
        e.preventDefault();
        showError(phone, msg);
        phone.focus();
      }
    });
  }

  function bindCodeSubmit(form) {
    if (!form) return;
    bindClearOnInput(form);
    form.addEventListener('submit', function (e) {
      var wrap = form.querySelector('[data-pin-boxes]');
      if (!wrap) return;
      if (!validatePinWrap(wrap, true)) {
        e.preventDefault();
        var first = wrap.querySelector('.pin-box');
        if (first) first.focus();
      }
    });
  }

  global.AuthFieldValidation = {
    MSG: MSG,
    normalizePhone: normalizePhone,
    validatePhone: validatePhone,
    validatePin: validatePin,
    pinsMatch: pinsMatch,
    validateCode: validateCode,
    validateEmailOptional: validateEmailOptional,
    validateRequired: validateRequired,
    validatePanel: validatePanel,
    showError: showError,
    clearError: clearError,
    showPinError: showPinError,
    clearPinError: clearPinError,
    validatePinWrap: validatePinWrap,
    pinValueFrom: pinValueFrom,
    isEmpty: isEmpty,
    fieldLabel: fieldLabel,
    bindClearOnInput: bindClearOnInput,
    bindPhoneSubmit: bindPhoneSubmit,
    bindCodeSubmit: bindCodeSubmit,
  };
})(window);
