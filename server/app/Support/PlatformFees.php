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

    /** % phụ thu trên giá một chiều — thời điểm về trong ngày. */
    public static function departurePlanTodaySurchargePercent(): float
    {
        $setting = self::readFinanceSetting('departure_plan_surcharge_today_percentage', ['value' => 50]);

        return max((float) ($setting['value'] ?? 50), 0);
    }

    /** % phụ thu trên giá một chiều — về ngày mai. */
    public static function departurePlanTomorrowSurchargePercent(): float
    {
        $setting = self::readFinanceSetting('departure_plan_surcharge_tomorrow_percentage', ['value' => 100]);

        return max((float) ($setting['value'] ?? 100), 0);
    }

    /** % phụ thu mỗi ngày chờ về — chọn trên 2 ngày (VD: 30 → 3 ngày = +90% giá một chiều). */
    public static function departurePlanLaterPercentPerDay(): float
    {
        $setting = self::readFinanceSetting('departure_plan_surcharge_later_per_day_percentage', ['value' => 30]);

        return max((float) ($setting['value'] ?? 30), 0);
    }

    public static function departurePlanLaterPriceMultiplier(int $days): float
    {
        $days = \App\Support\DeparturePlan::normalizeLaterReturnDays($days);

        return round(1 + ($days * self::departurePlanLaterPercentPerDay() / 100), 4);
    }

    public static function departurePlanSurchargePercent(string $plan): float
    {
        return match (\App\Support\DeparturePlan::normalize($plan)) {
            \App\Support\DeparturePlan::TODAY    => self::departurePlanTodaySurchargePercent(),
            \App\Support\DeparturePlan::TOMORROW => self::departurePlanTomorrowSurchargePercent(),
            default                              => 0.0,
        };
    }

    public static function departurePlanPriceMultiplier(string $plan): float
    {
        return round(1 + self::departurePlanSurchargePercent($plan) / 100, 4);
    }

    public static function roundTripMultiplier(): float
    {
        $discount = self::roundTripDiscountPercent();

        return round(2 * (1 - $discount / 100), 4);
    }

    /** Giá khách — làm tròn đến chục nghìn gần nhất (1.096.200 → 1.100.000; 1.254.567 → 1.250.000). */
    public static function roundDisplayPrice(float|int $amount): int
    {
        if ($amount <= 0) {
            return 0;
        }

        return (int) (round((float) $amount / 10_000) * 10_000);
    }

    /** Tiền cả xe theo km — cấu hình admin (≤100 km / phần vượt). */
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
    public static function roundDownToHundred(float|int $amount): int
    {
        return self::roundDisplayPrice($amount);
    }

    /** Làm tròn lên bội 10.000đ — giữ cho tương thích nội bộ cũ. */
    public static function roundUpToThousand(float|int $amount): int
    {
        if ($amount <= 0) {
            return 0;
        }

        $unit = 10_000;

        return (int) (ceil($amount / $unit) * $unit);
    }

    /** @deprecated Dùng {@see roundDisplayPrice()} */
    public static function roundDownPrice(float|int $amount): int
    {
        return self::roundDisplayPrice($amount);
    }

    /** @return array<string, mixed> */
    private static function readFinanceSetting(string $key, array $default): array
    {
        try {
            $value = PlatformSetting::getValue($key, $default);

            return is_array($value) ? $value : $default;
        } catch (\Throwable) {
            return $default;
        }
    }
}
