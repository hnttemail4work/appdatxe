<?php

namespace Tests\Unit;

use App\Models\DriverTripRequest;
use App\Models\Schedule;
use App\Support\DriverWaitProgress;
use Carbon\Carbon;
use Tests\TestCase;

class DriverWaitProgressTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_trip_request_wait_progress_counts_down_to_expires_at(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-07 12:00:00'));

        $request = new DriverTripRequest([
            'status'     => 'pending',
            'created_at' => now(),
            'expires_at' => now()->addMinute(),
        ]);

        $progress = DriverWaitProgress::forTripRequest($request);

        $this->assertNotNull($progress);
        $this->assertSame('trip_accept', $progress['kind']);
        $this->assertSame(
            $request->expires_at->toIso8601String(),
            Carbon::parse($progress['deadline_at'])->toIso8601String(),
        );
    }

    public function test_schedule_movement_confirm_wait_progress_when_deadline_future(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-07 12:00:00'));

        $schedule = new Schedule([
            'driver_id'                    => 42,
            'driver_stage'                 => Schedule::DRIVER_STAGE_ASSIGNED,
            'driver_assigned_at'           => now(),
            'driver_movement_deadline_at'  => now()->addMinutes(3),
            'driver_movement_confirmed_at' => null,
        ]);
        $schedule->setRelation('bookings', collect());
        $schedule->setRelation('tripSettlement', null);

        $progress = DriverWaitProgress::forSchedule($schedule);

        $this->assertNotNull($progress);
        $this->assertSame('movement_confirm', $progress['kind']);
        $this->assertFalse($progress['indeterminate']);
    }
}
