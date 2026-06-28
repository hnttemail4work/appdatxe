<?php



namespace App\Services;



use App\Models\Schedule;

use App\Models\ScheduleTemplate;

use App\Models\TripRoute;

use App\Support\PlatformFees;

use App\Support\SouthernProvinces;

use App\Support\VehicleCapacityOptions;



/** Giá vé: cả xe theo số chỗ (mốc 7 chỗ = 1tr), ghép = 15% giá cả xe / ghế. */

class TripPricingService

{

    /** @deprecated Dùng PlatformFees::roundTripDiscountPercent() */

    public const ROUND_TRIP_DISCOUNT_PERCENT = 15;



    /** Giá cả xe mốc cho xe 7 chỗ (một chiều). */

    public const REFERENCE_WHOLE_CAR = 1_000_000;



    public const REFERENCE_CAPACITY = 7;



    /** 150k / 1tr — giá một ghế ghép so với cả xe. */

    public const SHARED_TO_WHOLE_RATIO = 0.15;



    /** @return array<string, mixed> */

    public function quote(

        ScheduleTemplate $template,

        string $tripType,

        ?string $pickup = null,

        ?string $dropoff = null,

        string $bookingMode = 'shared',

    ): array {

        $template->loadMissing(['route', 'vehicle']);

        $oneWaySeat = $this->oneWaySeatPrice($template, $pickup, $dropoff);

        $oneWayWhole = $this->oneWayWholeCarPrice($template, $pickup, $dropoff);

        $distance = $this->resolveDistanceKm($template->route, $pickup, $dropoff);



        $isWholeCar = $bookingMode === 'whole_car';

        $oneWayUnit = $isWholeCar ? $oneWayWhole : $oneWaySeat;

        $unit = $this->priceForTripType($oneWayUnit, $tripType);

        $sharedUnit = $this->priceForTripType($oneWaySeat, $tripType);

        $wholeUnit = $this->priceForTripType($oneWayWhole, $tripType);



        return [

            'distance_km'             => $distance,

            'one_way_seat_price'      => $oneWaySeat,

            'one_way_whole_car_price' => $oneWayWhole,

            'seat_price'              => $isWholeCar ? $wholeUnit : $sharedUnit,

            'shared_seat_price'       => $sharedUnit,

            'whole_car_price'         => $wholeUnit,

            'booking_mode'            => $bookingMode,

            'trip_type'               => $tripType,

            'rate_per_km'             => $distance > 0 ? (int) round($oneWaySeat / $distance) : null,

            'round_trip_savings'      => $tripType === 'round_trip'

                ? max($oneWayUnit * 2 - $unit, 0)

                : 0,

        ];

    }



    public function defaultWholeCarPrice(int $capacity): int

    {

        $cap = max($capacity, 1);



        return $this->roundToThousand((int) round(self::REFERENCE_WHOLE_CAR * $cap / self::REFERENCE_CAPACITY));

    }



    public function sharedSeatFromWholeCar(int $wholeCarPrice): int

    {

        return $this->roundToThousand((int) round($wholeCarPrice * self::SHARED_TO_WHOLE_RATIO));

    }



    public function wholeCarPrice(ScheduleTemplate|Schedule $entity): int

    {

        if ($entity->whole_car_price !== null) {

            return (int) round((float) $entity->whole_car_price);

        }



        if ($entity->seat_price !== null) {

            return $this->roundToThousand((int) round((float) $entity->seat_price / self::SHARED_TO_WHOLE_RATIO));

        }



        $entity->loadMissing('vehicle');



        return $this->defaultWholeCarPrice($entity->capacity());

    }



    public function oneWayWholeCarPrice(

        ScheduleTemplate|Schedule $entity,

        ?string $pickup = null,

        ?string $dropoff = null,

    ): int {

        if ($this->hasExplicitPricing($entity)) {

            return $this->wholeCarPrice($entity);

        }



        $seat = $this->oneWaySeatPrice($entity, $pickup, $dropoff);

        $entity->loadMissing('vehicle');



        return $this->roundToThousand($seat * max($entity->capacity(), 1));

    }



