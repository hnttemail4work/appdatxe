<?php

namespace Tests\Unit;

use App\Models\Vehicle;
use App\Services\TripPricingService;
use App\Support\ProvinceCenters;
use Tests\TestCase;

class TripPricingServiceTest extends TestCase
{
    private TripPricingService $pricing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pricing = app(TripPricingService::class);
    }

    public function test_intra_province_up_to_three_km_is_flat_thirty_thousand(): void
    {
        foreach ([1, 2, 3] as $distanceKm) {
            $this->assertSame(
                TripPricingService::INTRA_PROVINCE_FLAT_PRICE,
                $this->pricing->wholeCarPriceForRoute('Đồng Nai', 'Đồng Nai', $distanceKm, 7),
                'Expected flat price for ' . $distanceKm . ' km',
            );
        }
    }

    public function test_intra_province_short_trip_quote_uses_flat_price(): void
    {
        $center = ProvinceCenters::forProvince('Đồng Nai');
        $this->assertNotNull($center);

        $vehicle = new Vehicle(['capacity' => 7]);
        $quote = $this->pricing->quoteForVehicle(
            $vehicle,
            'Đồng Nai',
            'Đồng Nai',
            $center['lat'],
            $center['lng'],
            $center['lat'] + 0.018,
            $center['lng'],
        );

        $this->assertLessThanOrEqual(TripPricingService::INTRA_PROVINCE_FLAT_MAX_KM, $quote->distanceKm);
        $this->assertTrue($quote->usedIntraFlat);
        $this->assertSame(TripPricingService::INTRA_PROVINCE_FLAT_PRICE, $quote->priceSubtotal);
    }

    public function test_inter_province_route_is_not_intra_province(): void
    {
        $this->assertFalse($this->pricing->isIntraProvinceRoute('TP.HCM', 'Bình Dương'));
        $this->assertTrue($this->pricing->isIntraProvinceRoute('Đồng Nai', 'Đồng Nai'));
    }
}
