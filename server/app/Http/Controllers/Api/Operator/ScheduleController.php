<?php

namespace App\Http\Controllers\Api\Operator;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ScheduleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $schedules = Schedule::query()
            ->with(['route', 'vehicle'])
            ->when($request->user()->role !== 'admin', function ($query) use ($request): void {
                $query->whereHas('vehicle', function ($vehicleQuery) use ($request): void {
                    $vehicleQuery->where('operator_id', $request->user()->id);
                });
            })
            ->when($request->filled('route_id'), fn ($query) => $query->where('route_id', $request->integer('route_id')))
            ->when($request->filled('vehicle_id'), fn ($query) => $query->where('vehicle_id', $request->integer('vehicle_id')))
            ->when($request->filled('date'), fn ($query) => $query->whereDate('departure_time', $request->date('date')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->orderBy('departure_time')
            ->paginate((int) $request->input('per_page', 15));

        return response()->json([
            'data' => $schedules->getCollection()->values(),
            'meta' => [
                'current_page' => $schedules->currentPage(),
                'last_page' => $schedules->lastPage(),
                'per_page' => $schedules->perPage(),
                'total' => $schedules->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'route_id' => ['required', 'exists:routes,id'],
            'vehicle_id' => ['required', 'exists:vehicles,id'],
            'driver_name' => ['required', 'string', 'max:255'],
            'departure_time' => ['required', 'date'],
            'available_seats' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', Rule::in(['draft', 'scheduled', 'running', 'completed', 'cancelled'])],
        ]);

        $vehicle = Vehicle::query()->findOrFail($validated['vehicle_id']);

        if ($request->user()->role !== 'admin' && $vehicle->operator_id !== $request->user()->id) {
            abort(403, 'Forbidden.');
        }

        $schedule = Schedule::query()->create([
            'route_id' => $validated['route_id'],
            'vehicle_id' => $vehicle->id,
            'driver_name' => $validated['driver_name'],
            'departure_time' => $validated['departure_time'],
            'available_seats' => $validated['available_seats'] ?? $vehicle->capacity,
            'status' => $validated['status'] ?? 'scheduled',
        ]);

        return response()->json(['message' => 'Schedule created successfully.', 'data' => $schedule->load(['route', 'vehicle'])], 201);
    }

    public function show(Request $request, Schedule $schedule): JsonResponse
    {
        if ($request->user()->role !== 'admin' && $schedule->vehicle->operator_id !== $request->user()->id) {
            abort(403, 'Forbidden.');
        }

        $schedule->load(['route', 'vehicle', 'seatReservations.booking.customer', 'bookings.customer']);

        return response()->json([
            'data' => [
                'schedule' => $schedule,
                'seat_grid' => $this->buildSeatGrid($schedule),
                'passenger_count' => $schedule->bookings->count(),
            ],
        ]);
    }

    public function update(Request $request, Schedule $schedule): JsonResponse
    {
        if ($request->user()->role !== 'admin' && $schedule->vehicle->operator_id !== $request->user()->id) {
            abort(403, 'Forbidden.');
        }

        $validated = $request->validate([
            'route_id' => ['sometimes', 'exists:routes,id'],
            'vehicle_id' => ['sometimes', 'exists:vehicles,id'],
            'driver_name' => ['sometimes', 'string', 'max:255'],
            'departure_time' => ['sometimes', 'date'],
            'available_seats' => ['sometimes', 'integer', 'min:1'],
            'status' => ['sometimes', Rule::in(['draft', 'scheduled', 'running', 'completed', 'cancelled'])],
        ]);

        if (array_key_exists('vehicle_id', $validated)) {
            $vehicle = Vehicle::query()->findOrFail($validated['vehicle_id']);

            if ($request->user()->role !== 'admin' && $vehicle->operator_id !== $request->user()->id) {
                abort(403, 'Forbidden.');
            }
        }

        $schedule->update(array_filter($validated, static fn ($value): bool => $value !== null));

        return response()->json(['message' => 'Schedule updated successfully.', 'data' => $schedule->fresh()->load(['route', 'vehicle'])]);
    }

    public function destroy(Request $request, Schedule $schedule): JsonResponse
    {
        if ($request->user()->role !== 'admin' && $schedule->vehicle->operator_id !== $request->user()->id) {
            abort(403, 'Forbidden.');
        }

        if ($schedule->bookings()->exists()) {
            return response()->json(['message' => 'Schedule already has bookings and cannot be deleted.'], 422);
        }

        $schedule->delete();

        return response()->json(['message' => 'Schedule deleted successfully.']);
    }

    public function seatGrid(Request $request, Schedule $schedule): JsonResponse
    {
        if ($request->user()->role !== 'admin' && $schedule->vehicle->operator_id !== $request->user()->id) {
            abort(403, 'Forbidden.');
        }

        $schedule->load(['vehicle', 'seatReservations.booking.customer']);

        return response()->json([
            'schedule_id' => $schedule->id,
            'available_seats' => $schedule->available_seats,
            'seat_grid' => $this->buildSeatGrid($schedule),
        ]);
    }

    private function buildSeatGrid(Schedule $schedule): array
    {
        $reservations = $schedule->seatReservations->keyBy('seat_number');

        return collect(range(1, (int) $schedule->vehicle->capacity))->map(function (int $seatNumber) use ($reservations): array {
            $reservation = $reservations->get((string) $seatNumber);

            return [
                'seat_number' => (string) $seatNumber,
                'status' => $reservation?->status ?? 'available',
                'booking_reference' => $reservation?->booking?->booking_reference,
                'customer_name' => $reservation?->booking?->customer?->name,
                'expires_at' => $reservation?->expires_at?->toIso8601String(),
            ];
        })->values()->all();
    }
}
