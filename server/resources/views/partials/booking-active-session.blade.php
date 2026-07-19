<div id="booking-active-session" class="booking-active-session booking-flash booking-flash-success mb-3 d-none" role="region" aria-label="Chuyến đang trong phiên đặt">
    <div class="booking-active-session-layout">
        <div class="booking-active-session-main">
            <div class="booking-active-head">
                <strong class="booking-flash-title">Chuyến đang đặt</strong>
                <span class="booking-active-trip-code" id="booking-active-trip-code">—</span>
            </div>

            <div class="booking-finding-driver" id="booking-finding-driver" aria-live="polite">
                <div class="booking-finding-driver__spin" aria-hidden="true"></div>
                <div class="booking-finding-driver__copy">
                    <strong class="booking-finding-driver__title" id="booking-finding-driver-title">Đang tìm tài xế gần bạn…</strong>
                </div>
            </div>

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

            <div class="booking-active-session-actions mt-2">
                <a href="{{ route('booking.trips') }}" class="btn btn-sm btn-outline-light">Xem chuyến / Hủy</a>
            </div>
        </div>
    </div>
</div>
