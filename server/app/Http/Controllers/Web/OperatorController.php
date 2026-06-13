<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\DriverProfile;
use App\Models\Schedule;
use App\Models\TripRoute;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OperatorController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = Auth::user();

        $vehicles = Vehicle::query()
            ->where('operator_id', $user->id)
            ->with('schedules.route')
            ->get();

        $drivers = DriverProfile::query()
            ->where('operator_id', $user->id)
            ->where('status', 'active')
            ->with('user')
            ->get();

        $routes = TripRoute::query()->where('is_active', true)->get();

        $passengers = Booking::query()
            ->with(['customer', 'schedule.route', 'schedule.vehicle'])
            ->whereHas('schedule.vehicle', function ($q) use ($user): void {
                $q->where('operator_id', $user->id);
            })
            ->latest()
            ->get();

        return view('operator.dashboard', compact('vehicles', 'drivers', 'routes', 'passengers'));
    }

    public function storeVehicle(Request $request)
    {
        $validated = $request->validate([
            'license_plate' => ['required', 'string', 'max:50', 'unique:vehicles,license_plate'],
            'type' => ['required', 'string', 'in:limousine,sedan,suv'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
            'status' => ['required', 'string', 'in:active,maintenance,inactive'],
        ]);

        Vehicle::query()->create(array_merge($validated, ['operator_id' => Auth::id()]));

        return redirect()->route('operator.dashboard')->with('success', 'Xe mới đã được thêm.');
    }

    public function storeSchedule(Request $request)
    {
        $validated = $request->validate([
            'route_id'       => ['required', 'exists:routes,id'],
            'vehicle_id'     => ['required', 'exists:vehicles,id'],
            'driver_id'      => ['nullable', 'exists:users,id'],
            'driver_name'    => ['required', 'string', 'max:255'],
            'departure_time' => ['required', 'date'],
            'status'         => ['required', 'string', 'in:draft,scheduled,running,completed,cancelled'],
        ]);

        $vehicle = Vehicle::query()->where('operator_id', Auth::id())->findOrFail($validated['vehicle_id']);

        Schedule::query()->create([
            'route_id'       => $validated['route_id'],
            'vehicle_id'     => $vehicle->id,
            'driver_id'      => $validated['driver_id'] ?? null,
            'driver_name'    => $validated['driver_name'],
            'departure_time' => $validated['departure_time'],
            'available_seats' => $vehicle->capacity,
            'status'         => $validated['status'],
        ]);

        return redirect()->route('operator.dashboard')->with('success', 'Lịch trình mới đã được tạo.');
    }

    public function acceptBooking(Request $request, Booking $booking)
    {
        if (! $this->isBookingForOperator($booking)) {
            abort(403);
        }

        if ($booking->payment_status !== 'paid') {
            return back()->withErrors(['booking' => 'Booking cần phải thanh toán trước khi xác nhận.']);
        }

        DB::transaction(function () use ($booking) {
            $booking->update(['booking_status' => 'confirmed', 'trip_status' => 'confirmed']);
            $booking->seatReservations()->update(['status' => 'booked', 'expires_at' => null]);
            $this->syncScheduleAvailability($booking->schedule()->with(['vehicle', 'seatReservations'])->first());
        });

        return redirect()->route('operator.dashboard')->with('success', 'Booking đã được chấp nhận.');
    }

    public function rejectBooking(Request $request, Booking $booking)
    {
        if (! $this->isBookingForOperator($booking)) {
            abort(403);
        }

        DB::transaction(function () use ($booking) {
            $booking->update(['booking_status' => 'rejected', 'trip_status' => 'cancelled']);
            $booking->seatReservations()->update(['status' => 'released', 'expires_at' => now()]);
            $this->syncScheduleAvailability($booking->schedule()->with(['vehicle', 'seatReservations'])->first());
        });

        return redirect()->route('operator.dashboard')->with('success', 'Booking đã bị từ chối.');
    }

    private function isBookingForOperator(Booking $booking): bool
    {
        return $booking->schedule?->vehicle?->operator_id === Auth::id();
    }

    private function syncScheduleAvailability(Schedule $schedule): void
    {
        if (! $schedule) {
            return;
        }

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
}
