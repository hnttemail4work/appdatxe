@php
/** @var \App\Models\Schedule $schedule */
$bookings = $schedule->driverRelevantBookings();
$phase = $schedule->driverWorkflowPhase();
$stage = $schedule->resolvedDriverStage();
$showActions = $showActions ?? true;
$needsAction = $showActions && in_array($phase, ['upcoming', 'active'], true);
$isRunning = in_array($phase, ['upcoming', 'active'], true);
$isAssigned = $stage === \App\Models\Schedule::DRIVER_STAGE_ASSIGNED;
$primaryBooking = $bookings->first();
$mapNav = ($primaryBooking && $isRunning && ! $isAssigned)
    ? \App\Support\MapNavigation::driverTargetForSchedule($schedule, $primaryBooking)
    : null;
@endphp

@if($isAssigned)
    {{-- Đang đi đón: sheet peek/full riêng + map nav turn-by-turn — không dùng layout card chung (tránh lặp). --}}
    @include('partials.driver-pickup-sheet', ['schedule' => $schedule])
@else
<article class="driver-trip-card driver-trip-card--compact {{ $needsAction ? 'is-action' : '' }} {{ $isRunning ? 'is-running' : '' }}" data-schedule-id="{{ $schedule->id }}">
    <div class="driver-card-top">
        <div class="driver-card-top-main">
            @include('partials.driver-route-head', [
                'from' => $primaryBooking?->driverPickupDetailLabel() ?: $schedule->routeDepartureLabel(),
                'to' => $primaryBooking?->driverDropoffDetailLabel() ?: $schedule->routeDestinationLabel(),
            ])
            @if($schedule->shortTripCode())
                <div class="meta driver-schedule-trip-code">
                    Mã chuyến: <code class="driver-trip-code">{{ $schedule->shortTripCode() }}</code>
                </div>
            @endif
            @php
                $pickupTimeLabel = $primaryBooking?->pickupTimeLabel();
                $pickupDateLabel = $primaryBooking?->driverPickupDateLabel();
            @endphp
            @if($primaryBooking?->isScheduledPickup() && ($pickupTimeLabel || $pickupDateLabel))
                <div class="meta driver-schedule-pickup-meta">
                    @if($pickupTimeLabel)
                        <span>Giờ đón: <strong>{{ $pickupTimeLabel }}</strong></span>
                    @endif
                    @if($pickupDateLabel)
                        <span>Ngày đi: <strong>{{ $pickupDateLabel }}</strong></span>
                    @endif
                </div>
            @elseif($primaryBooking)
                <div class="meta driver-schedule-pickup-meta">
                    <span><strong>{{ $primaryBooking->pickupModeLabel() }}</strong></span>
                </div>
            @endif
        </div>
        <div class="driver-card-top-aside">
            <span class="status-pill status-pill--{{ $schedule->driverWorkflowColor() }}">{{ $schedule->driverWorkflowLabel() }}</span>
        </div>
    </div>

    <div class="driver-card-body driver-card-body--compact">
        @include('partials.driver-schedule-passengers', [
            'schedule' => $schedule,
            'bookings' => $bookings,
            'showTripTotal' => $showTripTotal ?? ($showActions ?? true),
        ])
    </div>

    {{-- ASSIGNED dùng partials.driver-pickup-sheet ở nhánh trên — đến đây luôn là các stage sau đón. --}}
    @if($isRunning)
        @include('partials.driver-trip-quick-actions', [
            'booking' => $primaryBooking,
            'mapNav' => $mapNav,
            'inAppNavOnly' => true,
        ])
    @endif

    @if($showActions && $phase !== 'settled')
    <div class="driver-card-actions driver-card-actions--compact">
        @include('partials.driver-schedule-workflow', ['schedule' => $schedule])
    </div>
    @endif
</article>
@endif
