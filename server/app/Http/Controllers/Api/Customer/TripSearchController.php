<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Services\ScheduleLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TripSearchController extends Controller
{
    public function __construct(private readonly ScheduleLifecycleService $scheduleLifecycle)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->scheduleLifecycle->sync();

        $validated = $request->validate([
            'departure' => ['nullable', 'string', 'max:255'],
            'destination' => ['nullable', 'string', 'max:255'],
            'date' => ['nullable', 'date'],
            'time' => ['nullable', 'date_format:H:i'],
            'vehicle_type' => ['nullable', Rule::in(['limousine', 'sedan', 'suv'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $schedules = Schedule::query()
            ->with(['route', 'vehicle'])
            ->withCount([
                'seatReservations as active_reservations_count' => function ($query): void {
                    $query->whereIn('status', ['held', 'booked'])
                        ->where(function ($nestedQuery): void {
                            $nestedQuery->whereNull('expires_at')
                                ->orWhere('expires_at', '>', now());
                        });
                },
            ])
            ->whereIn('status', ['scheduled', 'running'])
            ->when($validated['departure'] ?? null, function ($query, string $departure): void {
                $query->whereHas('route', function ($routeQuery) use ($departure): void {
                    $routeQuery->where('departure', $departure);
                });
            })
            ->when($validated['destination'] ?? null, function ($query, string $destination): void {
                $query->whereHas('route', function ($routeQuery) use ($destination): void {
                    $routeQuery->where('destination', $destination);
                });
            })
            ->when($validated['date'] ?? null, function ($query, string $date): void {
                $query->whereDate('departure_time', $date);
            })
            ->when($validated['time'] ?? null, function ($query, string $time): void {
                $query->whereTime('departure_time', '>=', $time);
            })
            ->when($validated['vehicle_type'] ?? null, function ($query, string $vehicleType): void {
                $query->whereHas('vehicle', function ($vehicleQuery) use ($vehicleType): void {
                    $vehicleQuery->where('type', $vehicleType);
                });
            })
            ->orderBy('departure_time')
            ->paginate((int) ($validated['per_page'] ?? 15));

        return response()->json([
            'data' => $schedules->getCollection()->map(function (Schedule $schedule): array {
                return [
                    'id' => $schedule->id,
                    'departure' => $schedule->route->departure,
                    'destination' => $schedule->route->destination,
                    'base_price' => $schedule->route->base_price,
                    'vehicle_type' => $schedule->vehicle->type,
                    'license_plate' => $schedule->vehicle->license_plate,
                    'driver_name' => $schedule->driver_name,
                    'departure_time' => $schedule->departure_time?->toIso8601String(),
                    'booked_seats' => $schedule->bookedSeatsCount(),
                    'capacity' => $schedule->capacity(),
                    'seats_label' => $schedule->seatsLabel(),
                    'available_seats' => max($schedule->capacity() - $schedule->bookedSeatsCount(), 0),
                    'status' => $schedule->status,
                ];
            })->values(),
            'meta' => [
                'current_page' => $schedules->currentPage(),
                'last_page' => $schedules->lastPage(),
                'per_page' => $schedules->perPage(),
                'total' => $schedules->total(),
            ],
        ]);
    }

    public function show(Schedule $schedule): JsonResponse
    {
        $schedule->load(['route', 'vehicle', 'seatReservations.booking.customer']);

        $occupiedSeats = $schedule->seatReservations
            ->filter(function ($reservation): bool {
                if ($reservation->status === 'booked') {
                    return true;
                }

                return $reservation->status === 'held' && ($reservation->expires_at === null || $reservation->expires_at->isFuture());
            })
            ->keyBy('seat_number');

        $seatMap = collect(range(1, (int) $schedule->vehicle->capacity))->map(function (int $seatNumber) use ($occupiedSeats): array {
            $reservation = $occupiedSeats->get((string) $seatNumber);

            return [
                'seat_number' => (string) $seatNumber,
                'status' => $reservation?->status ?? 'available',
                'booking_reference' => $reservation?->booking?->booking_reference,
                'ticket_code' => $reservation?->booking?->ticket_code,
                'reserved_until' => $reservation?->expires_at?->toIso8601String(),
            ];
        })->values();

        return response()->json([
            'data' => [
                'id' => $schedule->id,
                'route' => $schedule->route,
                'vehicle' => $schedule->vehicle,
                'driver_name' => $schedule->driver_name,
                'departure_time' => $schedule->departure_time?->toIso8601String(),
                'status' => $schedule->status,
                'available_seats' => max((int) $schedule->vehicle->capacity - $occupiedSeats->count(), 0),
                'seat_map' => $seatMap,
            ],
        ]);
    }
}
