@php
/** @var string $action */
/** @var int $amount */
/** @var bool $amountEditable */
/** @var string $startLabel */
/** @var string $confirmLabel */
/** @var string|null $formId */
/** @var string|null $qrElementId */
/** @var int|null $minAmount */

$action = $action ?? '#';
$amount = (int) ($amount ?? 0);
$amountEditable = (bool) ($amountEditable ?? false);
$startLabel = $startLabel ?? 'Nạp tiền';
$confirmLabel = $confirmLabel ?? 'Xác nhận';
$formId = $formId ?? 'transfer-form-' . uniqid();
$minAmount = (int) ($minAmount ?? 0);
$openRefStep = (bool) ($openRefStep ?? false)
    || old('transfer_ref')
    || $errors->has('transfer_ref');
@endphp

<form method="POST" action="{{ $action }}" class="transfer-confirm-form mt-3" id="{{ $formId }}"
      data-transfer-open="{{ $openRefStep ? '1' : '0' }}"
      @if($minAmount > 0) data-transfer-min="{{ $minAmount }}" @endif
      @if($qrElementId) data-transfer-qr="#{{ $qrElementId }}" @endif>
    @csrf
    @if($amountEditable)
        <div class="mb-2" data-transfer-start-step>
            <label class="form-label small">Số tiền</label>
            <input type="number" name="amount" class="form-control form-control-sm transfer-amount-input"
                   data-transfer-amount min="{{ $minAmount ?: 1 }}" step="1000" required
                   value="{{ old('amount', $amount) }}">
        </div>
    @else
        <input type="hidden" name="amount" value="{{ $amount }}" data-transfer-amount>
    @endif

    <div class="mb-2 {{ $openRefStep ? '' : 'd-none' }}" data-transfer-ref-step>
        <label class="form-label small">Mã nạp tiền</label>
        <input type="text" name="transfer_ref" class="form-control form-control-sm" maxlength="100" required
               value="{{ old('transfer_ref') }}"
               @if(! $openRefStep) disabled @endif>
    </div>

    <div class="d-flex gap-2 flex-wrap">
        <button type="button" class="btn btn-success btn-sm {{ $openRefStep ? 'd-none' : '' }}" data-transfer-start>
            {{ $startLabel }}
        </button>
        <button type="submit" class="btn btn-primary btn-sm {{ $openRefStep ? '' : 'd-none' }}" data-transfer-confirm>
            {{ $confirmLabel }}
        </button>
    </div>
</form>
