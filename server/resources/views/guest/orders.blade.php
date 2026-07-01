@extends('layouts.app')

@section('content')
<div class="customer-page customer-page--orders" id="booking-page-top">
    <div class="guest-orders-head mb-3">
        <a href="{{ route('home') }}" class="guest-orders-back">
            <span aria-hidden="true">←</span> Đặt vé
        </a>
    </div>

    @include('partials.guest-trip-watch')

    @include('partials.customer-scroll-dock', ['customerDockMode' => 'orders'])
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/customer.css') }}?v={{ filemtime(public_path('css/customer.css')) }}">
@endpush

@push('scripts')
<script>
window.__guestOrdersUrl = @json(route('guest.orders'));
window.__customerHomeUrl = @json(route('home'));
window.__guestTripWatchUrl = @json(route('guest.tripWatch'));
window.__guestTripReloadMs = @json(\App\Services\GuestTripWatchService::GUEST_PAGE_RELOAD_SECONDS * 1000);
window.__guestTripSearchingReload = @json(request()->boolean('searching'));
window.__guestTripReviewUrl = @json(route('guest.tripReviews.store'));
window.__guestTripCancelUrl = @json(route('guest.bookings.cancel'));
window.__guestActiveOrdersCount = @json($guestActiveOrdersCount);
window.__guestWatchlistCount = @json($guestWatchlistCount);
window.__guestOrdersPage = true;
window.__cancellationReasonsUrl = @json(route('cancellationReasons.index'));
</script>
<script src="{{ asset('js/wait-progress.js') }}?v={{ filemtime(public_path('js/wait-progress.js')) }}"></script>
<script src="{{ asset('js/guest-trip-watch.js') }}?v={{ filemtime(public_path('js/guest-trip-watch.js')) }}"></script>
<script src="{{ asset('js/customer-scroll-dock.js') }}?v={{ filemtime(public_path('js/customer-scroll-dock.js')) }}"></script>
@endpush
