<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingAudit;
use App\Models\Schedule;
use App\Models\SeatReservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BookingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $bookings = Booking::query()
            ->with(['schedule.route', 'schedule.vehicle', 'seatReservations'])
            ->where('customer_id', $request->user()->id)
            ->latest()
            ->get();

        $now = now();

        $formatted = $bookings->map(function (Booking $booking) use ($now): array {
            $departureTime = $booking->schedule?->departure_time;
            $isUpcoming = $departureTime?->greaterThanOrEqualTo($now) && ! in_array($booking->trip_status, ['completed', 'cancelled'], true);

            return [
                'id' => $booking->id,
                'booking_reference' => $booking->booking_reference,
                'ticket_code' => $booking->ticket_code,
                'seat_numbers' => $booking->seat_numbers,
                'driver_name' => $booking->schedule?->driver_name,
                'vehicle_type' => $booking->schedule?->vehicle?->type,
                'license_plate' => $booking->schedule?->vehicle?->license_plate,
                'departure' => $booking->schedule?->route?->departure,
                'destination' => $booking->schedule?->route?->destination,
                'departure_time' => $departureTime?->toIso8601String(),
                'payment_status' => $booking->payment_status,
                'trip_status' => $booking->trip_status,
                'booking_status' => $booking->booking_status,
                'is_upcoming' => $isUpcoming,
                'ticket_qr_code' => $booking->ticket_code,
                'pickup_address' => $booking->pickup_address,
                'dropoff_address' => $booking->dropoff_address,
                'notes' => $booking->notes,
            ];
        });

        return response()->json([
            'upcoming_trips' => $formatted->where('is_upcoming', true)->values(),
            'past_trips' => $formatted->where('is_upcoming', false)->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'schedule_id' => ['required', 'exists:schedules,id'],
            'seat_numbers' => ['required', 'array', 'min:1'],
            'seat_numbers.*' => ['required', 'string', 'max:10'],
            'pickup_address' => ['nullable', 'string', 'max:255'],
            'dropoff_address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $seatNumbers = collect($validated['seat_numbers'])->map(fn ($s): string => trim((string) $s))->unique()->values()->all();

        $booking = DB::transaction(function () use ($validated, $seatNumbers, $request): Booking {
            $schedule = Schedule::query()->with(['route', 'vehicle', 'seatReservations'])->lockForUpdate()->findOrFail($validated['schedule_id']);
            $this->assertSeatsAreAvailable($schedule, $seatNumbers);

            $totalPrice = round((float) $schedule->route->base_price * count($seatNumbers), 2);
            $holdExpiresAt = now()->addMinutes(15);

            $booking = Booking::query()->create([
                'customer_id' => $request->user()->id,
                'schedule_id' => $schedule->id,
                'seat_numbers' => $seatNumbers,
                'ticket_code' => 'TCK-' . Str::upper(Str::random(10)),
                'booking_reference' => 'BK-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(6)),
                'total_price' => $totalPrice,
                'payment_status' => 'unpaid',
                'trip_status' => 'confirmed',
                'booking_status' => 'pending',
                'pickup_address' => $validated['pickup_address'] ?? null,
                'dropoff_address' => $validated['dropoff_address'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'hold_expires_at' => $holdExpiresAt,
            ]);

            foreach ($seatNumbers as $seatNumber) {
                SeatReservation::query()->create([
                    'schedule_id' => $schedule->id,
                    'booking_id' => $booking->id,
                    'customer_id' => $request->user()->id,
                    'seat_number' => $seatNumber,
                    'reservation_token' => (string) Str::uuid(),
                    'status' => 'held',
                    'expires_at' => $holdExpiresAt,
                ]);
            }

            $this->syncScheduleAvailability($schedule);
            $this->recordAudit($booking, $request->user()->id, 'booking_created', null, $booking->toArray());

            return $booking->load(['schedule.route', 'schedule.vehicle', 'seatReservations']);
        });

        return response()->json(['message' => 'Booking created successfully.', 'data' => $booking], 201);
    }

    public function show(Booking $booking): JsonResponse
    {
        $booking->load(['customer', 'schedule.route', 'schedule.vehicle', 'seatReservations', 'audits.actor']);

        return response()->json(['data' => $booking]);
    }

    public function update(Request $request, Booking $booking): JsonResponse
    {
        $validated = $request->validate([
            'pickup_address' => ['nullable', 'string', 'max:255'],
            'dropoff_address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'booking_status' => ['nullable', Rule::in(['pending', 'confirmed', 'rejected', 'cancelled'])],
        ]);

        $beforeState = $booking->toArray();
        $booking->fill($validated)->save();
        $this->recordAudit($booking, $request->user()->id, 'booking_updated', $beforeState, $booking->fresh()->toArray());

        return response()->json(['message' => 'Booking updated successfully.', 'data' => $booking->fresh()]);
    }

    public function destroy(Request $request, Booking $booking): JsonResponse
    {
        $beforeState = $booking->toArray();

        DB::transaction(function () use ($booking, $request, $beforeState): void {
            $booking->update([
                'booking_status' => 'cancelled',
                'trip_status' => 'cancelled',
                'payment_status' => 'refunded',
                'cancelled_at' => now(),
            ]);

            $booking->seatReservations()->update(['status' => 'released', 'expires_at' => now()]);
            $this->recordAudit($booking, $request->user()->id, 'booking_cancelled', $beforeState, $booking->fresh()->toArray());
            $this->syncScheduleAvailability($booking->schedule()->with(['vehicle', 'seatReservations'])->first());
        });

        return response()->json(['message' => 'Booking cancelled successfully.']);
    }

    public function confirmPayment(Request $request, Booking $booking): JsonResponse
    {
        $beforeState = $booking->toArray();

        DB::transaction(function () use ($booking, $request, $beforeState): void {
            $booking->update([
                'payment_status' => 'paid',
                'booking_status' => 'confirmed',
                'trip_status' => 'confirmed',
                'confirmed_at' => now(),
            ]);

            $booking->seatReservations()->update(['status' => 'booked', 'expires_at' => null]);
            $this->syncScheduleAvailability($booking->schedule()->with(['vehicle', 'seatReservations'])->first());
            $this->recordAudit($booking, $request->user()->id, 'payment_confirmed', $beforeState, $booking->fresh()->toArray());
        });

        return response()->json([
            'message' => 'Payment confirmed successfully.',
            'data' => $booking->fresh(['schedule.route', 'schedule.vehicle', 'seatReservations']),
        ]);
    }

    public function accept(Request $request, Booking $booking): JsonResponse
    {
        if ($booking->payment_status !== 'paid') {
            return response()->json(['message' => 'Payment must be confirmed before accepting booking.'], 422);
        }

        $beforeState = $booking->toArray();
        $booking->update(['booking_status' => 'confirmed', 'trip_status' => 'confirmed', 'confirmed_at' => now()]);
        $booking->seatReservations()->update(['status' => 'booked', 'expires_at' => null]);
        $this->syncScheduleAvailability($booking->schedule()->with(['vehicle', 'seatReservations'])->first());
        $this->recordAudit($booking, $request->user()->id, 'booking_accepted', $beforeState, $booking->fresh()->toArray());

        return response()->json(['message' => 'Booking accepted successfully.', 'data' => $booking->fresh()]);
    }

    public function reject(Request $request, Booking $booking): JsonResponse
    {
        $beforeState = $booking->toArray();
        $booking->update(['booking_status' => 'rejected', 'trip_status' => 'cancelled', 'cancelled_at' => now()]);
        $booking->seatReservations()->update(['status' => 'released', 'expires_at' => now()]);
        $this->syncScheduleAvailability($booking->schedule()->with(['vehicle', 'seatReservations'])->first());
        $this->recordAudit($booking, $request->user()->id, 'booking_rejected', $beforeState, $booking->fresh()->toArray());

        return response()->json(['message' => 'Booking rejected successfully.', 'data' => $booking->fresh()]);
    }

    private function assertSeatsAreAvailable(Schedule $schedule, array $seatNumbers): void
    {
        $capacity = (int) $schedule->vehicle->capacity;

        foreach ($seatNumbers as $seatNumber) {
            if (! is_numeric($seatNumber) || (int) $seatNumber < 1 || (int) $seatNumber > $capacity) {
                abort(422, 'Seat number ' . $seatNumber . ' is invalid for this vehicle.');
            }
        }

        $exists = SeatReservation::query()
            ->where('schedule_id', $schedule->id)
            ->whereIn('seat_number', $seatNumbers)
            ->whereIn('status', ['held', 'booked'])
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->exists();

        if ($exists) {
            abort(422, 'One or more requested seats are no longer available.');
        }
    }

    private function syncScheduleAvailability(Schedule $schedule): void
    {
        $schedule->loadMissing(['vehicle', 'seatReservations']);

        $activeReservations = $schedule->seatReservations
            ->filter(function ($reservation): bool {
                if (! in_array($reservation->status, ['held', 'booked'], true)) {
                    return false;
                }

                return ! $reservation->expires_at || $reservation->expires_at->isFuture();
            })
            ->count();

        $schedule->forceFill(['available_seats' => max((int) $schedule->vehicle->capacity - $activeReservations, 0)])->save();
    }

    private function recordAudit(Booking $booking, ?int $actorId, string $action, ?array $beforeState, ?array $afterState): void
    {
        BookingAudit::query()->create([
            'booking_id' => $booking->id,
            'actor_id' => $actorId,
            'action' => $action,
            'before_state' => $beforeState,
            'after_state' => $afterState,
        ]);
    }
}
