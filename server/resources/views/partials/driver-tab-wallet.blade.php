@php
use App\Support\DriverWalletConfig;

/** @var \App\Models\DriverWallet $wallet */
/** @var \Illuminate\Support\Collection $walletHistory */

$pendingDeposits = $wallet->transactions
    ->where('type', 'deposit')
    ->where('status', 'pending');

$walletHistory = $walletHistory ?? collect();
$walletQrId = 'wallet-deposit-qr';
$atPendingCap = $pendingDeposits->count() >= DriverWalletConfig::MAX_PENDING_DEPOSITS;
$minBalance = DriverWalletConfig::minBalanceFormatted();
@endphp

<section class="driver-wallet-page" aria-label="Ví tài xế">
    <header class="driver-wallet-hero mb-3">
        <p class="driver-wallet-hero__eyebrow mb-1">Ví tài xế</p>
        <span class="driver-wallet-hero__label">Số dư khả dụng</span>
        <strong class="driver-wallet-hero__balance">{{ number_format($wallet->balance, 0, ',', '.') }} <small>đ</small></strong>
        <p class="driver-wallet-hero__hint mb-0">Giữ số dư từ {{ $minBalance }} để tiếp tục nhận cuốc.</p>
    </header>

    @include('partials.driver-wallet-pending-deposits', ['pendingDeposits' => $pendingDeposits])

    <div class="driver-wallet-block mb-3">
        <h2 class="driver-wallet-block__title">Nạp tiền</h2>
        @if($atPendingCap)
            <div class="driver-notice driver-notice-info mb-0" role="status">
                Đang có yêu cầu nạp chờ duyệt — chờ quản lý xác nhận trước khi gửi thêm.
            </div>
        @else
            @include('partials.driver-wallet-deposit-form', [
                'qrElementId' => $walletQrId,
            ])
        @endif
    </div>

    <div class="driver-wallet-block">
        @include('partials.driver-wallet-history', [
            'walletHistory' => $walletHistory,
            'historyTitle' => 'Lịch sử nạp tiền',
            'historyEmpty' => 'Chưa có lịch sử nạp tiền.',
        ])
    </div>
</section>
