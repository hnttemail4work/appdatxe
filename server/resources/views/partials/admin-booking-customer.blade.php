@php /** @var \App\Models\Booking $booking */ @endphp
<div class="cell-primary">{{ $booking->passenger_name ?: '—' }}</div>
<div class="cell-muted small">{{ $booking->contact_phone ?? '—' }}</div>
@if($booking->pickup_address || $booking->pickup_detail)
    <div class="cell-muted small mt-1">📍 Đón: {{ $booking->pickupLabel() }}</div>
@endif
@if($booking->dropoff_address || $booking->dropoff_detail)
    <div class="cell-muted small">🏁 Trả: {{ $booking->dropoffLabel() }}</div>
@endif
@if($booking->notes)
    <div class="cell-muted small mt-1">📝 {{ \Illuminate\Support\Str::limit($booking->notes, 80) }}</div>
@endif
@if($booking->referral_code)
    <div class="cell-muted small mt-1">Mã GT: <strong>{{ $booking->referral_code }}</strong></div>
@endif
