<?php

namespace Tests\Unit;

use App\Services\TripPricingService;
use App\Support\DeparturePlan;
use Tests\TestCase;

class DeparturePlanPricingTest extends TestCase
{
    public function test_departure_plan_multipliers(): void
    {
        $this->assertSame(1.5, DeparturePlan::priceMultiplier(DeparturePlan::TODAY));
        $this->assertSame(2.0, DeparturePlan::priceMultiplier(DeparturePlan::TOMORROW));
        $this->assertSame(1.0, DeparturePlan::priceMultiplier(DeparturePlan::LATER));
    }

    public function test_price_with_departure_plan_rounds_to_thousand(): void
    {
        $pricing = app(TripPricingService::class);

        $this->assertSame(150_000, $pricing->priceWithDeparturePlan(100_000, DeparturePlan::TODAY));
        $this->assertSame(200_000, $pricing->priceWithDeparturePlan(100_000, DeparturePlan::TOMORROW));
        $this->assertSame(100_000, $pricing->priceWithDeparturePlan(100_000, DeparturePlan::LATER));
    }
}
