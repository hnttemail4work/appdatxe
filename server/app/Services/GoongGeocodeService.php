<?php

namespace App\Services;

use App\Support\ProvinceCenters;
use App\Support\ProvinceResolver;
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

        return $this->searchViaGeocode($input, $province);
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
            $presented['lat'] = null;
            $presented['lon'] = null;
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
