<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\ScheduleTemplate;
use App\Models\TripRoute;
use App\Support\LocationCatalog;
use App\Support\RouteDistanceCatalog;
use App\Support\VehicleCapacityPricing;

/** Giá thuê cả xe một chiều theo km / cấu hình template. */
class TripPricingService
{
    /** Giá tham chiếu: thuê cả xe 4 chỗ, ~100 km. */
    public const REFERENCE_WHOLE_CAR = 1_000_000;
    public const REFERENCE_CAPACITY = 4;
    public const REFERENCE_DISTANCE_KM = 100;

    /** @return array<string, mixed> */
    public function quote(
        ScheduleTemplate $template,
        ?string $pickup = null,
        ?string $dropoff = null,
    ): array {
        $template->loadMissing(['route', 'vehicle']);

        if ($pickup && $dropoff && $template->vehicle) {
            return $this->quoteForVehicle($template->vehicle, $pickup, $dropoff);
        }

        $wholeCar = $this->oneWayWholeCarPrice($template, $pickup, $dropoff);
        $distance = $this->resolveDistanceKm($template->route, $pickup, $dropoff);

        return [
            'distance_km'      => $distance,
            'whole_car_price'  => $wholeCar,
            'unit_price'       => $wholeCar,
        ];
    }

    public function bookingTotal(
        ScheduleTemplate|Schedule $entity,
        ?string $pickup = null,
        ?string $dropoff = null,
    ): float {
        return (float) $this->oneWayWholeCarPrice($entity, $pickup, $dropoff);
    }

    public function oneWayWholeCarPrice(ScheduleTemplate|Schedule $entity, ?string $pickup = null, ?string $dropoff = null): int
    {
        $entity->loadMissing(['route', 'vehicle']);
        $capacity = $entity instanceof ScheduleTemplate
            ? $entity->capacity()
            : (int) ($entity->vehicle?->capacity ?? 4);

        if ($pickup && $dropoff) {
            $distanceKm = RouteDistanceCatalog::resolveKm($pickup, $dropoff);
            if ($distanceKm <= 0) {
                $distanceKm = (int) LocationCatalog::estimateDistanceKm($pickup, $dropoff);
            }
            if ($distanceKm > 0) {
                return $this->wholeCarOneWayFromDistance($distanceKm, $capacity);
            }
        }

        $configured = (int) ($entity->whole_car_price ?? 0);

        if ($configured > 0) {
            return $this->roundToThousand($configured);
        }

        $distanceKm = $this->resolveDistanceKm($entity->route, $pickup, $dropoff);
        if ($distanceKm > 0) {
            return $this->wholeCarOneWayFromDistance($distanceKm, $capacity);
        }

        $routeBase = $this->roundToThousand((float) ($entity->route?->base_price ?? 0));

        return max($routeBase, 0);
    }

    /** @return array<string, mixed> */
    public function quoteForVehicle(
        \App\Models\Vehicle $vehicle,
        string $pickup,
        string $dropoff,
    ): array {
        $pickup = trim($pickup);
        $dropoff = trim($dropoff);
        $distanceKm = RouteDistanceCatalog::resolveKm($pickup, $dropoff);
        if ($distanceKm <= 0) {
            $distanceKm = (int) LocationCatalog::estimateDistanceKm($pickup, $dropoff);
        }

        $wholeCar = $this->wholeCarOneWayFromDistance(max(1, $distanceKm), (int) $vehicle->capacity);

        return [
            'distance_km'     => $distanceKm,
            'whole_car_price' => $wholeCar,
            'unit_price'      => $wholeCar,
        ];
    }

    public function wholeCarOneWayFromDistance(float $distanceKm, int $capacity): int
    {
        $perKm = self::REFERENCE_WHOLE_CAR / max(1, self::REFERENCE_DISTANCE_KM);
        $base = $distanceKm * $perKm;
        $multiplier = VehicleCapacityPricing::multiplierForCapacity($capacity);

        return $this->roundToThousand($base * $multiplier);
    }

    public function resolveDistanceKm(?TripRoute $route, ?string $pickup = null, ?string $dropoff = null): float
    {
        if (! $route) {
            return 0.0;
        }

        $pickup = $pickup ?: $route->departure;
        $dropoff = $dropoff ?: $route->destination;

        $catalog = RouteDistanceCatalog::resolveKm($pickup, $dropoff);
        if ($catalog > 0) {
            return $catalog;
        }

        if ($pickup === $route->departure && $dropoff === $route->destination) {
            return (float) ($route->distance_km ?? 0);
        }

        return LocationCatalog::estimateDistanceKm($pickup, $dropoff);
    }

    public function defaultWholeCarPrice(int $capacity): int
    {
        return $this->wholeCarOneWayFromDistance(100, $capacity);
    }

    public function roundToThousand(float $amount): int
    {
        return (int) (ceil($amount / 1000) * 1000);
    }

    public function tripTypeLabel(string $tripType = 'one_way'): string
    {
        return 'Thuê xe';
    }
}
