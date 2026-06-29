@php
use App\Support\DriverWalletConfig;

/** @var \App\Models\DriverWallet $wallet */
/** @var \Illuminate\Support\Collection $walletHistory */

$pendingDeposits = $wallet->transactions->where('status', 'pending');
$walletHistory = $walletHistory ?? collect();
$revenueStats = $revenueStats ?? ['day' => 0, 'week' => 0];
$walletQrId = 'wallet-deposit-qr';
$needsMinBalance = $wallet->wallet_gate_enabled;
@endphp

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
    Đã gửi yêu cầu nạp tiền — chờ quản lý duyệt ({{ $pendingDeposits->count() }}).
</div>
@else
    @if($needsMinBalance)
    <p class="small text-muted mb-3">Sau chuyến ≥ {{ DriverWalletConfig::revenueThresholdShortLabel() }} — giữ số dư trên {{ number_format(DriverWalletConfig::MIN_BALANCE, 0, ',', '.') }} đ để nhận cuốc mới.</p>
    @else
    <p class="small text-muted mb-3">Nạp ví bất cứ lúc nào — quản lý duyệt sau khi bạn chuyển khoản.</p>
    @endif

    @include('partials.wallet-deposit-transfer', [
        'amount' => (int) old('amount', DriverWalletConfig::MIN_BALANCE),
        'qrElementId' => $walletQrId,
    ])

    @include('partials.driver-wallet-deposit-form', [
        'qrElementId' => $walletQrId,
    ])
@endif

@include('partials.driver-wallet-history', ['walletHistory' => $walletHistory])
