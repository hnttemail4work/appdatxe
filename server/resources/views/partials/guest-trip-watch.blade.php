<div id="guest-trip-watch-root" class="guest-trip-watch" aria-live="polite" data-wait-progress-root>
    <div class="guest-trip-watch-head mb-3">
        <h2 class="booking-list-title mb-2">Đơn đặt của bạn</h2>
        <div class="guest-trip-watch-info" role="status">
            <span class="guest-trip-watch-info-icon" aria-hidden="true">ℹ</span>
            <p class="guest-trip-watch-info-text mb-0">Theo dõi đơn chuyến của bạn — có thể đánh giá khi hoàn tất chuyến.</p>
        </div>
    </div>
    <div id="guest-trip-watch-list" class="guest-trip-watch-list"></div>
</div>

<template id="guest-trip-watch-card-template">
    <article class="guest-trip-card" data-booking-ref="">
        <div class="guest-trip-card-header">
            <div class="guest-trip-card-trip">
                <div class="guest-trip-card-code">Mã chuyến: <code class="driver-trip-code" data-field="trip_code"></code></div>
                <div class="text-muted small" data-field="route"></div>
                <div class="text-muted small" data-field="service_date"></div>
            </div>
        </div>
        <div class="guest-trip-wait d-none" data-field="wait_progress_slot"></div>
        <div class="guest-trip-driver-panel d-none" data-field="driver_panel">
            <div class="guest-trip-driver-identity">
                <div class="guest-trip-driver-avatar-wrap">
                    <img src="" alt="" class="guest-trip-driver-avatar d-none" data-field="driver_avatar" loading="lazy" decoding="async">
                    <div class="guest-trip-driver-avatar-fallback d-none" data-field="driver_avatar_fallback" aria-hidden="true"></div>
                </div>
                <div class="guest-trip-driver-meta">
                    <div class="guest-trip-driver-label">Tài xế của bạn</div>
                    <div class="guest-trip-driver-name fw-semibold" data-field="driver_name"></div>
                    <div class="small text-muted d-none guest-trip-driver-distance" data-field="driver_distance"></div>
                    <div class="small text-muted d-none guest-trip-vehicle-caption" data-field="vehicle_info"></div>
                </div>
            </div>
            <div class="guest-trip-vehicle-visual d-none" data-field="vehicle_visual">
                <img src="" alt="Ảnh xe" class="guest-trip-vehicle-photo" data-field="vehicle_photo" loading="lazy" decoding="async">
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
