<div class="modal fade" id="bookingModal" tabindex="-1" aria-labelledby="modal-route" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable booking-modal-dialog">
        <div class="modal-content border-0 shadow booking-modal-sheet">
            <div class="booking-sheet-handle" aria-hidden="true"></div>
            <form method="POST" action="{{ route('booking.store') }}" id="booking-form" class="booking-modal-form" novalidate>
                @csrf
                <input type="hidden" name="template_id" id="modal-template-id">
                <input type="hidden" name="vehicle_id" id="modal-vehicle-id">
                <input type="hidden" name="driver_profile_id" id="modal-driver-profile-id">
                <input type="hidden" name="booking_browser_id" id="booking-browser-id" value="">
                <input type="hidden" name="pickup_address" id="modal-pickup-address" value="{{ old('pickup_address') }}">
                <input type="hidden" name="dropoff_address" id="modal-dropoff-address" value="{{ old('dropoff_address') }}">

                <div class="modal-header border-0 p-0 booking-modal-header">
                    <div class="booking-modal-header-inner w-100">
                        <div class="booking-modal-header-top">
                            <div class="booking-steps booking-steps--compact">
                                <div class="booking-step active" data-step="1"><span class="step-num">1</span><span class="step-label">Chuyến</span></div>
                                <div class="booking-step" data-step="2"><span class="step-num">2</span><span class="step-label">Liên hệ</span></div>
                            </div>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Đóng"></button>
                        </div>
                        <div class="booking-trip-banner">
                            <div id="modal-vehicle-photo-wrap" class="booking-modal-vehicle-photo d-none"></div>
                            <div class="booking-trip-banner-copy">
                                <div class="booking-trip-banner-route" id="modal-route"></div>
                                <div class="small text-white-50 d-none" id="modal-route-distance"></div>
                                <div class="small text-white-50" id="modal-departure-plan-label"></div>
                                <div class="small" id="modal-vehicle-meta"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-body pt-0 booking-modal-body">
                    <div id="booking-modal-flash" class="app-flash-stack" aria-live="polite" aria-atomic="true"></div>
                    <div id="booking-step-1">
                        <div class="booking-sheet-section">
                            <div class="booking-panel-label mb-3">Hành trình</div>
                            <input type="hidden" name="pickup_lat" id="modal-pickup-lat" value="{{ old('pickup_lat') }}">
                            <input type="hidden" name="pickup_lng" id="modal-pickup-lng" value="{{ old('pickup_lng') }}">
                            <input type="hidden" name="dropoff_lat" id="modal-dropoff-lat" value="{{ old('dropoff_lat') }}">
                            <input type="hidden" name="dropoff_lng" id="modal-dropoff-lng" value="{{ old('dropoff_lng') }}">

                            <div class="booking-address-card">
                                <div class="booking-address-row">
                                    <span class="booking-address-marker booking-address-marker--pickup" aria-hidden="true"></span>
                                    <div class="booking-address-field flex-grow-1">
                                        <label class="booking-address-label" for="modal-pickup-detail">Điểm đón <span class="text-danger">*</span></label>
                                        <div class="booking-address-input-wrap">
                                            <input type="text" name="pickup_detail" id="modal-pickup-detail"
                                                   class="form-control address-map-readonly-input" required readonly
                                                   placeholder="Tự lấy GPS hoặc chọn trên bản đồ"
                                                   value="{{ old('pickup_detail') }}"
                                                   data-address-map-for="modal-pickup-detail"
                                                   data-address-map-lat="modal-pickup-lat"
                                                   data-address-map-lng="modal-pickup-lng"
                                                   data-address-map-label="Chọn điểm đón">
                                            <button type="button" class="booking-address-map-btn"
                                                    data-address-map-for="modal-pickup-detail"
                                                    data-address-map-lat="modal-pickup-lat"
                                                    data-address-map-lng="modal-pickup-lng"
                                                    data-address-map-label="Chọn điểm đón"
                                                    aria-label="Chọn điểm đón trên bản đồ" title="Bản đồ">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                                                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                                    <circle cx="12" cy="10" r="3"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="booking-address-divider" aria-hidden="true"></div>
                                <div class="booking-address-row">
                                    <span class="booking-address-marker booking-address-marker--dropoff" aria-hidden="true"></span>
                                    <div class="booking-address-field flex-grow-1">
                                        <label class="booking-address-label" for="modal-dropoff-detail">Điểm trả <span class="text-danger">*</span></label>
                                        <div class="booking-address-input-wrap">
                                            <input type="text" name="dropoff_detail" id="modal-dropoff-detail"
                                                   class="form-control address-map-readonly-input" required readonly
                                                   placeholder="Tìm địa chỉ hoặc chọn trên bản đồ"
                                                   value="{{ old('dropoff_detail') }}"
                                                   data-address-map-for="modal-dropoff-detail"
                                                   data-address-map-lat="modal-dropoff-lat"
                                                   data-address-map-lng="modal-dropoff-lng"
                                                   data-address-map-label="Chọn điểm trả">
                                            <button type="button" class="booking-address-map-btn"
                                                    data-address-map-for="modal-dropoff-detail"
                                                    data-address-map-lat="modal-dropoff-lat"
                                                    data-address-map-lng="modal-dropoff-lng"
                                                    data-address-map-label="Chọn điểm trả"
                                                    aria-label="Chọn điểm trả trên bản đồ" title="Bản đồ">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                                                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                                    <circle cx="12" cy="10" r="3"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="booking-sheet-section">
                            <div class="booking-panel-label mb-2">Thời điểm về</div>
                            <div class="booking-chip-group booking-chip-group--inline mb-2" id="modal-departure-plan">
                                @foreach([
                                    \App\Support\DeparturePlan::ONE_WAY,
                                    \App\Support\DeparturePlan::TODAY,
                                    \App\Support\DeparturePlan::TOMORROW,
                                    \App\Support\DeparturePlan::LATER,
                                ] as $planValue)
                                <label class="form-check booking-chip">
                                    <input type="radio" name="departure_plan" value="{{ $planValue }}" class="form-check-input"
                                           @checked(old('departure_plan', \App\Support\DeparturePlan::ONE_WAY) === $planValue)>
                                    {{ \App\Support\DeparturePlan::label($planValue) }}
                                </label>
                                @endforeach
                            </div>
                            <div id="modal-later-return-days-wrap" class="d-none mb-2">
                                <label class="form-label" for="modal-later-return-days">Số ngày chờ về</label>
                                <div class="input-group" style="max-width: 12rem;">
                                    <input type="number" name="later_return_days" id="modal-later-return-days"
                                           class="form-control" min="{{ \App\Support\DeparturePlan::MIN_LATER_RETURN_DAYS }}"
                                           max="{{ \App\Support\DeparturePlan::MAX_LATER_RETURN_DAYS }}" step="1"
                                           value="{{ old('later_return_days', \App\Support\DeparturePlan::DEFAULT_LATER_RETURN_DAYS) }}">
                                    <span class="input-group-text">ngày</span>
                                </div>
                                <div class="form-text" id="modal-later-return-days-hint"></div>
                            </div>
                            <div class="row g-3 align-items-end">
                                <div class="col-md-6" id="modal-pickup-date-wrap">
                                    <label class="form-label" for="modal-service-date">Ngày đón <span class="text-danger">*</span></label>
                                    <input type="date" name="service_date" id="modal-service-date"
                                           class="form-control @error('service_date') is-invalid @enderror"
                                           value="{{ old('service_date', $defaultServiceDate) }}"
                                           min="{{ $defaultServiceDate }}" required>
                                    @error('service_date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6" id="modal-pickup-time-wrap">
                                    @include('partials.vi-pickup-time-input', [
                                        'name' => 'pickup_time',
                                        'id' => 'modal-pickup-time',
                                        'value' => old('pickup_time', $defaultPickupTime),
                                        'label' => 'Giờ đón',
                                        'required' => true,
                                    ])
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="booking-step-2" class="d-none">
                        <div class="booking-sheet-section booking-summary-card">
                            <div class="d-flex justify-content-between align-items-start gap-3">
                                <div class="d-flex align-items-start gap-3 min-w-0">
                                    <div id="modal-step2-vehicle-photo-wrap" class="booking-summary-vehicle-photo d-none" aria-hidden="true"></div>
                                    <div class="min-w-0">
                                        <div class="fw-bold" id="modal-route-step2"></div>
                                        <div class="small text-muted" id="modal-route-distance-step2"></div>
                                        <div class="small text-muted" id="modal-departure-plan-label-step2"></div>
                                        <div class="small text-muted" id="modal-vehicle-step2"></div>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="booking-price-summary booking-price-summary--compact" id="modal-price-summary-step2">
                                        <div class="booking-price-summary-row" id="modal-price-departure-row-step2">
                                            <span class="booking-price-summary-label">Đặt xe:</span>
                                            <span class="booking-price-summary-value" id="modal-price-departure-plan-step2"></span>
                                        </div>
                                        <div class="booking-price-summary-row d-none" id="modal-price-distance-row-step2">
                                            <span class="booking-price-summary-label">Số km:</span>
                                            <span class="booking-price-summary-value" id="modal-price-distance-step2"></span>
                                        </div>
                                        <div class="booking-price-summary-row d-none" id="modal-original-row-step2">
                                            <span class="booking-price-summary-label">Giá gốc:</span>
                                            <span class="booking-price-summary-value booking-price-original text-muted text-decoration-line-through" id="modal-original-price-step2"></span>
                                        </div>
                                        <div class="booking-price-summary-row d-none" id="modal-discount-row-step2">
                                            <span class="booking-price-summary-label">Giảm giá:</span>
                                            <span class="booking-price-summary-value booking-price-discount text-success" id="modal-referral-discount-step2"></span>
                                        </div>
                                        <div class="booking-price-summary-row booking-price-summary-row--total">
                                            <span class="booking-price-summary-label">Thành tiền:</span>
                                            <span class="booking-price-summary-value booking-footer-price" id="modal-total-price">0 đ</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="booking-sheet-section">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="modal-passenger-name">Tên <span class="text-danger">*</span></label>
                                    <input type="text" name="passenger_name" id="modal-passenger-name" class="form-control" required value="{{ old('passenger_name') }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="modal-contact-phone">Số điện thoại <span class="text-danger">*</span></label>
                                    <input type="tel" name="contact_phone" id="modal-contact-phone" class="form-control" required value="{{ old('contact_phone') }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label d-block">Giới tính</label>
                                    <div class="booking-chip-group booking-chip-group--inline">
                                        <label class="form-check booking-chip"><input type="radio" name="passenger_gender" value="male" class="form-check-input" @checked(old('passenger_gender', 'male') === 'male')> Nam</label>
                                        <label class="form-check booking-chip"><input type="radio" name="passenger_gender" value="female" class="form-check-input" @checked(old('passenger_gender') === 'female')> Nữ</label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="modal-notes">Ghi chú</label>
                                    <textarea name="notes" id="modal-notes" rows="2" class="form-control" maxlength="500">{{ old('notes') }}</textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0 booking-modal-footer" id="modal-footer-step1">
                    <div class="booking-footer-price-wrap me-auto">
                        <div class="booking-price-summary" id="modal-price-summary-step1">
                            <div class="booking-price-summary-row" id="modal-price-departure-row-step1">
                                <span class="booking-price-summary-label">Đặt xe:</span>
                                <span class="booking-price-summary-value" id="modal-price-departure-plan-step1"></span>
                            </div>
                            <div class="booking-price-summary-row d-none" id="modal-price-distance-row-step1">
                                <span class="booking-price-summary-label">Số km:</span>
                                <span class="booking-price-summary-value" id="modal-price-distance-step1"></span>
                            </div>
                            <div class="booking-price-summary-row d-none" id="modal-original-row-step1">
                                <span class="booking-price-summary-label">Giá gốc:</span>
                                <span class="booking-price-summary-value booking-price-original text-muted text-decoration-line-through" id="modal-original-price-step1"></span>
                            </div>
                            <div class="booking-price-summary-row d-none" id="modal-discount-row-step1">
                                <span class="booking-price-summary-label">Giảm giá:</span>
                                <span class="booking-price-summary-value booking-price-discount text-success" id="modal-referral-discount-step1"></span>
                            </div>
                            <div class="booking-price-summary-row booking-price-summary-row--total">
                                <span class="booking-price-summary-label">Thành tiền:</span>
                                <span class="booking-price-summary-value booking-footer-price" id="modal-total-price-step1"></span>
                            </div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-primary fw-semibold px-4" id="modal-next-btn">Tiếp tục</button>
                </div>
                <div class="modal-footer border-0 booking-modal-footer d-none" id="modal-footer-step2">
                    <button type="button" class="btn btn-outline-secondary" id="modal-back-btn">← Quay lại</button>
                    <button type="submit" class="btn btn-primary px-4 fw-semibold ms-auto" id="modal-submit-btn">Đặt vé</button>
                </div>
            </form>
        </div>
    </div>
</div>

@include('partials.address-map-picker-modal')
