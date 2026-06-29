@php
/** @var \App\Models\Schedule $schedule */
$bookings = $schedule->driverRelevantBookings();
$phase = $schedule->driverWorkflowPhase();
$incomplete = $schedule->driverIncompleteBookings();
$settlement = $schedule->tripSettlement;
$totalRevenue = $bookings->sum(fn ($b) => (float) $b->total_price);

$currentKey = match (true) {
    $phase === 'settled' => 'done',
    $phase === 'enter_settle_code' => 'code',
    $phase === 'needs_settle' => 'fee',
    in_array($phase, ['upcoming', 'active'], true) && $incomplete->isNotEmpty() => 'complete',
    in_array($phase, ['upcoming', 'active'], true) => 'upcoming',
    default => 'upcoming',
};

$steps = [
    ['key' => 'upcoming', 'label' => 'Sắp chạy'],
    ['key' => 'complete', 'label' => 'Hoàn thành'],
    ['key' => 'fee', 'label' => 'Chuyển phí'],
    ['key' => 'code', 'label' => 'Kết chuyến'],
    ['key' => 'done', 'label' => 'Hoàn tất'],
];

$order = ['upcoming' => 0, 'complete' => 1, 'fee' => 2, 'code' => 3, 'done' => 4];
$currentOrder = $order[$currentKey] ?? 0;

$visibleSteps = collect($steps)->filter(function (array $step) use ($settlement): bool {
    return ! ($step['key'] === 'code' && $settlement?->isUnderThreshold());
})->values();
@endphp

<div class="driver-workflow-compact" aria-label="Tiến trình chuyến">
    <div class="driver-workflow-compact-steps">
        @foreach($visibleSteps as $step)
            @php
                $stepOrder = $order[$step['key']] ?? 0;
                $state = $stepOrder < $currentOrder ? 'done' : ($step['key'] === $currentKey ? 'current' : 'pending');
            @endphp
            <span class="driver-workflow-compact-step is-{{ $state }}">{{ $step['label'] }}</span>
        @endforeach
    </div>

    @if($currentKey === 'complete' && in_array($phase, ['upcoming', 'active'], true))
        <form method="POST" action="{{ route('driver.schedules.complete', $schedule) }}"
              data-confirm="Xác nhận đã chạy xong chuyến này ({{ $bookings->count() }} khách)?"
              data-confirm-title="Hoàn thành chuyến"
              data-confirm-ok="Hoàn thành"
              data-confirm-variant="success">
            @csrf
            <button class="btn btn-success btn-sm w-100">Hoàn thành chuyến</button>
        </form>

    @elseif($currentKey === 'fee' && $settlement && $phase === 'needs_settle')
        @php $feeQrId = 'platform-fee-qr-' . $settlement->id; @endphp
        <div class="driver-workflow-compact-panel">
            <div class="small text-muted mb-2">
                Phí <strong>{{ number_format($settlement->platform_fee_amount, 0, ',', '.') }} đ</strong>
                Doanh thu {{ number_format($totalRevenue, 0, ',', '.') }} đ
            </div>
            @if($settlement->driverConfirmedTransfer())
                <p class="small text-muted mb-0">
                    @if($settlement->isUnderThreshold())
                        Đã xác nhận chuyển phí — chờ quản lý.
                    @else
                        Đã xác nhận CK, chờ quản lý cấp mã.
                    @endif
                </p>
            @else
                @include('partials.platform-fee-transfer', [
                    'feeAmount' => $settlement->platform_fee_amount,
                    'qrElementId' => $feeQrId,
                ])
                @if($settlement->isUnderThreshold())
                    <form method="POST" action="{{ route('driver.settlements.confirmTransfer', $settlement) }}" class="mt-2">
                        @csrf
                        <button class="btn btn-success btn-sm w-100">Đã chuyển phí</button>
                    </form>
                @else
                    @include('partials.transfer-confirm-form', [
                        'action' => route('driver.settlements.confirmTransfer', $settlement),
                        'amount' => $settlement->platform_fee_amount,
                        'amountEditable' => false,
                        'startLabel' => 'Đã chuyển phí',
                        'confirmLabel' => 'Xác nhận',
                        'formId' => 'settlement-transfer-' . $settlement->id,
                        'qrElementId' => $feeQrId,
                        'openRefStep' => (bool) old('transfer_ref') || $errors->has('transfer_ref'),
                    ])
                @endif
            @endif
        </div>

    @elseif($currentKey === 'code' && $settlement && $phase === 'enter_settle_code')
        <form method="POST" action="{{ route('driver.settlements.settle', $settlement) }}">
            @csrf
            <label class="form-label small mb-1">Mã kết chuyến</label>
            <div class="input-group input-group-sm">
                <input type="text" name="settlement_code" class="form-control" required maxlength="20"
                       placeholder="VD: 482931" autocomplete="off">
                <button class="btn btn-primary">Kết chuyến</button>
            </div>
        </form>

    @elseif($currentKey === 'upcoming' && in_array($phase, ['upcoming', 'active'], true))
        <p class="small text-muted mb-0">Chuẩn bị khởi hành theo giờ trên vé.</p>
    @endif
</div>
