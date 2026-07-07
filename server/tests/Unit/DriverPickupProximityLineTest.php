<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\Schedule;
use App\Models\TripRoute;
use App\Services\DriverLatePickupService;
use Carbon\Carbon;
use Tests\TestCase;

class DriverPickupProximityLineTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_hidden_assigned_trip_does_not_show_proximity_line(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-07 10:00:00'));

        $route = new TripRoute([
            'departure'   => 'TP.HCM',
            'destination' => 'Vũng Tàu',
            'distance_km' => 95,
        ]);

        $schedule = new Schedule([
            'driver_id'      => 42,
            'driver_stage'   => Schedule::DRIVER_STAGE_ASSIGNED,
            'departure_time' => now()->subMinutes(5),
        ]);
        $schedule->setRelation('route', $route);
        $schedule->setRelation('tripSettlement', null);

        $booking = new Booking([
            'pickup_time'               => now()->subMinutes(5)->format('H:i'),
            'driver_pickup_distance_km' => 0.5,
            'trip_status'               => 'scheduled',
            'booking_status'            => 'confirmed',
        ]);
        $booking->setRelation('schedule', $schedule);
        $schedule->setRelation('bookings', collect([$booking]));

        $this->assertFalse($schedule->isVisibleOnDriverDashboard());

        $line = app(DriverLatePickupService::class)->driverPickupProximityLine($schedule);

        $this->assertNull($line);
    }
}
