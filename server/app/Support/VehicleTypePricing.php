<?php

namespace App\Support;

/**
 * Hệ số % giá cả xe theo loại xe — chuẩn {@see BASELINE_TYPE} = 100%.
 * Không còn cấu hình admin; dùng mặc định (fallback số chỗ qua {@see VehicleCapacityPricing}).
 */
class VehicleTypePricing
{
    public const BASELINE_TYPE = 'sedan_4';

    public const DEFAULT_STEP_PERCENT = 1.5;

    /** @return list<string> */
    public static function priceableKeys(): array
    {
        return array_values(array_filter(
            DriverVehicleOptions::keys(),
            static fn (string $key): bool => $key !== 'other',
        ));
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        $out = [];
        foreach (self::priceableKeys() as $key) {
            $out[$key] = DriverVehicleOptions::label($key);
        }

        return $out;
    }

    public static function stepPercent(): float
    {
        return self::DEFAULT_STEP_PERCENT;
    }

    public static function tierIndex(string $type): int
    {
        $keys = self::priceableKeys();
        $typeIdx = array_search($type, $keys, true);
        $baseIdx = array_search(self::BASELINE_TYPE, $keys, true);

        if ($typeIdx === false) {
            return 0;
        }

        return max(0, $typeIdx - ($baseIdx !== false ? $baseIdx : 0));
    }

    public static function defaultPercentForType(string $type): float
    {
        $seats = DriverVehicleOptions::seatsFor($type);
        if ($seats !== null) {
            return VehicleCapacityPricing::percentForCapacity($seats);
        }

        return round(100.0 + self::tierIndex($type) * self::stepPercent(), 2);
    }

    public static function percentForType(?string $type): float
    {
        if ($type === null || $type === '' || $type === 'other') {
            return 100.0;
        }

        // Legacy family trên catalog cũ — map sang loại chuẩn cùng family.
        $type = match ($type) {
            'sedan' => 'sedan_4',
            'suv' => 'suv_7',
            'limousine' => 'limousine_7',
            default => $type,
        };

        return self::defaultPercentForType($type);
    }

    public static function multiplierForType(?string $type): float
    {
        return self::percentForType($type) / 100.0;
    }

    /** Ưu tiên loại xe; không có thì fallback số chỗ. */
    public static function multiplierFor(?string $vehicleType, ?int $capacity = null): float
    {
        if (filled($vehicleType) && $vehicleType !== 'other') {
            return self::multiplierForType($vehicleType);
        }

        if ($capacity !== null && $capacity > 0) {
            return VehicleCapacityPricing::multiplierForCapacity($capacity);
        }

        return 1.0;
    }

    /** @return array<string, float> */
    public static function allPercents(): array
    {
        $out = [];
        foreach (self::priceableKeys() as $type) {
            $out[$type] = self::percentForType($type);
        }

        return $out;
    }
}
