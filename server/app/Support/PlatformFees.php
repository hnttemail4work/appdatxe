<?php

namespace App\Support;

use App\Models\PlatformSetting;

/** Tỷ lệ phí / hoa hồng — admin cấu hình qua platform_settings. */
class PlatformFees
{
    public static function referralCommissionPercent(): float
    {
        $setting = PlatformSetting::getValue('referral_commission_percentage', ['value' => 8]);

        return (float) ($setting['value'] ?? 8);
    }

    public static function appCommissionPercent(): float
    {
        $setting = PlatformSetting::getValue('app_commission_percentage', null);

        if ($setting !== null) {
            return (float) ($setting['value'] ?? 2);
        }

        $legacy = PlatformSetting::getValue('commission_percentage', ['value' => 2]);

        return (float) ($legacy['value'] ?? 2);
    }

    public static function roundTripDiscountPercent(): float
    {
        $setting = PlatformSetting::getValue('round_trip_discount_percentage', ['value' => 15]);

        return (float) ($setting['value'] ?? 15);
    }

    public static function roundTripMultiplier(): float
    {
        $discount = self::roundTripDiscountPercent();

        return round(2 * (1 - $discount / 100), 4);
    }

    public static function referralCommissionRate(): float
    {
        return self::referralCommissionPercent() / 100;
    }
}
