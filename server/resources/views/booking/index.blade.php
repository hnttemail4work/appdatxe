@extends('layouts.app')

@section('content')
@php
use App\Support\ServiceDate;

$defaultServiceDate = $defaultServiceDate ?? ServiceDate::today();
$defaultPickupTime = $defaultPickupTime ?? now()->addHour()->format('H:i');
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
$platformHotlinePhone = (string) config('app.contact_phone');
$heroTitle = $bookingPageHeroTitle ?? 'Đặt xe liên tỉnh';
$driverCount = ($driverOffers ?? collect())->count();
@endphp

<div class="customer-page booking-page" id="booking-page-top">
    <div id="booking-browser-guard-banner" class="booking-flash booking-flash-warning mb-3 @if(($browserCancelCount ?? 0) < \App\Services\BookingBrowserGuardService::CANCEL_BLOCK_LIMIT) d-none @endif" role="alert">
        <div class="booking-flash-icon" aria-hidden="true">!</div>
        <div class="booking-flash-body">
            <strong class="booking-flash-title">Chưa thể đặt cuốc mới</strong>
            <p class="mb-0 small booking-browser-guard-text">{{ app(\App\Services\BookingBrowserGuardService::class)->blockMessage() }}</p>
        </div>
    </div>

    @if($errors->any())
    <div class="alert alert-danger mb-3 booking-flash booking-flash-error app-flash" role="alert">
        <strong>Không thể đặt chuyến:</strong>
        @foreach($errors->all() as $error)
            <div class="small @if(! $loop->last) mb-1 @endif">{{ $error }}</div>
        @endforeach
        @include('partials.flash-close')
    </div>
    @endif

    @if($bookingPageBannerUrl ?? null)
    <section class="booking-page-hero booking-page-hero--banner" aria-label="Trang đặt xe">
        <img src="{{ $bookingPageBannerUrl }}" alt="" class="booking-page-hero__banner-img" decoding="async">
        <div class="booking-page-hero__banner-scrim"></div>
        <div class="booking-page-hero__inner">
            <p class="booking-page-hero__eyebrow">Gọi xe · Thuê xe</p>
            <h1 class="booking-page-hero__title">{{ $heroTitle }}</h1>
            <p class="booking-page-hero__lead">Ghim điểm đón — trả, chọn xe và nhận báo giá ngay.</p>
            @if(($appliedReferral ?? null) || ($prefillReferral ?? '') !== '')
            <div class="booking-page-hero__referral">
                @if($appliedReferral ?? null)
                    <span>Giới thiệu <strong>{{ $appliedReferral->name }}</strong> · mã <span class="driver-meta-code">{{ $appliedReferral->code }}</span>
                    @if($appliedReferral->grantsCustomerDiscount() && ($referralDiscountMeta['eligible'] ?? false))
                        <span class="text-success">— giảm {{ rtrim(rtrim(number_format($referralDiscountMeta['percent'] ?? 0, 1, '.', ''), '0'), '.') }}%</span>
                    @endif
                    </span>
                @elseif(($prefillReferral ?? '') !== '')
                    <span class="text-warning">Mã {{ $prefillReferral }} không hợp lệ.</span>
                @endif
            </div>
            @endif
        </div>
    </section>
    @else
    <section class="booking-page-hero" aria-label="Trang đặt xe">
        <div class="booking-page-hero__glow booking-page-hero__glow--one" aria-hidden="true"></div>
        <div class="booking-page-hero__glow booking-page-hero__glow--two" aria-hidden="true"></div>
        <div class="booking-page-hero__map-art" aria-hidden="true">
            <svg viewBox="0 0 400 200" class="booking-page-hero__map-svg" preserveAspectRatio="xMidYMid slice">
                <defs>
                    <linearGradient id="booking-map-fade" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="rgba(212,175,55,0.12)"/>
                        <stop offset="100%" stop-color="rgba(10,10,10,0)"/>
                    </linearGradient>
                </defs>
                <rect width="400" height="200" fill="url(#booking-map-fade)"/>
                <path d="M0 120 Q80 90 160 115 T320 100 T400 110" fill="none" stroke="rgba(212,175,55,0.2)" stroke-width="2"/>
                <path d="M0 150 Q100 130 200 145 T400 135" fill="none" stroke="rgba(212,175,55,0.12)" stroke-width="1.5"/>
                <circle cx="280" cy="95" r="5" fill="#d4af37"/>
                <circle cx="120" cy="130" r="4" fill="rgba(212,175,55,0.6)"/>
            </svg>
        </div>
        <div class="booking-page-hero__inner">
            <p class="booking-page-hero__eyebrow">Gọi xe · Thuê xe</p>
            <h1 class="booking-page-hero__title">{{ $heroTitle }}</h1>
            <p class="booking-page-hero__lead">Chọn xe phù hợp, ghim điểm đón trả trên bản đồ và xem giá minh bạch trước khi đặt.</p>

            <ul class="booking-page-hero__steps">
                <li>
                    <span class="booking-page-hero__step-num">1</span>
                    <span>Chọn xe</span>
                </li>
                <li>
                    <span class="booking-page-hero__step-num">2</span>
                    <span>Ghim điểm đi / đến</span>
                </li>
                <li>
                    <span class="booking-page-hero__step-num">3</span>
                    <span>Xác nhận chuyến</span>
                </li>
            </ul>

            @if($driverCount > 0)
            <div class="booking-page-hero__stat">
                <strong>{{ $driverCount }}</strong>
                <span>tài xế đang hoạt động</span>
            </div>
            @endif

            @if($appliedReferral ?? null)
            <div class="booking-page-hero__referral">
                Giới thiệu <strong>{{ $appliedReferral->name }}</strong> — mã <span class="driver-meta-code">{{ $appliedReferral->code }}</span>
                @if($appliedReferral->grantsCustomerDiscount() && ($referralDiscountMeta['eligible'] ?? false))
                    <span class="text-success">— giảm {{ rtrim(rtrim(number_format($referralDiscountMeta['percent'] ?? 0, 1, '.', ''), '0'), '.') }}% khi đặt cuốc</span>
                @endif
            </div>
            @elseif(($prefillReferral ?? '') !== '')
            <div class="booking-page-hero__referral text-warning">Mã {{ $prefillReferral }} không hợp lệ.</div>
            @endif
        </div>
    </section>
    @endif

    @include('partials.booking-driver-catalog', ['driverOffers' => $driverOffers])

    @include('partials.customer-scroll-dock')

    @include('partials.customer-contact-fab', [
        'hotlinePhone' => $platformHotlinePhone,
        'variant' => 'fixed',
    ])
</div>

@include('partials.booking-modal', [
    'defaultServiceDate' => $defaultServiceDate,
    'defaultPickupTime' => $defaultPickupTime,
])
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/customer.css') }}?v={{ filemtime(public_path('css/customer.css')) }}">
<link rel="stylesheet" href="{{ asset('css/address-map-picker.css') }}?v={{ filemtime(public_path('css/address-map-picker.css')) }}">
@endpush

@push('scripts')
@include('partials.booking-form-scripts', [
    'bookingTemplates' => $bookingTemplates,
    'bookingRestoreModal' => $bookingRestoreModal,
    'defaultServiceDate' => $defaultServiceDate,
    'defaultPickupTime' => $defaultPickupTime,
    'referralDiscountMeta' => $referralDiscountMeta ?? [],
    'appliedReferral' => $appliedReferral ?? null,
    'bookingReferralSuccess' => $bookingReferralSuccess,
    'browserCancelCount' => $browserCancelCount ?? 0,
])
<script src="{{ asset('js/booking-catalog-filter.js') }}?v={{ filemtime(public_path('js/booking-catalog-filter.js')) }}" defer></script>
@endpush
