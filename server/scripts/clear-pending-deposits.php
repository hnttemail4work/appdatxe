<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\DriverWalletTransaction;

$query = DriverWalletTransaction::query()->where('type', 'deposit');

echo 'Deposit transactions (all statuses): ' . $query->count() . PHP_EOL;

$pending = (clone $query)->where('status', 'pending');
echo 'Pending: ' . $pending->count() . PHP_EOL;

foreach ($query->orderBy('id')->get(['id', 'driver_wallet_id', 'amount', 'status', 'created_at']) as $tx) {
    echo sprintf(
        "  #%d wallet=%d amount=%s status=%s at=%s\n",
        $tx->id,
        $tx->driver_wallet_id,
        number_format((int) $tx->amount, 0, ',', '.'),
        $tx->status,
        $tx->created_at,
    );
}

$deleted = $query->delete();
echo PHP_EOL . 'Deleted all deposit transactions: ' . $deleted . PHP_EOL;
