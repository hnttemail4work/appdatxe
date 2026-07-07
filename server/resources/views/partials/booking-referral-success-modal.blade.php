@php
$bookingQrDiscountPercent = \App\Support\PlatformFees::bookingQrDiscountPercent();
$bookingQrDiscountLabel = rtrim(rtrim(number_format($bookingQrDiscountPercent, 1, '.', ''), '0'), '.');
@endphp
<div class="modal fade" id="booking-referral-success-modal" tabindex="-1" aria-labelledby="booking-referral-success-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content booking-referral-success-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" id="booking-referral-success-title">Mã giới thiệu GT của bạn</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body text-center pt-2">
                <p class="small text-muted mb-3">
                    Mã giới thiệu, sử dụng sau khi hoàn tất chuyến, giảm
                    <strong id="booking-referral-success-percent">{{ $bookingQrDiscountLabel }}</strong>% bạn bè người thân.
                </p>
                <div id="booking-referral-success-qr" class="booking-referral-success-qr mx-auto mb-3"></div>
                <p class="mb-2"><code class="fs-5" id="booking-referral-success-code">—</code></p>
                <div class="input-group input-group-sm mb-3">
                    <input type="text" class="form-control" id="booking-referral-success-url" readonly>
                    <button type="button" class="btn btn-outline-primary" id="booking-referral-success-copy">Sao chép</button>
                </div>
                <div class="d-flex flex-column flex-sm-row gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm flex-fill" id="booking-referral-success-download">Tải ảnh QR</button>
                    <button type="button" class="btn btn-primary btn-sm flex-fill" id="booking-referral-success-share">Chia sẻ link</button>
                </div>
            </div>
        </div>
    </div>
</div>
