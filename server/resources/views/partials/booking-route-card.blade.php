@php
$authUser = auth()->user();
$canBookNow = $authUser && $authUser->role === 'customer' && $authUser->canBookTrips();
$bookingBlockedMessage = ($authUser && $authUser->role === 'customer' && ! $authUser->canBookTrips())
    ? $authUser->bookingBlockMessage()
    : null;
$pickupValue = old('pickup_detail');
$dropoffValue = old('dropoff_detail');
$loginUrl = route('booking.start');
@endphp
{{--
  Flow Grab/Be:
  Home: title + 1 thanh (map | hint)
  → Sheet: Điểm đi / Điểm đến + gợi ý
  → Đủ 2 điểm: auto mở bảng giá/xe
  → Đặt xe (trong flow) → tìm TX → trạng thái
--}}
<div class="be-route-card grab-search-card" id="booking-route-card"
     data-can-book="{{ $canBookNow ? '1' : '0' }}"
     data-login-url="{{ $loginUrl }}"
     @if($bookingBlockedMessage) data-booking-blocked="{{ $bookingBlockedMessage }}" @endif>

    <input type="hidden" name="pickup_lat" id="modal-pickup-lat" form="booking-form" value="{{ old('pickup_lat') }}">
    <input type="hidden" name="pickup_lng" id="modal-pickup-lng" form="booking-form" value="{{ old('pickup_lng') }}">
    <input type="hidden" name="dropoff_lat" id="modal-dropoff-lat" form="booking-form" value="{{ old('dropoff_lat') }}">
    <input type="hidden" name="dropoff_lng" id="modal-dropoff-lng" form="booking-form" value="{{ old('dropoff_lng') }}">

    <div class="visually-hidden" aria-hidden="true">
        <input type="text" name="pickup_detail" id="modal-pickup-detail" form="booking-form"
               class="address-map-readonly-input" required readonly tabindex="-1"
               value="{{ $pickupValue }}">
        <input type="text" name="dropoff_detail" id="modal-dropoff-detail" form="booking-form"
               class="address-map-readonly-input" required readonly tabindex="-1"
               value="{{ $dropoffValue }}">
    </div>

    <h2 class="grab-home-title">Bạn muốn đi đâu?</h2>

    <div class="grab-home-bar" data-open-address-sheet>
        <button type="button"
                class="grab-home-bar__map"
                data-address-map-for="modal-dropoff-detail"
                data-address-map-lat="modal-dropoff-lat"
                data-address-map-lng="modal-dropoff-lng"
                data-address-map-default-province="TP.HCM"
                data-address-map-label="Chọn điểm trả"
                data-address-map-locate="1"
                aria-label="Chọn trên bản đồ"
                title="Bản đồ">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                <circle cx="12" cy="10" r="3"/>
            </svg>
        </button>

        <button type="button" class="grab-home-bar__main" data-open-address-sheet-main aria-label="Chọn điểm đến">
            <span class="grab-home-bar__hint" data-home-dest-label>
                {{ filled($dropoffValue) ? $dropoffValue : 'Bạn muốn đi đâu?' }}
            </span>
        </button>
    </div>

    @if($bookingBlockedMessage)
        <p class="small text-muted mt-3 mb-0">{{ $bookingBlockedMessage }}
            <a href="{{ route('customer.account') }}">Xem tài khoản</a>
        </p>
    @endif
</div>

{{-- Full-screen address sheet --}}
<div id="booking-address-sheet" class="booking-addr-sheet" hidden aria-hidden="true">
    <div class="booking-addr-sheet__panel" role="dialog" aria-modal="true" aria-labelledby="booking-addr-sheet-title">
        <header class="booking-addr-sheet__header">
            <button type="button" class="booking-addr-sheet__back" data-addr-sheet-close aria-label="Đóng">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M15 18l-6-6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
            <h2 class="booking-addr-sheet__title" id="booking-addr-sheet-title">Bạn muốn đi đâu?</h2>
            <button type="button" class="booking-addr-sheet__map booking-addr-sheet__map--text" data-addr-sheet-map
                    aria-label="Chọn từ bản đồ" title="Chọn từ bản đồ">
                Chọn từ bản đồ
            </button>
        </header>
        {{-- Trigger ẩn: mở map picker cho ô đang focus --}}
        <button type="button" id="addr-sheet-map-trigger" class="d-none" tabindex="-1" aria-hidden="true"
                data-address-map-for="modal-dropoff-detail"
                data-address-map-lat="modal-dropoff-lat"
                data-address-map-lng="modal-dropoff-lng"
                data-address-map-default-province="TP.HCM"
                data-address-map-label="Chọn điểm trên bản đồ"
                data-address-map-locate="1"></button>

        <div class="booking-addr-sheet__fields">
            <div class="booking-addr-sheet__rail" aria-hidden="true">
                <span class="grab-route-dot grab-route-dot--pickup"></span>
                <span class="booking-addr-sheet__line"></span>
                <span class="grab-route-dot grab-route-dot--dropoff"></span>
            </div>
            <div class="booking-addr-sheet__inputs">
                <div class="booking-addr-field" data-addr-field="pickup">
                    <span class="booking-addr-field__label" aria-hidden="true">Điểm đi</span>
                    <label class="visually-hidden" for="addr-sheet-pickup">Điểm đi</label>
                    <input type="text" id="addr-sheet-pickup" class="booking-addr-field__input"
                           placeholder="Nhập điểm đón" autocomplete="off" enterkeyhint="next">
                    <button type="button" class="booking-addr-field__clear d-none" data-addr-clear="pickup" aria-label="Xóa điểm đi">×</button>
                </div>
                <div class="booking-addr-field" data-addr-field="dropoff">
                    <span class="booking-addr-field__label" aria-hidden="true">Điểm đến</span>
                    <label class="visually-hidden" for="addr-sheet-dropoff">Điểm đến</label>
                    <input type="text" id="addr-sheet-dropoff" class="booking-addr-field__input"
                           placeholder="Nhập điểm trả" autocomplete="off" enterkeyhint="search">
                    <button type="button" class="booking-addr-field__clear d-none" data-addr-clear="dropoff" aria-label="Xóa điểm đến">×</button>
                </div>
            </div>
            <button type="button" class="booking-addr-sheet__swap" data-addr-swap aria-label="Đảo điểm đi và điểm đến" title="Đảo điểm">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M7 7h11l-3-3M17 17H6l3 3" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </div>

        <div class="booking-addr-sheet__suggest" id="booking-addr-suggest" role="listbox" aria-label="Gợi ý địa chỉ"></div>
    </div>
</div>
