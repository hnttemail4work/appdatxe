/**
 * Validation chung — focus ô đầu tiên thiếu, viền đỏ, "Vui lòng điền {tên trường}".
 * Dùng với form [novalidate] và các trường [required] hoặc [data-validate-required].
 */
(function (global) {
    'use strict';

    var FEEDBACK_CLASS = 'guest-field-error';
    var INVALID_CLASS = 'is-invalid';

    function isFieldEmpty(field) {
        if (!field || field.disabled) {
            return false;
        }
        if (field.type === 'checkbox' || field.type === 'radio') {
            if (field.type === 'radio' && field.name) {
                var checked = field.form
                    ? field.form.querySelector('input[type="radio"][name="' + field.name + '"]:checked')
                    : null;
                return !checked;
            }
            return !field.checked;
        }
        if (field.type === 'file') {
            return !field.files || field.files.length === 0;
        }
        if (field.tagName === 'SELECT') {
            return String(field.value || '').trim() === '';
        }
        return String(field.value || '').trim() === '';
    }

    function fieldLabel(field) {
        if (field.dataset.validateLabel) {
            return field.dataset.validateLabel.trim();
        }
        var id = field.getAttribute('id');
        if (id) {
            var root = field.closest('form') || document;
            var label = root.querySelector('label[for="' + id + '"]');
            if (label) {
                return label.textContent.replace(/\*/g, '').trim();
            }
        }
        var wrap = field.closest('.col-md-6, .col-12, .mb-3, .booking-panel-section');
        if (wrap) {
            var near = wrap.querySelector('label.form-label');
            if (near) {
                return near.textContent.replace(/\*/g, '').trim();
            }
        }
        return field.getAttribute('name') || field.getAttribute('aria-label') || 'trường này';
    }

    function feedbackElement(field) {
        var existing = field.parentElement
            ? field.parentElement.querySelector('.' + FEEDBACK_CLASS + '[data-for="' + (field.id || field.name) + '"]')
            : null;
        if (existing) {
            return existing;
        }
        var el = document.createElement('div');
        el.className = 'invalid-feedback d-block ' + FEEDBACK_CLASS;
        el.setAttribute('data-for', field.id || field.name || '');
        field.insertAdjacentElement('afterend', el);
        return el;
    }

    function clearField(field) {
        if (!field) {
            return;
        }
        field.classList.remove(INVALID_CLASS);
        var fb = field.nextElementSibling;
        if (fb && fb.classList.contains(FEEDBACK_CLASS)) {
            fb.textContent = '';
            fb.classList.remove('d-block');
        }
    }

    function showFieldError(field, message) {
        field.classList.add(INVALID_CLASS);
        var fb = feedbackElement(field);
        fb.textContent = message;
        fb.classList.add('d-block');
    }

    function collectFields(container, selector) {
        if (!container) {
            return [];
        }
        var sel = selector || '[required], [data-validate-required]';
        return Array.prototype.filter.call(container.querySelectorAll(sel), function (field) {
            if (field.type === 'hidden' || field.disabled) {
                return false;
            }
            if (field.type === 'radio') {
                return field === container.querySelector('input[type="radio"][name="' + field.name + '"]');
            }
            return true;
        });
    }

    function validateContainer(container, options) {
        options = options || {};
        var fields = collectFields(container, options.selector);
        var firstInvalid = null;

        fields.forEach(function (field) {
            if (!options.keepOthers) {
                clearField(field);
            }
        });

        fields.some(function (field) {
            if (!isFieldEmpty(field)) {
                return false;
            }
            firstInvalid = field;
            return true;
        });

        if (!firstInvalid) {
            return { valid: true, field: null };
        }

        var label = fieldLabel(firstInvalid);
        var message = options.message
            ? options.message(label, firstInvalid)
            : 'Vui lòng điền ' + label;

        showFieldError(firstInvalid, message);

        if (options.focus !== false) {
            firstInvalid.focus({ preventScroll: true });
            firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        return { valid: false, field: firstInvalid, message: message };
    }

    function bindClearOnInput(container) {
        if (!container) {
            return;
        }
        container.addEventListener('input', function (e) {
            var t = e.target;
            if (t && (t.matches('[required]') || t.matches('[data-validate-required]'))) {
                clearField(t);
            }
        });
        container.addEventListener('change', function (e) {
            var t = e.target;
            if (t && (t.matches('[required]') || t.matches('[data-validate-required]'))) {
                clearField(t);
            }
        });
    }

    global.FormFieldValidation = {
        isEmpty: isFieldEmpty,
        label: fieldLabel,
        clear: clearField,
        clearAll: function (container) {
            collectFields(container).forEach(clearField);
        },
        validate: validateContainer,
        validateFirst: function (container, options) {
            return validateContainer(container, options).valid;
        },
        bindClearOnInput: bindClearOnInput,
    };
})(window);
