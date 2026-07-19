(function () {
  'use strict';

  function onlyDigit(value) {
    var m = String(value || '').match(/\d/);
    return m ? m[0] : '';
  }

  function digitsOnly(value) {
    return String(value || '').replace(/\D/g, '').slice(0, 6);
  }

  function boxesOf(root) {
    return Array.prototype.slice.call(root.querySelectorAll('.pin-box'));
  }

  function syncHidden(root) {
    var boxes = boxesOf(root);
    var hidden = root.querySelector('[data-pin-value]');
    if (!hidden) return;
    hidden.value = boxes.map(function (b) { return onlyDigit(b.value); }).join('');
  }

  function fill(root, digits) {
    var boxes = boxesOf(root);
    var d = digitsOnly(digits);
    boxes.forEach(function (box, i) {
      box.value = d.charAt(i) || '';
    });
    syncHidden(root);
    var focusIdx = Math.min(d.length, boxes.length - 1);
    if (boxes[focusIdx]) boxes[focusIdx].focus();
  }

  function clear(root) {
    fill(root, '');
    var first = boxesOf(root)[0];
    if (first) first.focus();
  }

  function maybeAutoSubmit(root, value) {
    if (!/^\d{6}$/.test(value || '')) return;
    var form = root.closest('form');
    if (!form) return;
    if (form.getAttribute('data-pin-autosubmit') !== '1' && root.getAttribute('data-pin-autosubmit') !== '1') {
      return;
    }
    if (form.dataset.pinSubmitting === '1') return;
    form.dataset.pinSubmitting = '1';
    window.setTimeout(function () {
      try {
        if (typeof form.requestSubmit === 'function') {
          form.requestSubmit();
        } else {
          form.submit();
        }
      } catch (e) {
        form.submit();
      }
      window.setTimeout(function () {
        form.dataset.pinSubmitting = '0';
      }, 1500);
    }, 40);
  }

  function bind(root) {
    if (!root || root.dataset.pinBound === '1') return;
    root.dataset.pinBound = '1';
    var boxes = boxesOf(root);

    boxes.forEach(function (box, index) {
      box.addEventListener('input', function () {
        var digit = onlyDigit(box.value);
        box.value = digit;
        syncHidden(root);
        if (digit && boxes[index + 1]) {
          boxes[index + 1].focus();
        }
        var value = (root.querySelector('[data-pin-value]') || {}).value || '';
        root.dispatchEvent(new CustomEvent('pin:change', {
          bubbles: true,
          detail: { value: value },
        }));
        maybeAutoSubmit(root, value);
      });

      box.addEventListener('keydown', function (e) {
        if (e.key === 'Backspace' && !box.value && boxes[index - 1]) {
          boxes[index - 1].focus();
          boxes[index - 1].value = '';
          syncHidden(root);
          e.preventDefault();
        }
        if (e.key === 'ArrowLeft' && boxes[index - 1]) {
          boxes[index - 1].focus();
          e.preventDefault();
        }
        if (e.key === 'ArrowRight' && boxes[index + 1]) {
          boxes[index + 1].focus();
          e.preventDefault();
        }
      });

      box.addEventListener('paste', function (e) {
        var text = (e.clipboardData || window.clipboardData).getData('text');
        var d = digitsOnly(text);
        if (!d) return;
        e.preventDefault();
        fill(root, d);
        root.dispatchEvent(new CustomEvent('pin:change', {
          bubbles: true,
          detail: { value: d },
        }));
        maybeAutoSubmit(root, d);
      });

      box.addEventListener('focus', function () {
        box.select();
      });
    });

    var existing = (root.querySelector('[data-pin-value]') || {}).value || '';
    if (existing) fill(root, existing);
  }

  function init(scope) {
    (scope || document).querySelectorAll('[data-pin-boxes]').forEach(bind);
  }

  window.PinInput = {
    init: init,
    fill: fill,
    clear: clear,
    value: function (root) {
      syncHidden(root);
      return (root.querySelector('[data-pin-value]') || {}).value || '';
    },
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { init(document); });
  } else {
    init(document);
  }
})();
