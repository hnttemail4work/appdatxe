<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/** Tọa độ trung tâm tỉnh/thành — tĩnh + tự tra cứu cho điểm admin thêm. */
final class ProvinceCenters
{
    private const GEOCODE_CACHE_DAYS = 30;

    private const CATALOG_CACHE_SECONDS = 300;

    /** @return array{lat: float, lng: float}|null */
    public static function forProvince(?string $province): ?array
    {
        $key = trim((string) $province);

        return $key === '' ? null : self::centerFor($key);
    }

    /** @return array{lat: float, lng: float}|null */
    public static function centerFor(string $name): ?array
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $static = self::all();
        if (isset($static[$name])) {
            return $static[$name];
        }

        if (! LocationCatalog::isAllowed($name)) {
            return null;
        }

        return Cache::remember(
            self::geocodeCacheKey($name),
            now()->addDays(self::GEOCODE_CACHE_DAYS),
            fn (): ?array => self::geocodeCenter($name),
        );
    }

    /** @return array<string, array{lat: float, lng: float}> */
    public static function centersForCatalog(): array
    {
        return Cache::remember('province_centers.catalog', self::CATALOG_CACHE_SECONDS, function (): array {
            $out = [];

            foreach (LocationCatalog::all() as $name) {
                $center = self::centerFor($name);
                if ($center !== null) {
                    $out[$name] = $center;
                }
            }

            return $out;
        });
    }

    public static function searchLabelFor(string $province): string
    {
        $province = trim($province);

        return match ($province) {
            'TP.HCM' => 'Thành phố Hồ Chí Minh',
            default  => $province,
        };
    }

    public static function viewboxFor(?string $province): ?string
    {
        $center = self::forProvince($province);
        if ($center === null) {
            return null;
        }

        $padLat = 0.35;
        $padLng = 0.35;

        return sprintf(
            '%f,%f,%f,%f',
            $center['lng'] - $padLng,
            $center['lat'] + $padLat,
            $center['lng'] + $padLng,
            $center['lat'] - $padLat,
        );
    }

    public static function forgetCenterCache(?string $name = null): void
    {
        Cache::forget('province_centers.catalog');

        if ($name !== null && trim($name) !== '') {
            Cache::forget(self::geocodeCacheKey(trim($name)));
        }
    }

    public static function warmCenter(string $name): void
    {
        self::forgetCenterCache($name);
        self::centerFor($name);
        self::centersForCatalog();
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
            'Trà Vinh'   => ['lat' => 9.9513, 'lng' => 106.3345],
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

  /** @return array{lat: float, lng: float}|null */
    private static function geocodeCenter(string $name): ?array
    {
        $query = self::searchLabelFor($name).', Việt Nam';
        $name = (string) config('app.name', 'App');
        $email = (string) config('app.contact_email', 'noreply@localhost');

        try {
            $client = Http::timeout(8)->withHeaders([
                'User-Agent' => $name.' ('.$email.')',
            ]);

            if (! (bool) config('app.geocode_verify_ssl', true)) {
                $client = $client->withOptions(['verify' => false]);
            }

            $response = $client->get('https://nominatim.openstreetmap.org/search', [
                'q'              => $query,
                'format'         => 'json',
                'countrycodes'   => 'vn',
                'limit'          => 1,
                'accept-language'=> 'vi',
            ]);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $rows = $response->json();
        if (! is_array($rows) || $rows === [] || ! is_array($rows[0])) {
            return null;
        }

        $lat = isset($rows[0]['lat']) ? (float) $rows[0]['lat'] : null;
        $lng = isset($rows[0]['lon']) ? (float) $rows[0]['lon'] : null;

        if ($lat === null || $lng === null) {
            return null;
        }

        return ['lat' => $lat, 'lng' => $lng];
    }

    private static function geocodeCacheKey(string $name): string
    {
        return 'province_center.geocode.'.hash('sha256', mb_strtolower(trim($name)));
    }
}
