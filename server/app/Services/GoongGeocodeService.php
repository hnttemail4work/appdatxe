<?php

namespace App\Services;

use App\Support\ProvinceCenters;
use App\Support\ProvinceResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GoongGeocodeService
{
    private const BASE_URL = 'https://rsapi.goong.io';

    public function isConfigured(): bool
    {
        return $this->apiKey() !== '';
    }

    private function apiKey(): string
    {
        return trim((string) config('services.goong.api_key', ''));
    }

    /** @return list<array<string, mixed>> */
    public function search(string $query, string $province = ''): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        $input = trim($query);
        if (mb_strlen($input) < 2) {
            return [];
        }

        $results = $this->autocompleteSearch($input, $province);
        if ($results !== []) {
            return $results;
        }

        $withProvince = $this->inputWithProvince($input, $province);
        if ($withProvince !== '' && $withProvince !== $input) {
            $results = $this->autocompleteSearch($withProvince, $province);
            if ($results !== []) {
                return $results;
            }
        }

        // Geocode fallback đã có lat/lon — dùng để hiện km ngay.
        return $this->searchViaGeocode($input, $province);
    }

    /**
     * Gợi ý địa điểm quanh tọa độ (lọc trong bán kính mét).
     *
     * @return list<array<string, mixed>>
     */
    public function nearby(float $lat, float $lng, int $radiusMeters = 300): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        $radiusMeters = max(30, min(500, $radiusMeters));
        $reverse = $this->reverse($lat, $lng);
        $queries = [];

        if ($reverse !== null) {
            $address = trim((string) ($reverse['address'] ?? ''));
            $segments = array_values(array_filter(array_map('trim', explode(',', $address))));
            if ($segments !== []) {
                $queries[] = $segments[0];
                if (isset($segments[1])) {
                    $queries[] = trim($segments[0].' '.$segments[1]);
                }
            }
        }

        $queries = array_values(array_unique(array_filter(
            $queries,
            static fn (string $q): bool => mb_strlen($q) >= 2,
        )));

        if ($queries === []) {
            return [];
        }

        $radiusKm = max(1, (int) ceil($radiusMeters / 1000));
        $seenPlaceIds = [];
        $seenLabels = [];
        $out = [];

        foreach ($queries as $query) {
            foreach ($this->autocompleteNear($query, $lat, $lng, $radiusKm) as $prediction) {
                $placeId = trim((string) ($prediction['place_id'] ?? ''));
                if ($placeId === '' || isset($seenPlaceIds[$placeId])) {
                    continue;
                }
                $seenPlaceIds[$placeId] = true;

                $resolved = $this->resolvePlaceId($placeId);
                if ($resolved === null) {
                    continue;
                }

                $itemLat = (float) $resolved['lat'];
                $itemLng = (float) $resolved['lon'];
                $dist = $this->haversineMeters($lat, $lng, $itemLat, $itemLng);
                if ($dist > $radiusMeters || $dist < 8) {
                    continue;
                }

                $title = trim((string) ($resolved['title'] ?? $prediction['title'] ?? ''));
                $address = trim((string) ($resolved['address'] ?? ''));
                $labelKey = $this->nearbyDedupeKey($title !== '' ? $title : $address, $address);
                if ($labelKey !== '' && isset($seenLabels[$labelKey])) {
                    continue;
                }

                // Trùng tọa độ với kết quả đã có (cùng chỗ, khác place_id)
                $tooClose = false;
                foreach ($out as $existing) {
                    if ($this->haversineMeters($itemLat, $itemLng, (float) $existing['lat'], (float) $existing['lon']) < 25) {
                        $tooClose = true;
                        break;
                    }
                }
                if ($tooClose) {
                    continue;
                }

                if ($labelKey !== '') {
                    $seenLabels[$labelKey] = true;
                }

                $out[] = [
                    'title' => $title !== '' ? $title : $address,
                    'subtitle' => (string) ($resolved['subtitle'] ?? $prediction['subtitle'] ?? ''),
                    'address' => $address !== '' ? $address : $title,
                    'lat' => $itemLat,
                    'lon' => $itemLng,
                    'place_id' => $placeId,
                    'province' => (string) ($resolved['province'] ?? ''),
                    'distance_m' => (int) round($dist),
                    'kind_label' => (string) ($resolved['kind_label'] ?? $prediction['kind_label'] ?? 'Địa điểm'),
                ];
            }
        }

        usort($out, static fn (array $a, array $b): int => ($a['distance_m'] ?? 0) <=> ($b['distance_m'] ?? 0));

        return array_slice($out, 0, 8);
    }

    private function nearbyDedupeKey(string $title, string $address): string
    {
        $raw = $address !== '' ? $address : $title;
        $raw = mb_strtolower(trim($raw));
        if ($raw === '') {
            return '';
        }

        // Bỏ khoảng trắng / dấu câu thừa để gom “số 12 …” trùng nhau
        $raw = preg_replace('/[^\p{L}\p{N}]+/u', '', $raw) ?? $raw;

        return $raw;
    }

    private function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $earth * asin(min(1, sqrt($a)));
    }

    /** @return list<array<string, mixed>> */
    private function autocompleteNear(string $input, float $lat, float $lng, int $radiusKm): array
    {
        $payload = $this->get('/Place/AutoComplete', [
            'input' => $input,
            'api_key' => $this->apiKey(),
            'limit' => 10,
            'more_compound' => 'true',
            'location' => $lat.','.$lng,
            'radius' => max(1, $radiusKm),
        ]);

        if ($payload === null || ($payload['status'] ?? '') !== 'OK') {
            return [];
        }

        $results = [];
        foreach ($payload['predictions'] ?? [] as $prediction) {
            if (! is_array($prediction)) {
                continue;
            }
            $mapped = $this->mapPrediction($prediction);
            if ($mapped !== null) {
                $results[] = $mapped;
            }
        }

        return $results;
    }

    /** @return array<string, mixed>|null */
    public function resolvePlaceId(string $placeId): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $placeId = trim($placeId);
        if ($placeId === '') {
            return null;
        }

        $payload = $this->get('/Place/Detail', [
            'place_id' => $placeId,
            'api_key' => $this->apiKey(),
        ]);

        if ($payload === null || ($payload['status'] ?? '') !== 'OK') {
            $payload = $this->get('/Geocode', [
                'place_id' => $placeId,
                'api_key' => $this->apiKey(),
            ]);
        }

        if ($payload === null || ($payload['status'] ?? '') !== 'OK') {
            return null;
        }

        $result = $payload['result'] ?? null;
        if (! is_array($result)) {
            $rows = $payload['results'] ?? [];
            $result = is_array($rows) && is_array($rows[0] ?? null) ? $rows[0] : null;
        }

        if (! is_array($result)) {
            return null;
        }

        $address = trim((string) ($result['formatted_address'] ?? ''));
        $name = trim((string) ($result['name'] ?? ''));
        if ($name !== '' && $address !== '' && ! str_contains($address, $name)) {
            $address = $name.', '.$address;
        } elseif ($address === '' && $name !== '') {
            $address = $name;
        }

        $location = $result['geometry']['location'] ?? null;
        if ($address === '' || ! is_array($location)) {
            return null;
        }

        $lat = isset($location['lat']) ? (float) $location['lat'] : null;
        $lon = isset($location['lng']) ? (float) $location['lng'] : null;
        if ($lat === null || $lon === null) {
            return null;
        }

        $presented = $this->presentResult($result, $address, $lat, $lon, $name);
        $presented['place_id'] = trim((string) ($result['place_id'] ?? $placeId));
        $presented['province'] = ProvinceResolver::fromMapPick($lat, $lon, $address) ?? '';

        return $presented;
    }

    /**
     * Lộ trình xe theo đường (Goong Direction).
     *
     * @return array{coordinates: list<array{0: float, 1: float}>, distance_m: int|null, duration_s: int|null}|null
     */
    /**
     * @return array{coordinates: list<array{0: float, 1: float}>, distance_m: int|null, duration_s: int|null, steps: list<array<string, mixed>>}|null
     */
    public function direction(float $originLat, float $originLng, float $destLat, float $destLng): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        // Bám tick GPS liên tục nhưng phía gọi (turn-by-turn) chỉ fetch lại khi lệch tuyến —
        // cache ngắn hạn thêm 1 lớp chống gọi trùng (retry mạng, nhiều tab…) mà không tốn thêm phí.
        $cacheKey = 'goong_direction:v1:'
            .round($originLat, 3).','.round($originLng, 3).'|'
            .round($destLat, 4).','.round($destLng, 4);

        return Cache::remember($cacheKey, 45, function () use ($originLat, $originLng, $destLat, $destLng): ?array {
            return $this->fetchDirection($originLat, $originLng, $destLat, $destLng);
        });
    }

    /**
     * @return array{coordinates: list<array{0: float, 1: float}>, distance_m: int|null, duration_s: int|null, steps: list<array<string, mixed>>}|null
     */
    private function fetchDirection(float $originLat, float $originLng, float $destLat, float $destLng): ?array
    {
        $payload = $this->get('/Direction', [
            'origin' => $originLat.','.$originLng,
            'destination' => $destLat.','.$destLng,
            'vehicle' => 'car',
            'api_key' => $this->apiKey(),
        ]);

        if ($payload === null) {
            return null;
        }

        $routes = $payload['routes'] ?? null;
        if (! is_array($routes) || $routes === []) {
            return null;
        }

        $route = is_array($routes[0]) ? $routes[0] : null;
        if ($route === null) {
            return null;
        }

        $encoded = (string) data_get($route, 'overview_polyline.points', '');
        $coordinates = $encoded !== '' ? $this->decodePolyline($encoded) : [];

        if ($coordinates === []) {
            $coordinates = [
                [$originLng, $originLat],
                [$destLng, $destLat],
            ];
        }

        $distanceM = data_get($route, 'legs.0.distance.value');
        $durationS = data_get($route, 'legs.0.duration.value');

        return [
            'coordinates' => $coordinates,
            'distance_m' => is_numeric($distanceM) ? (int) $distanceM : null,
            'duration_s' => is_numeric($durationS) ? (int) $durationS : null,
            'steps' => $this->extractSteps($route),
        ];
    }

    /**
     * Turn-by-turn steps từ legs.0.steps — dùng cho banner chỉ đường phía tài xế.
     *
     * @return list<array{instruction: string, maneuver: string, distance_m: int|null, duration_s: int|null, start: array{lat: float, lng: float}|null, end: array{lat: float, lng: float}|null}>
     */
    private function extractSteps(array $route): array
    {
        $rawSteps = data_get($route, 'legs.0.steps');
        if (! is_array($rawSteps)) {
            return [];
        }

        $steps = [];
        foreach ($rawSteps as $rawStep) {
            if (! is_array($rawStep)) {
                continue;
            }

            $instruction = trim(strip_tags((string) data_get($rawStep, 'html_instructions', '')));
            $end = $this->extractLatLng(data_get($rawStep, 'end_location'));
            if ($instruction === '' || $end === null) {
                // Không có điểm cuối thì không dùng được để bám tuyến — bỏ qua.
                continue;
            }

            $distanceM = data_get($rawStep, 'distance.value');
            $durationS = data_get($rawStep, 'duration.value');

            $steps[] = [
                'instruction' => $instruction,
                'maneuver' => (string) data_get($rawStep, 'maneuver', ''),
                'distance_m' => is_numeric($distanceM) ? (int) $distanceM : null,
                'duration_s' => is_numeric($durationS) ? (int) $durationS : null,
                'start' => $this->extractLatLng(data_get($rawStep, 'start_location')),
                'end' => $end,
            ];
        }

        return $steps;
    }

    /** @return array{lat: float, lng: float}|null */
    private function extractLatLng(mixed $location): ?array
    {
        if (! is_array($location) || ! isset($location['lat'], $location['lng'])
            || ! is_numeric($location['lat']) || ! is_numeric($location['lng'])) {
            return null;
        }

        return ['lat' => (float) $location['lat'], 'lng' => (float) $location['lng']];
    }

    /**
     * Decode Google/Goong encoded polyline → [[lng, lat], …]
     *
     * @return list<array{0: float, 1: float}>
     */
    private function decodePolyline(string $encoded): array
    {
        $coordinates = [];
        $index = 0;
        $lat = 0;
        $lng = 0;
        $len = strlen($encoded);

        while ($index < $len) {
            $result = 0;
            $shift = 0;
            do {
                $b = ord($encoded[$index++]) - 63;
                $result |= ($b & 0x1F) << $shift;
                $shift += 5;
            } while ($b >= 0x20 && $index < $len);
            $lat += ($result & 1) ? ~($result >> 1) : ($result >> 1);

            $result = 0;
            $shift = 0;
            do {
                $b = ord($encoded[$index++]) - 63;
                $result |= ($b & 0x1F) << $shift;
                $shift += 5;
            } while ($b >= 0x20 && $index < $len);
            $lng += ($result & 1) ? ~($result >> 1) : ($result >> 1);

            $coordinates[] = [$lng / 1e5, $lat / 1e5];
        }

        return $coordinates;
    }

    /** @return array{address: string, lat: float, lon: float, province: string}|null */
    public function reverse(float $lat, float $lon): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $payload = $this->get('/Geocode', [
            'latlng' => $lat.','.$lon,
            'api_key' => $this->apiKey(),
        ]);

        if ($payload === null || ($payload['status'] ?? '') !== 'OK') {
            return null;
        }

        $rows = $payload['results'] ?? [];
        if (! is_array($rows) || $rows === []) {
            return null;
        }

        $best = is_array($rows[0]) ? $rows[0] : null;
        if ($best === null) {
            return null;
        }

        $address = trim((string) ($best['formatted_address'] ?? ''));
        if ($address === '') {
            return null;
        }

        return [
            'address' => $address,
            'lat' => $lat,
            'lon' => $lon,
            'province' => ProvinceResolver::fromMapPick($lat, $lon, $address) ?? '',
        ];
    }

    /** @return array{lat: float, lng: float}|null */
    public function geocodeCenter(string $query): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $payload = $this->get('/Geocode', [
            'address' => $query,
            'api_key' => $this->apiKey(),
        ]);

        if ($payload === null || ($payload['status'] ?? '') !== 'OK') {
            return null;
        }

        $rows = $payload['results'] ?? [];
        if (! is_array($rows) || $rows === [] || ! is_array($rows[0])) {
            return null;
        }

        $location = $rows[0]['geometry']['location'] ?? null;
        if (! is_array($location)) {
            return null;
        }

        $lat = $location['lat'] ?? null;
        $lng = $location['lng'] ?? null;
        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return null;
        }

        return ['lat' => (float) $lat, 'lng' => (float) $lng];
    }

    /** @return list<array<string, mixed>> */
    private function autocompleteSearch(string $input, string $province): array
    {
        $center = ProvinceCenters::forProvince($province);
        $params = [
            'input' => $input,
            'api_key' => $this->apiKey(),
            'limit' => 8,
            'more_compound' => 'true',
        ];

        if ($center !== null) {
            $params['location'] = $center['lat'].','.$center['lng'];
            $params['radius'] = 80;
        }

        $payload = $this->get('/Place/AutoComplete', $params);
        if ($payload === null || ($payload['status'] ?? '') !== 'OK') {
            return [];
        }

        $results = [];
        foreach ($payload['predictions'] ?? [] as $prediction) {
            if (! is_array($prediction)) {
                continue;
            }

            $mapped = $this->mapPrediction($prediction);
            if ($mapped !== null) {
                $results[] = $mapped;
            }
        }

        return array_slice($results, 0, 8);
    }

    /** @return list<array<string, mixed>> */
    private function searchViaGeocode(string $input, string $province): array
    {
        $address = $input;
        if ($province !== '') {
            $label = ProvinceCenters::searchLabelFor($province);
            if (! str_contains(mb_strtolower($address), mb_strtolower($label))) {
                $address .= ', '.$label;
            }
        }

        $payload = $this->get('/Geocode', [
            'address' => $address,
            'api_key' => $this->apiKey(),
        ]);

        if ($payload === null || ($payload['status'] ?? '') !== 'OK') {
            return [];
        }

        $rows = $payload['results'] ?? [];
        if (! is_array($rows)) {
            return [];
        }

        $results = [];
        foreach (array_slice($rows, 0, 8) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $formatted = trim((string) ($row['formatted_address'] ?? ''));
            $location = $row['geometry']['location'] ?? null;
            if ($formatted === '' || ! is_array($location)) {
                continue;
            }

            $lat = isset($location['lat']) ? (float) $location['lat'] : null;
            $lon = isset($location['lng']) ? (float) $location['lng'] : null;
            if ($lat === null || $lon === null) {
                continue;
            }

            $placeId = trim((string) ($row['place_id'] ?? ''));
            $presented = $this->presentResult($row, $formatted, $lat, $lon, '');
            $presented['place_id'] = $placeId;

            $results[] = $presented;
        }

        return $results;
    }

    private function inputWithProvince(string $input, string $province): string
    {
        $province = trim($province);
        if ($province === '') {
            return '';
        }

        $label = ProvinceCenters::searchLabelFor($province);
        $lower = mb_strtolower($input);

        if (str_contains($lower, mb_strtolower($label))
            || ($province === 'TP.HCM' && (str_contains($lower, 'ho chi minh') || str_contains($lower, 'hồ chí minh')))) {
            return '';
        }

        return $input.', '.$label;
    }

    /** @param array<string, mixed> $prediction */
    private function mapPrediction(array $prediction): ?array
    {
        $placeId = trim((string) ($prediction['place_id'] ?? ''));
        $description = trim((string) ($prediction['description'] ?? ''));
        if ($placeId === '' || $description === '') {
            return null;
        }

        $structured = is_array($prediction['structured_formatting'] ?? null)
            ? $prediction['structured_formatting']
            : [];

        $title = trim((string) ($structured['main_text'] ?? ''));
        $subtitle = trim((string) ($structured['secondary_text'] ?? ''));
        if ($title === '') {
            $segments = array_values(array_filter(array_map('trim', explode(',', $description))));
            $title = $segments[0] ?? $description;
            $subtitle = count($segments) > 1 ? implode(', ', array_slice($segments, 1)) : '';
        }

        $kindMeta = $this->kindMeta((string) ($prediction['display_type'] ?? ''));

        return [
            'address' => $description,
            'title' => $title,
            'subtitle' => $subtitle,
            'kind' => $kindMeta['kind'],
            'kind_label' => $kindMeta['label'],
            'lat' => null,
            'lon' => null,
            'place_id' => $placeId,
        ];
    }

    /** @param array<string, mixed> $row
     * @return array{address: string, title: string, subtitle: string, kind: string, kind_label: string, lat: float, lon: float}
     */
    private function presentResult(array $row, string $address, float $lat, float $lon, string $name = ''): array
    {
        $segments = array_values(array_filter(array_map('trim', explode(',', $address))));
        $title = $name !== '' ? $name : ($segments[0] ?? $address);
        $subtitle = count($segments) > 1 ? implode(', ', array_slice($segments, 1)) : '';
        $kindMeta = $this->kindMeta((string) ($row['display_type'] ?? ''));

        return [
            'address' => $address,
            'title' => $title,
            'subtitle' => $subtitle,
            'kind' => $kindMeta['kind'],
            'kind_label' => $kindMeta['label'],
            'lat' => $lat,
            'lon' => $lon,
        ];
    }

    /** @return array{kind: string, label: string} */
    private function kindMeta(string $displayType): array
    {
        if (str_contains($displayType, 'address') || str_contains($displayType, 'expand0')) {
            return ['kind' => 'address', 'label' => 'Địa chỉ'];
        }

        if (str_contains($displayType, 'route') || str_contains($displayType, 'street')) {
            return ['kind' => 'road', 'label' => 'Đường'];
        }

        return ['kind' => 'place', 'label' => 'Địa điểm'];
    }

    /** @param array<string, mixed> $params
     * @return array<string, mixed>|null
     */
    private function get(string $path, array $params): ?array
    {
        try {
            $client = Http::timeout(4);
            if (! (bool) config('app.geocode_verify_ssl', true)) {
                $client = $client->withOptions(['verify' => false]);
            }

            $response = $client->get(self::BASE_URL.$path, $params);
        } catch (\Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $payload = $response->json();

        return is_array($payload) ? $payload : null;
    }
}
