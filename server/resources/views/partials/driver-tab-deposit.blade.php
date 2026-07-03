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

<div class="driver-wallet-overview mb-3">
    <div class="driver-wallet-balance-card">
        <span class="driver-stat-tile-label">Số dư ví</span>
        <span class="driver-wallet-balance-value">{{ number_format($wallet->balance, 0, ',', '.') }} đ</span>
        @if(! $activated)
            <p class="driver-wallet-balance-hint">Chưa kích hoạt — nạp tối thiểu {{ DriverWalletConfig::minDepositFormatted() }}</p>
        @endif
    </div>
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
