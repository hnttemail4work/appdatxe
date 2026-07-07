@php
$bookingQrDiscountPercent = \App\Support\PlatformFees::bookingQrDiscountPercent();
$bookingQrDiscountLabel = rtrim(rtrim(number_format($bookingQrDiscountPercent, 1, '.', ''), '0'), '.');
@endphp
<div class="modal fade" id="guestTripCompletionModal" tabindex="-1" aria-labelledby="guestTripCompletionModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable guest-trip-completion-dialog">
        <div class="modal-content border-0 shadow guest-trip-completion-modal">
            <div class="modal-header border-0 pb-0">
                <div>
                    <p class="guest-trip-completion-eyebrow mb-1">Chuyến đã hoàn tất</p>
                    <h5 class="modal-title fw-bold mb-0" id="guestTripCompletionModalTitle">Cảm ơn bạn đã đi cùng chúng tôi</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body pt-3">
                <section id="guest-trip-completion-referral" class="guest-trip-completion-referral d-none" aria-label="Mã QR giới thiệu">
                    <h6 class="guest-trip-completion-section-title">Mã QR giới thiệu</h6>
                    <p class="guest-trip-completion-section-lead mb-2" id="guest-trip-completion-referral-note">
                        Giảm ngay {{ $bookingQrDiscountLabel }}% khi hoàn tất chuyến.
                    </p>
                    <div class="guest-trip-completion-qr-wrap">
                        <div id="guest-trip-completion-referral-qr" class="guest-trip-completion-qr" aria-hidden="true"></div>
                    </div>
                    <p class="guest-trip-completion-section-hint mb-0">Chia sẻ mã QR để bạn bè đặt xe qua link của bạn.</p>
                </section>

                <section id="guest-trip-completion-review" class="guest-trip-review guest-trip-completion-review d-none" aria-label="Đánh giá tài xế">
                    <h6 class="guest-trip-review-title">Đánh giá chuyến đi</h6>
                    <p class="guest-trip-review-lead">Bạn hài lòng với chuyến đi không?</p>
                    <div class="guest-trip-review-actions" role="group" aria-label="Chọn đánh giá trong popup">
                        <button type="button" class="guest-trip-review-btn guest-trip-review-btn--like" data-completion-review-sentiment="like">
                            <span aria-hidden="true">👍</span> Hài lòng
                        </button>
                        <button type="button" class="guest-trip-review-btn guest-trip-review-btn--dislike" data-completion-review-sentiment="dislike">
                            <span aria-hidden="true">👎</span> Chưa hài lòng
                        </button>
                    </div>
                    <div class="guest-trip-review-form d-none" id="guest-trip-completion-review-form">
                        <label class="guest-trip-review-form-label" for="guest-trip-completion-review-comment">Góp ý thêm (tuỳ chọn)</label>
                        <textarea id="guest-trip-completion-review-comment" class="form-control form-control-sm guest-trip-review-textarea" rows="2" maxlength="500" placeholder="Chia sẻ thêm về chuyến đi…"></textarea>
                        <button type="button" class="btn btn-primary btn-sm mt-2" id="guest-trip-completion-review-submit">Gửi đánh giá</button>
                    </div>
                    <div class="guest-trip-review-error d-none" id="guest-trip-completion-review-error"></div>
                </section>

                <section id="guest-trip-completion-review-done" class="guest-trip-review-done guest-trip-completion-review-done d-none" aria-live="polite">
                    <div class="guest-trip-review-done-icon" id="guest-trip-completion-review-icon" aria-hidden="true">👍</div>
                    <div>
                        <strong class="guest-trip-review-done-title" id="guest-trip-completion-review-label">Đã gửi đánh giá</strong>
                        <p class="guest-trip-review-done-comment mb-0" id="guest-trip-completion-review-comment-done"></p>
                    </div>
                </section>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>
