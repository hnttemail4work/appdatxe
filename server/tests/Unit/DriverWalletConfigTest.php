<?php

namespace Tests\Unit;

use App\Support\DriverWalletConfig;
use PHPUnit\Framework\TestCase;

class DriverWalletConfigTest extends TestCase
{
    public function test_revenue_threshold_is_100k(): void
    {
        $this->assertSame(100_000, DriverWalletConfig::REVENUE_THRESHOLD);
        $this->assertSame('100k', DriverWalletConfig::revenueThresholdShortLabel());
    }

    public function test_min_balance_matches_threshold(): void
    {
        $this->assertSame(100_000, DriverWalletConfig::MIN_BALANCE);
    }
}
