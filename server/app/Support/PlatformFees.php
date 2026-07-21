<?php

namespace App\Support;

/**
 * Tỷ lệ phí / hoa hồng / đơn giá — đọc từ PricingConfig (admin).
 */
class PlatformFees
{
    public static function appCommissionPercent(): float
    {
        return PricingConfig::appCommissionPercent();
    }

    public static function referralCommissionFirstPercent(): float
    {
        return PricingConfig::referralCommissionFirstPercent();
    }

    public static function kmRateUnder100(): int
    {
        return PricingConfig::kmRateUnder100();
    }

    public static function kmRateOver100(): int
    {
        return PricingConfig::kmRateOver100();
    }

    /** Giá khách — làm tròn theo đơn vị cấu hình (mặc định chục nghìn). */
    public static function roundDisplayPrice(float|int $amount): int
    {
        if ($amount <= 0) {
            return 0;
        }

        $unit = PricingConfig::roundingUnit();

        return (int) (round((float) $amount / $unit) * $unit);
    }

    /** Tiền cả xe theo km — ≤100 km / phần vượt. */
    public static function wholeCarBaseFromDistanceKm(float $distanceKm): int
    {
        $km = max(0.0, $distanceKm);
        if ($km <= 0) {
            return 0;
        }

        $underRate = self::kmRateUnder100();
        $overRate = self::kmRateOver100();

        if ($km <= 100) {
            return (int) round($km * $underRate);
        }

        return (int) round((100 * $underRate) + (($km - 100) * $overRate));
    }

    /** @deprecated Dùng {@see roundDisplayPrice()} */
    public static function roundDownPrice(float|int $amount): int
    {
        return self::roundDisplayPrice($amount);
    }
}
