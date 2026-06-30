@php
use App\Support\DriverWalletConfig;

/** @var string $action */
/** @var string $qrElementId */

$action = $action ?? route('driver.wallet.deposit');
$qrElementId = $qrElementId ?? 'wallet-deposit-qr';
$minAmount = DriverWalletConfig::MIN_DEPOSIT;
$amount = (int) old('amount', $minAmount);
@endphp

<form method="POST" action="{{ $action }}" class="driver-wallet-deposit-form mt-3" id="wallet-deposit-form"
      data-deposit-min="{{ $minAmount }}"
      data-deposit-qr="#{{ $qrElementId }}">
    @csrf

    @if($errors->has('wallet') || $errors->has('amount'))
    <div class="alert alert-danger py-2 small mb-3" role="alert">
        @foreach(['wallet', 'amount'] as $field)
            @foreach($errors->get($field) as $message)
                <div>{{ $message }}</div>
            @endforeach
        @endforeach
    </div>
    @endif

    <div class="mb-3">
        <label class="form-label small">Số tiền nạp (đ)</label>
        <input type="number" name="amount" class="form-control form-control-sm driver-deposit-amount"
               min="{{ $minAmount }}" step="1000" required
               value="{{ $amount }}">
        <div class="form-text">Chuyển khoản theo QR phía trên, rồi bấm Nạp tiền — quản lý sẽ cộng vào ví.</div>
    </div>

    <button type="submit" class="btn btn-primary btn-sm driver-deposit-submit-btn">Nạp tiền</button>
</form>
