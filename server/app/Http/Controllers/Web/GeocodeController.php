<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Geocode\DirectionGeocodeRequest;
use App\Http\Requests\Geocode\NearbyGeocodeRequest;
use App\Http\Requests\Geocode\ResolvePlaceRequest;
use App\Http\Requests\Geocode\ReverseGeocodeRequest;
use App\Http\Requests\Geocode\SearchGeocodeRequest;
use App\Services\GoongGeocodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class GeocodeController extends Controller
{
    public function __construct(
        private GoongGeocodeService $goongGeocode,
    ) {
    }

    private function ensureConfigured(): ?JsonResponse
    {
        if ($this->goongGeocode->isConfigured()) {
            return null;
        }

        return response()->json(['message' => 'Chưa cấu hình GOONG_API_KEY.'], 503);
    }

    public function reverse(ReverseGeocodeRequest $request): JsonResponse
    {
        if ($error = $this->ensureConfigured()) {
            return $error;
        }

        $validated = $request->validated();

        $result = $this->goongGeocode->reverse(
            (float) $validated['lat'],
            (float) $validated['lon'],
        );

        if ($result === null) {
            return response()->json(['message' => 'Không lấy được địa chỉ.'], 502);
        }

        return response()->json($result);
    }

    public function search(SearchGeocodeRequest $request): JsonResponse
    {
        if ($error = $this->ensureConfigured()) {
            return $error;
        }

        $validated = $request->validated();

        $province = trim((string) ($validated['province'] ?? ''));
        $query = $validated['q'];

        $cacheKey = 'geocode_search:goong:v2:'.hash('xxh128', mb_strtolower(trim($query)).'|'.$province);
        $results = Cache::remember($cacheKey, now()->addMinutes(15), function () use ($query, $province): array {
            return $this->goongGeocode->search($query, $province);
        });

        return response()->json(['results' => $results]);
    }

    public function resolve(ResolvePlaceRequest $request): JsonResponse
    {
        if ($error = $this->ensureConfigured()) {
            return $error;
        }

        $validated = $request->validated();

        $result = $this->goongGeocode->resolvePlaceId($validated['place_id']);
        if ($result === null) {
            return response()->json(['message' => 'Không lấy được vị trí địa điểm.'], 404);
        }

        return response()->json($result);
    }

    public function direction(DirectionGeocodeRequest $request): JsonResponse
    {
        if ($error = $this->ensureConfigured()) {
            return $error;
        }

        $validated = $request->validated();

        $result = $this->goongGeocode->direction(
            (float) $validated['origin_lat'],
            (float) $validated['origin_lng'],
            (float) $validated['dest_lat'],
            (float) $validated['dest_lng'],
        );

        if ($result === null) {
            return response()->json(['message' => 'Không lấy được lộ trình.'], 502);
        }

        return response()->json($result);
    }

    public function nearby(NearbyGeocodeRequest $request): JsonResponse
    {
        if ($error = $this->ensureConfigured()) {
            return $error;
        }

        $validated = $request->validated();
        $lat = (float) $validated['lat'];
        $lng = (float) $validated['lng'];
        $radius = (int) ($validated['radius_m'] ?? 300);

        $cacheKey = 'geocode_nearby:v2:'.hash('xxh128', round($lat, 5).'|'.round($lng, 5).'|'.$radius);
        $results = Cache::remember($cacheKey, now()->addMinutes(10), function () use ($lat, $lng, $radius): array {
            return $this->goongGeocode->nearby($lat, $lng, $radius);
        });

        return response()->json(['results' => $results]);
    }
}
