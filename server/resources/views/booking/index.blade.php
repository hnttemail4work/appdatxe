@extends('layouts.app')

@section('content')
@php
use App\Support\ServiceDate;

$defaultServiceDate = $defaultServiceDate ?? ServiceDate::today();
$defaultPickupTime = $defaultPickupTime ?? now()->addHour()->format('H:i');
$defaultPickup = old('pickup_address', 'TP.HCM');
$defaultDropoff = old('dropoff_address', '');

$bookingRestoreModal = $errors->any() && (old('template_id') || old('vehicle_id') || old('driver_profile_id'))
    ? [
        'driver_profile_id' => old('driver_profile_id'),
        'vehicle_id' => old('vehicle_id'),
        'template_id' => old('template_id'),
        'step' => 2,
    ]
    : null;

$bookingReferralSuccess = session('booking_success.referral_code')
    ? [
        'code' => session('booking_success.referral_code'),
        'url' => session('booking_success.referral_url')
            ?: route('home', ['ref' => session('booking_success.referral_code')]),
        'discount_percent' => session('booking_success.referral_discount_percent'),
        'pending' => session('booking_success.referral_pending', true),
    ]
    : null;

$bookingTemplates = $driverOffers ?? collect();
@endphp

<div class="customer-page" id="booking-page-top">
    <div id="booking-browser-guard-banner" class="booking-flash booking-flash-warning mb-3 @if(($browserCancelCount ?? 0) < \App\Services\BookingBrowserGuardService::CANCEL_BLOCK_LIMIT) d-none @endif" role="alert">
        <div class="booking-flash-icon" aria-hidden="true">!</div>
        <div class="booking-flash-body">
            <strong class="booking-flash-title">Chưa thể đặt cuốc mới</strong>
            <p class="mb-0 small booking-browser-guard-text">{{ app(\App\Services\BookingBrowserGuardService::class)->blockMessage() }}</p>
        </div>
    </div>

    @include('partials.booking-active-session')

    @if($errors->any())
    <div class="alert alert-danger mb-3 booking-flash booking-flash-error app-flash" role="alert">
        <strong>Không thể đặt chuyến:</strong>
        @foreach($errors->all() as $error)
            <div class="small @if(! $loop->last) mb-1 @endif">{{ $error }}</div>
        @endforeach
        @include('partials.flash-close')
    </div>
    @endif

    <div class="customer-hero mb-4">
        <h1>Đặt xe liên tỉnh</h1>
        @if($appliedReferral ?? null)
            <div class="small mt-2">
                Giới thiệu: <strong>{{ $appliedReferral->name }}</strong>
                — mã <span class="driver-meta-code">{{ $appliedReferral->code }}</span>
                @if($appliedReferral->grantsCustomerDiscount() && ($referralDiscountMeta['eligible'] ?? false))
                    <span class="text-success">(giảm {{ number_format($referralDiscountMeta['percent'] ?? 0, 1) }}%)</span>
                @endif
            </div>
        @elseif(($prefillReferral ?? '') !== '')
            <div class="small mt-2 text-warning">Mã {{ $prefillReferral }} không hợp lệ.</div>
        @endif
    </div>

    <div id="booking-results-main" class="booking-results-main">
        <h2 class="booking-list-title h5 mb-3">Danh sách tài xế <span class="status-pill status-pill--gold" id="vehicle-count">{{ $driverOffers->count() }}</span></h2>

        <div id="trips-list">
            @forelse($driverOffers as $offer)
            @php
                $vehiclePhotoUrl = $offer['vehicle_photo'] ?? null;
                $capacityLabel = $offer['capacity_label'] ?? '—';
                $driverName = $offer['driver_name'] ?? '—';
                $bookingActionLabel = $offer['booking_action_label'] ?? 'Đặt sau';
                $bookingActionTone = $offer['booking_action_tone'] ?? 'pending';
                $typeLabel = $offer['type_label'] ?? '—';
                $licensePlate = $offer['license_plate'] ?? '—';
                $offerLabel = $offer['offer_label'] ?? collect([$driverName, $licensePlate, $typeLabel, $capacityLabel])->filter(fn ($p) => filled($p) && $p !== '—')->implode(' - ');
            @endphp
            <article class="trip-card-pro" data-driver-profile-id="{{ $offer['driver_profile_id'] }}">
                <div class="trip-card-layout trip-card-layout--catalog">
                    <div class="trip-vehicle-thumb" aria-hidden="true">
                        @if($vehiclePhotoUrl)
                            <img src="{{ $vehiclePhotoUrl }}" alt="" class="trip-vehicle-photo" loading="lazy" decoding="async">
                        @else
                            <div class="trip-vehicle-photo trip-vehicle-photo--empty">{{ strtoupper(substr($offer['vehicle_type'] ?? 'X', 0, 1)) }}</div>
                        @endif
                    </div>
                    <div class="trip-card-body">
                        <div class="trip-card-head">
                            <div class="trip-card-head-main">
                                <div class="trip-card-plate trip-vehicle-plate">{{ $licensePlate }}</div>
                                <div class="trip-card-driver-line">{{ $driverName }} · {{ $typeLabel }} · {{ $capacityLabel }}</div>
                            </div>
                            <span class="status-pill status-pill--{{ $bookingActionTone }}">{{ $bookingActionLabel }}</span>
                        </div>
                    </div>
                    <div class="trip-card-side">
                        <button type="button" class="btn btn-outline-primary btn-book fw-semibold"
                            data-open-booking
                            data-driver-profile-id="{{ $offer['driver_profile_id'] }}"
                            data-vehicle-id="{{ $offer['vehicle_id'] ?? '' }}"
                            data-template-id="{{ $offer['template_id'] ?? '' }}"
                            data-license-plate="{{ $offer['license_plate'] }}"
                            data-capacity-label="{{ $capacityLabel }}"
                            data-type-label="{{ $typeLabel }}"
                            data-vehicle-photo="{{ $vehiclePhotoUrl ?? '' }}"
                            data-driver-name="{{ $driverName }}"
                            data-offer-label="{{ $offerLabel }}">
                            Đặt xe
                        </button>
                    </div>
                </div>
            </article>
            @empty
            <div class="booking-empty-state">
                <h3 class="h6 fw-bold">Chưa có tài xế</h3>
                <p class="text-muted small mb-0">Chưa có tài xế đã duyệt với đủ thông tin xe. Liên hệ tổng đài {{ config('app.contact_phone') }}.</p>
            </div>
            @endforelse
        </div>
    </div>

    @include('partials.customer-scroll-dock')
