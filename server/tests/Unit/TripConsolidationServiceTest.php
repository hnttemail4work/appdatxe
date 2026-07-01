<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\Schedule;
use App\Services\TripConsolidationService;
use Carbon\Carbon;
use Tests\TestCase;

class TripConsolidationServiceTest extends TestCase
{
    public function test_pool_window_constant_is_forty_five_minutes(): void
    {
        $this->assertSame(45, TripConsolidationService::POOL_WINDOW_MINUTES);
    }

    public function test_accepts_candidate_within_pool_window(): void
    {
        $service = new TripConsolidationService();
        $base = now()->addDays(2)->setTime(8, 0, 0);
        $schedule = $this->mockOpenSharedSchedule($base, 1, 7);

        $this->assertTrue($service->canAcceptSharedBooking(
            $schedule,
            $base->copy()->addMinutes(35),
            1,
        ));
    }

    public function test_rejects_candidate_outside_pool_window(): void
    {
        $service = new TripConsolidationService();
        $base = now()->addDays(2)->setTime(8, 0, 0);
        $schedule = $this->mockOpenSharedSchedule($base, 1, 7);

        $this->assertFalse($service->canAcceptSharedBooking(
            $schedule,
            $base->copy()->addMinutes(90),
            1,
        ));
    }

    public function test_rejects_when_not_enough_seats(): void
    {
        $service = new TripConsolidationService();
        $base = now()->addDays(2)->setTime(8, 0, 0);
        $schedule = $this->mockOpenSharedSchedule($base, 7, 7);

        $this->assertFalse($service->canAcceptSharedBooking(
            $schedule,
            $base->copy()->addMinutes(20),
            1,
        ));
    }

    private function mockOpenSharedSchedule(Carbon $departure, int $booked, int $capacity): Schedule
    {
        $schedule = $this->getMockBuilder(Schedule::class)
            ->onlyMethods(['bookedSeatsCount', 'capacity', 'resolvedDriverStage'])
            ->getMock();

        $schedule->method('bookedSeatsCount')->willReturn($booked);
        $schedule->method('capacity')->willReturn($capacity);
        $schedule->method('resolvedDriverStage')->willReturn(Schedule::DRIVER_STAGE_ASSIGNED);

        $schedule->status = 'scheduled';
        $schedule->driver_id = null;
        $schedule->departure_time = $departure->copy();

        $schedule->setRelation('bookings', collect([
            new Booking([
                'booking_status' => 'confirmed',
                'booking_mode'   => 'shared',
                'trip_status'    => 'confirmed',
            ]),
        ]));

        $schedule->setRelation('vehicle', (object) ['capacity' => $capacity]);

        return $schedule;
    }
}
