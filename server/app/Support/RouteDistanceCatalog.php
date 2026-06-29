<?php

namespace App\Support;

use App\Models\TripRoute;

/** Quãng đường cố định — admin cấu hình qua bảng routes từ TP.HCM. */
class RouteDistanceCatalog
{
    public const HUB = 'TP.HCM';

    /** @return array<string, int> km từ TP.HCM — chỉ dùng khi seed DB. */
    public static function defaultsFromHub(): array
    {
        return [
            'Bình Dương' => 30,
            'Đồng Nai'   => 30,
            'Long An'    => 50,
            'Tây Ninh'   => 100,
            'Bà Rịa'     => 85,
            'Vũng Tàu'   => 100,
            'Phan Thiết' => 200,
            'Mũi Né'     => 220,
            'Đà Lạt'     => 300,
            'Mỹ Tho'     => 70,
            'Bến Tre'    => 85,
            'Vĩnh Long'  => 135,
            'Cần Thơ'    => 165,
            'Long Xuyên' => 190,
            'Châu Đốc'   => 250,
        ];
    }

    public static function resolveKm(string $departure, string $destination): int
    {
        $departure = trim($departure);
        $destination = trim($destination);

        if ($departure === '' || $destination === '' || $departure === $destination) {
            return 0;
        }

        $direct = TripRoute::query()
            ->where('departure', $departure)
            ->where('destination', $destination)
            ->where('is_active', true)
            ->value('distance_km');

        if ($direct !== null && (int) $direct > 0) {
            return (int) $direct;
        }

        $reverse = TripRoute::query()
            ->where('departure', $destination)
            ->where('destination', $departure)
            ->where('is_active', true)
            ->value('distance_km');

        if ($reverse !== null && (int) $reverse > 0) {
            return (int) $reverse;
        }

        return LocationCatalog::distanceBetween($departure, $destination);
    }

    /** @return list<array{departure: string, destination: string, distance_km: int}> */
    public static function hubRouteRows(): array
    {
        return collect(self::defaultsFromHub())
            ->map(fn (int $km, string $destination) => [
                'departure'   => self::HUB,
                'destination' => $destination,
                'distance_km' => $km,
            ])
            ->values()
            ->all();
    }
}
