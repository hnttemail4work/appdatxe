<?php

namespace App\Support;

use App\Models\PlatformSetting;

/** Đọc cấu hình tính giá từ platform_settings (có fallback). */
class PricingConfig
{
    public static function kmRateUnder100(): int
    {
        return max(0, self::intSetting('pricing_km_rate_under_100', 13000));
    }

    public static function kmRateOver100(): int
    {
        return max(0, self::intSetting('pricing_km_rate_over_100', 10000));
    }

    public static function intraFlatMaxKm(): int
    {
        return max(0, self::intSetting('pricing_intra_flat_max_km', 3));
    }

    public static function intraFlatPrice(): int
    {
        return max(0, self::intSetting('pricing_intra_flat_price', 30000));
    }

    public static function roundingUnit(): int
    {
        $unit = self::intSetting('pricing_rounding_unit', 10000);

        return $unit > 0 ? $unit : 10000;
    }

    public static function appCommissionPercent(): float
    {
        try {
            $setting = PlatformSetting::getValue('app_commission_percentage', null);
            if ($setting !== null) {
                return max(0.0, (float) ($setting['value'] ?? 2));
            }

            $legacy = PlatformSetting::getValue('commission_percentage', ['value' => 2]);

            return max(0.0, (float) ($legacy['value'] ?? 2));
        } catch (\Throwable) {
            return 2.0;
        }
    }

    public static function referralCommissionFirstPercent(): float
    {
        return max(0.0, (float) (self::raw('referral_commission_first_percentage', ['value' => 8])['value'] ?? 8));
    }

    public static function rainSurchargeEnabled(): bool
    {
        $raw = PlatformSetting::getValue('rain_surcharge_enabled', ['value' => false]);

        return (bool) ($raw['value'] ?? false);
    }

    public static function setRainSurchargeEnabled(bool $enabled): void
    {
        PlatformSetting::setValue('rain_surcharge_enabled', ['value' => $enabled], 'finance');
    }

    /** @return array<string, mixed> */
    public static function forAdmin(): array
    {
        return [
            'km_rate_under_100'              => self::kmRateUnder100(),
            'km_rate_over_100'               => self::kmRateOver100(),
            'intra_flat_max_km'              => self::intraFlatMaxKm(),
            'intra_flat_price'               => self::intraFlatPrice(),
            'rounding_unit'                  => self::roundingUnit(),
            'app_commission'                 => self::appCommissionPercent(),
            'referral_commission_first'      => self::referralCommissionFirstPercent(),
            'rain_surcharge_enabled'         => self::rainSurchargeEnabled(),
        ];
    }

    private static function intSetting(string $key, int $default): int
    {
        $raw = self::raw($key, ['value' => $default]);

        return (int) ($raw['value'] ?? $default);
    }

    /** @return array<string, mixed> */
    private static function raw(string $key, array $default): array
    {
        try {
            $value = PlatformSetting::getValue($key, $default);

            return is_array($value) ? $value : $default;
        } catch (\Throwable) {
            return $default;
        }
    }
}
