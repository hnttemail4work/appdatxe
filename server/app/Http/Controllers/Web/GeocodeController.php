<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
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
        return [
            'User-Agent' => config('app.name') . ' (' . config('app.contact_email') . ')',
        ];
    }

    private function nominatimClient()
    {
        $client = Http::timeout(10)->withHeaders($this->nominatimHeaders());

        if (! config('app.geocode_verify_ssl', ! app()->environment('local'))) {
            $client = $client->withOptions(['verify' => false]);
        }

        return $client;
    }

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
                ', ' . self::HCM_CITY_LABEL,
                $display,
            ) ?? $display;
            $display = preg_replace(
                '/,?\s*Thủ Đức\s*$/u',
                ', ' . self::HCM_CITY_LABEL,
                $display,
            ) ?? $display;

            if (! str_contains(mb_strtolower($display), 'hồ chí minh')
                && ! str_contains(mb_strtolower($display), 'ho chi minh')) {
                $display .= ', ' . self::HCM_CITY_LABEL;
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
                $text .= ', ' . $label;
            }
        }

        if (! str_contains($lower, 'việt nam') && ! str_contains($lower, 'vietnam')) {
            $text .= ', Việt Nam';
        }

        return $text;
    }

    public function reverse(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lon' => ['required', 'numeric', 'between:-180,180'],
        ]);

        try {
            $response = $this->nominatimClient()->get('https://nominatim.openstreetmap.org/reverse', [
                'lat' => $validated['lat'],
                'lon' => $validated['lon'],
                'format' => 'json',
                'addressdetails' => 1,
                'accept-language' => 'vi',
                'zoom' => 18,
            ]);
        } catch (\Throwable) {
            return response()->json(['message' => 'Không lấy được địa chỉ.'], 502);
        }

        if (! $response->successful()) {
            return response()->json(['message' => 'Không lấy được địa chỉ.'], 502);
        }

        $data = $response->json();
        $address = $this->formatAddress(
            is_array($data) ? $data : [],
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
            'limit' => 8,
            'addressdetails' => 1,
            'accept-language' => 'vi',
        ];

        if ($province !== '' && isset(self::PROVINCE_VIEWBOXES[$province])) {
            $params['viewbox'] = self::PROVINCE_VIEWBOXES[$province];
            $params['bounded'] = 0;
        }

        try {
            $response = $this->nominatimClient()->get('https://nominatim.openstreetmap.org/search', $params);
        } catch (\Throwable) {
            return response()->json(['results' => []]);
        }

        if (! $response->successful()) {
            return response()->json(['results' => []]);
        }

        $results = $this->mapSearchResults($response->json());

        if ($results === [] && $province !== '') {
            try {
                $fallback = $this->nominatimClient()->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $this->buildSearchQuery($validated['q']),
                    'format' => 'json',
                    'countrycodes' => 'vn',
                    'limit' => 8,
                    'addressdetails' => 1,
                    'accept-language' => 'vi',
                ]);
                if ($fallback->successful()) {
                    $results = $this->mapSearchResults($fallback->json());
                }
            } catch (\Throwable) {
                // ignore fallback errors
            }
        }

        return response()->json(['results' => $results]);
    }

    private function mapSearchResults(mixed $payload): array
    {
        return collect(is_array($payload) ? $payload : [])
            ->map(function ($item) {
                if (! is_array($item)) {
                    return null;
                }

                $address = $this->formatAddress($item);

                return [
                    'address' => $address,
                    'lat' => isset($item['lat']) ? (float) $item['lat'] : null,
                    'lon' => isset($item['lon']) ? (float) $item['lon'] : null,
                ];
            })
            ->filter(fn (?array $item) => $item && $item['address'] !== '' && $item['lat'] !== null && $item['lon'] !== null)
            ->unique('address')
            ->values()
            ->take(6)
            ->all();
    }
}
