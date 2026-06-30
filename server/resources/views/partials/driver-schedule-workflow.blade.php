@php
/** @var \App\Models\Schedule $schedule */
$bookings = $schedule->driverRelevantBookings();
$phase = $schedule->driverWorkflowPhase();
$incomplete = $schedule->driverIncompleteBookings();

$currentKey = match (true) {
    $phase === 'settled' => 'done',
    in_array($phase, ['upcoming', 'active'], true) && $incomplete->isNotEmpty() => 'complete',
    in_array($phase, ['upcoming', 'active'], true) => 'upcoming',
    default => 'upcoming',
};

$steps = [
    ['key' => 'upcoming', 'label' => 'Sắp chạy'],
    ['key' => 'complete', 'label' => 'Hoàn thành'],
    ['key' => 'done', 'label' => 'Hoàn tất'],
];

$order = ['upcoming' => 0, 'complete' => 1, 'done' => 2];
$currentOrder = $order[$currentKey] ?? 0;
@endphp

<div class="driver-workflow-compact" aria-label="Tiến trình chuyến">
    <div class="driver-workflow-compact-steps">
        @foreach($steps as $step)
            @php
                $stepOrder = $order[$step['key']] ?? 0;
                $state = $stepOrder < $currentOrder ? 'done' : ($step['key'] === $currentKey ? 'current' : 'pending');
            @endphp
            <span class="driver-workflow-compact-step is-{{ $state }}">{{ $step['label'] }}</span>
        @endforeach
    </div>

    @if($currentKey === 'complete' && in_array($phase, ['upcoming', 'active'], true))
        @if($schedule->driverCanCancelTrip())
            <form method="POST" action="{{ route('driver.schedules.cancel', $schedule) }}"
                  class="mb-2 cancel-reason-form"
                  data-audience="driver"
                  data-reason-title="Lý do hủy chuyến"
                  data-reason-hint="Chọn lý do để quản lý nắm thông tin và hỗ trợ khách.">
                @csrf
                <button type="submit" class="btn btn-outline-danger btn-sm w-100 mb-2">Hủy chuyến (khách chưa lên xe)</button>
            </form>
        @endif
        <form method="POST" action="{{ route('driver.schedules.complete', $schedule) }}"
              data-confirm="Xác nhận đã chạy xong chuyến này ({{ $bookings->count() }} khách)?"
              data-confirm-title="Hoàn thành chuyến"
              data-confirm-ok="Hoàn thành"
              data-confirm-variant="success">
            @csrf
            <button class="btn btn-success btn-sm w-100">Hoàn thành chuyến</button>
        </form>

    @elseif($currentKey === 'done' && $phase === 'settled')
        <p class="small text-success mb-0">Chuyến đã hoàn tất.</p>

    @elseif($currentKey === 'upcoming' && in_array($phase, ['upcoming', 'active'], true))
        @if($schedule->driverCanCancelTrip())
            <form method="POST" action="{{ route('driver.schedules.cancel', $schedule) }}"
                  class="mb-2 cancel-reason-form"
                  data-audience="driver"
                  data-reason-title="Lý do hủy chuyến"
                  data-reason-hint="Chọn lý do để quản lý nắm thông tin và hỗ trợ khách.">
                @csrf
                <button type="submit" class="btn btn-outline-danger btn-sm w-100">Hủy chuyến (khách chưa lên xe)</button>
            </form>
        @endif
        <p class="small text-muted mb-0">Chuẩn bị khởi hành theo giờ trên vé.</p>
    @endif
</div>
