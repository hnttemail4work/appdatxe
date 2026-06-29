<?php

namespace App\Support;

/** Tọa độ trung tâm tỉnh/thành — fallback khi chưa có GPS chính xác. */
final class ProvinceCenters
{
    /** @return array{lat: float, lng: float}|null */
    public static function forProvince(?string $province): ?array
    {
        $key = trim((string) $province);
        if ($key === '') {
            return null;
        }

        $coords = self::all();

        return $coords[$key] ?? null;
    }

    /** @return array<string, array{lat: float, lng: float}> */
    public static function all(): array
    {
        return [
            'TP.HCM'     => ['lat' => 10.7769, 'lng' => 106.7009],
            'Bình Dương' => ['lat' => 11.3254, 'lng' => 106.4770],
            'Đồng Nai'   => ['lat' => 10.9574, 'lng' => 106.8427],
            'Long An'    => ['lat' => 10.5339, 'lng' => 106.4132],
            'Tây Ninh'   => ['lat' => 11.3359, 'lng' => 106.1093],
            'Vũng Tàu'   => ['lat' => 10.3460, 'lng' => 107.0843],
            'Bà Rịa'     => ['lat' => 10.4963, 'lng' => 107.1684],
            'Phan Thiết' => ['lat' => 10.9289, 'lng' => 108.1021],
            'Mũi Né'     => ['lat' => 10.9558, 'lng' => 108.2100],
            'Đà Lạt'     => ['lat' => 11.9404, 'lng' => 108.4583],
            'Mỹ Tho'     => ['lat' => 10.3600, 'lng' => 106.3600],
            'Bến Tre'    => ['lat' => 10.2434, 'lng' => 106.3757],
            'Vĩnh Long'  => ['lat' => 10.2537, 'lng' => 105.9722],
            'Cần Thơ'    => ['lat' => 10.0452, 'lng' => 105.7469],
            'Long Xuyên' => ['lat' => 10.3866, 'lng' => 105.4352],
            'Châu Đốc'   => ['lat' => 10.7047, 'lng' => 105.1200],
        ];
    }

    public static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earth * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
