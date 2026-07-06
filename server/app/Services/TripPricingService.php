<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\ScheduleTemplate;
use App\Models\TripRoute;
use App\Support\DeparturePlan;
use App\Support\LocationCatalog;
use App\Support\PlatformFees;
use App\Support\ProvinceCenters;
use App\Support\RouteDistanceCatalog;
use App\Support\VehicleCapacityPricing;

/** Giá thuê cả xe một chiều theo km / cấu hình template. */
class TripPricingService
{
    /** Giá tham chiếu: thuê cả xe 4 chỗ, ~100 km. */
    public const REFERENCE_WHOLE_CAR = 1_000_000;
    public const REFERENCE_CAPACITY = 4;
    public const REFERENCE_DISTANCE_KM = 100;

    /** Cùng tỉnh/thành — quãng đường tối đa (km) áp giá cố định. */
    public const INTRA_PROVINCE_FLAT_MAX_KM = 3;

    /** Cùng tỉnh/thành — giá thuê cả xe cho chuyến ngắn (≤ {@see INTRA_PROVINCE_FLAT_MAX_KM} km). */
    public const INTRA_PROVINCE_FLAT_PRICE = 30_000;

    /** @return array<string, mixed> */
    public function quote(
        ScheduleTemplate $template,
        ?string $pickup = null,
        ?string $dropoff = null,
        ?float $pickupLat = null,
        ?float $pickupLng = null,
        ?float $dropoffLat = null,
        ?float $dropoffLng = null,
        ?string $departurePlan = null,
        ?int $laterReturnDays = null,
    ): array {
        $template->loadMissing(['route', 'vehicle']);
        $plan = DeparturePlan::normalize($departurePlan);

        if ($pickup && $dropoff && $template->vehicle) {
            return $this->applyDeparturePlanToQuote(
                $this->quoteForVehicle($template->vehicle, $pickup, $dropoff, $pickupLat, $pickupLng, $dropoffLat, $dropoffLng),
                $plan,
                $laterReturnDays,
            );
        }

        $wholeCar = $this->oneWayWholeCarPrice($template, $pickup, $dropoff, $pickupLat, $pickupLng, $dropoffLat, $dropoffLng);
        $distance = $this->resolveDistanceKm($template->route, $pickup, $dropoff, $pickupLat, $pickupLng, $dropoffLat, $dropoffLng);

        return $this->applyDeparturePlanToQuote([
            'distance_km'      => $distance,
            'whole_car_price'  => $wholeCar,
            'unit_price'       => $wholeCar,
        ], $plan, $laterReturnDays);
    }

    public function bookingTotal(
        ScheduleTemplate|Schedule $entity,
        ?string $pickup = null,
        ?string $dropoff = null,
        ?float $pickupLat = null,
        ?float $pickupLng = null,
        ?float $dropoffLat = null,
        ?float $dropoffLng = null,
        ?string $departurePlan = null,
        ?int $laterReturnDays = null,
    ): float {
        $base = $this->oneWayWholeCarPrice($entity, $pickup, $dropoff, $pickupLat, $pickupLng, $dropoffLat, $dropoffLng);

        return (float) $this->priceWithDeparturePlan($base, $departurePlan, $laterReturnDays);
    }

    public function oneWayWholeCarPrice(
        ScheduleTemplate|Schedule $entity,
        ?string $pickup = null,
        ?string $dropoff = null,
        ?float $pickupLat = null,
        ?float $pickupLng = null,
        ?float $dropoffLat = null,
        ?float $dropoffLng = null,
    ): int {
        $entity->loadMissing(['route', 'vehicle']);
        $capacity = $entity instanceof ScheduleTemplate
            ? $entity->capacity()
            : (int) ($entity->vehicle?->capacity ?? 4);

        if ($pickup && $dropoff) {
            $distanceKm = $this->resolveRouteDistanceKm($pickup, $dropoff, $pickupLat, $pickupLng, $dropoffLat, $dropoffLng);
            if ($distanceKm > 0) {
                return $this->wholeCarPriceForRoute($pickup, $dropoff, $distanceKm, $capacity);
            }
        }

        $configured = (int) ($entity->whole_car_price ?? 0);

        if ($configured > 0) {
            return $this->roundToThousand($configured);
        }

        $distanceKm = $this->resolveDistanceKm($entity->route, $pickup, $dropoff, $pickupLat, $pickupLng, $dropoffLat, $dropoffLng);
        if ($distanceKm > 0) {
            return $this->wholeCarOneWayFromDistance((int) $distanceKm, $capacity);
        }

        $routeBase = $this->roundToThousand((float) ($entity->route?->base_price ?? 0));

        return max($routeBase, 0);
    }

