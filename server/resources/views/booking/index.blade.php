@extends('layouts.app')

@section('content')
@php
use App\Support\ServiceDate;
$basePrices = [];
$filterServiceDate = $filters['service_date'] ?? ServiceDate::today();
$filterDateCarbon = ServiceDate::parse($filterServiceDate);
$filterDateLabel = $filterDateCarbon->format('d/m/Y');
$filterWeekday = ['Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'][(int) $filterDateCarbon->dayOfWeek];
$filterQuery = array_filter([
    'departure' => $filters['departure'] ?? null,
    'destination' => $filters['destination'] ?? null,
    'vehicle_capacity' => $filters['vehicle_capacity'] ?? null,
    'service_date' => $filterServiceDate,
    'ref' => ($appliedReferral ?? null)?->code ?? (($prefillReferral ?? '') !== '' ? $prefillReferral : null),
]);
$bookingRestoreModal = $errors->any() && old('template_id')
    ? [
        'template_id'      => old('template_id'),
        'seat_count'       => old('seat_count', 1),
        'vehicle_count'    => old('vehicle_count', 1),
        'vehicle_capacity' => old('vehicle_capacity'),
        'step'             => 2,
        'duplicate_route'  => $errors->first('booking') === 'active_booking',
    ]
    : null;

$bookingReferralSuccess = session('booking_success.referral_code')
    ? [
        'code' => session('booking_success.referral_code'),
        'url' => session('booking_success.referral_url'),
        'discount_percent' => session('booking_success.referral_discount_percent'),
        'pending' => session('booking_success.referral_pending', true),
    ]
    : null;

$pricingService = app(\App\Services\TripPricingService::class);

$offerItems = ($offers instanceof \Illuminate\Contracts\Pagination\Paginator)
    ? collect($offers->items())
    : collect($offers ?? []);

$guestWatchlistCount = $guestWatchlistCount ?? 0;
$guestActiveOrdersCount = $guestActiveOrdersCount ?? 0;

$bookingTemplates = $offerItems->map(function ($offer) use ($filterServiceDate, $pricingService) {
    $schedule = $offer->scheduleInfoForDate($filterServiceDate);
    $tripQuote = $pricingService->quote($offer, 'one_way', null, null, 'shared');
    $wholeQuote = $pricingService->quote($offer, 'one_way', null, null, 'whole_car');
    $roundQuote = $pricingService->quote($offer, 'round_trip', null, null, 'shared');
    $capacity = $offer->capacity();

    return [
        'id' => $offer->id,
        'route' => $offer->route->departure . ' → ' . $offer->route->destination,
        'departure' => $offer->route->departure,
        'destination' => $offer->route->destination,
        'capacity' => $capacity,
        'capacity_label' => \App\Support\VehicleCapacityOptions::label($capacity),
        'capacity_sort' => \App\Support\VehicleCapacityOptions::sortKey($capacity),
        'vehicle_photo' => \App\Support\VehicleDisplay::photoFromVehicle($offer->vehicle),
        'vehicle_type' => $offer->vehicle->type ?? 'sedan',
        'vehicle_label' => \App\Support\VehicleDisplay::labelFromVehicle($offer->vehicle),
        'price' => $tripQuote['shared_seat_price'],
        'one_way_price' => $tripQuote['one_way_seat_price'],
        'whole_car_price' => $wholeQuote['one_way_whole_car_price'],
        'whole_car_round_trip_price' => $offer->whole_car_round_trip_price !== null ? (int) $offer->whole_car_round_trip_price : null,
        'seat_round_trip_price' => $offer->seat_round_trip_price !== null ? (int) $offer->seat_round_trip_price : null,
        'round_trip_price' => $roundQuote['shared_seat_price'],
        'service_date' => $schedule['service_date'] ?? $filterServiceDate,
        'pickup_default' => $offer->route->departure,
        'dropoff_default' => $offer->route->destination,
    ];
})->sortBy('capacity_sort')->values();
@endphp

