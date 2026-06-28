@include('partials.company-bank-transfer', [
    'amount' => (int) ($amount ?? \App\Support\DriverWalletConfig::MIN_BALANCE),
    'addInfo' => $addInfo ?? null,
    'qrElementId' => $qrElementId ?? 'wallet-deposit-qr',
])
