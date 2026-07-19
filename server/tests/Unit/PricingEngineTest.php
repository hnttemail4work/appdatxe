<?php

namespace Tests\Unit;

use App\Services\PricingEngine;
use App\Services\TripPricingService;
use App\Support\PlatformFees;
use App\Support\PriceQuote;
use Tests\TestCase;

class PricingEngineTest extends TestCase
{
    private PricingEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = app(PricingEngine::class);
    }

    public function test_km_under_100_sedan(): void
    {
        $q = $this->engine->quoteForRoute('TP.HCM', 'Đồng Nai', 80, 'sedan_4', 4);
        $this->assertSame(80, $q->distanceKm);
        $this->assertSame(1_040_000, $q->priceBase);
        $this->assertSame(1_040_000, $q->priceSubtotal);
    }

    public function test_km_over_100(): void
    {
        $q = $this->engine->quoteForRoute('TP.HCM', 'Cần Thơ', 165, 'sedan_4', 4);
        $this->assertSame(1_950_000, $q->priceBase);
    }

    public function test_vehicle_multiplier_rounds(): void
    {
        $q = $this->engine->quoteForRoute('TP.HCM', 'Đồng Nai', 80, 'suv_7', 7);
        $this->assertEqualsWithDelta(1.015, $q->vehicleMultiplier, 0.0001);
        $this->assertSame(1_060_000, $q->priceVehicle);
    }

    public function test_intra_province_flat_skips_multiplier(): void
    {
        $q = $this->engine->quoteForRoute('Đồng Nai', 'Đồng Nai', 2, 'suv_7', 7);
        $this->assertTrue($q->usedIntraFlat);
        $this->assertSame(30_000, $q->priceSubtotal);
        $this->assertSame(1.0, $q->vehicleMultiplier);
    }

    public function test_referral_applies_on_pre_discount_then_rounds(): void
    {
        $base = new PriceQuote(
            distanceKm: 80,
            priceBase: 1_040_000,
            usedIntraFlat: false,
            vehicleTypeKey: 'sedan_4',
            vehicleMultiplier: 1.0,
            priceVehicle: 1_040_000,
            surchargeHoliday: 104_000,
            surchargePeak: 20_000,
            surchargeRain: 15_000,
            tollAmount: 50_000,
            priceSubtotal: 1_229_000,
            totalPrice: 1_229_000,
        );

        $withRef = $base->withReferral(2);
        $this->assertSame(2.0, $withRef->referralDiscountPercent);
        $this->assertSame(1_200_000, $withRef->totalPrice);
        $this->assertSame(29_000, $withRef->referralDiscountAmount);
        $this->assertSame(1_229_000, $withRef->priceSubtotal);
    }

    public function test_booking_columns_include_snapshot(): void
    {
        $q = $this->engine->quoteForRoute('TP.HCM', 'Đồng Nai', 50, 'sedan_4', 4)->withReferral(0);
        $cols = $q->toBookingColumns();
        $this->assertSame(650_000, $cols['total_price']);
        $this->assertSame(650_000, $cols['price_subtotal']);
        $this->assertIsArray($cols['price_breakdown']);
        $this->assertSame(50, $cols['distance_km']);
    }

    public function test_trip_pricing_service_flat_constant_path(): void
    {
        $pricing = app(TripPricingService::class);
        $this->assertSame(
            PlatformFees::roundDisplayPrice(30_000),
            $pricing->wholeCarPriceForRoute('Đồng Nai', 'Đồng Nai', 3, 7, 'suv_7'),
        );
    }
}
