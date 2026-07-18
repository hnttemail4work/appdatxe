@php

use App\Support\DriverWalletConfig;

/** @var \App\Models\DriverWallet $wallet */
/** @var \Illuminate\Support\Collection $walletHistory */

$pendingDeposits = $wallet->transactions
    ->where('type', 'deposit')
    ->where('status', 'pending');

$walletHistory = $walletHistory ?? collect();
$revenueStats = $revenueStats ?? ['day' => 0, 'week' => 0];
$walletQrId = 'wallet-deposit-qr';
$atPendingCap = $pendingDeposits->count() >= DriverWalletConfig::MAX_PENDING_DEPOSITS;

@endphp

@include('partials.driver-wallet-pending-deposits', ['pendingDeposits' => $pendingDeposits])

<div class="driver-wallet-stats mb-3" aria-label="Thu nhập và ví">
    <div class="driver-wallet-stats__item">
        <span class="driver-wallet-stats__label">Hôm nay</span>
        <strong class="driver-wallet-stats__value">{{ number_format($revenueStats['day'] ?? 0, 0, ',', '.') }} đ</strong>
    </div>
    <div class="driver-wallet-stats__item">
        <span class="driver-wallet-stats__label">Tuần này</span>
        <strong class="driver-wallet-stats__value">{{ number_format($revenueStats['week'] ?? 0, 0, ',', '.') }} đ</strong>
    </div>
    <div class="driver-wallet-stats__item driver-wallet-stats__item--balance">
        <span class="driver-wallet-stats__label">Số dư ví</span>
        <strong class="driver-wallet-stats__value">{{ number_format($wallet->balance, 0, ',', '.') }} đ</strong>
    </div>
</div>

@if($atPendingCap)
<div class="driver-notice driver-notice-info mb-3" role="status">
    Đang có yêu cầu nạp chờ duyệt — chờ quản lý xác nhận trước khi gửi thêm.
</div>
@else
    @include('partials.driver-wallet-deposit-form', [
        'qrElementId' => $walletQrId,
    ])
@endif

@include('partials.driver-wallet-history', [
    'walletHistory' => $walletHistory,
    'historyTitle' => 'Lịch sử nạp tiền',
    'historyEmpty' => 'Chưa có lịch sử nạp tiền.',
])
