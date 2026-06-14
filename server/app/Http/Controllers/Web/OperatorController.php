<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\DriverProfile;
use App\Models\Schedule;
use App\Models\ScheduleTemplate;
use App\Models\TripRoute;
use App\Models\Vehicle;
use App\Services\BookingWorkflowService;
use App\Services\ScheduleLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class OperatorController extends Controller
{
    public function __construct(
        private readonly BookingWorkflowService $workflow,
        private readonly ScheduleLifecycleService $scheduleLifecycle,
    ) {
    }

    public function dashboard(Request $request)
    {
        $this->scheduleLifecycle->sync();

        $user = Auth::user();

        $vehicles = Vehicle::query()
            ->when($user->role !== 'admin', fn ($q) => $q->where('operator_id', $user->id))
            ->with('schedules.route')
            ->get();

        $drivers = DriverProfile::query()
            ->when($user->role !== 'admin', fn ($q) => $q->where('operator_id', $user->id))
            ->where('status', 'active')
            ->with('user')
            ->get();

        $routes = TripRoute::query()->where('is_active', true)->get();

        $todayTrips = Schedule::query()
            ->with(['route', 'vehicle', 'driver', 'template'])
            ->withCount([
                'seatReservations as active_reservations_count' => function ($q): void {
                    $q->whereIn('status', ['held', 'booked'])
                        ->where(fn ($n) => $n->whereNull('expires_at')->orWhere('expires_at', '>', now()));
                },
            ])
            ->whereHas('vehicle', function ($q) use ($user): void {
                if ($user->role !== 'admin') {
                    $q->where('operator_id', $user->id);
                }
            })
            ->whereDate('service_date', today())
            ->orderBy('departure_time')
            ->get();

        $passengers = Booking::query()
            ->with(['customer', 'schedule.route', 'schedule.vehicle', 'paymentTransactions'])
            ->whereHas('schedule.vehicle', function ($q) use ($user): void {
                if ($user->role !== 'admin') {
                    $q->where('operator_id', $user->id);
                }
            })
            ->whereNotIn('booking_status', ['cancelled', 'rejected'])
            ->latest()
            ->get();

        return view('operator.dashboard', compact('vehicles', 'drivers', 'routes', 'passengers', 'todayTrips'));
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
            'driver_name'    => ['nullable', 'string', 'max:255'],
            'departure_time' => ['required', 'date_format:H:i'],
        ]);

        $operatorId = Auth::user()->role === 'admin' ? null : Auth::id();
        $vehicleQuery = Vehicle::query()->where('id', $validated['vehicle_id']);
        if ($operatorId) {
            $vehicleQuery->where('operator_id', $operatorId);
        }
        $vehicle = $vehicleQuery->firstOrFail();

        $driverName = $validated['driver_name'] ?? null;
        if (! $driverName && ! empty($validated['driver_id'])) {
            $driverName = \App\Models\User::query()->whereKey($validated['driver_id'])->value('name');
        }

        ScheduleTemplate::query()->create([
            'route_id'       => $validated['route_id'],
            'vehicle_id'     => $vehicle->id,
            'driver_id'      => $validated['driver_id'] ?? null,
            'driver_name'    => $driverName ?: 'Chưa phân công',
            'departure_time' => $validated['departure_time'] . ':00',
            'status'         => 'active',
        ]);

        $this->scheduleLifecycle->sync();

        return redirect()->route('operator.dashboard')
            ->with('success', 'Đã tạo chuyến chạy hằng ngày lúc ' . $validated['departure_time'] . '.');
    }

    public function confirmPayment(Request $request, Booking $booking)
    {
        if (! $this->isBookingForOperator($booking)) {
            abort(403);
        }

        try {
            $this->workflow->confirmPayment($booking, Auth::id(), 'manual');
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        return redirect()->route('operator.dashboard')
            ->with('success', 'Đã xác nhận thanh toán. Bạn có thể duyệt chuyến cho tài xế.');
    }

    public function acceptBooking(Request $request, Booking $booking)
    {
        if (! $this->isBookingForOperator($booking)) {
            abort(403);
        }

        try {
            $this->workflow->acceptBooking($booking, Auth::id());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        return redirect()->route('operator.dashboard')
            ->with('success', 'Đã duyệt chuyến. Tài xế có thể xem thông tin hành khách.');
    }

    public function rejectBooking(Request $request, Booking $booking)
    {
        if (! $this->isBookingForOperator($booking)) {
            abort(403);
        }

        try {
            $this->workflow->rejectBooking($booking, Auth::id());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['booking' => $e->getMessage()]);
        }

        return redirect()->route('operator.dashboard')->with('success', 'Booking đã bị từ chối.');
    }

    private function isBookingForOperator(Booking $booking): bool
    {
        if (Auth::user()->role === 'admin') {
            return true;
        }

        return $booking->schedule?->vehicle?->operator_id === Auth::id();
    }
}
