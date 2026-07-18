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

    public function test_deadline_is_pickup_minus_travel_and_lead_minutes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-27 08:00:00'));

        $service = new DriverMovementConfirmService();
        [$schedule, $booking] = $this->fixtures(minutesToPickup: 180, distanceKm: 30.0);

        $deadline = $service->computeDeadline($schedule, $booking, now());
        $travelMinutes = $service->travelMinutesForBooking($schedule, $booking);
        $expected = now()->addMinutes(180)->subMinutes($travelMinutes + DriverMovementConfirmService::MOVEMENT_CONFIRM_LEAD_MINUTES);

        $this->assertTrue($deadline->equalTo($expected));
    }

    public function test_deadline_not_before_now_plus_buffer(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-27 11:50:00'));

        $service = new DriverMovementConfirmService();
        [$schedule, $booking] = $this->fixtures(minutesToPickup: 10, distanceKm: 5.0);

        $deadline = $service->computeDeadline($schedule, $booking, now());

        $this->assertTrue($deadline->greaterThanOrEqualTo(now()->addMinutes(DriverMovementConfirmService::MIN_DEADLINE_BUFFER_MINUTES)));
    }

    public function test_confirm_action_available_within_one_hour_before_pickup(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-27 08:00:00'));

        $service = new DriverMovementConfirmService();

        [$farSchedule, $farBooking] = $this->assignedFixtures(minutesToPickup: 180);
        $this->assertFalse($service->isConfirmActionAvailable($farSchedule, $farBooking));

        [$nearSchedule, $nearBooking] = $this->assignedFixtures(minutesToPickup: 45);
        $this->assertTrue($service->isConfirmActionAvailable($nearSchedule, $nearBooking));
    }

    /** @return array{0: Schedule, 1: Booking} */
    private function assignedFixtures(int $minutesToPickup): array
    {
        [$schedule, $booking] = $this->fixtures($minutesToPickup, 10.0);
        $schedule->fill([
            'driver_stage'                 => Schedule::DRIVER_STAGE_ASSIGNED,
            'driver_movement_confirmed_at' => null,
        ]);
        $schedule->setRelation('bookings', collect([$booking]));
        $schedule->setRelation('tripSettlement', null);
        $booking->setRelation('schedule', $schedule);

        return [$schedule, $booking];
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
