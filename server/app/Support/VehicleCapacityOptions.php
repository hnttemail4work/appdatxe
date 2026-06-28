<?php

namespace App\Support;

class VehicleCapacityOptions
{
    /** Số chỗ chuẩn — đồng bộ UI đặt xe & tạo chuyến quản lý. */
    public const STANDARD = [4, 7, 16];

    public static function label(int $capacity): string
    {
        return $capacity . ' chỗ';
    }

    public static function sortKey(int $capacity): int
    {
        $idx = array_search($capacity, self::STANDARD, true);

        return $idx === false ? 100 + $capacity : $idx;
    }

    public static function isStandard(int $capacity): bool
    {
        return in_array($capacity, self::STANDARD, true);
    }
}
