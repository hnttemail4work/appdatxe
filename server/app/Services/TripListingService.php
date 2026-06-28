<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\ScheduleTemplate;
use App\Support\SouthernProvinces;
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
            $serviceDate = now()->addDay()->toDateString();
        } elseif ($serviceDate < now()->toDateString()) {
            $serviceDate = now()->toDateString();
        }

        return [
            'departure'     => SouthernProvinces::isAllowed($departure) ? $departure : null,
            'destination'   => SouthernProvinces::isAllowed($destination) ? $destination : null,
            'vehicle_type'  => $request->input('vehicle_type'),
            'service_date'  => $serviceDate,
        ];
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
            ->orderBy('id')
            ->get();
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
        ?array $routeWholeCarRange = null,
    ): array {
        $capacity = $template->capacity();
        $quote = $this->pricing->quote($template, 'one_way', null, null, 'shared');
        $wholeQuote = $this->pricing->quote($template, 'one_way', null, null, 'whole_car');
        $roundQuote = $this->pricing->quote($template, 'round_trip', null, null, 'shared');
        $hintDate = $serviceDate ?? now()->addDay()->toDateString();
        $schedule = $template->scheduleInfoForDate($hintDate);
        $occupied = $this->occupiedSeatMapForDate($template, $hintDate, $schedule['departure_time']);
        $taken = count($occupied);
        $free = max($capacity - $taken, 0);
        $wholeRange = $routeWholeCarRange ?? $this->pricing->wholeCarPriceRangeForRoute([$template]);
        $priceLabel = $this->pricing->formatWholeCarRange($wholeRange['min'], $wholeRange['max']);

        return [
            'id'               => $template->id,
            'departure'        => $template->route->departure,
            'destination'      => $template->route->destination,
            'reference_time'   => $schedule['departure_time'],
            'service_date'     => $schedule['service_date'],
            'weekday'          => $schedule['weekday'],
            'weekday_short'    => $schedule['weekday_short'],
            'date_day'         => $schedule['date_day'],
            'date_month'       => $schedule['date_month'],
            'date_short'       => $schedule['date_short'],
            'date_label'       => $schedule['date_label'],
            'departure_time'   => $schedule['departure_time'],
            'arrival_time'     => $schedule['arrival_time'],
            'time_range'       => $schedule['time_range'],
            'vehicle_type'     => $template->vehicle->type,
            'license_plate'    => $template->vehicle->license_plate,
            'capacity'         => $capacity,
            'capacity_sort'    => \App\Support\VehicleCapacityOptions::sortKey($capacity),
            'capacity_label'   => \App\Support\VehicleCapacityOptions::label($capacity),
            'seats_free'       => $free,
            'seats_taken'      => $taken,
            'distance_km'      => $quote['distance_km'],
            'price'            => $priceLabel,
            'price_raw'        => $wholeRange['min'],
            'price_range_min'  => $wholeRange['min'],
            'price_range_max'  => $wholeRange['max'],
            'one_way_price'    => $quote['one_way_seat_price'],
            'whole_car_price'  => $wholeQuote['one_way_whole_car_price'],
            'round_trip_price' => $roundQuote['shared_seat_price'],
            'rate_per_km'      => $quote['rate_per_km'],
            'route_line'       => $template->route->departure . ' → ' . $template->route->destination,
            'vehicle_label'    => \App\Support\VehicleDisplay::labelFromVehicle($template->vehicle),
            'vehicle_photo_url' => \App\Support\VehicleDisplay::photoFromVehicle($template->vehicle),
            'pickup_default'   => $template->route->departure,
            'dropoff_default'  => $template->route->destination,
            'seats_hint'       => $this->seatsHintForDate($template, $hintDate, $schedule['departure_time']),
            'is_bookable'      => true,
        ];
    }

    /** Ghế đã giữ/đặt cho chuyến theo nhu cầu trong ngày. */
    public function occupiedSeatMapForDate(ScheduleTemplate $template, string $serviceDate, ?string $preferredTime = null): array
    {
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

    public function seatsHintForDate(ScheduleTemplate $template, string $serviceDate, ?string $preferredTime = null): string
    {
        $capacity = $template->capacity();
        $occupied = $this->occupiedSeatMapForDate($template, $serviceDate, $preferredTime);
        $taken = count($occupied);
        $free = max($capacity - $taken, 0);

        if ($taken === 0) {
            return 'Còn ' . $capacity . ' ghế';
        }

        return 'Còn ' . $free . ' ghế';
    }
}
