<?php

namespace App\Support;

/** Danh mục loại xe tài xế — gắn sẵn số chỗ, dùng đăng ký / admin / hiển thị. */
class DriverVehicleOptions
{
    /**
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

    /** Kiểu cũ còn trong DB trước khi mở rộng danh mục. */
    private const LEGACY_LABELS = [
        'sedan'     => 'Sedan',
        'suv'       => 'SUV',
        'limousine' => 'Limousine',
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::OPTIONS);
    }

    /** @return list<string> — keys hợp lệ khi validate (gồm legacy). */
    public static function allowedKeys(): array
    {
        return array_values(array_unique(array_merge(self::keys(), array_keys(self::LEGACY_LABELS))));
    }

    /** @return array<string, string> value => label */
    public static function labels(): array
    {
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

        if (isset(self::OPTIONS[$type])) {
            return self::OPTIONS[$type]['label'];
        }

        return self::LEGACY_LABELS[$type] ?? $type;
    }

    public static function seatsFor(?string $type): ?int
    {
        if ($type === null || $type === '') {
            return null;
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

        if (isset(self::OPTIONS[$type])) {
            return self::OPTIONS[$type]['family'];
        }

        if (isset(self::LEGACY_LABELS[$type])) {
            return $type;
        }

        return 'other';
    }

    /** So khớp loại xe catalog (sedan/suv/limousine) với loại đăng ký tài xế. */
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
}
