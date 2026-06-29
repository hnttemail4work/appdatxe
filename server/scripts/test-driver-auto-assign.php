<?php

/**
 * Script kiểm tra auto-assign trên MySQL (rollback transaction — không lưu dữ liệu).
 * Chạy: php scripts/test-driver-auto-assign.php
 */

$base = dirname(__DIR__);
$envFile = $base . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\"'");
        if (str_starts_with($key, 'DB_')) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}
putenv('DB_CONNECTION=mysql');
$_ENV['DB_CONNECTION'] = 'mysql';
$_SERVER['DB_CONNECTION'] = 'mysql';
unset($_ENV['DB_DATABASE'], $_SERVER['DB_DATABASE']);

require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$app['config']->set('database.default', 'mysql');
$dbName = $_ENV['DB_DATABASE'] ?? env('DB_DATABASE', 'appdatxe');
$app['config']->set('database.connections.mysql.database', $dbName);
Illuminate\Support\Facades\DB::purge('mysql');
Illuminate\Support\Facades\DB::reconnect('mysql');

use App\Models\Booking;
use App\Models\DriverProfile;
use App\Models\DriverTripRequest;
use App\Models\DriverWallet;
use App\Models\Schedule;
use App\Models\TripRoute;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\DriverProximityService;
use App\Services\DriverTripRequestService;
use App\Support\DriverWalletConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$failures = 0;

function assertTrue(bool $cond, string $label): void
{
    global $failures;
    if ($cond) {
        echo "  OK  {$label}\n";
    } else {
        echo "  FAIL {$label}\n";
        $failures++;
    }
}

echo "=== Driver auto-assign integration test ===\n";
echo 'DB: ' . config('database.default') . ' / ' . config('database.connections.mysql.database') . "\n";

$missing = array_filter([
    'driver_trip_requests' => ! Schema::hasTable('driver_trip_requests'),
    'driver_wallets' => ! Schema::hasTable('driver_wallets'),
], fn (bool $v): bool => $v);

if ($missing !== []) {
    echo 'SKIP: thiếu bảng ' . implode(', ', array_keys($missing)) . "\n";
    exit(1);
}

DB::beginTransaction();

