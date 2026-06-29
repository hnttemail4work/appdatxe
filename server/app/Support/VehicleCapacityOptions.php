<?php

namespace App\Support;

class VehicleCapacityOptions
{
    /** Thứ tự hiển thị trên form quản lý & đặt xe. */
    public const STANDARD = [4, 7, 9, 11, 12, 16, 19, 20, 22, 24, 28, 34];

    /** @var array<int, true> */
    private const BED_ROOM = [
        20 => true,
        22 => true,
    ];

    public static function label(int $capacity): string
    {
        $text = $capacity . ' chỗ';

        if (isset(self::BED_ROOM[$capacity])) {
            $text .= ' (loại giường phòng)';
        }

        return $text;
    }

    public static function sortKey(int $capacity): int
    {
        $index = array_search($capacity, self::STANDARD, true);

        return $index !== false ? $index : $capacity;
    }

    public static function isStandard(int $capacity): bool
    {
        return self::isAllowed($capacity);
    }

    public static function isAllowed(int $capacity): bool
    {
        return in_array($capacity, self::STANDARD, true);
    }

    /** @return array<int, string> */
    public static function choices(): array
    {
        $out = [];
        foreach (self::STANDARD as $capacity) {
            $out[$capacity] = self::label($capacity);
        }

        return $out;
    }
}
