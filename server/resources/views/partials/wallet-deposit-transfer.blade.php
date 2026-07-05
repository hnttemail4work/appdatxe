@include('partials.company-bank-transfer', [
    'amount' => (int) ($amount ?? \App\Support\DriverWalletConfig::MIN_DEPOSIT),
    'addInfo' => $addInfo ?? null,
    'qrElementId' => $qrElementId ?? 'wallet-deposit-qr',
    'dynamicAmount' => (bool) ($dynamicAmount ?? false),
])
