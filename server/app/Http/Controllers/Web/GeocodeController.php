<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\GoongGeocodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    public function reverse(Request $request): JsonResponse
    {
        if ($error = $this->ensureConfigured()) {
            return $error;
        }

        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lon' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $result = $this->goongGeocode->reverse(
            (float) $validated['lat'],
            (float) $validated['lon'],
        );

        if ($result === null) {
            return response()->json(['message' => 'Không lấy được địa chỉ.'], 502);
        }

        return response()->json($result);
    }

    public function search(Request $request): JsonResponse
    {
        if ($error = $this->ensureConfigured()) {
            return $error;
        }

        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:200'],
            'province' => ['nullable', 'string', 'max:100'],
        ]);

        $province = trim((string) ($validated['province'] ?? ''));
        $query = $validated['q'];

        $cacheKey = 'geocode_search:goong:'.hash('xxh128', mb_strtolower(trim($query)).'|'.$province);
        $results = Cache::remember($cacheKey, now()->addMinutes(15), function () use ($query, $province): array {
            return $this->goongGeocode->search($query, $province);
        });

        return response()->json(['results' => $results]);
    }

    public function resolve(Request $request): JsonResponse
    {
        if ($error = $this->ensureConfigured()) {
            return $error;
        }

        $validated = $request->validate([
            'place_id' => ['required', 'string', 'max:255'],
        ]);

        $result = $this->goongGeocode->resolvePlaceId($validated['place_id']);
        if ($result === null) {
            return response()->json(['message' => 'Không lấy được vị trí địa điểm.'], 404);
        }

        return response()->json($result);
    }
}
