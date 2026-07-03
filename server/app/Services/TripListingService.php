<?php

namespace App\Services;

use App\Models\ScheduleTemplate;
use App\Support\DepartureTimeDisplay;
use App\Support\ServiceDate;
use App\Support\VehicleDisplay;
use Illuminate\Support\Collection;

class TripListingService
{
    public function __construct(
        private readonly TripPricingService $pricing,
    ) {
    }

    /** @return Collection<int, ScheduleTemplate> */
    public function listActiveTemplates(): Collection
    {
        return ScheduleTemplate::query()
            ->where('status', 'active')
            ->with(['route', 'vehicle'])
            ->orderBy('route_id')
            ->orderBy('id')
            ->get()
            ->sortBy(fn (ScheduleTemplate $t) => $t->route?->departure . '|' . $t->route?->destination)
            ->values();
    }

    /** @return array<string, mixed> */
    public function serializeOffer(ScheduleTemplate $template, ?string $serviceDate = null): array
    {
        $template->loadMissing(['route', 'vehicle']);
        $serviceDate = $serviceDate ?: ServiceDate::today();
        $schedule = $template->scheduleInfoForDate($serviceDate);
        $quote = $this->pricing->quote($template);
        $capacity = $template->capacity();

        return [
            'id'               => $template->id,
            'route'            => $template->route->departure . ' → ' . $template->route->destination,
            'departure'        => $template->route->departure,
            'destination'      => $template->route->destination,
            'capacity'         => $capacity,
            'vehicle_type'     => $template->vehicle->type ?? 'sedan',
            'vehicle_label'    => VehicleDisplay::labelFromVehicle($template->vehicle),
            'vehicle_photo'    => VehicleDisplay::photoFromVehicle($template->vehicle),
            'price'            => $quote['whole_car_price'],
            'service_date'     => $schedule['service_date'] ?? $serviceDate,
            'date_label'       => $schedule['date_label'] ?? '',
            'weekday'          => $schedule['weekday'] ?? '',
            'date_short'       => $schedule['date_short'] ?? '',
            'pickup_default'   => $template->route->departure,
            'dropoff_default'  => $template->route->destination,
            'departure_label'  => $template->hasFixedDepartureTime()
                ? DepartureTimeDisplay::label($template->departure_time)
                : null,
        ];
    }
}
