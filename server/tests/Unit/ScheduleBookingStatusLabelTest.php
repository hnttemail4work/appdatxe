<?php

namespace Tests\Unit;

use App\Models\Schedule;
use Carbon\Carbon;
use Tests\TestCase;

class ScheduleBookingStatusLabelTest extends TestCase
{
    public function test_assigned_without_movement_confirm_shows_driver_assigned(): void
    {
        $schedule = new Schedule([
            'driver_id'                    => 42,
            'driver_stage'                 => Schedule::DRIVER_STAGE_ASSIGNED,
            'driver_movement_confirmed_at' => null,
        ]);

        $this->assertSame('Đã tìm thấy tài xế', $schedule->bookingStatusLabel());
        $this->assertTrue($schedule->needsDriverMovementConfirm());
        $this->assertFalse($schedule->driverHasConfirmedMovement());
    }

    public function test_assigned_with_movement_confirm_shows_en_route_not_at_pickup(): void
    {
        $schedule = new Schedule([
            'driver_id'                    => 42,
            'driver_stage'                 => Schedule::DRIVER_STAGE_ASSIGNED,
            'driver_movement_confirmed_at' => Carbon::now(),
        ]);

        $this->assertSame('Tài xế đang đi đón', $schedule->bookingStatusLabel());
        $this->assertFalse($schedule->needsDriverMovementConfirm());
        $this->assertTrue($schedule->driverHasConfirmedMovement());
    }

    public function test_at_pickup_shows_arrived_label(): void
    {
        $schedule = new Schedule([
            'driver_id'    => 42,
            'driver_stage' => Schedule::DRIVER_STAGE_AT_PICKUP,
        ]);

        $this->assertSame('Tài xế đã đến điểm đón', $schedule->bookingStatusLabel());
        $this->assertTrue($schedule->driverHasConfirmedMovement());
    }
}
