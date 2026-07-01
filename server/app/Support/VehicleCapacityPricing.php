<?php

namespace App\Support;

use App\Models\PlatformSetting;

/** Hệ số % giá cả xe theo loại chỗ — 4 chỗ làm chuẩn 100%. */
class VehicleCapacityPricing
{
    public const BASELINE_CAPACITY = 4;

    public const DEFAULT_STEP_PERCENT = 1.5;

    public const SETTING_KEY = 'vehicle_capacity_whole_car_pricing';

    public static function stepPercent(): float
    {
        $data = self::load();

        return (float) ($data['step_percent'] ?? self::DEFAULT_STEP_PERCENT);
    }

    public static function tierIndex(int $capacity): int
    {
        $capIdx = array_search($capacity, VehicleCapacityOptions::enabled(), true);
        $baseIdx = array_search(self::BASELINE_CAPACITY, VehicleCapacityOptions::enabled(), true);

        if ($capIdx === false) {
            return 0;
        }

        return max(0, $capIdx - ($baseIdx !== false ? $baseIdx : 0));
    }

    public static function defaultPercentForCapacity(int $capacity): float
    {
        return round(100.0 + self::tierIndex($capacity) * self::stepPercent(), 2);
    }

    public static function percentForCapacity(int $capacity): float
    {
        $data = self::load();
        $key = (string) $capacity;

        if (isset($data['percents'][$key])) {
            return (float) $data['percents'][$key];
        }

        return self::defaultPercentForCapacity($capacity);
    }

    public static function multiplierForCapacity(int $capacity): float
    {
        return self::percentForCapacity($capacity) / 100.0;
    }

    /** @return array<int, float> */
    public static function allPercents(): array
    {
        $out = [];
        foreach (VehicleCapacityOptions::enabled() as $capacity) {
            $out[$capacity] = self::percentForCapacity($capacity);
        }

        return $out;
    }

    /** @return array{step_percent: float, percents: array<string, float>} */
    public static function settingsForAdmin(): array
    {
        $data = self::load();

        return [
            'step_percent' => self::stepPercent(),
            'percents'     => self::allPercents(),
        ];
    }

    /** @param  array<int|string, mixed>  $percents */
    public static function save(float $stepPercent, array $percents): void
    {
        $normalized = [];
        foreach (VehicleCapacityOptions::enabled() as $capacity) {
            $key = (string) $capacity;
            if (array_key_exists($capacity, $percents)) {
                $normalized[$key] = round((float) $percents[$capacity], 2);
            } elseif (array_key_exists($key, $percents)) {
                $normalized[$key] = round((float) $percents[$key], 2);
            }
        }

        PlatformSetting::setValue(self::SETTING_KEY, [
            'step_percent' => round($stepPercent, 2),
            'percents'     => $normalized,
        ], 'finance');
    }

    /** @return array{step_percent?: float, percents?: array<string, float>} */
    private static function load(): array
    {
        $raw = PlatformSetting::getValue(self::SETTING_KEY, null);

        return is_array($raw) ? $raw : [];
    }
}
