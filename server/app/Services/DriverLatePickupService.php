<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\DriverProfile;
use App\Models\DriverTripRequest;
use App\Models\PushSubscription;
use App\Models\Schedule;
use App\Support\PushAudience;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Tài xế chưa đến điểm đón đúng giờ — nhắc bấm Tiếp tục; hết 1 phút thì gỡ và tìm tài xế khác.
 */
class DriverLatePickupService
{
    public const CONTINUE_WINDOW_MINUTES = 1;

    /** Bắt đầu cảnh báo admin trước giờ đón (phút). */
    public const ADMIN_WARN_MINUTES_BEFORE = 60;

    /** Bước nhắc admin khi sát giờ đón (phút). */
    public const ADMIN_WARN_STEP_MINUTES = 15;

    public function __construct(
        private readonly DriverTripRequestService $tripRequests,
        private readonly DriverBehaviorPenaltyService $penalties,
        private readonly PushNotificationService $pushNotifications,
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

    /** Gửi TB đẩy nhắc tài xế khi gần giờ đón / cần xuất phát / hết hạn xác nhận di chuyển. */
    public function processPickupPushReminders(): int
    {
        $sent = 0;

        Schedule::query()
            ->with(['route', 'vehicle', 'bookings'])
            ->whereNotNull('driver_id')
            ->where('driver_stage', Schedule::DRIVER_STAGE_ASSIGNED)
            ->whereIn('status', ['scheduled', 'running'])
            ->each(function (Schedule $schedule) use (&$sent): void {
                $booking = $schedule->driverRelevantBookings()->first();

                if (! $booking
                    || in_array($booking->booking_status, ['cancelled', 'rejected'], true)
                    || $booking->trip_status === 'completed') {
                    return;
                }

                $sent += $this->pushRemindersForBooking($schedule, $booking);
            });

        return $sent;
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

        try {
            $this->pushNotifications->onDriverLatePickupDue($booking);
        } catch (\Throwable) {
        }

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

    /** Ước tính theo giờ đón đã đặt khi TX chưa chia sẻ GPS. */
    public function minutesUntilScheduledPickupLabel(Booking $booking): ?string
    {
        $pickupAt = $booking->guestPickupAt();
        if (! $pickupAt instanceof Carbon || ! $pickupAt->isFuture()) {
            return null;
        }

        $minutes = (int) now()->diffInMinutes($pickupAt, false);
        if ($minutes <= 0) {
            return null;
        }

        return $this->formatMinutesLabel($minutes);
    }

    public function formatMinutesLabel(int $travelMinutes): string
    {
        if ($travelMinutes < 60) {
            return $travelMinutes . ' phút nữa';
        }

        $hours = (int) floor($travelMinutes / 60);
        $mins = $travelMinutes % 60;

        return $mins > 0 ? ($hours . ' giờ ' . $mins . ' phút nữa') : ($hours . ' giờ nữa');
    }

    /**
     * Cảnh báo admin — TX có thể không kịp đón hoặc đã quá giờ đón.
     *
     * @return array{level: string, label: string, detail: string, push_label?: string|null}|null
     */
    public function adminAlertForBooking(Booking $booking): ?array
    {
        $booking->loadMissing('schedule');
        $schedule = $booking->schedule;

        if (! $schedule
            || ! $booking->hasDriverAccepted()
            || in_array($booking->booking_status, ['cancelled', 'rejected'], true)
            || $booking->trip_status === 'completed') {
            return null;
        }

        $pickupAt = $booking->tripStartAt();
        if (! $pickupAt instanceof Carbon) {
            return null;
        }

        $stage = $schedule->resolvedDriverStage();
        if (in_array($stage, [Schedule::DRIVER_STAGE_PICKED_UP, Schedule::DRIVER_STAGE_RUNNING, Schedule::DRIVER_STAGE_COMPLETED], true)) {
            return null;
        }

        $pushMeta = $this->pushNotifications->pickupNotifyMetaForBooking($booking);
        $pushSuffix = $this->adminPushDetailSuffix($pushMeta);

        $minutesUntilPickup = (int) now()->diffInMinutes($pickupAt, false);

        if ($minutesUntilPickup < 0 && $stage === Schedule::DRIVER_STAGE_ASSIGNED) {
            $lateMinutes = abs($minutesUntilPickup);

            return [
                'level'       => 'danger',
                'label'       => 'Quá giờ đón',
                'detail'      => 'TX chưa đến điểm đón · trễ ' . $lateMinutes . ' phút' . $pushSuffix,
                'push_label'  => $pushMeta['sent_label'],
            ];
        }

        if ($minutesUntilPickup <= 0 || $minutesUntilPickup > self::ADMIN_WARN_MINUTES_BEFORE) {
            return null;
        }

        $driverUserId = (int) ($schedule->driver_id ?: $booking->resolveAssignedDriverId($schedule));
        $profile = $driverUserId > 0
            ? DriverProfile::query()->where('user_id', $driverUserId)->first()
            : null;

        if (! $profile?->hasFreshLocation()) {
            if ($minutesUntilPickup <= self::ADMIN_WARN_STEP_MINUTES) {
                return [
                    'level'      => $minutesUntilPickup <= 5 ? 'danger' : 'warning',
                    'label'      => 'Sát giờ đón',
                    'detail'     => 'Còn ' . $minutesUntilPickup . ' phút · TX chưa chia sẻ vị trí' . $pushSuffix,
                    'push_label' => $pushMeta['sent_label'],
                ];
            }

            return null;
        }

        $etaMinutes = $this->travelMinutesToPickup($schedule, $booking);
        if ($etaMinutes <= $minutesUntilPickup) {
            return null;
        }

        $level = match (true) {
            $minutesUntilPickup <= self::ADMIN_WARN_STEP_MINUTES     => 'danger',
            $minutesUntilPickup <= self::ADMIN_WARN_STEP_MINUTES * 2 => 'warning',
            default                                                  => 'pending',
        };

        return [
            'level'      => $level,
            'label'      => 'Có thể trễ đón',
            'detail'     => 'Còn ' . $minutesUntilPickup . ' phút tới giờ đón · TX ~' . $etaMinutes . ' phút nữa mới tới' . $pushSuffix,
            'push_label' => $pushMeta['sent_label'],
        ];
    }

    /** @param array{has_push: bool, sent_at: ?Carbon, sent_label: ?string} $pushMeta */
    private function adminPushDetailSuffix(array $pushMeta): string
    {
        if (! $pushMeta['has_push']) {
            return ' · TX chưa bật app';
        }

        if ($pushMeta['sent_label']) {
            return ' · ' . $pushMeta['sent_label'];
        }

        return '';
    }

    private function pushRemindersForBooking(Schedule $schedule, Booking $booking): int
    {
        $driverUserId = (int) ($schedule->driver_id ?: $booking->resolveAssignedDriverId($schedule));
        if ($driverUserId <= 0) {
            return 0;
        }

        $hasPush = PushSubscription::query()
            ->where('audience', PushAudience::DRIVER)
            ->where('user_id', $driverUserId)
            ->exists();

        if (! $hasPush) {
            return 0;
        }

        $sent = 0;

        if ($schedule->driver_movement_deadline_at?->isFuture()) {
            $minutesToDeadline = (int) now()->diffInMinutes($schedule->driver_movement_deadline_at, false);
            if ($minutesToDeadline >= 0 && $minutesToDeadline <= 3) {
                try {
                    if ($this->pushNotifications->onDriverMovementDeadline($booking, $minutesToDeadline)) {
                        $sent++;
                    }
                } catch (\Throwable) {
                }
            }
        }

        if ($this->shouldDepartForPickup($booking)) {
            try {
                if ($this->pushNotifications->onDriverDepartReminder(
                    $booking,
                    $this->etaLabel($schedule, $booking),
                )) {
                    $sent++;
                }
            } catch (\Throwable) {
            }
        }

        $pickupAt = $booking->tripStartAt();
        if (! $pickupAt instanceof Carbon) {
            return $sent;
        }

        $minutesUntilPickup = (int) now()->diffInMinutes($pickupAt, false);
        if ($minutesUntilPickup <= 0 || $minutesUntilPickup > self::ADMIN_WARN_MINUTES_BEFORE) {
            return $sent;
        }

        $profile = DriverProfile::query()->where('user_id', $driverUserId)->first();
        if ($profile?->hasFreshLocation()) {
            return $sent;
        }

        foreach ([15, 10, 5] as $threshold) {
            if ($minutesUntilPickup <= $threshold) {
                try {
                    if ($this->pushNotifications->onDriverPickupUrgent($booking, $minutesUntilPickup)) {
                        $sent++;
                    }
                } catch (\Throwable) {
                }
                break;
            }
        }

        return $sent;
    }

    private function etaLabel(Schedule $schedule, ?Booking $booking): ?string
    {
        if (! $booking) {
            return null;
        }

        $travelMinutes = $this->travelMinutesToPickup($schedule, $booking);

        return $this->formatMinutesLabel($travelMinutes);
    }

    private function travelMinutesToPickup(Schedule $schedule, Booking $booking): int
    {
        $driverUserId = (int) ($schedule->driver_id ?: $booking->resolveAssignedDriverId($schedule));
        $profile = $driverUserId > 0
            ? DriverProfile::query()->where('user_id', $driverUserId)->first()
            : null;

        if (! $profile || ! $profile->hasFreshLocation()) {
            return 1;
        }

        $snap = app(DriverProximityService::class)->snapshotPickupDistance($booking, $profile);
        if ($snap === null) {
            return 1;
        }

        $distanceKm = max(0.5, $snap);

        return (int) max(1, ceil($distanceKm / DriverMovementConfirmService::ASSUMED_SPEED_KMH * 60));
    }
}
