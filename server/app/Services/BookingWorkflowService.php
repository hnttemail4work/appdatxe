<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingAudit;
use App\Models\DriverProfile;
use App\Models\PaymentTransaction;
use App\Models\Schedule;
use App\Models\TripLedger;
use App\Models\ScheduleTemplate;
use App\Models\SeatReservation;
use App\Models\ReferralCode;
use App\Services\TripPricingService;
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
    ) {
    }

    public function createBookingFromTemplate(
        ScheduleTemplate $template,
        string $contactPhone,
        string $passengerName,
        array $seatNumbers,
        string $serviceDate,
        ?string $pickupTime,
        ?string $pickupAddress,
        ?string $pickupDetail,
        ?string $dropoffAddress,
        ?string $dropoffDetail,
        ?string $notes,
        string $tripType = 'one_way',
        string $bookingMode = 'shared',
        ?int $appliedReferralCodeId = null,
        string $passengerGender = 'male',
        ?int $passengerAge = null,
        ?float $pickupLat = null,
        ?float $pickupLng = null,
    ): Booking {
        $template->loadMissing(['route', 'vehicle']);

        $schedule = $this->scheduleLifecycle->resolveScheduleForBooking(
            $template,
            $serviceDate,
            $pickupTime,
        );

        $pickup = $pickupAddress ?: $template->route->departure;
        $dropoff = $dropoffAddress ?: $template->route->destination;

        $existing = $this->findReusablePendingBooking($schedule, $contactPhone, $seatNumbers);
        if ($existing) {
            return $this->refreshPendingBooking(
                $existing,
                $passengerName,
                $pickupTime,
                $pickup,
                $pickupDetail,
                $dropoff,
                $dropoffDetail,
                $notes,
                $tripType,
                $bookingMode,
                $appliedReferralCodeId,
                $passengerGender,
                $passengerAge,
                $pickupLat,
                $pickupLng,
            );
        }

        return $this->createBooking(
            $schedule,
            $contactPhone,
            $passengerName,
            $seatNumbers,
            $pickup,
            $pickupDetail,
            $dropoff,
            $dropoffDetail,
            $notes,
            $tripType,
            $bookingMode,
            $pickupTime,
            $appliedReferralCodeId,
            $passengerGender,
            $passengerAge,
            $pickupLat,
            $pickupLng,
        );
    }

    public function createBooking(
        Schedule $schedule,
        string $contactPhone,
        string $passengerName,
        array $seatNumbers,
        ?string $pickupAddress = null,
        ?string $pickupDetail = null,
        ?string $dropoffAddress = null,
        ?string $dropoffDetail = null,
        ?string $notes = null,
        string $tripType = 'one_way',
        string $bookingMode = 'shared',
        ?string $pickupTime = null,
        ?int $appliedReferralCodeId = null,
        string $passengerGender = 'male',
        ?int $passengerAge = null,
        ?float $pickupLat = null,
        ?float $pickupLng = null,
    ): Booking {
        $booking = DB::transaction(function () use ($schedule, $contactPhone, $passengerName, $seatNumbers, $pickupAddress, $pickupDetail, $dropoffAddress, $dropoffDetail, $notes, $tripType, $bookingMode, $pickupTime, $appliedReferralCodeId, $passengerGender, $passengerAge, $pickupLat, $pickupLng): Booking {
            $this->scheduleLifecycle->sync();

            $schedule = Schedule::query()
                ->with(['route', 'vehicle', 'seatReservations'])
                ->lockForUpdate()
                ->findOrFail($schedule->id);

            if (! in_array($schedule->status, ['scheduled'], true)) {
                throw new InvalidArgumentException('Chuyến không còn mở đặt vé (đang chạy hoặc đã kết thúc).');
            }

            $seatNumbers = array_map(fn ($seat): string => (string) $seat, $seatNumbers);

            $this->scheduleLifecycle->purgeInactiveSeatReservations($schedule, $seatNumbers);
            $this->assertSeatsAvailable($schedule, $seatNumbers);

            $totalPrice = $this->pricing->bookingTotal(
                $schedule,
                $tripType,
                $bookingMode,
                count($seatNumbers),
                $pickupAddress,
                $dropoffAddress,
            );
            $totalPrice = $this->applyReferralToTotal($totalPrice, $contactPhone, $appliedReferralCodeId);
            $holdExpires = null;

            $booking = Booking::query()->create([
                'contact_phone'     => trim($contactPhone),
                'passenger_name'    => trim($passengerName),
                'passenger_gender'  => $passengerGender === 'female' ? 'female' : 'male',
                'passenger_age'     => $passengerAge,
                'schedule_id'       => $schedule->id,
                'seat_numbers'      => $seatNumbers,
                'trip_type'         => $tripType,
                'booking_mode'      => $bookingMode,
                'booking_reference' => 'BK-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(6)),
                'applied_referral_code_id' => $appliedReferralCodeId,
                'total_price'       => $totalPrice,
                'payment_status'    => 'unpaid',
                'trip_status'       => 'pending',
                'booking_status'    => 'pending',
                'pickup_address'    => $pickupAddress,
                'pickup_detail'     => $pickupDetail ? trim($pickupDetail) : null,
                'pickup_lat'        => $pickupLat,
                'pickup_lng'        => $pickupLng,
                'pickup_time'       => $pickupTime ? \App\Support\DepartureTimeDisplay::storageValue($pickupTime) : null,
                'dropoff_address'   => $dropoffAddress,
                'dropoff_detail'    => $dropoffDetail ? trim($dropoffDetail) : null,
                'notes'             => $notes,
                'hold_expires_at'   => $holdExpires,
            ]);

            foreach ($seatNumbers as $seat) {
                SeatReservation::query()->create([
                    'schedule_id'       => $schedule->id,
                    'booking_id'        => $booking->id,
                    'seat_number'       => $seat,
                    'reservation_token' => (string) Str::uuid(),
                    'status'            => 'held',
                    'expires_at'        => $holdExpires,
                ]);
            }

            $this->syncScheduleAvailability($schedule);
            $this->audit($booking, null, 'booking_created', null, $booking->toArray());
            $this->referralCodes->issueForBooking($booking);

            return $booking;
        });

        $this->driverRequests->autoAssignForBooking($booking->fresh(['schedule.route', 'schedule.vehicle']));

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
            $schedule = $booking->schedule()->with(['vehicle', 'seatReservations', 'route'])->first();

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

            $booking->seatReservations()->delete();
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
    }

    /** Tài xế hủy chuyến trước khi khách lên xe / trước hoàn thành. */
    public function cancelScheduleByDriver(Schedule $schedule, int $driverUserId, int $cancellationReasonId): void
    {
        $reason = $this->cancellationReasons->resolveForCancel($cancellationReasonId, 'driver');

        $schedule = Schedule::query()
            ->with(['route', 'vehicle', 'seatReservations', 'bookings'])
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

                $booking->seatReservations()->delete();
                $booking->paymentTransactions()->where('status', 'pending')->update(['status' => 'failed']);
                $this->audit($booking, $driverUserId, 'driver_trip_cancelled', $before, $booking->fresh()->toArray());
            }

            $schedule->update(['status' => 'cancelled']);
            $this->syncScheduleAvailability($schedule->fresh(['vehicle', 'seatReservations', 'route']));
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

            $booking->seatReservations()->update(['status' => 'booked', 'expires_at' => null]);
            $this->syncScheduleAvailability($booking->schedule()->with(['vehicle', 'seatReservations'])->first());
            $this->audit($booking, null, 'driver_accept_confirmed', $before, $booking->fresh()->toArray());
        });
    }

    public function driverCompleteTrip(Booking $booking, int $driverUserId): int
    {
        $booking->loadMissing('schedule');

        return $this->driverCompleteSchedule($booking->schedule, $driverUserId);
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
        });

        $schedule = $schedule->fresh(['route', 'bookings']);
        $this->driverWallet->onScheduleCompleted($schedule);
        $this->tripLedger->recordForSchedule($schedule, TripLedger::OUTCOME_COMPLETED);

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

        app(\App\Services\DriverTripRequestService::class)->clearOperatorHelp($booking->fresh());

        $this->audit($booking, $driverUserId, 'driver_trip_completed', $before, $booking->fresh()->toArray());
        $this->referralCodes->activateForCompletedBooking($booking);
    }

    public function assertSeatsAvailable(Schedule $schedule, array $seats): void
    {
        $capacity = (int) $schedule->vehicle->capacity;

        foreach ($seats as $seat) {
            if (! is_numeric($seat) || (int) $seat < 1 || (int) $seat > $capacity) {
                throw new InvalidArgumentException('Số ghế không hợp lệ: ' . $seat);
            }
        }

        $taken = SeatReservation::query()
            ->where('schedule_id', $schedule->id)
            ->whereIn('seat_number', array_map(fn ($seat): string => (string) $seat, $seats))
            ->whereIn('status', ['held', 'booked'])
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->pluck('seat_number')
            ->all();

        if ($taken !== []) {
            throw new InvalidArgumentException(
                'Ghế ' . implode(', ', $taken) . ' đã có người đặt. Vui lòng chọn ghế khác hoặc đợi vài phút nếu bạn vừa đặt dở.'
            );
        }
    }

    public function findReusablePendingBooking(
        Schedule $schedule,
        string $contactPhone,
        array $seatNumbers,
    ): ?Booking {
        $targetSeats = collect($seatNumbers)->map(fn ($seat): string => (string) $seat)->sort()->values()->all();

        return Booking::query()
            ->where('schedule_id', $schedule->id)
            ->where('booking_status', 'pending')
            ->where(fn ($q) => $q->whereNull('hold_expires_at')->orWhere('hold_expires_at', '>', now()))
            ->get()
            ->first(function (Booking $booking) use ($contactPhone, $targetSeats): bool {
                if (! $booking->matchesContactPhone($contactPhone)) {
                    return false;
                }

                $seats = collect($booking->seat_numbers)->map(fn ($seat): string => (string) $seat)->sort()->values()->all();

                return $seats === $targetSeats;
            });
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
        string $tripType = 'one_way',
        string $bookingMode = 'shared',
        ?int $appliedReferralCodeId = null,
        string $passengerGender = 'male',
        ?int $passengerAge = null,
        ?float $pickupLat = null,
        ?float $pickupLng = null,
    ): Booking {
        $booking->loadMissing('schedule.route');
        $seatCount = count($booking->seat_numbers ?? []);
        $totalPrice = $this->pricing->bookingTotal(
            $booking->schedule,
            $tripType,
            $bookingMode,
            max($seatCount, 1),
            $pickupAddress,
            $dropoffAddress,
        );
        $appliedId = $appliedReferralCodeId;
        $totalPrice = $this->applyReferralToTotal($totalPrice, $booking->contact_phone, $appliedId);

        $booking->update([
            'passenger_name'   => trim($passengerName),
            'passenger_gender' => $passengerGender === 'female' ? 'female' : 'male',
            'passenger_age'    => $passengerAge,
            'pickup_address'   => $pickupAddress,
            'pickup_detail'   => $pickupDetail ? trim($pickupDetail) : null,
            'pickup_lat'      => $pickupLat,
            'pickup_lng'      => $pickupLng,
            'pickup_time'     => $pickupTime ? \App\Support\DepartureTimeDisplay::storageValue($pickupTime) : null,
            'dropoff_address' => $dropoffAddress,
            'dropoff_detail'  => $dropoffDetail ? trim($dropoffDetail) : null,
            'notes'           => $notes,
            'booking_mode'    => $bookingMode,
            'trip_type'       => $tripType,
            'total_price'     => $totalPrice,
            'hold_expires_at' => null,
        ]);

        if ($appliedId !== null) {
            $booking->update(['applied_referral_code_id' => $appliedId]);
        } elseif ($appliedReferralCodeId !== null) {
            $booking->update(['applied_referral_code_id' => null]);
        }

        $booking->seatReservations()
            ->where('status', 'held')
            ->update(['expires_at' => null]);

        $this->referralCodes->ensureForBooking($booking);

        $booking = $booking->fresh(['schedule']);
        $this->driverRequests->autoAssignForBooking($booking->fresh(['schedule.route', 'schedule.vehicle']));

        return $booking;
    }

    public function syncScheduleAvailability(Schedule $schedule): void
    {
        if (! $schedule) {
            return;
        }

        $schedule->loadMissing(['vehicle', 'seatReservations']);

        $active = $schedule->seatReservations
            ->filter(fn ($r) => in_array($r->status, ['held', 'booked'], true)
                && (! $r->expires_at || $r->expires_at->isFuture()))
            ->count();

        $schedule->forceFill(['available_seats' => max((int) $schedule->vehicle->capacity - $active, 0)])->save();
    }

    public function finalizeTripsAfterScheduleEnd(Schedule $schedule): void
    {
        Booking::query()
            ->where('schedule_id', $schedule->id)
            ->where('booking_status', 'confirmed')
            ->where('trip_status', 'confirmed')
            ->each(function (Booking $booking): void {
                $before = $booking->toArray();

                $booking->update([
                    'trip_status'  => 'awaiting_completion',
                    'completed_at' => now(),
                ]);

                $this->audit($booking, null, 'trip_auto_finalized', $before, $booking->fresh()->toArray());
            });
    }

    public function expireStaleBookings(): void
    {
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
            return round($subtotal, 2);
        }

        $referral = ReferralCode::query()->find($appliedReferralCodeId);
        if (! $referral || ! $this->referralCodes->shouldAttributeBooking($referral, $contactPhone)) {
            $appliedReferralCodeId = null;

            return round($subtotal, 2);
        }

        $percent = $this->referralCodes->customerDiscountPercent($referral, $contactPhone);
        if ($percent <= 0) {
            return round($subtotal, 2);
        }

        return $this->referralCodes->applyDiscount($subtotal, $percent);
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
