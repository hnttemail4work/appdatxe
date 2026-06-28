@php
/** @var \App\Models\Schedule $schedule */
$bookings = $schedule->driverRelevantBookings();
$phase = $schedule->driverWorkflowPhase();
$showActions = $showActions ?? true;
$needsAction = $showActions && in_array($phase, ['active', 'needs_settle', 'enter_settle_code'], true);
$settlement = $schedule->tripSettlement;
@endphp

<article class="driver-trip-card {{ $needsAction ? 'is-action' : '' }}">
    <div class="driver-card-top">
        <div>
            <div class="route">{{ $schedule->route->departure }} → {{ $schedule->route->destination }}</div>
            <div class="meta">{{ $schedule->tripMetaLabel() }}</div>
            @if($schedule->shortTripCode())
                <div class="meta driver-schedule-trip-code">Mã chuyến · <code class="driver-trip-code">{{ $schedule->shortTripCode() }}</code></div>
            @endif
        </div>
        <span class="badge bg-{{ $schedule->driverWorkflowColor() }}">{{ $schedule->driverWorkflowLabel() }}</span>
    </div>

    <div class="driver-card-body">
        @include('partials.driver-schedule-passengers', [
            'schedule' => $schedule,
            'bookings' => $bookings,
            'showTripTotal' => $showTripTotal ?? ($showActions ?? true),
        ])
    </div>

    @if($showActions)
    <div class="driver-card-actions">
        @include('partials.driver-schedule-workflow', ['schedule' => $schedule])
    </div>
    @elseif($phase === 'settled' && $settlement)
    <div class="driver-card-actions py-2">
        <p class="small text-success mb-0 fw-semibold">✓ Đã kết chuyến · Doanh thu {{ number_format($settlement->revenue_amount, 0, ',', '.') }} đ</p>
    </div>
    @endif
</article>
