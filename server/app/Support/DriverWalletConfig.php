<?php

namespace App\Support;

/** Quy tắc ví tài xế & kết chuyến. */
class DriverWalletConfig
{
    public const REVENUE_THRESHOLD = 500_000;

    public const MIN_BALANCE = 100_000;

    public const FEE_DEADLINE_HOURS = 12;

    /** Mã kết chuyến quản lý cấp — hiệu lực 24 giờ. */
    public const SETTLEMENT_CODE_TTL_HOURS = 24;

    public static function commissionRate(): float
    {
        return \App\Support\PlatformFees::appCommissionPercent();
    }

    public static function platformFee(float $revenue): int
    {
        return (int) round($revenue * self::commissionRate() / 100, 0);
    }
}
