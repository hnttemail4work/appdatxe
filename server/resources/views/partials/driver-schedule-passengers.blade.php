@php
/** @var \App\Models\Schedule $schedule */
/** @var \Illuminate\Support\Collection<int, \App\Models\Booking> $bookings */
$bookings = $bookings ?? $schedule->driverRelevantBookings();
$tripTotal = $tripTotal ?? (float) $bookings->sum(fn (\App\Models\Booking $b) => (float) $b->total_price);
$showTripTotal = $showTripTotal ?? true;
$phase = $phase ?? $schedule->driverWorkflowPhase();
/** Sheet đón (peek đã hiện tên + điểm đón): chỉ bổ sung phần chưa có. */
$omitPickupDupes = (bool) ($omitPickupDupes ?? false);
@endphp

@if($bookings->isEmpty())
    <p class="text-muted small mb-0">Chưa có hành khách trên chuyến này.</p>
@else
    <div class="driver-passenger-list">
        @foreach($bookings as $booking)
        <div class="driver-passenger-item {{ ! $loop->last ? 'driver-passenger-item--split' : '' }}">
            @if(! $omitPickupDupes || $bookings->count() > 1)
            <div class="driver-passenger-head">
                <strong>{{ $booking->passenger_name ?: 'Hành khách' }}</strong>
            </div>
            @endif
            @if($booking->passengerProfileDetail())
                <div class="driver-info-line">{{ $booking->passengerProfileDetail() }}</div>
            @endif
            @unless($omitPickupDupes)
            <div class="driver-info-line"><span class="driver-info-k">Điểm đón</span> {{ $booking->driverPickupDetailLabel() }}</div>
            @endunless
            <div class="driver-info-line"><span class="driver-info-k">Điểm trả</span> {{ $booking->driverDropoffDetailLabel() }}</div>
            @if($booking->notes)
                <div class="driver-info-line driver-info-line--note">{{ $booking->notes }}</div>
            @endif
            @if($showCancelDetail ?? false)
                @include('partials.booking-cancel-detail', ['booking' => $booking])
            @endif
        </div>
        @endforeach
    </div>
    @if($showTripTotal && $phase !== 'settled')
    <div class="driver-trip-total border-top pt-2 mt-2">
        Tổng chuyến: <strong>{{ number_format($tripTotal, 0, ',', '.') }} đ</strong>
    </div>
    @endif
@endif
