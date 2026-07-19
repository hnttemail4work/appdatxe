@php
use App\Support\DriverWalletConfig;

/** @var string $action */
/** @var string $qrElementId */
/** @var int|null $minAmount */
/** @var bool $hideBankDetails */

$action = $action ?? route('driver.wallet.deposit');
$qrElementId = $qrElementId ?? 'wallet-deposit-qr';
$minAmount = (int) ($minAmount ?? DriverWalletConfig::MIN_DEPOSIT);
$hideBankDetails = $hideBankDetails ?? true;
$amount = (int) old('amount', $minAmount);
$quickAmounts = [100_000, 200_000, 500_000, 1_000_000];
$formId = $formId ?? 'wallet-deposit-form';
@endphp

<form method="POST" action="{{ $action }}" class="driver-wallet-deposit-form" id="{{ $formId }}"
      enctype="multipart/form-data"
      data-deposit-min="{{ $minAmount }}"
      data-deposit-qr="#{{ $qrElementId }}"
      novalidate>
    @csrf

    <div class="driver-deposit-panel">
        @if($errors->has('wallet') || $errors->has('amount') || $errors->has('proof_image'))
        <div class="alert alert-danger py-2 small mb-0" role="alert">
            @foreach(['wallet', 'amount', 'proof_image'] as $field)
                @foreach($errors->get($field) as $message)
                    <div>{{ $message }}</div>
                @endforeach
            @endforeach
        </div>
        @endif

        <div class="driver-deposit-amount-section">
            <label class="driver-deposit-field-label" for="{{ $formId }}-amount">Số tiền nạp</label>
            <div class="driver-deposit-amount-row">
                <input type="text" name="amount" id="{{ $formId }}-amount"
                       class="form-control driver-deposit-amount"
                       inputmode="numeric" pattern="[0-9]*" autocomplete="off"
                       value="{{ old('amount') !== null ? $amount : '' }}"
                       placeholder="Tối thiểu {{ number_format($minAmount, 0, ',', '.') }} đ"
                       aria-label="Số tiền nạp">
                <span class="driver-deposit-amount-suffix">đ</span>
            </div>
            <div class="driver-deposit-amount-error small text-danger mb-2 d-none" data-deposit-amount-error role="alert"></div>
            <div class="driver-deposit-quick-amounts" role="group" aria-label="Chọn nhanh số tiền">
                @foreach($quickAmounts as $preset)
                    <button type="button"
                            class="driver-deposit-preset"
                            data-amount="{{ $preset }}">
                        {{ number_format($preset / 1000, 0, ',', '.') }}k
                    </button>
                @endforeach
            </div>
        </div>

        <div class="driver-deposit-qr-section">
            <p class="driver-deposit-qr-heading">Quét QR để chuyển khoản</p>
            <p class="driver-deposit-qr-hint">QR đã gồm ngân hàng, số TK và nội dung chuyển khoản. Chụp màn hình rồi gửi yêu cầu.</p>
            <p class="driver-deposit-qr-amount" data-deposit-amount-label hidden></p>
            @include('partials.wallet-deposit-transfer', [
                'amount' => $amount >= $minAmount ? $amount : 0,
                'qrElementId' => $qrElementId,
                'dynamicAmount' => true,
                'hideBankDetails' => $hideBankDetails,
            ])
        </div>

        <div class="driver-deposit-proof-section">
            <label class="driver-deposit-proof-label" for="{{ $formId }}-proof">Ảnh chụp chuyển khoản <span class="text-danger">*</span></label>
            <input type="file"
                   name="proof_image"
                   id="{{ $formId }}-proof"
                   class="form-control form-control-sm driver-deposit-proof"
                   accept="image/jpeg,image/png,image/webp,image/gif"
                   capture="environment"
                   required>
            <div class="driver-deposit-proof-preview d-none" data-deposit-proof-preview hidden>
                <img src="" alt="Xem trước ảnh chuyển khoản" class="driver-deposit-proof-preview-img" data-deposit-proof-preview-img>
            </div>
        </div>

        <button type="submit" class="btn btn-warning fw-semibold driver-deposit-submit-btn w-100">
            Gửi yêu cầu nạp
        </button>
    </div>
</form>
