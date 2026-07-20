<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\DriverProfile;
use App\Models\DriverTripRequest;
use App\Models\PushSubscription;
use App\Models\Schedule;
use App\Support\PushAudience;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Ước ETA / cảnh báo admin / nhắc đẩy khi sát giờ đón.
 * Không còn tự gỡ TX vì cửa sổ «Tiếp tục» 1 phút hay chỉ vì quá giờ đón mà còn ASSIGNED.
 */
class DriverLatePickupService
{
    /** @deprecated Cửa sổ «Tiếp tục» đã tắt — không còn dùng để gỡ TX. */
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
        // Đã tắt: không stamp hạn «Tiếp tục» 1 phút sau giờ đón.
        return 0;
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

    // TODO (Fix Flow): Đặt Lịch — push nhắc khởi hành đúng mốc T-30 (một lần / chuyến).
    public function processScheduledDepartReminders(): int
    {
        $sent = 0;

        Schedule::query()
            ->with(['route', 'vehicle', 'bookings'])
            ->whereNotNull('driver_id')
            ->where('driver_stage', Schedule::DRIVER_STAGE_ASSIGNED)
            ->whereIn('status', ['scheduled', 'running'])
            ->whereNull('driver_depart_reminder_sent_at')
            ->each(function (Schedule $schedule) use (&$sent): void {
                $booking = $schedule->driverRelevantBookings()->first();

                if (! $booking
                    || $booking->isOnDemandPickup()
                    || in_array($booking->booking_status, ['cancelled', 'rejected'], true)
                    || $booking->trip_status === 'completed') {
                    return;
                }

                $pickupAt = $booking->operationalPickupAt();
                if (! $pickupAt instanceof Carbon) {
                    return;
                }

                $remindAt = $pickupAt->copy()->subMinutes(30);
                if (now()->lt($remindAt)) {
                    return;
                }

                try {
                    $this->pushNotifications->onDriverDepartReminder(
                        $booking,
                        $this->etaLabel($schedule, $booking),
                    );
                } catch (\Throwable) {
                }

                $schedule->update(['driver_depart_reminder_sent_at' => now()]);
                $sent++;
            });

        return $sent;
    }

    public function expireOverdueContinue(): int
    {
        // Đã tắt: hết cửa sổ «Tiếp tục» 1 phút không còn gỡ TX.
        return 0;
    }

    /** Quá giờ đón mà TX vẫn ASSIGNED — đã tắt auto-gỡ (TX tự bấm «Đến điểm đón»). */
    public function processAssignedPastPickup(): int
    {
        return 0;
    }

    public function travelMinutesForProfile(DriverProfile $profile, Booking $booking): ?int
    {
        $distanceKm = $this->livePickupDistanceKmForProfile($profile, $booking);
        if ($distanceKm === null) {
            return null;
        }

        return (int) max(1, ceil($distanceKm / DriverMovementConfirmService::ASSUMED_SPEED_KMH * 60));
    }

    public function pickupDistanceKmForProfile(DriverProfile $profile, Booking $booking, bool $allowStored = true): ?float
    {
        $snap = app(DriverProximityService::class)->snapshotPickupDistance($booking, $profile);
        if ($snap !== null) {
            return max(0.1, $snap);
        }

        if ($allowStored) {
            $stored = (float) ($booking->driver_pickup_distance_km ?? 0);
            if ($stored > 0) {
                return max(0.1, $stored);
            }
        }

        return null;
    }

    public function livePickupDistanceKmForProfile(DriverProfile $profile, Booking $booking): ?float
    {
        return $this->pickupDistanceKmForProfile($profile, $booking, allowStored: false);
    }

    /** @return array{distance_km: float, distance_label: string, travel_minutes: int, eta_label: string}|null */
    public function assignedPickupProximity(Schedule $schedule, ?Booking $booking = null): ?array
    {
        $booking ??= $schedule->driverRelevantBookings()->first();
        if (! $booking || ! $schedule->driver_id) {
            return null;
        }

        $profile = DriverProfile::query()->where('user_id', (int) $schedule->driver_id)->first();
        if (! $profile) {
            return null;
        }

        $distanceKm = $this->pickupDistanceKmForProfile($profile, $booking);
        if ($distanceKm === null) {
            return null;
        }

        $liveKm = $this->livePickupDistanceKmForProfile($profile, $booking);
        $travelMinutes = $liveKm !== null
            ? (int) max(1, ceil($liveKm / DriverMovementConfirmService::ASSUMED_SPEED_KMH * 60))
            : null;

        return [
            'distance_km'       => $distanceKm,
            'distance_label'    => DriverProximityService::formatDistanceLabel($distanceKm),
            'travel_minutes'    => $travelMinutes,
            'eta_label'         => $travelMinutes !== null ? $this->formatMinutesLabel($travelMinutes) : null,
            'travel_time_label' => $travelMinutes !== null ? $this->formatDriverTravelTimeLabel($travelMinutes) : null,
        ];
    }

