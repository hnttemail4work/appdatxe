<?php

namespace App\Support;

use App\Models\PlatformSetting;

/** Tỷ lệ phí / hoa hồng — admin cấu hình qua platform_settings. */
class PlatformFees
{
    public static function appCommissionPercent(): float
    {
        $setting = PlatformSetting::getValue('app_commission_percentage', null);

        if ($setting !== null) {
            return (float) ($setting['value'] ?? 2);
        }

        $legacy = PlatformSetting::getValue('commission_percentage', ['value' => 2]);

        return (float) ($legacy['value'] ?? 2);
    }

    /** % hoa hồng người giới thiệu — mã admin (QR người GT). */
    public static function referralCommissionFirstPercent(): float
    {
        $setting = PlatformSetting::getValue('referral_commission_first_percentage', ['value' => 8]);

        return (float) ($setting['value'] ?? 8);
    }

    /** % giảm giá khách + hoa hồng — mã phát sinh từ khách đặt chuyến thành công. */
    public static function referralCommissionRepeatPercent(): float
    {
        $setting = PlatformSetting::getValue('referral_commission_repeat_percentage', ['value' => 2]);

        return (float) ($setting['value'] ?? 2);
    }

    /**
     * @param  int  $occurrence  1 = lần giới thiệu đầu, >= 2 = các lần sau
     */
    public static function referralCommissionPercentForOccurrence(int $occurrence): float
    {
        return $occurrence <= 1
            ? self::referralCommissionFirstPercent()
            : self::referralCommissionRepeatPercent();
    }

    /** @deprecated Dùng referralCommissionFirstPercent() */
    public static function referralCommissionPercent(): float
    {
        return self::referralCommissionFirstPercent();
    }

    public static function roundTripDiscountPercent(): float
    {
        $setting = PlatformSetting::getValue('round_trip_discount_percentage', ['value' => 15]);

        return (float) ($setting['value'] ?? 15);
    }

    public static function kmRateUnder100(): int
    {
        $setting = PlatformSetting::getValue('pricing_km_rate_under_100', ['value' => 13000]);

        return max((int) ($setting['value'] ?? 13000), 0);
    }

    public static function kmRateOver100(): int
    {
        $setting = PlatformSetting::getValue('pricing_km_rate_over_100', ['value' => 10000]);

        return max((int) ($setting['value'] ?? 10000), 0);
    }

    public static function roundTripMultiplier(): float
    {
        $discount = self::roundTripDiscountPercent();

        return round(2 * (1 - $discount / 100), 4);
    }
}
