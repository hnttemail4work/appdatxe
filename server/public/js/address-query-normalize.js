/**
 * Chuẩn hoá chuỗi tìm địa chỉ (khu phố → kp) — dùng chung sheet / autocomplete / map picker.
 */
(function (global) {
    /** Bản dùng khi gọi API — trim + thay khu phố. */
    function normalize(query) {
        var input = String(query || '').trim();
        if (!input) {
            return '';
        }
        var out = input.replace(/\bkhu\s*ph[oốớ]\b/giu, 'kp');
        out = out.replace(/\s{2,}/g, ' ').trim();
        return out || input;
    }

    /**
     * Chỉ thay "khu phố" → "kp" trên ô nhập — không trim để gõ khoảng trắng bình thường.
     * @returns {string} query đã normalize (đã trim) để search
     */
    function applyToInput(inputEl) {
        if (!inputEl) {
            return '';
        }
        var raw = String(inputEl.value || '');
        var next = raw.replace(/\bkhu\s*ph[oốớ]\b/giu, 'kp');
        // Gộp khoảng trắng đôi giữa từ, nhưng giữ khoảng trắng đầu/cuối đang gõ.
        next = next.replace(/([^\s])\s{2,}([^\s])/g, '$1 $2');
        if (next !== raw) {
            var start = typeof inputEl.selectionStart === 'number' ? inputEl.selectionStart : next.length;
            var end = typeof inputEl.selectionEnd === 'number' ? inputEl.selectionEnd : next.length;
            var delta = next.length - raw.length;
            inputEl.value = next;
            try {
                var ns = Math.max(0, Math.min(next.length, start + delta));
                var ne = Math.max(0, Math.min(next.length, end + delta));
                inputEl.setSelectionRange(ns, ne);
            } catch (e) {
            }
        }
        return String(inputEl.value || '').trim();
    }

    global.AddressQueryNormalize = {
        normalize: normalize,
        applyToInput: applyToInput,
    };
})(window);