</div>

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
                                <div class="small" id="modal-vehicle-meta"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-body pt-0 booking-modal-body">
                    <div id="booking-step-1">
                        <div class="booking-sheet-section">
                            <div class="booking-panel-label mb-3">Hành trình</div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="modal-pickup">Điểm đi <span class="text-danger">*</span></label>
                                    <select name="pickup_address" id="modal-pickup" class="form-select" required>
                                        <option value="">— Chọn tỉnh/thành —</option>
                                        @include('partials.province-options', ['selected' => $defaultPickup])
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="modal-dropoff">Điểm đến <span class="text-danger">*</span></label>
                                    <select name="dropoff_address" id="modal-dropoff" class="form-select" required>
                                        <option value="">— Chọn tỉnh/thành —</option>
                                        @include('partials.province-options', ['selected' => $defaultDropoff])
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="booking-sheet-section">
                            <div class="row g-3">
                                <div class="col-6">
                                    <label class="form-label" for="modal-service-date">Ngày đi <span class="text-danger">*</span></label>
                                    <input type="date" name="service_date" id="modal-service-date" class="form-control" required
                                           min="{{ now()->toDateString() }}" value="{{ old('service_date', $defaultServiceDate) }}">
                                </div>
                                <div class="col-6">
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

                        <div class="booking-sheet-section">
                            <div class="booking-panel-label">Địa chỉ cụ thể</div>
                            <input type="hidden" name="pickup_lat" id="modal-pickup-lat" value="{{ old('pickup_lat') }}">
                            <input type="hidden" name="pickup_lng" id="modal-pickup-lng" value="{{ old('pickup_lng') }}">

                            <div class="booking-address-card">
                                <div class="booking-address-row">
                                    <span class="booking-address-marker booking-address-marker--pickup" aria-hidden="true"></span>
                                    <div class="booking-address-field flex-grow-1">
                                        <label class="booking-address-label" for="modal-pickup-detail">Điểm đón <span class="text-danger">*</span></label>
                                        <div class="input-group address-map-input-group">
                                            <input type="text" name="pickup_detail" id="modal-pickup-detail" class="form-control" required
                                                   placeholder="Nhập địa chỉ hoặc chọn bản đồ" value="{{ old('pickup_detail') }}">
                                            <button type="button" class="btn btn-outline-primary address-map-trigger"
                                                    data-address-map-for="modal-pickup-detail"
                                                    data-address-map-province="modal-pickup"
                                                    data-address-map-lat="modal-pickup-lat"
                                                    data-address-map-lng="modal-pickup-lng"
                                                    data-address-map-label="Chọn điểm đón" aria-label="Bản đồ">📍</button>
                                        </div>
                                    </div>
                                </div>
                                <div class="booking-address-divider" aria-hidden="true"></div>
                                <div class="booking-address-row">
                                    <span class="booking-address-marker booking-address-marker--dropoff" aria-hidden="true"></span>
                                    <div class="booking-address-field flex-grow-1">
                                        <label class="booking-address-label" for="modal-dropoff-detail">Điểm trả</label>
                                        <div class="input-group address-map-input-group">
                                            <input type="text" name="dropoff_detail" id="modal-dropoff-detail" class="form-control"
                                                   placeholder="Nhập địa chỉ" value="{{ old('dropoff_detail') }}">
                                            <button type="button" class="btn btn-outline-primary address-map-trigger"
                                                    data-address-map-for="modal-dropoff-detail"
                                                    data-address-map-province="modal-dropoff"
                                                    data-address-map-label="Chọn điểm trả" aria-label="Bản đồ">📍</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="booking-step-2" class="d-none">
                        <div class="booking-sheet-section booking-summary-card">
                            <div class="d-flex justify-content-between align-items-start gap-3">
                                <div>
                                    <div class="fw-bold" id="modal-route-step2"></div>
                                    <div class="small text-muted" id="modal-vehicle-step2"></div>
                                </div>
                                <div class="text-end">
                                    <div class="small text-muted">Tổng tiền</div>
                                    <div class="booking-summary-total" id="modal-total-price">0 đ</div>
                                    <div id="modal-referral-discount" class="booking-referral-discount mt-1 d-none small text-success"></div>
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
                    <div class="booking-footer-price-wrap text-end me-auto">
                        <div class="small text-muted">Tạm tính</div>
                        <div class="booking-footer-price fw-semibold" id="modal-total-price-step1"></div>
                    </div>
                    <button type="button" class="btn btn-primary fw-semibold px-4" id="modal-next-btn">Tiếp tục</button>
                </div>
                <div class="modal-footer border-0 booking-modal-footer d-none" id="modal-footer-step2">
                    <button type="button" class="btn btn-outline-secondary" id="modal-back-btn">← Quay lại</button>
                    <button type="submit" class="btn btn-primary px-4 fw-semibold ms-auto" id="modal-submit-btn">Xác nhận đặt xe</button>
                </div>
            </form>
        </div>
    </div>
