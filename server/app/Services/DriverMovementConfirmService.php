<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\DriverTripRequest;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Hạn tài xế bấm «Đến điểm đón» sau khi được gán cuốc.
 *
 * - Đón trong ≤ 60 phút: 5 phút kể từ lúc gán.
 * - Đón > 60 phút: tối đa 30 phút, rút ngắn theo km + thời gian cần di chuyển đến điểm đón.
 */
class DriverMovementConfirmService
{
    public const URGENT_PICKUP_WITHIN_MINUTES = 60;

    public const URGENT_CONFIRM_MINUTES = 5;

    public const LONG_BOOKING_CONFIRM_MAX_MINUTES = 30;

    public const LONG_BOOKING_CONFIRM_MIN_MINUTES = 10;

    public const ASSUMED_SPEED_KMH = 30;

    public const MIN_DEADLINE_BUFFER_MINUTES = 2;

    public function stampAssignmentDeadline(Schedule $schedule, ?Booking $booking = null): void
    {
        if (! $schedule->driver_id) {
            return;
        }

        $booking ??= $schedule->driverRelevantBookings()->first();
        if (! $booking) {
            return;
        }

        $assignedAt = now();
        $deadline = $this->computeDeadline($schedule, $booking, $assignedAt);

        $schedule->update([
            'driver_assigned_at'          => $assignedAt,
            'driver_movement_deadline_at' => $deadline,
            'driver_stage'                => $schedule->driver_stage ?: Schedule::DRIVER_STAGE_ASSIGNED,
        ]);
    }

    public function clearDeadline(Schedule $schedule): void
    {
        if (! $schedule->driver_movement_deadline_at && ! $schedule->driver_assigned_at) {
            return;
        }

        $schedule->update([
            'driver_movement_deadline_at' => null,
        ]);
    }

    public function computeDeadline(Schedule $schedule, Booking $booking, ?Carbon $assignedAt = null): Carbon
    {
        $assignedAt ??= now();
        $pickupAt = $booking->tripStartAt() ?? $schedule->departure_time;
        $minutesToPickup = max(0, (int) $assignedAt->diffInMinutes($pickupAt, false));

        $distanceKm = max(0.5, (float) ($booking->driver_pickup_distance_km ?? 0));
        if ($distanceKm <= 0.5 && $booking->pickup_lat !== null && $booking->pickup_lng !== null && $schedule->driver_id) {
            $profile = \App\Models\DriverProfile::query()->where('user_id', $schedule->driver_id)->first();
            if ($profile) {
                $snap = app(DriverProximityService::class)->snapshotPickupDistance($booking, $profile);
                if ($snap !== null) {
                    $distanceKm = max(0.5, $snap);
                }
            }
        }

        $travelMinutes = (int) max(1, ceil($distanceKm / self::ASSUMED_SPEED_KMH * 60));

        if ($minutesToPickup <= self::URGENT_PICKUP_WITHIN_MINUTES) {
            $windowMinutes = self::URGENT_CONFIRM_MINUTES;
        } else {
            $windowMinutes = min(
                self::LONG_BOOKING_CONFIRM_MAX_MINUTES,
                max(
                    self::LONG_BOOKING_CONFIRM_MIN_MINUTES,
                    (int) round($travelMinutes * 0.5 + 8),
                ),
            );

            $latestByPickup = $minutesToPickup - $travelMinutes - 3;
            $windowMinutes = min($windowMinutes, max(self::URGENT_CONFIRM_MINUTES, $latestByPickup));
        }

        $deadline = $assignedAt->copy()->addMinutes($windowMinutes);
        $mustDepartBy = $pickupAt->copy()->subMinutes($travelMinutes + 2);

        if ($deadline->gt($mustDepartBy)) {
            $deadline = $mustDepartBy;
        }

        $floor = now()->copy()->addMinutes(self::MIN_DEADLINE_BUFFER_MINUTES);
        if ($deadline->lt($floor)) {
            $deadline = $floor;
        }

        return $deadline;
    }

    public function confirmWindowMinutes(Schedule $schedule, ?Booking $booking = null): int
    {
        $booking ??= $schedule->driverRelevantBookings()->first();
        if (! $booking || ! $schedule->driver_assigned_at || ! $schedule->driver_movement_deadline_at) {
            return self::URGENT_CONFIRM_MINUTES;
        }

        return max(
            1,
            (int) $schedule->driver_assigned_at->diffInMinutes($schedule->driver_movement_deadline_at),
        );
    }

