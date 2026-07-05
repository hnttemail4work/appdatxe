@php
/** @var \App\Models\Booking $booking */
$alert = $booking->adminPickupAlert();
@endphp
@if($alert)
    <span class="status-pill status-pill--{{ $alert['level'] }}" title="{{ $alert['detail'] }}">
        {{ $alert['label'] }}
    </span>
    <div class="cell-muted small mt-1">{{ $alert['detail'] }}</div>
@else
    <span class="text-muted">—</span>
@endif
