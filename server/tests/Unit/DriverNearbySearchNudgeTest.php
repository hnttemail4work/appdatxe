<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\DriverProfile;
use App\Models\Schedule;
use App\Models\TripRoute;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\DriverAvailabilityService;
use App\Services\DriverSystemNotificationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DriverNearbySearchNudgeTest extends TestCase
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

    public function test_nearby_nudge_once_per_offline_session(): void
    {
        Cache::flush();

        $operator = User::factory()->create([
            'role'  => 'operator',
            'email' => 'op-nudge-' . uniqid() . '@test.test',
        ]);
        $driver = User::factory()->create([
            'role'  => 'driver',
            'email' => 'tx-nudge-' . uniqid() . '@test.test',
        ]);

        $profile = DriverProfile::query()->create([
            'user_id'             => $driver->id,
            'operator_id'         => $operator->id,
            'driver_code'         => 'TXN' . random_int(100, 999),
            'license_number'      => 'L' . random_int(100000, 999999),
            'license_class'       => 'B2',
            'license_expiry'      => now()->addYears(2)->toDateString(),
            'status'              => 'active',
            'approval_status'     => 'approved',
            'availability_status' => 'available',
            'vehicle_type'        => 'sedan',
            'vehicle_seats'       => 4,
            'last_lat'            => 10.7769,
            'last_lng'            => 106.7009,
            'last_location_at'    => now(),
        ]);

        $availability = app(DriverAvailabilityService::class);
        $system = app(DriverSystemNotificationService::class);

        $availability->markOffDuty($profile->fresh());

        $suffix = substr(uniqid(), -6);
        $route = TripRoute::query()->create([
            'departure'   => 'N1-' . $suffix,
            'destination' => 'N2-' . $suffix,
            'base_price'  => 200000,
            'distance_km' => 10,
            'is_active'   => true,
        ]);
        $vehicle = Vehicle::query()->create([
            'operator_id'   => $operator->id,
            'license_plate' => '51N-' . random_int(10000, 99999),
            'type'          => 'sedan',
            'capacity'      => 4,
            'status'        => 'active',
        ]);
        $schedule = Schedule::query()->create([
            'route_id'       => $route->id,
            'vehicle_id'     => $vehicle->id,
            'driver_name'    => 'Chờ',
            'departure_time' => now()->addMinutes(15),
            'service_date'   => now()->toDateString(),
            'status'         => 'scheduled',
            'trip_code'      => 'NDG' . uniqid(),
        ]);
        $booking = Booking::query()->create([
            'contact_phone'     => '0909' . random_int(100000, 999999),
            'passenger_name'    => 'Khách Nudge',
            'passenger_gender'  => 'male',
            'schedule_id'       => $schedule->id,
            'booking_reference' => 'BK-ND-' . uniqid(),
            'total_price'       => 150000,
            'payment_status'    => 'unpaid',
            'trip_status'       => 'pending',
            'booking_status'    => 'pending',
            'pickup_address'    => 'Q1',
            'dropoff_address'   => 'Q3',
            'pickup_lat'        => 10.7775,
            'pickup_lng'        => 106.7015,
        ]);

        $first = $system->notifyNearbySearchForBooking($booking->fresh(['schedule']));
        $second = $system->notifyNearbySearchForBooking($booking->fresh(['schedule']));

        $this->assertSame(1, $first);
        $this->assertSame(0, $second);

        // Bật app rồi tắt lại → được nudge lần nữa.
        $availability->markAvailable($profile->fresh());
        $profile->fresh()->update([
            'last_lat'         => 10.7769,
            'last_lng'         => 106.7009,
            'last_location_at' => now(),
        ]);
        $availability->markOffDuty($profile->fresh());

        $third = $system->notifyNearbySearchForBooking($booking->fresh(['schedule']));
        $this->assertSame(1, $third);
    }

    public function test_no_nudge_while_app_presence_active(): void
    {
        Cache::flush();

        $operator = User::factory()->create([
            'role'  => 'operator',
            'email' => 'op-nudge2-' . uniqid() . '@test.test',
        ]);
        $driver = User::factory()->create([
            'role'  => 'driver',
            'email' => 'tx-nudge2-' . uniqid() . '@test.test',
        ]);

        $profile = DriverProfile::query()->create([
            'user_id'             => $driver->id,
            'operator_id'         => $operator->id,
            'driver_code'         => 'TXP' . random_int(100, 999),
            'license_number'      => 'L' . random_int(100000, 999999),
            'license_class'       => 'B2',
            'license_expiry'      => now()->addYears(2)->toDateString(),
            'status'              => 'active',
            'approval_status'     => 'approved',
            'availability_status' => 'available',
            'vehicle_type'        => 'sedan',
            'vehicle_seats'       => 4,
            'last_lat'            => 10.7769,
            'last_lng'            => 106.7009,
            'last_location_at'    => now(),
        ]);

        $availability = app(DriverAvailabilityService::class);
        $availability->touchWebPresence((int) $driver->id);

        $suffix = substr(uniqid(), -6);
        $route = TripRoute::query()->create([
            'departure'   => 'P1-' . $suffix,
            'destination' => 'P2-' . $suffix,
            'base_price'  => 200000,
            'distance_km' => 10,
            'is_active'   => true,
        ]);
        $vehicle = Vehicle::query()->create([
            'operator_id'   => $operator->id,
            'license_plate' => '51P-' . random_int(10000, 99999),
            'type'          => 'sedan',
            'capacity'      => 4,
            'status'        => 'active',
        ]);
        $schedule = Schedule::query()->create([
            'route_id'       => $route->id,
            'vehicle_id'     => $vehicle->id,
            'driver_name'    => 'Chờ',
            'departure_time' => now()->addMinutes(15),
            'service_date'   => now()->toDateString(),
            'status'         => 'scheduled',
            'trip_code'      => 'NDP' . uniqid(),
        ]);
        $booking = Booking::query()->create([
            'contact_phone'     => '0919' . random_int(100000, 999999),
            'passenger_name'    => 'Khách Online',
            'passenger_gender'  => 'male',
            'schedule_id'       => $schedule->id,
            'booking_reference' => 'BK-NP-' . uniqid(),
            'total_price'       => 150000,
            'payment_status'    => 'unpaid',
            'trip_status'       => 'pending',
            'booking_status'    => 'pending',
            'pickup_address'    => 'Q1',
            'dropoff_address'   => 'Q3',
            'pickup_lat'        => 10.7775,
            'pickup_lng'        => 106.7015,
        ]);

        // Còn online — không arm offline → không nudge.
        $sent = app(DriverSystemNotificationService::class)
            ->notifyNearbySearchForBooking($booking->fresh(['schedule']));

        $this->assertSame(0, $sent);
        $this->assertSame('available', $profile->fresh()->availability_status);
    }
}