    /** Dòng «Cách khách …, thời gian di chuyển khoảng …» trên dashboard tài xế. */
    public function driverPickupProximityLine(?Schedule $schedule): ?string
    {
        if (! $schedule || $schedule->resolvedDriverStage() !== Schedule::DRIVER_STAGE_ASSIGNED) {
            return null;
        }

        // TODO (Hide Proximity When Trip Hidden): Chỉ hiện khi chuyến còn trên dashboard tài xế.
        if (! $schedule->isVisibleOnDriverDashboard()) {
            return null;
        }

        $proximity = $this->assignedPickupProximity($schedule);
        if (! $proximity) {
            return null;
        }

        $line = 'Cách khách ' . $proximity['distance_label'];
        if (! empty($proximity['travel_time_label'])) {
            $line .= ', thời gian di chuyển ' . $proximity['travel_time_label'];
        }

        return $line . '.';
    }

    public function stampPromptIfDue(Schedule $schedule): bool
    {
        // Đã tắt: không tạo hạn «Tiếp tục» 1 phút / không ép TX phải đến ngay sau nhận.
        return false;
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

        $proximity = $this->assignedPickupProximity($schedule, $booking);
        $etaLabel = $proximity['eta_label'] ?? null;
        $distanceLabel = $proximity['distance_label'] ?? null;

        $message = 'Vui lòng di chuyển đến điểm đón';
        if ($distanceLabel) {
            $message .= ' (cách khách ' . $distanceLabel . ')';
        }
        if ($etaLabel) {
            $message .= ' — dự kiến ' . $etaLabel;
        }
        $message .= '.';

        return [
            'message'        => $message,
            'hint'           => 'Bấm «Đến điểm đón» khi bạn đến điểm đón khách.',
            'distance_label' => $distanceLabel,
            'eta_label'      => $etaLabel,
        ];
    }

    public function releaseDriverAfterTimeout(Schedule $schedule): bool
    {
        // Đã tắt: không gỡ TX vì hết hạn «Tiếp tục».
        return false;
    }

    /** Đã tắt: sau khi TX nhận cuốc không còn tự hủy / gỡ vì hết giờ chờ xác nhận. */
    public function processLateAlertAutoReassign(): int
    {
        return 0;
    }

    // TODO (Auto Reassign Late Trip):
    public function attemptLateTripAutoReassign(Booking $booking, string $reason): bool
    {
        $booking->loadMissing('schedule');
        $schedule = $booking->schedule;
        $formerDriverId = (int) ($schedule?->driver_id ?? 0);

        if ($formerDriverId <= 0 || $schedule->resolvedDriverStage() !== Schedule::DRIVER_STAGE_ASSIGNED) {
            return false;
        }

        $profile = DriverProfile::query()->where('user_id', $formerDriverId)->first();
        $reassigned = app(BookingWorkflowService::class)->releaseDriverAndTryAutoReassign(
            $booking->fresh(['schedule.route', 'schedule.vehicle']),
            $reason,
        );

        $booking = $booking->fresh(['schedule']);
        $released = (int) ($booking->schedule?->driver_id ?? 0) !== $formerDriverId;

        // TODO (Auto Reassign Late Trip): Chỉ phạt khi TX đã thực sự bị gỡ.
        if ($profile && $released) {
            $this->penalties->recordLateContinueTimeout($profile);
        }

        return $reassigned || (bool) $booking->needs_operator_help_at;
    }

