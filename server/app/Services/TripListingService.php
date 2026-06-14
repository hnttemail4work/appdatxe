<?php

namespace App\Services;

use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TripListingService
{
    public function __construct(private readonly ScheduleLifecycleService $scheduleLifecycle)
    {
    }

    /** @return array<string, mixed> */
    public function filtersFromRequest(Request $request): array
    {
        return [
            'departure'    => $request->input('departure'),
            'destination'  => $request->input('destination'),
            'date'         => $request->input('date'),
            'vehicle_type' => $request->input('vehicle_type'),
            'status'       => $request->input('status'),
        ];
    }

    /** @param array<string, mixed> $filters */
    public function query(array $filters): Collection
    {
        $this->scheduleLifecycle->sync();

        $statuses = match ($filters['status'] ?? null) {
            'running'   => ['running'],
            'scheduled' => ['scheduled'],
            default     => ['scheduled', 'running'],
        };

        return Schedule::query()
            ->with(['route', 'vehicle', 'driver.driverProfile'])
            ->withCount([
                'seatReservations as active_reservations_count' => function ($q): void {
                    $q->whereIn('status', ['held', 'booked'])
                        ->where(fn ($n) => $n->whereNull('expires_at')->orWhere('expires_at', '>', now()));
                },
            ])
            ->whereIn('status', $statuses)
            ->where('departure_time', '>=', now()->startOfDay())
            ->when($filters['departure'] ?? null, fn ($q, $dep) => $q->whereHas(
                'route',
                fn ($r) => $r->where('departure', $dep)
            ))
            ->when($filters['destination'] ?? null, fn ($q, $dst) => $q->whereHas(
                'route',
                fn ($r) => $r->where('destination', $dst)
            ))
            ->when($filters['date'] ?? null, fn ($q, $date) => $q->whereDate('departure_time', $date))
            ->when($filters['vehicle_type'] ?? null, fn ($q, $type) => $q->whereHas(
                'vehicle',
                fn ($v) => $v->where('type', $type)
            ))
            ->orderBy('departure_time')
            ->get()
            ->map(function (Schedule $s): Schedule {
                $s->available_seats = max($s->capacity() - $s->activeReservationCount(), 0);
                $s->occupied_seat_map = $this->scheduleLifecycle->occupiedSeatMap($s);

                return $s;
            });
    }

    /** @return array<string, mixed> */
    public function serializeSchedule(Schedule $s): array
    {
        $booked = $s->bookedSeatsCount();
        $capacity = $s->capacity();

        return [
            'id'              => $s->id,
            'departure'       => $s->route->departure,
            'destination'     => $s->route->destination,
            'departure_time'  => $s->departure_time->format('H:i · d/m/Y'),
            'departure_iso'   => $s->departure_time->toIso8601String(),
            'status'          => $s->status,
            'status_label'    => $s->statusLabel(),
            'vehicle_type'    => $s->vehicle->type,
            'license_plate'   => $s->vehicle->license_plate,
            'booked'          => $booked,
            'capacity'        => $capacity,
            'seats_label'     => $booked . '/' . $capacity,
            'available_seats' => max($capacity - $booked, 0),
            'price'           => number_format($s->route->base_price, 0, ',', '.'),
            'is_bookable'     => $s->isBookable(),
            'occupied_seats'  => array_keys($s->occupied_seat_map ?? []),
        ];
    }
}
