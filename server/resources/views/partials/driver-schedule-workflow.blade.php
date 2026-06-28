@php
/** @var \App\Models\Schedule $schedule */
$bookings = $schedule->driverRelevantBookings();
$phase = $schedule->driverWorkflowPhase();
$incomplete = $schedule->driverIncompleteBookings();
$settlement = $schedule->tripSettlement;
$totalRevenue = $bookings->sum(fn ($b) => (float) $b->total_price);

$stepIndex = match ($phase) {
    'upcoming', 'active' => $incomplete->isEmpty() ? 1 : 0,
    'needs_settle' => 2,
    'enter_settle_code' => 3,
    'settled' => 4,
    default => 0,
};

$steps = [
    ['label' => 'Sắp chạy'],
    ['label' => 'Hoàn thành'],
    ['label' => 'Chuyển phí'],
    ['label' => 'Kết chuyến'],
    ['label' => 'Xong'],
];
@endphp

<div class="driver-workflow-steps" aria-label="Tiến trình chuyến">
    @foreach($steps as $i => $step)
        @php
            $cls = 'driver-workflow-step';
            if ($i < $stepIndex) {
                $cls .= ' is-done';
            } elseif ($i === $stepIndex) {
                $cls .= ' is-current';
            }
        @endphp
        <div class="{{ $cls }}">{{ $step['label'] }}</div>
    @endforeach
</div>

<div class="driver-action-panel">
    <div class="action-title">{{ $schedule->driverWorkflowLabel() }}</div>

    @if($incomplete->isNotEmpty() && in_array($phase, ['upcoming', 'active'], true))
        <form method="POST" action="{{ route('driver.schedules.complete', $schedule) }}"
              data-confirm="Xác nhận đã chạy xong chuyến này ({{ $bookings->count() }} khách)?"
              data-confirm-title="Hoàn thành chuyến"
              data-confirm-ok="Hoàn thành"
              data-confirm-variant="success">
            @csrf
            <button class="btn btn-success btn-sm w-100">
                Hoàn thành chuyến
                @if($bookings->count() > 1)
                    · {{ $bookings->count() }} vé
                @endif
            </button>
        </form>
        @elseif($settlement && in_array($phase, ['needs_settle', 'enter_settle_code'], true))
        @if($phase === 'needs_settle')
            @php
                $feeQrId = 'platform-fee-qr-' . $settlement->id;
            @endphp
            @include('partials.platform-fee-transfer', [
                'feeAmount' => $settlement->platform_fee_amount,
                'qrElementId' => $feeQrId,
            ])
            <p class="small text-muted mb-0 mt-2">
                Doanh thu: <strong>{{ number_format($totalRevenue, 0, ',', '.') }} đ</strong>
                · Phí: <strong>{{ number_format($settlement->platform_fee_amount, 0, ',', '.') }} đ</strong>
                @if($settlement->category === 'under_threshold')
                    <span class="d-block mt-1">Chuyến doanh thu dưới 500k — chuyển phí chiết khấu rồi xác nhận.</span>
                @endif
            </p>

            @if($settlement->transfer_ref)
                <div class="driver-notice driver-notice-info mt-2 mb-0">
                    Đã xác nhận chuyển phí · Mã CK: <code>{{ $settlement->transfer_ref }}</code>
                    — chờ quản lý cấp mã kết chuyến.
                </div>
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

        @elseif($phase === 'enter_settle_code')
            <form method="POST" action="{{ route('driver.settlements.settle', $settlement) }}">
                @csrf
                <label class="form-label small mb-1">Mã kết chuyến từ quản lý (cả chuyến)</label>
                <div class="input-group input-group-sm">
                    <input type="text" name="settlement_code" class="form-control" required maxlength="20"
                           placeholder="VD: 482931" autocomplete="off">
                    <button class="btn btn-primary">Kết chuyến</button>
                </div>
                @if($settlement->settlement_code_expires_at)
                    <p class="small text-muted mb-0 mt-2">Hết hạn mã: {{ $settlement->settlement_code_expires_at->format('d/m/Y H:i') }}</p>
                @endif
            </form>

        @endif

    @elseif($phase === 'settled')
        <p class="small text-success mb-0 fw-semibold">✓ Chuyến đã kết xong</p>

    @else
        <p class="small text-muted mb-0">Không có thao tác.</p>
    @endif
</div>
