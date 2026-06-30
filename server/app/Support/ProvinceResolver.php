<?php

namespace App\Support;

use App\Models\Booking;

/** Suy ra tên tỉnh/thành từ tọa độ hoặc chuỗi địa điểm trong catalog. */
final class ProvinceResolver
{
    public static function fromBooking(Booking $booking): ?string
    {
        if ($booking->pickup_lat !== null && $booking->pickup_lng !== null) {
            $fromCoords = self::nearestNamedProvince((float) $booking->pickup_lat, (float) $booking->pickup_lng);
            if ($fromCoords !== null) {
                return $fromCoords;
            }
        }

        return self::fromLocationName($booking->pickup_address);
    }

    public static function fromLocationName(?string $name): ?string
    {
        $name = trim((string) $name);
        if ($name === '') {
            return null;
        }

        if (LocationCatalog::isAllowed($name)) {
            return $name;
        }

        foreach (collect(LocationCatalog::all())->sortByDesc(fn (string $loc): int => mb_strlen($loc)) as $location) {
            if (str_contains($name, $location) || str_contains($location, $name)) {
                return $location;
            }
        }

        return null;
    }

    public static function nearestNamedProvince(float $lat, float $lng, float $maxKm = 120.0): ?string
    {
        $best = null;
        $bestDist = PHP_FLOAT_MAX;

        foreach (ProvinceCenters::all() as $province => $coords) {
            $dist = ProvinceCenters::distanceKm($lat, $lng, $coords['lat'], $coords['lng']);
            if ($dist < $bestDist) {
                $bestDist = $dist;
                $best = $province;
            }
        }

        return $bestDist <= $maxKm ? $best : null;
    }

    public static function forDriver(?float $lat, ?float $lng, ?string $storedProvince): ?string
    {
        $stored = trim((string) $storedProvince);
        if ($stored !== '' && LocationCatalog::isAllowed($stored)) {
            return $stored;
        }

        if ($lat !== null && $lng !== null) {
            return self::nearestNamedProvince($lat, $lng);
        }

        return null;
    }

    /** Map/GPS: ưu tiên đọc tỉnh từ chuỗi địa chỉ, fallback tọa độ gần nhất. */
    public static function fromMapPick(float $lat, float $lng, ?string $address = null): ?string
    {
        $fromAddress = self::fromLocationName($address);
        if ($fromAddress !== null) {
            return $fromAddress;
        }

        return self::nearestNamedProvince($lat, $lng);
    }
}
