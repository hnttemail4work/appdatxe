@php
/** @var \App\Models\DriverTripRequest $tripRequest */
/** @var \App\Models\Schedule $schedule */
/** @var \Illuminate\Support\Collection<int, \App\Models\Booking> $passengers */

use App\Support\DriverWaitProgress;

$schedule = $schedule ?? $tripRequest->schedule;
$passengers = $passengers ?? $schedule->driverRelevantBookings();
$waitProgress = DriverWaitProgress::forTripRequest($tripRequest);
$tripTotal = (float) $passengers->sum(fn (\App\Models\Booking $b) => (float) $b->total_price);
$expiresLabel = $tripRequest->acceptTimeRemainingLabel();
$primaryBooking = $passengers->first();
$pickupAt = $primaryBooking?->tripStartAt();
@endphp

<article class="driver-request-card driver-action-card driver-trip-request-card driver-request-card--incoming"
         data-trip-request-id="{{ $tripRequest->id }}"
         @if($tripRequest->expires_at) data-trip-request-expires="{{ $tripRequest->expires_at->toIso8601String() }}" @endif>
    <div class="driver-request-card__accent" aria-hidden="true"></div>

    <header class="driver-request-card__header">
        <div class="driver-request-card__header-copy">
            <div class="driver-request-card__eyebrow-row">
                <span class="driver-request-card__eyebrow">Cuốc chờ nhận</span>
                @if($schedule->shortTripCode())
                    <span class="driver-request-card__code">Mã {{ $schedule->shortTripCode() }}</span>
                @endif
            </div>
            <div class="driver-request-card__schedule">
                @if($passengers->count() > 0)
                    {{ $passengers->count() }} khách
                @endif
            </div>
        </div>
        <div class="driver-request-card__aside">
            @if($expiresLabel)
                <span class="driver-request-card__countdown">{{ $expiresLabel }}</span>
            @endif
            <div class="driver-request-card__fare">
                <span class="driver-request-card__fare-label">Thu khách</span>
                <strong class="driver-request-card__fare-value">{{ number_format($tripTotal, 0, ',', '.') }} đ</strong>
            </div>
        </div>
    </header>

    <div class="driver-request-card__route">
        @include('partials.driver-route-head', [
            'from' => $schedule->routeDepartureLabel(),
            'to' => $schedule->routeDestinationLabel(),
        ])
    </div>

    @if($primaryBooking)
        <div class="driver-request-card__pickup">
            <span class="driver-request-card__pickup-label">Đón</span>
            <span class="driver-request-card__pickup-value">
                {{ $primaryBooking->pickupTimeLabel() ? $primaryBooking->pickupTimeLabel() . ' · ' : '' }}{{ $primaryBooking->driverPickupDetailLabel() }}
            </span>
        </div>
    @endif

    <details class="driver-request-card__details">
        <summary class="driver-request-card__details-toggle">Chi tiết khách &amp; lộ trình</summary>
        <div class="driver-request-card__details-body">
            @include('partials.driver-schedule-passengers', [
                'schedule' => $schedule,
                'bookings' => $passengers,
                'showTripTotal' => false,
            ])
        </div>
    </details>

    @include('partials.wait-progress', [
        'waitProgress' => $waitProgress,
        'variant' => 'driver',
        'layout' => 'embedded',
    ])

    <div class="driver-card-actions driver-card-actions--job driver-request-card__actions">
        <form method="POST" action="{{ route('driver.tripRequests.accept', $tripRequest) }}" class="driver-accept-form driver-request-card__accept-form"
              data-confirm="Xác nhận nhận cuốc này?"
              data-confirm-title="Nhận cuốc"
              data-confirm-ok="Nhận cuốc"
              data-confirm-variant="success">
            @csrf
            <button type="submit" class="btn btn-success driver-btn-accept driver-request-card__accept-btn">Nhận cuốc</button>
        </form>
        <form method="POST" action="{{ route('driver.tripRequests.reject', $tripRequest) }}" class="driver-reject-form"
              data-confirm="Từ chối cuốc — hệ thống sẽ gán cho tài xế khác?"
              data-confirm-title="Từ chối cuốc"
              data-confirm-variant="danger"
              data-confirm-ok="Từ chối">
            @csrf
            <button type="submit" class="btn btn-driver-reject-ghost">Từ chối</button>
        </form>
    </div>
</article>
