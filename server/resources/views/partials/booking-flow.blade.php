@php
$bookingPrefill = is_array($customerBookingPrefill ?? null) ? $customerBookingPrefill : [];
$prefillName = old('passenger_name', $bookingPrefill['passenger_name'] ?? '');
$prefillPhone = old('contact_phone', $bookingPrefill['contact_phone'] ?? '');
$prefillGender = old('passenger_gender', $bookingPrefill['passenger_gender'] ?? 'male');
$prefillAge = old('passenger_age', $bookingPrefill['passenger_age'] ?? '');
@endphp
<section id="booking-flow" class="be-booking-flow d-none" aria-label="Đặt xe" hidden>
    <form method="POST" action="{{ route('booking.store') }}" id="booking-form" class="be-booking-flow__form" novalidate>
        @csrf
        <input type="hidden" name="capacity" id="modal-capacity">
        <input type="hidden" name="vehicle_type" id="modal-vehicle-type">
        <input type="hidden" name="booking_browser_id" id="booking-browser-id" value="">
        <input type="hidden" name="pickup_address" id="modal-pickup-address" value="{{ old('pickup_address') }}">
        <input type="hidden" name="dropoff_address" id="modal-dropoff-address" value="{{ old('dropoff_address') }}">

        <header class="be-booking-flow__header">
            <div class="be-booking-flow__header-top">
                <button type="button" class="be-booking-flow__close" data-booking-flow-close aria-label="Đóng">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M15 18l-6-6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
                <div class="booking-steps booking-steps--compact">
                    <div class="booking-step active" data-step="1"><span class="step-num">1</span><span class="step-label">Chọn xe</span></div>
                    <div class="booking-step" data-step="2"><span class="step-num">2</span><span class="step-label">Liên hệ</span></div>
                </div>
            </div>
            <div class="be-booking-flow__route-banner booking-trip-banner">
                <div id="modal-vehicle-photo-wrap" class="booking-modal-vehicle-photo d-none"></div>
                <div class="booking-trip-banner-copy">
                    <div class="booking-trip-banner-route" id="modal-route"></div>
                    <div class="small text-white-50 d-none" id="modal-route-distance"></div>
                    <div class="small" id="modal-vehicle-meta"></div>
                </div>
            </div>
        </header>

        <div class="be-booking-flow__body booking-modal-body">
            <div id="booking-modal-flash" class="app-flash-stack" aria-live="polite" aria-atomic="true"></div>

            <div id="booking-step-1">
                <div class="be-booking-flow__section">
                    <div class="booking-panel-label mb-2">Chọn loại xe phù hợp</div>
                    @include('partials.booking-vehicle-select', ['driverOffers' => $driverOffers ?? collect()])
                </div>
            </div>

            <div id="booking-step-2" class="d-none">
                <div class="be-booking-flow__section booking-summary-card">
                    <div class="d-flex justify-content-between align-items-start gap-3">
                        <div class="d-flex align-items-start gap-3 min-w-0">
                            <div id="modal-step2-vehicle-photo-wrap" class="booking-summary-vehicle-photo d-none" aria-hidden="true"></div>
                            <div class="min-w-0">
                                <div class="fw-bold" id="modal-route-step2"></div>
                                <div class="small text-muted" id="modal-route-distance-step2"></div>
                                <div class="small text-muted" id="modal-vehicle-step2"></div>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="booking-price-summary booking-price-summary--compact" id="modal-price-summary-step2">
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
                                    <span class="booking-price-summary-value booking-footer-price" id="modal-total-price">Chọn đủ thông tin</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="be-booking-flow__section">
                    <div class="booking-panel-label mb-2">Thông tin liên hệ</div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="modal-passenger-name">Tên <span class="text-danger">*</span></label>
                            <input type="text" name="passenger_name" id="modal-passenger-name"
                                   class="form-control @error('passenger_name') is-invalid @enderror"
                                   required autocomplete="name"
                                   value="{{ $prefillName }}">
                            @error('passenger_name')<div class="invalid-feedback d-block guest-field-error" data-for="modal-passenger-name">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="modal-contact-phone">Số điện thoại <span class="text-danger">*</span></label>
                            <input type="tel" name="contact_phone" id="modal-contact-phone"
                                   class="form-control @error('contact_phone') is-invalid @enderror"
                                   required autocomplete="tel"
                                   value="{{ $prefillPhone }}">
                            @error('contact_phone')<div class="invalid-feedback d-block guest-field-error" data-for="modal-contact-phone">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label d-block">Giới tính</label>
                            <div class="booking-chip-group booking-chip-group--inline">
                                <label class="form-check booking-chip"><input type="radio" name="passenger_gender" value="male" class="form-check-input" @checked($prefillGender === 'male')> Nam</label>
                                <label class="form-check booking-chip"><input type="radio" name="passenger_gender" value="female" class="form-check-input" @checked($prefillGender === 'female')> Nữ</label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="modal-passenger-age">Tuổi</label>
                            <input type="number" name="passenger_age" id="modal-passenger-age"
                                   class="form-control @error('passenger_age') is-invalid @enderror"
                                   min="1" max="120" inputmode="numeric"
                                   value="{{ $prefillAge }}">
                            @error('passenger_age')<div class="invalid-feedback d-block guest-field-error" data-for="modal-passenger-age">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="modal-notes">Ghi chú</label>
                            <textarea name="notes" id="modal-notes" rows="2" class="form-control" maxlength="500">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <footer class="be-booking-flow__footer booking-modal-footer d-none" id="modal-footer-step2">
            <div class="be-booking-flow__footer-actions booking-modal-footer-actions booking-modal-footer-actions--step2">
                <button type="button" class="btn btn-outline-secondary" data-modal-back id="modal-back-btn">← Quay lại</button>
                <button type="submit" class="btn btn-primary fw-semibold be-booking-flow__submit" id="modal-submit-btn">Đặt xe</button>
            </div>
        </footer>
    </form>
</section>

@include('partials.address-map-picker-modal')
