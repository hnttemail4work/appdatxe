<?php

namespace App\Support;

/**
 * @deprecated Dùng LocationCatalog — giữ tên class để tương thích validate cũ.
 */
class SouthernProvinces
{
    /** @return array<string, list<string>> */
    public static function grouped(): array
    {
        return LocationCatalog::grouped();
    }

    public static function distanceFromHub(string $city): int
    {
        return LocationCatalog::distanceFromHub($city);
    }

    public static function distanceBetween(string $from, string $to): int
    {
        return LocationCatalog::distanceBetween($from, $to);
    }

    /** @return list<string> */
    public static function all(): array
    {
        return LocationCatalog::all();
    }

    public static function isAllowed(?string $value): bool
    {
        return LocationCatalog::isAllowed($value);
    }

    /** @return \Illuminate\Validation\Rules\In */
    public static function inRule()
    {
        return LocationCatalog::inRule();
    }
}
