<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\DriverProfile;
use App\Models\DriverTripRequest;
use App\Models\Schedule;
use App\Services\DriverTripRequestService;
use App\Services\ScheduleLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LiveSyncController extends Controller
{
    public function __construct(
        private readonly ScheduleLifecycleService $scheduleLifecycle,
        private readonly DriverTripRequestService $driverRequests,
    ) {
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
            ->with(['schedule.route', 'schedule.vehicle', 'schedule.bookings'])
            ->latest()
            ->get()
            ->map(function (DriverTripRequest $r) {
                $booking = $r->relatedBooking();

                return [
                    'id'               => $r->id,
                    'accept_url'       => route('driver.tripRequests.accept', $r),
                    'reject_url'       => route('driver.tripRequests.reject', $r),
                    'passenger_name'   => $booking?->passenger_name,
                    'booking_mode'     => $booking?->bookingModeLabel(),
                    'booking_mode_key' => $booking?->booking_mode ?? 'shared',
                    'route'            => $r->schedule->route->departure . ' → ' . $r->schedule->route->destination,
                    'departure_time'   => $r->schedule->departure_time->format('H:i · d/m/Y'),
                    'expires_at'       => $r->expires_at?->toIso8601String(),
                    'expires_in_label' => $r->acceptTimeRemainingLabel(),
                    'pickup'           => $booking?->driverPickupDetailLabel(),
                    'dropoff'          => $booking?->driverDropoffDetailLabel(),
                    'notes'            => $booking?->notes,
                    'trip_total'       => $booking
                        ? number_format((float) $booking->total_price, 0, ',', '.')
                        : null,
                    'trip_code'        => $r->schedule->shortTripCode(),
                    'meta_label'       => $r->schedule->tripMetaLabel(),
                    'seats_label'      => $booking
                        ? ($booking->booking_mode === 'whole_car' ? 'Cả xe' : ($booking->seatCount() > 0 ? $booking->seatCountLabel() : null))
                        : null,
                ];
            });

        $schedules = Schedule::query()
            ->with([
                'route',
                'vehicle',
                'bookings' => fn ($q) => $q->whereNotIn('booking_status', ['cancelled', 'rejected'])->latest(),
            ])
            ->where('driver_id', $user->id)
            ->whereHas('bookings', fn ($q) => $q->whereNotIn('booking_status', ['cancelled', 'rejected']))
            ->where('departure_time', '>=', now()->subHours(2))
            ->orderBy('departure_time')
            ->get()
            ->map(fn (Schedule $s) => [
                'id'             => $s->id,
                'route'          => $s->route->departure . ' → ' . $s->route->destination,
                'departure_time' => $s->departure_time->format('H:i · d/m/Y'),
                'status'         => $s->status,
                'display_status' => $s->displayStatus(),
                'status_label'   => $s->statusLabel(),
                'seats_label'    => $s->seatsLabel(),
                'passengers'     => $s->bookings->count(),
                'trip_total'     => number_format($s->tripRevenueTotal(), 0, ',', '.'),
                'bookings'       => $s->bookings->map(fn (Booking $b) => [
                    'passenger_name'   => $b->passenger_name,
                    'booking_mode'     => $b->bookingModeLabel(),
                    'booking_mode_key' => $b->booking_mode ?? 'shared',
                    'pickup'           => $b->pickupLabel(),
                    'dropoff'          => $b->dropoffLabel(),
                    'notes'            => $b->notes,
                    'seats_label'      => ($b->booking_mode ?? 'shared') === 'shared' ? $b->seatCountLabel() : null,
                    'booking_status'   => $b->booking_status,
                    'payment_status'   => $b->payment_status,
                    'trip_status'      => $b->trip_status,
                ])->values(),
            ]);

        return response()->json([
            'synced_at'         => now()->toIso8601String(),
            'driver_code'       => $profile?->driver_code,
            'availability'      => $profile?->availability_status ?? 'off_duty',
            'availability_label'=> $profile?->displayStatusLabel() ?? 'Nghỉ',
            'pending_requests'  => $pendingIncoming,
            'schedules'         => $schedules,
        ]);
    }

    public function operator(Request $request)
    {
        $this->driverRequests->expireStale();
        $this->scheduleLifecycle->sync();

        $user = Auth::user();

        $todayTrips = Schedule::query()
            ->with(['route', 'driver'])
            ->withCount([
                'bookings as active_bookings_count' => fn ($q) => $q->whereNotIn('booking_status', ['cancelled', 'rejected']),
            ])
            ->whereHas('bookings', fn ($q) => $q->whereNotIn('booking_status', ['cancelled', 'rejected']))
            ->whereHas('vehicle', fn ($q) => $q->where('operator_id', $user->id))
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
                'bookings_count' => $s->active_bookings_count,
                'status'       => $s->status,
                'display_status' => $s->displayStatus(),
                'status_label' => $s->statusLabel(),
                'status_color' => match ($s->displayStatus()) {
                    'running'   => 'primary',
                    'completed' => 'success',
                    'cancelled' => 'danger',
                    default     => 'warning text-dark',
                },
            ]);

        $pendingDriverRequests = DriverTripRequest::query()
            ->where('status', 'pending')
            ->whereHas('schedule.vehicle', fn ($q) => $q->where('operator_id', $user->id))
            ->with(['driver', 'schedule.route'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (DriverTripRequest $r) => [
                'id'            => $r->id,
                'route'         => $r->schedule->route->departure . ' → ' . $r->schedule->route->destination,
                'driver'        => $r->driver->name,
                'contact_phone' => $r->contact_phone,
            ]);

        return response()->json([
            'synced_at'                => now()->toIso8601String(),
            'today_trips'              => $todayTrips,
            'pending_driver_requests'  => $pendingDriverRequests,
        ]);
    }
}
