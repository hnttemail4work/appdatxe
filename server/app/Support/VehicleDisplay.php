<?php

namespace App\Support;

use App\Models\DriverProfile;
use App\Models\Vehicle;
use App\Services\DriverTripRequestService;

/** Nhãn & ảnh xe thống nhất — quản lý (Vehicle), tài xế (DriverProfile), đặt xe. */
class VehicleDisplay
{
    /** @var array<string, string> */
    public const TYPE_LABELS = [
        'sedan'     => 'Sedan',
        'suv'       => 'SUV',
        'limousine' => 'Limousine',
    ];

    public static function typeLabel(?string $type): string
    {
        if ($type === null || $type === '') {
            return '';
        }

        return self::TYPE_LABELS[$type] ?? ucfirst($type);
    }

    public static function typeForCapacity(int $capacity): string
    {
        if ($capacity <= 5) {
            return 'sedan';
        }

        if ($capacity <= 9) {
            return 'suv';
        }

        return 'limousine';
    }

    public static function labelFromVehicle(?Vehicle $vehicle): string
    {
        if (! $vehicle) {
            return '—';
        }

        return self::compactLabel(
            $vehicle->type ? (string) $vehicle->type : null,
            $vehicle->license_plate,
            $vehicle->capacity ? (int) $vehicle->capacity : null,
        );
    }

    public static function compactLabel(?string $type, ?string $plate, ?int $capacity): string
    {
        $typeLabel = self::typeLabel($type);
        $head = trim($typeLabel . ($typeLabel && $plate ? ' ' : '') . ($plate ?? ''));
        $capLabel = $capacity ? VehicleCapacityOptions::label($capacity) : null;

        if ($head !== '' && $capLabel) {
            return $head . ', ' . $capLabel;
        }

        if ($head !== '') {
            return $head;
        }

        return $capLabel ?? '—';
    }

    public static function labelFromDriverProfile(DriverProfile $profile): string
    {
        return DriverTripRequestService::vehicleLabel($profile);
    }

    public static function photoFromVehicle(?Vehicle $vehicle): ?string
    {
        return $vehicle?->photoUrl();
    }

    public static function photoFromDriverProfile(DriverProfile $profile): ?string
    {
        return $profile->firstVehiclePhotoUrl();
    }

    public static function capacityFromDriverProfile(DriverProfile $profile): int
    {
        return max(0, (int) ($profile->vehicle_seats ?? 0));
    }
}
