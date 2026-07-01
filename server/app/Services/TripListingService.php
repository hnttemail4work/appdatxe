<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\ScheduleTemplate;
use App\Support\DepartureTimeDisplay;
use App\Support\SouthernProvinces;
use App\Support\ServiceDate;
use App\Support\VehicleCapacityOptions;
use App\Services\TripPricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TripListingService
{
    public function __construct(
        private readonly ScheduleLifecycleService $scheduleLifecycle,
        private readonly TripPricingService $pricing,
    ) {
    }

    /** @return array<string, mixed> */
    public function filtersFromRequest(Request $request): array
    {
        $departure = $request->input('departure');
        $destination = $request->input('destination');

        $serviceDate = $request->input('service_date');
        if (! is_string($serviceDate) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $serviceDate)) {
            $serviceDate = ServiceDate::today();
        } elseif ($serviceDate < ServiceDate::today()) {
            $serviceDate = ServiceDate::today();
        }

        $base = [
            'departure'    => SouthernProvinces::isAllowed($departure) ? $departure : null,
            'destination'  => SouthernProvinces::isAllowed($destination) ? $destination : null,
            'vehicle_type' => $request->input('vehicle_type'),
            'service_date' => $serviceDate,
        ];

        if ($request->has('vehicle_capacity')) {
            $raw = (int) $request->input('vehicle_capacity', 0);
            $vehicleCapacity = VehicleCapacityOptions::isAllowed($raw) ? $raw : null;
        } else {
            $vehicleCapacity = $this->resolveDefaultVehicleCapacity($base);
        }

        return array_merge($base, ['vehicle_capacity' => $vehicleCapacity]);
    }

    /**
     * Dò theo thứ tự cấu hình (4 → 7 → 9 …), trả về loại xe đầu tiên còn chuyến.
     */
    public function resolveDefaultVehicleCapacity(array $filters): ?int
    {
        $available = array_flip($this->availableCapacities($filters));

        foreach (VehicleCapacityOptions::enabled() as $capacity) {
            if (isset($available[$capacity])) {
                return $capacity;
            }
        }

        return null;
    }

    /** @return list<int> */
    public function availableCapacities(array $filters): array
    {
        $probe = $filters;
        unset($probe['vehicle_capacity']);

        return $this->query($probe)
            ->pluck('vehicle.capacity')
            ->map(fn ($cap) => (int) $cap)
            ->filter(fn (int $cap) => $cap > 0)
            ->unique()
            ->values()
            ->all();
    }

    /** Danh sách chuyến gợi ý — từ mẫu tuyến, không phải lịch chạy hằng ngày. */
    public function query(array $filters): Collection
    {
        $this->scheduleLifecycle->sync();

        return ScheduleTemplate::query()
            ->where('status', 'active')
            ->with(['route', 'vehicle'])
            ->when($filters['departure'] ?? null, fn ($q, $dep) => $q->whereHas(
                'route',
                fn ($r) => $r->where('departure', $dep)
            ))
            ->when($filters['destination'] ?? null, fn ($q, $dst) => $q->whereHas(
                'route',
                fn ($r) => $r->where('destination', $dst)
            ))
            ->when($filters['vehicle_type'] ?? null, fn ($q, $type) => $q->whereHas(
                'vehicle',
                fn ($v) => $v->where('type', $type)
            ))
            ->when($filters['vehicle_capacity'] ?? null, fn ($q, $cap) => $q->whereHas(
                'vehicle',
                fn ($v) => $v->where('capacity', $cap)
            ))
            ->orderBy('id')
            ->get()
            ->values();
    }

    public function isOfferVisibleForDate(
        ScheduleTemplate $template,
        string $serviceDate,
        ?string $preferredTime = null,
    ): bool {
        return true;
    }

    public function resolveScheduleForDate(
        ScheduleTemplate $template,
        string $serviceDate,
        ?string $preferredTime = null,
    ): ?Schedule {
        $departure = app(DriverAvailabilityService::class)->resolveDepartureTime(
            $template,
            $serviceDate,
            $preferredTime,
        );

        return Schedule::query()
            ->where('template_id', $template->id)
            ->whereDate('service_date', $serviceDate)
            ->where('departure_time', $departure)
            ->whereIn('status', ['scheduled', 'running'])
            ->with('bookings')
            ->first();
    }

    public function routeOptions(): Collection
    {
        return ScheduleTemplate::query()
            ->where('status', 'active')
            ->with('route')
            ->get()
            ->pluck('route')
            ->filter()
            ->unique('id')
            ->values();
    }

    /** @return array<int, array{min: int, max: int}> */
    public function wholeCarPriceRanges(Collection $offers): array
    {
        $ranges = [];
        foreach ($offers->groupBy('route_id') as $routeId => $routeOffers) {
            $ranges[(int) $routeId] = $this->pricing->wholeCarPriceRangeForRoute($routeOffers);
        }

        return $ranges;
    }

    /** @return array<string, mixed> */
    public function serializeOffer(
        ScheduleTemplate $template,
        ?string $serviceDate = null,
    ): array {
        $capacity = $template->capacity();
        $quote = $this->pricing->quote($template, 'one_way', null, null, 'shared');
        $wholeQuote = $this->pricing->quote($template, 'one_way', null, null, 'whole_car');
        $roundQuote = $this->pricing->quote($template, 'round_trip', null, null, 'shared');
        $hintDate = $serviceDate ?? ServiceDate::today();
        $schedule = $template->scheduleInfoForDate($hintDate);
        $referenceTime = $template->hasFixedDepartureTime()
            ? DepartureTimeDisplay::normalizeForClock($template->departure_time)
            : null;
        $occupied = $this->occupiedSeatMapForDate($template, $hintDate, $referenceTime);
        $taken = count($occupied);
        $free = max($capacity - $taken, 0);
        $seatRange = $this->pricing->seatPriceRangeForTemplate($template);
        $priceLabel = $this->pricing->formatSeatRange($seatRange['min'], $seatRange['max']);

        return [
            'id'               => $template->id,
            'departure'        => $template->route->departure,
            'destination'      => $template->route->destination,
            'service_date'     => $schedule['service_date'],
            'weekday'          => $schedule['weekday'],
            'weekday_short'    => $schedule['weekday_short'],
            'date_day'         => $schedule['date_day'],
            'date_month'       => $schedule['date_month'],
            'date_short'       => $schedule['date_short'],
            'date_label'       => $schedule['date_label'],
            'vehicle_type'     => $template->vehicle->type,
            'license_plate'    => $template->vehicle->license_plate,
            'capacity'         => $capacity,
            'capacity_sort'    => \App\Support\VehicleCapacityOptions::sortKey($capacity),
            'capacity_label'   => \App\Support\VehicleCapacityOptions::label($capacity),
            'seats_free'       => $free,
            'seats_taken'      => $taken,
            'distance_km'      => $quote['distance_km'],
            'price'            => $priceLabel,
            'price_raw'        => $seatRange['min'],
            'price_range_min'  => $seatRange['min'],
            'price_range_max'  => $seatRange['max'],
            'seat_price_min'   => $seatRange['min'],
            'seat_price_max'   => $seatRange['max'],
            'one_way_price'    => $quote['one_way_seat_price'],
            'whole_car_price'  => $wholeQuote['one_way_whole_car_price'],
            'whole_car_round_trip_price' => $template->whole_car_round_trip_price !== null
                ? (int) $template->whole_car_round_trip_price
                : null,
            'seat_round_trip_price' => $template->seat_round_trip_price !== null
                ? (int) $template->seat_round_trip_price
                : null,
            'round_trip_price' => $roundQuote['shared_seat_price'],
            'rate_per_km'      => $quote['rate_per_km'],
            'route_line'       => $template->route->departure . ' → ' . $template->route->destination,
            'vehicle_label'    => \App\Support\VehicleDisplay::labelFromVehicle($template->vehicle),
            'vehicle_photo_url' => \App\Support\VehicleDisplay::photoFromVehicle($template->vehicle),
            'pickup_default'   => $template->route->departure,
            'dropoff_default'  => $template->route->destination,
            'is_bookable'      => true,
        ];
    }

    /** Ghế đã giữ/đặt cho chuyến theo nhu cầu trong ngày. */
    public function occupiedSeatMapForDate(ScheduleTemplate $template, string $serviceDate, ?string $preferredTime = null): array
    {
        if (! $template->hasFixedDepartureTime()
            && (! is_string($preferredTime) || trim($preferredTime) === '')) {
            $schedules = Schedule::query()
                ->where('template_id', $template->id)
                ->whereDate('service_date', $serviceDate)
                ->whereIn('status', ['scheduled', 'running'])
                ->get();

            $map = [];
            foreach ($schedules as $schedule) {
                $map = array_merge($map, $this->scheduleLifecycle->occupiedSeatMap($schedule));
            }

            return $map;
        }

        $departure = app(DriverAvailabilityService::class)->resolveDepartureTime(
            $template,
            $serviceDate,
            $preferredTime,
        );

        $schedule = Schedule::query()
            ->where('template_id', $template->id)
            ->whereDate('service_date', $serviceDate)
            ->where('departure_time', $departure)
            ->whereIn('status', ['scheduled', 'running'])
            ->first();

        if (! $schedule) {
            return [];
        }

        return $this->scheduleLifecycle->occupiedSeatMap($schedule);
    }

    public function resolveTemplateForCustomBooking(
        string $departure,
        string $destination,
        ?int $vehicleCapacity = null,
    ): ?ScheduleTemplate {
        $departure = trim($departure);
        $destination = trim($destination);

        if ($departure === '' || $destination === '' || $departure === $destination) {
            return null;
        }

        return ScheduleTemplate::query()
            ->where('status', 'active')
            ->whereHas('route', fn ($route) => $route
                ->where('departure', $departure)
                ->where('destination', $destination))
            ->when(
                $vehicleCapacity !== null && VehicleCapacityOptions::isAllowed($vehicleCapacity),
                fn ($q) => $q->whereHas('vehicle', fn ($v) => $v->where('capacity', $vehicleCapacity))
            )
            ->with(['route', 'vehicle'])
            ->get()
            ->sortBy(fn (ScheduleTemplate $template) => VehicleCapacityOptions::sortKey($template->capacity()))
            ->first();
    }
}
