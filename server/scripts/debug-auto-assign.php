<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Booking;
use App\Models\DriverProfile;
use App\Services\DriverProximityService;
use App\Services\DriverTripRequestService;
use App\Services\DriverWalletService;

$wallet = app(DriverWalletService::class);
$prox = app(DriverProximityService::class);

echo "=== DRIVERS ===\n";
foreach (DriverProfile::with('user')->get() as $p) {
    $block = $wallet->acceptBlockReason($p);
    echo sprintf(
        "%s | avail=%s | approved=%s | wallet_act=%s | operational=%s | fresh_loc=%s | block=%s\n",
        $p->user?->name ?? '?',
        $p->availability_status ?? 'null',
        $p->isApproved() ? 'Y' : 'N',
        $p->isWalletActivated() ? 'Y' : 'N',
        $p->isOperational() ? 'Y' : 'N',
        $p->hasFreshLocation() ? 'Y' : 'N',
        $block ?? 'none',
    );
}

$booking = Booking::query()
    ->whereHas('schedule', fn ($q) => $q->whereNull('driver_id'))
    ->whereNotIn('booking_status', ['cancelled', 'rejected'])
    ->latest()
    ->first();

if (! $booking) {
    echo "\nNo unassigned booking found.\n";
    exit(0);
}

$booking->loadMissing(['schedule.route', 'schedule.vehicle']);
echo "\n=== BOOKING #{$booking->id} ===\n";
echo "pickup: {$booking->pickup_lat}, {$booking->pickup_lng}\n";
echo "schedule driver: " . ($booking->schedule?->driver_id ?? 'null') . "\n";
echo "needs_operator_help: " . ($booking->needs_operator_help_at ?? 'null') . "\n";
echo "reason: " . ($booking->operator_help_reason ?? 'null') . "\n";

$pick = $prox->pickBest($booking->schedule, $booking, collect(), true);
echo "pickBest: " . ($pick?->user?->name ?? 'NULL') . "\n";

if ($pick) {
    $diag = $prox->assignDiagnostics($pick, $booking, $booking->schedule);
    echo "diagnostics: " . json_encode($diag, JSON_UNESCAPED_UNICODE) . "\n";
}

$result = app(DriverTripRequestService::class)->autoAssignForBooking($booking->fresh(['schedule.route', 'schedule.vehicle']));
echo "autoAssign result: " . ($result ? 'OK driver ' . $result->driver_id : 'NULL') . "\n";
$booking->refresh();
$booking->schedule?->refresh();
echo "after assign schedule driver: " . ($booking->schedule?->driver_id ?? 'null') . "\n";
