<?php



namespace App\Services;



use App\Models\Schedule;

use App\Models\ScheduleTemplate;

use App\Models\TripRoute;

use App\Support\PlatformFees;

use App\Support\RouteDistanceCatalog;

use App\Support\LocationCatalog;



/** Giá vé theo km (admin) và giá cấu hình trên từng chuyến. */

class TripPricingService
{
    /** Giá cả xe mốc cho xe 7 chỗ (một chiều) — fallback khi chưa cấu hình giá. */

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
        $discount = $this->tripDiscountPercent($template);

        if ($tripType === 'round_trip' && $this->hasExplicitRoundTripPricing($template)) {
            $wholeUnit = (int) ($template->whole_car_round_trip_price ?? $this->priceForTripType($oneWayWhole, $tripType, $discount));
            $sharedUnit = (int) ($template->seat_round_trip_price ?? $this->priceForTripType($oneWaySeat, $tripType, $discount));
            $unit = $isWholeCar ? $wholeUnit : $sharedUnit;
        } else {
            $unit = $this->priceForTripType($oneWayUnit, $tripType, $discount);
            $sharedUnit = $this->priceForTripType($oneWaySeat, $tripType, $discount);
            $wholeUnit = $this->priceForTripType($oneWayWhole, $tripType, $discount);
        }



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

        $wholeFromDistance = $distance > 0 ? $this->wholeCarOneWayFromDistance($distance) : 0;

        $entity->loadMissing('vehicle');

        $fromDistance = $wholeFromDistance > 0

            ? $this->roundToThousand((int) round($wholeFromDistance / max($entity->capacity(), 1)))

            : 0;

        $routeBase = (int) round((float) ($route->base_price ?? 0));



        if ($fromDistance <= 0) {

            return max($routeBase, 0);

        }



        if ($routeBase <= 0) {

            return $fromDistance;

        }



        return (int) min($routeBase, $fromDistance);

    }



    public function seatPriceForTripType(int $oneWaySeatPrice, string $tripType, ?float $discountPercent = null): int
    {
        return $this->priceForTripType($oneWaySeatPrice, $tripType, $discountPercent);
    }

    public function priceForTripType(int $oneWayPrice, string $tripType, ?float $discountPercent = null): int
    {
        if ($tripType === 'round_trip') {
            $multiplier = $discountPercent !== null
                ? round(2 * (1 - $discountPercent / 100), 4)
                : PlatformFees::roundTripMultiplier();

            return $this->roundToThousand((int) round($oneWayPrice * $multiplier));
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



            return (float) $this->priceForTripType($oneWay, $tripType, $this->tripDiscountPercent($entity));

        }



        $oneWay = $this->oneWaySeatPrice($entity, $pickup, $dropoff);

        $unit = $this->priceForTripType($oneWay, $tripType, $this->tripDiscountPercent($entity));



        return round($unit * max($seatCount, 1), 2);

    }



    public function resolveDistanceKm(

        TripRoute $route,

        ?string $pickup = null,

        ?string $dropoff = null,

    ): int {

        if ($pickup && $dropoff

            && LocationCatalog::isAllowed($pickup)

            && LocationCatalog::isAllowed($dropoff)

        ) {

            return LocationCatalog::distanceBetween($pickup, $dropoff);

        }



        if ($route->distance_km) {

            return (int) $route->distance_km;

        }



        return RouteDistanceCatalog::resolveKm($route->departure, $route->destination);

    }



    /** Giá cả xe một chiều theo km và bảng giá admin. */

    public function wholeCarOneWayFromDistance(int $distanceKm): int

    {

        if ($distanceKm <= 0) {

            return 0;

        }



        $rate = $distanceKm > 100

            ? PlatformFees::kmRateOver100()

            : PlatformFees::kmRateUnder100();



        return $this->roundToThousand((int) round($distanceKm * $rate));

    }



    /**
     * Gợi ý giá khi quản lý tạo chuyến — chia ghế từ giá cả xe.
     *
     * @return array{
     *   distance_km: int,
     *   rate_per_km: int,
     *   whole_car_one_way: int,
     *   seat_one_way: int,
     *   whole_car_round: int,
     *   seat_round: int,
     * }
     */
    public function suggestOfferPrices(int $distanceKm, int $seats): array

    {

        $seats = max($seats, 1);

        $rate = $distanceKm > 100

            ? PlatformFees::kmRateOver100()

            : PlatformFees::kmRateUnder100();

        $wholeOneWay = $this->wholeCarOneWayFromDistance($distanceKm);

        $seatOneWay = $this->roundToThousand((int) round($wholeOneWay / $seats));

        $multiplier = PlatformFees::roundTripMultiplier();

        $wholeRound = $this->roundToThousand((int) round($wholeOneWay * $multiplier));

        $seatRound = $this->roundToThousand((int) round($seatOneWay * $multiplier));



        return [

            'distance_km'       => $distanceKm,

            'rate_per_km'       => $rate,

            'whole_car_one_way' => $wholeOneWay,

            'seat_one_way'      => $seatOneWay,

            'whole_car_round'   => $wholeRound,

            'seat_round'        => $seatRound,

        ];

    }



    public function roundTripHint(int $oneWaySeatPrice, ?float $discountPercent = null): int
    {
        return $this->priceForTripType($oneWaySeatPrice, 'round_trip', $discountPercent);
    }



    public function tripTypeLabel(string $tripType): string

    {

        return $tripType === 'round_trip' ? 'Khứ hồi (2 chiều)' : 'Một chiều';

    }

    /**
     * Khoảng giá cả xe (một chiều) theo các loại xe đã cấu hình trên tuyến.
     *
     * @param  iterable<ScheduleTemplate>  $templatesOnRoute
     * @return array{min: int, max: int}
     */
    public function wholeCarPriceRangeForRoute(iterable $templatesOnRoute): array
    {
        $prices = [];
        foreach ($templatesOnRoute as $template) {
            $prices[] = $this->oneWayWholeCarPrice($template);
        }

        if ($prices === []) {
            return ['min' => 0, 'max' => 0];
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

    /** @return array{min: int, max: int} */
    public function seatPriceRangeForTemplate(ScheduleTemplate $template): array
    {
        $oneWay = $this->oneWaySeatPrice($template);
        $roundTrip = (int) $this->quote($template, 'round_trip', null, null, 'shared')['shared_seat_price'];

        return [
            'min' => min($oneWay, $roundTrip),
            'max' => max($oneWay, $roundTrip),
        ];
    }

    public function formatSeatRange(int $min, int $max): string
    {
        return $this->formatWholeCarRange($min, $max);
    }

    private function hasExplicitRoundTripPricing(ScheduleTemplate|Schedule $entity): bool
    {
        return $entity->whole_car_round_trip_price !== null
            || $entity->seat_round_trip_price !== null;
    }

    private function tripDiscountPercent(ScheduleTemplate|Schedule $entity): ?float
    {
        $entity->loadMissing('route');

        if (! $entity->route || $entity->route->round_trip_discount_percent === null) {
            return null;
        }

        return (float) $entity->route->round_trip_discount_percent;
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

