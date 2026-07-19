<?php

namespace App\Support;

use App\Models\VehicleType;
use Illuminate\Support\Facades\Schema;

/**
 * Hệ số % giá theo loại xe — đọc từ bảng vehicle_types (fallback const cũ).
 */
class VehicleTypePricing
{
    public const BASELINE_TYPE = 'sedan_4';

    public const DEFAULT_STEP_PERCENT = 1.5;

    public static function normalizeTypeKey(?string $type): ?string
    {
        if ($type === null || $type === '') {
            return null;
        }

        return match ($type) {
            'sedan' => 'sedan_4',
            'suv' => 'suv_7',
            'limousine' => 'limousine_7',
            default => $type,
        };
    }

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
        return DriverVehicleOptions::labels();
    }

    public static function percentForType(?string $type): float
    {
        $type = self::normalizeTypeKey($type);

        if ($type === null || $type === '' || $type === 'other') {
            return 100.0;
        }

        $row = self::findType($type);
        if ($row) {
            return max(0.0, (float) $row->price_percent);
        }

        $seats = DriverVehicleOptions::seatsFor($type);
        if ($seats !== null) {
            return VehicleCapacityPricing::percentForCapacity($seats);
        }

        return 100.0;
    }

    public static function multiplierForType(?string $type): float
    {
        return self::percentForType($type) / 100.0;
    }

    public static function multiplierFor(?string $vehicleType, ?int $capacity = null): float
    {
        $vehicleType = self::normalizeTypeKey($vehicleType);

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

    private static function findType(string $key): ?VehicleType
    {
        if (! self::tableReady()) {
            return null;
        }

        try {
            return VehicleType::activeCached()->firstWhere('key', $key)
                ?? VehicleType::allCached()->firstWhere('key', $key);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function tableReady(): bool
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }

        try {
            return $ready = Schema::hasTable('vehicle_types');
        } catch (\Throwable) {
            return $ready = false;
        }
    }
}
