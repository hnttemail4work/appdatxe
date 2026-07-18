@extends('layouts.app')

@section('content')
@php
use App\Support\ServiceDate;

$defaultServiceDate = $defaultServiceDate ?? ServiceDate::today();
$defaultPickupTime = $defaultPickupTime ?? app(\App\Services\DriverAvailabilityService::class)->suggestedPickupClock();
$bookingRestoreModal = $errors->any() && old('capacity')
    ? [
        'capacity' => old('capacity'),
        'vehicle_type' => old('vehicle_type'),
        'step' => 2,
    ]
    : null;

$platformHotlinePhone = (string) config('app.contact_phone');
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

    @include('partials.booking-active-session')

    <div id="booking-home-surface">
        <section class="be-home-topbar grab-home-topbar" aria-label="Trang đặt xe">
            <div>
                <p class="grab-home-topbar__eyebrow">Gọi xe</p>
            </div>
        </section>

        @if(($appliedReferral ?? null) || ($prefillReferral ?? '') !== '')
        <div class="grab-home-referral @if(($prefillReferral ?? '') !== '' && ! ($appliedReferral ?? null)) grab-home-referral--warning @endif mb-3">
            @if($appliedReferral ?? null)
                Giới thiệu <strong>{{ $appliedReferral->name }}</strong> · mã <span class="driver-meta-code">{{ $appliedReferral->code }}</span>
                @if($appliedReferral->grantsCustomerDiscount() && ($referralDiscountMeta['eligible'] ?? false))
                    <span class="text-success">— giảm {{ rtrim(rtrim(number_format($referralDiscountMeta['percent'] ?? 0, 1, '.', ''), '0'), '.') }}% khi đặt cuốc</span>
                @endif
            @elseif(($prefillReferral ?? '') !== '')
                Mã {{ $prefillReferral }} không hợp lệ.
            @endif
        </div>
        @endif

        @if($bookingPageBannerUrl ?? null)
        <section class="grab-home-banner" aria-label="Khuyến mãi">
            <img src="{{ $bookingPageBannerUrl }}" alt="" class="grab-home-banner__img" decoding="async">
        </section>
        @endif

        @include('partials.booking-route-card', [
            'defaultServiceDate' => $defaultServiceDate,
            'defaultPickupTime' => $defaultPickupTime,
        ])
    </div>

    @include('partials.booking-flow', [
        'defaultServiceDate' => $defaultServiceDate,
        'defaultPickupTime' => $defaultPickupTime,
        'driverOffers' => $driverOffers ?? collect(),
    ])

    @include('partials.customer-contact-fab', [
        'hotlinePhone' => $platformHotlinePhone,
        'variant' => 'fixed',
    ])
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/customer.css') }}?v={{ filemtime(public_path('css/customer.css')) }}">
<link rel="stylesheet" href="{{ asset('css/address-map-picker.css') }}?v={{ filemtime(public_path('css/address-map-picker.css')) }}">
@endpush

@push('scripts')
@include('partials.booking-form-scripts', [
    'bookingRestoreModal' => $bookingRestoreModal,
    'defaultServiceDate' => $defaultServiceDate,
    'defaultPickupTime' => $defaultPickupTime,
    'referralDiscountMeta' => $referralDiscountMeta ?? [],
    'appliedReferral' => $appliedReferral ?? null,
    'browserCancelCount' => $browserCancelCount ?? 0,
])
@endpush
