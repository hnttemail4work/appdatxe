@php
    $isCancelled = in_array($booking->booking_status, ['cancelled', 'rejected'], true)
        || $booking->trip_status === 'cancelled';
@endphp

<span class="status-pill status-pill--{{ $booking->operatorMonitorColor() }}">{{ $booking->operatorMonitorLabel() }}</span>

@if($isCancelled)
    <div class="mt-1">@include('partials.booking-cancel-detail', ['booking' => $booking])</div>
@endif
