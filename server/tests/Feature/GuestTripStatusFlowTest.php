<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\DriverProfile;
use App\Models\DriverTripRequest;
use App\Models\Schedule;
use App\Models\TripRoute;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\DriverTripRequestService;
use App\Services\GuestTripStatusService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Status flow KH/TX — chạy MySQL (giống DriverAutoAssignTest).
 */
class GuestTripStatusFlowTest extends TestCase
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

    private function seedPendingOnDemandBooking(array $overrides = []): Booking
    {
        $operator = User::factory()->create(['role' => 'operator', 'email' => 'op-st-' . uniqid() . '@test.test']);

        $suffix = substr(uniqid(), -6);
        $route = TripRoute::query()->create([
            'departure'   => 'A-' . $suffix,
            'destination' => 'B-' . $suffix,
            'base_price'  => 200000,
            'distance_km' => 95,
            'is_active'   => true,
        ]);

        $vehicle = Vehicle::query()->create([
            'operator_id'   => $operator->id,
            'license_plate' => '51A-' . random_int(10000, 99999),
            'type'          => 'sedan',
            'capacity'      => 4,
            'status'        => 'active',
        ]);

        $schedule = Schedule::query()->create([
            'route_id'       => $route->id,
            'vehicle_id'     => $vehicle->id,
            'driver_name'    => 'Chờ phân bổ',
            'departure_time' => now()->addMinutes(20),
            'service_date'   => now()->toDateString(),
            'status'         => 'scheduled',
            'trip_code'      => 'GTST' . uniqid(),
        ]);

        return Booking::query()->create(array_merge([
            'contact_phone'            => '0909' . random_int(100000, 999999),
            'passenger_name'           => 'Khách Test',
            'passenger_gender'         => 'male',
            'schedule_id'              => $schedule->id,
            'booking_reference'        => 'BK-ST-' . uniqid(),
            'total_price'              => 200000,
            'payment_status'           => 'unpaid',
            'trip_status'              => 'pending',
            'booking_status'           => 'pending',
            'pickup_address'           => 'TP.HCM',
            'dropoff_address'          => 'Vũng Tàu',
            'pickup_lat'               => 10.7769,
            'pickup_lng'               => 106.7009,
            'driver_search_started_at' => now(),
        ], $overrides));
    }

    public function test_serialize_hides_driver_while_offer_pending(): void
    {
        $booking = $this->seedPendingOnDemandBooking();
        $driver = User::factory()->create([
            'role'  => 'driver',
            'name'  => 'TX Pending',
            'email' => 'tx-pend-' . uniqid() . '@test.test',
        ]);

        DriverTripRequest::query()->create([
            'schedule_id'   => $booking->schedule_id,
            'contact_phone' => $booking->contact_phone,
            'driver_id'     => $driver->id,
            'status'        => 'pending',
            'expires_at'    => now()->addMinute(),
        ]);

        $payload = app(GuestTripStatusService::class)->serialize($booking->fresh(['schedule']));

        $this->assertFalse($payload['has_driver']);
        $this->assertNull($payload['driver']);
        $this->assertSame('Chờ tài xế', $payload['guest_status_label']);
        $this->assertSame('Chờ tài xế', $payload['progress_label']);
    }

    public function test_serialize_shows_driver_and_status_after_accept(): void
    {
        $booking = $this->seedPendingOnDemandBooking();
        $driver = User::factory()->create([
            'role'  => 'driver',
            'name'  => 'TX Accepted',
            'email' => 'tx-acc-' . uniqid() . '@test.test',
        ]);

        DriverProfile::query()->create([
            'user_id'               => $driver->id,
            'driver_code'           => 'TXACC' . random_int(10, 99),
            'license_number'        => 'GPLX' . random_int(100000, 999999),
            'license_class'         => 'B2',
            'license_expiry'        => now()->addYears(2)->toDateString(),
            'status'                => 'active',
            'approval_status'       => 'approved',
            'availability_status'   => 'on_trip',
            'vehicle_type'          => 'sedan',
            'vehicle_seats'         => 4,
            'vehicle_license_plate' => '51B-12345',
            'last_lat'              => 10.7775,
            'last_lng'              => 106.7015,
            'last_location_at'      => now(),
        ]);

        $booking->schedule->update([
            'driver_id'    => $driver->id,
            'driver_name'  => $driver->name,
            'driver_stage' => Schedule::DRIVER_STAGE_ASSIGNED,
        ]);

        $booking->update([
            'booking_status' => 'confirmed',
            'trip_status'    => 'confirmed',
            'confirmed_at'   => now(),
        ]);

        DriverTripRequest::query()->create([
            'schedule_id'   => $booking->schedule_id,
            'contact_phone' => $booking->contact_phone,
            'driver_id'     => $driver->id,
            'status'        => 'accepted',
            'expires_at'    => now()->addMinute(),
            'responded_at'  => now(),
        ]);

        $payload = app(GuestTripStatusService::class)->serialize($booking->fresh(['schedule']));

        $this->assertTrue($payload['has_driver']);
        $this->assertNotNull($payload['driver']);
        $this->assertSame('Đã nhận chuyến', $payload['driver_status_line']);
        $this->assertSame('Đã tìm thấy tài xế', $payload['guest_status_label']);
    }

    public function test_on_demand_search_timeout_cancels_booking(): void
    {
        $booking = $this->seedPendingOnDemandBooking([
            'driver_search_started_at' => now()->subMinutes(11),
        ]);
        $booking->forceFill(['created_at' => now()->subMinutes(11)])->saveQuietly();

        $service = app(DriverTripRequestService::class);
        $fresh = $booking->fresh(['schedule']);
        $this->assertTrue($service->hasExceededCustomerSearchDeadline($fresh));
        $this->assertTrue($service->cancelCustomerSearchIfOverdue($fresh));

        $booking->refresh();
        $this->assertSame('cancelled', $booking->booking_status);
        $this->assertSame('cancelled', $booking->trip_status);
        $this->assertSame('system', $booking->cancelled_by);
        $this->assertStringContainsString('10 phút', (string) $booking->cancellation_reason_label);
    }

    public function test_scheduled_search_stop_cancels_booking_one_hour_before_pickup(): void
    {
        $pickupAt = now()->addMinutes(50);
        $booking = $this->seedPendingOnDemandBooking([
            'pickup_time' => $pickupAt->format('H:i'),
            'driver_search_started_at' => now()->subHours(2),
        ]);
        $booking->schedule->update([
            'departure_time' => $pickupAt,
            'service_date'   => $pickupAt->toDateString(),
        ]);

        $service = app(DriverTripRequestService::class);
        $fresh = $booking->fresh(['schedule']);
        $this->assertTrue($service->hasReachedScheduledSearchStop($fresh));
        $this->assertTrue(app(\App\Services\BookingWorkflowService::class)->cancelScheduledSearchTimeout($fresh));

        $booking->refresh();
        $this->assertSame('cancelled', $booking->booking_status);
        $this->assertSame('cancelled', $booking->trip_status);
        $this->assertSame('system', $booking->cancelled_by);
        $this->assertStringContainsString('1 tiếng', (string) $booking->cancellation_reason_label);
    }

}
