<div id="guest-trip-watch-root" class="guest-trip-watch d-none" aria-live="polite">
    <div class="guest-trip-watch-head mb-3">
        <h2 class="booking-list-title mb-1">Chuyến của bạn</h2>
        <p class="text-muted small mb-0">Theo dõi tiến trình và đánh giá tài xế sau khi hoàn thành chuyến (trong 2 ngày).</p>
    </div>
    <div id="guest-trip-watch-list" class="guest-trip-watch-list"></div>
</div>

<template id="guest-trip-watch-card-template">
    <article class="guest-trip-card" data-booking-ref="">
        <div class="guest-trip-card-header">
            <div>
                <div class="guest-trip-card-code">Mã chuyến: <code class="driver-trip-code" data-field="trip_code"></code></div>
                <div class="text-muted small" data-field="route"></div>
                <div class="text-muted small" data-field="service_date"></div>
            </div>
            <div class="text-end">
                <div class="small text-muted">Tài xế</div>
                <div class="fw-semibold" data-field="driver_name"></div>
            </div>
        </div>
        <div class="guest-trip-progress" data-field="progress_steps"></div>
        <div class="guest-trip-review d-none" data-field="review_form">
            <p class="guest-trip-review-prompt mb-2">Bạn hài lòng với tài xế?</p>
            <div class="guest-trip-sentiment-btns mb-2" role="group" aria-label="Đánh giá tài xế">
                <button type="button" class="btn btn-outline-success guest-sentiment-btn" data-sentiment="like">👍 Thích</button>
                <button type="button" class="btn btn-outline-danger guest-sentiment-btn" data-sentiment="dislike">👎 Không thích</button>
            </div>
            <label class="form-label small mb-1">Phản hồi (không bắt buộc)</label>
            <textarea class="form-control form-control-sm guest-review-comment" rows="2" maxlength="500" placeholder="Chia sẻ thêm về chuyến đi…"></textarea>
            <div class="text-danger small mt-1 d-none guest-review-error"></div>
            <button type="button" class="btn btn-primary btn-sm mt-2 guest-review-submit" disabled>Gửi phản hồi</button>
        </div>
        <div class="guest-trip-thanks d-none" data-field="thanks">
            <p class="mb-0 text-success fw-semibold">Cảm ơn bạn đã phản hồi!</p>
        </div>
        <div class="guest-trip-cancel-wrap mt-2" data-field="cancel_wrap">
            <button type="button" class="btn btn-outline-danger btn-sm guest-trip-cancel-btn">Hủy chuyến</button>
        </div>
    </article>
</template>
