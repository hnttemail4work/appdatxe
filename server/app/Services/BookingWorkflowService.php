<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingAudit;
use App\Models\CancellationReason;
use App\Models\DriverProfile;
use App\Models\Schedule;
use App\Models\TripLedger;
use App\Models\ScheduleTemplate;
use App\Models\DriverTripRequest;
use App\Services\DriverAvailabilityService;
use App\Services\DriverCuocOfferHideService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class BookingWorkflowService
{
    public function __construct(
        private readonly ScheduleLifecycleService $scheduleLifecycle,
        private readonly TripLedgerService $tripLedger,
        private readonly DriverTripRequestService $driverRequests,
        private readonly BookingPhoneGuardService $phoneGuard,
        private readonly CancellationReasonService $cancellationReasons,
        private readonly BookingBrowserGuardService $browserGuard,
        private readonly DriverAvailabilityService $driverAvailability,
        private readonly BookingCreationService $creation,
        private readonly DriverTripProgressionService $progression,
    ) {
    }

    public function createBookingFromTemplate(
        ScheduleTemplate $template,
        string $contactPhone,
        string $passengerName,
        string $serviceDate,
        ?string $pickupTime,
        ?string $pickupAddress,
        ?string $pickupDetail,
        ?string $dropoffAddress,
        ?string $dropoffDetail,
        ?string $notes,
        ?int $appliedReferralCodeId = null,
        string $passengerGender = 'male',
        ?int $passengerAge = null,
        ?float $pickupLat = null,
        ?float $pickupLng = null,
        ?float $dropoffLat = null,
        ?float $dropoffLng = null,
        bool $autoMatchDriver = false,
    ): Booking {
        return $this->creation->createBookingFromTemplate(
            $template,
            $contactPhone,
            $passengerName,
            $serviceDate,
            $pickupTime,
            $pickupAddress,
            $pickupDetail,
            $dropoffAddress,
            $dropoffDetail,
            $notes,
            $appliedReferralCodeId,
            $passengerGender,
            $passengerAge,
            $pickupLat,
            $pickupLng,
            $dropoffLat,
            $dropoffLng,
            $autoMatchDriver,
        );
    }

    public function createBooking(
        Schedule $schedule,
        string $contactPhone,
        string $passengerName,
        ?string $pickupAddress = null,
        ?string $pickupDetail = null,
        ?string $dropoffAddress = null,
        ?string $dropoffDetail = null,
        ?string $notes = null,
        ?string $pickupTime = null,
        ?int $appliedReferralCodeId = null,
        string $passengerGender = 'male',
        ?int $passengerAge = null,
        ?float $pickupLat = null,
        ?float $pickupLng = null,
        ?float $dropoffLat = null,
        ?float $dropoffLng = null,
    ): Booking {
        return $this->creation->createBooking(
            $schedule,
            $contactPhone,
            $passengerName,
            $pickupAddress,
            $pickupDetail,
            $dropoffAddress,
            $dropoffDetail,
            $notes,
            $pickupTime,
            $appliedReferralCodeId,
            $passengerGender,
            $passengerAge,
            $pickupLat,
            $pickupLng,
            $dropoffLat,
            $dropoffLng,
        );
    }

    public function cancelByPhone(Booking $booking, string $contactPhone, ?int $cancellationReasonId = null, ?string $cancellationReasonNote = null): void
    {
        $this->assertContactPhone($booking, $contactPhone);

        if (in_array($booking->booking_status, ['cancelled', 'rejected'], true)) {
            throw new InvalidArgumentException('Vé này đã hủy rồi.');
        }

        if ($booking->trip_status === 'completed') {
            throw new InvalidArgumentException('Chuyến đã hoàn tất, không thể hủy.');
        }

        $reason = null;
        $reasonLabel = null;
        if ($booking->hasDriverAccepted()) {
            if (! $cancellationReasonId) {
                throw new InvalidArgumentException('Vui lòng chọn lý do hủy chuyến.');
            }
            $reason = $this->cancellationReasons->resolveForCancel($cancellationReasonId, 'driver');
            $reasonLabel = $this->cancellationReasons->composeLabel($reason, $cancellationReasonNote);
        }

        DB::transaction(function () use ($booking, $reason, $reasonLabel): void {
            $before = $booking->toArray();
            $schedule = $booking->schedule()->with(['vehicle', 'route'])->first();

            $cancelCount = $this->phoneGuard->recordCustomerCancel((string) $booking->contact_phone);
            $visibility = [];
            if (Booking::supportsOperatorDismiss()) {
                $visibility = $cancelCount > BookingPhoneGuardService::MAX_CANCEL_CYCLES
                    ? (Schema::hasColumn('bookings', 'repeat_cancel_flag') ? ['repeat_cancel_flag' => true] : [])
                    : ['operator_dismissed_at' => now()];
            }

            $assignment = [];
            $assignedDriverId = $booking->resolveAssignedDriverId($schedule);
            if ($assignedDriverId && Schema::hasColumn('bookings', 'assigned_driver_id')) {
                $assignment = ['assigned_driver_id' => $assignedDriverId];
            }

            $booking->update(array_merge([
                'booking_status'              => 'cancelled',
                'trip_status'                 => 'cancelled',
                'payment_status'              => $booking->payment_status === 'paid' ? 'refunded' : 'unpaid',
                'cancelled_at'                => now(),
                'cancelled_by'                => 'customer',
                'cancellation_reason_id'      => $reason?->id,
                'cancellation_reason_label'   => $reasonLabel,
            ], $visibility, $assignment));

            $booking->paymentTransactions()->where('status', 'pending')->update(['status' => 'failed']);
            $this->syncScheduleAvailability($schedule);
            $this->audit($booking, null, 'booking_cancelled', $before, $booking->fresh()->toArray());

            $stillActive = Booking::query()
                ->where('schedule_id', $schedule->id)
                ->whereNotIn('booking_status', ['cancelled', 'rejected'])
                ->exists();

            if (! $stillActive) {
                $this->tripLedger->recordForSchedule($schedule, TripLedger::OUTCOME_CANCELLED_CUSTOMER, [
                    'actor_label' => $booking->passenger_name,
                    'actor_code'  => $booking->contact_phone,
                ]);
            }
        });

        $this->browserGuard->clearActiveBookingForBooking($booking->fresh());
    }

    /** Ghi lý do TX từ chối / hủy cuốc — đơn vẫn tìm TX khác, không hủy vé. */
    public function stampDriverReleaseReason(Booking $booking, CancellationReason $reason, ?string $note = null): void
    {
        if (in_array($booking->booking_status, ['cancelled', 'rejected'], true)
            || $booking->trip_status === 'completed') {
            return;
        }

        $booking->update([
            'cancellation_reason_id'    => $reason->id,
            'cancellation_reason_label' => $this->cancellationReasons->composeLabel($reason, $note),
        ]);
    }

    /** Khách hủy chuyến qua web — tab Đã hủy admin, gỡ khỏi tài xế. */
    public function cancelByGuest(
        Booking $booking,
        ?string $contactPhone = null,
        ?int $cancellationReasonId = null,
        ?string $cancellationReasonNote = null,
    ): void {
        if (in_array($booking->booking_status, ['cancelled', 'rejected'], true)) {
            throw new InvalidArgumentException('Chuyến đã được hủy.');
        }

        if ($booking->trip_status === 'completed') {
            throw new InvalidArgumentException('Chuyến đã hoàn tất, không thể hủy.');
        }

        if ($booking->passengerPickedUp()) {
            throw new InvalidArgumentException('Tài xế đã đón khách — không thể hủy chuyến.');
        }

        $phone = trim((string) ($contactPhone ?: $booking->contact_phone));
        $locationKey = $phone !== ''
            ? $this->phoneGuard->locationFingerprintFromBooking($booking)
            : null;

        $reason = null;
        $reasonLabel = 'Khách hủy chuyến';

        // TX đã nhận: bắt buộc lý do — dùng chung danh sách với tài xế.
        // Chưa có TX: hủy thoải mái, không cần lý do.
        if ($booking->hasDriverAccepted()) {
            if (! $cancellationReasonId) {
                throw new InvalidArgumentException('Vui lòng chọn lý do hủy chuyến.');
            }
            $reason = $this->cancellationReasons->resolveForCancel($cancellationReasonId, 'driver');
            $reasonLabel = $this->cancellationReasons->composeLabel($reason, $cancellationReasonNote);
        }

        $booking->loadMissing(['schedule.vehicle', 'schedule.route']);
        $schedule = $booking->schedule;
        $formerDriverId = (int) (
            $booking->assigned_driver_id
            ?: $schedule?->driver_id
            ?: 0
        );

        DB::transaction(function () use ($booking, $schedule, $reason, $reasonLabel, $phone, $locationKey, $formerDriverId): void {
            $before = $booking->toArray();

            if ($phone !== '') {
                $this->phoneGuard->recordCustomerCancel($phone, $locationKey);
            }

            $updates = [
                'booking_status'            => 'cancelled',
                'trip_status'               => 'cancelled',
                'payment_status'            => $booking->payment_status === 'paid' ? 'refunded' : 'unpaid',
                'cancelled_at'              => now(),
                'cancelled_by'              => 'customer',
                'cancellation_reason_id'    => $reason?->id,
                'cancellation_reason_label' => $reasonLabel,
                'hold_expires_at'           => null,
            ];

            if (Schema::hasColumn('bookings', 'assigned_driver_id')) {
                $updates['assigned_driver_id'] = null;
            }

            if (Booking::supportsOperatorDismiss()) {
                $updates['operator_dismissed_at'] = null;
            }

            // Giữ id TX để observer / push hủy gửi đúng người (trước khi clear schedule.driver_id).
            if ($formerDriverId > 0) {
                $booking->cancelNotifyDriverId = $formerDriverId;
            }

            $booking->update($updates);

            if ($schedule) {
                $stillActive = Booking::query()
                    ->where('schedule_id', $schedule->id)
                    ->whereNotIn('booking_status', ['cancelled', 'rejected'])
                    ->where('trip_status', '!=', 'completed')
                    ->exists();

                if (! $stillActive) {
                    $schedule->update([
                        'driver_id'                               => null,
                        'driver_name'                             => 'Chờ phân bổ',
                        'driver_stage'                            => null,
                        'driver_assigned_at'                      => null,
                        'driver_movement_deadline_at'             => null,
                'driver_movement_confirmed_at'            => null,
                        'driver_late_pickup_prompt_at'            => null,
                        'driver_late_pickup_continue_deadline_at' => null,
                        'status'                                  => $schedule->status === 'running'
                            ? 'scheduled'
                            : $schedule->status,
                    ]);

                    $this->tripLedger->recordForSchedule($schedule, TripLedger::OUTCOME_CANCELLED_CUSTOMER, [
                        'actor_label' => $booking->passenger_name,
                        'actor_code'  => $booking->contact_phone,
                    ]);
                }
            }

            $booking->paymentTransactions()->where('status', 'pending')->update(['status' => 'failed']);
            $this->syncScheduleAvailability($schedule);
            $this->audit($booking, null, 'booking_cancelled_guest', $before, $booking->fresh()->toArray());
        });

        if ($formerDriverId > 0) {
            $this->driverAvailability->syncAfterTripCompleted($formerDriverId);
        }

        $freshBooking = $booking->fresh();
        $this->driverRequests->revokePendingOffersForGuestBooking($freshBooking);

        try {
            app(PushNotificationService::class)->onTripCancelled(
                $freshBooking,
                null,
                $formerDriverId > 0 ? $formerDriverId : null,
            );
        } catch (\Throwable) {
        }

        $this->browserGuard->clearActiveBookingForBooking($freshBooking);
    }

    /** Quá giờ đón — hủy hệ thống để khách đặt lại, admin thấy tab Đã hủy. */
    public function cancelPickupTimeout(Booking $booking): void
    {
        if (in_array($booking->booking_status, ['cancelled', 'rejected'], true)) {
            return;
        }

        if ($booking->hasDriverAccepted()) {
            return;
        }

        if ($booking->needs_operator_help_at) {
            return;
        }

        if (! $booking->isPastPickupTime()) {
            return;
        }

        $hadDriver = $booking->hadDriverEngagedForPickup();

        DB::transaction(function () use ($booking, $hadDriver): void {
            $before = $booking->toArray();
            $schedule = $booking->schedule()->with(['vehicle', 'route'])->first();

            $updates = [
                'booking_status'            => 'cancelled',
                'trip_status'               => 'cancelled',
                'payment_status'            => $booking->payment_status === 'paid' ? 'refunded' : 'unpaid',
                'cancelled_at'              => now(),
                'cancelled_by'              => 'system',
                'cancellation_reason_label' => $hadDriver
                    ? 'Quá giờ đón — tài xế không đến đón'
                    : 'Quá giờ đón — không có tài xế',
            ];

            if (Schema::hasColumn('bookings', 'assigned_driver_id')) {
                $updates['assigned_driver_id'] = null;
            }

            $booking->update($updates);

            if ($schedule) {
                DriverTripRequest::query()
                    ->where('schedule_id', $schedule->id)
                    ->where('contact_phone', (string) $booking->contact_phone)
                    ->whereIn('status', ['pending', 'accepted'])
                    ->update([
                        'status'       => 'cancelled',
                        'responded_at' => now(),
                    ]);

                $this->syncScheduleAvailability($schedule);
            }

            $this->audit($booking, null, 'booking_cancelled', $before, $booking->fresh()->toArray());
        });

        $this->browserGuard->clearActiveBookingForBooking($booking->fresh());
    }

    public function cancelPickupTimeoutIfDue(Booking $booking): bool
    {
        $booking = $booking->fresh(['schedule']);

        if ($booking->hasDriverAccepted() || ! $booking->isPastPickupTime()) {
            return false;
        }

        if ($booking->needs_operator_help_at) {
            return false;
        }

        if (in_array($booking->booking_status, ['cancelled', 'rejected'], true)
            || in_array($booking->trip_status, ['completed', 'cancelled'], true)) {
            return false;
        }

        $this->cancelPickupTimeout($booking);

        return true;
    }

    /** @return int Số đơn đã hủy vì quá giờ đón. */
    public function expirePastPickupWithoutDriver(): int
    {
        $cancelled = 0;

        Booking::query()
            ->with('schedule')
            ->whereNotIn('booking_status', ['cancelled', 'rejected'])
            ->whereNotIn('trip_status', ['completed', 'cancelled'])
            ->whereNull('expired_at')
            ->whereNull('needs_operator_help_at')
            ->when(
                Schema::hasColumn('bookings', 'assigned_driver_id'),
                fn ($q) => $q->whereNull('assigned_driver_id'),
            )
            ->whereHas('schedule', fn ($q) => $q->whereNull('driver_id'))
            ->orderBy('id')
            ->chunkById(50, function ($bookings) use (&$cancelled): void {
                foreach ($bookings as $booking) {
                    $booking = $booking->fresh(['schedule']);
                    if (! $booking->isOnDemandPickup()) {
                        continue;
                    }

                    if ($this->cancelPickupTimeoutIfDue($booking)) {
                        $cancelled++;
                    }
                }
            });

        return $cancelled;
    }

    /** Đặt Lịch — hết T−1 tiếng không có TX thì system cancel. */
    public function expireScheduledSearchWithoutDriver(): int
    {
        $cancelled = 0;

        Booking::query()
            ->with('schedule')
            ->whereNotIn('booking_status', ['cancelled', 'rejected'])
            ->whereNotIn('trip_status', ['completed', 'cancelled'])
            ->whereNull('expired_at')
            ->when(
                Schema::hasColumn('bookings', 'assigned_driver_id'),
                fn ($q) => $q->whereNull('assigned_driver_id'),
            )
            ->whereHas('schedule', fn ($q) => $q->whereNull('driver_id'))
            ->orderBy('id')
            ->chunkById(50, function ($bookings) use (&$cancelled): void {
                foreach ($bookings as $booking) {
                    $booking = $booking->fresh(['schedule']);
                    if ($booking->isOnDemandPickup() || $booking->hasDriverAccepted()) {
                        continue;
                    }

                    if (! app(DriverTripRequestService::class)->hasReachedScheduledSearchStop($booking)) {
                        continue;
                    }

                    if ($this->cancelScheduledSearchTimeout($booking)) {
                        $cancelled++;
                    }
                }
            });

        return $cancelled;
    }

    public function cancelScheduledSearchTimeout(Booking $booking): bool
    {
        $booking = $booking->fresh(['schedule']);

        if ($booking->hasDriverAccepted() || $booking->schedule?->driver_id) {
            return false;
        }

        if (in_array($booking->booking_status, ['cancelled', 'rejected'], true)
            || in_array($booking->trip_status, ['completed', 'cancelled'], true)) {
            return false;
        }

        $this->cancelScheduledSearchTimeoutBooking($booking);

        $fresh = $booking->fresh(['schedule']);
        if (! $fresh
            || $fresh->booking_status !== 'cancelled'
            || $fresh->hasDriverAccepted()
            || $fresh->hadDriverEngagedForPickup()) {
            return false;
        }

        $this->notifyCustomerScheduledSearchTimeout($fresh);

        return true;
    }

    private function cancelScheduledSearchTimeoutBooking(Booking $booking): void
    {
        if (in_array($booking->booking_status, ['cancelled', 'rejected'], true)) {
            return;
        }

        if ($booking->schedule?->driver_id) {
            return;
        }

        DB::transaction(function () use ($booking): void {
            $before = $booking->toArray();
            $schedule = $booking->schedule()->with(['vehicle', 'route'])->first();

            $updates = [
                'booking_status'            => 'cancelled',
                'trip_status'               => 'cancelled',
                'payment_status'            => $booking->payment_status === 'paid' ? 'refunded' : 'unpaid',
                'cancelled_at'              => now(),
                'cancelled_by'              => 'system',
                'cancellation_reason_label' => 'Không tìm được tài xế trước giờ đón 1 tiếng',
                'needs_operator_help_at'    => null,
                'operator_help_reason'      => null,
            ];

            if (Schema::hasColumn('bookings', 'assigned_driver_id')) {
                $updates['assigned_driver_id'] = null;
            }

            $booking->update($updates);

            if ($schedule) {
                DriverTripRequest::query()
                    ->where('schedule_id', $schedule->id)
                    ->where('status', 'pending')
                    ->update([
                        'status'       => 'cancelled',
                        'responded_at' => now(),
                    ]);

                $this->syncScheduleAvailability($schedule);
            }

            $this->audit($booking, null, 'booking_cancelled', $before, $booking->fresh()->toArray());
        });

        $this->browserGuard->clearActiveBookingForBooking($booking->fresh());
    }

    /** Hết 10 phút tìm tài xế (Đặt ngay) — hủy để khách đặt lại. */
    public function cancelSearchTimeout(Booking $booking): void
    {
        if (in_array($booking->booking_status, ['cancelled', 'rejected'], true)) {
            return;
        }

        if ($booking->schedule?->driver_id || $booking->hasDriverAccepted()) {
            return;
        }

        DB::transaction(function () use ($booking): void {
            $before = $booking->toArray();
            $schedule = $booking->schedule()->with(['vehicle', 'route'])->first();

            $updates = [
                'booking_status'            => 'cancelled',
                'trip_status'               => 'cancelled',
                'payment_status'            => $booking->payment_status === 'paid' ? 'refunded' : 'unpaid',
                'cancelled_at'              => now(),
                'cancelled_by'              => 'system',
                'cancellation_reason_label' => 'Không tìm được tài xế trong 10 phút',
                'needs_operator_help_at'    => null,
                'operator_help_reason'      => null,
            ];

            if (Schema::hasColumn('bookings', 'assigned_driver_id')) {
                $updates['assigned_driver_id'] = null;
            }

            $booking->update($updates);

            if ($schedule) {
                DriverTripRequest::query()
                    ->where('schedule_id', $schedule->id)
                    ->whereIn('status', ['pending', 'accepted'])
                    ->update([
                        'status'       => 'cancelled',
                        'responded_at' => now(),
                    ]);

                $this->syncScheduleAvailability($schedule);
            }

            $this->audit($booking, null, 'booking_cancelled', $before, $booking->fresh()->toArray());
        });

        $fresh = $booking->fresh();
        $this->browserGuard->clearActiveBookingForBooking($fresh);

        try {
            app(PushNotificationService::class)->onTripCancelled($fresh);
        } catch (\Throwable) {
        }
    }

    public function notifyCustomerDriverSearchTimeout(Booking $booking): void
    {
        $this->notifyCustomerSearchTimeoutInbox(
            $booking,
            'Chuyến '.$booking->booking_reference.' đã tự hủy vì không có tài xế nhận trong 10 phút. Bạn có thể đặt lại.',
        );
    }

    /** Đặt sau: hết T−1 tiếng chưa có TX nhận — inbox nhắc khách đặt lại. */
    public function notifyCustomerScheduledSearchTimeout(Booking $booking): void
    {
        $this->notifyCustomerSearchTimeoutInbox(
            $booking,
            'Chuyến '.$booking->booking_reference.' đã tự hủy vì không có tài xế nhận trước giờ đón 1 tiếng. Bạn có thể đặt lại.',
        );
    }

    private function notifyCustomerSearchTimeoutInbox(Booking $booking, string $body): void
    {
        $customerId = (int) ($booking->customer_id ?? 0);
        if ($customerId < 1) {
            return;
        }

        // Đã có / từng có TX nhận → không gửi «Không tìm được tài xế».
        if ($booking->hasDriverAccepted() || $booking->hadDriverEngagedForPickup()) {
            return;
        }

        if ($booking->booking_status !== 'cancelled' || $booking->cancelled_by !== 'system') {
            return;
        }

        try {
            app(CustomerInboxService::class)->notify(
                $customerId,
                \App\Models\CustomerInboxMessage::CATEGORY_NOTICE,
                'Không tìm được tài xế',
                $body,
                [
                    'booking_reference' => $booking->booking_reference,
                    'reason'            => 'driver_search_timeout',
                ],
            );
        } catch (\Throwable) {
        }
    }

    /** Báo admin cần xử lý — chuyến đã gỡ TX hoặc chưa có TX. */
    public function flagOperatorHelpNeeded(Booking $booking, string $reason): void
    {
        if (in_array($booking->booking_status, ['cancelled', 'rejected'], true)) {
            return;
        }

        if ($booking->needs_operator_help_at) {
            return;
        }

        DB::transaction(function () use ($booking, $reason): void {
            $before = $booking->toArray();

            $booking->update([
                'needs_operator_help_at' => now(),
                'operator_help_reason'   => $reason,
            ]);

            $schedule = $booking->schedule;
            if ($schedule) {
                $this->syncScheduleAvailability($schedule);
            }

            $this->audit($booking, null, 'booking_operator_help', $before, $booking->fresh()->toArray());
        });
    }

    /**
     * Gỡ TX khỏi chuyến đang ASSIGNED và báo admin theo dõi / hủy.
     * Không tự gán TX khác.
     */
    public function releaseDriverAndFlagOperatorHelp(Booking $booking, string $reason): bool
    {
        if (in_array($booking->booking_status, ['cancelled', 'rejected'], true)) {
            return false;
        }

        if ($booking->trip_status === 'completed' || $booking->passengerPickedUp()) {
            return false;
        }

        if ($booking->needs_operator_help_at) {
            return false;
        }

        $booking->loadMissing('schedule');
        $schedule = $booking->schedule;

        if (! $schedule || ! $schedule->driver_id) {
            return false;
        }

        if ($schedule->resolvedDriverStage() !== Schedule::DRIVER_STAGE_ASSIGNED) {
            return false;
        }

        $formerDriverId = (int) $schedule->driver_id;

        DB::transaction(function () use ($booking, $schedule, $formerDriverId, $reason): void {
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
                'driver_movement_confirmed_at'            => null,
                'driver_late_pickup_prompt_at'            => null,
                'driver_late_pickup_continue_deadline_at' => null,
            ]);

            DriverTripRequest::query()
                ->where('schedule_id', $locked->id)
                ->where('contact_phone', (string) $booking->contact_phone)
                ->where('driver_id', $formerDriverId)
                ->whereIn('status', ['pending', 'accepted'])
                ->update([
                    'status'       => 'expired',
                    'responded_at' => now(),
                    'expires_at'   => null,
                ]);

            $hide = app(DriverCuocOfferHideService::class);
            DriverTripRequest::query()
                ->where('schedule_id', $locked->id)
                ->where('contact_phone', (string) $booking->contact_phone)
                ->where('driver_id', $formerDriverId)
                ->whereIn('status', ['expired', 'cancelled'])
                ->each(fn (DriverTripRequest $request) => $hide->recordMissedOffer($request));

            $booking->update([
                'needs_operator_help_at' => now(),
                'operator_help_reason'   => $reason,
            ]);

            if (Schema::hasColumn('bookings', 'assigned_driver_id')) {
                $booking->update(['assigned_driver_id' => null]);
            }

            $this->syncScheduleAvailability($locked->fresh());
        });

        app(DriverAvailabilityService::class)->syncAfterTripCompleted($formerDriverId);

        return true;
    }

    // TODO (Auto Reassign Late Trip): Gỡ TX trễ (assigned) → auto-assign TX gần; fallback admin nếu không có TX.
    public function releaseDriverAndTryAutoReassign(Booking $booking, string $reason): bool
    {
        if (in_array($booking->booking_status, ['cancelled', 'rejected'], true)) {
            return false;
        }

        if ($booking->trip_status === 'completed' || $booking->passengerPickedUp()) {
            return false;
        }

        if ($booking->needs_operator_help_at) {
            return false;
        }

        $booking->loadMissing('schedule');
        $schedule = $booking->schedule;

        if (! $schedule || ! $schedule->driver_id) {
            return false;
        }

        if ($schedule->resolvedDriverStage() !== Schedule::DRIVER_STAGE_ASSIGNED) {
            return false;
        }

        $formerDriverId = (int) $schedule->driver_id;

        DB::transaction(function () use ($booking, $schedule, $formerDriverId): void {
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
                'driver_movement_confirmed_at'            => null,
                'driver_late_pickup_prompt_at'            => null,
                'driver_late_pickup_continue_deadline_at' => null,
                'driver_depart_reminder_sent_at'          => null,
            ]);

            DriverTripRequest::query()
                ->where('schedule_id', $locked->id)
                ->where('contact_phone', (string) $booking->contact_phone)
                ->where('driver_id', $formerDriverId)
                ->whereIn('status', ['pending', 'accepted'])
                ->update([
                    'status'       => 'expired',
                    'responded_at' => now(),
                    'expires_at'   => null,
                ]);

            $hide = app(DriverCuocOfferHideService::class);
            DriverTripRequest::query()
                ->where('schedule_id', $locked->id)
                ->where('contact_phone', (string) $booking->contact_phone)
                ->where('driver_id', $formerDriverId)
                ->whereIn('status', ['expired', 'cancelled'])
                ->each(fn (DriverTripRequest $request) => $hide->recordMissedOffer($request));

            if (Schema::hasColumn('bookings', 'assigned_driver_id')) {
                $booking->update(['assigned_driver_id' => null]);
            }

            $this->syncScheduleAvailability($locked->fresh());
        });

        app(DriverAvailabilityService::class)->syncAfterTripCompleted($formerDriverId);

        $tripRequests = app(DriverTripRequestService::class);
        $booking = $booking->fresh(['schedule.route', 'schedule.vehicle', 'schedule.template']);
        $tripRequests->refreshCustomerSearchDeadline($booking);
        $tripRequests->tryReassignAfterDriverRelease(
            $booking->fresh(['schedule.route', 'schedule.vehicle', 'schedule.template']),
            $formerDriverId,
        );

        $booking = $booking->fresh(['schedule']);
        if ($this->bookingHasReplacementDriver($booking)) {
            return true;
        }

        $this->flagOperatorHelpNeeded($booking->fresh(), $reason);

        return false;
    }

    // TODO (Auto Reassign Late Trip): TX mới đã nhận hoặc đang chờ xác nhận sau auto-assign.
    private function bookingHasReplacementDriver(Booking $booking): bool
    {
        $booking->loadMissing('schedule');
        $schedule = $booking->schedule;

        if ($schedule?->driver_id) {
            return true;
        }

        if (! $booking->schedule_id || ! $booking->contact_phone) {
            return false;
        }

        return DriverTripRequest::query()
            ->where('schedule_id', $booking->schedule_id)
            ->where('contact_phone', (string) $booking->contact_phone)
            ->where('status', 'pending')
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    private function abandonScheduleIfEmpty(Schedule $schedule): void
    {
        $stillActive = Booking::query()
            ->where('schedule_id', $schedule->id)
            ->whereNotIn('booking_status', ['cancelled', 'rejected'])
            ->where('trip_status', '!=', 'completed')
            ->exists();

        if ($stillActive) {
            return;
        }

        if ($schedule->driver_id) {
            $driverId = (int) $schedule->driver_id;
            $schedule->update([
                'driver_id'                               => null,
                'driver_name'                             => 'Chờ phân bổ',
                'driver_stage'                            => null,
                'driver_assigned_at'                      => null,
                'driver_movement_deadline_at'             => null,
                'driver_movement_confirmed_at'            => null,
                'driver_late_pickup_prompt_at'            => null,
                'driver_late_pickup_continue_deadline_at' => null,
                'status'                                  => 'cancelled',
            ]);
            $this->driverAvailability->syncAfterTripCompleted($driverId);

            return;
        }

        if ($schedule->status === 'scheduled' && $schedule->departure_time <= now()) {
            $schedule->update(['status' => 'cancelled']);
        }
    }

    /** Ẩn đơn không gán được TX — hủy hệ thống để khách/TX không còn theo dõi. */
    public function cancelUnfulfilledForOperatorDismiss(Booking $booking): void
    {
        if (in_array($booking->booking_status, ['cancelled', 'rejected'], true)) {
            $booking->update(['operator_dismissed_at' => now()]);

            return;
        }

        DB::transaction(function () use ($booking): void {
            $before = $booking->toArray();
            $schedule = $booking->schedule()->with(['vehicle', 'route'])->first();

            $assignment = [];
            $assignedDriverId = $booking->resolveAssignedDriverId($schedule);
            if ($assignedDriverId && Schema::hasColumn('bookings', 'assigned_driver_id')) {
                $assignment = ['assigned_driver_id' => $assignedDriverId];
            }

            $booking->update(array_merge([
                'booking_status'            => 'cancelled',
                'trip_status'               => 'cancelled',
                'payment_status'            => $booking->payment_status === 'paid' ? 'refunded' : 'unpaid',
                'cancelled_at'              => now(),
                'cancelled_by'              => 'system',
                'cancellation_reason_label' => 'Quản lý ẩn — không gán được tài xế',
                'operator_dismissed_at'     => now(),
            ], $assignment));

            if ($schedule) {
                DriverTripRequest::query()
                    ->where('schedule_id', $schedule->id)
                    ->where('contact_phone', (string) $booking->contact_phone)
                    ->where('status', 'pending')
                    ->update([
                        'status'       => 'cancelled',
                        'responded_at' => now(),
                        'expires_at'   => null,
                    ]);
            }

            $booking->paymentTransactions()->where('status', 'pending')->update(['status' => 'failed']);
            $this->syncScheduleAvailability($schedule);
            $this->audit($booking, null, 'booking_operator_dismissed', $before, $booking->fresh()->toArray());

            if ($schedule) {
                $stillActive = Booking::query()
                    ->where('schedule_id', $schedule->id)
                    ->whereNotIn('booking_status', ['cancelled', 'rejected'])
                    ->exists();

                if (! $stillActive) {
                    $this->tripLedger->recordForSchedule($schedule, TripLedger::OUTCOME_CANCELLED_CUSTOMER, [
                        'actor_label' => 'Hệ thống',
                        'actor_code'  => 'operator_dismiss',
                    ]);
                }
            }
        });

        $this->browserGuard->clearActiveBookingForBooking($booking->fresh());
    }

    /** Quản lý hủy chuyến đang xử lý — chuyển sang tab Đã hủy, gỡ khỏi tài xế và trang khách. */
    public function cancelByAdmin(Booking $booking, int $adminUserId): void
    {
        $formerDriverId = 0;

        DB::transaction(function () use ($booking, $adminUserId, &$formerDriverId): void {
            $locked = Booking::query()
                ->with(['schedule.vehicle', 'schedule.route'])
                ->lockForUpdate()
                ->findOrFail($booking->id);

            if (in_array($locked->booking_status, ['cancelled', 'rejected'], true)) {
                throw new InvalidArgumentException('Đơn đã hủy.');
            }

            if ($locked->trip_status === 'completed') {
                throw new InvalidArgumentException('Chuyến đã hoàn thành.');
            }

            if ($locked->passengerPickedUp()) {
                throw new InvalidArgumentException('Tài xế đã đón khách — không thể hủy chuyến.');
            }

            $schedule = $locked->schedule;
            if ($schedule) {
                $schedule = Schedule::query()->lockForUpdate()->find($schedule->id) ?? $schedule;
            }
            $formerDriverId = (int) ($schedule?->driver_id
                ?: $locked->assigned_driver_id
                ?: 0);

            $before = $locked->toArray();

            // Giữ assigned_driver_id / schedule.driver_id đến sau khi observer gửi push hủy cho TX.
            $updates = [
                'booking_status'            => 'cancelled',
                'trip_status'               => 'cancelled',
                'payment_status'            => $locked->payment_status === 'paid' ? 'refunded' : 'unpaid',
                'cancelled_at'              => now(),
                'cancelled_by'              => 'admin',
                'cancellation_reason_label' => 'Quản lý hủy chuyến',
                'hold_expires_at'           => null,
                'needs_operator_help_at'    => null,
                'operator_help_reason'      => null,
            ];

            if ($formerDriverId > 0) {
                $locked->cancelNotifyDriverId = $formerDriverId;
            }

            $locked->update($updates);

            if (Schema::hasColumn('bookings', 'assigned_driver_id') && $locked->assigned_driver_id) {
                $locked->update(['assigned_driver_id' => null]);
            }

            if ($schedule) {
                DriverTripRequest::query()
                    ->where('schedule_id', $schedule->id)
                    ->where('contact_phone', (string) $locked->contact_phone)
                    ->whereIn('status', ['pending', 'accepted', 'expired'])
                    ->update([
                        'status'       => 'cancelled',
                        'responded_at' => now(),
                        'expires_at'   => null,
                    ]);

                $stillActive = Booking::query()
                    ->where('schedule_id', $schedule->id)
                    ->whereNotIn('booking_status', ['cancelled', 'rejected'])
                    ->where('trip_status', '!=', 'completed')
                    ->exists();

                if (! $stillActive) {
                    $schedule->update([
                        'driver_id'                               => null,
                        'driver_name'                             => 'Chờ phân bổ',
                        'driver_stage'                            => null,
                        'driver_assigned_at'                      => null,
                        'driver_movement_deadline_at'             => null,
                        'driver_movement_confirmed_at'            => null,
                        'driver_late_pickup_prompt_at'            => null,
                        'driver_late_pickup_continue_deadline_at' => null,
                        'status'                                  => $schedule->status === 'running'
                            ? 'scheduled'
                            : $schedule->status,
                    ]);
                }
            }

            $locked->paymentTransactions()->where('status', 'pending')->update(['status' => 'failed']);
            $this->audit($locked, $adminUserId, 'booking_cancelled_admin', $before, $locked->fresh()->toArray());
        });

        if ($formerDriverId > 0) {
            $this->driverAvailability->syncAfterTripCompleted($formerDriverId);
        }

        $fresh = $booking->fresh();
        $this->syncScheduleAvailability($fresh?->schedule);
        if ($fresh) {
            $this->browserGuard->clearActiveBookingForBooking($fresh);
        }
    }

    /** Tài xế hủy chuyến sau khi nhận — gỡ TX và tìm lại nếu còn thời gian. */
    public function cancelScheduleByDriver(
        Schedule $schedule,
        int $driverUserId,
        int $cancellationReasonId,
        ?string $cancellationReasonNote = null,
    ): void {
        if (! $schedule->driverCanCancelTrip()) {
            throw new InvalidArgumentException('Sau khi đón khách không thể hủy — liên hệ quản lý nếu cần hỗ trợ.');
        }

        $reason = $this->cancellationReasons->resolveForCancel($cancellationReasonId, 'driver');
        // Validate note early (before locking).
        $this->cancellationReasons->composeLabel($reason, $cancellationReasonNote);

        $schedule = Schedule::query()
            ->with(['route', 'vehicle', 'bookings'])
            ->lockForUpdate()
            ->findOrFail($schedule->id);

        if ((int) $schedule->driver_id !== $driverUserId) {
            throw new InvalidArgumentException('Không có quyền hủy chuyến này.');
        }

        if (in_array($schedule->status, ['completed', 'cancelled'], true)) {
            throw new InvalidArgumentException('Chuyến đã kết thúc, không thể hủy.');
        }

        $activeBookings = $schedule->driverRelevantBookings()
            ->filter(fn (Booking $b): bool => ! in_array($b->booking_status, ['cancelled', 'rejected'], true)
                && $b->trip_status !== 'completed');

        if ($activeBookings->isEmpty()) {
            throw new InvalidArgumentException('Không còn vé cần hủy trên chuyến này.');
        }

        if ($activeBookings->contains(fn (Booking $b): bool => $b->trip_status === 'completed')) {
            throw new InvalidArgumentException('Đã có khách hoàn thành chuyến — không thể hủy.');
        }

        if (! in_array($schedule->resolvedDriverStage(), [Schedule::DRIVER_STAGE_ASSIGNED, Schedule::DRIVER_STAGE_AT_PICKUP], true)) {
            throw new InvalidArgumentException('Đã đón khách — không thể hủy cuốc.');
        }

        $formerDriverId = $driverUserId;
        $profile = DriverProfile::query()->with('user')->where('user_id', $driverUserId)->first();
        $driverLabel = $profile?->user?->name ?? $schedule->driver_name ?? 'Tài xế';
        $driverCode = $profile?->driver_code;

        DB::transaction(function () use ($schedule, $activeBookings, $formerDriverId, $reason, $cancellationReasonNote): void {
            $locked = Schedule::query()->lockForUpdate()->findOrFail($schedule->id);

            if ((int) $locked->driver_id !== $formerDriverId) {
                throw new InvalidArgumentException('Chuyến đã thay đổi.');
            }

            $locked->update([
                'driver_id'                               => null,
                'driver_name'                             => 'Chờ phân bổ',
                'driver_stage'                            => null,
                'driver_assigned_at'                      => null,
                'driver_movement_deadline_at'             => null,
                'driver_movement_confirmed_at'            => null,
                'driver_late_pickup_prompt_at'            => null,
                'driver_late_pickup_continue_deadline_at' => null,
                'driver_depart_reminder_sent_at'          => null,
            ]);

            foreach ($activeBookings as $booking) {
                $this->stampDriverReleaseReason($booking, $reason, $cancellationReasonNote);

                $releasedRequests = DriverTripRequest::query()
                    ->where('schedule_id', $locked->id)
                    ->where('contact_phone', (string) $booking->contact_phone)
                    ->where('driver_id', $formerDriverId)
                    ->whereIn('status', ['pending', 'accepted'])
                    ->get();

                DriverTripRequest::query()
                    ->where('schedule_id', $locked->id)
                    ->where('contact_phone', (string) $booking->contact_phone)
                    ->where('driver_id', $formerDriverId)
                    ->whereIn('status', ['pending', 'accepted'])
                    ->update([
                        'status'       => 'cancelled',
                        'responded_at' => now(),
                    ]);

                $hide = app(DriverCuocOfferHideService::class);
                foreach ($releasedRequests as $request) {
                    $hide->recordMissedOffer($request->fresh());
                }

                if (Schema::hasColumn('bookings', 'assigned_driver_id')) {
                    $booking->update(['assigned_driver_id' => null]);
                }
            }

            $this->syncScheduleAvailability($locked->fresh(['vehicle', 'route']));
        });

        $this->tripLedger->recordForSchedule($schedule, TripLedger::OUTCOME_CANCELLED_DRIVER, [
            'actor_label' => $driverLabel,
            'actor_code'  => $driverCode,
        ]);

        app(DriverAvailabilityService::class)->syncAfterTripCompleted($formerDriverId);

        $tripRequests = app(DriverTripRequestService::class);

        foreach ($activeBookings as $booking) {
            $fresh = $booking->fresh(['schedule.route', 'schedule.vehicle', 'schedule.template']);

            if ($fresh->isOnDemandPickup() && $fresh->isPastPickupTime()) {
                $this->cancelPickupTimeoutIfDue($fresh);

                continue;
            }

            if ($tripRequests->hasReachedScheduledSearchStop($fresh)) {
                $this->cancelScheduledSearchTimeout($fresh);

                continue;
            }

            if ($tripRequests->hasExceededCustomerSearchDeadline($fresh)) {
                $tripRequests->cancelCustomerSearchIfOverdue($fresh);

                continue;
            }

            $tripRequests->refreshCustomerSearchDeadline($fresh);

            if ($fresh->needs_operator_help_at) {
                $fresh->update([
                    'needs_operator_help_at' => null,
                    'operator_help_reason'   => null,
                ]);
                $fresh = $fresh->fresh(['schedule.route', 'schedule.vehicle', 'schedule.template']);
            }

            $tripRequests->tryReassignAfterDriverRelease($fresh, $formerDriverId);
        }
    }

    /** Tài xế nhận cuốc — khách trả trực tiếp, không cần QL xác nhận thanh toán. */
    public function confirmForDriverAccept(Booking $booking): void
    {
        $this->progression->confirmForDriverAccept($booking);
    }

    public function driverCompleteTrip(Booking $booking, int $driverUserId): int
    {
        return $this->progression->driverCompleteTrip($booking, $driverUserId);
    }

    /** Tài xế chuyển bước: đến điểm đón → đón khách → đang chạy. */
    public function driverAdvanceScheduleStage(Schedule $schedule, int $driverUserId): string
    {
        return $this->progression->driverAdvanceScheduleStage($schedule, $driverUserId);
    }

    /** Hoàn thành tất cả vé còn lại trên cùng một chuyến xe. */
    public function driverCompleteSchedule(Schedule $schedule, int $driverUserId): int
    {
        return $this->progression->driverCompleteSchedule($schedule, $driverUserId);
    }

    public function findReusablePendingBooking(
        Schedule $schedule,
        string $contactPhone,
    ): ?Booking {
        return $this->creation->findReusablePendingBooking($schedule, $contactPhone);
    }

    public function syncScheduleAvailability(Schedule $schedule): void
    {
        $driverUserId = (int) ($schedule->driver_id ?? 0);

        if ($driverUserId <= 0) {
            return;
        }

        $this->driverAvailability->syncAfterTripCompleted($driverUserId);
    }

    public function finalizeTripsAfterScheduleEnd(Schedule $schedule): void
    {
        $touched = false;

        Booking::query()
            ->where('schedule_id', $schedule->id)
            ->where('booking_status', 'confirmed')
            ->where('trip_status', 'confirmed')
            ->each(function (Booking $booking) use (&$touched): void {
                $before = $booking->toArray();

                $booking->update([
                    'trip_status'  => 'awaiting_completion',
                    'completed_at' => now(),
                ]);

                $this->audit($booking, null, 'trip_auto_finalized', $before, $booking->fresh()->toArray());
                $touched = true;
            });
    }

    public function expireStaleBookings(): void
    {
        $this->expirePastPickupWithoutDriver();
    }

    private function assertContactPhone(Booking $booking, string $phone): void
    {
        if (! $booking->matchesContactPhone($phone)) {
            abort(403, 'Số điện thoại không khớp với vé.');
        }
    }

    private function audit(Booking $booking, ?int $actor, string $action, ?array $before, ?array $after): void
    {
        BookingAudit::query()->create([
            'booking_id'   => $booking->id,
            'actor_id'     => $actor,
            'action'       => $action,
            'before_state' => $before,
            'after_state'  => $after,
        ]);
    }
}
