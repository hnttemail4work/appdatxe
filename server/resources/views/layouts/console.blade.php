@extends('layouts.app')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/console.css') }}?v={{ filemtime(public_path('css/console.css')) }}">
<link rel="stylesheet" href="{{ asset('css/operator-notifications.css') }}">
@endpush

@push('scripts')
<script src="{{ asset('js/operator-notifications.js') }}"></script>
@if(auth()->check() && auth()->user()->role === 'operator')
<script src="{{ asset('js/share-booking-qr.js') }}"></script>
@endif
@endpush

@if(auth()->check() && auth()->user()->role === 'operator')
@push('modals')
    @include('partials.share-booking-qr-modal', [
        'shareUrl' => \App\Support\BookingShareUrl::guest(),
        'shareLabel' => 'QR đặt vé chung',
        'modalId' => 'shareQrModal-operator-guest',
    ])
@endpush
@endif

@section('content')
<div class="console-page">
    @yield('console')
</div>
@endsection
