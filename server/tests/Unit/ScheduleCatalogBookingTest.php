<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\Schedule;
use App\Models\TripRoute;
use Carbon\Carbon;
use Tests\TestCase;

class ScheduleCatalogBookingTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_hidden_past_pickup_assigned_trip_does_not_block_catalog(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-07 10:00:00'));

        $schedule = $this->assignedScheduleWithPickupOffset(-5);

        $this->assertFalse($schedule->isVisibleOnDriverDashboard());
        $this->assertFalse($schedule->blocksDriverCatalogBooking());
    }

    public function test_visible_upcoming_assigned_trip_blocks_catalog(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-07 10:00:00'));

        $schedule = $this->assignedScheduleWithPickupOffset(30);

        $this->assertTrue($schedule->isVisibleOnDriverDashboard());
        $this->assertTrue($schedule->blocksDriverCatalogBooking());
    }

    private function assignedScheduleWithPickupOffset(int $minutesFromNow): Schedule
    {
        $route = new TripRoute([
            'departure'   => 'TP.HCM',
            'destination' => 'Vũng Tàu',
            'distance_km' => 95,
        ]);

        $pickupAt = now()->addMinutes($minutesFromNow);
        $schedule = new Schedule([
            'driver_id'      => 42,
            'driver_stage'   => Schedule::DRIVER_STAGE_ASSIGNED,
            'departure_time' => $pickupAt,
            'status'         => 'scheduled',
        ]);
        $schedule->setRelation('route', $route);
        $schedule->setRelation('tripSettlement', null);

        $booking = new Booking([
            'pickup_time'               => $pickupAt->format('H:i'),
            'driver_pickup_distance_km' => 0.5,
            'trip_status'               => 'scheduled',
            'booking_status'            => 'confirmed',
        ]);
        $booking->setRelation('schedule', $schedule);
        $schedule->setRelation('bookings', collect([$booking]));

        return $schedule;
    }
}
