@php
$authUser = auth()->user();
$canBookNow = $authUser && $authUser->role === 'customer' && $authUser->canBookTrips();
$bookingBlockedMessage = ($authUser && $authUser->role === 'customer' && ! $authUser->canBookTrips())
    ? $authUser->bookingBlockMessage()
    : null;
$scheduleLaterOn = filled(old('pickup_time')) || filled(old('service_date'));
@endphp
<div class="be-route-card grab-search-card" id="booking-route-card">
    <input type="hidden" name="pickup_lat" id="modal-pickup-lat" form="booking-form" value="{{ old('pickup_lat') }}">
    <input type="hidden" name="pickup_lng" id="modal-pickup-lng" form="booking-form" value="{{ old('pickup_lng') }}">
    <input type="hidden" name="dropoff_lat" id="modal-dropoff-lat" form="booking-form" value="{{ old('dropoff_lat') }}">
    <input type="hidden" name="dropoff_lng" id="modal-dropoff-lng" form="booking-form" value="{{ old('dropoff_lng') }}">

    <div class="grab-search-card__toolbar">
        <button type="button"
                id="booking-schedule-later"
                class="grab-schedule-chip{{ $scheduleLaterOn ? ' is-active' : '' }}"
                aria-pressed="{{ $scheduleLaterOn ? 'true' : 'false' }}"
                aria-controls="booking-schedule-fields"
                title="Đặt sau">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="3" y="4" width="18" height="18" rx="2"/>
                <path d="M16 2v4M8 2v4M3 10h18"/>
            </svg>
            <span data-schedule-chip-label>Đặt sau</span>
        </button>
    </div>

    <div class="be-route-card__addresses grab-search-card__addresses">
        <div class="be-route-card__rail grab-search-rail" aria-hidden="true">
            <span class="grab-search-dot grab-search-dot--pickup"></span>
            <span class="grab-search-rail__line"></span>
            <span class="grab-search-dot grab-search-dot--dropoff"></span>
        </div>
        <div class="be-route-card__fields grab-search-card__fields">
            <div class="grab-search-row">
                <div class="grab-search-field flex-grow-1">
                    <label class="grab-search-label" for="modal-pickup-detail">Điểm đi</label>
                    <div class="booking-address-input-wrap">
                        <input type="text" name="pickup_detail" id="modal-pickup-detail" form="booking-form"
                               class="grab-search-input address-map-readonly-input" required readonly
                               placeholder="Tự lấy GPS hoặc chọn trên bản đồ"
                               value="{{ old('pickup_detail') }}"
                               data-address-map-for="modal-pickup-detail"
                               data-address-map-lat="modal-pickup-lat"
                               data-address-map-lng="modal-pickup-lng"
                               data-address-map-default-province="TP.HCM"
                               data-address-map-label="Chọn điểm đón">
                        <button type="button" class="booking-address-map-btn"
                                data-address-map-for="modal-pickup-detail"
                                data-address-map-lat="modal-pickup-lat"
                                data-address-map-lng="modal-pickup-lng"
                                data-address-map-default-province="TP.HCM"
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
            <div class="grab-search-row">
                <div class="grab-search-field flex-grow-1">
                    <label class="grab-search-label" for="modal-dropoff-detail">Điểm đến</label>
                    <div class="booking-address-input-wrap">
                        <input type="text" name="dropoff_detail" id="modal-dropoff-detail" form="booking-form"
                               class="grab-search-input address-map-readonly-input" required readonly
                               placeholder="Tìm địa chỉ hoặc chọn trên bản đồ"
                               value="{{ old('dropoff_detail') }}"
                               data-address-map-for="modal-dropoff-detail"
                               data-address-map-lat="modal-dropoff-lat"
                               data-address-map-lng="modal-dropoff-lng"
                               data-address-map-default-province="TP.HCM"
                               data-address-map-label="Chọn điểm trả">
                        <button type="button" class="booking-address-map-btn"
                                data-address-map-for="modal-dropoff-detail"
                                data-address-map-lat="modal-dropoff-lat"
                                data-address-map-lng="modal-dropoff-lng"
                                data-address-map-default-province="TP.HCM"
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

    <div id="booking-schedule-fields"
         class="be-route-card__schedule grab-search-card__schedule{{ $scheduleLaterOn ? '' : ' d-none' }}">
        <div class="row g-2 g-md-3 align-items-end booking-pickup-schedule-row">
            <div class="col-6 col-md-6" id="modal-pickup-date-wrap">
                <label class="form-label" for="modal-service-date">Ngày đón <span class="text-danger">*</span></label>
                <input type="date" name="service_date" id="modal-service-date" form="booking-form"
                       class="form-control @error('service_date') is-invalid @enderror"
                       value="{{ old('service_date', $scheduleLaterOn ? $defaultServiceDate : '') }}"
                       min="{{ $defaultServiceDate }}"
                       @if($scheduleLaterOn) required @endif
                       @if(! $scheduleLaterOn) disabled @endif>
                @error('service_date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-6 col-md-6" id="modal-pickup-time-wrap">
                @include('partials.vi-pickup-time-input', [
                    'name' => 'pickup_time',
                    'id' => 'modal-pickup-time',
                    'formAttr' => 'booking-form',
                    'value' => old('pickup_time', $scheduleLaterOn ? $defaultPickupTime : ''),
                    'label' => 'Giờ đón',
                    'required' => $scheduleLaterOn,
                    'disabled' => ! $scheduleLaterOn,
                ])
            </div>
        </div>
    </div>

    <div class="be-route-card__footer grab-search-card__footer">
        @if($canBookNow)
        <button type="button" class="grab-search-cta be-route-card__cta" id="route-continue-btn">
            <span>Tìm xe</span>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </button>
        @elseif($bookingBlockedMessage)
        <div class="w-100">
            <p class="small text-muted mb-2">{{ $bookingBlockedMessage }}</p>
            <a href="{{ route('customer.account') }}" class="grab-search-cta be-route-card__cta">
                <span>Xem tài khoản</span>
            </a>
        </div>
        @else
        <a href="{{ auth()->check() ? route('dashboard') : route('booking.start') }}" class="grab-search-cta be-route-card__cta">
            <span>{{ auth()->check() ? 'Về trang tài khoản' : 'Đăng nhập để đặt xe' }}</span>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </a>
        @endif
    </div>
</div>
