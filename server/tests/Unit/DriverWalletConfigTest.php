<?php

namespace Tests\Unit;

use App\Support\DriverWalletConfig;
use PHPUnit\Framework\TestCase;

class DriverWalletConfigTest extends TestCase
{
    public function test_revenue_threshold_is_200k(): void
    {
        $this->assertSame(200_000, DriverWalletConfig::REVENUE_THRESHOLD);
        $this->assertSame('200k', DriverWalletConfig::revenueThresholdShortLabel());
    }

    public function test_min_balance_is_100k_after_gate(): void
    {
        $this->assertSame(100_000, DriverWalletConfig::MIN_BALANCE);
    }

    public function test_min_deposit_is_100k(): void
    {
        $this->assertSame(100_000, DriverWalletConfig::MIN_DEPOSIT);
        $this->assertSame(100_000, DriverWalletConfig::ACTIVATION_DEPOSIT);
    }
}
