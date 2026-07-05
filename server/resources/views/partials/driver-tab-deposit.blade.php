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

@if(session('success'))
<div class="driver-notice driver-notice-success mb-3" role="status">
    {{ session('success') }}
</div>
@endif

@include('partials.driver-wallet-pending-deposits', ['pendingDeposits' => $pendingDeposits])

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
