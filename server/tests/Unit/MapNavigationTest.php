<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Models\Schedule;
use App\Support\MapNavigation;
use Tests\TestCase;

class MapNavigationTest extends TestCase
{
    public function test_directions_url_uses_geo_coordinates_when_available(): void
    {
        $url = MapNavigation::directionsUrl(10.7769, 106.7009, null);

        $this->assertNotNull($url);
        $this->assertStringStartsWith('geo:', $url);
        $this->assertStringContainsString('10.776900,106.700900', $url);
    }

    public function test_directions_url_falls_back_to_address(): void
    {
        $url = MapNavigation::directionsUrl(null, null, '123 Nguyễn Huệ, Q1');

        $this->assertNotNull($url);
        $this->assertStringStartsWith('geo:0,0?q=', $url);
        $this->assertStringContainsString(rawurlencode('123 Nguyễn Huệ, Q1'), $url);
    }

    public function test_driver_target_switches_between_pickup_and_dropoff(): void
    {
        $booking = new Booking([
            'pickup_lat'     => 10.77,
            'pickup_lng'     => 106.70,
            'pickup_detail'  => 'Điểm đón',
            'dropoff_lat'    => 10.80,
            'dropoff_lng'    => 106.72,
            'dropoff_detail' => 'Điểm trả',
        ]);

        $schedule = new Schedule([
            'driver_id'    => 1,
            'driver_stage' => Schedule::DRIVER_STAGE_ASSIGNED,
        ]);
        $pickup = MapNavigation::driverTargetForSchedule($schedule, $booking);
        $this->assertSame('Chỉ đường', $pickup['label'] ?? null);
        $this->assertStringContainsString('10.770000,106.700000', $pickup['url'] ?? '');

        $schedule->driver_stage = Schedule::DRIVER_STAGE_RUNNING;
        $dropoff = MapNavigation::driverTargetForSchedule($schedule, $booking);
        $this->assertSame('Chỉ đường', $dropoff['label'] ?? null);
        $this->assertStringContainsString('10.800000,106.720000', $dropoff['url'] ?? '');
    }
}
