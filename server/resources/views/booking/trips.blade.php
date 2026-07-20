@extends('layouts.app')

@section('navTitle', 'Chuyến')
@section('navBack', route('home'))

@section('content')
@php
$platformHotlinePhone = (string) config('app.contact_phone');
@endphp

<div class="customer-page guest-trip-page" id="guest-trip-page">
    @if(session('booking_success'))
        @php $bookingSuccess = session('booking_success'); @endphp
        <div class="booking-flash booking-flash-success mb-3 app-flash" role="alert" data-auto-dismiss="10000">
            <div class="booking-flash-icon" aria-hidden="true">✓</div>
            <div class="booking-flash-body">
                <strong class="booking-flash-title">Đặt chuyến thành công!</strong>
                <p class="mb-1">
                    Mã chuyến: <span class="booking-ticket-code">{{ $bookingSuccess['trip_code'] ?? '—' }}</span>
                </p>
            </div>
            @include('partials.flash-close')
        </div>
    @endif

    @include('partials.guest-trip-panel')
    @include('partials.address-map-picker-modal')

    @include('partials.trip-action-fabs', [
        'hotlinePhone' => $platformHotlinePhone,
        'showLocateBtn' => true,
        'inTrip' => false,
    ])
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/customer.css') }}?v={{ filemtime(public_path('css/customer.css')) }}">
<link rel="stylesheet" href="{{ asset('css/trip-chat.css') }}?v={{ filemtime(public_path('css/trip-chat.css')) }}">
<link rel="stylesheet" href="{{ asset('css/address-map-picker.css') }}?v={{ filemtime(public_path('css/address-map-picker.css')) }}">
@endpush

@push('scripts')
<script>
window.__bookingTripStatusUrl = @json(route('booking.tripStatus'));
window.__bookingTripReviewUrl = @json(route('booking.tripReview'));
window.__bookingTripCancelUrl = @json(route('booking.tripCancel'));
window.__bookingChangeDropoffUrl = @json(route('booking.changeDropoff'));
window.__bookingPreviewChangeDropoffUrl = @json(route('booking.previewChangeDropoff'));
window.__bookingSuccess = @json(session('booking_success'));
window.__appContactPhone = @json(config('app.contact_phone'));
window.__guestBrowserCancelCount = @json((int) ($browserCancelCount ?? 0));
window.__guestBrowserCancelBlockLimit = @json(\App\Services\BookingBrowserGuardService::CANCEL_BLOCK_LIMIT);
window.__guestBrowserEnforceCancelBlock = @json(\App\Services\BookingBrowserGuardService::ENFORCE_CANCEL_BLOCK);
@include('partials.geocode-client-config')
</script>
<script src="{{ asset('js/booking-browser-guard.js') }}?v={{ filemtime(public_path('js/booking-browser-guard.js')) }}"></script>
<script src="{{ asset('js/idle-poll.js') }}?v={{ filemtime(public_path('js/idle-poll.js')) }}"></script>
<script src="{{ asset('js/booking-active-session.js') }}?v={{ filemtime(public_path('js/booking-active-session.js')) }}"></script>
<script src="{{ asset('js/wait-progress.js') }}?v={{ filemtime(public_path('js/wait-progress.js')) }}"></script>
<script src="{{ asset('js/address-query-normalize.js') }}?v={{ filemtime(public_path('js/address-query-normalize.js')) }}"></script>
<script src="{{ asset('js/geocode-search-ui.js') }}?v={{ filemtime(public_path('js/geocode-search-ui.js')) }}"></script>
<script src="{{ asset('js/geocode-resolve.js') }}?v={{ filemtime(public_path('js/geocode-resolve.js')) }}"></script>
<script src="{{ asset('js/address-map-picker.js') }}?v={{ filemtime(public_path('js/address-map-picker.js')) }}"></script>
<script src="{{ asset('js/trip-chat.js') }}?v={{ filemtime(public_path('js/trip-chat.js')) }}"></script>
<script src="{{ asset('js/driver-call-reveal.js') }}?v={{ filemtime(public_path('js/driver-call-reveal.js')) }}"></script>
<script src="{{ asset('js/trip-action-fabs.js') }}?v={{ filemtime(public_path('js/trip-action-fabs.js')) }}"></script>
<script src="{{ asset('js/map-sheet-camera.js') }}?v={{ filemtime(public_path('js/map-sheet-camera.js')) }}"></script>
<script src="{{ asset('js/guest-trip-live-map.js') }}?v={{ filemtime(public_path('js/guest-trip-live-map.js')) }}"></script>
<script src="{{ asset('js/guest-trip-sheet.js') }}?v={{ filemtime(public_path('js/guest-trip-sheet.js')) }}"></script>
<script src="{{ asset('js/guest-trip-page.js') }}?v={{ filemtime(public_path('js/guest-trip-page.js')) }}"></script>
<script src="{{ asset('js/customer-scroll-dock.js') }}?v={{ filemtime(public_path('js/customer-scroll-dock.js')) }}"></script>
@if(session('booking_success'))
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!window.PwaClient) return;
    var phone = @json(session('booking_success.contact_phone'));
    if (phone) {
        window.PwaClient.touchContactPhone(phone);
    }
    window.PwaClient.afterBookingSuccess();
});
</script>
@endif
@endpush
