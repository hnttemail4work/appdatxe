@extends('layouts.app')

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

    <div class="customer-hero customer-hero--compact mb-3">
        <h1 class="h4 mb-0">Xem chuyến</h1>
    </div>

    @include('partials.guest-trip-panel')

    @include('partials.customer-contact-fab', [
        'hotlinePhone' => $platformHotlinePhone,
        'variant' => 'fixed',
    ])
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/customer.css') }}?v={{ filemtime(public_path('css/customer.css')) }}">
@endpush

@push('scripts')
<script>
window.__bookingTripStatusUrl = @json(route('booking.tripStatus'));
window.__bookingTripReviewUrl = @json(route('booking.tripReview'));
window.__bookingTripCancelUrl = @json(route('booking.tripCancel'));
window.__bookingSuccess = @json(session('booking_success'));
window.__bookingQrDiscountPercent = @json(\App\Support\PlatformFees::bookingQrDiscountPercent());
window.__appContactPhone = @json(config('app.contact_phone'));
window.__guestBrowserCancelCount = @json((int) ($browserCancelCount ?? 0));
window.__guestBrowserCancelBlockLimit = @json(\App\Services\BookingBrowserGuardService::CANCEL_BLOCK_LIMIT);
</script>
<script src="{{ asset('js/booking-browser-guard.js') }}?v={{ filemtime(public_path('js/booking-browser-guard.js')) }}"></script>
<script src="{{ asset('js/idle-poll.js') }}?v={{ filemtime(public_path('js/idle-poll.js')) }}"></script>
<script src="{{ asset('js/booking-active-session.js') }}?v={{ filemtime(public_path('js/booking-active-session.js')) }}"></script>
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
