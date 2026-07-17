@php
$canBookNow = auth()->check() && auth()->user()->role === 'customer';
@endphp
<div class="grab-search-card" id="booking-route-card">
    <input type="hidden" name="pickup_lat" id="modal-pickup-lat" form="booking-form" value="{{ old('pickup_lat') }}">
    <input type="hidden" name="pickup_lng" id="modal-pickup-lng" form="booking-form" value="{{ old('pickup_lng') }}">
    <input type="hidden" name="dropoff_lat" id="modal-dropoff-lat" form="booking-form" value="{{ old('dropoff_lat') }}">
    <input type="hidden" name="dropoff_lng" id="modal-dropoff-lng" form="booking-form" value="{{ old('dropoff_lng') }}">

    <div class="grab-search-card__addresses">
        <div class="grab-search-row">
            <span class="grab-search-dot grab-search-dot--pickup" aria-hidden="true"></span>
            <div class="grab-search-field flex-grow-1">
                <label class="grab-search-label" for="modal-pickup-detail">Điểm đón</label>
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
        <div class="grab-search-divider" aria-hidden="true"></div>
        <div class="grab-search-row">
            <span class="grab-search-dot grab-search-dot--dropoff" aria-hidden="true"></span>
            <div class="grab-search-field flex-grow-1">
                <label class="grab-search-label" for="modal-dropoff-detail">Bạn muốn đi đâu?</label>
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

    <div class="grab-search-card__schedule">
        <div class="grab-search-card__schedule-label">Thời điểm về</div>
        <div class="booking-chip-group booking-chip-group--inline mb-2" id="modal-departure-plan">
            @foreach([
                \App\Support\DeparturePlan::ONE_WAY,
                \App\Support\DeparturePlan::TODAY,
                \App\Support\DeparturePlan::TOMORROW,
                \App\Support\DeparturePlan::LATER,
            ] as $planValue)
            <label class="form-check booking-chip">
                <input type="radio" name="departure_plan" form="booking-form" value="{{ $planValue }}" class="form-check-input"
                       @checked(old('departure_plan', \App\Support\DeparturePlan::ONE_WAY) === $planValue)>
                {{ \App\Support\DeparturePlan::label($planValue) }}
            </label>
            @endforeach
        </div>
        <div id="modal-later-return-days-wrap" class="d-none mb-2">
            <label class="form-label" for="modal-later-return-days">Số ngày chờ về</label>
            <div class="input-group" style="max-width: 12rem;">
                <input type="number" name="later_return_days" id="modal-later-return-days" form="booking-form"
                       class="form-control" min="{{ \App\Support\DeparturePlan::MIN_LATER_RETURN_DAYS }}"
                       max="{{ \App\Support\DeparturePlan::MAX_LATER_RETURN_DAYS }}" step="1"
                       value="{{ old('later_return_days', \App\Support\DeparturePlan::DEFAULT_LATER_RETURN_DAYS) }}">
                <span class="input-group-text">ngày</span>
            </div>
            <div class="form-text" id="modal-later-return-days-hint"></div>
        </div>
        <div class="row g-2 g-md-3 align-items-end booking-pickup-schedule-row">
            <div class="col-6 col-md-6" id="modal-pickup-date-wrap">
                <label class="form-label" for="modal-service-date">Ngày đón <span class="text-danger">*</span></label>
                <input type="date" name="service_date" id="modal-service-date" form="booking-form"
                       class="form-control @error('service_date') is-invalid @enderror"
                       value="{{ old('service_date', $defaultServiceDate) }}"
                       min="{{ $defaultServiceDate }}" required>
                @error('service_date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            </div>
            <div class="col-6 col-md-6" id="modal-pickup-time-wrap">
                @include('partials.vi-pickup-time-input', [
                    'name' => 'pickup_time',
                    'id' => 'modal-pickup-time',
                    'formAttr' => 'booking-form',
                    'value' => old('pickup_time', $defaultPickupTime),
                    'label' => 'Giờ đón',
                    'required' => true,
                ])
            </div>
        </div>
    </div>

    <div class="grab-search-card__footer">
        @if($canBookNow)
        <button type="button" class="grab-search-cta" id="route-continue-btn">
            <span>Tìm xe</span>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </button>
        @else
        <a href="{{ auth()->check() ? route('dashboard') : route('booking.start') }}" class="grab-search-cta">
            <span>{{ auth()->check() ? 'Về trang tài khoản' : 'Đăng nhập để đặt xe' }}</span>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </a>
        @endif
    </div>
</div>
