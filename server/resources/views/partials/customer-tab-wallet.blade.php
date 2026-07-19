@php
    use App\Services\CustomerWalletService;

    $wallet = $wallet ?? null;
    $balance = (int) ($wallet?->balance ?? 0);
    $pendingDeposits = $pendingDeposits ?? collect();
    $history = $walletHistory ?? collect();
@endphp
<section class="customer-account-panel is-active" aria-label="Ví">
    <div class="customer-account-card mb-3">
        <div class="customer-wallet-balance">
            <span class="customer-wallet-balance__label">Số dư</span>
            <strong class="customer-wallet-balance__value">{{ number_format($balance, 0, ',', '.') }} đ</strong>
        </div>
    </div>

    <div class="customer-account-card mb-3">
        <h3 class="customer-account-card__title">Nạp tiền</h3>
        @include('partials.driver-wallet-deposit-form', [
            'action' => route('customer.wallet.deposit'),
            'qrElementId' => 'customer-wallet-deposit-qr',
            'formId' => 'customer-wallet-deposit-form',
            'minAmount' => CustomerWalletService::MIN_DEPOSIT,
            'hideBankDetails' => true,
        ])
    </div>

    @if($pendingDeposits->isNotEmpty())
        <div class="customer-account-card mb-3">
            <h3 class="customer-account-card__title">Đang chờ duyệt</h3>
            <ul class="list-unstyled mb-0">
                @foreach($pendingDeposits as $tx)
                    <li class="d-flex justify-content-between py-2 border-bottom border-secondary-subtle">
                        <span>{{ number_format($tx->amount, 0, ',', '.') }} đ · {{ $tx->depositReference() }}</span>
                        <span class="small text-warning">{{ $tx->statusLabel() }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="customer-account-card">
        <h3 class="customer-account-card__title">Lịch sử nạp</h3>
        @if($history->isEmpty())
            <p class="small text-muted mb-0">Chưa có giao dịch.</p>
        @else
            <ul class="list-unstyled mb-0">
                @foreach($history as $tx)
                    <li class="d-flex justify-content-between py-2 border-bottom border-secondary-subtle">
                        <span>
                            {{ number_format($tx->amount, 0, ',', '.') }} đ
                            <span class="small text-muted">· {{ $tx->created_at?->format('d/m/Y H:i') }}</span>
                        </span>
                        <span class="small">{{ $tx->statusLabel() }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</section>
