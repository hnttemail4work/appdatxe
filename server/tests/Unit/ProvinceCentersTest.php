<?php

namespace Tests\Unit;

use App\Support\ProvinceCenters;
use PHPUnit\Framework\TestCase;

class ProvinceCentersTest extends TestCase
{
    public function test_distance_same_point_is_near_zero(): void
    {
        $distance = ProvinceCenters::distanceKm(10.7769, 106.7009, 10.7769, 106.7009);

        $this->assertLessThan(0.01, $distance);
    }

    public function test_for_province_returns_known_center(): void
    {
        $coords = ProvinceCenters::forProvince('TP.HCM');

        $this->assertNotNull($coords);
        $this->assertEqualsWithDelta(10.7769, $coords['lat'], 0.0001);
        $this->assertEqualsWithDelta(106.7009, $coords['lng'], 0.0001);
    }

    public function test_hcm_to_binh_duong_is_reasonable_distance(): void
    {
        $hcm = ProvinceCenters::forProvince('TP.HCM');
        $bd = ProvinceCenters::forProvince('Bình Dương');

        $this->assertNotNull($hcm);
        $this->assertNotNull($bd);

        $km = ProvinceCenters::distanceKm($hcm['lat'], $hcm['lng'], $bd['lat'], $bd['lng']);

        $this->assertGreaterThan(10, $km);
        $this->assertLessThan(80, $km);
    }
}
