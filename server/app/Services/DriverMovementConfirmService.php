<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Schedule;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Hạn tài xế bấm «Xác nhận» / «Đến điểm đón» sau khi được gán cuốc.
 *
 * Hạn = giờ đón − (thời gian di chuyển ước tính + 15 phút).
 */
class DriverMovementConfirmService
{
    public const ASSUMED_SPEED_KMH = 30;

    public const MOVEMENT_CONFIRM_LEAD_MINUTES = 15;

    /** Hiện nút «Xác nhận» đi đón từ trước giờ đón bao nhiêu phút. */
    public const MOVEMENT_CONFIRM_VISIBLE_BEFORE_MINUTES = 60;

    public const MIN_DEADLINE_BUFFER_MINUTES = 2;

    /** Đã tới cửa sổ bấm «Xác nhận» (Đặt ngay / trong 1 giờ trước giờ đón / quá giờ đón). */
    public function isConfirmActionAvailable(Schedule $schedule, ?Booking $booking = null): bool
    {
        if (! $schedule->needsDriverMovementConfirm()) {
            return false;
        }

        $booking ??= $schedule->driverRelevantBookings()->first();
        if (! $booking) {
            return true;
        }

        if ($booking->isOnDemandPickup()) {
            return true;
        }

        $pickupAt = $booking->operationalPickupAt() ?? $booking->tripStartAt();
        if (! $pickupAt instanceof Carbon) {
            return true;
        }

        $opensAt = $pickupAt->copy()->subMinutes(self::MOVEMENT_CONFIRM_VISIBLE_BEFORE_MINUTES);

        return now()->gte($opensAt);
    }

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

        // Nhận cuốc = đang đi đón — không còn cửa sổ «Xác nhận» / tự hủy theo deadline.
        $schedule->update([
            'driver_assigned_at'            => $assignedAt,
            'driver_movement_deadline_at'   => null,
            'driver_movement_confirmed_at'  => $assignedAt,
            'driver_stage'                  => $schedule->driver_stage ?: Schedule::DRIVER_STAGE_ASSIGNED,
            'driver_depart_reminder_sent_at' => null,
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

    public function confirmMovement(Schedule $schedule, int $driverUserId): void
    {
        if ((int) $schedule->driver_id !== $driverUserId) {
            throw new InvalidArgumentException('Bạn không được phân công cho chuyến này.');
        }

        if ($schedule->resolvedDriverStage() !== Schedule::DRIVER_STAGE_ASSIGNED) {
            throw new InvalidArgumentException('Chuyến đã chuyển bước — không cần xác nhận.');
        }

        if ($schedule->driver_movement_confirmed_at) {
            throw new InvalidArgumentException('Bạn đã xác nhận rồi.');
        }

        $this->clearDeadline($schedule);

        $schedule->update(['driver_movement_confirmed_at' => now()]);
    }

    public function computeDeadline(Schedule $schedule, Booking $booking, ?Carbon $assignedAt = null): Carbon
    {
        $pickupAt = $booking->tripStartAt() ?? $schedule->departure_time;
        $travelMinutes = $this->travelMinutesForBooking($schedule, $booking);

        $deadline = $pickupAt->copy()->subMinutes($travelMinutes + self::MOVEMENT_CONFIRM_LEAD_MINUTES);

        $floor = now()->copy()->addMinutes(self::MIN_DEADLINE_BUFFER_MINUTES);
        if ($deadline->lt($floor)) {
            $deadline = $floor;
        }

        return $deadline;
    }

    public function travelMinutesForBooking(Schedule $schedule, Booking $booking): int
    {
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

        return (int) max(1, ceil($distanceKm / self::ASSUMED_SPEED_KMH * 60));
    }

    public function confirmWindowMinutes(Schedule $schedule, ?Booking $booking = null): int
    {
        $booking ??= $schedule->driverRelevantBookings()->first();
        if (! $booking || ! $schedule->driver_movement_deadline_at) {
            return self::MOVEMENT_CONFIRM_LEAD_MINUTES;
        }

        return max(
            1,
            (int) now()->diffInMinutes($schedule->driver_movement_deadline_at, false),
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

        return 'Còn ' . $minutes . ' phút để bấm «Xác nhận»';
    }

    // TODO (Auto Reassign Late Trip): Điều kiện movement (Đặt Ngay 3 phút / Đặt Lịch T-25) — dùng chung với cron auto-reassign.
    public function isLateMovementReassignDue(Schedule $schedule, Booking $booking): bool
    {
        return $this->shouldFlagMovementAlert($schedule, $booking);
    }

    private function shouldFlagMovementAlert(Schedule $schedule, Booking $booking): bool
    {
        if ($schedule->resolvedDriverStage() !== Schedule::DRIVER_STAGE_ASSIGNED) {
            return false;
        }

        if ($booking->isOnDemandPickup()) {
            if (! $schedule->driver_assigned_at) {
                return false;
            }

            return now()->gte(
                $schedule->driver_assigned_at->copy()->addMinutes(3),
            );
        }

        $pickupAt = $booking->operationalPickupAt();
        if (! $pickupAt instanceof Carbon) {
            return false;
        }

        if (! $schedule->driver_depart_reminder_sent_at) {
            return false;
        }

        return now()->gte(
            $pickupAt->copy()->subMinutes(25),
        );
    }

}
