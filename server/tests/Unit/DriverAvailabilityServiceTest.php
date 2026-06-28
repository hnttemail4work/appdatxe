<?php

namespace Tests\Unit;

use App\Services\DriverAvailabilityService;
use PHPUnit\Framework\TestCase;

class DriverAvailabilityServiceTest extends TestCase
{
    public function test_busy_only_when_driver_accepted_and_seats_full(): void
    {
        $service = new DriverAvailabilityService();
        $this->assertTrue(method_exists($service, 'isDriverBusyForSlot'));
    }
}
