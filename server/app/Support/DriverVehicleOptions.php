<?php

namespace App\Support;

use App\Models\VehicleType;
use Illuminate\Support\Facades\Schema;

/** Danh mục loại xe — ưu tiên DB vehicle_types, fallback const seed. */
class DriverVehicleOptions
{
    /**
     * Seed / fallback khi chưa migrate.
     *
     * @var array<string, array{label: string, seats: int|null, family: string}>
     */
    public const OPTIONS = [
        'hatchback_4'  => ['label' => '4 chỗ - Cỡ nhỏ (Hatchback)', 'seats' => 4, 'family' => 'sedan'],
        'sedan_4'      => ['label' => '4 chỗ - Phổ thông (Sedan)', 'seats' => 4, 'family' => 'sedan'],
        'mpv_7'        => ['label' => '7 chỗ - Phổ thông (MPV)', 'seats' => 7, 'family' => 'suv'],
        'suv_7'        => ['label' => '7 chỗ - Gầm cao (SUV)', 'seats' => 7, 'family' => 'suv'],
        'limousine_7'  => ['label' => '7 chỗ - Thương gia (Limousine)', 'seats' => 7, 'family' => 'limousine'],
        'limousine_9'  => ['label' => '9 chỗ - Thương gia (Limousine)', 'seats' => 9, 'family' => 'limousine'],
        'limousine_11' => ['label' => '11 chỗ - Thương gia (Limousine)', 'seats' => 11, 'family' => 'limousine'],
        'minibus_16'   => ['label' => '16 chỗ - Tiêu chuẩn (Minibus)', 'seats' => 16, 'family' => 'limousine'],
        'other'        => ['label' => 'Khác', 'seats' => null, 'family' => 'other'],
    ];

    private const LEGACY_LABELS = [
        'sedan'     => 'Sedan',
        'suv'       => 'SUV',
        'limousine' => 'Limousine',
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        $fromDb = self::dbOptions();
        if ($fromDb !== null) {
            return array_keys($fromDb);
        }

        return array_keys(self::OPTIONS);
    }

    /** @return list<string> */
    public static function allowedKeys(): array
    {
        return array_values(array_unique(array_merge(self::keys(), array_keys(self::LEGACY_LABELS))));
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        $fromDb = self::dbOptions();
        if ($fromDb !== null) {
            $out = [];
            foreach ($fromDb as $key => $meta) {
                $out[$key] = $meta['label'];
            }

            return $out;
        }

        $out = [];
        foreach (self::OPTIONS as $key => $meta) {
            $out[$key] = $meta['label'];
        }

        return $out;
    }

    public static function label(?string $type): string
    {
        if ($type === null || $type === '') {
            return '';
        }

        $labels = self::labels();
        if (isset($labels[$type])) {
            return $labels[$type];
        }

        return self::LEGACY_LABELS[$type] ?? $type;
    }

    public static function seatsFor(?string $type): ?int
    {
        if ($type === null || $type === '') {
            return null;
        }

        $type = VehicleTypePricing::normalizeTypeKey($type) ?? $type;
        $fromDb = self::dbOptions();
        if ($fromDb !== null && isset($fromDb[$type])) {
            return $fromDb[$type]['seats'];
        }

        if (isset(self::OPTIONS[$type])) {
            return self::OPTIONS[$type]['seats'];
        }

        return match ($type) {
            'sedan' => 4,
            'suv' => 7,
            'limousine' => 9,
            default => null,
        };
    }

    public static function family(?string $type): string
    {
        if ($type === null || $type === '') {
            return 'other';
        }

        $type = VehicleTypePricing::normalizeTypeKey($type) ?? $type;
        $fromDb = self::dbOptions();
        if ($fromDb !== null && isset($fromDb[$type])) {
            return $fromDb[$type]['family'];
        }

        if (isset(self::OPTIONS[$type])) {
            return self::OPTIONS[$type]['family'];
        }

        if (isset(self::LEGACY_LABELS[$type])) {
            return $type;
        }

        return 'other';
    }

    public static function compatibleWithVehicleType(?string $driverType, ?string $vehicleType): bool
    {
        if (! $vehicleType || ! $driverType) {
            return true;
        }

        if ($driverType === $vehicleType) {
            return true;
        }

        $family = self::family($driverType);

        return $family === 'other' || $family === $vehicleType;
    }

    /**
     * @return array<string, array{label: string, seats: int|null, family: string}>|null
     */
    private static function dbOptions(): ?array
    {
        if (! self::tableReady()) {
            return null;
        }

        try {
            $rows = VehicleType::activeCached();
            if ($rows->isEmpty()) {
                return null;
            }

            $out = [];
            foreach ($rows as $row) {
                $out[$row->key] = [
                    'label'  => $row->label,
                    'seats'  => $row->seats,
                    'family' => $row->family ?: 'other',
                ];
            }

            return $out;
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
