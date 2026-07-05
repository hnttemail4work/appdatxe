<div id="booking-active-session" class="booking-active-session booking-flash booking-flash-success mb-3 d-none" role="region" aria-label="Chuyến đang trong phiên đặt">
    <div class="booking-active-session-layout">
        <div class="booking-active-session-main">
            <div class="booking-active-head">
                <strong class="booking-flash-title">Chuyến đang đặt</strong>
                <span class="booking-active-trip-code" id="booking-active-trip-code">—</span>
            </div>

            <p class="booking-active-contact-note mb-0" id="booking-active-session-note-contact">
                Vui lòng đợi tài xế nhận chuyến, chúng tôi sẽ liên hệ lại cho bạn qua số điện thoại của bạn.
            </p>

            <div class="booking-active-driver-card d-none" id="booking-active-driver-panel" aria-live="polite">
                <div class="booking-active-driver-photo d-none" id="booking-active-driver-vehicle-photo-wrap" aria-hidden="true">
                    <img src="" alt="" class="booking-active-driver-vehicle-img" id="booking-active-driver-vehicle-photo" loading="lazy" decoding="async">
                </div>
                <div class="booking-active-driver-copy">
                    <div class="booking-active-driver-name" id="booking-active-driver-name">—</div>
                    <div class="booking-active-driver-vehicle d-none" id="booking-active-driver-vehicle-line"></div>
                    <div class="booking-active-driver-status d-none" id="booking-active-driver-status"></div>
                    <div class="booking-active-driver-distance d-none" id="booking-active-driver-distance"></div>
                    <div class="booking-active-driver-eta d-none" id="booking-active-driver-eta"></div>
                </div>
            </div>
        </div>

        <div class="booking-active-session-referral d-none" id="booking-active-referral-wrap">
            <p class="booking-active-referral-label mb-1">Mã giới thiệu</p>
            <button type="button" class="booking-active-referral-qr-btn" id="booking-active-referral-qr-btn" aria-label="Bấm để xem mã QR giới thiệu">
                <span id="booking-active-referral-qr" class="booking-active-referral-qr" aria-hidden="true"></span>
            </button>
        </div>
    </div>
</div>

<div id="booking-active-referral-qr-overlay" class="booking-active-referral-qr-overlay d-none" role="dialog" aria-modal="true" aria-labelledby="booking-active-referral-qr-overlay-title" hidden>
    <div class="booking-active-referral-qr-overlay-backdrop" data-close-referral-qr></div>
    <div class="booking-active-referral-qr-overlay-panel">
        <div class="booking-active-referral-qr-overlay-head">
            <strong id="booking-active-referral-qr-overlay-title">Mã giới thiệu</strong>
            <button type="button" class="btn-close" data-close-referral-qr" aria-label="Đóng"></button>
        </div>
        <div id="booking-active-referral-qr-large" class="booking-active-referral-qr-large"></div>
        <p class="booking-active-referral-qr-overlay-note small mb-0">Giảm ngay 2% khi hoàn tất chuyến.</p>
    </div>
</div>
