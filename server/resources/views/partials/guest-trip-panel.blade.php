<div class="guest-trip-page-panel" id="guest-trip-page-panel">

    <div id="guest-trip-empty" class="guest-trip-empty">

        <div class="guest-trip-empty__icon" aria-hidden="true">

            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">

                <path d="M5 17h14v-4H5v4zM6 13l2-7h8l2 7M7 17v2M17 17v2" stroke-linecap="round" stroke-linejoin="round"/>

            </svg>

        </div>

        <h2 class="guest-trip-empty__title">Chưa có chuyến nào</h2>

        <p class="guest-trip-empty__text">Sau khi đặt xe, thông tin chuyến và đánh giá sẽ hiển thị tại đây.</p>

    </div>



    <article id="guest-trip-card" class="guest-trip-card d-none" aria-live="polite">

        <header class="guest-trip-card__head">

            <div class="guest-trip-card__id">

                <span class="guest-trip-card__id-label">Mã chuyến</span>

                <code class="guest-trip-card__code" data-field="trip_code">—</code>

            </div>

        </header>



        <div class="guest-trip-card__hero">

            <div class="guest-trip-route" data-field="route_wrap">

                <span class="guest-trip-route__city" data-field="route_from">—</span>

                <span class="guest-trip-route__arrow" aria-hidden="true">

                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">

                        <path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/>

                    </svg>

                </span>

                <span class="guest-trip-route__city" data-field="route_to">—</span>

            </div>

            <div class="guest-trip-card__meta">

                <div class="guest-trip-card__schedule d-none" data-field="schedule_wrap">

                    <svg class="guest-trip-card__schedule-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">

                        <rect x="3" y="4" width="18" height="18" rx="2"/>

                        <path d="M16 2v4M8 2v4M3 10h18"/>

                    </svg>

                    <span data-field="schedule_display"></span>

                </div>

                <span class="guest-trip-vehicle-badge d-none" data-field="vehicle_label"></span>

            </div>

        </div>



        <div class="guest-trip-stops">

            <div class="guest-trip-stop guest-trip-stop--pickup d-none" data-field="pickup_wrap">

                <div class="guest-trip-stop__rail" aria-hidden="true"></div>

                <div class="guest-trip-stop__marker"></div>

                <div class="guest-trip-stop__body">

                    <span class="guest-trip-stop__label">Điểm đón</span>

                    <p class="guest-trip-stop__address" data-field="pickup_address"></p>

                </div>

            </div>

            <div class="guest-trip-stop guest-trip-stop--dropoff d-none" data-field="dropoff_wrap">

                <div class="guest-trip-stop__marker"></div>

                <div class="guest-trip-stop__body">

                    <span class="guest-trip-stop__label">Điểm trả</span>

                    <p class="guest-trip-stop__address" data-field="dropoff_address"></p>

                </div>

            </div>

        </div>



        <div id="guest-trip-driver-panel" class="guest-trip-driver d-none" data-field="driver_panel">

            <div class="guest-trip-driver__media">

                <div class="guest-trip-driver__photo-wrap d-none" data-field="driver_photo_wrap">

                    <img src="" alt="" class="guest-trip-driver__photo" data-field="driver_photo" loading="lazy" decoding="async">

                </div>

                <div class="guest-trip-driver__avatar-fallback d-none" data-field="driver_avatar_fallback" aria-hidden="true">TX</div>

            </div>

            <div class="guest-trip-driver__info">

                <div class="guest-trip-driver__top">

                    <strong class="guest-trip-driver__name" data-field="driver_name">—</strong>

                    <span class="guest-trip-driver__code d-none" data-field="driver_code"></span>

                </div>

                <div class="guest-trip-driver__vehicle-line d-none" data-field="driver_vehicle"></div>

                <span class="guest-trip-driver__plate d-none" data-field="driver_plate"></span>

                <div class="guest-trip-driver__status d-none" data-field="driver_status"></div>

                <div class="guest-trip-driver__live d-none" data-field="driver_live_wrap">

                    <span class="guest-trip-driver__live-item d-none" data-field="driver_distance"></span>

                    <span class="guest-trip-driver__live-item d-none" data-field="driver_eta"></span>

                </div>

            </div>

        </div>



        <div id="guest-trip-review-section" class="guest-trip-review d-none">

            <h3 class="guest-trip-review-title">Đánh giá chuyến đi</h3>

            <p class="guest-trip-review-lead">Bạn hài lòng với chuyến đi không?</p>

            <div class="guest-trip-review-actions" role="group" aria-label="Chọn đánh giá">

                <button type="button" class="guest-trip-review-btn guest-trip-review-btn--like" data-review-sentiment="like">

                    <span aria-hidden="true">👍</span> Hài lòng

                </button>

                <button type="button" class="guest-trip-review-btn guest-trip-review-btn--dislike" data-review-sentiment="dislike">

                    <span aria-hidden="true">👎</span> Chưa hài lòng

                </button>

            </div>

            <div class="guest-trip-review-form d-none" id="guest-trip-review-form">

                <label class="guest-trip-review-form-label" for="guest-trip-review-comment">Góp ý thêm (tuỳ chọn)</label>

                <textarea id="guest-trip-review-comment" class="form-control form-control-sm guest-trip-review-textarea" rows="2" maxlength="500" placeholder="Chia sẻ thêm về chuyến đi…"></textarea>

                <button type="button" class="btn btn-primary btn-sm mt-2" id="guest-trip-review-submit">Gửi đánh giá</button>

            </div>

            <div class="guest-trip-review-error d-none" id="guest-trip-review-error"></div>

        </div>



        <div id="guest-trip-review-done" class="guest-trip-review-done d-none">

            <div class="guest-trip-review-done-icon" data-field="review_icon" aria-hidden="true">👍</div>

            <div>

                <strong class="guest-trip-review-done-title" data-field="review_label">Đã đánh giá</strong>

                <p class="guest-trip-review-done-comment" data-field="review_comment"></p>

                <p class="guest-trip-review-done-time" data-field="review_time"></p>

            </div>

        </div>

    </article>

</div>