    public function movementDeadlineLabel(Schedule $schedule): ?string
    {
        if (! $schedule->driver_movement_deadline_at
            || $schedule->resolvedDriverStage() !== Schedule::DRIVER_STAGE_ASSIGNED) {
            return null;
        }

        if (! $schedule->driver_movement_deadline_at->isFuture()) {
            return 'Đã quá hạn xác nhận di chuyển';
        }

        $seconds = $schedule->driver_movement_deadline_at->getTimestamp() - now()->getTimestamp();
        $minutes = max(1, (int) ceil($seconds / 60));

        return 'Còn ' . $minutes . ' phút để bấm «Đến điểm đón»';
    }

    /** Hết hạn xác nhận di chuyển → gỡ tài xế và thử gán người khác. */
    public function expireOverdue(): int
    {
        $released = 0;

        Schedule::query()
            ->with(['route', 'vehicle', 'bookings'])
            ->whereNotNull('driver_id')
            ->where('driver_stage', Schedule::DRIVER_STAGE_ASSIGNED)
            ->whereIn('status', ['scheduled', 'running'])
            ->where('departure_time', '>', now())
            ->where(function ($query): void {
                $query->where(function ($q): void {
                    $q->whereNotNull('driver_movement_deadline_at')
                        ->where('driver_movement_deadline_at', '<=', now());
                })->orWhere(function ($q): void {
                    $q->whereNull('driver_movement_deadline_at')
                        ->whereNotNull('driver_assigned_at')
                        ->where('driver_assigned_at', '<=', now()->subMinutes(self::URGENT_CONFIRM_MINUTES));
                });
            })
            ->each(function (Schedule $schedule) use (&$released): void {
                if ($this->releaseDriverAndReassign($schedule)) {
                    $released++;
                }
            });

        return $released;
    }

    public function releaseDriverAndReassign(Schedule $schedule): bool
    {
        $schedule->loadMissing(['route', 'vehicle', 'bookings']);

        if (! $schedule->driver_id
            || $schedule->resolvedDriverStage() !== Schedule::DRIVER_STAGE_ASSIGNED) {
            return false;
        }

        if ($schedule->driver_movement_deadline_at?->isFuture()) {
            return false;
        }

        if (! $schedule->driver_movement_deadline_at && $schedule->driver_assigned_at) {
            $primary = $schedule->driverRelevantBookings()->first();
            if ($primary) {
                $expected = $this->computeDeadline($schedule, $primary, $schedule->driver_assigned_at);
                if ($expected->isFuture()) {
                    $schedule->update(['driver_movement_deadline_at' => $expected]);

                    return false;
                }
            }
        }

        $formerDriverId = (int) $schedule->driver_id;
        $bookings = $schedule->driverRelevantBookings()
            ->filter(fn (Booking $b): bool => ! in_array($b->booking_status, ['cancelled', 'rejected'], true)
                && $b->trip_status !== 'completed')
            ->values();

        if ($bookings->isEmpty()) {
            return false;
        }

        DB::transaction(function () use ($schedule, $formerDriverId, $bookings): void {
            $locked = Schedule::query()->lockForUpdate()->findOrFail($schedule->id);

            if ((int) $locked->driver_id !== $formerDriverId
                || $locked->resolvedDriverStage() !== Schedule::DRIVER_STAGE_ASSIGNED) {
                throw new InvalidArgumentException('Chuyến đã thay đổi.');
            }

            $locked->update([
                'driver_id'                   => null,
                'driver_name'                 => null,
                'driver_stage'                => null,
                'driver_assigned_at'          => null,
                'driver_movement_deadline_at' => null,
            ]);

            foreach ($bookings as $booking) {
                if ($booking->assigned_driver_id) {
                    $booking->update(['assigned_driver_id' => null]);
                }

                DriverTripRequest::query()->updateOrCreate(
                    [
                        'schedule_id'   => $locked->id,
                        'contact_phone' => (string) $booking->contact_phone,
                        'driver_id'     => $formerDriverId,
                    ],
                    [
                        'status'       => 'expired',
                        'responded_at' => now(),
                        'expires_at'   => null,
                    ],
                );
            }
        });

        $tripRequests = app(DriverTripRequestService::class);

        foreach ($bookings as $booking) {
            $fresh = $booking->fresh(['schedule.route', 'schedule.vehicle']);
            if ($fresh && ! $fresh->schedule?->driver_id) {
                $tripRequests->autoAssignForBooking($fresh);
            }
        }

        return true;
    }
}