</div>

@include('partials.address-map-picker-modal')
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/customer.css') }}?v={{ filemtime(public_path('css/customer.css')) }}">
<link rel="stylesheet" href="{{ asset('css/address-map-picker.css') }}?v={{ filemtime(public_path('css/address-map-picker.css')) }}">
@endpush

@push('scripts')
<script>
window.__bookingCheckDuplicateUrl = @json(route('booking.checkDuplicate'));
window.__quotePriceUrl = @json(route('booking.quotePrice'));
window.__geocodeReverseUrl = @json(route('geocode.reverse'));
window.__geocodeSearchUrl = @json(route('geocode.search'));
window.__bookingTemplates = @json($bookingTemplates);
window.__bookingRestoreModal = @json($bookingRestoreModal);
window.__defaultServiceDate = @json($defaultServiceDate);
window.__defaultPickupTime = @json($defaultPickupTime);
window.__referralDiscountPercent = @json($referralDiscountMeta['percent'] ?? 0);
window.__referralHasCode = @json((bool) ($appliedReferral ?? null));
window.__bookingReferralSuccess = @json($bookingReferralSuccess);
window.__bookingSuccess = @json(session('booking_success'));
window.__guestBrowserCancelCount = @json((int) ($browserCancelCount ?? session('guest_browser_cancel_count', 0)));
window.__guestBrowserCancelBlockLimit = @json(\App\Services\BookingBrowserGuardService::CANCEL_BLOCK_LIMIT);
</script>
<script src="{{ asset('js/booking-browser-guard.js') }}?v={{ filemtime(public_path('js/booking-browser-guard.js')) }}"></script>
<script src="{{ asset('js/booking-active-session.js') }}?v={{ filemtime(public_path('js/booking-active-session.js')) }}"></script>
<script src="{{ asset('js/geocode-address-autocomplete.js') }}?v={{ filemtime(public_path('js/geocode-address-autocomplete.js')) }}"></script>
<script src="{{ asset('js/customer-booking.js') }}?v={{ filemtime(public_path('js/customer-booking.js')) }}"></script>
<script src="{{ asset('js/customer-scroll-dock.js') }}?v={{ filemtime(public_path('js/customer-scroll-dock.js')) }}"></script>
<script src="{{ asset('js/address-map-picker.js') }}?v={{ filemtime(public_path('js/address-map-picker.js')) }}"></script>
@endpush
