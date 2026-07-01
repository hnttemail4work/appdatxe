<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\Schedule;
use App\Models\TripRoute;
use App\Services\DriverMovementConfirmService;
use Carbon\Carbon;
use Tests\TestCase;

class DriverMovementConfirmServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_urgent_pickup_gets_five_minute_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-27 10:00:00'));

        $service = new DriverMovementConfirmService();
        [$schedule, $booking] = $this->fixtures(minutesToPickup: 45, distanceKm: 12.0);

        $deadline = $service->computeDeadline($schedule, $booking, now());

        $this->assertTrue($deadline->equalTo(now()->addMinutes(5)));
    }

    public function test_long_booking_caps_window_by_travel_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-27 08:00:00'));

        $service = new DriverMovementConfirmService();
        [$schedule, $booking] = $this->fixtures(minutesToPickup: 180, distanceKm: 40.0);

        $deadline = $service->computeDeadline($schedule, $booking, now());
        $window = (int) now()->diffInMinutes($deadline);

        $this->assertGreaterThanOrEqual(10, $window);
        $this->assertLessThanOrEqual(30, $window);
    }

    /** @return array{0: Schedule, 1: Booking} */
    private function fixtures(int $minutesToPickup, float $distanceKm): array
    {
        $route = new TripRoute([
            'departure'   => 'TP.HCM',
            'destination' => 'Vũng Tàu',
            'distance_km' => 95,
        ]);

        $schedule = new Schedule([
            'departure_time' => now()->addMinutes($minutesToPickup),
            'driver_id'      => 99,
        ]);
        $schedule->setRelation('route', $route);

        $booking = new Booking([
            'pickup_time'               => now()->addMinutes($minutesToPickup)->format('H:i'),
            'driver_pickup_distance_km' => $distanceKm,
            'pickup_lat'                => 10.77,
            'pickup_lng'                => 106.70,
        ]);
        $booking->setRelation('schedule', $schedule);

        return [$schedule, $booking];
    }
}
