@php
    use App\Services\CustomerWalletService;
    use App\Support\PlatformPaymentInfo;

    $wallet = $wallet ?? null;
    $balance = (int) ($wallet?->balance ?? 0);
    $pendingDeposits = $pendingDeposits ?? collect();
    $history = $walletHistory ?? collect();
    $minAmount = CustomerWalletService::MIN_DEPOSIT;
    $amount = (int) old('amount', $minAmount);
@endphp
<section class="customer-account-panel is-active" aria-label="Ví">
    <div class="customer-account-subhead mb-3">
        <a href="{{ route('customer.account', ['tab' => 'account']) }}" class="customer-account-back" aria-label="Quay lại">←</a>
        <h2 class="customer-account-panel__title mb-0">Ví</h2>
    </div>

    <div class="customer-account-card mb-3">
        <div class="customer-wallet-balance">
            <span class="customer-wallet-balance__label">Số dư</span>
            <strong class="customer-wallet-balance__value">{{ number_format($balance, 0, ',', '.') }} đ</strong>
        </div>
    </div>

    <div class="customer-account-card mb-3">
        <h3 class="customer-account-card__title">Nạp tiền</h3>
        <p class="small text-muted mb-3">Chuyển khoản theo QR / thông tin bên dưới, gửi ảnh để admin duyệt.</p>

        @if($errors->has('wallet') || $errors->has('amount') || $errors->has('proof_image'))
            <div class="alert alert-danger py-2 small" role="alert">
                @foreach(['wallet', 'amount', 'proof_image'] as $field)
                    @foreach($errors->get($field) as $message)
                        <div>{{ $message }}</div>
                    @endforeach
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('customer.wallet.deposit') }}" enctype="multipart/form-data"
              class="driver-wallet-deposit-form"
              id="customer-wallet-deposit-form"
              data-deposit-min="{{ $minAmount }}" data-deposit-qr="#customer-wallet-deposit-qr">
            @csrf
            <label class="form-label" for="customer-deposit-amount">Số tiền nạp</label>
            <input type="text" name="amount" id="customer-deposit-amount"
                   class="form-control driver-deposit-amount mb-2"
                   inputmode="numeric" autocomplete="off"
                   value="{{ old('amount') !== null ? $amount : '' }}"
                   placeholder="Tối thiểu {{ number_format($minAmount, 0, ',', '.') }} đ">
            <div class="small text-danger mb-2 d-none" id="driver-deposit-amount-error" role="alert"></div>

            <div class="mb-3">
                @include('partials.company-bank-transfer', [
                    'amount' => $amount >= $minAmount ? $amount : 0,
                    'qrElementId' => 'customer-wallet-deposit-qr',
                    'dynamicAmount' => true,
                    'addInfo' => PlatformPaymentInfo::driverTransferContent(auth()->user()?->phone),
                    'hideBankDetails' => false,
                ])
            </div>

            <label class="form-label" for="customer-deposit-proof">Ảnh chụp chuyển khoản</label>
            <input type="file" name="proof_image" id="customer-deposit-proof"
                   class="form-control form-control-sm driver-deposit-proof mb-3"
                   accept="image/jpeg,image/png,image/webp,image/gif" capture="environment">

            <button type="submit" class="btn btn-primary w-100 driver-deposit-submit-btn">Gửi yêu cầu nạp</button>
        </form>
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
