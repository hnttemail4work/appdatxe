/**
 * Co giãn ô nhập / combobox theo độ dài chữ bên trong (field-sizing + fallback).
 */
(function () {
    var SELECTOR = [
        'select[name="vehicle_type"]',
        'select.vehicle-type-select',
        '.fields-auto-size input.form-control:not([type="file"]):not([type="hidden"]):not([type="checkbox"]):not([type="radio"]):not([type="submit"]):not([type="button"])',
        '.fields-auto-size select.form-select',
        'input.field-auto-size',
        'select.field-auto-size',
    ].join(',');

    var mirror = null;
    var supportsFieldSizing = false;

    try {
        supportsFieldSizing = typeof CSS !== 'undefined'
            && CSS.supports
            && CSS.supports('field-sizing', 'content');
    } catch (e) {
        supportsFieldSizing = false;
    }

    function ensureMirror() {
        if (mirror) {
            return mirror;
        }
        mirror = document.createElement('span');
        mirror.setAttribute('aria-hidden', 'true');
        mirror.style.cssText = [
            'position:absolute',
            'visibility:hidden',
            'white-space:pre',
            'height:0',
            'overflow:scroll',
            'top:0',
            'left:0',
            'pointer-events:none',
        ].join(';');
        document.body.appendChild(mirror);
        return mirror;
    }

    function copyFont(from, to) {
        var style = window.getComputedStyle(from);
        to.style.font = style.font;
        to.style.fontSize = style.fontSize;
        to.style.fontFamily = style.fontFamily;
        to.style.fontWeight = style.fontWeight;
        to.style.letterSpacing = style.letterSpacing;
        to.style.textTransform = style.textTransform;
        to.style.padding = style.padding;
        to.style.border = style.border;
        to.style.boxSizing = style.boxSizing;
    }

    function textFor(el) {
        if (el.tagName === 'SELECT') {
            var opt = el.options[el.selectedIndex];
            return opt ? String(opt.text || '') : '';
        }
        return String(el.value || el.placeholder || '');
    }

    function measureWidth(el) {
        var m = ensureMirror();
        copyFont(el, m);
        m.textContent = textFor(el) || '—';
        var extra = el.tagName === 'SELECT' ? 36 : 24;
        return Math.ceil(m.scrollWidth + extra);
    }

    function parentMax(el) {
        var parent = el.parentElement;
        if (!parent) {
            return window.innerWidth;
        }
        var style = window.getComputedStyle(parent);
        var pad = (parseFloat(style.paddingLeft) || 0) + (parseFloat(style.paddingRight) || 0);
        return Math.max(0, parent.clientWidth - pad);
    }

    function sizeField(el) {
        if (!el || el.disabled || el.hidden) {
            return;
        }
        if (supportsFieldSizing) {
            el.style.width = '';
            el.style.minWidth = '';
            return;
        }

        var min = el.tagName === 'SELECT' ? 96 : 72;
        var max = parentMax(el) || window.innerWidth;
        var width = Math.min(max, Math.max(min, measureWidth(el)));
        el.style.width = width + 'px';
        el.style.maxWidth = '100%';
    }

    function bind(el) {
        if (!el || el.dataset.autoSizeBound === '1') {
            return;
        }
        el.dataset.autoSizeBound = '1';
        el.classList.add('field-auto-size');
        sizeField(el);

        el.addEventListener('input', function () {
            sizeField(el);
        });
        el.addEventListener('change', function () {
            sizeField(el);
        });
    }

    function init(root) {
        (root || document).querySelectorAll(SELECTOR).forEach(bind);
    }

    function refresh() {
        init();
        document.querySelectorAll(SELECTOR).forEach(sizeField);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', refresh);
    } else {
        refresh();
    }

    document.addEventListener('drivertab:changed', refresh);
    window.addEventListener('resize', refresh);

    window.FieldAutoSize = {
        init: init,
        size: sizeField,
        refresh: refresh,
    };
})();
