@php
use App\Support\PlatformPaymentInfo;

/** @var int $amount */
/** @var string|null $addInfo */
/** @var string|null $qrElementId */
$amount = (int) ($amount ?? 0);
$addInfo = $addInfo ?? PlatformPaymentInfo::driverTransferContent(auth()->user()?->phone);
$bank = PlatformPaymentInfo::bank();
$qrUrl = PlatformPaymentInfo::vietQrImageUrl($amount, $addInfo);
$qrElementId = $qrElementId ?? 'company-transfer-qr-' . uniqid();
@endphp

<div class="company-bank-transfer border rounded-3 p-3 bg-white" data-company-transfer>
    @if(PlatformPaymentInfo::isConfigured() && $qrUrl)
        <div class="d-flex flex-wrap gap-3 align-items-start">
            <div class="text-center flex-shrink-0">
                <img id="{{ $qrElementId }}" src="{{ $qrUrl }}" alt="QR chuyển khoản" width="140" height="140"
                     class="rounded border company-transfer-qr" loading="lazy"
                     data-bank-bin="{{ $bank['bank_bin'] }}"
                     data-account="{{ $bank['account'] }}"
                     data-add-info="{{ $addInfo }}"
                     data-account-name="{{ $bank['account_name'] }}">
            </div>
            <div class="small flex-grow-1">
                <div><span class="text-muted">Ngân hàng:</span> <strong>{{ $bank['bank_name'] }}</strong></div>
                <div><span class="text-muted">Số TK:</span> <strong><code>{{ $bank['account'] }}</code></strong></div>
                <div><span class="text-muted">Chủ TK:</span> <strong>{{ $bank['account_name'] }}</strong></div>
                @if($addInfo)
                    <div class="mt-1"><span class="text-muted">Nội dung CK:</span> <code>{{ $addInfo }}</code></div>
                @endif
            </div>
        </div>
    @else
        <div class="small text-muted">Liên hệ quản lý lấy thông tin chuyển khoản.</div>
    @endif
</div>