<div class="customer-page" id="booking-page-top">
    @if(session('booking_success'))
        @php $bookingSuccess = session('booking_success'); @endphp
        <div class="booking-flash booking-flash-success mb-3 app-flash" id="booking-result-banner" role="alert" data-auto-dismiss="10000">
            <div class="booking-flash-icon" aria-hidden="true">✓</div>
            <div class="booking-flash-body">
                <strong class="booking-flash-title">Đặt chuyến thành công!</strong>
                <p class="mb-1">
                    Mã chuyến: <span class="booking-ticket-code">{{ $bookingSuccess['trip_code'] ?? '—' }}</span>
                </p>
                @if(! empty($bookingSuccess['referral_code']))
                <p class="mb-1 small">
                    Mã giới thiệu: <span class="driver-meta-code">{{ $bookingSuccess['referral_code'] }}</span>
                    <span class="text-muted">(dùng được sau khi bạn hoàn tất chuyến)</span>
                </p>
                @endif
                <p class="mb-2 small booking-flash-note">
                    @if(! empty($bookingSuccess['driver_assigned']))
                        Tài xế đã nhận chuyến — sẽ gọi xác nhận
                        @if(! empty($bookingSuccess['driver_distance_label']))
                            · cách điểm đón ~{{ $bookingSuccess['driver_distance_label'] }}
                        @endif
                    @elseif(! empty($bookingSuccess['searching_driver']))
                        Đang chờ tài xế gần bạn nhận cuốc
                    @elseif(! empty($bookingSuccess['awaiting_operator']))
                        Quản lý sẽ gọi xác nhận trong thời gian sớm nhất
                    @else
                        Tài xế sẽ gọi sau khi nhận chuyến
                    @endif
                    @if(! empty($bookingSuccess['contact_phone']))
                        — giữ máy <strong>{{ $bookingSuccess['contact_phone'] }}</strong>
                    @endif
                    .
                </p>
                <a href="{{ route('guest.orders', ! empty($bookingSuccess['searching_driver']) ? ['searching' => 1] : []) }}"
                   class="btn btn-sm btn-outline-light booking-flash-view-order"
                   id="booking-flash-view-order-btn">
                    Xem tiến trình đơn đặt
                </a>
            </div>
            @include('partials.flash-close')
        </div>
    @endif
    @if($errors->any())
    <div class="alert alert-danger mb-3 booking-flash booking-flash-error app-flash" id="booking-form-errors" role="alert">
        <strong>Không thể đặt vé:</strong>
        @foreach($errors->all() as $error)
            <div class="small @if(! $loop->last) mb-1 @endif">{{ $error }}</div>
        @endforeach
        @include('partials.flash-close')
    </div>
    @endif

    <div id="booking-search-block">

    @if($bookingBannerUrl ?? null)
    <div class="customer-hero customer-hero--banner">
        <img src="{{ $bookingBannerUrl }}" alt="" class="customer-hero-banner-img" loading="eager" decoding="async">
        <div class="customer-hero-banner-overlay">
            <span class="badge customer-hero-badge px-3 py-2">Tổng đài {{ config('app.contact_phone') }}</span>
        </div>
        @if($appliedReferral ?? null)
            <div class="customer-hero-banner-foot small">
                Giới thiệu: <strong>{{ $appliedReferral->name }}</strong>
                — mã <span class="driver-meta-code">{{ $appliedReferral->code }}</span>
                @if($appliedReferral->grantsCustomerDiscount() && ($referralDiscountMeta['eligible'] ?? false))
                    <span class="text-success">(giảm {{ number_format($referralDiscountMeta['percent'] ?? 0, 1) }}%)</span>
                @elseif($appliedReferral->type === \App\Models\ReferralCode::TYPE_REFERRER)
                    <span class="text-muted">(mã người giới thiệu)</span>
                @endif
            </div>
        @elseif($pendingReferral ?? null)
            <div class="customer-hero-banner-foot small text-warning">
                Mã <span class="driver-meta-code">{{ $pendingReferral->code }}</span> chưa kích hoạt —
                hoàn tất chuyến giới thiệu trước khi dùng được.
            </div>
        @elseif(($prefillReferral ?? '') !== '')
            <div class="customer-hero-banner-foot small text-warning">Mã {{ $prefillReferral }} không hợp lệ hoặc chưa sử dụng được.</div>
        @endif
    </div>
    @else
    <div class="customer-hero">
        <div class="row align-items-center position-relative" style="z-index:1">
            <div class="col-lg-8">
                <h1>Đặt vé xe liên tỉnh</h1>
                <p>Hân hạnh phục vụ quý khách hàng.</p>
                @if($appliedReferral ?? null)
                    <div class="small mt-2">
                        Giới thiệu: <strong>{{ $appliedReferral->name }}</strong>
                        — mã <span class="driver-meta-code">{{ $appliedReferral->code }}</span>
                        @if($appliedReferral->grantsCustomerDiscount() && ($referralDiscountMeta['eligible'] ?? false))
                            <span class="text-success">(giảm {{ number_format($referralDiscountMeta['percent'] ?? 0, 1) }}%)</span>
                        @elseif($appliedReferral->type === \App\Models\ReferralCode::TYPE_REFERRER)
                            <span class="text-muted">(mã người giới thiệu)</span>
                        @endif
                    </div>
                @elseif($pendingReferral ?? null)
                    <div class="small mt-2 text-warning">
                        Mã <span class="driver-meta-code">{{ $pendingReferral->code }}</span> chưa kích hoạt —
                        hoàn tất chuyến giới thiệu trước khi dùng được.
                    </div>
                @elseif(($prefillReferral ?? '') !== '')
                    <div class="small mt-2 text-warning">Mã {{ $prefillReferral }} không hợp lệ hoặc chưa sử dụng được.</div>
                @endif
            </div>
            <div class="col-lg-4 text-lg-end d-none d-lg-block">
                <span class="badge customer-hero-badge px-3 py-2">Tổng đài {{ config('app.contact_phone') }}</span>
            </div>
        </div>
    </div>
    @endif

    <div class="booking-route-sheet mb-4">
        <form method="GET" action="{{ route('home') }}" id="trip-filter-form" class="booking-route-sheet-form">
            <div class="route-sheet-journey">
                <div class="route-sheet-rail" aria-hidden="true">
                    <span class="route-sheet-marker route-sheet-marker--from"></span>
                    <span class="route-sheet-rail-line"></span>
                    <span class="route-sheet-marker route-sheet-marker--to"></span>
                </div>
                <div class="route-sheet-stops">
                    <div class="route-sheet-stop">
                        <span class="route-sheet-stop-label">Điểm đi</span>
                        <div class="route-sheet-picker-field">
                            <select name="departure" id="filter-departure" class="route-sheet-stop-native" tabindex="-1" aria-hidden="true">
                                <option value="">Chọn tỉnh / thành</option>
                                @include('partials.province-options', ['selected' => $filters['departure'] ?? ''])
                            </select>
                            <button type="button" class="route-sheet-stop-value route-sheet-picker-trigger"
                                    data-picker-for="filter-departure" data-placeholder="Chọn tỉnh / thành"
                                    aria-haspopup="listbox" aria-expanded="false" aria-controls="route-sheet-picker-body">
                                <span class="route-sheet-picker-text">Chọn tỉnh / thành</span>
                            </button>
                        </div>
                    </div>
                    <div class="route-sheet-stop route-sheet-stop--dest">
                        <span class="route-sheet-stop-label">Điểm đến</span>
                        <div class="route-sheet-picker-field">
                            <select name="destination" id="filter-destination" class="route-sheet-stop-native" tabindex="-1" aria-hidden="true">
                                <option value="">Bạn muốn đi đâu?</option>
                                @include('partials.province-options', ['selected' => $filters['destination'] ?? ''])
                            </select>
                            <button type="button" class="route-sheet-stop-value route-sheet-picker-trigger"
                                    data-picker-for="filter-destination" data-placeholder="Bạn muốn đi đâu?"
                                    aria-haspopup="listbox" aria-expanded="false" aria-controls="route-sheet-picker-body">
                                <span class="route-sheet-picker-text">Bạn muốn đi đâu?</span>
                            </button>
                        </div>
                    </div>
                </div>
                <button type="button" class="route-sheet-swap swap-route-btn" title="Đổi chiều" aria-label="Đổi chiều đi và đến">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                        <path d="M11 2.5 13.5 5 11 7.5M13.5 5H4.5a3 3 0 0 0-3 3v.5M5 13.5 2.5 11 5 8.5M2.5 11h9a3 3 0 0 0 3-3V7.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            </div>

            <label class="route-sheet-date">
                <span class="route-sheet-date-icon" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
                        <rect x="2" y="3.5" width="14" height="12.5" rx="2" stroke="currentColor" stroke-width="1.3"/>
                        <path d="M2 7.5h14M6 2v3M12 2v3" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/>
                    </svg>
                </span>
                <span class="route-sheet-date-body">
                    <span class="route-sheet-date-weekday" id="filter-date-weekday">{{ $filterWeekday }}</span>
                    <span class="route-sheet-date-day" id="filter-date-label">{{ $filterDateLabel }}</span>
                </span>
                <span class="route-sheet-date-action">Đổi</span>
                <input type="date" name="service_date" id="filter-service-date" class="route-sheet-date-native"
                       min="{{ now()->toDateString() }}" value="{{ $filterServiceDate }}" required
                       aria-label="Ngày đi">
            </label>

            <div class="route-sheet-filters" id="booking-offer-filters">
                <div class="route-sheet-seg-row">
                    <span class="route-sheet-seg-label">Chuyến</span>
                    <div class="route-sheet-seg" role="group" aria-label="Loại chuyến">
                        <button type="button" class="route-sheet-seg-btn is-active" data-trip-type="one_way" aria-pressed="true">Một chiều</button>
                        <button type="button" class="route-sheet-seg-btn" data-trip-type="round_trip" aria-pressed="false">Khứ hồi</button>
                    </div>
                </div>
                <div class="route-sheet-seg-row">
                    <span class="route-sheet-seg-label">Vé</span>
                    <div class="route-sheet-seg" role="group" aria-label="Hình thức đặt">
                        <button type="button" class="route-sheet-seg-btn is-active" data-booking-mode="whole_car" aria-pressed="true">Cả xe</button>
                        <button type="button" class="route-sheet-seg-btn" data-booking-mode="shared" aria-pressed="false">Ghép xe</button>
                    </div>
                </div>
                <div class="route-sheet-seg-row">
                    <span class="route-sheet-seg-label">Số chỗ</span>
                    <select name="vehicle_capacity" id="filter-vehicle-capacity" class="form-select form-select-sm route-sheet-capacity-select" aria-label="Lọc theo số chỗ">
                        <option value="" @selected(! ($filters['vehicle_capacity'] ?? null))>Tất cả loại xe</option>
                        @foreach($vehicleCapacityChoices ?? \App\Support\VehicleCapacityOptions::choices() as $capacity => $label)
                            <option value="{{ $capacity }}" @selected((int) ($filters['vehicle_capacity'] ?? 0) === (int) $capacity)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="route-sheet-actions">
                <button type="submit" id="filter-search-submit" class="route-sheet-cta">Tìm chuyến</button>
                <button type="button" class="route-sheet-secondary js-open-custom-booking" id="open-custom-booking">Đặt theo yêu cầu</button>
            </div>
        </form>
    </div>

    <div class="route-sheet-picker-sheet" id="route-sheet-province-picker" hidden>
        <div class="route-sheet-picker-backdrop" data-picker-close></div>
        <div class="route-sheet-picker-panel" role="dialog" aria-modal="true" aria-labelledby="route-sheet-picker-title">
            <div class="route-sheet-picker-head">
                <span class="route-sheet-picker-head-title" id="route-sheet-picker-title">Chọn tỉnh</span>
                <button type="button" class="route-sheet-picker-close" data-picker-close aria-label="Đóng">&times;</button>
            </div>
            <div class="route-sheet-picker-body" id="route-sheet-picker-body" role="listbox"></div>
        </div>
    </div>

    </div>

    <div id="booking-results-main" class="booking-results-main">
            <div class="booking-list-head mb-3">
                @php
                    $hasActiveFilters = ($filters['departure'] ?? '') !== ''
                        || ($filters['destination'] ?? '') !== ''
                        || (request()->has('vehicle_capacity') && request()->input('vehicle_capacity') !== '');
                @endphp
                <div class="booking-list-head-main">
                    <div class="booking-list-head-row">
                        <h2 class="booking-list-title mb-0">
                            <span class="booking-list-title-text">
                            @if(! $hasActiveFilters)
                                Tuyến cố định
                            @else
                                Kết quả
                            @endif
                            </span>
                            <span class="status-pill status-pill--gold" id="trip-count">{{ $offers->total() }}</span>
                        </h2>
                        @if($hasActiveFilters)
                            <button type="button" class="btn btn-sm btn-outline-secondary booking-clear-search-btn" id="booking-clear-search">Xóa tìm kiếm</button>
                        @endif
                    </div>
                </div>
            </div>

            <div id="trips-list">
        @forelse($offers as $offer)
        @php
            $capacity = $offer->capacity();
            $schedule = $offer->scheduleInfoForDate($filterServiceDate);
            $tripQuote = $pricingService->quote($offer, 'one_way', null, null, 'shared');
            $wholeQuote = $pricingService->quote($offer, 'one_way', null, null, 'whole_car');
            $roundQuote = $pricingService->quote($offer, 'round_trip', null, null, 'shared');
            $wholeRoundQuote = $pricingService->quote($offer, 'round_trip', null, null, 'whole_car');
            $listPriceLabel = number_format($wholeQuote['one_way_whole_car_price'], 0, ',', '.') . ' đ';
            $roundPrice = $roundQuote['shared_seat_price'];
            $vehicleCapacityLabel = \App\Support\VehicleCapacityOptions::label($capacity);
            $vehiclePhotoUrl = \App\Support\VehicleDisplay::photoFromVehicle($offer->vehicle);
            $basePrices[$offer->id] = [
                'one_way' => $tripQuote['one_way_seat_price'],
                'round_trip' => $roundPrice,
            ];
            $vehicleType = $offer->vehicle->type ?? 'sedan';
        @endphp
        <article class="trip-card-pro" data-template-id="{{ $offer->id }}">
            <div class="trip-card-layout">
                <div class="trip-vehicle-thumb" aria-hidden="true">
                    @if($vehiclePhotoUrl)
                        <img src="{{ $vehiclePhotoUrl }}" alt="" class="trip-vehicle-photo" loading="lazy" decoding="async">
                    @else
                        <div class="trip-vehicle-photo trip-vehicle-photo--empty">{{ strtoupper(substr($vehicleType, 0, 1)) }}</div>
                    @endif
                </div>

                <div class="trip-card-body">
                    <div class="trip-route-line">
                        <span class="city">{{ $offer->route->departure }}</span>
                        <span class="arrow">→</span>
                        <span class="city">{{ $offer->route->destination }}</span>
                    </div>

                    <div class="trip-card-prices">
                        <span class="trip-price-inline">
                            <span class="trip-price-amount">{{ $listPriceLabel }}</span><span class="trip-price-unit">/cả xe</span>
                        </span>
                    </div>
                </div>

                <div class="trip-card-side">
                    <button type="button" class="btn btn-outline-primary btn-book fw-semibold"
                        data-open-booking
                        data-template-id="{{ $offer->id }}"
                        data-route="{{ $offer->route->departure }} → {{ $offer->route->destination }}"
                        data-service-date="{{ $schedule['service_date'] }}"
                        data-date-label="{{ $schedule['date_label'] }}"
                        data-weekday="{{ $schedule['weekday'] }}"
                        data-date-short="{{ $schedule['date_short'] }}"
                        data-price="{{ $wholeQuote['one_way_whole_car_price'] }}"
                        data-one-way-price="{{ $tripQuote['one_way_seat_price'] }}"
                        data-whole-car-price="{{ $wholeQuote['one_way_whole_car_price'] }}"
                        data-whole-car-round-price="{{ $wholeRoundQuote['whole_car_price'] }}"
                        data-seat-round-trip-price="{{ $roundQuote['shared_seat_price'] }}"
                        data-round-trip-price="{{ $roundPrice }}"
                        data-capacity="{{ $capacity }}"
                        data-vehicle-photo="{{ $vehiclePhotoUrl ?? '' }}"
                        data-vehicle-label="{{ $vehicleCapacityLabel }}"
                        data-pickup-default="{{ $offer->route->departure }}"
                        data-dropoff-default="{{ $offer->route->destination }}">
                        Đặt chuyến
                    </button>
                </div>
            </div>
        </article>
        @empty
        <div class="booking-empty-state" id="no-trips-msg">
            <h3 class="h6 fw-bold">Không có chuyến phù hợp</h3>
            <p class="text-muted small mb-3">Đổi ngày hoặc bộ lọc.</p>
            <button type="button" class="btn btn-sm btn-outline-primary" id="booking-show-all-trips">Xem tất cả chuyến</button>
        </div>
        @endforelse
            </div>
            <div id="trips-pagination">
            @include('partials.pagination', ['paginator' => $offers])
            </div>
    </div>

    @include('partials.customer-scroll-dock', [
        'customerDockMode' => 'home',
        'guestActiveOrdersCount' => $guestActiveOrdersCount,
    ])
