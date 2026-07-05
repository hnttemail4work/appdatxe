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
$platformHotlinePhone = (string) config('app.contact_phone');
@endphp

<div class="customer-page" id="booking-page-top">
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
    <div class="customer-hero customer-hero--banner mb-4">
        <div class="customer-hero-banner-top">
            <h1 class="customer-hero-banner-title">{{ $bookingPageHeroTitle ?? 'Xe của chúng tôi' }}</h1>
        </div>
        <img src="{{ $bookingPageBannerUrl }}" alt="{{ $bookingPageHeroTitle ?? 'Xe của chúng tôi' }}" class="customer-hero-banner-img">
        @if(($appliedReferral ?? null) || ($prefillReferral ?? '') !== '')
        <div class="customer-hero-banner-foot">
            @if($appliedReferral ?? null)
                <div class="small">
                    Giới thiệu: <strong>{{ $appliedReferral->name }}</strong>
                    — mã <span class="driver-meta-code">{{ $appliedReferral->code }}</span>
                    @if($appliedReferral->grantsCustomerDiscount() && ($referralDiscountMeta['eligible'] ?? false))
                        <span class="text-success">— giảm {{ rtrim(rtrim(number_format($referralDiscountMeta['percent'] ?? 0, 1, '.', ''), '0'), '.') }}% khi đặt cuốc</span>
                    @endif
                </div>
            @elseif(($prefillReferral ?? '') !== '')
                <div class="small text-warning">Mã {{ $prefillReferral }} không hợp lệ.</div>
            @endif
        </div>
        @endif
    </div>
    @else
    <div class="customer-hero mb-4">
        <h1>{{ $bookingPageHeroTitle ?? 'Xe của chúng tôi' }}</h1>
        @if($appliedReferral ?? null)
            <div class="small mt-2">
                Giới thiệu: <strong>{{ $appliedReferral->name }}</strong>
                — mã <span class="driver-meta-code">{{ $appliedReferral->code }}</span>
                @if($appliedReferral->grantsCustomerDiscount() && ($referralDiscountMeta['eligible'] ?? false))
                    <span class="text-success">— giảm {{ rtrim(rtrim(number_format($referralDiscountMeta['percent'] ?? 0, 1, '.', ''), '0'), '.') }}% khi đặt cuốc</span>
                @endif
            </div>
        @elseif(($prefillReferral ?? '') !== '')
            <div class="small mt-2 text-warning">Mã {{ $prefillReferral }} không hợp lệ.</div>
        @endif
    </div>
    @endif

    @include('partials.booking-driver-catalog', ['driverOffers' => $driverOffers])

    @include('partials.customer-scroll-dock')

    @include('partials.customer-contact-fab', [
        'hotlinePhone' => $platformHotlinePhone,
        'variant' => 'fixed',
    ])
</div>

@include('partials.booking-modal', [
    'defaultPickup' => $defaultPickup,
    'defaultDropoff' => $defaultDropoff,
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
@endpush