    private function shouldDepartForPickup(Booking $booking): bool
    {
        $pickupAt = $booking->operationalPickupAt();

        if (! $pickupAt instanceof Carbon) {
            return false;
        }

        $booking->loadMissing('schedule');
        $schedule = $booking->schedule;

        if (! $schedule) {
            return false;
        }

        $travelMinutes = $this->travelMinutesToPickup($schedule, $booking);
        if ($travelMinutes === null) {
            return false;
        }

        $departBy = $pickupAt->copy()->subMinutes($travelMinutes);

        return now()->gte($departBy) && $pickupAt->isFuture();
    }

    public function pickupEtaLabel(Schedule $schedule, Booking $booking): ?string
    {
        if (! $schedule->driver_id) {
            return null;
        }

        return $this->etaLabel($schedule, $booking);
    }

    /** Số phút thô còn lại tới điểm đón — dùng cho đồng hồ đếm ngược phía khách. */
    public function pickupEtaMinutes(Schedule $schedule, Booking $booking): ?int
    {
        if (! $schedule->driver_id) {
            return null;
        }

        return $this->travelMinutesToPickup($schedule, $booking);
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

    /** Thời lượng đến điểm đón — «15 phút» / «1h30 phút» (dùng trên thanh sheet khách). */
    public function formatArrivalDurationLabel(int $travelMinutes): string
    {
        $travelMinutes = max(1, $travelMinutes);
        if ($travelMinutes < 60) {
            return $travelMinutes . ' phút';
        }

        $hours = (int) floor($travelMinutes / 60);
        $mins = $travelMinutes % 60;

        if ($mins <= 0) {
            return $hours . ' giờ';
        }

        return $hours . 'h' . $mins . ' phút';
    }

    /** Nhãn thời gian di chuyển trên app tài xế — «khoảng 2 phút». */
    public function formatDriverTravelTimeLabel(int $travelMinutes): string
    {
        if ($travelMinutes < 60) {
            return 'khoảng ' . $travelMinutes . ' phút';
        }

        $hours = (int) floor($travelMinutes / 60);
        $mins = $travelMinutes % 60;

        return $mins > 0
            ? ('khoảng ' . $hours . ' giờ ' . $mins . ' phút')
            : ('khoảng ' . $hours . ' giờ');
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

        $pickupAt = $booking->operationalPickupAt();
        if (! $pickupAt instanceof Carbon) {
            return null;
        }

        $stage = $schedule->resolvedDriverStage();
        if (in_array($stage, [Schedule::DRIVER_STAGE_PICKED_UP, Schedule::DRIVER_STAGE_RUNNING, Schedule::DRIVER_STAGE_COMPLETED], true)) {
            return null;
        }

        $pushMeta = $this->pushNotifications->pickupNotifyMetaForBooking($booking);
        $pushSuffix = $this->adminPushDetailSuffix($booking, $pushMeta);

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

        if ($stage === Schedule::DRIVER_STAGE_ASSIGNED
            && $schedule->driver_movement_deadline_at
            && $schedule->driver_movement_deadline_at->lte(now())) {
            return [
                'level'      => $minutesUntilPickup <= 5 ? 'danger' : 'warning',
                'label'      => 'TX chưa xác nhận',
                'detail'     => 'Còn ' . $minutesUntilPickup . ' phút · TX chưa bấm «Xác nhận» đi đón' . $pushSuffix,
                'push_label' => $pushMeta['sent_label'],
            ];
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
        if ($etaMinutes === null || $etaMinutes <= $minutesUntilPickup) {
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
    private function adminPushDetailSuffix(Booking $booking, array $pushMeta): string
    {
        if ($pushMeta['sent_label']) {
            return ' · ' . $pushMeta['sent_label'];
        }

        if ($pushMeta['has_push']) {
            return '';
        }

        if ($booking->assignedDriverSharesLiveLocation()) {
            return ' · TX đã chia sẻ vị trí';
        }

        return ' · TX chưa bật app';
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

        $pickupAt = $booking->operationalPickupAt();
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
        if ($travelMinutes === null) {
            return null;
        }

        return $this->formatMinutesLabel($travelMinutes);
    }

    private function travelMinutesToPickup(Schedule $schedule, Booking $booking): ?int
    {
        $driverUserId = (int) ($schedule->driver_id ?: $booking->resolveAssignedDriverId($schedule));
        $profile = $driverUserId > 0
            ? DriverProfile::query()->where('user_id', $driverUserId)->first()
            : null;

        if (! $profile) {
            return null;
        }

        return $this->travelMinutesForProfile($profile, $booking);
    }
}
