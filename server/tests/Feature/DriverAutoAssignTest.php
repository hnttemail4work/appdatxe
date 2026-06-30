<?php

namespace Tests\Feature;

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
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Integration tests — chạy trên MySQL (phpunit.xml sqlite bị override).
 * Rollback sau mỗi test, không để rác dữ liệu.
 */
class DriverAutoAssignTest extends TestCase
{
    use DatabaseTransactions;

    public function createApplication()
    {
        $base = dirname(__DIR__, 2);
        self::applyMysqlEnvFromDotEnv($base);

        $app = require $base . '/bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $app['config']->set('database.default', 'mysql');

        return $app;
    }

    private static function applyMysqlEnvFromDotEnv(string $base): void
    {
        $envFile = $base . '/.env';
        if (! is_file($envFile)) {
            return;
        }

        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\"'");
            if (! str_starts_with($key, 'DB_')) {
                continue;
            }
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        putenv('DB_CONNECTION=mysql');
        $_ENV['DB_CONNECTION'] = 'mysql';
        $_SERVER['DB_CONNECTION'] = 'mysql';
    }

    /** @return array<string, mixed> */
    private function seedOpenScheduleWithDrivers(): array
    {
        $operator = User::factory()->create(['role' => 'operator', 'email' => 'op-auto-' . uniqid() . '@test.test']);

        $driverNear = User::factory()->create(['role' => 'driver', 'name' => 'TX Gần', 'email' => 'near-' . uniqid() . '@test.test']);
        $driverFar = User::factory()->create(['role' => 'driver', 'name' => 'TX Xa', 'email' => 'far-' . uniqid() . '@test.test']);
        $driverBlocked = User::factory()->create(['role' => 'driver', 'name' => 'TX Block', 'email' => 'block-' . uniqid() . '@test.test']);

        $profileNear = $this->makeDriverProfile($driverNear, $operator->id, 'TXNEAR' . random_int(10, 99), [
            'last_lat' => 10.7769,
            'last_lng' => 106.7009,
            'last_location_at' => now(),
            'last_address' => '123 Nguyễn Huệ, Quận 1, Thành phố Hồ Chí Minh',
        ]);
        $this->setDriverPreferences($profileNear, 5, 0);

        $profileFar = $this->makeDriverProfile($driverFar, $operator->id, 'TXFAR' . random_int(10, 99), [
            'last_lat' => 10.8500,
            'last_lng' => 106.7800,
            'last_location_at' => now(),
            'last_address' => '456 Lê Lợi, Quận 1, Thành phố Hồ Chí Minh',
        ]);
        $this->setDriverPreferences($profileFar, 10, 0);

        $profileBlocked = $this->makeDriverProfile($driverBlocked, $operator->id, 'TXBLK' . random_int(10, 99), [
            'last_lat' => 10.7770,
            'last_lng' => 106.7010,
            'last_location_at' => now(),
            'last_address' => '789 Pasteur, Quận 3, Thành phố Hồ Chí Minh',
        ]);
        $this->setDriverPreferences($profileBlocked, 100, 0);

        DriverWallet::query()->updateOrCreate(
            ['driver_profile_id' => $profileBlocked->id],
            [
                'balance' => 0,
                'wallet_gate_enabled' => true,
                'wallet_activated_at' => now(),
                'total_approved_deposits' => 100_000,
            ],
        );

        DriverWallet::query()->updateOrCreate(
            ['driver_profile_id' => $profileNear->id],
            [
                'balance' => DriverWalletConfig::MIN_BALANCE + 50_000,
                'wallet_gate_enabled' => false,
                'wallet_activated_at' => now(),
                'total_approved_deposits' => 100_000,
            ],
        );

        DriverWallet::query()->updateOrCreate(
            ['driver_profile_id' => $profileFar->id],
            [
                'balance' => DriverWalletConfig::MIN_BALANCE + 50_000,
                'wallet_gate_enabled' => false,
                'wallet_activated_at' => now(),
                'total_approved_deposits' => 100_000,
            ],
        );

        $route = TripRoute::query()->firstOrCreate(
            ['departure' => 'TP.HCM', 'destination' => 'Vũng Tàu'],
            ['base_price' => 200000, 'distance_km' => 95, 'is_active' => true],
        );

        $vehicle = Vehicle::query()->create([
            'operator_id'   => $operator->id,
            'license_plate' => '51A-' . random_int(10000, 99999),
            'type'          => 'sedan',
            'capacity'      => 4,
            'status'        => 'active',
        ]);

        $departure = now()->addHours(3);

        $schedule = Schedule::query()->create([
            'route_id'        => $route->id,
            'vehicle_id'      => $vehicle->id,
            'driver_id'       => null,
            'driver_name'     => '',
            'departure_time'  => $departure,
            'service_date'    => $departure->toDateString(),
            'available_seats' => 4,
            'status'          => 'scheduled',
            'trip_code'       => 'AT' . strtoupper(substr(uniqid(), -5)),
        ]);

        $booking = Booking::query()->create([
            'contact_phone'     => '0909' . random_int(100000, 999999),
            'passenger_name'    => 'Khách AutoAssign',
            'passenger_gender'  => 'male',
            'schedule_id'       => $schedule->id,
            'seat_numbers'      => ['1'],
            'trip_type'         => 'one_way',
            'booking_mode'      => 'shared',
            'booking_reference' => 'BK-AUTO-' . uniqid(),
            'total_price'       => 200000,
            'payment_status'    => 'unpaid',
            'trip_status'       => 'pending',
            'booking_status'    => 'pending',
            'pickup_address'    => 'TP.HCM',
            'pickup_lat'        => 10.7769,
            'pickup_lng'        => 106.7009,
            'dropoff_address'   => 'Vũng Tàu',
        ]);

        $testDriverUserIds = [$driverNear->id, $driverFar->id, $driverBlocked->id];
        DriverProfile::query()
            ->whereNotIn('user_id', $testDriverUserIds)
            ->update(['availability_status' => 'off_duty']);

        return compact(
            'operator',
            'driverNear',
            'driverFar',
            'driverBlocked',
            'profileNear',
            'profileFar',
            'profileBlocked',
            'route',
            'vehicle',
            'schedule',
            'booking',
        );
    }

    private function makeDriverProfile(User $user, int $operatorId, string $code, array $extra = []): DriverProfile
    {
        $base = [
            'user_id'             => $user->id,
            'operator_id'         => $operatorId,
            'driver_code'         => $code,
            'license_number'      => 'L' . random_int(100000, 999999),
            'license_class'       => 'B2',
            'license_expiry'      => now()->addYears(2)->toDateString(),
            'status'              => 'active',
            'approval_status'     => 'approved',
            'availability_status' => 'available',
        ];

        if (Schema::hasColumn('driver_profiles', 'last_lat')) {
            $base = array_merge($base, array_intersect_key($extra, array_flip([
                'last_lat', 'last_lng', 'last_location_at', 'last_province', 'last_address',
            ])));
        }

        return DriverProfile::query()->create(array_merge($base, array_diff_key($extra, array_flip([
            'last_lat', 'last_lng', 'last_location_at', 'last_province', 'last_address', 'preference_likes', 'preference_dislikes',
        ]))));
    }

    private function setDriverPreferences(DriverProfile $profile, int $likes, int $dislikes): void
    {
        if (! Schema::hasColumn('driver_profiles', 'preference_likes')) {
            return;
        }

        $profile->update([
            'preference_likes'    => $likes,
            'preference_dislikes' => $dislikes,
        ]);
    }

    private function schemaReady(): bool
    {
        return Schema::hasTable('driver_trip_requests')
            && Schema::hasTable('driver_wallets')
            && Schema::hasColumn('driver_profiles', 'driver_code');
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->schemaReady()) {
            $this->markTestSkipped('Database thiếu migration auto-assign / ví tài xế — chạy php artisan migrate.');
        }
    }

    public function test_auto_assign_picks_nearest_available_driver(): void
    {
        if (! $this->schemaReady()) {
            $this->markTestSkipped('Thiếu bảng/cột cần cho auto-assign — chạy php artisan migrate.');
        }

        $data = $this->seedOpenScheduleWithDrivers();
        $service = app(DriverTripRequestService::class);

        $request = $service->autoAssignForBooking($data['booking']->fresh(['schedule.route', 'schedule.vehicle']));

        $this->assertNotNull($request);
        $this->assertSame('pending', $request->status);
        $this->assertSame((int) $data['driverNear']->id, (int) $request->driver_id);
        $this->assertNull($data['schedule']->fresh()->driver_id);
    }

    public function test_auto_assign_skips_wallet_blocked_driver(): void
    {
        $data = $this->seedOpenScheduleWithDrivers();

        DriverProfile::query()->whereKey($data['profileNear']->id)->update([
            'availability_status' => 'off_duty',
        ]);

        $service = app(DriverTripRequestService::class);
        $request = $service->autoAssignForBooking($data['booking']->fresh(['schedule.route', 'schedule.vehicle']));

        $this->assertNotNull($request);
        $this->assertSame((int) $data['driverFar']->id, (int) $request->driver_id);
        $this->assertNotSame((int) $data['driverBlocked']->id, (int) $request->driver_id);
    }

    public function test_proximity_prefers_nearest_over_dislikes(): void
    {
        if (! Schema::hasColumn('driver_profiles', 'preference_dislikes')) {
            $this->markTestSkipped('Chưa có cột preference_dislikes — chạy php artisan migrate.');
        }

        $data = $this->seedOpenScheduleWithDrivers();

        DriverProfile::query()->whereKey($data['profileNear']->id)->update([
            'preference_dislikes' => 2,
            'last_lat' => 10.7770,
            'last_lng' => 106.7010,
            'last_location_at' => now(),
        ]);

        DriverProfile::query()->whereKey($data['profileFar']->id)->update([
            'preference_dislikes' => 0,
            'last_lat' => 11.0,
            'last_lng' => 106.5,
            'last_location_at' => now(),
        ]);

        $pick = app(DriverProximityService::class)->pickBest(
            $data['schedule']->fresh(['route', 'vehicle']),
            $data['booking']->fresh(),
            collect(),
            true,
        );

        $this->assertNotNull($pick);
        $this->assertSame((int) $data['driverNear']->id, (int) $pick->user_id);
    }

    public function test_proximity_prefers_fewer_dislikes_when_distance_and_threshold_equal(): void
    {
        if (! Schema::hasColumn('driver_profiles', 'preference_dislikes')) {
            $this->markTestSkipped('Chưa có cột preference_dislikes — chạy php artisan migrate.');
        }

        $data = $this->seedOpenScheduleWithDrivers();

        DriverProfile::query()->whereKey($data['profileNear']->id)->update([
            'preference_dislikes' => 0,
            'preference_likes'    => 3,
            'last_lat'            => 10.7769,
            'last_lng'            => 106.7009,
            'last_location_at'    => now(),
        ]);

        DriverProfile::query()->whereKey($data['profileFar']->id)->update([
            'preference_dislikes' => 4,
            'preference_likes'    => 3,
            'last_lat'            => 10.7769,
            'last_lng'            => 106.7009,
            'last_location_at'    => now(),
        ]);

        $pick = app(DriverProximityService::class)->pickBest(
            $data['schedule']->fresh(['route', 'vehicle']),
            $data['booking']->fresh(),
            collect(),
            true,
        );

        $this->assertNotNull($pick);
        $this->assertSame((int) $data['driverNear']->id, (int) $pick->user_id);
    }

    public function test_proximity_prefers_pre_threshold_driver_when_other_factors_equal(): void
    {
        $data = $this->seedOpenScheduleWithDrivers();

        DriverProfile::query()->whereKey($data['profileNear']->id)->update([
            'preference_dislikes' => 0,
            'preference_likes'    => 5,
            'last_lat'            => 10.7769,
            'last_lng'            => 106.7009,
            'last_location_at'    => now(),
        ]);

        DriverProfile::query()->whereKey($data['profileFar']->id)->update([
            'preference_dislikes' => 0,
            'preference_likes'    => 5,
            'last_lat'            => 10.7769,
            'last_lng'            => 106.7009,
            'last_location_at'    => now(),
        ]);

        DriverWallet::query()->where('driver_profile_id', $data['profileNear']->id)->update([
            'wallet_gate_enabled'          => false,
            'completed_settlements_count'  => 0,
        ]);

        DriverWallet::query()->where('driver_profile_id', $data['profileFar']->id)->update([
            'wallet_gate_enabled'         => true,
            'balance'                     => DriverWalletConfig::MIN_BALANCE + 50_000,
            'completed_settlements_count' => 3,
        ]);

        $pick = app(DriverProximityService::class)->pickBest(
            $data['schedule']->fresh(['route', 'vehicle']),
            $data['booking']->fresh(),
            collect(),
            true,
        );

        $this->assertNotNull($pick);
        $this->assertSame((int) $data['driverNear']->id, (int) $pick->user_id);
    }

    public function test_auto_assign_ignores_operator(): void
    {
        $data = $this->seedOpenScheduleWithDrivers();
        $otherOperator = User::factory()->create([
            'role' => 'operator',
            'email' => 'op-other-' . uniqid() . '@test.test',
        ]);

        DriverProfile::query()->whereKey($data['profileNear']->id)->update([
            'operator_id' => $otherOperator->id,
        ]);

        $request = app(DriverTripRequestService::class)->autoAssignForBooking(
            $data['booking']->fresh(['schedule.route', 'schedule.vehicle']),
        );

        $this->assertNotNull($request);
        $this->assertSame((int) $data['driverNear']->id, (int) $request->driver_id);
    }

    public function test_expire_stale_reassigns_to_next_driver(): void
    {
        $data = $this->seedOpenScheduleWithDrivers();
        $service = app(DriverTripRequestService::class);

        $first = DriverTripRequest::query()->create([
            'schedule_id'   => $data['schedule']->id,
            'contact_phone' => $data['booking']->contact_phone,
            'driver_id'     => $data['driverNear']->id,
            'status'        => 'pending',
            'expires_at'    => now()->subMinute(),
        ]);

        $service->expireStale();

        $first->refresh();
        $this->assertSame('expired', $first->status);

        $pending = DriverTripRequest::query()
            ->where('schedule_id', $data['schedule']->id)
            ->where('contact_phone', $data['booking']->contact_phone)
            ->where('status', 'pending')
            ->latest()
            ->first();

        $this->assertNotNull($pending);
        $this->assertNotSame((int) $data['driverNear']->id, (int) $pending->driver_id);
        $this->assertSame((int) $data['driverFar']->id, (int) $pending->driver_id);
    }

    public function test_reject_reassigns_to_next_driver(): void
    {
        $data = $this->seedOpenScheduleWithDrivers();
        $service = app(DriverTripRequestService::class);

        $request = DriverTripRequest::query()->create([
            'schedule_id'   => $data['schedule']->id,
            'contact_phone' => $data['booking']->contact_phone,
            'driver_id'     => $data['driverNear']->id,
            'status'        => 'pending',
            'expires_at'    => now()->addMinutes(15),
        ]);

        $service->reject($request, (int) $data['driverNear']->id);

        $request->refresh();
        $this->assertSame('rejected', $request->status);

        $next = DriverTripRequest::query()
            ->where('schedule_id', $data['schedule']->id)
            ->where('contact_phone', $data['booking']->contact_phone)
            ->where('status', 'pending')
            ->latest()
            ->first();

        $this->assertNotNull($next);
        $this->assertSame((int) $data['driverFar']->id, (int) $next->driver_id);
    }

    public function test_auto_assign_skipped_when_schedule_already_has_driver(): void
    {
        $data = $this->seedOpenScheduleWithDrivers();
        $data['schedule']->update([
            'driver_id' => $data['driverNear']->id,
            'driver_name' => $data['driverNear']->name,
        ]);

        $result = app(DriverTripRequestService::class)->autoAssignForBooking($data['booking']->fresh(['schedule']));

        $this->assertNull($result);
    }

    public function test_auto_assign_skips_driver_outside_radius(): void
    {
        $data = $this->seedOpenScheduleWithDrivers();

        DriverProfile::query()->whereKey($data['profileNear']->id)->update([
            'last_lat' => 10.3460,
            'last_lng' => 107.0843,
            'last_location_at' => now(),
        ]);
        DriverProfile::query()->whereKey($data['profileFar']->id)->update([
            'last_lat' => 10.3460,
            'last_lng' => 107.0843,
            'last_location_at' => now(),
        ]);

        $request = app(DriverTripRequestService::class)->autoAssignForBooking(
            $data['booking']->fresh(['schedule.route', 'schedule.vehicle']),
        );

        $this->assertNull($request);
        $this->assertNull($data['schedule']->fresh()->driver_id);
    }

    public function test_auto_assign_does_not_duplicate_pending_request(): void
    {
        $data = $this->seedOpenScheduleWithDrivers();
        $service = app(DriverTripRequestService::class);
        $booking = $data['booking']->fresh(['schedule.route', 'schedule.vehicle']);

        $first = $service->autoAssignForBooking($booking);
        $second = $service->autoAssignForBooking($booking);

        $this->assertNotNull($first);
        $this->assertNull($second);
    }

    public function test_retry_waiting_bookings_after_driver_updates_location(): void
    {
        $data = $this->seedOpenScheduleWithDrivers();

        DriverProfile::query()->whereKey($data['profileNear']->id)->update([
            'last_lat' => null,
            'last_lng' => null,
            'last_location_at' => null,
        ]);
        DriverProfile::query()->whereKey($data['profileFar']->id)->update([
            'last_lat' => null,
            'last_lng' => null,
            'last_location_at' => null,
        ]);

        $service = app(DriverTripRequestService::class);
        $this->assertNull($service->autoAssignForBooking($data['booking']->fresh(['schedule.route', 'schedule.vehicle'])));

        DriverProfile::query()->whereKey($data['profileNear']->id)->update([
            'last_lat' => 10.7769,
            'last_lng' => 106.7009,
            'last_location_at' => now(),
        ]);

        $assigned = $service->retryWaitingBookings();
        $this->assertSame(1, $assigned);

        $pending = DriverTripRequest::query()
            ->where('schedule_id', $data['schedule']->id)
            ->where('status', 'pending')
            ->first();

        $this->assertNotNull($pending);
        $this->assertSame((int) $data['driverNear']->id, (int) $pending->driver_id);
    }

    public function test_operator_manual_request_still_works(): void
    {
        $data = $this->seedOpenScheduleWithDrivers();
        $service = app(DriverTripRequestService::class);

        $request = $service->requestDriver(
            $data['schedule']->fresh(['route', 'vehicle']),
            $data['profileFar']->driver_code,
            $data['booking']->contact_phone,
        );

        $this->assertSame('pending', $request->status);
        $this->assertSame((int) $data['driverFar']->id, (int) $request->driver_id);
    }

    public function test_manual_request_to_blocked_driver_fails(): void
    {
        $data = $this->seedOpenScheduleWithDrivers();
        $service = app(DriverTripRequestService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Tài xế không thể nhận cuốc');

        $service->requestDriver(
            $data['schedule']->fresh(['route', 'vehicle']),
            $data['profileBlocked']->driver_code,
            $data['booking']->contact_phone,
        );
    }

    public function test_accept_blocked_driver_cannot_accept(): void
    {
        $data = $this->seedOpenScheduleWithDrivers();

        $request = DriverTripRequest::query()->create([
            'schedule_id'   => $data['schedule']->id,
            'contact_phone' => $data['booking']->contact_phone,
            'driver_id'     => $data['driverBlocked']->id,
            'status'        => 'pending',
            'expires_at'    => now()->addMinutes(15),
        ]);

        DriverWallet::query()->where('driver_profile_id', $data['profileBlocked']->id)->update([
            'wallet_gate_enabled' => true,
            'balance' => 0,
        ]);

        $this->expectException(\InvalidArgumentException::class);

        app(DriverTripRequestService::class)->accept($request, (int) $data['driverBlocked']->id);
    }
}
