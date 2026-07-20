@php
/** @var \App\Models\Schedule $schedule */
use App\Support\DriverWaitProgress;

$bookings = $schedule->driverRelevantBookings();
$waitProgress = DriverWaitProgress::forSchedule($schedule);
$phase = $schedule->driverWorkflowPhase();
$stage = $schedule->resolvedDriverStage();
$nextAction = $schedule->driverNextStageActionLabel();
$nextStage = $schedule->driverNextStage();
$pendingClosure = $schedule->driverPendingClosure();
$isAssigned = $stage === \App\Models\Schedule::DRIVER_STAGE_ASSIGNED;
@endphp

<div class="driver-workflow-compact {{ $isAssigned ? 'driver-workflow-compact--assigned' : '' }}" aria-label="Tiến trình chuyến">

    {{-- Sau nhận cuốc (ASSIGNED): chỉ Hủy + Đã đến — không countdown / bước / vuốt / xác nhận. --}}
    @if($isAssigned && in_array($phase, ['upcoming', 'active'], true))
        <div class="driver-workflow-compact-actions driver-workflow-compact-actions--assigned">
            @if($schedule->driverCanCancelTrip())
                <form method="POST" action="{{ route('driver.schedules.cancel', $schedule) }}"
                      class="driver-workflow-compact-action cancel-reason-form"
                      data-audience="driver"
                      data-reason-title="Lý do hủy chuyến"
                      data-reason-hint="Chọn lý do để quản lý nắm thông tin và hỗ trợ khách.">
                    @csrf
                    <button type="submit" class="btn btn-outline-danger">Hủy chuyến</button>
                </form>
            @endif
            <form method="POST" action="{{ route('driver.schedules.advance', $schedule) }}"
                  class="driver-workflow-compact-action">
                @csrf
                <button type="submit" class="btn btn-primary">Đã đến</button>
            </form>
        </div>
    @else
        @if($waitProgress)
            @include('partials.wait-progress', ['waitProgress' => $waitProgress, 'variant' => 'driver'])
        @endif

        @if($phase === 'settled')
            <p class="small text-success mb-0">Chuyến đã hoàn tất.</p>
        @elseif(in_array($phase, ['upcoming', 'active'], true))
            @php
                $showCancel = ! $pendingClosure && $schedule->driverCanCancelTrip();
                $showComplete = $pendingClosure || $nextStage === \App\Models\Schedule::DRIVER_STAGE_COMPLETED;
                $showAdvance = ! $pendingClosure && $nextAction && ! $showComplete;
            @endphp

            @if($showCancel || $showAdvance || $showComplete)
            <div class="driver-workflow-compact-actions">
                @if($showCancel)
                    <form method="POST" action="{{ route('driver.schedules.cancel', $schedule) }}"
                          class="driver-workflow-compact-action cancel-reason-form"
                          data-audience="driver"
                          data-reason-title="Lý do hủy chuyến"
                          data-reason-hint="Chọn lý do để quản lý nắm thông tin và hỗ trợ khách.">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger btn-sm">Hủy chuyến</button>
                    </form>
                @endif

                @if($showComplete)
                    <form method="POST" action="{{ route('driver.schedules.complete', $schedule) }}"
                          class="driver-workflow-compact-action"
                          data-confirm="Xác nhận đã chạy xong chuyến này ({{ $bookings->count() }} khách)?"
                          data-confirm-title="Hoàn thành chuyến"
                          data-confirm-ok="Hoàn thành"
                          data-confirm-variant="success">
                        @csrf
                        <button type="submit" class="btn btn-success btn-sm">{{ $pendingClosure ? 'Xác nhận hoàn thành' : ($nextAction ?: 'Hoàn thành chuyến') }}</button>
                    </form>
                @elseif($showAdvance)
                    <form method="POST" action="{{ route('driver.schedules.advance', $schedule) }}"
                          class="driver-workflow-compact-action">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-sm">{{ $nextAction }}</button>
                    </form>
                @endif
            </div>
            @endif
        @endif
    @endif

</div>
