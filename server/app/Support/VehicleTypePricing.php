<?php

namespace App\Support;

use App\Models\PlatformSetting;

/**
 * Hệ số % giá cả xe theo loại xe — chuẩn {@see BASELINE_TYPE} = 100%.
 * Fallback tạm: map số chỗ → {@see VehicleCapacityPricing} nếu chưa cấu hình loại xe.
 */
class VehicleTypePricing
{
    public const BASELINE_TYPE = 'sedan_4';

    public const DEFAULT_STEP_PERCENT = 1.5;

    public const SETTING_KEY = 'vehicle_type_whole_car_pricing';

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
        $data = self::load();

        return (float) ($data['step_percent'] ?? self::DEFAULT_STEP_PERCENT);
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

        $data = self::load();
        $percents = $data['percents'] ?? null;
        if (is_array($percents) && array_key_exists($type, $percents)) {
            return (float) $percents[$type];
        }

        return self::defaultPercentForType($type);
    }

    public static function multiplierForType(?string $type): float
    {
        return self::percentForType($type) / 100.0;
    }

    /** Ưu tiên loại xe; không có thì fallback số chỗ (cấu hình cũ). */
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

    /** @return array{step_percent: float, percents: array<string, float>, baseline: string} */
    public static function settingsForAdmin(): array
    {
        return [
            'step_percent' => self::stepPercent(),
            'percents'     => self::allPercents(),
            'baseline'     => self::BASELINE_TYPE,
        ];
    }

    /** @param  array<string, mixed>  $percents */
    public static function save(float $stepPercent, array $percents): void
    {
        $normalized = [];
        foreach (self::priceableKeys() as $type) {
            if (array_key_exists($type, $percents)) {
                $normalized[$type] = round((float) $percents[$type], 2);
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
