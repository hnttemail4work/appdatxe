<?php

namespace App\Support;

/** Hệ số % giá cả xe theo loại chỗ — 4 chỗ làm chuẩn 100%. Không còn cấu hình admin. */
class VehicleCapacityPricing
{
    public const BASELINE_CAPACITY = 4;

    public const DEFAULT_STEP_PERCENT = 1.5;

    public static function stepPercent(): float
    {
        return self::DEFAULT_STEP_PERCENT;
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
}
