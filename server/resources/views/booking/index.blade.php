@extends('layouts.app')

@section('content')
@php
use App\Support\ServiceDate;
use App\Support\VehicleDisplay;
use App\Services\TripPricingService;

$pricingService = app(TripPricingService::class);
$defaultServiceDate = $defaultServiceDate ?? ServiceDate::today();

$bookingRestoreModal = $errors->any() && old('template_id')
    ? ['template_id' => old('template_id'), 'step' => 2]
    : null;

$bookingReferralSuccess = session('booking_success.referral_code')
    ? [
        'code' => session('booking_success.referral_code'),
        'url' => session('booking_success.referral_url'),
        'discount_percent' => session('booking_success.referral_discount_percent'),
        'pending' => session('booking_success.referral_pending', true),
    ]
    : null;

$bookingTemplates = collect($offers instanceof \Illuminate\Contracts\Pagination\Paginator ? $offers->items() : ($offers ?? []))
    ->map(fn ($offer) => app(\App\Services\TripListingService::class)->serializeOffer($offer, $defaultServiceDate))
    ->values();
@endphp

<div class="customer-page" id="booking-page-top">
    @if(session('booking_success'))
        @php $bookingSuccess = session('booking_success'); @endphp
        <div class="booking-flash booking-flash-success mb-3 app-flash" id="booking-result-banner" role="alert" data-auto-dismiss="10000">
            <div class="booking-flash-icon" aria-hidden="true">✓</div>
            <div class="booking-flash-body">
                <strong class="booking-flash-title">Đặt chuyến thành công!</strong>
                <p class="mb-1">Mã chuyến: <span class="booking-ticket-code">{{ $bookingSuccess['trip_code'] ?? '—' }}</span></p>
                @if(! empty($bookingSuccess['referral_code']))
                <p class="mb-1 small">Mã giới thiệu: <span class="driver-meta-code">{{ $bookingSuccess['referral_code'] }}</span></p>
                @endif
                <p class="mb-0 small booking-flash-note">Tài xế sẽ gọi xác nhận — giữ máy <strong>{{ $bookingSuccess['contact_phone'] ?? '' }}</strong>.</p>
            </div>
            @include('partials.flash-close')
        </div>
    @endif

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
        <p>Chọn xe và đặt chuyến — tài xế sẽ liên hệ xác nhận.</p>
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
        <h2 class="booking-list-title h5 mb-3">Xe sẵn sàng <span class="status-pill status-pill--gold">{{ $offers->total() }}</span></h2>

        <div id="trips-list">
            @forelse($offers as $offer)
            @php
                $quote = $pricingService->quote($offer);
                $vehiclePhotoUrl = VehicleDisplay::photoFromVehicle($offer->vehicle);
                $vehicleLabel = VehicleDisplay::labelFromVehicle($offer->vehicle);
                $priceLabel = number_format($quote['whole_car_price'], 0, ',', '.') . ' đ';
            @endphp
            <article class="trip-card-pro" data-template-id="{{ $offer->id }}">
                <div class="trip-card-layout">
                    <div class="trip-vehicle-thumb" aria-hidden="true">
                        @if($vehiclePhotoUrl)
                            <img src="{{ $vehiclePhotoUrl }}" alt="" class="trip-vehicle-photo" loading="lazy" decoding="async">
                        @else
                            <div class="trip-vehicle-photo trip-vehicle-photo--empty">{{ strtoupper(substr($offer->vehicle->type ?? 'X', 0, 1)) }}</div>
                        @endif
                    </div>
                    <div class="trip-card-body">
                        <div class="trip-route-line">
                            <span class="city">{{ $offer->route->departure }}</span>
                            <span class="arrow">→</span>
                            <span class="city">{{ $offer->route->destination }}</span>
                        </div>
                        <div class="small text-muted mb-1">{{ $vehicleLabel }}</div>
                        <div class="trip-card-prices">
                            <span class="trip-price-amount">{{ $priceLabel }}</span>
                        </div>
                    </div>
                    <div class="trip-card-side">
                        <button type="button" class="btn btn-outline-primary btn-book fw-semibold"
                            data-open-booking
                            data-template-id="{{ $offer->id }}"
                            data-route="{{ $offer->route->departure }} → {{ $offer->route->destination }}"
                            data-vehicle-label="{{ $vehicleLabel }}"
                            data-vehicle-photo="{{ $vehiclePhotoUrl ?? '' }}"
                            data-price="{{ $quote['whole_car_price'] }}"
                            data-pickup-default="{{ $offer->route->departure }}"
                            data-dropoff-default="{{ $offer->route->destination }}">
                            Đặt xe
                        </button>
                    </div>
                </div>
            </article>
            @empty
            <div class="booking-empty-state">
                <h3 class="h6 fw-bold">Chưa có xe</h3>
                <p class="text-muted small mb-0">Liên hệ tổng đài {{ config('app.contact_phone') }}.</p>
            </div>
            @endforelse
        </div>

        @include('partials.pagination', ['paginator' => $offers])
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
                                <div class="booking-trip-banner-route" id="modal-route">—</div>
                                <div class="small text-muted" id="modal-vehicle-label">—</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-body pt-0 booking-modal-body">
                    <div id="booking-step-1">
                        <div class="booking-sheet-section">
                            <div class="row g-3">
                                <div class="col-6">
                                    <label class="form-label" for="modal-service-date">Ngày đi</label>
                                    <input type="date" name="service_date" id="modal-service-date" class="form-control" required
                                           min="{{ now()->toDateString() }}" value="{{ old('service_date', $defaultServiceDate) }}">
                                </div>
                                <div class="col-6">
                                    @include('partials.vi-pickup-time-input', [
                                        'name' => 'pickup_time',
                                        'id' => 'modal-pickup-time',
                                        'value' => old('pickup_time', ''),
                                        'label' => 'Giờ đón',
                                        'required' => false,
                                    ])
                                </div>
                            </div>
                        </div>

                        <div class="booking-sheet-section">
                            <div class="booking-panel-label">Địa điểm</div>
                            <input type="hidden" name="pickup_address" id="modal-pickup" value="{{ old('pickup_address') }}">
                            <input type="hidden" name="pickup_lat" id="modal-pickup-lat" value="{{ old('pickup_lat') }}">
                            <input type="hidden" name="pickup_lng" id="modal-pickup-lng" value="{{ old('pickup_lng') }}">
                            <input type="hidden" name="dropoff_address" id="modal-dropoff" value="{{ old('dropoff_address') }}">

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
                                    <div class="fw-bold" id="modal-route-step2">—</div>
                                    <div class="small text-muted" id="modal-vehicle-step2">—</div>
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
                    <div class="booking-footer-price fw-semibold" id="modal-total-price-step1">0 đ</div>
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
@if(session('booking_success.referral_code'))
    @include('partials.booking-referral-success-modal')
@endif
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
window.__referralDiscountPercent = @json($referralDiscountMeta['percent'] ?? 0);
window.__referralHasCode = @json((bool) ($appliedReferral ?? null));
window.__bookingReferralSuccess = @json($bookingReferralSuccess);
</script>
<script src="{{ asset('js/geocode-address-autocomplete.js') }}?v={{ filemtime(public_path('js/geocode-address-autocomplete.js')) }}"></script>
<script src="{{ asset('js/customer-booking.js') }}?v={{ filemtime(public_path('js/customer-booking.js')) }}"></script>
<script src="{{ asset('js/customer-scroll-dock.js') }}?v={{ filemtime(public_path('js/customer-scroll-dock.js')) }}"></script>
@if(session('booking_success.referral_code'))
<script src="{{ asset('js/booking-referral-success.js') }}?v={{ filemtime(public_path('js/booking-referral-success.js')) }}"></script>
@endif
<script src="{{ asset('js/address-map-picker.js') }}?v={{ filemtime(public_path('js/address-map-picker.js')) }}"></script>
@endpush
