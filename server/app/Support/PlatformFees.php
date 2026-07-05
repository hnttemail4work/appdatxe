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

    /** % hoa hồng người giới thiệu — mã admin tạo từ hệ thống. */
    public static function referralCommissionFirstPercent(): float
    {
        $setting = PlatformSetting::getValue('referral_commission_first_percentage', ['value' => 8]);

        return (float) ($setting['value'] ?? 8);
    }

    /** % giảm giá khách khi quét mã QR từ chuyến hoàn tất — không tính vào doanh thu admin. */
    public static function bookingQrDiscountPercent(): float
    {
        $setting = PlatformSetting::getValue('referral_commission_repeat_percentage', ['value' => 2]);

        return (float) ($setting['value'] ?? 2);
    }

    /** @deprecated Dùng {@see bookingQrDiscountPercent()} hoặc {@see referralCommissionFirstPercent()}. */
    public static function referralCommissionRepeatPercent(): float
    {
        return self::bookingQrDiscountPercent();
    }

    /**
     * @deprecated Không còn phân tầng lần đầu / lần sau.
     */
    public static function referralCommissionPercentForOccurrence(int $occurrence): float
    {
        return $occurrence <= 1
            ? self::referralCommissionFirstPercent()
            : self::bookingQrDiscountPercent();
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

    /** Bội số làm tròn giá hiển thị / tính vé (10.000đ — không có lẻ hàng nghìn). */
    public const PRICE_ROUND_UNIT = 10_000;

    /** Làm tròn lên bội {@see PRICE_ROUND_UNIT} (vd. 138.001 → 140.000). */
    public static function roundUpToThousand(float|int $amount): int
    {
        if ($amount <= 0) {
            return 0;
        }

        $unit = self::PRICE_ROUND_UNIT;

        return (int) (ceil($amount / $unit) * $unit);
    }

    /** Làm tròn xuống bội {@see PRICE_ROUND_UNIT} — dùng sau giảm giá GT. */
    public static function roundDownPrice(float|int $amount): int
    {
        if ($amount <= 0) {
            return 0;
        }

        $unit = self::PRICE_ROUND_UNIT;

        return (int) (floor($amount / $unit) * $unit);
    }
}
