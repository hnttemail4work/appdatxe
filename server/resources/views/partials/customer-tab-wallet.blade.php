@php
    use App\Models\CustomerWalletTransaction;
    use App\Models\DriverWalletTransaction;
    use App\Services\CustomerWalletService;

    $wallet = $wallet ?? null;
    $balance = (int) ($wallet?->balance ?? 0);
    $pendingDeposits = collect($pendingDeposits ?? [])->sortByDesc('created_at')->values();
    $atPendingCap = $pendingDeposits->count() >= 1;
    $minDeposit = number_format(CustomerWalletService::MIN_DEPOSIT, 0, ',', '.');

    $walletHistory = collect($walletHistory ?? [])->map(function ($tx) {
        if (is_array($tx)) {
            return $tx;
        }

        /** @var CustomerWalletTransaction $tx */
        return [
            'kind'            => 'deposit',
            'amount'          => (int) $tx->amount,
            'at'              => $tx->created_at,
            'label'           => DriverWalletTransaction::historyLabelFor($tx->status, $tx->transfer_ref),
            'meta'            => null,
            'status'          => $tx->status,
            'proof_image_url' => $tx->proofImageUrl(),
            'reference'       => $tx->depositReference(),
        ];
    });
@endphp

<section class="driver-wallet-page customer-wallet-page" aria-label="Ví khách"
         data-wallet-ptr="{{ route('customer.account', ['tab' => 'wallet']) }}">
    <header class="driver-wallet-hero mb-3">
        <p class="driver-wallet-hero__eyebrow mb-1">Ví khách hàng</p>
        <span class="driver-wallet-hero__label">Số dư khả dụng</span>
        <strong class="driver-wallet-hero__balance">{{ number_format($balance, 0, ',', '.') }} <small>đ</small></strong>
        <p class="driver-wallet-hero__hint mb-0">Nạp tối thiểu {{ $minDeposit }} đ để thanh toán chuyến bằng ví.</p>
    </header>

    @include('partials.driver-wallet-pending-deposits', ['pendingDeposits' => $pendingDeposits])

    <div class="driver-wallet-block mb-3">
        <h2 class="driver-wallet-block__title">Nạp tiền</h2>
        @if($atPendingCap)
            <div class="driver-notice driver-notice-info mb-0" role="status">
                Đang có yêu cầu nạp chờ duyệt
            </div>
        @else
            @include('partials.driver-wallet-deposit-form', [
                'action' => route('customer.wallet.deposit'),
                'qrElementId' => 'customer-wallet-deposit-qr',
                'formId' => 'customer-wallet-deposit-form',
                'minAmount' => CustomerWalletService::MIN_DEPOSIT,
                'hideBankDetails' => true,
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