try {
    $operator = User::factory()->create(['role' => 'operator', 'email' => 'auto-op-' . uniqid() . '@t.local']);
    $driverNearUser = User::factory()->create(['role' => 'driver', 'name' => 'TX Near', 'email' => 'near-' . uniqid() . '@t.local']);
    $driverFarUser = User::factory()->create(['role' => 'driver', 'name' => 'TX Far', 'email' => 'far-' . uniqid() . '@t.local']);
    $driverBlockUser = User::factory()->create(['role' => 'driver', 'name' => 'TX Block', 'email' => 'blk-' . uniqid() . '@t.local']);

    $mkProfile = function (User $u, string $code, array $gps) use ($operator): DriverProfile {
        $data = [
            'user_id' => $u->id,
            'operator_id' => $operator->id,
            'driver_code' => $code,
            'license_number' => 'L' . random_int(10000, 99999),
            'license_class' => 'B2',
            'license_expiry' => now()->addYears(2)->toDateString(),
            'status' => 'active',
            'approval_status' => 'approved',
            'availability_status' => 'available',
        ];
        if (Schema::hasColumn('driver_profiles', 'last_lat')) {
            $data['last_lat'] = $gps['lat'];
            $data['last_lng'] = $gps['lng'];
            $data['last_location_at'] = now();
        }
        $p = DriverProfile::query()->create($data);
        if (Schema::hasColumn('driver_profiles', 'preference_likes')) {
            $p->update(['preference_likes' => $gps['likes'] ?? 0, 'preference_dislikes' => $gps['dislikes'] ?? 0]);
        }

        return $p;
    };

    $profileNear = $mkProfile($driverNearUser, 'TNE' . random_int(100, 999), ['lat' => 10.7769, 'lng' => 106.7009, 'likes' => 5]);
    $profileFar = $mkProfile($driverFarUser, 'TFA' . random_int(100, 999), ['lat' => 11.3254, 'lng' => 106.477, 'likes' => 10]);
    $profileBlock = $mkProfile($driverBlockUser, 'TBL' . random_int(100, 999), ['lat' => 10.777, 'lng' => 106.701, 'likes' => 99]);

    DriverWallet::query()->updateOrCreate(
        ['driver_profile_id' => $profileNear->id],
        ['balance' => 200_000, 'wallet_gate_enabled' => false],
    );
    DriverWallet::query()->updateOrCreate(
        ['driver_profile_id' => $profileFar->id],
        ['balance' => 200_000, 'wallet_gate_enabled' => false],
    );
    DriverWallet::query()->updateOrCreate(
        ['driver_profile_id' => $profileBlock->id],
        ['balance' => 0, 'wallet_gate_enabled' => true],
    );

    $route = TripRoute::query()->firstOrCreate(
        ['departure' => 'TP.HCM', 'destination' => 'Vũng Tàu'],
        ['base_price' => 200000, 'distance_km' => 95, 'is_active' => true],
    );
    $vehicle = Vehicle::query()->create([
        'operator_id' => $operator->id, 'license_plate' => '51T-' . random_int(10000, 99999),
        'type' => 'sedan', 'capacity' => 4, 'status' => 'active',
    ]);
    $dep = now()->addHours(4);
    $schedule = Schedule::query()->create([
        'route_id' => $route->id, 'vehicle_id' => $vehicle->id, 'driver_id' => null, 'driver_name' => '',
        'departure_time' => $dep, 'service_date' => $dep->toDateString(),
        'available_seats' => 4, 'status' => 'scheduled',
    ]);

    $bookingData = [
        'contact_phone' => '0918' . random_int(100000, 999999),
        'passenger_name' => 'Test Auto',
        'schedule_id' => $schedule->id,
        'seat_numbers' => ['1'],
        'trip_type' => 'one_way',
        'booking_mode' => 'shared',
        'booking_reference' => 'BK-SCR-' . uniqid(),
        'total_price' => 200000,
        'payment_status' => 'unpaid',
        'trip_status' => 'pending',
        'booking_status' => 'pending',
        'pickup_address' => 'TP.HCM',
        'dropoff_address' => 'Vũng Tàu',
    ];
    if (Schema::hasColumn('bookings', 'pickup_lat')) {
        $bookingData['pickup_lat'] = 10.7769;
        $bookingData['pickup_lng'] = 106.7009;
    }
    $booking = Booking::query()->create($bookingData);

    $svc = app(DriverTripRequestService::class);
    $prox = app(DriverProximityService::class);

    echo "\n1) autoAssignForBooking chọn tài xế gần\n";
    $req = $svc->autoAssignForBooking($booking->fresh(['schedule.route', 'schedule.vehicle']));
    assertTrue($req !== null, 'tạo được request pending');
    assertTrue($req->status === 'pending', 'status pending');
    assertTrue((int) $req->driver_id === (int) $driverNearUser->id, 'chọn TX gần (không phải TX bị block)');
    assertTrue($req->expires_at !== null, 'có expires_at');
    assertTrue(
        $req->expires_at->between(now()->addMinutes(14), now()->addMinutes(16)),
        'expires_at ~15 phút',
    );

    echo "\n2) không tạo trùng pending\n";
    $dup = $svc->autoAssignForBooking($booking->fresh(['schedule.route', 'schedule.vehicle']));
    assertTrue($dup === null, 'lần 2 trả null');

    echo "\n3) expireStale → gán TX khác\n";
    $req->update(['expires_at' => now()->subMinute()]);
    $svc->expireStale();
    $req->refresh();
    assertTrue($req->status === 'expired', 'request cũ expired');
    $next = DriverTripRequest::query()
        ->where('schedule_id', $schedule->id)
        ->where('status', 'pending')
        ->latest()
        ->first();
    assertTrue($next !== null, 'có request pending mới');
    assertTrue((int) $next->driver_id === (int) $driverFarUser->id, 'reassign sang TX xa');

    echo "\n4) reject → reassign (scenario riêng)\n";
    $dep2 = now()->addHours(5);
    $schedule2 = Schedule::query()->create([
        'route_id' => $route->id, 'vehicle_id' => $vehicle->id, 'driver_id' => null, 'driver_name' => '',
        'departure_time' => $dep2, 'service_date' => $dep2->toDateString(),
        'available_seats' => 4, 'status' => 'scheduled',
    ]);
    $booking2 = Booking::query()->create(array_merge($bookingData, [
        'schedule_id' => $schedule2->id,
        'contact_phone' => '0917' . random_int(100000, 999999),
        'booking_reference' => 'BK-SCR2-' . uniqid(),
    ]));
    $reqNear = DriverTripRequest::query()->create([
        'schedule_id' => $schedule2->id,
        'contact_phone' => $booking2->contact_phone,
        'driver_id' => $driverNearUser->id,
        'status' => 'pending',
        'expires_at' => now()->addMinutes(15),
    ]);
    $svc->reject($reqNear, (int) $driverNearUser->id);
    $afterReject = DriverTripRequest::query()
        ->where('schedule_id', $schedule2->id)
        ->where('status', 'pending')
        ->latest()
        ->first();
    assertTrue($afterReject !== null, 'reject → pending tài xế khác');
    assertTrue((int) $afterReject->driver_id === (int) $driverFarUser->id, 'reject → chọn TX xa');

    echo "\n5) wallet block — pickBest bỏ qua TX block\n";
    $pick = $prox->pickBest(
        $schedule2->fresh(['route', 'vehicle']),
        $booking2->fresh(),
        collect([(int) $driverNearUser->id, (int) $driverFarUser->id]),
    );
    assertTrue($pick === null, 'không còn TX khả dụng sau khi loại gần/xa');

    $pickOpen = $prox->pickBest(
        $schedule2->fresh(['route', 'vehicle']),
        $booking2->fresh(),
        collect([(int) $driverNearUser->id]),
    );
    assertTrue($pickOpen !== null, 'vẫn còn TX khi chỉ loại near');
    assertTrue((int) $pickOpen->user_id === (int) $driverFarUser->id, 'chọn TX xa thay vì TX block');

    echo "\n6) requestDriver TX block → exception\n";
    $dep3 = now()->addHours(6);
    $schedule3 = Schedule::query()->create([
        'route_id' => $route->id, 'vehicle_id' => $vehicle->id, 'driver_id' => null, 'driver_name' => '',
        'departure_time' => $dep3, 'service_date' => $dep3->toDateString(),
        'available_seats' => 4, 'status' => 'scheduled',
    ]);
    $booking3 = Booking::query()->create(array_merge($bookingData, [
        'schedule_id' => $schedule3->id,
        'contact_phone' => '0916' . random_int(100000, 999999),
        'booking_reference' => 'BK-SCR3-' . uniqid(),
    ]));
    $blocked = false;
    try {
        $svc->requestDriver($schedule3->fresh(['route', 'vehicle']), $profileBlock->driver_code, $booking3->contact_phone);
    } catch (InvalidArgumentException $e) {
        $blocked = str_contains($e->getMessage(), 'không thể nhận cuốc');
    }
    assertTrue($blocked, 'operator/request tài xế block bị từ chối');

    echo "\n7) schedule đã có driver → autoAssign null\n";
    $schedule2->update(['driver_id' => $driverNearUser->id, 'driver_name' => $driverNearUser->name]);
    assertTrue(
        $svc->autoAssignForBooking($booking2->fresh(['schedule'])) === null,
        'bỏ qua khi đã gán driver',
    );

    echo "\n=== Kết quả: " . ($failures === 0 ? 'PASS' : "{$failures} FAIL") . " ===\n";
} finally {
    DB::rollBack();
}

exit($failures > 0 ? 1 : 0);