    /** @return array<string, mixed> */
    public function quoteForVehicle(
        \App\Models\Vehicle $vehicle,
        string $pickup,
        string $dropoff,
        ?float $pickupLat = null,
        ?float $pickupLng = null,
        ?float $dropoffLat = null,
        ?float $dropoffLng = null,
    ): array {
        $pickup = trim($pickup);
        $dropoff = trim($dropoff);
        $distanceKm = $this->resolveRouteDistanceKm($pickup, $dropoff, $pickupLat, $pickupLng, $dropoffLat, $dropoffLng);

        $wholeCar = $this->wholeCarPriceForRoute(
            $pickup,
            $dropoff,
            max(1, $distanceKm),
            (int) $vehicle->capacity,
        );

        return [
            'distance_km'     => $distanceKm,
            'whole_car_price' => $wholeCar,
            'unit_price'      => $wholeCar,
        ];
    }

    public function wholeCarOneWayFromDistance(float $distanceKm, int $capacity): int
    {
        $base = PlatformFees::wholeCarBaseFromDistanceKm($distanceKm);
        $multiplier = VehicleCapacityPricing::multiplierForCapacity($capacity);

        return $this->roundToThousand($base * $multiplier);
    }

    public function isIntraProvinceRoute(?string $pickup, ?string $dropoff): bool
    {
        $pickup = trim((string) $pickup);
        $dropoff = trim((string) $dropoff);

        return $pickup !== '' && $pickup === $dropoff;
    }

    public function wholeCarPriceForRoute(string $pickup, string $dropoff, int $distanceKm, int $capacity): int
    {
        if ($this->isIntraProvinceRoute($pickup, $dropoff)
            && $distanceKm > 0
            && $distanceKm <= self::INTRA_PROVINCE_FLAT_MAX_KM) {
            return self::INTRA_PROVINCE_FLAT_PRICE;
        }

        return $this->wholeCarOneWayFromDistance($distanceKm, $capacity);
    }

    public function resolveDistanceKm(
        ?TripRoute $route,
        ?string $pickup = null,
        ?string $dropoff = null,
        ?float $pickupLat = null,
        ?float $pickupLng = null,
        ?float $dropoffLat = null,
        ?float $dropoffLng = null,
    ): float {
        if (! $route) {
            return 0.0;
        }

        $pickup = $pickup ?: $route->departure;
        $dropoff = $dropoff ?: $route->destination;

        $resolved = $this->resolveRouteDistanceKm($pickup, $dropoff, $pickupLat, $pickupLng, $dropoffLat, $dropoffLng);
        if ($resolved > 0) {
            return (float) $resolved;
        }

        if ($pickup === $route->departure && $dropoff === $route->destination) {
            return (float) ($route->distance_km ?? 0);
        }

        return LocationCatalog::estimateDistanceKm($pickup, $dropoff);
    }

    public function resolveRouteDistanceKm(
        string $pickup,
        string $dropoff,
        ?float $pickupLat = null,
        ?float $pickupLng = null,
        ?float $dropoffLat = null,
        ?float $dropoffLng = null,
    ): int {
        $pickup = trim($pickup);
        $dropoff = trim($dropoff);

        if ($pickupLat !== null && $pickupLng !== null && $dropoffLat !== null && $dropoffLng !== null) {
            $km = ProvinceCenters::distanceKm($pickupLat, $pickupLng, $dropoffLat, $dropoffLng);

            return (int) max(1, (int) ceil($km));
        }

        $catalog = RouteDistanceCatalog::resolveKm($pickup, $dropoff);
        if ($catalog > 0) {
            return $catalog;
        }

        return (int) LocationCatalog::estimateDistanceKm($pickup, $dropoff);
    }

    public function defaultWholeCarPrice(int $capacity): int
    {
        return $this->wholeCarOneWayFromDistance(100, $capacity);
    }

    public function roundToThousand(float $amount): int
    {
        return PlatformFees::roundDisplayPrice($amount);
    }

    public function priceWithDeparturePlan(int|float $basePrice, ?string $departurePlan, ?int $laterReturnDays = null): int
    {
        $multiplier = DeparturePlan::priceMultiplier((string) $departurePlan, $laterReturnDays);

        return $this->roundToThousand((float) $basePrice * $multiplier);
    }

    /** @param  array<string, mixed>  $quote */
    private function applyDeparturePlanToQuote(array $quote, string $plan, ?int $laterReturnDays = null): array
    {
        $base = (int) ($quote['whole_car_price'] ?? 0);
        $normalizedDays = $plan === DeparturePlan::LATER
            ? DeparturePlan::normalizeLaterReturnDays($laterReturnDays)
            : null;
        $adjusted = $this->priceWithDeparturePlan($base, $plan, $normalizedDays);

        return array_merge($quote, [
            'base_whole_car_price' => $base,
            'departure_plan'       => $plan,
            'departure_plan_label' => DeparturePlan::displayLabel($plan, $normalizedDays),
            'later_return_days'    => $normalizedDays,
            'surcharge_percent'    => DeparturePlan::surchargePercent($plan, $normalizedDays),
            'whole_car_price'      => $adjusted,
            'unit_price'           => $adjusted,
        ]);
    }
}
