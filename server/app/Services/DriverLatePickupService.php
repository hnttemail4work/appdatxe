<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\DriverProfile;
use App\Models\DriverTripRequest;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Tài xế chưa đến điểm đón đúng giờ — nhắc bấm Tiếp tục; hết 1 phút thì gỡ và tìm tài xế khác.
 */
class DriverLatePickupService
{
    public const CONTINUE_WINDOW_MINUTES = 1;

    public function __construct(
        private readonly DriverTripRequestService $tripRequests,
        private readonly DriverBehaviorPenaltyService $penalties,
    ) {
    }

    public function processDuePrompts(): int
    {
        $prompted = 0;

        Schedule::query()
            ->with(['route', 'vehicle', 'bookings'])
            ->whereNotNull('driver_id')
            ->where('driver_stage', Schedule::DRIVER_STAGE_ASSIGNED)
            ->whereIn('status', ['scheduled', 'running'])
            ->whereNull('driver_late_pickup_prompt_at')
            ->each(function (Schedule $schedule) use (&$prompted): void {
                if ($this->stampPromptIfDue($schedule)) {
                    $prompted++;
                }
            });

        return $prompted;
    }

    public function expireOverdueContinue(): int
    {
        $released = 0;

        Schedule::query()
            ->with(['route', 'vehicle', 'bookings'])
            ->whereNotNull('driver_id')
            ->where('driver_stage', Schedule::DRIVER_STAGE_ASSIGNED)
            ->whereNotNull('driver_late_pickup_continue_deadline_at')
            ->where('driver_late_pickup_continue_deadline_at', '<=', now())
            ->each(function (Schedule $schedule) use (&$released): void {
                if ($this->releaseDriverAfterTimeout($schedule)) {
                    $released++;
                }
            });

        return $released;
    }

    public function stampPromptIfDue(Schedule $schedule): bool
    {
        $schedule->loadMissing(['bookings']);
        $booking = $schedule->driverRelevantBookings()->first();

        if (! $booking || ! $this->isPickupDue($booking)) {
            return false;
        }

        if ($schedule->driver_late_pickup_prompt_at) {
            return false;
        }

        $deadline = now()->addMinutes(self::CONTINUE_WINDOW_MINUTES);
        $schedule->update([
            'driver_late_pickup_prompt_at'            => now(),
            'driver_late_pickup_continue_deadline_at' => $deadline,
        ]);

        return true;
    }

    public function confirmContinue(Schedule $schedule, int $driverUserId): void
    {
        if ((int) $schedule->driver_id !== $driverUserId) {
            throw new InvalidArgumentException('Bạn không được phân công cho chuyến này.');
        }

        if ($schedule->resolvedDriverStage() !== Schedule::DRIVER_STAGE_ASSIGNED) {
            throw new InvalidArgumentException('Chuyến đã chuyển bước — không cần xác nhận.');
        }

        $schedule->update([
            'driver_late_pickup_prompt_at'            => null,
            'driver_late_pickup_continue_deadline_at' => null,
        ]);
    }

    public function latePickupPromptPayload(Schedule $schedule): ?array
    {
        if ($schedule->resolvedDriverStage() !== Schedule::DRIVER_STAGE_ASSIGNED
            || ! $schedule->driver_late_pickup_prompt_at
            || ! $schedule->driver_late_pickup_continue_deadline_at) {
            return null;
        }

        $booking = $schedule->driverRelevantBookings()->first();
        $etaLabel = $this->etaLabel($schedule, $booking);

        return [
            'active'       => $schedule->driver_late_pickup_continue_deadline_at->isFuture(),
            'message'      => 'Đã đến giờ đón — vui lòng đến điểm đón khách' . ($etaLabel ? ' (dự kiến ' . $etaLabel . ')' : '') . '.',
            'hint'         => 'Bấm «Đến điểm đón» khi bạn đến điểm đón khách.',
            'deadline_at'  => $schedule->driver_late_pickup_continue_deadline_at->toIso8601String(),
            'continue_url' => route('driver.schedules.latePickupContinue', $schedule),
        ];
    }

