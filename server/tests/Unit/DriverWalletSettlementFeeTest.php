<?php

namespace Tests\Unit;

use App\Support\DriverWalletConfig;
use Tests\TestCase;

class DriverWalletSettlementFeeTest extends TestCase
{
    public function test_under_threshold_when_cumulative_stays_below_200k(): void
    {
        $this->assertSame(
            'under_threshold',
            DriverWalletConfig::resolveSettlementCategory(80_000, 50_000),
        );
        $this->assertSame(
            'under_threshold',
            DriverWalletConfig::resolveSettlementCategory(50_000, 0),
        );
    }

    public function test_first_over_threshold_when_crossing_200k_cumulative(): void
    {
        $this->assertSame(
            'first_over_threshold',
            DriverWalletConfig::resolveSettlementCategory(150_000, 100_000),
        );
        $this->assertSame(
            'first_over_threshold',
            DriverWalletConfig::resolveSettlementCategory(250_000, 0),
        );
    }

    public function test_over_threshold_when_cumulative_already_at_200k(): void
    {
        $this->assertSame(
            'over_threshold',
            DriverWalletConfig::resolveSettlementCategory(30_000, 250_000),
        );
        $this->assertSame(
            'over_threshold',
            DriverWalletConfig::resolveSettlementCategory(80_000, 200_000),
        );
    }

    public function test_fee_not_deducted_for_under_threshold(): void
    {
        $this->assertFalse(DriverWalletConfig::shouldDeductPlatformFee('under_threshold', true));
    }

    public function test_fee_not_deducted_for_first_over_threshold(): void
    {
        $this->assertFalse(DriverWalletConfig::shouldDeductPlatformFee('first_over_threshold', true));
    }

    public function test_fee_not_deducted_when_wallet_not_activated(): void
    {
        $this->assertFalse(DriverWalletConfig::shouldDeductPlatformFee('over_threshold', false));
    }

    public function test_fee_deducted_for_activated_wallet_on_over_threshold(): void
    {
        $this->assertTrue(DriverWalletConfig::shouldDeductPlatformFee('over_threshold', true));
    }
}
