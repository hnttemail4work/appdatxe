<div class="modal fade" id="booking-qr-modal" tabindex="-1" aria-labelledby="booking-qr-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="booking-qr-modal-title">QR đặt xe</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body text-center">
                <p class="text-muted small mb-3">Khách quét mã để mở trang đặt vé.</p>
                <div id="booking-qr-modal-canvas" class="referral-qr-modal-canvas mx-auto mb-3"></div>
                <div class="input-group input-group-sm mb-3">
                    <input type="text" class="form-control" id="booking-qr-modal-url" readonly>
                    <button type="button" class="btn btn-outline-primary" id="booking-qr-modal-copy">Sao chép</button>
                </div>
                <a href="{{ route('home') }}" target="_blank" rel="noopener" class="btn btn-primary btn-sm w-100" id="booking-qr-modal-open">Mở trang đặt xe</a>
            </div>
        </div>
    </div>
</div>
