@php /** @var \App\Models\Booking $booking */ @endphp
<div class="cell-primary">{{ $booking->passenger_name ?: '—' }}</div>
<div class="cell-muted small">{{ $booking->contact_phone ?? '—' }}</div>
@if($booking->pickupTimeLabel())
    <div class="cell-muted small mt-1">🕐 Giờ đón: {{ $booking->pickupTimeLabel() }}</div>
@endif
@if($booking->pickup_address || $booking->pickup_detail)
    <div class="cell-muted small mt-1">📍 Địa chỉ đón: {{ $booking->driverPickupDetailLabel() }}</div>
@endif
@if($booking->dropoff_address || $booking->dropoff_detail)
    <div class="cell-muted small">🏁 Địa chỉ trả: {{ $booking->driverDropoffDetailLabel() }}</div>
@endif
@if($booking->notes)
    <div class="cell-muted small mt-1">📝 {{ \Illuminate\Support\Str::limit($booking->notes, 80) }}</div>
@endif
