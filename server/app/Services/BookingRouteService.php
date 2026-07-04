<?php

namespace App\Services;

use App\Models\TripRoute;
use App\Support\LocationCatalog;
use App\Support\RouteDistanceCatalog;
use InvalidArgumentException;

class BookingRouteService
{
    public function resolve(string $departure, string $destination): TripRoute
    {
        $departure = trim($departure);
        $destination = trim($destination);

        if ($departure === '' || $destination === '') {
            throw new InvalidArgumentException('Vui lòng chọn điểm đi và điểm đến.');
        }

        if ($departure === $destination) {
            throw new InvalidArgumentException('Điểm đi và điểm đến phải khác nhau.');
        }

        $existing = TripRoute::query()
            ->where('departure', $departure)
            ->where('destination', $destination)
            ->first();

        if ($existing) {
            return $existing;
        }

        $distanceKm = RouteDistanceCatalog::resolveKm($departure, $destination);
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
}
