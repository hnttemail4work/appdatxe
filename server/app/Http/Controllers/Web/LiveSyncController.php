<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\DriverProfile;
use App\Models\DriverTripRequest;
use App\Models\Schedule;
use App\Services\DriverTripRequestService;
use App\Services\ScheduleLifecycleService;
use App\Services\TripListingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LiveSyncController extends Controller
{
    public function __construct(
        private readonly ScheduleLifecycleService $scheduleLifecycle,
        private readonly TripListingService $tripListing,
        private readonly DriverTripRequestService $driverRequests,
    ) {
    }

    public function customer(Request $request)
    {
        $this->driverRequests->expireStale();

        $filters = $this->tripListing->filtersFromRequest($request);
        $schedules = $this->tripListing->query($filters);

        $pendingRequests = DriverTripRequest::query()
            ->where('customer_id', Auth::id())
            ->whereIn('status', ['pending', 'accepted', 'rejected', 'expired'])
            ->where('updated_at', '>=', now()->subHours(24))
            ->with(['driver', 'driverProfile', 'schedule.route'])
            ->latest()
            ->get()
            ->keyBy('schedule_id');

        $bookings = Auth::user()
            ->bookings()
            ->with(['schedule.route', 'schedule.vehicle'])
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (Booking $b) => [
                'id'             => $b->id,
                'ticket_code'    => $b->ticket_code,
                'route'          => $b->schedule->route->departure . ' → ' . $b->schedule->route->destination,
                'departure_time' => $b->schedule->departure_time->format('H:i · d/m/Y'),
                'seats'          => implode(', ', (array) $b->seat_numbers),
                'booking_status' => $b->booking_status,
                'payment_status' => $b->payment_status,
                'trip_status'    => $b->trip_status,
                'total_price'    => number_format($b->total_price, 0, ',', '.'),
                'has_pending_payment' => $b->hasPendingPaymentClaim(),
            ]);

        return response()->json([
            'synced_at' => now()->toIso8601String(),
            'trips'     => $schedules->map(fn (Schedule $s) => array_merge(
                $this->tripListing->serializeSchedule($s),
                [
                    'driver_request' => $this->driverRequests->serializeRequest(
                        $pendingRequests->get($s->id)
                    ),
                ]
            ))->values(),
            'bookings' => $bookings,
        ]);
    }

    public function driver(Request $request)
    {
        $this->driverRequests->expireStale();
        $this->scheduleLifecycle->sync();

        $user = Auth::user();
        $profile = DriverProfile::query()->where('user_id', $user->id)->first();

        $pendingIncoming = DriverTripRequest::query()
            ->where('driver_id', $user->id)
            ->where('status', 'pending')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->with(['schedule.route', 'schedule.vehicle', 'customer'])
            ->latest()
            ->get()
            ->map(fn (DriverTripRequest $r) => [
                'id'             => $r->id,
                'customer_name'  => $r->customer->name,
                'customer_phone' => $r->customer->phone,
                'route'          => $r->schedule->route->departure . ' → ' . $r->schedule->route->destination,
                'departure_time' => $r->schedule->departure_time->format('H:i · d/m/Y'),
                'vehicle'        => ucfirst($r->schedule->vehicle->type) . ' · ' . $r->schedule->vehicle->license_plate,
                'expires_at'     => $r->expires_at?->toIso8601String(),
            ]);

        $schedules = Schedule::query()
            ->with(['route', 'vehicle'])
            ->withCount([
                'bookings as confirmed_passengers_count' => fn ($q) => $q->where('booking_status', 'confirmed'),
            ])
            ->where('driver_id', $user->id)
            ->where('departure_time', '>=', now()->subHours(2))
            ->orderBy('departure_time')
            ->get()
            ->map(fn (Schedule $s) => [
                'id'             => $s->id,
                'route'          => $s->route->departure . ' → ' . $s->route->destination,
                'departure_time' => $s->departure_time->format('H:i · d/m/Y'),
                'status'         => $s->status,
                'status_label'   => $s->statusLabel(),
                'seats_label'    => $s->seatsLabel(),
                'passengers'     => $s->confirmed_passengers_count,
            ]);

        return response()->json([
            'synced_at'         => now()->toIso8601String(),
            'driver_code'       => $profile?->driver_code,
            'availability'      => $profile?->availability_status ?? 'off_duty',
            'availability_label'=> $profile?->availabilityLabel() ?? 'Nghỉ / Bận',
            'pending_requests'  => $pendingIncoming,
            'schedules'         => $schedules,
        ]);
    }

    public function operator(Request $request)
    {
        $this->driverRequests->expireStale();
        $this->scheduleLifecycle->sync();

        $user = Auth::user();
        $operatorId = $user->role === 'admin' ? null : $user->id;

        app(\App\Services\DriverAssignmentService::class)->autoAssignUnassigned($operatorId);

        $todayTrips = Schedule::query()
            ->with(['route', 'driver'])
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
            ->get()
            ->map(fn (Schedule $s) => [
                'id'           => $s->id,
                'time'         => $s->departure_time->format('H:i'),
                'route'        => $s->route->departure . ' → ' . $s->route->destination,
                'driver'       => $s->driver_id
                    ? ($s->driver?->name ?? $s->driver_name)
                    : 'Chờ phân bổ',
                'seats_label'  => $s->seatsLabel(),
                'status'       => $s->status,
                'status_label' => $s->statusLabel(),
            ]);

        $pendingDriverRequests = DriverTripRequest::query()
            ->where('status', 'pending')
            ->whereHas('schedule.vehicle', function ($q) use ($user): void {
                if ($user->role !== 'admin') {
                    $q->where('operator_id', $user->id);
                }
            })
            ->with(['driver', 'customer', 'schedule.route'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (DriverTripRequest $r) => [
                'id'       => $r->id,
                'route'    => $r->schedule->route->departure . ' → ' . $r->schedule->route->destination,
                'driver'   => $r->driver->name,
                'customer' => $r->customer->name,
            ]);

        return response()->json([
            'synced_at'                => now()->toIso8601String(),
            'today_trips'              => $todayTrips,
            'pending_driver_requests'  => $pendingDriverRequests,
        ]);
    }
}
