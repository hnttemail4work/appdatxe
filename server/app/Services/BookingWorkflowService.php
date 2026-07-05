<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingAudit;
use App\Models\DriverProfile;
use App\Models\Schedule;
use App\Models\TripLedger;
use App\Models\ScheduleTemplate;
use App\Models\ReferralCode;
use App\Models\DriverTripRequest;
use App\Services\DriverAvailabilityService;
use App\Services\TripPricingService;
use App\Support\PlatformFees;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;

class BookingWorkflowService
{
    public function __construct(
        private readonly ScheduleLifecycleService $scheduleLifecycle,
        private readonly DriverWalletService $driverWallet,
        private readonly TripPricingService $pricing,
        private readonly ReferralCodeService $referralCodes,
        private readonly TripLedgerService $tripLedger,
        private readonly DriverTripRequestService $driverRequests,
        private readonly BookingPhoneGuardService $phoneGuard,
        private readonly CancellationReasonService $cancellationReasons,
        private readonly DuplicateBookingService $duplicateBookings,
        private readonly BookingBrowserGuardService $browserGuard,
        private readonly DriverAvailabilityService $driverAvailability,
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
    ): Booking {
        $template->loadMissing(['route', 'vehicle']);

        $pickup = $pickupAddress ?: $template->route?->departure;
        $dropoff = $dropoffAddress ?: $template->route?->destination;

        if (! $pickup || ! $dropoff) {
            throw new InvalidArgumentException('Vui lòng chọn điểm đi và điểm đến.');
        }

        $route = app(BookingRouteService::class)->resolve($pickup, $dropoff);

        $schedule = $this->scheduleLifecycle->resolveScheduleForBooking(
            $template,
            $serviceDate,
            $pickupTime,
            true,
            1,
            $pickupLat,
            $pickupLng,
            $route->id,
        );

        $pickup = $pickupAddress ?: $pickup;
        $dropoff = $dropoffAddress ?: $dropoff;

        $existing = $this->findReusablePendingBooking($schedule, $contactPhone);
        if ($existing) {
            $booking = $this->refreshPendingBooking(
                $existing,
                $passengerName,
                $pickupTime,
                $pickup,
                $pickupDetail,
                $dropoff,
                $dropoffDetail,
                $notes,
                $appliedReferralCodeId,
                $passengerGender,
                $passengerAge,
                $pickupLat,
                $pickupLng,
                $dropoffLat,
                $dropoffLng,
            );
            try {
                $this->driverRequests->assignCatalogBooking($booking->fresh(['schedule.route', 'schedule.vehicle']), $template);
            } catch (InvalidArgumentException) {
            }

            return $booking;
        }

        $booking = $this->duplicateBookings->withPhoneBookingLock($contactPhone, function () use (
            $schedule,
            $contactPhone,
            $passengerName,
            $pickup,
            $pickupDetail,
            $dropoff,
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
        ): Booking {
            return $this->createBooking(
                $schedule,
                $contactPhone,
                $passengerName,
                $pickup,
                $pickupDetail,
                $dropoff,
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
        });

        try {
            $this->driverRequests->assignCatalogBooking($booking->fresh(['schedule.route', 'schedule.vehicle']), $template);
        } catch (InvalidArgumentException) {
            // Đơn vẫn hiển thị admin — tài xế có thể nhận thủ công.
        }

        return $booking;
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
        $booking = DB::transaction(function () use ($schedule, $contactPhone, $passengerName, $pickupAddress, $pickupDetail, $dropoffAddress, $dropoffDetail, $notes, $pickupTime, $appliedReferralCodeId, $passengerGender, $passengerAge, $pickupLat, $pickupLng, $dropoffLat, $dropoffLng): Booking {
            $this->duplicateBookings->assertCanBook($contactPhone);

            $this->scheduleLifecycle->sync();

            $schedule = Schedule::query()
                ->with(['route', 'vehicle'])
                ->lockForUpdate()
                ->findOrFail($schedule->id);

            if (! in_array($schedule->status, ['scheduled'], true)) {
                throw new InvalidArgumentException('Chuyến không còn mở đặt vé (đang chạy hoặc đã kết thúc).');
            }

            $totalPrice = $this->pricing->bookingTotal($schedule, $pickupAddress, $dropoffAddress, $pickupLat, $pickupLng, $dropoffLat, $dropoffLng);
            $totalPrice = $this->applyReferralToTotal($totalPrice, $contactPhone, $appliedReferralCodeId);

            $booking = Booking::query()->create([
                'contact_phone'            => trim($contactPhone),
                'passenger_name'           => trim($passengerName),
                'passenger_gender'         => $passengerGender === 'female' ? 'female' : 'male',
                'passenger_age'            => $passengerAge,
                'schedule_id'              => $schedule->id,
                'booking_reference'        => 'BK-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(6)),
                'applied_referral_code_id' => $appliedReferralCodeId,
                'total_price'              => $totalPrice,
                'payment_status'           => 'unpaid',
                'trip_status'              => 'pending',
                'booking_status'           => 'pending',
                'pickup_address'           => $pickupAddress,
                'pickup_detail'            => $pickupDetail ? trim($pickupDetail) : null,
                'pickup_lat'               => $pickupLat,
                'pickup_lng'               => $pickupLng,
                'pickup_time'              => $pickupTime ? \App\Support\DepartureTimeDisplay::storageValue($pickupTime) : null,
                'dropoff_address'          => $dropoffAddress,
                'dropoff_detail'           => $dropoffDetail ? trim($dropoffDetail) : null,
                'dropoff_lat'              => $dropoffLat,
                'dropoff_lng'              => $dropoffLng,
                'notes'                    => $notes,
                'hold_expires_at'          => null,
            ]);

            $this->syncScheduleAvailability($schedule);
            $this->audit($booking, null, 'booking_created', null, $booking->toArray());
            $this->referralCodes->issueForBooking($booking);

            return $booking;
        });

        return $booking;
    }

    public function cancelByPhone(Booking $booking, string $contactPhone, ?int $cancellationReasonId = null): void
    {
        $this->assertContactPhone($booking, $contactPhone);

        if (in_array($booking->booking_status, ['cancelled', 'rejected'], true)) {
            throw new InvalidArgumentException('Vé này đã hủy rồi.');
        }

        if ($booking->trip_status === 'completed') {
            throw new InvalidArgumentException('Chuyến đã hoàn tất, không thể hủy.');
        }

        $reason = null;
        if ($this->phoneGuard->requiresCancelReason($contactPhone)) {
            if (! $cancellationReasonId) {
                throw new InvalidArgumentException('Vui lòng chọn lý do hủy chuyến.');
            }
            $reason = $this->cancellationReasons->resolveForCancel($cancellationReasonId, 'customer');
        }

        DB::transaction(function () use ($booking, $reason): void {
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
                'cancellation_reason_label'   => $reason?->label,
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

    /** Quá giờ đón mà chưa có tài xế — hủy hệ thống để khách đặt lại, admin thấy tab Đã hủy. */
    public function cancelPickupTimeout(Booking $booking): void
    {
        if (in_array($booking->booking_status, ['cancelled', 'rejected'], true)) {
            return;
        }

        if ($booking->hasDriverAccepted()) {
            return;
        }

        if (! $booking->isPastPickupTime()) {
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
                'cancellation_reason_label' => 'Quá giờ đón — không có tài xế',
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

    public function cancelPickupTimeoutIfDue(Booking $booking): bool
    {
        $booking = $booking->fresh(['schedule']);

        if ($booking->hasDriverAccepted() || ! $booking->isPastPickupTime()) {
            return false;
        }

        if (in_array($booking->booking_status, ['cancelled', 'rejected'], true)
            || in_array($booking->trip_status, ['completed', 'cancelled'], true)) {
            return false;
        }

        $this->cancelPickupTimeout($booking);

        return true;
    }

    /** @return int Số đơn đã hủy vì quá giờ đón không có tài xế. */
    public function expirePastPickupWithoutDriver(): int
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
                    if ($this->cancelPickupTimeoutIfDue($booking)) {
                        $cancelled++;
                    }
                }
            });

        return $cancelled;
    }

    /** Hết 15 phút tìm tài xế — hủy để khách đặt lại. */
    public function cancelSearchTimeout(Booking $booking): void
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
                'cancellation_reason_label' => 'Hết thời gian tìm tài xế',
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

    /** Hết 15 phút chờ tài xế xác nhận sau khi quản lý gán — hủy, chuyển tab Đã hủy. */
    public function cancelOperatorInviteTimeout(Booking $booking): void
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
                'cancellation_reason_label' => 'Hết thời gian chờ tài xế xác nhận (15 phút)',
                'hold_expires_at'           => null,
            ];

            if (Schema::hasColumn('bookings', 'assigned_driver_id')) {
                $updates['assigned_driver_id'] = null;
            }

            $booking->update($updates);

            if ($schedule) {
                DriverTripRequest::query()
                    ->where('schedule_id', $schedule->id)
                    ->whereIn('status', ['pending', 'expired'])
                    ->update([
                        'status'       => 'cancelled',
                        'responded_at' => now(),
                        'expires_at'   => null,
                    ]);

                $this->syncScheduleAvailability($schedule);
            }

            $this->audit($booking, null, 'booking_cancelled', $before, $booking->fresh()->toArray());
        });

        $this->browserGuard->clearActiveBookingForBooking($booking->fresh());
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
        if (in_array($booking->booking_status, ['cancelled', 'rejected'], true)) {
            throw new InvalidArgumentException('Đơn đã hủy.');
        }

        if ($booking->trip_status === 'completed') {
            throw new InvalidArgumentException('Chuyến đã hoàn thành.');
        }

        if ($booking->passengerPickedUp()) {
            throw new InvalidArgumentException('Tài xế đã đón khách — không thể hủy chuyến.');
        }

        $booking->loadMissing(['schedule.vehicle', 'schedule.route']);
        $schedule = $booking->schedule;
        $formerDriverId = (int) ($schedule?->driver_id ?? 0);

        DB::transaction(function () use ($booking, $adminUserId, $schedule, $formerDriverId): void {
            $before = $booking->toArray();

            $updates = [
                'booking_status'            => 'cancelled',
                'trip_status'               => 'cancelled',
                'payment_status'            => $booking->payment_status === 'paid' ? 'refunded' : 'unpaid',
                'cancelled_at'              => now(),
                'cancelled_by'              => 'admin',
                'cancellation_reason_label' => 'Quản lý hủy chuyến',
                'hold_expires_at'           => null,
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
                        'driver_late_pickup_prompt_at'            => null,
                        'driver_late_pickup_continue_deadline_at' => null,
                        'status'                                  => $schedule->status === 'running'
                            ? 'scheduled'
                            : $schedule->status,
                    ]);
                }
            }

            $booking->paymentTransactions()->where('status', 'pending')->update(['status' => 'failed']);
            $this->audit($booking, $adminUserId, 'booking_cancelled_admin', $before, $booking->fresh()->toArray());
        });

        if ($formerDriverId > 0) {
            $this->driverAvailability->syncAfterTripCompleted($formerDriverId);
        }

        $this->syncScheduleAvailability($booking->fresh()->schedule);
        $this->browserGuard->clearActiveBookingForBooking($booking->fresh());
    }

    /** Tài xế hủy chuyến — đã tắt sau khi nhận cuốc (chỉ từ chối trước khi nhận). */
    public function cancelScheduleByDriver(Schedule $schedule, int $driverUserId, int $cancellationReasonId): void
    {
        if (! $schedule->driverCanCancelTrip()) {
            throw new InvalidArgumentException('Sau khi nhận cuốc không thể hủy — liên hệ quản lý nếu cần hỗ trợ.');
        }

        $reason = $this->cancellationReasons->resolveForCancel($cancellationReasonId, 'driver');

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

        DB::transaction(function () use ($schedule, $activeBookings, $driverUserId, $reason): void {
            $profile = DriverProfile::query()->with('user')->where('user_id', $driverUserId)->first();
            $driverLabel = $profile?->user?->name ?? $schedule->driver_name ?? 'Tài xế';
            $driverCode = $profile?->driver_code;

            foreach ($activeBookings as $booking) {
                $before = $booking->toArray();

                $booking->update([
                    'booking_status'            => 'cancelled',
                    'trip_status'               => 'cancelled',
                    'payment_status'            => $booking->payment_status === 'paid' ? 'refunded' : 'unpaid',
                    'cancelled_at'              => now(),
                    'cancelled_by'              => 'driver',
                    'cancellation_reason_id'    => $reason->id,
                    'cancellation_reason_label' => $reason->label,
                ]);

                $booking->paymentTransactions()->where('status', 'pending')->update(['status' => 'failed']);
                $this->audit($booking, $driverUserId, 'driver_trip_cancelled', $before, $booking->fresh()->toArray());
            }

            $schedule->update(['status' => 'cancelled']);
            $this->syncScheduleAvailability($schedule->fresh(['vehicle', 'route']));
            $this->tripLedger->recordForSchedule($schedule, TripLedger::OUTCOME_CANCELLED_DRIVER, [
                'actor_label' => $driverLabel,
                'actor_code'  => $driverCode,
            ]);
        });
    }

    /** Tài xế nhận cuốc — khách trả trực tiếp, không cần QL xác nhận thanh toán. */
    public function confirmForDriverAccept(Booking $booking): void
    {
        if (in_array($booking->booking_status, ['cancelled', 'rejected'], true)) {
            return;
        }

        if ($booking->booking_status === 'confirmed' && $booking->trip_status === 'confirmed') {
            return;
        }

        DB::transaction(function () use ($booking): void {
            $before = $booking->toArray();

            $booking->update([
                'booking_status' => 'confirmed',
                'trip_status'    => 'confirmed',
                'payment_status' => 'paid',
                'confirmed_at'   => now(),
            ]);

            $this->syncScheduleAvailability($booking->schedule()->with(['vehicle'])->first());
            $this->audit($booking, null, 'driver_accept_confirmed', $before, $booking->fresh()->toArray());
        });
    }

    public function driverCompleteTrip(Booking $booking, int $driverUserId): int
    {
        $booking->loadMissing('schedule');

        return $this->driverCompleteSchedule($booking->schedule, $driverUserId);
    }

    /** Tài xế chuyển bước: đến điểm đón → đón khách → đang chạy. */
    public function driverAdvanceScheduleStage(Schedule $schedule, int $driverUserId): string
    {
        if ((int) $schedule->driver_id !== $driverUserId) {
            abort(403, 'Bạn không được phân công cho chuyến này.');
        }

        if (in_array($schedule->status, ['completed', 'cancelled'], true)) {
            throw new InvalidArgumentException('Chuyến đã kết thúc.');
        }

        $next = $schedule->driverNextStage();
        if (! $next || $next === Schedule::DRIVER_STAGE_COMPLETED) {
            throw new InvalidArgumentException('Không thể chuyển bước tiếp theo.');
        }

        DB::transaction(function () use ($schedule, $next): void {
            $schedule = Schedule::query()->lockForUpdate()->findOrFail($schedule->id);
            $bookings = Booking::query()
                ->where('schedule_id', $schedule->id)
                ->whereNotIn('booking_status', ['cancelled', 'rejected'])
                ->lockForUpdate()
                ->get();

            if ($bookings->isEmpty()) {
                throw new InvalidArgumentException('Không còn vé trên chuyến này.');
            }

            if ($next === Schedule::DRIVER_STAGE_PICKED_UP) {
                foreach ($bookings as $booking) {
                    $this->confirmForDriverAccept($booking);
                }
            }

            $payload = ['driver_stage' => $next];

            if ($next === Schedule::DRIVER_STAGE_AT_PICKUP) {
                app(DriverMovementConfirmService::class)->clearDeadline($schedule);
            }

            if ($next === Schedule::DRIVER_STAGE_RUNNING) {
                $payload['status'] = 'running';
            }

            $schedule->update($payload);
        });

        return $next;
    }

    /** Hoàn thành tất cả vé còn lại trên cùng một chuyến xe. */
    public function driverCompleteSchedule(Schedule $schedule, int $driverUserId): int
    {
        if ((int) $schedule->driver_id !== $driverUserId) {
            abort(403, 'Bạn không được phân công cho chuyến này.');
        }

        $completed = 0;
        $completedBookings = collect();

        DB::transaction(function () use ($schedule, $driverUserId, &$completed, &$completedBookings): void {
            $bookings = Booking::query()
                ->where('schedule_id', $schedule->id)
                ->whereNotIn('booking_status', ['cancelled', 'rejected'])
                ->where('trip_status', '!=', 'completed')
                ->lockForUpdate()
                ->get();

            if ($bookings->isEmpty()) {
                throw new InvalidArgumentException('Không còn vé nào cần hoàn thành trên chuyến này.');
            }

            foreach ($bookings as $booking) {
                $this->completeSingleDriverTrip($booking, $driverUserId);
                $completedBookings->push($booking);
                $completed++;
            }

            $schedule->update([
                'driver_stage' => Schedule::DRIVER_STAGE_COMPLETED,
                'status'       => 'completed',
            ]);
        });

        $schedule = $schedule->fresh(['route', 'bookings']);
        $this->driverWallet->onScheduleCompleted($schedule);
        $this->tripLedger->recordForSchedule($schedule, TripLedger::OUTCOME_COMPLETED);
        $this->syncScheduleAvailability($schedule);

        return $completed;
    }

    private function completeSingleDriverTrip(Booking $booking, int $driverUserId): void
    {
        $this->assertNotTerminal($booking);

        if ($booking->trip_status === 'completed') {
            return;
        }

        $before = $booking->toArray();

        $booking->update([
            'trip_status'  => 'completed',
            'completed_at' => now(),
        ]);

        $this->audit($booking, $driverUserId, 'driver_trip_completed', $before, $booking->fresh()->toArray());
        $this->referralCodes->activateForCompletedBooking($booking);
        $this->browserGuard->clearActiveBookingForBooking($booking->fresh());
    }

    public function assertSeatsAvailable(Schedule $schedule, array $seats): void
    {
        // Ghế / ghép xe đã bỏ — mỗi đơn thuê cả xe trên chuyến riêng.
    }

    public function findReusablePendingBooking(
        Schedule $schedule,
        string $contactPhone,
    ): ?Booking {
        return Booking::query()
            ->where('schedule_id', $schedule->id)
            ->where('booking_status', 'pending')
            ->where(fn ($q) => $q->whereNull('hold_expires_at')->orWhere('hold_expires_at', '>', now()))
            ->get()
            ->first(fn (Booking $booking): bool => $booking->matchesContactPhone($contactPhone));
    }

    private function refreshPendingBooking(
        Booking $booking,
        string $passengerName,
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
    ): Booking {
        $booking->loadMissing('schedule.route');
        $totalPrice = $this->pricing->bookingTotal($booking->schedule, $pickupAddress, $dropoffAddress, $pickupLat, $pickupLng, $dropoffLat, $dropoffLng);
        $totalPrice = $this->applyReferralToTotal($totalPrice, $booking->contact_phone, $appliedReferralCodeId);

        $refreshFields = [
            'passenger_name'   => trim($passengerName),
            'passenger_gender' => $passengerGender === 'female' ? 'female' : 'male',
            'passenger_age'    => $passengerAge,
            'pickup_address'   => $pickupAddress,
            'pickup_detail'    => $pickupDetail ? trim($pickupDetail) : null,
            'pickup_lat'       => $pickupLat,
            'pickup_lng'       => $pickupLng,
            'pickup_time'      => $pickupTime ? \App\Support\DepartureTimeDisplay::storageValue($pickupTime) : null,
            'dropoff_address'  => $dropoffAddress,
            'dropoff_detail'   => $dropoffDetail ? trim($dropoffDetail) : null,
            'dropoff_lat'      => $dropoffLat,
            'dropoff_lng'      => $dropoffLng,
            'notes'            => $notes,
            'total_price'      => $totalPrice,
            'hold_expires_at'  => null,
            'driver_search_started_at' => now(),
            'needs_operator_help_at'   => null,
        ];

        if (Booking::supportsOperatorDismiss()) {
            $refreshFields['operator_dismissed_at'] = null;
        }

        $booking->update($refreshFields);

        if ($appliedReferralCodeId !== null) {
            $booking->update(['applied_referral_code_id' => $appliedReferralCodeId]);
        }

        $this->driverRequests->autoAssignForBooking($booking->fresh(['schedule.route', 'schedule.vehicle']));

        return $booking->fresh();
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

    /** @deprecated Operator help queue removed. */
    public function handOffScheduleToOperator(Schedule $schedule, string $reason = 'trip_not_completed'): void
    {
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

    private function assertDriverAssigned(Booking $booking, int $driverUserId): void
    {
        $schedule = $booking->schedule()->first();

        if (! $schedule || (int) $schedule->driver_id !== $driverUserId) {
            abort(403, 'Bạn không được phân công cho chuyến này.');
        }
    }

    private function assertNotTerminal(Booking $booking): void
    {
        if (in_array($booking->booking_status, ['cancelled', 'rejected'], true)) {
            throw new InvalidArgumentException('Vé đã bị hủy hoặc từ chối.');
        }
    }

    private function assertNotExpired(Booking $booking): void
    {
        if ($booking->isExpired()) {
            throw new InvalidArgumentException('Vé đã hết hạn, không thể thực hiện thao tác.');
        }
    }

    private function applyReferralToTotal(float $subtotal, string $contactPhone, ?int &$appliedReferralCodeId): float
    {
        if (! $appliedReferralCodeId) {
            return (float) PlatformFees::roundUpToThousand($subtotal);
        }

        $referral = ReferralCode::query()->find($appliedReferralCodeId);
        if (! $referral || ! $referral->isUsable()) {
            $appliedReferralCodeId = null;

            return (float) PlatformFees::roundUpToThousand($subtotal);
        }

        if ($referral->type === ReferralCode::TYPE_BOOKING_TEMP) {
            if (! $this->referralCodes->qualifiesForCustomerDiscount($referral, $contactPhone)) {
                $appliedReferralCodeId = null;

                return (float) PlatformFees::roundUpToThousand($subtotal);
            }

            return $this->referralCodes->applyDiscount($subtotal, $referral->customerDiscountPercent());
        }

        if ($referral->type === ReferralCode::TYPE_REFERRER) {
            if (! $this->referralCodes->shouldAttributeBooking($referral, $contactPhone)) {
                $appliedReferralCodeId = null;

                return (float) PlatformFees::roundUpToThousand($subtotal);
            }

            $percent = $this->referralCodes->customerDiscountPercent($referral, $contactPhone);
            if ($percent > 0) {
                return $this->referralCodes->applyDiscount($subtotal, $percent);
            }

            return (float) PlatformFees::roundUpToThousand($subtotal);
        }

        $appliedReferralCodeId = null;

        return (float) PlatformFees::roundUpToThousand($subtotal);
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
