<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Schedule;
use App\Models\TripRoute;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\DriverAvailabilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DriverAvailabilityGapTest extends TestCase
{
    use DatabaseTransactions;

    public function createApplication()
    {
        $base = dirname(__DIR__, 2);
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
                if (! str_starts_with($key, 'DB_')) {
                    continue;
                }
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
        putenv('DB_CONNECTION=mysql');
        $_ENV['DB_CONNECTION'] = 'mysql';
        $_SERVER['DB_CONNECTION'] = 'mysql';

        $app = require $base . '/bootstrap/app.php';
        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
        $app['config']->set('database.default', 'mysql');

        return $app;
    }

    public function test_driver_can_accept_short_gap_trip_before_committed_schedule(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-02 08:00:00', config('app.timezone')));

        $driver = User::factory()->create(['role' => 'driver']);
        $operator = User::factory()->create(['role' => 'operator']);

        $vehicle = Vehicle::query()->create([
            'operator_id'   => $operator->id,
            'license_plate' => '51G-' . random_int(10000, 99999),
            'type'          => 'sedan',
            'capacity'      => 4,
            'status'        => 'active',
        ]);

        $longRoute = TripRoute::query()->firstOrCreate(
            ['departure' => 'TP.HCM', 'destination' => 'Cà Mau'],
            ['base_price' => 800000, 'distance_km' => 250, 'is_active' => true],
        );

        $shortRoute = TripRoute::query()->firstOrCreate(
            ['departure' => 'TP.HCM', 'destination' => 'Đồng Nai'],
            ['base_price' => 150000, 'distance_km' => 20, 'is_active' => true],
        );

        $committedDeparture = now()->copy()->setTime(13, 0);
        $committedSchedule = Schedule::query()->create([
            'route_id'        => $longRoute->id,
            'vehicle_id'      => $vehicle->id,
            'driver_id'       => $driver->id,
            'driver_name'     => $driver->name,
            'departure_time'  => $committedDeparture,
            'service_date'    => $committedDeparture->toDateString(),
            'status'          => 'scheduled',
            'trip_code'       => 'CM' . strtoupper(substr(uniqid(), -4)),
        ]);

        Booking::query()->create([
            'contact_phone'     => '0909111222',
            'passenger_name'    => 'Khách Cà Mau',
            'schedule_id'       => $committedSchedule->id,
            'booking_reference' => 'BK-CM-' . uniqid(),
            'total_price'       => 800000,
            'payment_status'    => 'unpaid',
            'trip_status'       => 'pending',
            'booking_status'    => 'pending',
            'pickup_address'    => 'TP.HCM',
            'pickup_lat'        => 10.7769,
            'pickup_lng'        => 106.7009,
            'dropoff_address'   => 'Cà Mau',
        ]);

        $gapDeparture = now()->copy()->setTime(8, 30);
        $gapSchedule = Schedule::query()->create([
            'route_id'        => $shortRoute->id,
            'vehicle_id'      => $vehicle->id,
            'driver_id'       => null,
            'driver_name'     => '',
            'departure_time'  => $gapDeparture,
            'service_date'    => $gapDeparture->toDateString(),
            'status'          => 'scheduled',
            'trip_code'       => 'DN' . strtoupper(substr(uniqid(), -4)),
        ]);

        $gapBooking = Booking::query()->create([
            'contact_phone'     => '0909333444',
            'passenger_name'    => 'Khách Đồng Nai',
            'schedule_id'       => $gapSchedule->id,
            'booking_reference' => 'BK-DN-' . uniqid(),
            'total_price'       => 150000,
            'payment_status'    => 'unpaid',
            'trip_status'       => 'pending',
            'booking_status'    => 'pending',
            'pickup_address'    => 'TP.HCM',
            'pickup_lat'        => 10.7769,
            'pickup_lng'        => 106.7009,
            'dropoff_address'   => 'Đồng Nai',
        ]);

        $availability = app(DriverAvailabilityService::class);

        $this->assertFalse($availability->hasTripTimeConflict(
            (int) $driver->id,
            $gapSchedule,
            $gapBooking,
        ));
    }

    public function test_driver_cannot_accept_gap_trip_that_runs_into_committed_schedule(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-02 08:00:00', config('app.timezone')));

        $driver = User::factory()->create(['role' => 'driver']);
        $operator = User::factory()->create(['role' => 'operator']);

        $vehicle = Vehicle::query()->create([
            'operator_id'   => $operator->id,
            'license_plate' => '51H-' . random_int(10000, 99999),
            'type'          => 'sedan',
            'capacity'      => 4,
            'status'        => 'active',
        ]);

        $longRoute = TripRoute::query()->firstOrCreate(
            ['departure' => 'TP.HCM', 'destination' => 'Cà Mau'],
            ['base_price' => 800000, 'distance_km' => 250, 'is_active' => true],
        );

        $committedDeparture = now()->copy()->setTime(13, 0);
        $committedSchedule = Schedule::query()->create([
            'route_id'        => $longRoute->id,
            'vehicle_id'      => $vehicle->id,
            'driver_id'       => $driver->id,
            'driver_name'     => $driver->name,
            'departure_time'  => $committedDeparture,
            'service_date'    => $committedDeparture->toDateString(),
            'status'          => 'scheduled',
            'trip_code'       => 'CM2' . strtoupper(substr(uniqid(), -3)),
        ]);

        Booking::query()->create([
            'contact_phone'     => '0909555666',
            'passenger_name'    => 'Khách Cà Mau',
            'schedule_id'       => $committedSchedule->id,
            'booking_reference' => 'BK-CM2-' . uniqid(),
            'total_price'       => 800000,
            'payment_status'    => 'unpaid',
            'trip_status'       => 'pending',
            'booking_status'    => 'pending',
            'pickup_address'    => 'TP.HCM',
            'pickup_lat'        => 10.7769,
            'pickup_lng'        => 106.7009,
            'dropoff_address'   => 'Cà Mau',
        ]);

        $heavyDeparture = now()->copy()->setTime(8, 30);
        $heavySchedule = Schedule::query()->create([
            'route_id'        => $longRoute->id,
            'vehicle_id'      => $vehicle->id,
            'driver_id'       => null,
            'driver_name'     => '',
            'departure_time'  => $heavyDeparture,
            'service_date'    => $heavyDeparture->toDateString(),
            'status'          => 'scheduled',
            'trip_code'       => 'CM3' . strtoupper(substr(uniqid(), -3)),
        ]);

        $heavyBooking = Booking::query()->create([
            'contact_phone'     => '0909777888',
            'passenger_name'    => 'Khách dài',
            'schedule_id'       => $heavySchedule->id,
            'booking_reference' => 'BK-CM3-' . uniqid(),
            'total_price'       => 800000,
            'payment_status'    => 'unpaid',
            'trip_status'       => 'pending',
            'booking_status'    => 'pending',
            'pickup_address'    => 'TP.HCM',
            'pickup_lat'        => 10.7769,
            'pickup_lng'        => 106.7009,
            'dropoff_address'   => 'Cà Mau',
        ]);

        $availability = app(DriverAvailabilityService::class);

        $this->assertTrue($availability->hasTripTimeConflict(
            (int) $driver->id,
            $heavySchedule,
            $heavyBooking,
        ));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
