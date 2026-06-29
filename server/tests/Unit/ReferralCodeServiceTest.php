<?php

namespace Tests\Unit;

use App\Services\ReferralCodeService;
use PHPUnit\Framework\TestCase;

class ReferralCodeServiceTest extends TestCase
{
    public function test_apply_discount_floors_to_thousand(): void
    {
        $service = new ReferralCodeService();

        $this->assertSame(253_000.0, $service->applyDiscount(282_000, 10));
        $this->assertSame(253_000.0, $service->applyDiscount(282_133.33, 10));
        $this->assertSame(285_000.0, $service->applyDiscount(300_000, 5));
    }

    public function test_apply_discount_without_percent_unchanged(): void
    {
        $service = new ReferralCodeService();

        $this->assertSame(253_920.0, $service->applyDiscount(253_920, 0));
    }
}
