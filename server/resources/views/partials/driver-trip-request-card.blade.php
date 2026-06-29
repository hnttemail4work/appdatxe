@php
/** @var \App\Models\DriverTripRequest $req */
/** @var \App\Models\Schedule $schedule */
/** @var \Illuminate\Support\Collection<int, \App\Models\Booking> $passengers */
$schedule = $schedule ?? $req->schedule;
$passengers = $passengers ?? collect([$req->relatedBooking()])->filter();
$walletBlocked = (bool) ($walletBlockReason ?? null);
@endphp
<div class="driver-request-card" data-request-id="{{ $req->id }}">
    <div class="driver-card-top">
        <div>
            <div class="route">{{ $schedule->route->departure }} → {{ $schedule->route->destination }}</div>
            <div class="meta">{{ $schedule->tripMetaLabel() }}</div>
            @if($schedule->shortTripCode())
                <div class="meta driver-schedule-trip-code">Mã chuyến: <code class="driver-trip-code">{{ $schedule->shortTripCode() }}</code></div>
            @endif
            @if($passengers->count() > 1)
                <div class="meta">{{ $passengers->count() }} khách ghép</div>
            @endif
            @if($label = $req->acceptTimeRemainingLabel())
                <div class="meta text-warning">Còn {{ $label }} để nhận</div>
            @endif
        </div>
        <div class="driver-card-top-aside text-end">
            <span class="status-pill status-pill--accent">Cuốc mới</span>
        </div>
    </div>
    <div class="driver-card-body">
        @include('partials.driver-schedule-passengers', [
            'schedule' => $schedule,
            'bookings' => $passengers,
            'showTripTotal' => true,
        ])
    </div>
    <div class="driver-card-actions d-flex gap-2 flex-wrap justify-content-end">
        <form method="POST" action="{{ route('driver.tripRequests.accept', $req) }}">@csrf
            <button class="btn btn-success btn-sm px-4" @if($walletBlocked) disabled @endif>Nhận cuốc</button>
        </form>
        <form method="POST" action="{{ route('driver.tripRequests.reject', $req) }}">@csrf
            <button class="btn btn-driver-reject btn-sm px-4" @if($walletBlocked) disabled @endif>Từ chối</button>
        </form>
    </div>
</div>
