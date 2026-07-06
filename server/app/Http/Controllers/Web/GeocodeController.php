<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\GeocodeSearchAliases;
use App\Support\ProvinceCenters;
use App\Support\ProvinceResolver;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class GeocodeController extends Controller
{
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

        $name = trim((string) ($data['name'] ?? ''));
        if ($name !== '') {
            $cityLabel = is_array($addr) && $addr !== []
                ? $this->cityLabelForAddress($addr, $lat, $lon)
                : '';

            if ($cityLabel !== '' && ! str_contains(mb_strtolower($name), mb_strtolower($cityLabel))) {
                return $name.', '.$cityLabel;
            }

            return $name;
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

        foreach (['city_district', 'district', 'county', 'subdistrict', 'historic'] as $key) {
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
        if ($lat !== null && $lon !== null) {
            $hcmViewbox = ProvinceCenters::viewboxFor('TP.HCM');
            if ($hcmViewbox !== null && $this->isInViewbox($lat, $lon, $hcmViewbox)) {
                return self::HCM_CITY_LABEL;
            }
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

        if ($lat !== null && $lon !== null) {
            $hcmViewbox = ProvinceCenters::viewboxFor('TP.HCM');
            if ($hcmViewbox !== null && $this->isInViewbox($lat, $lon, $hcmViewbox)) {
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
        $text = $this->normalizeVietnameseQuery($query);
        $lower = mb_strtolower($text);

        if ($province !== '') {
            $label = ProvinceCenters::searchLabelFor($province);
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

    private function normalizeVietnameseQuery(string $query): string
    {
        $text = trim($query);
        if ($text === '') {
            return '';
        }

        $text = preg_replace('/\bquan\s*(\d{1,2})\b/iu', 'Quận $1', $text) ?? $text;
        $text = preg_replace('/\bq\.?\s*(\d{1,2})\b/iu', 'Quận $1', $text) ?? $text;
        $text = preg_replace('/\bphuong\s+/iu', 'Phường ', $text) ?? $text;
        $text = preg_replace('/\bp\.?\s+/iu', 'Phường ', $text) ?? $text;
        $text = preg_replace('/\bhuyen\s+/iu', 'Huyện ', $text) ?? $text;
        $text = preg_replace('/\bthi\s*xa\s+/iu', 'Thị xã ', $text) ?? $text;
        $text = preg_replace('/\bduong\s+/iu', 'Đường ', $text) ?? $text;
        $text = preg_replace('/\bd\.?\s+/iu', 'Đường ', $text) ?? $text;
        $text = preg_replace('/\bngo\s+/iu', 'Ngõ ', $text) ?? $text;
        $text = preg_replace('/\bhem\s+/iu', 'Hẻm ', $text) ?? $text;
        $text = preg_replace('/\btx\.?\s+/iu', 'Thị xã ', $text) ?? $text;
        $text = preg_replace('/\bsn\s+/iu', 'Số ', $text) ?? $text;

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    private function asciiSearchVariant(string $query): string
    {
        $folded = trim(preg_replace('/\s+/u', ' ', Str::ascii($query)) ?? '');

        return $folded;
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
            in_array($type, ['suburb', 'neighbourhood', 'quarter', 'hamlet'], true) => 55,
            $class === 'boundary' && in_array($type, ['administrative', 'historic'], true) => 50,
            default => 10,
        };
    }

    /** @param array<string, mixed> $item */
    private function isUsefulAreaSearchHit(array $item): bool
    {
        $type = (string) ($item['type'] ?? '');
        $class = (string) ($item['class'] ?? '');
        $addresstype = (string) ($item['addresstype'] ?? '');

        if ($class === 'boundary' && $type === 'historic') {
            return true;
        }

        if ($class !== 'boundary' || $type !== 'administrative') {
            return false;
        }

        return in_array($addresstype, [
            'suburb',
            'neighbourhood',
            'quarter',
            'city_district',
            'borough',
            'district',
            'hamlet',
            'town',
            'village',
        ], true);
    }

    /** @param array<string, mixed> $item */
    private function isLowQualitySearchHit(array $item, string $address): bool
    {
        $type = (string) ($item['type'] ?? '');
        $class = (string) ($item['class'] ?? '');

        if ($this->isUsefulAreaSearchHit($item)) {
            return mb_strlen(trim($address)) < 4;
        }

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
            'province' => ProvinceResolver::fromMapPick(
                (float) $validated['lat'],
                (float) $validated['lon'],
                $address,
            ) ?? '',
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:200'],
            'province' => ['nullable', 'string', 'max:100'],
        ]);

        $province = trim((string) ($validated['province'] ?? ''));
        $results = $this->runSearchVariants($validated['q'], $province);

        return response()->json(['results' => $results]);
    }

    /** @return list<array{address: string, title: string, subtitle: string, kind: string, kind_label: string, lat: float, lon: float}> */
    private function runSearchVariants(string $query, string $province = ''): array
    {
        $variants = array_values(array_unique(array_filter(array_merge(
            [
                $this->normalizeVietnameseQuery($query),
                $this->asciiSearchVariant($query),
                trim($query),
            ],
            GeocodeSearchAliases::variants($query, $province),
        ), fn (string $value): bool => trim($value) !== '')));

        $searchQuery = $this->normalizeVietnameseQuery($query) ?: trim($query);
        /** @var array<string, array<string, mixed>> $merged */
        $merged = [];

        foreach ($variants as $variant) {
            foreach ($this->searchWithProvince($variant, $province, $searchQuery) as $result) {
                $key = round($result['lat'], 4).','.round($result['lon'], 4);
                if (! isset($merged[$key])) {
                    $merged[$key] = $result;
                }
            }
        }

        $results = array_values($merged);
        usort($results, fn (array $a, array $b): int => ($b['_score'] ?? 0) <=> ($a['_score'] ?? 0));

        return array_map(function (array $row): array {
            unset($row['_score']);

            return $row;
        }, array_slice($results, 0, 8));
    }

    /** @return list<array<string, mixed>> */
    private function searchWithProvince(string $query, string $province = '', string $searchQuery = ''): array
    {
        $searchText = $this->buildSearchQuery($query, $province);

        $params = [
            'q' => $searchText,
            'format' => 'json',
            'countrycodes' => 'vn',
            'limit' => 15,
            'addressdetails' => 1,
            'accept-language' => 'vi',
            'dedupe' => 1,
        ];

        if ($province !== '' && ($viewbox = ProvinceCenters::viewboxFor($province)) !== null) {
            $params['viewbox'] = $viewbox;
            $params['bounded'] = 1;
        }

        $raw = $this->nominatimSearch($params);
        $results = $this->mapSearchResults($raw, $province, $searchQuery !== '' ? $searchQuery : $query);

        if ($results === [] && $province !== '' && ($viewbox = ProvinceCenters::viewboxFor($province)) !== null) {
            $loose = $this->nominatimSearch([
                'q' => $this->buildSearchQuery($query, $province),
                'format' => 'json',
                'countrycodes' => 'vn',
                'limit' => 15,
                'addressdetails' => 1,
                'accept-language' => 'vi',
                'viewbox' => $viewbox,
                'bounded' => 0,
            ]);
            $results = $this->mapSearchResults($loose, $province, $searchQuery !== '' ? $searchQuery : $query);
        }

        if ($results === [] && $province !== '') {
            $fallback = $this->nominatimSearch([
                'q' => $this->buildSearchQuery($query),
                'format' => 'json',
                'countrycodes' => 'vn',
                'limit' => 15,
                'addressdetails' => 1,
                'accept-language' => 'vi',
            ]);
            $results = $this->mapSearchResults($fallback, $province, $searchQuery !== '' ? $searchQuery : $query);
        }

        return $results;
    }

    /** @param list<array<string, mixed>> $payload
     * @return list<array<string, mixed>>
     */
    private function mapSearchResults(array $payload, string $province = '', string $searchQuery = ''): array
    {
        $viewbox = ($province !== '' && ($resolved = ProvinceCenters::viewboxFor($province)) !== null)
            ? $resolved
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

            $candidates[] = array_merge($this->presentSearchResult($row, $address), [
                'lat' => $lat,
                'lon' => $lon,
                '_importance' => (float) ($row['importance'] ?? 0),
                '_type_score' => $this->searchTypeScore($row),
                '_relevance' => $this->queryRelevanceScore($searchQuery, $address, $row),
                '_key' => round($lat, 4).','.round($lon, 4),
            ]);
        }

        $unique = [];
        foreach ($candidates as $candidate) {
            $unique[$candidate['_key']] = $candidate;
        }
        $candidates = array_values($unique);

        usort($candidates, function (array $a, array $b) use ($viewbox): int {
            $scoreA = ($a['_type_score'] * 4) + ($a['_relevance'] * 3) + ($a['_importance'] * 10);
            $scoreB = ($b['_type_score'] * 4) + ($b['_relevance'] * 3) + ($b['_importance'] * 10);
            $score = $scoreB <=> $scoreA;
            if ($score !== 0) {
                return $score;
            }

            if ($viewbox !== null) {
                return $this->distanceScore($a['lat'], $a['lon'], $viewbox)
                    <=> $this->distanceScore($b['lat'], $b['lon'], $viewbox);
            }

            return 0;
        });

        $results = [];
        foreach (array_slice($candidates, 0, 8) as $candidate) {
            $score = ($candidate['_type_score'] * 4)
                + ($candidate['_relevance'] * 3)
                + ($candidate['_importance'] * 10);
            $results[] = array_merge([
                'address' => $candidate['address'],
                'title' => $candidate['title'],
                'subtitle' => $candidate['subtitle'],
                'kind' => $candidate['kind'],
                'kind_label' => $candidate['kind_label'],
                'lat' => $candidate['lat'],
                'lon' => $candidate['lon'],
                '_score' => $score,
            ]);
        }

        return $results;
    }

    /** @param array<string, mixed> $row */
    private function presentSearchResult(array $row, string $address): array
    {
        $kindMeta = $this->searchKindMeta($row);
        $addr = is_array($row['address'] ?? null) ? $row['address'] : [];
        $name = trim((string) ($row['name'] ?? ''));

        $title = $name;
        if ($title === '' && $addr !== []) {
            $streetParts = array_filter([
                $addr['house_number'] ?? null,
                $addr['road'] ?? $addr['pedestrian'] ?? $addr['footway'] ?? $addr['residential'] ?? null,
            ], fn ($value) => is_string($value) && trim($value) !== '');
            if ($streetParts !== []) {
                $title = implode(' ', $streetParts);
            }
        }

        if ($title === '') {
            $segments = array_values(array_filter(array_map('trim', explode(',', $address))));
            $title = $segments[0] ?? $address;
        }

        $subtitleParts = [];
        if ($addr !== []) {
            foreach (['quarter', 'suburb', 'neighbourhood', 'hamlet'] as $key) {
                if (! empty($addr[$key]) && is_string($addr[$key])) {
                    $subtitleParts[] = trim($addr[$key]);
                    break;
                }
            }
            foreach (['city_district', 'district', 'county', 'subdistrict', 'historic'] as $key) {
                if (! empty($addr[$key]) && is_string($addr[$key])) {
                    $subtitleParts[] = trim($addr[$key]);
                    break;
                }
            }
            $cityLabel = $this->cityLabelForAddress(
                $addr,
                isset($row['lat']) ? (float) $row['lat'] : null,
                isset($row['lon']) ? (float) $row['lon'] : null,
            );
            if ($cityLabel !== '') {
                $subtitleParts[] = $cityLabel;
            }
        }

        $subtitle = implode(', ', array_values(array_unique(array_filter($subtitleParts))));
        if ($subtitle === '' || mb_strtolower($subtitle) === mb_strtolower($title)) {
            $segments = array_values(array_filter(array_map('trim', explode(',', $address))));
            if (count($segments) > 1) {
                $subtitle = implode(', ', array_slice($segments, 1));
            } else {
                $subtitle = '';
            }
        }

        return [
            'address' => $address,
            'title' => $title,
            'subtitle' => $subtitle,
            'kind' => $kindMeta['kind'],
            'kind_label' => $kindMeta['label'],
        ];
    }

    /** @param array<string, mixed> $row
     * @return array{kind: string, label: string}
     */
    private function searchKindMeta(array $row): array
    {
        $type = (string) ($row['type'] ?? '');
        $class = (string) ($row['class'] ?? '');

        if ($this->isUsefulAreaSearchHit($row)) {
            return ['kind' => 'area', 'label' => 'Khu vực'];
        }

        if (in_array($type, ['road', 'pedestrian', 'footway', 'residential', 'living_street', 'service'], true)) {
            return ['kind' => 'road', 'label' => 'Đường'];
        }

        if (in_array($type, ['house', 'house_number', 'building', 'apartments', 'residential', 'terrace', 'detached', 'address'], true)) {
            return ['kind' => 'address', 'label' => 'Địa chỉ'];
        }

        if ($class === 'amenity' || $class === 'shop' || $class === 'tourism' || $class === 'leisure') {
            return ['kind' => 'place', 'label' => 'Địa điểm'];
        }

        return ['kind' => 'place', 'label' => 'Địa điểm'];
    }

  /** @param array<string, mixed> $row */
    private function queryRelevanceScore(string $searchQuery, string $address, array $row): int
    {
        $query = mb_strtolower(trim($searchQuery));
        if ($query === '') {
            return 0;
        }

        $haystacks = array_filter([
            mb_strtolower((string) ($row['name'] ?? '')),
            mb_strtolower($address),
            mb_strtolower((string) ($row['display_name'] ?? '')),
            mb_strtolower($this->asciiSearchVariant($searchQuery)),
        ]);

        $tokens = array_values(array_filter(preg_split('/\s+/u', $query) ?: [], fn (string $t): bool => mb_strlen($t) >= 2));
        if ($tokens === []) {
            return 0;
        }

        $score = 0;
        foreach ($tokens as $token) {
            foreach ($haystacks as $haystack) {
                if ($haystack === '') {
                    continue;
                }
                if (str_starts_with($haystack, $token)) {
                    $score += 12;
                } elseif (str_contains($haystack, $token)) {
                    $score += 6;
                }
                $asciiToken = mb_strtolower($this->asciiSearchVariant($token));
                if ($asciiToken !== $token && str_contains($haystack, $asciiToken)) {
                    $score += 4;
                }
            }
        }

        return min($score, 120);
    }
}