    /** Nhắc tài xế di chuyển khi đến lúc cần xuất phát (theo km + giờ đón). */
    public function departReminderPayload(Schedule $schedule): ?array
    {
        if ($schedule->resolvedDriverStage() !== Schedule::DRIVER_STAGE_ASSIGNED
            || $schedule->driver_late_pickup_prompt_at) {
            return null;
        }

        $booking = $schedule->driverRelevantBookings()->first();
        if (! $booking || ! $this->shouldDepartForPickup($booking)) {
            return null;
        }

        $etaLabel = $this->etaLabel($schedule, $booking);

        return [
            'message' => 'Vui lòng di chuyển đến điểm đón' . ($etaLabel ? ' — dự kiến ' . $etaLabel : '') . '.',
            'hint'    => 'Bấm «Đến điểm đón» khi bạn đến điểm đón khách.',
        ];
    }

    public function releaseDriverAfterTimeout(Schedule $schedule): bool
    {
        $schedule->loadMissing(['route', 'vehicle', 'bookings']);

        if (! $schedule->driver_id
            || $schedule->resolvedDriverStage() !== Schedule::DRIVER_STAGE_ASSIGNED
            || ! $schedule->driver_late_pickup_continue_deadline_at
            || $schedule->driver_late_pickup_continue_deadline_at->isFuture()) {
            return false;
        }

        $formerDriverId = (int) $schedule->driver_id;
        $profile = DriverProfile::query()->where('user_id', $formerDriverId)->first();
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
                'driver_id'                               => null,
                'driver_name'                             => 'Chờ phân bổ',
                'driver_stage'                            => null,
                'driver_assigned_at'                      => null,
                'driver_movement_deadline_at'             => null,
                'driver_late_pickup_prompt_at'            => null,
                'driver_late_pickup_continue_deadline_at' => null,
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

        if ($profile) {
            $this->penalties->recordLateContinueTimeout($profile);
        }

        app(DriverAvailabilityService::class)->syncAfterTripCompleted($formerDriverId);

        foreach ($bookings as $booking) {
            $freshBooking = $booking->fresh(['schedule.route', 'schedule.vehicle']);
            if ($freshBooking && ! $freshBooking->schedule?->driver_id) {
                $this->tripRequests->tryReassignAfterDriverRelease(
                    $freshBooking,
                    $formerDriverId,
                );
            }
        }

        return true;
    }

    private function shouldDepartForPickup(Booking $booking): bool
    {
        $pickupAt = $booking->tripStartAt();

        if (! $pickupAt instanceof Carbon) {
            return false;
        }

        $booking->loadMissing('schedule');
        $schedule = $booking->schedule;

        if (! $schedule) {
            return false;
        }

        $travelMinutes = $this->travelMinutesToPickup($schedule, $booking);
        $departBy = $pickupAt->copy()->subMinutes($travelMinutes);

        return now()->gte($departBy) && $pickupAt->isFuture();
    }

    private function isPickupDue(Booking $booking): bool
    {
        $pickupAt = $booking->tripStartAt();

        return $pickupAt instanceof Carbon && $pickupAt->lte(now());
    }

    public function pickupEtaLabel(Schedule $schedule, Booking $booking): ?string
    {
        if (! $schedule->driver_id) {
            return null;
        }

        return $this->etaLabel($schedule, $booking);
    }

    private function etaLabel(Schedule $schedule, ?Booking $booking): ?string
    {
        if (! $booking) {
            return null;
        }

        $travelMinutes = $this->travelMinutesToPickup($schedule, $booking);

        if ($travelMinutes < 60) {
            return $travelMinutes . ' phút nữa';
        }

        $hours = (int) floor($travelMinutes / 60);
        $mins = $travelMinutes % 60;

        return $mins > 0 ? ($hours . ' giờ ' . $mins . ' phút nữa') : ($hours . ' giờ nữa');
    }

    private function travelMinutesToPickup(Schedule $schedule, Booking $booking): int
    {
        $profile = DriverProfile::query()->where('user_id', $schedule->driver_id)->first();
        if (! $profile) {
            return 1;
        }

        $distanceKm = max(0.5, (float) ($booking->driver_pickup_distance_km ?? 0));
        if ($distanceKm <= 0.5 && $booking->pickup_lat !== null && $booking->pickup_lng !== null) {
            $snap = app(DriverProximityService::class)->snapshotPickupDistance($booking, $profile);
            if ($snap !== null) {
                $distanceKm = max(0.5, $snap);
            }
        }

        return (int) max(1, ceil($distanceKm / DriverMovementConfirmService::ASSUMED_SPEED_KMH * 60));
    }
}
