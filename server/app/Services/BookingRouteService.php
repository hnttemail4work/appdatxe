<?php

namespace App\Services;

use App\Models\TripRoute;
use App\Support\LocationCatalog;
use App\Support\ProvinceCenters;
use App\Support\RouteDistanceCatalog;
use InvalidArgumentException;

class BookingRouteService
{
    public function resolve(
        string $departure,
        string $destination,
        ?float $pickupLat = null,
        ?float $pickupLng = null,
        ?float $dropoffLat = null,
        ?float $dropoffLng = null,
    ): TripRoute {
        $departure = trim($departure);
        $destination = trim($destination);

        if ($departure === '' || $destination === '') {
            throw new InvalidArgumentException('Vui lòng chọn điểm đi và điểm đến.');
        }

        $coordDistance = $this->distanceFromCoords($pickupLat, $pickupLng, $dropoffLat, $dropoffLng);

        if ($departure === $destination) {
            return TripRoute::query()->firstOrCreate(
                ['departure' => $departure, 'destination' => $destination],
                ['distance_km' => $coordDistance ?? 1, 'base_price' => 0, 'is_active' => true],
            );
        }

        $existing = TripRoute::query()
            ->where('departure', $departure)
            ->where('destination', $destination)
            ->first();

        if ($existing) {
            if ($coordDistance !== null && (int) $existing->distance_km <= 0) {
                $existing->update(['distance_km' => $coordDistance]);
            }

            return $existing->fresh() ?? $existing;
        }

        $distanceKm = $coordDistance;
        if ($distanceKm === null || $distanceKm <= 0) {
            $distanceKm = RouteDistanceCatalog::resolveKm($departure, $destination);
        }
        if ($distanceKm <= 0) {
            $distanceKm = max(1, (int) LocationCatalog::estimateDistanceKm($departure, $destination));
        }

        return TripRoute::query()->create([
            'departure'   => $departure,
            'destination' => $destination,
            'distance_km' => $distanceKm,
            'base_price'  => 0,
            'is_active'   => true,
        ]);
    }

    private function distanceFromCoords(
        ?float $pickupLat,
        ?float $pickupLng,
        ?float $dropoffLat,
        ?float $dropoffLng,
    ): ?int {
        if ($pickupLat === null || $pickupLng === null || $dropoffLat === null || $dropoffLng === null) {
            return null;
        }

        return max(1, (int) ceil(ProvinceCenters::distanceKm($pickupLat, $pickupLng, $dropoffLat, $dropoffLng)));
    }
}
