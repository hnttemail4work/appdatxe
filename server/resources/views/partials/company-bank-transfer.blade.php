@php
use App\Support\PlatformPaymentInfo;

/** @var int $amount */
/** @var string|null $addInfo */
/** @var string|null $qrElementId */
/** @var bool $dynamicAmount */
/** @var bool $hideBankDetails */
$amount = (int) ($amount ?? 0);
$dynamicAmount = (bool) ($dynamicAmount ?? false);
$hideBankDetails = (bool) ($hideBankDetails ?? false);
$addInfo = $addInfo ?? PlatformPaymentInfo::driverTransferContent(auth()->user()?->phone);
$bank = PlatformPaymentInfo::bank();
$qrUrl = (! $dynamicAmount && $amount > 0)
    ? PlatformPaymentInfo::vietQrImageUrl($amount, $addInfo)
    : null;
$qrElementId = $qrElementId ?? 'company-transfer-qr-' . uniqid();
$qrReady = ! $dynamicAmount && $qrUrl;
@endphp

<div class="company-bank-transfer {{ $hideBankDetails ? 'company-bank-transfer--qr-only' : 'border rounded-3 p-3 bg-white' }}" data-company-transfer>
    @if(PlatformPaymentInfo::isConfigured())
        <div class="d-flex flex-wrap gap-3 align-items-start {{ $hideBankDetails ? 'justify-content-center' : '' }}">
            <div class="text-center flex-shrink-0 company-transfer-qr-col">
                <div class="company-transfer-qr-frame">
                    <img id="{{ $qrElementId }}"
                         src="{{ $qrReady ? $qrUrl : '' }}"
                         alt="QR chuyển khoản"
                         width="140"
                         height="140"
                         class="rounded border company-transfer-qr{{ $qrReady ? '' : ' is-hidden' }}"
                         @unless($qrReady) hidden @endunless
                         loading="lazy"
                         data-bank-bin="{{ $bank['bank_bin'] }}"
                         data-account="{{ $bank['account'] }}"
                         data-add-info="{{ $addInfo }}"
                         data-account-name="{{ $bank['account_name'] }}">
                    <div class="company-transfer-qr-placeholder{{ $qrReady ? ' is-hidden' : '' }}"
                         @if($qrReady) hidden @endif
                         data-deposit-qr-placeholder>
                        <span class="company-transfer-qr-placeholder-icon" aria-hidden="true">QR</span>
                        <span class="company-transfer-qr-placeholder-text">Nhập số tiền để tạo mã QR</span>
                    </div>
                </div>
            </div>
            @unless($hideBankDetails)
            <div class="small flex-grow-1">
                <div><span class="text-muted">Ngân hàng:</span> <strong>{{ $bank['bank_name'] }}</strong></div>
                <div><span class="text-muted">Số TK:</span> <strong><code>{{ $bank['account'] }}</code></strong></div>
                <div><span class="text-muted">Chủ TK:</span> <strong>{{ $bank['account_name'] }}</strong></div>
                @if($addInfo)
                    <div class="mt-1"><span class="text-muted">Nội dung CK:</span> <code>{{ $addInfo }}</code></div>
                @endif
            </div>
            @endunless
        </div>
    @else
        <div class="small text-muted">Liên hệ quản lý lấy thông tin chuyển khoản.</div>
    @endif
</div>
