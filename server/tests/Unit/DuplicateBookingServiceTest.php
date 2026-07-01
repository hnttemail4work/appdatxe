<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\Schedule;
use App\Models\TripRoute;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\DuplicateBookingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class DuplicateBookingServiceTest extends TestCase
{
    use RefreshDatabase;

    private function seedBooking(string $phone, array $overrides = []): Booking
    {
        $operator = User::factory()->create(['role' => 'operator']);

        $route = TripRoute::query()->create([
            'departure'   => 'TP.HCM',
            'destination' => 'Vũng Tàu',
            'base_price'  => 200000,
            'distance_km' => 95,
            'is_active'   => true,
        ]);

        $vehicle = Vehicle::query()->create([
            'operator_id'   => $operator->id,
            'license_plate' => '51A-88888',
            'type'          => 'sedan',
            'capacity'      => 4,
            'status'        => 'active',
        ]);

        $schedule = Schedule::query()->create([
            'route_id'        => $route->id,
            'vehicle_id'      => $vehicle->id,
            'driver_name'     => 'Chờ phân bổ',
            'departure_time'  => now()->addDay(),
            'service_date'    => now()->addDay()->toDateString(),
            'available_seats' => 4,
            'status'          => 'scheduled',
            'trip_code'       => 'GTDUP' . uniqid(),
        ]);

        return Booking::query()->create(array_merge([
            'contact_phone'     => $phone,
            'passenger_name'    => 'Khách Test',
            'passenger_gender'  => 'male',
            'schedule_id'       => $schedule->id,
            'seat_numbers'      => ['1'],
            'trip_type'         => 'one_way',
            'booking_mode'      => 'shared',
            'booking_reference' => 'BK-DUP-' . uniqid(),
            'total_price'       => 200000,
            'payment_status'    => 'unpaid',
            'trip_status'       => 'pending',
            'booking_status'    => 'pending',
            'pickup_address'    => 'TP.HCM',
            'dropoff_address'   => 'Vũng Tàu',
        ], $overrides));
    }

    public function test_find_active_booking_by_normalized_phone(): void
    {
        $this->seedBooking('0909123456');

        $service = app(DuplicateBookingService::class);

        $this->assertNotNull($service->findActiveBooking('84909123456'));
        $this->assertNull($service->findActiveBooking('0909111111'));
    }

    public function test_assert_can_book_blocks_when_active_exists(): void
    {
        $this->seedBooking('0909123456');

        $service = app(DuplicateBookingService::class);

        $this->expectException(InvalidArgumentException::class);
        $service->assertCanBook('0909123456');
    }

    public function test_completed_booking_does_not_block(): void
    {
        $this->seedBooking('0909123456', [
            'trip_status'    => 'completed',
            'booking_status' => 'confirmed',
            'completed_at'   => now(),
        ]);

        $service = app(DuplicateBookingService::class);

        $service->assertCanBook('0909123456');
        $this->assertNull($service->findActiveBooking('0909123456'));
    }

    public function test_cancelled_booking_does_not_block(): void
    {
        $this->seedBooking('0909123456', [
            'trip_status'    => 'cancelled',
            'booking_status' => 'cancelled',
            'cancelled_at'   => now(),
            'cancelled_by'   => 'customer',
        ]);

        $service = app(DuplicateBookingService::class);

        $service->assertCanBook('0909123456');
        $this->assertNull($service->findActiveBooking('0909123456'));
    }
}
