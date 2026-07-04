<div id="booking-active-session" class="booking-active-session booking-flash booking-flash-success mb-3 d-none" role="region" aria-label="Chuyến đang trong phiên đặt">

    <div class="booking-active-session-layout">

        <div class="booking-active-session-main">

            <strong class="booking-flash-title">Chuyến đang trong phiên đặt</strong>

            <p class="mb-1">

                Mã chuyến:

                <span class="booking-ticket-code" id="booking-active-trip-code">—</span>

            </p>

            <div class="small booking-flash-note" id="booking-active-session-note">

                <p class="mb-0" id="booking-active-session-note-contact">

                    Chúng tôi sẽ liên hệ bạn sớm nhất qua số điện thoại của bạn.

                </p>

            </div>

            <div class="booking-active-driver d-none" id="booking-active-driver-panel" aria-live="polite">

                <p class="booking-active-driver-title mb-2">Tài xế của bạn</p>

                <dl class="booking-active-driver-grid mb-0">

                    <div class="booking-active-driver-row">

                        <dt>Tài xế</dt>

                        <dd id="booking-active-driver-name">—</dd>

                    </div>

                    <div class="booking-active-driver-row" id="booking-active-driver-vehicle-name-row" hidden>

                        <dt>Xe</dt>

                        <dd id="booking-active-driver-vehicle-name">—</dd>

                    </div>

                    <div class="booking-active-driver-row" id="booking-active-driver-type-row" hidden>

                        <dt>Loại xe</dt>

                        <dd id="booking-active-driver-vehicle-type">—</dd>

                    </div>

                    <div class="booking-active-driver-row" id="booking-active-driver-plate-row" hidden>

                        <dt>Biển số</dt>

                        <dd id="booking-active-driver-vehicle-plate">—</dd>

                    </div>

                    <div class="booking-active-driver-row" id="booking-active-driver-proximity-row" hidden>

                        <dt>Đến điểm đón</dt>

                        <dd id="booking-active-driver-proximity">—</dd>

                    </div>

                </dl>

            </div>

        </div>

        <div class="booking-active-session-referral d-none" id="booking-active-referral-wrap">

            <p class="booking-active-referral-label mb-2">Mã giới thiệu</p>

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

            <button type="button" class="btn-close" data-close-referral-qr aria-label="Đóng"></button>

        </div>

        <div id="booking-active-referral-qr-large" class="booking-active-referral-qr-large"></div>

        <p class="booking-active-referral-qr-overlay-note small mb-0">Giảm ngay 2% khi hoàn tất chuyến.</p>

    </div>

</div>

