<?php

namespace App\Support;

use App\Models\TripRoute;
use Illuminate\Support\Facades\Cache;

/** Điểm đi/đến — danh sách từ admin (bảng routes từ TP.HCM). */
class LocationCatalog
{
    public static function hub(): string
    {
        return RouteDistanceCatalog::HUB;
    }

    /** @return list<string> */
    public static function hubDestinations(): array
    {
        return Cache::remember('catalog.hub_destinations', 300, function (): array {
            return TripRoute::query()
                ->where('departure', self::hub())
                ->where('is_active', true)
                ->orderBy('destination')
                ->pluck('destination')
                ->all();
        });
    }

    /** @return list<string> */
    public static function all(): array
    {
        return Cache::remember('catalog.all_locations', 300, function (): array {
            return collect([self::hub()])
                ->merge(self::hubDestinations())
                ->merge(
                    TripRoute::query()
                        ->where('is_active', true)
                        ->get()
                        ->flatMap(fn (TripRoute $route) => [$route->departure, $route->destination]),
                )
                ->map(fn (string $name) => trim($name))
                ->filter()
                ->unique()
                ->sort()
                ->values()
                ->all();
        });
    }

    /** @return array<string, list<string>> */
    public static function grouped(): array
    {
        return [
            'Trung tâm' => [self::hub()],
            'Điểm đến'  => self::hubDestinations(),
        ];
    }

    public static function isAllowed(?string $value): bool
    {
        $value = trim((string) $value);

        return $value !== '' && in_array($value, self::all(), true);
    }

    public static function distanceFromHub(string $city): int
    {
        $city = trim($city);

        if ($city === '' || $city === self::hub()) {
            return 0;
        }

        $km = TripRoute::query()
            ->where('departure', self::hub())
            ->where('destination', $city)
            ->where('is_active', true)
            ->value('distance_km');

        return $km !== null ? (int) $km : 0;
    }

    public static function estimateDistanceKm(string $from, string $to): float
    {
        return (float) self::distanceBetween($from, $to);
    }

    public static function distanceBetween(string $from, string $to): int
    {
        $from = trim($from);
        $to = trim($to);

        if ($from === '' || $to === '' || $from === $to) {
            return 0;
        }

        if ($from === self::hub()) {
            return self::distanceFromHub($to);
        }

        if ($to === self::hub()) {
            return self::distanceFromHub($from);
        }

        $fromKm = self::distanceFromHub($from);
        $toKm = self::distanceFromHub($to);

        if ($fromKm > 0 && $toKm > 0) {
            return $fromKm + $toKm;
        }

        return 0;
    }

    public static function forgetCache(): void
    {
        Cache::forget('catalog.hub_destinations');
        Cache::forget('catalog.all_locations');
        ProvinceCenters::forgetCenterCache();
    }
}
