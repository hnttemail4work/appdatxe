@php
/** @var \App\Models\Schedule $schedule */
use App\Support\DriverWaitProgress;

$bookings = $schedule->driverRelevantBookings();
$primaryBooking = $bookings->first();
$tripTotal = (float) $bookings->sum(fn (\App\Models\Booking $b) => (float) $b->total_price);
$stage = $schedule->resolvedDriverStage();
$phase = $schedule->driverWorkflowPhase();
$isAssigned = $stage === \App\Models\Schedule::DRIVER_STAGE_ASSIGNED;
$isAtPickup = $stage === \App\Models\Schedule::DRIVER_STAGE_AT_PICKUP;
$isInTransit = in_array($stage, [
    \App\Models\Schedule::DRIVER_STAGE_PICKED_UP,
    \App\Models\Schedule::DRIVER_STAGE_RUNNING,
], true);
/** Sau «Đã đến» (và khi đang chạy): hiện nút gọi. */
$showCall = ! $isAssigned;
$nextAction = $schedule->driverNextStageActionLabel();
$nextStage = $schedule->driverNextStage();
$pendingClosure = $schedule->driverPendingClosure();
$waitProgress = DriverWaitProgress::forSchedule($schedule);
$showComplete = $pendingClosure || $nextStage === \App\Models\Schedule::DRIVER_STAGE_COMPLETED;
$showAdvance = ! $pendingClosure && $nextAction && ! $showComplete && ! $isAssigned;
@endphp

{{-- Sheet chuyến live — đi đón / đã đến / đang di chuyển: cùng layout. --}}
<article class="driver-pickup-sheet is-peek {{ $isAtPickup ? 'driver-pickup-sheet--at-pickup' : '' }}{{ $isInTransit ? ' driver-pickup-sheet--in-transit' : '' }}"
         id="driver-pickup-sheet-{{ $schedule->id }}"
         data-schedule-id="{{ $schedule->id }}"
         data-driver-pickup-sheet
         data-driver-stage="{{ $stage }}">
    <button type="button" class="driver-pickup-sheet__handle" data-driver-pickup-sheet-handle
            aria-expanded="false" aria-label="Kéo lên để xem thông tin chuyến">
        <span class="driver-pickup-sheet__grip" aria-hidden="true"></span>
        <span class="driver-pickup-sheet__hint">Thông tin chuyến</span>
        @if($bookings->count() > 1)
            <span class="driver-pickup-sheet__peek-count">+{{ $bookings->count() - 1 }} khách</span>
        @endif
    </button>

    <div class="driver-pickup-sheet__body" id="driver-pickup-sheet-body-{{ $schedule->id }}">
        @if($waitProgress && ! $isAssigned)
            <div class="driver-pickup-sheet__wait">
                @include('partials.wait-progress', ['waitProgress' => $waitProgress, 'variant' => 'driver'])
            </div>
        @endif

        @forelse($bookings as $booking)
            @include('partials.driver-passenger-info-panel', [
                'booking' => $booking,
                'showCall' => $showCall,
            ])
        @empty
            <p class="text-muted small mb-0">Chưa có hành khách trên chuyến này.</p>
        @endforelse

        @if($bookings->isNotEmpty())
            <div class="driver-pax-total">
                Tổng chuyến: <strong>{{ number_format($tripTotal, 0, ',', '.') }} đ</strong>
            </div>
        @endif

        @if(in_array($phase, ['upcoming', 'active'], true))
        <div class="driver-pickup-sheet__cta-row {{ ($showAdvance || $showComplete) ? 'is-swipe-primary' : '' }}">
            @if($schedule->driverCanCancelTrip() && ! $pendingClosure)
                <form method="POST" action="{{ route('driver.schedules.cancel', $schedule) }}"
                      class="driver-pickup-sheet__cancel cancel-reason-form"
                      data-audience="driver"
                      data-reason-title="Lý do hủy chuyến"
                      data-reason-hint="Chọn lý do để quản lý nắm thông tin và hỗ trợ khách.">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger">Hủy chuyến</button>
                </form>
            @elseif(! $isAssigned)
                <span class="driver-pickup-sheet__cta-spacer" aria-hidden="true"></span>
            @endif

            @if($isAssigned)
                @if(! $schedule->driverCanCancelTrip())
                    <span class="driver-pickup-sheet__cta-spacer" aria-hidden="true"></span>
                @endif
                <form method="POST" action="{{ route('driver.schedules.advance', $schedule) }}"
                      class="driver-pickup-sheet__arrive"
                      data-driver-arrive-confirm
                      data-pickup-lat="{{ $primaryBooking?->pickup_lat }}"
                      data-pickup-lng="{{ $primaryBooking?->pickup_lng }}"
                      data-far-meters="800">
                    @csrf
                    <button type="submit" class="btn btn-primary">Đã đến</button>
                </form>
            @elseif($showComplete)
                <form method="POST" action="{{ route('driver.schedules.complete', $schedule) }}"
                      class="driver-pickup-sheet__arrive"
                      data-swipe-action>
                    @csrf
                    <button type="submit" class="btn btn-success">{{ $pendingClosure ? 'Xác nhận hoàn thành' : ($nextAction ?: 'Hoàn thành chuyến') }}</button>
                </form>
            @elseif($showAdvance)
                <form method="POST" action="{{ route('driver.schedules.advance', $schedule) }}"
                      class="driver-pickup-sheet__arrive"
                      data-swipe-action>
                    @csrf
                    <button type="submit" class="btn btn-primary">{{ $nextAction }}</button>
                </form>
            @endif
        </div>
        @endif
    </div>
</article>
