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
        $this->assertSame('Điều hướng', $pickup['label'] ?? null);
        $this->assertStringContainsString('10.770000,106.700000', $pickup['url'] ?? '');
        $this->assertTrue($pickup['use_current_origin'] ?? false);
        $this->assertStringContainsString('google.com/maps/dir', $pickup['google_url'] ?? '');
        $this->assertStringContainsString('10.770000%2C106.700000', $pickup['google_url'] ?? '');

        $schedule->driver_stage = Schedule::DRIVER_STAGE_RUNNING;
        $dropoff = MapNavigation::driverTargetForSchedule($schedule, $booking);
        $this->assertSame('Điều hướng', $dropoff['label'] ?? null);
        $this->assertStringContainsString('10.800000,106.720000', $dropoff['url'] ?? '');
        $this->assertFalse($dropoff['use_current_origin'] ?? true);
        $this->assertStringContainsString('10.770000%2C106.700000', $dropoff['google_url'] ?? '');
        $this->assertStringContainsString('10.800000%2C106.720000', $dropoff['google_url'] ?? '');
    }

    public function test_google_directions_url_with_origin_and_destination(): void
    {
        $url = MapNavigation::googleDirectionsUrl(10.77, 106.70, 10.80, 106.72);

        $this->assertNotNull($url);
        $this->assertStringStartsWith('https://www.google.com/maps/dir/?', $url);
        $this->assertStringContainsString('origin=10.770000%2C106.700000', $url);
        $this->assertStringContainsString('destination=10.800000%2C106.720000', $url);
        $this->assertStringContainsString('travelmode=driving', $url);
    }

    public function test_pickup_target_falls_back_to_province_center_when_coords_missing(): void
    {
        $booking = new Booking([
            'pickup_detail' => 'Gần bến xe',
        ]);
        $schedule = new Schedule([]);
        $schedule->setRelation('route', new \App\Models\TripRoute([
            'departure'   => 'TP.HCM',
            'destination' => 'Vũng Tàu',
        ]));

        $nav = MapNavigation::driverPickupTarget($booking, $schedule);

        $this->assertNotNull($nav);
        $this->assertNotNull($nav['dest_lat'] ?? null);
        $this->assertNotNull($nav['dest_lng'] ?? null);
        $this->assertStringContainsString('google.com/maps/dir', $nav['google_url'] ?? '');
    }
}
