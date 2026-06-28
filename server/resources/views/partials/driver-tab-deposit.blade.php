@php
use App\Support\DriverWalletConfig;

/** @var \App\Models\DriverWallet|null $wallet */

$pendingDeposits = $wallet ? $wallet->transactions->where('status', 'pending') : collect();
$revenueStats = $revenueStats ?? ['day' => 0, 'week' => 0];
$walletQrId = 'wallet-deposit-qr';
@endphp

@if(! $wallet)
    <div class="driver-empty">Chưa có thông tin ví.</div>
@else
    <div class="row g-3 mb-3">
        <div class="col-6">
            <div class="driver-sidebar-card h-100">
                <div class="small text-muted">Doanh thu ngày</div>
                <div class="fs-5 fw-bold">{{ number_format($revenueStats['day'] ?? 0, 0, ',', '.') }} đ</div>
            </div>
        </div>
        <div class="col-6">
            <div class="driver-sidebar-card h-100">
                <div class="small text-muted">Doanh thu tuần</div>
                <div class="fs-5 fw-bold">{{ number_format($revenueStats['week'] ?? 0, 0, ',', '.') }} đ</div>
            </div>
        </div>
    </div>

    <div class="driver-sidebar-card mb-3">
        <div class="small text-muted">Số dư ví</div>
        <div class="fs-3 fw-bold text-primary">{{ number_format($wallet->balance, 0, ',', '.') }} đ</div>
    </div>

    @if($pendingDeposits->isNotEmpty())
    <div class="driver-notice driver-notice-info mb-3">
        Đang chờ quản lý duyệt {{ $pendingDeposits->count() }} yêu cầu nạp tiền.
    </div>
    @elseif($wallet->wallet_gate_enabled)
    <p class="small text-muted mb-3">Luôn giữ số dư ví trên 100 nghìn để nhận cuốc bạn nhé</p>

    @include('partials.wallet-deposit-transfer', [
        'amount' => (int) old('amount', DriverWalletConfig::MIN_BALANCE),
        'qrElementId' => $walletQrId,
    ])

    @include('partials.transfer-confirm-form', [
        'action' => route('driver.wallet.deposit'),
        'amount' => (int) old('amount', DriverWalletConfig::MIN_BALANCE),
        'amountEditable' => true,
        'startLabel' => 'Nạp tiền',
        'confirmLabel' => 'Xác nhận',
        'formId' => 'wallet-deposit-form',
        'qrElementId' => $walletQrId,
        'minAmount' => DriverWalletConfig::MIN_BALANCE,
        'openRefStep' => (bool) old('transfer_ref') || $errors->has('transfer_ref'),
    ])
    @endif
@endif
