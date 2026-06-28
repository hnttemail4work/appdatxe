@include('partials.company-bank-transfer', [
    'amount' => (int) ($feeAmount ?? 0),
    'addInfo' => $addInfo ?? null,
    'qrElementId' => $qrElementId ?? 'platform-fee-qr-' . uniqid(),
])
