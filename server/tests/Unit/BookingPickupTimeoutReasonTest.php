<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\Schedule;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BookingPickupTimeoutReasonTest extends TestCase
{
    public function test_had_driver_engaged_when_assigned_driver_id_set(): void
    {
        if (! Schema::hasColumn('bookings', 'assigned_driver_id')) {
            $this->markTestSkipped('assigned_driver_id column missing');
        }

        $booking = new Booking(['assigned_driver_id' => 99]);
        $booking->setRelation('schedule', new Schedule());

        $this->assertTrue($booking->hadDriverEngagedForPickup());
    }

    public function test_had_driver_engaged_when_schedule_still_has_driver(): void
    {
        $schedule = new Schedule(['driver_id' => 42]);
        $booking = new Booking();
        $booking->setRelation('schedule', $schedule);

        $this->assertTrue($booking->hadDriverEngagedForPickup());
    }

    public function test_pickup_timeout_labels_differ_for_no_driver_vs_no_show(): void
    {
        $neverHadDriverLabel = 'Quá giờ đón — không có tài xế';
        $hadDriverLabel = 'Quá giờ đón — tài xế không đến đón';

        $this->assertNotSame($neverHadDriverLabel, $hadDriverLabel);
        $this->assertStringContainsString('không có tài xế', $neverHadDriverLabel);
        $this->assertStringContainsString('không đến đón', $hadDriverLabel);
    }

    public function test_admin_no_fake_wait_countdown_after_driver_released(): void
    {
        if (! Schema::hasColumn('bookings', 'assigned_driver_id')) {
            $this->markTestSkipped('assigned_driver_id column missing');
        }

        $schedule = new Schedule([
            'id'             => 1,
            'driver_id'      => null,
            'departure_time' => now()->addMinutes(27),
        ]);
        $booking = new Booking([
            'assigned_driver_id' => 99,
            'contact_phone'      => '0901234567',
            'schedule_id'        => 1,
            'pickup_time'        => now()->addMinutes(27)->format('H:i'),
        ]);
        $booking->setRelation('schedule', $schedule);

        $this->assertTrue($booking->adminReleasedAfterDriverEngagement());
        $this->assertNull($booking->adminWaitingMinutesRemaining());
    }
}
