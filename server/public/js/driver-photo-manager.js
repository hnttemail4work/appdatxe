/** @deprecated Dùng photo-upload-slots.js */
(function () {
    if (window.PhotoUploadSlots) {
        return;
    }
    var s = document.createElement('script');
    s.src = '/js/photo-upload-slots.js';
    document.head.appendChild(s);
})();
