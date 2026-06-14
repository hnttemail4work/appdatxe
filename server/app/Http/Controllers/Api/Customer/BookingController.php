<?php

namespace App\Http\Controllers\Api\Customer;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Schedule;
use App\Services\BookingWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class BookingController extends Controller
{
    public function __construct(private readonly BookingWorkflowService $workflow)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $bookings = Booking::query()
            ->with(['schedule.route', 'schedule.vehicle', 'seatReservations', 'paymentTransactions'])
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
                'payment_claim_pending' => $booking->hasPendingPaymentClaim(),
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
        $schedule = Schedule::query()->with(['route', 'vehicle'])->findOrFail($validated['schedule_id']);

        $booking = $this->workflow->createBooking(
            $schedule,
            $request->user()->id,
            $seatNumbers,
            $validated['pickup_address'] ?? null,
            $validated['dropoff_address'] ?? null,
            $validated['notes'] ?? null,
        );

        return response()->json([
            'message' => 'Booking created successfully.',
            'data' => $booking->load(['schedule.route', 'schedule.vehicle', 'seatReservations']),
        ], 201);
    }

    public function show(Request $request, Booking $booking): JsonResponse
    {
        if ($booking->customer_id !== $request->user()->id) {
            abort(403);
        }

        $booking->load(['customer', 'schedule.route', 'schedule.vehicle', 'seatReservations', 'audits.actor', 'paymentTransactions']);

        return response()->json(['data' => $booking]);
    }

    public function update(Request $request, Booking $booking): JsonResponse
    {
        if ($booking->customer_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'pickup_address' => ['nullable', 'string', 'max:255'],
            'dropoff_address' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $beforeState = $booking->toArray();
        $booking->fill($validated)->save();

        return response()->json(['message' => 'Booking updated successfully.', 'data' => $booking->fresh()]);
    }

    public function destroy(Request $request, Booking $booking): JsonResponse
    {
        if ($booking->customer_id !== $request->user()->id) {
            abort(403);
        }

        try {
            $this->workflow->cancelByCustomer($booking, $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Booking cancelled successfully.']);
    }

    public function claimPayment(Request $request, Booking $booking): JsonResponse
    {
        if ($booking->customer_id !== $request->user()->id) {
            abort(403);
        }

        try {
            $this->workflow->customerClaimPayment($booking, $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Payment claim submitted. Waiting for operator/admin confirmation.',
            'data' => $booking->fresh(['schedule.route', 'schedule.vehicle', 'seatReservations', 'paymentTransactions']),
        ]);
    }

    public function confirmTripComplete(Request $request, Booking $booking): JsonResponse
    {
        if ($booking->customer_id !== $request->user()->id) {
            abort(403);
        }

        try {
            $this->workflow->customerConfirmTripComplete($booking, $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Trip completion confirmed.',
            'data' => $booking->fresh(),
        ]);
    }

    public function operatorConfirmPayment(Request $request, Booking $booking): JsonResponse
    {
        try {
            $this->workflow->confirmPayment($booking, $request->user()->id, 'manual');
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Payment confirmed successfully.', 'data' => $booking->fresh()]);
    }

    public function accept(Request $request, Booking $booking): JsonResponse
    {
        try {
            $this->workflow->acceptBooking($booking, $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Booking accepted successfully.', 'data' => $booking->fresh()]);
    }

    public function reject(Request $request, Booking $booking): JsonResponse
    {
        try {
            $this->workflow->rejectBooking($booking, $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Booking rejected successfully.', 'data' => $booking->fresh()]);
    }

    public function driverCompleteTrip(Request $request, Booking $booking): JsonResponse
    {
        try {
            $this->workflow->driverCompleteTrip($booking, $request->user()->id);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Trip marked complete. Waiting for customer confirmation.', 'data' => $booking->fresh()]);
    }
}
