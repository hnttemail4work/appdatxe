@php
/** @var \App\Models\Booking $booking */
$showTripReview = $showTripReview ?? true;
@endphp
<div class="cell-primary">{{ $booking->passenger_name ?: '—' }}</div>
<div class="cell-muted small">{{ $booking->contact_phone ?? '—' }}</div>
<div class="cell-muted small">{{ $booking->passengerProfileDetail() }}</div>
@if($label = $booking->cancelledByLabel())
    <div class="cell-muted small mt-1 text-warning">{{ $label }}</div>
@endif
@if($showTripReview)
    @if($booking->tripReview)
        <div class="cell-muted small mt-1">
            {{ $booking->tripReview->sentimentIcon() }} {{ $booking->tripReview->driverPreferenceLabel() }}
            @if($booking->tripReview->comment)
                · “{{ \Illuminate\Support\Str::limit($booking->tripReview->comment, 120) }}”
            @endif
            <span class="text-muted">({{ $booking->tripReview->created_at?->format('d/m/Y H:i') }})</span>
        </div>
    @else
        <div class="cell-muted small mt-1 text-muted">Chưa có phản hồi khách</div>
    @endif
@endif
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
