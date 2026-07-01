@php
/** @var \App\Models\DriverTripRequest $req */
/** @var \App\Models\Schedule $schedule */
/** @var \Illuminate\Support\Collection<int, \App\Models\Booking> $passengers */
$schedule = $schedule ?? $req->schedule;
$passengers = $passengers ?? collect([$req->relatedBooking()])->filter();
$tripTotal = (float) $passengers->sum(fn (\App\Models\Booking $b) => (float) $b->total_price);
@endphp
<div class="driver-request-card driver-action-card" data-request-id="{{ $req->id }}">
    <div class="driver-card-top">
        <div class="driver-card-top-main">
            @include('partials.driver-route-head', [
                'from' => $schedule->route->departure,
                'to' => $schedule->route->destination,
            ])
            <div class="driver-card-meta-row">
                <span class="driver-meta-chip">{{ $schedule->tripMetaLabel() }}</span>
                @if($passengers->count() > 1)
                    <span class="driver-meta-chip">{{ $passengers->count() }} khách</span>
                @endif
                @if($label = $req->acceptTimeRemainingLabel())
                    <span class="driver-meta-chip driver-meta-chip--warn">⏱ {{ $label }}</span>
                @endif
            </div>
            @if($schedule->shortTripCode())
                <div class="meta driver-schedule-trip-code">Mã <code class="driver-trip-code">{{ $schedule->shortTripCode() }}</code></div>
            @endif
        </div>
        <div class="driver-card-top-aside">
            @if($tripTotal > 0)
                <div class="driver-fare-badge">
                    <span class="driver-fare-label">Tổng</span>
                    <span class="driver-fare-amount">{{ number_format($tripTotal, 0, ',', '.') }} đ</span>
                </div>
            @endif
            <span class="status-pill status-pill--accent">Cuốc mới</span>
        </div>
    </div>
    <div class="driver-card-body">
        @include('partials.driver-schedule-passengers', [
            'schedule' => $schedule,
            'bookings' => $passengers,
            'showTripTotal' => false,
        ])
    </div>
    <div class="driver-card-actions driver-card-actions--job">
        <form method="POST" action="{{ route('driver.tripRequests.accept', $req) }}" class="driver-accept-form">@csrf
            <button type="submit" class="btn btn-success driver-btn-accept">Nhận cuốc</button>
        </form>
        <form method="POST" action="{{ route('driver.tripRequests.reject', $req) }}" class="driver-reject-form">@csrf
            <button type="submit" class="btn btn-driver-reject-ghost">Từ chối</button>
        </form>
    </div>
</div>