</div>

<div class="modal fade" id="bookingModal" tabindex="-1" aria-labelledby="modal-route" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable booking-modal-dialog">
        <div class="modal-content border-0 shadow booking-modal-sheet">
            <div class="booking-sheet-handle" aria-hidden="true"></div>
            <form method="POST" action="{{ route('booking.store') }}" id="booking-form" class="booking-modal-form" novalidate>
                @csrf
                <input type="hidden" name="template_id" id="modal-template-id">
                <input type="hidden" name="vehicle_capacity" id="modal-vehicle-capacity" value="{{ old('vehicle_capacity') }}">

                <div class="modal-header border-0 p-0 booking-modal-header">
                    <div class="booking-modal-header-inner w-100">
                        <div class="booking-modal-header-top">
                            <div class="booking-steps booking-steps--compact" id="booking-steps">
                                <div class="booking-step active" data-step="1">
                                    <span class="step-num">1</span><span class="step-label">Chuyến</span>
                                </div>
                                <div class="booking-step" data-step="2">
                                    <span class="step-num">2</span><span class="step-label">Liên hệ</span>
                                </div>
                            </div>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Đóng"></button>
                        </div>
                        <div class="booking-trip-banner" id="modal-trip-banner">
                            <div class="booking-route-timeline" aria-hidden="true">
                                <span class="booking-route-dot booking-route-dot--from"></span>
                                <span class="booking-route-line"></span>
                                <span class="booking-route-dot booking-route-dot--to"></span>
                            </div>
                            <div class="booking-trip-banner-copy">
                                <div class="booking-trip-banner-route" id="modal-route">—</div>
                                <div class="booking-modal-offer-preset" id="modal-offer-preset" aria-live="polite">
                                    <span class="booking-preset-chip" id="modal-preset-trip-type">Một chiều</span>
                                    <span class="booking-preset-chip" id="modal-preset-booking-mode">Cả xe</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-body pt-0 booking-modal-body">
                    <div id="booking-step-1">
                        <div class="booking-sheet-section">
                            <div class="booking-schedule-grid">
                                <div class="booking-schedule-item">
                                    <label class="form-label" for="modal-service-date">Ngày đi</label>
                                    <input type="date" name="service_date" id="modal-service-date"
                                           class="form-control @error('service_date') is-invalid @enderror" required
                                           data-validate-label="Ngày đi"
                                           min="{{ now()->toDateString() }}" value="{{ old('service_date', $filterServiceDate) }}">
                                    @error('service_date')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                                <div class="booking-schedule-item">
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

                        <div class="booking-sheet-section d-none" id="modal-vehicle-count-wrap">
                            <div class="booking-panel-label">Số xe</div>
                            <div class="booking-seat-stepper">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="modal-vehicle-count-minus" aria-label="Giảm số xe">−</button>
                                <input type="number" name="vehicle_count" id="modal-vehicle-count" class="form-control form-control-sm text-center"
                                       min="1" max="10" value="{{ old('vehicle_count', 1) }}" inputmode="numeric" aria-label="Số xe đặt">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="modal-vehicle-count-plus" aria-label="Tăng số xe">+</button>
                            </div>
                            <div class="form-text">Số chiếc xe cùng loại trên một đơn (giá × số xe).</div>
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
                                    <div class="booking-address-field">
                                        <label class="booking-address-label" for="modal-pickup-detail">Điểm đón <span class="text-danger">*</span></label>
                                        <div class="input-group address-map-input-group">
                                            <input type="text" name="pickup_detail" id="modal-pickup-detail" class="form-control" required
                                                   data-validate-label="Địa chỉ đón"
                                                   placeholder="Nhập địa chỉ — chọn gợi ý hoặc bản đồ"
                                                   value="{{ old('pickup_detail') }}">
                                            <button type="button" class="btn btn-outline-primary address-map-trigger"
                                                    data-address-map-for="modal-pickup-detail"
                                                    data-address-map-province="modal-pickup"
                                                    data-address-map-lat="modal-pickup-lat"
                                                    data-address-map-lng="modal-pickup-lng"
                                                    data-address-map-label="Chọn điểm đón trên bản đồ"
                                                    aria-label="Chọn điểm đón trên bản đồ" title="Chọn trên bản đồ">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                                                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/>
                                                    <circle cx="12" cy="10" r="3"/>
                                                </svg>
                                            </button>
                                        </div>
                                        <div class="form-text">Gõ và chọn gợi ý địa chỉ, hoặc nút bản đồ — hệ thống cần tọa độ GPS để ghép tài xế.</div>
                                    </div>
                                </div>
                                <div class="booking-address-divider" aria-hidden="true"></div>
                                <div class="booking-address-row">
                                    <span class="booking-address-marker booking-address-marker--dropoff" aria-hidden="true"></span>
                                    <div class="booking-address-field">
                                        <label class="booking-address-label" for="modal-dropoff-detail">Điểm trả</label>
                                        <div class="input-group address-map-input-group">
                                            <input type="text" name="dropoff_detail" id="modal-dropoff-detail" class="form-control"
                                                   placeholder="Nhập hoặc chọn trên bản đồ"
                                                   value="{{ old('dropoff_detail') }}">
                                            <button type="button" class="btn btn-outline-primary address-map-trigger"
                                                    data-address-map-for="modal-dropoff-detail"
                                                    data-address-map-province="modal-dropoff"
                                                    data-address-map-label="Chọn điểm trả trên bản đồ"
                                                    aria-label="Chọn điểm trả trên bản đồ" title="Chọn trên bản đồ">
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

                        @if($appliedReferral)
                        <div class="booking-sheet-section">
                            <label class="form-label" for="modal-referral-code">Mã giới thiệu</label>
                            <input type="text" id="modal-referral-code" class="form-control bg-light"
                                   value="{{ $appliedReferral->code }}" readonly tabindex="-1" autocomplete="off"
                                   aria-readonly="true">
                            <div class="form-text">Mã từ link giới thiệu — không thể chỉnh sửa.</div>
                        </div>
                        @endif

                        <div class="booking-sheet-section d-none" id="modal-trip-type-section">
                            <div class="booking-panel-label">Loại chuyến</div>
                            <div class="booking-chip-group" id="modal-trip-type-group">
                                <div class="form-check booking-chip">
                                    <input class="form-check-input" type="radio" name="trip_type" id="trip-type-one-way"
                                           value="one_way" {{ old('trip_type', 'one_way') === 'round_trip' ? '' : 'checked' }}>
                                    <label class="form-check-label" for="trip-type-one-way">Một chiều</label>
                                </div>
                                <div class="form-check booking-chip">
                                    <input class="form-check-input" type="radio" name="trip_type" id="trip-type-round-trip"
                                           value="round_trip" {{ old('trip_type') === 'round_trip' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="trip-type-round-trip">Khứ hồi</label>
                                </div>
                            </div>
                        </div>

                        <div class="booking-sheet-section d-none" id="modal-booking-mode-section">
                            <div class="booking-panel-label">Hình thức đặt</div>
                            <div class="booking-chip-group mb-2" id="modal-booking-mode-group">
                                <div class="form-check booking-chip" id="booking-mode-whole-car-wrap">
                                    <input class="form-check-input" type="radio" name="booking_mode" id="booking-mode-whole-car"
                                           value="whole_car" {{ old('booking_mode', 'whole_car') !== 'shared' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="booking-mode-whole-car">Cả xe</label>
                                </div>
                                <div class="form-check booking-chip">
                                    <input class="form-check-input" type="radio" name="booking_mode" id="booking-mode-shared"
                                           value="shared" {{ old('booking_mode') === 'shared' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="booking-mode-shared">Ghép xe</label>
                                </div>
                            </div>
                            @error('booking_mode')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div class="booking-sheet-section d-none" id="modal-seat-count-wrap">
                            <div class="booking-panel-label">Số ghế</div>
                            <div class="booking-seat-stepper">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="modal-seat-count-minus" aria-label="Giảm số ghế">−</button>
                                <input type="number" name="seat_count" id="modal-seat-count" class="form-control form-control-sm text-center"
                                       min="1" value="{{ old('seat_count', 1) }}" inputmode="numeric">
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="modal-seat-count-plus" aria-label="Tăng số ghế">+</button>
                            </div>
                        </div>
                    </div>

                    <div id="booking-step-2" class="d-none">
                        <div class="booking-sheet-section booking-summary-card">
                            <div class="booking-summary-top">
                                <div class="min-w-0">
                                    <div class="booking-summary-route fw-bold" id="modal-route-step2">—</div>
                                    <div class="small mt-2" id="modal-driver-summary"></div>
                                    <div class="small mt-1" id="modal-trip-type-summary"></div>
                                    <div class="small d-none mt-1" id="modal-spot-summary">
                                        <span id="modal-seat-summary-text">—</span>
                                    </div>
                                    <div class="small text-muted mt-1" id="modal-vehicle">—</div>
                                </div>
                                <div class="text-end flex-shrink-0">
                                    <div class="small text-muted">Tổng tiền</div>
                                    <div class="booking-summary-total" id="modal-total-price">0 đ</div>
                                    <div class="small text-muted d-none" id="modal-price-unit-wrap"><span id="modal-price-unit">0 đ</span>/vé</div>
                                    <div id="modal-referral-discount-step2" class="booking-referral-discount mt-2 d-none"></div>
                                </div>
                            </div>
                        </div>

                        <div class="booking-sheet-section">
                            <div class="booking-panel-label">Liên hệ</div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label" for="modal-passenger-name">Tên <span class="text-danger">*</span></label>
                                    <input type="text" name="passenger_name" id="modal-passenger-name" class="form-control" required
                                           data-validate-label="Tên"
                                           value="{{ old('passenger_name') }}" autocomplete="name">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="modal-contact-phone">Số điện thoại <span class="text-danger">*</span></label>
                                    <input type="tel" name="contact_phone" id="modal-contact-phone" class="form-control" required
                                           data-validate-label="Số điện thoại" autocomplete="tel" inputmode="tel"
                                           value="{{ old('contact_phone') }}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label d-block">Giới tính</label>
                                    <div class="booking-chip-group booking-chip-group--inline">
                                        <div class="form-check booking-chip">
                                            <input type="radio" name="passenger_gender" id="modal-passenger-gender-male" value="male"
                                                   class="form-check-input" @checked(old('passenger_gender', 'male') === 'male')>
                                            <label class="form-check-label" for="modal-passenger-gender-male">Nam</label>
                                        </div>
                                        <div class="form-check booking-chip">
                                            <input type="radio" name="passenger_gender" id="modal-passenger-gender-female" value="female"
                                                   class="form-check-input" @checked(old('passenger_gender') === 'female')>
                                            <label class="form-check-label" for="modal-passenger-gender-female">Nữ</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="modal-passenger-age">Tuổi</label>
                                    <input type="number" name="passenger_age" id="modal-passenger-age" class="form-control"
                                           min="1" max="120" inputmode="numeric"
                                           value="{{ old('passenger_age') }}">
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="modal-notes">Ghi chú cho tài xế</label>
                                    <textarea name="notes" id="modal-notes" rows="2" maxlength="500"
                                              class="form-control @error('notes') is-invalid @enderror"
                                              placeholder="Ví dụ: mang theo hành lý lớn, cần ghế trẻ em…">{{ old('notes') }}</textarea>
                                    @error('notes')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0 booking-modal-footer" id="modal-footer-step1">
                    <div class="seat-footer-summary">
                        <div class="fw-semibold" id="modal-seat-list-step1">Số ghế: 1 ghế</div>
                        <div class="booking-footer-price" id="modal-total-price-step1">0 đ</div>
                        <div id="modal-referral-discount-step1" class="booking-referral-discount d-none"></div>
                    </div>
                    <button type="button" class="btn btn-primary fw-semibold px-4 booking-cta-btn" id="modal-next-btn">
                        Tiếp tục
                    </button>
                </div>

                <div class="modal-footer border-0 booking-modal-footer d-none" id="modal-footer-step2">
                    <button type="button" class="btn btn-outline-secondary" id="modal-back-btn">← Quay lại</button>
                    <button type="submit" class="btn btn-primary px-4 fw-semibold ms-auto booking-cta-btn" id="modal-submit-btn">
                        Xác nhận đặt vé
                    </button>
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
window.__vehicleCapacityChoices = @json($vehicleCapacityChoices ?? \App\Support\VehicleCapacityOptions::choices());
window.__customerSyncUrl = @json(route('booking.liveSync'));
window.__customerHomeUrl = @json(route('home'));
window.__seatAvailabilityUrl = @json(route('booking.seatAvailability'));
window.__quotePriceUrl = @json(route('booking.quotePrice'));
window.__bookingResolveRouteUrl = @json(route('booking.resolveRoute'));
window.__contactPhone = @json(config('app.contact_phone'));
window.__geocodeReverseUrl = @json(route('geocode.reverse'));
window.__geocodeSearchUrl = @json(route('geocode.search'));
window.__customerBasePrices = @json($basePrices);
window.__bookingRestoreModal = @json($bookingRestoreModal);
window.__bookingShowResult = @json(session('booking_success') !== null || $errors->any());
window.__filterServiceDate = @json($filterServiceDate);
window.__bookingTemplates = @json($bookingTemplates);
window.__referralPrefill = @json($appliedReferral?->code ?? '');
window.__referralDiscountPercent = @json($referralDiscountMeta['percent'] ?? 0);
window.__referralAttributionOnly = @json($referralDiscountMeta['attribution_only'] ?? false);
window.__referralHasCode = @json((bool) ($appliedReferral ?? null));
window.__bookingReferralSuccess = @json($bookingReferralSuccess);
window.__roundTripMultiplier = @json(\App\Support\PlatformFees::roundTripMultiplier());
window.__guestTripWatchUrl = @json(route('guest.tripWatch'));
window.__guestOrdersUrl = @json(route('guest.orders'));
window.__guestTripReloadMs = @json(\App\Services\GuestTripWatchService::GUEST_PAGE_RELOAD_SECONDS * 1000);
window.__guestTripSearchingReload = @json(! empty(session('booking_success.searching_driver')));
window.__guestTripReviewUrl = @json(route('guest.tripReviews.store'));
window.__guestTripCancelUrl = @json(route('guest.bookings.cancel'));
window.__guestActiveOrdersCount = @json($guestActiveOrdersCount);
window.__guestWatchlistCount = @json($guestWatchlistCount);
window.__bookingSuccessActive = @json((bool) session('booking_success'));
window.__cancellationReasonsUrl = @json(route('cancellationReasons.index'));
</script>
<script src="{{ asset('js/geocode-address-autocomplete.js') }}?v={{ filemtime(public_path('js/geocode-address-autocomplete.js')) }}"></script>
<script src="{{ asset('js/customer-booking.js') }}?v={{ filemtime(public_path('js/customer-booking.js')) }}"></script>
<script src="{{ asset('js/customer-scroll-dock.js') }}?v={{ filemtime(public_path('js/customer-scroll-dock.js')) }}"></script>
@if(session('booking_success.referral_code'))
<script src="{{ asset('js/booking-referral-success.js') }}?v={{ filemtime(public_path('js/booking-referral-success.js')) }}"></script>
@endif
<script src="{{ asset('js/address-map-picker.js') }}?v={{ filemtime(public_path('js/address-map-picker.js')) }}"></script>
@endpush
