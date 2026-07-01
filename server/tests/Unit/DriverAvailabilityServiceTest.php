<?php

namespace Tests\Unit;

use App\Models\Schedule;
use App\Services\DriverAvailabilityService;
use PHPUnit\Framework\TestCase;

class DriverAvailabilityServiceTest extends TestCase
{
    public function test_exposes_trip_scheduling_helpers(): void
    {
        $service = new DriverAvailabilityService();
        $this->assertTrue(method_exists($service, 'hasTripTimeConflict'));
        $this->assertTrue(method_exists($service, 'hasOtherActiveScheduleConflict'));
        $this->assertTrue(method_exists($service, 'activeTripCount'));
    }

    public function test_has_other_active_schedule_conflict_when_another_trip_is_open(): void
    {
        $service = $this->createPartialMock(DriverAvailabilityService::class, ['activeSchedulesForDriver']);

        $active = new Schedule(['route_id' => 5]);
        $active->id = 10;
        $service->method('activeSchedulesForDriver')->willReturn(collect([$active]));

        $this->assertTrue($service->hasOtherActiveScheduleConflict(1, 20));
        $this->assertFalse($service->hasOtherActiveScheduleConflict(1, 10));
    }

    public function test_has_other_active_schedule_conflict_without_active_trips(): void
    {
        $service = $this->createPartialMock(DriverAvailabilityService::class, ['activeSchedulesForDriver']);
        $service->method('activeSchedulesForDriver')->willReturn(collect());

        $this->assertFalse($service->hasOtherActiveScheduleConflict(1, 99));
    }
}
