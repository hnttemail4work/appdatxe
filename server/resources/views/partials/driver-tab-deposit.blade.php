@php
use App\Support\DriverWalletConfig;

/** @var \App\Models\DriverWallet $wallet */
/** @var \Illuminate\Support\Collection $walletHistory */

$pendingDeposits = $wallet->transactions->where('status', 'pending');
$walletHistory = $walletHistory ?? collect();
$revenueStats = $revenueStats ?? ['day' => 0, 'week' => 0];
$walletQrId = 'wallet-deposit-qr';
$needsMinBalance = $wallet->wallet_gate_enabled;
$activated = (bool) $wallet->wallet_activated_at;
@endphp

<div class="row g-3 mb-3">
    <div class="col-6">
        <div class="driver-sidebar-card h-100">
            <div class="small text-muted">Doanh thu ngày (hoàn thành)</div>
            <div class="fs-5 fw-bold">{{ number_format($revenueStats['day'] ?? 0, 0, ',', '.') }} đ</div>
        </div>
    </div>
    <div class="col-6">
        <div class="driver-sidebar-card h-100">
            <div class="small text-muted">Doanh thu tuần (hoàn thành)</div>
            <div class="fs-5 fw-bold">{{ number_format($revenueStats['week'] ?? 0, 0, ',', '.') }} đ</div>
        </div>
    </div>
</div>

<div class="driver-sidebar-card mb-3">
    <div class="small text-muted">Số dư ví</div>
    <div class="fs-3 fw-bold text-primary">{{ number_format($wallet->balance, 0, ',', '.') }} đ</div>
    @if(! $activated)
        <div class="small text-warning mt-1">Chưa kích hoạt — nạp tối thiểu {{ DriverWalletConfig::minDepositFormatted() }}</div>
    @endif
</div>

@if($pendingDeposits->isNotEmpty())
<div class="driver-notice driver-notice-info mb-3">
    Đã gửi yêu cầu nạp tiền — chờ quản lý duyệt ({{ $pendingDeposits->count() }}).
</div>
@else
    @if($needsMinBalance)
    <p class="small text-muted mb-3">Sau chuyến ≥ {{ DriverWalletConfig::revenueThresholdShortLabel() }} — giữ số dư trên {{ DriverWalletConfig::minBalanceFormatted() }} để nhận cuốc mới.</p>
    @elseif(! $activated)
    <p class="small text-muted mb-3">Nạp tối thiểu {{ DriverWalletConfig::minDepositFormatted() }} để kích hoạt tài khoản và nhận cuốc.</p>
    @else
    <p class="small text-muted mb-3">Nạp ví bất cứ lúc nào — quản lý duyệt sau khi bạn chuyển khoản.</p>
    @endif

    @include('partials.wallet-deposit-transfer', [
        'amount' => (int) old('amount', DriverWalletConfig::MIN_DEPOSIT),
        'qrElementId' => $walletQrId,
    ])

    @include('partials.driver-wallet-deposit-form', [
        'qrElementId' => $walletQrId,
    ])
@endif

@include('partials.driver-wallet-history', ['walletHistory' => $walletHistory])
