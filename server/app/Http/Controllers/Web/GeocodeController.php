<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class GeocodeController extends Controller
{
    /** Tên gửi Nominatim khi search (TP.HCM → tên đầy đủ). */
    private const PROVINCE_SEARCH_LABELS = [
        'TP.HCM' => 'Thành phố Hồ Chí Minh',
        'Bình Dương' => 'Bình Dương',
        'Đồng Nai' => 'Đồng Nai',
        'Long An' => 'Long An',
        'Tây Ninh' => 'Tây Ninh',
        'Vũng Tàu' => 'Vũng Tàu',
        'Bà Rịa' => 'Bà Rịa',
        'Phan Thiết' => 'Phan Thiết',
        'Mũi Né' => 'Mũi Né',
        'Đà Lạt' => 'Đà Lạt',
        'Mỹ Tho' => 'Mỹ Tho',
        'Bến Tre' => 'Bến Tre',
        'Vĩnh Long' => 'Vĩnh Long',
        'Cần Thơ' => 'Cần Thơ',
        'Long Xuyên' => 'Long Xuyên',
        'Châu Đốc' => 'Châu Đốc',
    ];

    /** @var array<string, string> minLon,maxLat,maxLon,minLat */
    private const PROVINCE_VIEWBOXES = [
        'TP.HCM' => '106.30,11.00,107.05,10.30',
        'Bình Dương' => '106.40,11.50,107.10,10.80',
        'Đồng Nai' => '106.60,11.20,107.50,10.50',
        'Long An' => '105.90,10.80,106.60,10.30',
        'Tây Ninh' => '105.80,11.60,106.50,10.90',
        'Vũng Tàu' => '107.00,10.60,107.40,10.20',
        'Bà Rịa' => '106.90,10.70,107.40,10.30',
        'Phan Thiết' => '108.00,11.10,108.30,10.80',
        'Mũi Né' => '108.10,11.05,108.30,10.85',
        'Đà Lạt' => '108.35,12.05,108.55,11.85',
        'Mỹ Tho' => '106.20,10.50,106.50,10.20',
        'Bến Tre' => '106.20,10.40,106.60,9.90',
        'Vĩnh Long' => '105.80,10.30,106.20,9.90',
        'Cần Thơ' => '105.60,10.20,105.90,9.90',
        'Long Xuyên' => '105.30,10.50,105.60,10.30',
        'Châu Đốc' => '104.90,10.80,105.20,10.50',
    ];

    private const HCM_CITY_LABEL = 'Thành phố Hồ Chí Minh';

    private function nominatimHeaders(): array
    {
        $name = (string) config('app.name', 'App');
        $email = (string) config('app.contact_email', 'noreply@localhost');

        return [
            'User-Agent' => $name.' ('.$email.')',
        ];
    }

    private function nominatimClient(): PendingRequest
    {
        $client = Http::timeout(10)->withHeaders($this->nominatimHeaders());

        if (! (bool) config('app.geocode_verify_ssl', true)) {
            $client = $client->withOptions(['verify' => false]);
        }

        return $client;
    }

    /** @param array<string, mixed> $params */
    private function nominatimGet(string $url, array $params): ?Response
    {
        try {
            $response = $this->nominatimClient()->get($url, $params);
        } catch (\Throwable $e) {
            return null;
        }

        return $response->successful() ? $response : null;
    }

    /** @param array<string, mixed> $data */
    private function formatAddress(array $data, ?float $lat = null, ?float $lon = null): string
    {
        if ($lat === null && isset($data['lat'])) {
            $lat = (float) $data['lat'];
        }
        if ($lon === null && isset($data['lon'])) {
            $lon = (float) $data['lon'];
        }

        $addr = $data['address'] ?? [];
        if (is_array($addr) && $addr !== []) {
            $built = $this->buildAddressFromParts($addr, $lat, $lon);
            if ($built !== '') {
                return $built;
            }
        }

        return $this->sanitizeDisplayName(
            $this->shortenDisplayName(trim((string) ($data['display_name'] ?? ''))),
            $lat,
            $lon,
        );
    }

    /** @param array<string, mixed> $addr */
    private function buildAddressFromParts(array $addr, ?float $lat = null, ?float $lon = null): string
    {
        $streetParts = array_filter([
            $addr['house_number'] ?? null,
            $addr['road'] ?? $addr['pedestrian'] ?? $addr['footway'] ?? $addr['residential'] ?? null,
        ], fn ($value) => is_string($value) && trim($value) !== '');

        $parts = [];
        if ($streetParts !== []) {
            $parts[] = implode(' ', $streetParts);
        }

        foreach (['quarter', 'suburb', 'neighbourhood', 'hamlet'] as $key) {
            if (! empty($addr[$key]) && is_string($addr[$key])) {
                $parts[] = trim($addr[$key]);
                break;
            }
        }

        foreach (['city_district', 'district', 'county', 'subdistrict'] as $key) {
            if (! empty($addr[$key]) && is_string($addr[$key])) {
                $parts[] = trim($addr[$key]);
                break;
            }
        }

        $cityLabel = $this->cityLabelForAddress($addr, $lat, $lon);
        if ($cityLabel !== '') {
            $parts[] = $cityLabel;
        }

        return implode(', ', array_values(array_unique(array_filter($parts))));
    }

    /** @param array<string, mixed> $addr */
    private function cityLabelForAddress(array $addr, ?float $lat, ?float $lon): string
    {
        if ($lat !== null && $lon !== null && $this->isInViewbox($lat, $lon, self::PROVINCE_VIEWBOXES['TP.HCM'])) {
            return self::HCM_CITY_LABEL;
        }

        foreach (['city', 'town', 'village', 'municipality'] as $key) {
            if (! empty($addr[$key]) && is_string($addr[$key])) {
                return trim($addr[$key]);
            }
        }

        return '';
    }

    private function sanitizeDisplayName(string $display, ?float $lat, ?float $lon): string
    {
        if ($display === '') {
            return '';
        }

        if ($lat !== null && $lon !== null && $this->isInViewbox($lat, $lon, self::PROVINCE_VIEWBOXES['TP.HCM'])) {
            $display = preg_replace(
                '/,?\s*Thành phố Thủ Đức\s*$/u',
                ', '.self::HCM_CITY_LABEL,
                $display,
            ) ?? $display;
            $display = preg_replace(
                '/,?\s*Thủ Đức\s*$/u',
                ', '.self::HCM_CITY_LABEL,
                $display,
            ) ?? $display;

            if (! str_contains(mb_strtolower($display), 'hồ chí minh')
                && ! str_contains(mb_strtolower($display), 'ho chi minh')) {
                $display .= ', '.self::HCM_CITY_LABEL;
            }
        }

        return $display;
    }

    private function isInViewbox(float $lat, float $lon, string $viewbox): bool
    {
        $parts = array_map('floatval', explode(',', $viewbox));
        if (count($parts) !== 4) {
            return false;
        }

        [$minLon, $maxLat, $maxLon, $minLat] = $parts;

        return $lon >= $minLon && $lon <= $maxLon && $lat >= $minLat && $lat <= $maxLat;
    }

    private function shortenDisplayName(string $display): string
    {
        if ($display === '') {
            return '';
        }

        $segments = array_values(array_filter(array_map('trim', explode(',', $display))));

        return implode(', ', array_slice($segments, 0, 4));
    }

    private function buildSearchQuery(string $query, string $province = ''): string
    {
        $text = trim($query);
        $lower = mb_strtolower($text);

        if ($province !== '') {
            $label = self::PROVINCE_SEARCH_LABELS[$province] ?? $province;
            $labelLower = mb_strtolower($label);

            if (! str_contains($lower, $labelLower)
                && ! ($province === 'TP.HCM' && (str_contains($lower, 'ho chi minh') || str_contains($lower, 'hồ chí minh') || str_contains($lower, 'sài gòn') || str_contains($lower, 'sai gon')))) {
                $text .= ', '.$label;
            }
        }

        if (! str_contains($lower, 'việt nam') && ! str_contains($lower, 'vietnam')) {
            $text .= ', Việt Nam';
        }

        return $text;
    }

    private function viewboxCenter(string $viewbox): ?array
    {
        $parts = array_map('floatval', explode(',', $viewbox));
        if (count($parts) !== 4) {
            return null;
        }

        [$minLon, $maxLat, $maxLon, $minLat] = $parts;

        return [($minLat + $maxLat) / 2, ($minLon + $maxLon) / 2];
    }

    private function distanceScore(float $lat, float $lon, string $viewbox): float
    {
        $center = $this->viewboxCenter($viewbox);
        if (! $center) {
            return 0.0;
        }

        $dlat = $lat - $center[0];
        $dlon = $lon - $center[1];

        return sqrt($dlat * $dlat + $dlon * $dlon);
    }

    /** @param array<string, mixed> $item */
    private function searchTypeScore(array $item): int
    {
        $type = (string) ($item['type'] ?? '');
        $class = (string) ($item['class'] ?? '');

        return match (true) {
            in_array($type, ['house', 'building', 'apartments', 'residential', 'terrace', 'detached'], true) => 100,
            in_array($type, ['retail', 'commercial', 'industrial', 'school', 'hospital', 'clinic', 'hotel', 'restaurant', 'cafe', 'fast_food', 'pharmacy', 'bank', 'fuel', 'parking', 'place_of_worship'], true) => 90,
            $class === 'amenity' || $class === 'shop' || $class === 'tourism' => 85,
            in_array($type, ['house_number', 'address'], true) => 80,
            in_array($type, ['road', 'pedestrian', 'footway', 'residential'], true) => 40,
            in_array($type, ['suburb', 'neighbourhood', 'quarter', 'hamlet'], true) => 20,
            default => 10,
        };
    }

    /** @param array<string, mixed> $item */
    private function isLowQualitySearchHit(array $item, string $address): bool
    {
        $type = (string) ($item['type'] ?? '');
        $class = (string) ($item['class'] ?? '');

        if ($class === 'boundary' && str_starts_with($type, 'administrative')) {
            return true;
        }

        if (in_array($type, ['country', 'state', 'region', 'county'], true)) {
            return true;
        }

        return mb_strlen(trim($address)) < 6;
    }

    /** @param array<string, mixed> $params */
    private function nominatimSearch(array $params): array
    {
        $response = $this->nominatimGet('https://nominatim.openstreetmap.org/search', $params);
        if ($response === null) {
            return [];
        }

        $payload = $response->json();

        return is_array($payload) ? $payload : [];
    }

    public function reverse(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lon' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $response = $this->nominatimGet('https://nominatim.openstreetmap.org/reverse', [
            'lat' => $validated['lat'],
            'lon' => $validated['lon'],
            'format' => 'json',
            'addressdetails' => 1,
            'accept-language' => 'vi',
            'zoom' => 19,
        ]);

        if ($response === null) {
            return response()->json(['message' => 'Không lấy được địa chỉ.'], 502);
        }

        $data = $response->json();
        /** @var array<string, mixed> $payload */
        $payload = is_array($data) ? $data : [];
        $address = $this->formatAddress(
            $payload,
            (float) $validated['lat'],
            (float) $validated['lon'],
        );

        if ($address === '') {
            return response()->json(['message' => 'Không đọc được địa chỉ tại vị trí này.'], 404);
        }

        return response()->json([
            'address' => $address,
            'lat' => (float) $validated['lat'],
            'lon' => (float) $validated['lon'],
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:200'],
            'province' => ['nullable', 'string', 'max:100'],
        ]);

        $province = trim((string) ($validated['province'] ?? ''));
        $searchText = $this->buildSearchQuery($validated['q'], $province);

        $params = [
            'q' => $searchText,
            'format' => 'json',
            'countrycodes' => 'vn',
            'limit' => 12,
            'addressdetails' => 1,
            'accept-language' => 'vi',
            'dedupe' => 1,
        ];

        if ($province !== '' && isset(self::PROVINCE_VIEWBOXES[$province])) {
            $params['viewbox'] = self::PROVINCE_VIEWBOXES[$province];
            $params['bounded'] = 1;
        }

        $raw = $this->nominatimSearch($params);
        $results = $this->mapSearchResults($raw, $province);

        if ($results === [] && $province !== '' && isset(self::PROVINCE_VIEWBOXES[$province])) {
            $loose = $this->nominatimSearch([
                'q' => $this->buildSearchQuery($validated['q'], $province),
                'format' => 'json',
                'countrycodes' => 'vn',
                'limit' => 12,
                'addressdetails' => 1,
                'accept-language' => 'vi',
                'viewbox' => self::PROVINCE_VIEWBOXES[$province],
                'bounded' => 0,
            ]);
            $results = $this->mapSearchResults($loose, $province);
        }

        if ($results === [] && $province !== '') {
            $fallback = $this->nominatimSearch([
                'q' => $this->buildSearchQuery($validated['q']),
                'format' => 'json',
                'countrycodes' => 'vn',
                'limit' => 12,
                'addressdetails' => 1,
                'accept-language' => 'vi',
            ]);
            $results = $this->mapSearchResults($fallback, $province);
        }

        return response()->json(['results' => $results]);
    }

    /** @param list<array<string, mixed>> $payload
     * @return list<array{address: string, lat: float, lon: float}>
     */
    private function mapSearchResults(array $payload, string $province = ''): array
    {
        $viewbox = ($province !== '' && isset(self::PROVINCE_VIEWBOXES[$province]))
            ? self::PROVINCE_VIEWBOXES[$province]
            : null;

        /** @var list<array{address: string, lat: float, lon: float, _importance: float, _key: string}> $candidates */
        $candidates = [];

        foreach ($payload as $row) {
            if (! is_array($row)) {
                continue;
            }

            $lat = isset($row['lat']) ? (float) $row['lat'] : null;
            $lon = isset($row['lon']) ? (float) $row['lon'] : null;
            $address = $this->formatAddress($row);

            if ($address === '' || $lat === null || $lon === null) {
                continue;
            }

            if ($this->isLowQualitySearchHit($row, $address)) {
                continue;
            }

            if ($viewbox !== null && ! $this->isInViewbox($lat, $lon, $viewbox)) {
                continue;
            }

            $candidates[] = [
                'address' => $address,
                'lat' => $lat,
                'lon' => $lon,
                '_importance' => (float) ($row['importance'] ?? 0),
                '_type_score' => $this->searchTypeScore($row),
                '_key' => round($lat, 4).','.round($lon, 4),
            ];
        }

        $unique = [];
        foreach ($candidates as $candidate) {
            $unique[$candidate['_key']] = $candidate;
        }
        $candidates = array_values($unique);

        usort($candidates, function (array $a, array $b) use ($viewbox): int {
            $typeScore = $b['_type_score'] <=> $a['_type_score'];
            if ($typeScore !== 0) {
                return $typeScore;
            }

            $importance = $b['_importance'] <=> $a['_importance'];
            if ($importance !== 0) {
                return $importance;
            }

            if ($viewbox !== null) {
                return $this->distanceScore($a['lat'], $a['lon'], $viewbox)
                    <=> $this->distanceScore($b['lat'], $b['lon'], $viewbox);
            }

            return 0;
        });

        $results = [];
        foreach (array_slice($candidates, 0, 6) as $candidate) {
            $results[] = [
                'address' => $candidate['address'],
                'lat' => $candidate['lat'],
                'lon' => $candidate['lon'],
            ];
        }

        return $results;
    }
}
