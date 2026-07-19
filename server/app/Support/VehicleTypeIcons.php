<?php

namespace App\Support;

/** Icon cố định theo loại xe (không dùng ảnh tài xế). */
class VehicleTypeIcons
{
    /** @return 'sedan'|'suv'|'limousine'|'van'|'other' */
    public static function keyFor(?string $type): string
    {
        $family = DriverVehicleOptions::family($type);
        $seats = DriverVehicleOptions::seatsFor($type);

        return match (true) {
            $family === 'sedan' => 'sedan',
            $family === 'suv' => 'suv',
            $family === 'limousine' && ($seats ?? 0) >= 16 => 'van',
            $family === 'limousine' => 'limousine',
            default => 'other',
        };
    }

    public static function hintFor(?string $type): string
    {
        $seats = DriverVehicleOptions::seatsFor($type);
        if ($seats !== null && $seats >= 7) {
            return 'Rộng rãi, tối đa '.$seats.' khách';
        }
        if ($seats !== null) {
            return 'Giá tốt · '.$seats.' chỗ';
        }

        return 'Chọn loại xe phù hợp';
    }
}
