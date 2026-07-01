<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\DriverProfile;
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

        $schedules = Schedule::query()
            ->with([
                'route',
                'vehicle',
                'bookings' => fn ($q) => $q->whereNotIn('booking_status', ['cancelled', 'rejected'])->latest(),
            ])
            ->forDriverActiveTrips($user->id)
            ->orderBy('departure_time')
            ->get()
            ->filter(fn (Schedule $s): bool => $s->driverRelevantBookings()->isNotEmpty()
                && $s->driverWorkflowPhase() !== 'settled'
                && $s->isVisibleOnDriverDashboard())
            ->map(fn (Schedule $s) => [
                'id'             => $s->id,
                'route'          => $s->route->departure . ' → ' . $s->route->destination,
                'departure_time' => $s->departure_time->format('H:i, d/m/Y'),
                'status'         => $s->status,
                'driver_stage'   => $s->resolvedDriverStage(),
                'stage_label'    => $s->driver_id ? $s->bookingStatusLabel() : $s->statusLabel(),
                'movement_deadline' => $s->driverMovementDeadlineLabel(),
                'movement_deadline_at' => $s->driver_movement_deadline_at?->toIso8601String(),
                'display_status' => $s->displayStatus(),
                'status_label'   => $s->statusLabel(),
                'seats_label'    => $s->seatsLabel(),
                'passengers'     => $s->driverRelevantBookings()->count(),
                'trip_total'     => number_format($s->tripRevenueTotal(), 0, ',', '.'),
                'bookings'       => $s->driverRelevantBookings()->map(fn (Booking $b) => [
                    'passenger_name'   => $b->passenger_name,
                    'passenger_gender' => $b->passengerGenderLabel(),
                    'passenger_age'    => $b->passenger_age,
                    'passenger_profile'=> $b->passengerProfileDetail(),
                    'booking_mode'     => $b->bookingModeLabel(),
                    'booking_mode_key' => $b->booking_mode ?? 'shared',
                    'pickup_time'      => $b->pickupTimeLabel(),
                    'pickup'           => $b->driverPickupDetailLabel(),
                    'dropoff'          => $b->driverDropoffDetailLabel(),
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
            'schedules'         => $schedules,
            'pending_trip_requests' => $this->driverRequests->pendingGroupsForDriver($user->id)
                ->map(fn (array $group): array => [
                    'id' => $group['primary']->id,
                ])
                ->values(),
        ]);
    }
}