    public function oneWaySeatPrice(

        ScheduleTemplate|Schedule $entity,

        ?string $pickup = null,

        ?string $dropoff = null,

    ): int {

        if ($this->hasExplicitPricing($entity)) {

            if ($entity->seat_price !== null) {

                return (int) round((float) $entity->seat_price);

            }



            return $this->sharedSeatFromWholeCar($this->wholeCarPrice($entity));

        }



        $entity->loadMissing('route');

        $route = $entity->route;



        if (! $route) {

            return 0;

        }



        $distance = $this->resolveDistanceKm($route, $pickup, $dropoff);

        $fromDistance = $distance > 0 ? $this->oneWayFromDistance($distance) : 0;

        $routeBase = (int) round((float) ($route->base_price ?? 0));



        if ($fromDistance <= 0) {

            return max($routeBase, 0);

        }



        if ($routeBase <= 0) {

            return $fromDistance;

        }



        return (int) min($routeBase, $fromDistance);

    }



    public function seatPriceForTripType(int $oneWaySeatPrice, string $tripType): int

    {

        return $this->priceForTripType($oneWaySeatPrice, $tripType);

    }



    public function priceForTripType(int $oneWayPrice, string $tripType): int

    {

        if ($tripType === 'round_trip') {

            return $this->roundToThousand((int) round($oneWayPrice * PlatformFees::roundTripMultiplier()));

        }



        return $oneWayPrice;

    }



    public function bookingTotal(

        ScheduleTemplate|Schedule $entity,

        string $tripType,

        string $bookingMode,

        int $seatCount,

        ?string $pickup = null,

        ?string $dropoff = null,

    ): float {

        if ($bookingMode === 'whole_car') {

            $oneWay = $this->oneWayWholeCarPrice($entity, $pickup, $dropoff);



            return (float) $this->priceForTripType($oneWay, $tripType);

        }



        $oneWay = $this->oneWaySeatPrice($entity, $pickup, $dropoff);

        $unit = $this->priceForTripType($oneWay, $tripType);



        return round($unit * max($seatCount, 1), 2);

    }



    public function resolveDistanceKm(

        TripRoute $route,

        ?string $pickup = null,

        ?string $dropoff = null,

    ): int {

        if ($pickup && $dropoff

            && SouthernProvinces::isAllowed($pickup)

            && SouthernProvinces::isAllowed($dropoff)

        ) {

            return SouthernProvinces::distanceBetween($pickup, $dropoff);

        }



        if ($route->distance_km) {

            return (int) $route->distance_km;

        }



        return SouthernProvinces::distanceBetween($route->departure, $route->destination);

    }



    /** Đơn giá một chiều theo km — càng xa càng rẻ/km. */

    public function oneWayFromDistance(int $distanceKm): int

    {

        if ($distanceKm <= 0) {

            return 0;

        }



        $rate = match (true) {

            $distanceKm <= 40  => 4500,

            $distanceKm <= 100 => 2100,

            $distanceKm <= 200 => 1800,

            default            => 1150,

        };



        $raw = (int) round($distanceKm * $rate);



        return max($this->roundToThousand($raw), 80_000);

    }



    public function roundTripHint(int $oneWaySeatPrice): int

    {

        return $this->priceForTripType($oneWaySeatPrice, 'round_trip');

    }



    public function tripTypeLabel(string $tripType): string

    {

        return $tripType === 'round_trip' ? 'Khứ hồi (2 chiều)' : 'Một chiều';

    }

    /**
     * Khoảng giá cả xe (một chiều) theo 4 / 7 / 16 chỗ trên cùng tuyến.
     *
     * @param  iterable<ScheduleTemplate>  $templatesOnRoute
     * @return array{min: int, max: int}
     */
    public function wholeCarPriceRangeForRoute(iterable $templatesOnRoute): array
    {
        $byCapacity = [];
        foreach ($templatesOnRoute as $template) {
            $byCapacity[$template->capacity()] = $template;
        }

        $prices = [];
        foreach (VehicleCapacityOptions::STANDARD as $cap) {
            if (isset($byCapacity[$cap])) {
                $prices[] = $this->oneWayWholeCarPrice($byCapacity[$cap]);
            } else {
                $prices[] = $this->defaultWholeCarPrice($cap);
            }
        }

        return [
            'min' => min($prices),
            'max' => max($prices),
        ];
    }

    public function formatWholeCarRange(int $min, int $max): string
    {
        $minFormatted = number_format($min, 0, ',', '.');

        if ($min === $max) {
            return $minFormatted . ' đ';
        }

        return $minFormatted . ' ~ ' . number_format($max, 0, ',', '.') . ' đ';
    }

    private function hasExplicitPricing(ScheduleTemplate|Schedule $entity): bool

    {

        return $entity->whole_car_price !== null || $entity->seat_price !== null;

    }



    private function roundToThousand(int $amount): int

    {

        return (int) (round($amount / 1000) * 1000);

    }

}

