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



$steps = [

    ['key' => \App\Models\Schedule::DRIVER_STAGE_AT_PICKUP, 'label' => 'Đến điểm đón'],

    ['key' => \App\Models\Schedule::DRIVER_STAGE_PICKED_UP, 'label' => 'Đón khách'],

    ['key' => \App\Models\Schedule::DRIVER_STAGE_RUNNING, 'label' => 'Đang chạy'],

    ['key' => \App\Models\Schedule::DRIVER_STAGE_COMPLETED, 'label' => 'Hoàn thành'],

];



$order = array_flip(\App\Models\Schedule::driverStageOrder());

$highlightStage = $stage === \App\Models\Schedule::DRIVER_STAGE_ASSIGNED

    ? \App\Models\Schedule::DRIVER_STAGE_AT_PICKUP

    : $stage;

$currentOrder = $order[$highlightStage] ?? 0;

@endphp



<div class="driver-workflow-compact" aria-label="Tiến trình chuyến">

    @include('partials.wait-progress', ['waitProgress' => $waitProgress, 'variant' => 'driver'])

    @php
        $latePickup = app(\App\Services\DriverLatePickupService::class);
        $departReminder = $latePickup->departReminderPayload($schedule);
        $latePickupPrompt = $latePickup->latePickupPromptPayload($schedule);
    @endphp
    @if($departReminder)
        <div class="driver-pickup-reminder mb-2" role="status">
            <strong>{{ $departReminder['message'] }}</strong>
            <p class="mb-0 small">{{ $departReminder['hint'] }}</p>
        </div>
    @endif
    @if($latePickupPrompt && ($latePickupPrompt['active'] ?? false))
        <div class="driver-late-pickup-banner mb-2" data-schedule-id="{{ $schedule->id }}" data-late-pickup-banner>
            <strong>{{ $latePickupPrompt['message'] }}</strong>
            <p class="mb-2 small">{{ $latePickupPrompt['hint'] }}</p>
            <button type="button"
                    class="btn btn-warning btn-sm driver-late-pickup-continue"
                    data-continue-url="{{ $latePickupPrompt['continue_url'] }}">
                Tiếp tục
            </button>
        </div>
    @endif

    <div class="driver-workflow-compact-steps">

        @foreach($steps as $step)

            @php

                $stepOrder = $order[$step['key']] ?? 0;

                if ($pendingClosure && $step['key'] === \App\Models\Schedule::DRIVER_STAGE_COMPLETED) {

                    $state = 'current';

                } else {

                    $state = $stepOrder < $currentOrder ? 'done' : ($step['key'] === $highlightStage ? 'current' : 'pending');

                }

            @endphp

            <span class="driver-workflow-compact-step is-{{ $state }}">{{ $step['label'] }}</span>

        @endforeach

    </div>



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

                      data-reason-title="Lý do hủy cuốc"

                      data-reason-hint="Chọn lý do để quản lý nắm thông tin và hỗ trợ khách.">

                    @csrf

                    <button type="submit" class="btn btn-outline-danger btn-sm">Hủy cuốc</button>

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

</div>

