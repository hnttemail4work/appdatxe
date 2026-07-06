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

            <div class="guest-trip-card__head-row">

                <div class="guest-trip-card__id">

                    <span class="guest-trip-card__id-label">Mã chuyến</span>

                    <code class="guest-trip-card__code" data-field="trip_code">—</code>

                </div>

                <aside class="guest-trip-card__aside">

                    <div class="guest-trip-referral d-none" id="guest-trip-referral-wrap" data-field="referral_wrap">

                        <p class="guest-trip-referral-label mb-0">Mã giới thiệu</p>

                        <button type="button" class="booking-active-referral-qr-btn guest-trip-referral-qr-btn" id="guest-trip-referral-qr-btn" aria-label="Bấm để xem mã QR giới thiệu">

                            <span id="guest-trip-referral-qr" class="booking-active-referral-qr guest-trip-referral-qr" aria-hidden="true"></span>

                        </button>

                    </div>

                </aside>

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



        <section class="guest-trip-vehicle-section d-none" data-field="vehicle_section_wrap" aria-label="Thông tin xe">

            <div id="guest-trip-driver-panel" class="guest-trip-driver d-none" data-field="driver_panel">

            <div class="guest-trip-driver__media">

                <div class="guest-trip-driver__photo-wrap d-none" data-field="driver_photo_wrap">

                    <img src="" alt="" class="guest-trip-driver__photo" data-field="driver_photo" loading="lazy" decoding="async">

                </div>

                <div class="guest-trip-driver__avatar-fallback d-none" data-field="driver_avatar_fallback" aria-hidden="true">TX</div>

            </div>

            <div class="guest-trip-driver__info">

                <div class="guest-trip-driver__header">

                    <div class="guest-trip-driver__main">

                        <div class="guest-trip-driver__vehicle-line d-none" data-field="driver_vehicle"></div>

                        <div class="guest-trip-driver__top">

                            <strong class="guest-trip-driver__name" data-field="driver_name">—</strong>

                            <span class="guest-trip-driver__code d-none" data-field="driver_code"></span>

                        </div>

                        <span class="guest-trip-driver__plate d-none" data-field="driver_plate"></span>

                    </div>

                    <div class="guest-trip-driver__status d-none" data-field="driver_status"></div>

                </div>

                <div class="guest-trip-driver__live d-none" data-field="driver_live_wrap">

                    <p class="guest-trip-driver__live-distance d-none" data-field="driver_distance_line"></p>

                    <p class="guest-trip-driver__live-eta d-none" data-field="driver_eta_line"></p>

                </div>

            </div>

            </div>

        </section>



        <section class="guest-trip-summary-section d-none" data-field="trip_summary_wrap" aria-label="Chi tiết chuyến">

            <div class="guest-trip-details" data-field="trip_details">

                    <div class="guest-trip-detail d-none" data-field="pickup_time_wrap">
                        <span class="guest-trip-detail__label">Giờ đón</span>
                        <span class="guest-trip-detail__value" data-field="pickup_time_label"></span>
                    </div>

                    <div class="guest-trip-detail d-none" data-field="service_date_wrap">
                        <span class="guest-trip-detail__label">Ngày đi</span>
                        <span class="guest-trip-detail__value" data-field="service_date_label"></span>
                    </div>

                    <div class="guest-trip-detail d-none" data-field="departure_plan_wrap">
                        <span class="guest-trip-detail__label">Loại chuyến</span>
                        <span class="guest-trip-detail__value" data-field="departure_plan_label"></span>
                    </div>

                    <div class="guest-trip-detail d-none" data-field="trip_distance_wrap">
                        <span class="guest-trip-detail__label">Quãng đường</span>
                        <span class="guest-trip-detail__value" data-field="trip_distance_km"></span>
                    </div>

                    <div class="guest-trip-detail guest-trip-detail--price d-none" data-field="total_price_wrap">
                        <span class="guest-trip-detail__label">Giá chuyến</span>
                        <span class="guest-trip-detail__value guest-trip-detail__value--price" data-field="total_price"></span>
                    </div>

            </div>

        </section>



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



        <div id="guest-trip-cancel-wrap" class="guest-trip-cancel d-none" data-field="cancel_wrap">

            <button type="button" class="btn btn-outline-danger btn-sm guest-trip-cancel-btn" id="guest-trip-cancel-btn">

                Hủy chuyến

            </button>

            <p class="guest-trip-cancel-error d-none" id="guest-trip-cancel-error"></p>

        </div>

    </article>

    <div id="guest-trip-referral-qr-overlay" class="booking-active-referral-qr-overlay d-none" role="dialog" aria-modal="true" aria-labelledby="guest-trip-referral-qr-overlay-title" hidden>

        <div class="booking-active-referral-qr-overlay-backdrop" data-close-guest-referral-qr></div>

        <div class="booking-active-referral-qr-overlay-panel">

            <div class="booking-active-referral-qr-overlay-head">

                <strong id="guest-trip-referral-qr-overlay-title">Mã giới thiệu</strong>

                <button type="button" class="btn-close" data-close-guest-referral-qr aria-label="Đóng"></button>

            </div>

            <div id="guest-trip-referral-qr-large" class="booking-active-referral-qr-large"></div>

            <p class="booking-active-referral-qr-overlay-note small mb-0" id="guest-trip-referral-qr-overlay-note"></p>

        </div>

    </div>

</div>

