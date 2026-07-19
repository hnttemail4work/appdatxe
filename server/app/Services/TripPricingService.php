<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\ScheduleTemplate;
use App\Models\TripRoute;
use App\Support\LocationCatalog;
use App\Support\PlatformFees;
use App\Support\PriceQuote;
use App\Support\PricingConfig;
use App\Support\ProvinceCenters;
use App\Support\RouteDistanceCatalog;
use Carbon\CarbonInterface;

/** Giá cả xe một chiều — distance helpers + facade sang PricingEngine. */
class TripPricingService
{
    /** @deprecated Dùng PricingConfig::intraFlatMaxKm() */
    public const INTRA_PROVINCE_FLAT_MAX_KM = 3;

    /** @deprecated Dùng PricingConfig::intraFlatPrice() */
    public const INTRA_PROVINCE_FLAT_PRICE = 30_000;

    public const REFERENCE_WHOLE_CAR = 1_000_000;

    public const REFERENCE_CAPACITY = 4;

    public const REFERENCE_DISTANCE_KM = 100;

    private ?PricingEngine $engine = null;

    private function engine(): PricingEngine
    {
        return $this->engine ??= app(PricingEngine::class);
    }

    /** @return array<string, mixed> */
    public function quote(
        ScheduleTemplate $template,
        ?string $pickup = null,
        ?string $dropoff = null,
        ?float $pickupLat = null,
        ?float $pickupLng = null,
        ?float $dropoffLat = null,
        ?float $dropoffLng = null,
        ?CarbonInterface $at = null,
    ): array {
        $template->loadMissing(['route', 'vehicle']);

        if ($pickup && $dropoff && $template->vehicle) {
            return $this->quoteForVehicle(
                $template->vehicle,
                $pickup,
                $dropoff,
                $pickupLat,
                $pickupLng,
                $dropoffLat,
                $dropoffLng,
                $at,
            )->toApiArray();
        }

        $quote = $this->buildQuoteForEntity($template, $pickup, $dropoff, $pickupLat, $pickupLng, $dropoffLat, $dropoffLng, $at);

        return $quote->toApiArray();
    }

    public function quoteDetailed(
        ScheduleTemplate|Schedule $entity,
        ?string $pickup = null,
        ?string $dropoff = null,
        ?float $pickupLat = null,
        ?float $pickupLng = null,
        ?float $dropoffLat = null,
        ?float $dropoffLng = null,
        ?CarbonInterface $at = null,
    ): PriceQuote {
        $entity->loadMissing(['route', 'vehicle']);

        if ($pickup && $dropoff && $entity->vehicle) {
            return $this->quoteForVehicle(
                $entity->vehicle,
                $pickup,
                $dropoff,
                $pickupLat,
                $pickupLng,
                $dropoffLat,
                $dropoffLng,
                $at,
            );
        }

        return $this->buildQuoteForEntity($entity, $pickup, $dropoff, $pickupLat, $pickupLng, $dropoffLat, $dropoffLng, $at);
    }

    public function bookingTotal(
        ScheduleTemplate|Schedule $entity,
        ?string $pickup = null,
        ?string $dropoff = null,
        ?float $pickupLat = null,
        ?float $pickupLng = null,
        ?float $dropoffLat = null,
        ?float $dropoffLng = null,
        ?CarbonInterface $at = null,
    ): float {
        return (float) $this->quoteDetailed($entity, $pickup, $dropoff, $pickupLat, $pickupLng, $dropoffLat, $dropoffLng, $at)->priceSubtotal;
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
        return $this->quoteDetailed($entity, $pickup, $dropoff, $pickupLat, $pickupLng, $dropoffLat, $dropoffLng)->priceSubtotal;
    }

    public function quoteForVehicle(
        \App\Models\Vehicle $vehicle,
        string $pickup,
        string $dropoff,
        ?float $pickupLat = null,
        ?float $pickupLng = null,
        ?float $dropoffLat = null,
        ?float $dropoffLng = null,
        ?CarbonInterface $at = null,
    ): PriceQuote {
        return $this->quoteForVehicleType(
            $pickup,
            $dropoff,
            (int) $vehicle->capacity,
            $vehicle->type ? (string) $vehicle->type : null,
            $pickupLat,
            $pickupLng,
            $dropoffLat,
            $dropoffLng,
            $at,
        );
    }

    /** Báo giá theo loại xe/số chỗ — không phụ thuộc template/tài xế. */
    public function quoteForVehicleType(
        string $pickup,
        string $dropoff,
        int $capacity,
        ?string $vehicleType = null,
        ?float $pickupLat = null,
        ?float $pickupLng = null,
        ?float $dropoffLat = null,
        ?float $dropoffLng = null,
        ?CarbonInterface $at = null,
    ): PriceQuote {
        $pickup = trim($pickup);
        $dropoff = trim($dropoff);
        $distanceKm = $this->resolveRouteDistanceKm($pickup, $dropoff, $pickupLat, $pickupLng, $dropoffLat, $dropoffLng);

        return $this->engine()->quoteForRoute(
            $pickup,
            $dropoff,
            max(1, $distanceKm),
            $vehicleType,
            $capacity,
            $at,
        );
    }

    public function wholeCarOneWayFromDistance(float $distanceKm, int $capacity, ?string $vehicleType = null): int
    {
        return $this->engine()->quoteForRoute(
            'A',
            'B',
            (int) max(1, round($distanceKm)),
            $vehicleType,
            $capacity,
        )->priceSubtotal;
    }

    public function isIntraProvinceRoute(?string $pickup, ?string $dropoff): bool
    {
        $pickup = trim((string) $pickup);
        $dropoff = trim((string) $dropoff);

        return $pickup !== '' && $pickup === $dropoff;
    }

    public function wholeCarPriceForRoute(
        string $pickup,
        string $dropoff,
        int $distanceKm,
        int $capacity,
        ?string $vehicleType = null,
    ): int {
        return $this->engine()->quoteForRoute(
            $pickup,
            $dropoff,
            $distanceKm,
            $vehicleType,
            $capacity,
        )->priceSubtotal;
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

    public function defaultWholeCarPrice(int $capacity, ?string $vehicleType = null): int
    {
        return $this->wholeCarOneWayFromDistance(100, $capacity, $vehicleType);
    }

    public function roundToThousand(float $amount): int
    {
        return PlatformFees::roundDisplayPrice($amount);
    }

    private function buildQuoteForEntity(
        ScheduleTemplate|Schedule $entity,
        ?string $pickup,
        ?string $dropoff,
        ?float $pickupLat,
        ?float $pickupLng,
        ?float $dropoffLat,
        ?float $dropoffLng,
        ?CarbonInterface $at,
    ): PriceQuote {
        $capacity = $entity instanceof ScheduleTemplate
            ? $entity->capacity()
            : (int) ($entity->vehicle?->capacity ?? 4);
        $vehicleType = $entity->vehicle?->type ? (string) $entity->vehicle->type : null;

        if ($pickup && $dropoff) {
            $distanceKm = $this->resolveRouteDistanceKm($pickup, $dropoff, $pickupLat, $pickupLng, $dropoffLat, $dropoffLng);
            if ($distanceKm > 0) {
                return $this->engine()->quoteForRoute($pickup, $dropoff, $distanceKm, $vehicleType, $capacity, $at);
            }
        }

        $configured = (int) ($entity->whole_car_price ?? 0);
        if ($configured > 0) {
            $rounded = $this->roundToThousand($configured);

            return new PriceQuote(
                distanceKm: 0,
                priceBase: $rounded,
                usedIntraFlat: false,
                vehicleTypeKey: $vehicleType,
                vehicleMultiplier: 1.0,
                priceVehicle: $rounded,
                surchargeHoliday: 0,
                surchargePeak: 0,
                surchargeRain: 0,
                tollAmount: 0,
                priceSubtotal: $rounded,
                totalPrice: $rounded,
                meta: ['source' => 'configured_whole_car_price'],
            );
        }

        $distanceKm = (int) $this->resolveDistanceKm($entity->route, $pickup, $dropoff, $pickupLat, $pickupLng, $dropoffLat, $dropoffLng);
        if ($distanceKm > 0) {
            return $this->engine()->quoteForRoute(
                $pickup ?: (string) ($entity->route?->departure ?? 'A'),
                $dropoff ?: (string) ($entity->route?->destination ?? 'B'),
                $distanceKm,
                $vehicleType,
                $capacity,
                $at,
            );
        }

        $routeBase = $this->roundToThousand((float) ($entity->route?->base_price ?? 0));

        return new PriceQuote(
            distanceKm: 0,
            priceBase: max($routeBase, 0),
            usedIntraFlat: false,
            vehicleTypeKey: $vehicleType,
            vehicleMultiplier: 1.0,
            priceVehicle: max($routeBase, 0),
            surchargeHoliday: 0,
            surchargePeak: 0,
            surchargeRain: 0,
            tollAmount: 0,
            priceSubtotal: max($routeBase, 0),
            totalPrice: max($routeBase, 0),
            meta: ['source' => 'route_base_price'],
        );
    }
}
