<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\DriverProfile;
use App\Models\DriverTripRequest;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Phần "quản lý gán tay TX" — tách ra từ DriverTripRequestService (God Service):
 * admin/operator nhập mã tài xế để mời hoặc đổi tài xế cho một chuyến/vé cụ thể.
 * Khác với engine auto-assign (tự dò tài xế gần nhất), nhóm này luôn do người
 * vận hành chủ động chọn tài xế. DriverTripRequestService vẫn giữ các method cũ
 * (delegate sang đây) để không phải sửa lại các nơi đang gọi.
 */
class DriverManualAssignmentService
{
    public function __construct(
        private readonly DriverAvailabilityService $availability,
        private readonly DriverWalletService $wallets,
    ) {
    }

    private function assertNoAssignmentConflict(
        int $driverUserId,
        Schedule $schedule,
        ?Booking $booking = null,
    ): void {
        $message = $this->availability->assignmentConflictMessage(
            $driverUserId,
            $schedule,
            $booking,
            (int) $schedule->id,
        );

        if ($message) {
            throw new InvalidArgumentException($message);
        }
    }

    /** Quản lý mời TX thủ công — không áp dụng danh sách loại auto-assign. */
    public function requestDriver(Schedule $schedule, string $driverCode, string $contactPhone): DriverTripRequest
    {
        app(DriverTripRequestService::class)->expireStale();

        $contactPhone = trim($contactPhone);
        if ($contactPhone === '') {
            throw new InvalidArgumentException('Thiếu số điện thoại liên hệ.');
        }

        if (! in_array($schedule->status, ['scheduled', 'running'], true)) {
            throw new InvalidArgumentException('Chuyến không còn mở để mời tài xế.');
        }

        $schedule->loadMissing('route');

        $profile = DriverProfile::query()
            ->operational()
            ->where('driver_code', strtoupper(trim($driverCode)))
            ->with('user')
            ->first();

        if (! $profile) {
            throw new InvalidArgumentException('Không tìm thấy tài xế với mã này.');
        }

        $this->assertManualAssignDriverAllowed($schedule, $profile, $contactPhone);

        $alreadyOnTrip = $schedule->driver_id && (int) $schedule->driver_id === (int) $profile->user_id;

        if (! $alreadyOnTrip) {
            $this->assertNoAssignmentConflict((int) $profile->user_id, $schedule);
        }

        if (! $alreadyOnTrip) {
            $blockReason = $this->wallets->acceptBlockReason($profile);
            if ($blockReason) {
                throw new InvalidArgumentException('Tài xế không thể nhận cuốc: ' . $blockReason);
            }
        }

        if ($schedule->driver_id) {
            if ((int) $schedule->driver_id === (int) $profile->user_id) {
                if ($schedule->bookedSeatsCount() >= $schedule->capacity()) {
                    throw new InvalidArgumentException('Chuyến này đã full ghế.');
                }

                return tap(
                    DriverTripRequest::query()->firstOrCreate(
                        [
                            'schedule_id'   => $schedule->id,
                            'contact_phone' => $contactPhone,
                            'driver_id'     => $profile->user_id,
                        ],
                        [
                            'status'       => 'accepted',
                            'responded_at' => now(),
                            'expires_at'   => null,
                        ],
                    ),
                    function () use ($schedule, $contactPhone, $profile): void {
                        app(DriverCuocOfferHideService::class)->clearForOffer(
                            (int) $profile->user_id,
                            $schedule,
                            $contactPhone,
                        );
                        $this->stampAssignedDriverOnBooking($schedule, $contactPhone, (int) $profile->user_id);
                    },
                );
            }

            throw new InvalidArgumentException('Chuyến này đã có tài xế nhận. Chỉ có thể chọn tài xế đang phục vụ chuyến này.');
        }

        $existing = $this->replaceStaleManualPendingOffers($schedule, $contactPhone, (int) $profile->user_id);
        if ($existing) {
            app(DriverCuocOfferHideService::class)->clearHidesForContact($schedule, $contactPhone);

            return $existing;
        }

        $booking = Booking::query()
            ->where('schedule_id', $schedule->id)
            ->where('contact_phone', $contactPhone)
            ->whereNotIn('booking_status', ['cancelled', 'rejected'])
            ->where('trip_status', '!=', 'completed')
            ->latest('id')
            ->first();

        if ($booking?->operator_confirmed_at) {
            app(DriverLatePickupService::class)->assertOperatorCanReachPickup($profile, $booking, $schedule);
        }

        app(DriverCuocOfferHideService::class)->clearHidesForContact(
            $schedule,
            $contactPhone,
        );

        $request = DriverTripRequest::query()->create([
            'schedule_id'   => $schedule->id,
            'contact_phone' => $contactPhone,
            'driver_id'     => $profile->user_id,
            'status'        => 'pending',
            'expires_at'    => $booking
                ? app(DriverTripRequestService::class)->resolveInviteExpiresAt($booking)
                : now()->addMinutes(DriverTripRequestService::OPERATOR_INVITE_ACCEPT_MINUTES),
        ]);

        $this->refreshCustomerSearchForContact($schedule, $contactPhone);

        return $request;
    }

    private function refreshCustomerSearchForContact(Schedule $schedule, string $contactPhone): void
    {
        $booking = Booking::query()
            ->where('schedule_id', $schedule->id)
            ->where('contact_phone', $contactPhone)
            ->whereNotIn('booking_status', ['cancelled', 'rejected'])
            ->where('trip_status', '!=', 'completed')
            ->latest('id')
            ->first();

        if ($booking) {
            app(DriverTripRequestService::class)->refreshCustomerSearchDeadline($booking);
        }
    }

    public function reassignScheduleDriver(Schedule $schedule, string $newDriverCode, int $operatorUserId): void
    {
        $schedule->loadMissing(['bookings']);
        $bookings = $schedule->driverRelevantBookings()
            ->filter(fn (Booking $booking): bool => ! in_array($booking->trip_status, ['completed'], true));

        if ($bookings->isEmpty()) {
            throw new InvalidArgumentException('Chuyến không còn vé cần phân công.');
        }

        foreach ($bookings as $booking) {
            $this->reassignBookingDriver($booking, $newDriverCode, $operatorUserId);
        }
    }

    /** Admin gán / gán lại TX — tạo chuyến mới cho khách, mời TX trong 15 phút. */
    public function assignBookingDriver(Booking $booking, string $driverCode, int $operatorUserId): void
    {
        DB::transaction(function () use ($booking, $driverCode, $operatorUserId): void {
            $locked = Booking::query()
                ->with(['schedule.route', 'schedule.vehicle', 'schedule.template'])
                ->lockForUpdate()
                ->findOrFail($booking->id);

            if ($locked->passengerPickedUp()) {
                throw new InvalidArgumentException('Tài xế đã đón khách — không thể gán lại.');
            }

            if ($locked->schedule) {
                Schedule::query()->lockForUpdate()->find($locked->schedule->id);
            }

            $needsFreshTrip = $locked->driverAcceptanceState() === 'accepted'
                || $locked->needs_operator_help_at
                || $locked->isPastPickupTime();

            if ($needsFreshTrip) {
                $this->reassignBookingDriver($locked, $driverCode, $operatorUserId);

                return;
            }

            $this->requestDriver(
                $locked->schedule->fresh(['route']),
                $driverCode,
                (string) $locked->contact_phone,
            );
            $locked = $locked->fresh();
            if (! $locked->operator_confirmed_at) {
                $locked->update(['operator_confirmed_at' => now()]);
                $locked = $locked->fresh();
            }
            app(DriverTripRequestService::class)->refreshCustomerSearchDeadline($locked);
            $this->acknowledgeOperatorManualAssign($locked);
        });
    }

    private function acknowledgeOperatorManualAssign(Booking $booking): void
    {
        if (! $booking->needs_operator_help_at && $booking->operator_help_reason === null) {
            return;
        }

        $booking->update([
            'needs_operator_help_at' => null,
            'operator_help_reason'   => null,
            'operator_confirmed_at'    => $booking->operator_confirmed_at ?? now(),
        ]);
    }

    public function reassignBookingDriver(Booking $booking, string $newDriverCode, int $operatorUserId): void
    {
        app(DriverTripRequestService::class)->expireStale();

        $booking->loadMissing(['schedule.route', 'schedule.vehicle', 'schedule.template']);
        $oldSchedule = $booking->schedule;

        if (! $oldSchedule) {
            throw new InvalidArgumentException('Không tìm thấy chuyến của vé.');
        }

        if ((int) $oldSchedule->vehicle->operator_id !== $operatorUserId) {
            $operator = User::query()->find($operatorUserId);
            if (! $operator || $operator->role !== 'admin') {
                throw new InvalidArgumentException('Không có quyền phân công chuyến này.');
            }
        }

        if ($booking->passengerPickedUp()) {
            throw new InvalidArgumentException('Tài xế đã đón khách — không thể đổi tài xế.');
        }

        $profile = DriverProfile::query()
            ->operational()
            ->where('driver_code', strtoupper(trim($newDriverCode)))
            ->with('user')
            ->first();

        if (! $profile) {
            throw new InvalidArgumentException('Không tìm thấy tài xế với mã này.');
        }

        $workflow = app(BookingWorkflowService::class);
        $newSchedule = $workflow->relocateBookingForReassign($booking->fresh(['schedule.template']));

        $booking = $booking->fresh(['schedule.route', 'schedule.vehicle', 'schedule.template']);
        app(DriverLatePickupService::class)->assertOperatorCanReachPickup($profile, $booking, $newSchedule);

        $this->assertNoAssignmentConflict((int) $profile->user_id, $newSchedule, $booking);

        $this->requestDriver(
            $newSchedule->fresh(['route']),
            $newDriverCode,
            (string) $booking->contact_phone,
        );

        $booking = $booking->fresh();
        app(DriverTripRequestService::class)->refreshCustomerSearchDeadline($booking);
        $this->acknowledgeOperatorManualAssign($booking);
    }

    private function assertManualAssignDriverAllowed(
        Schedule $schedule,
        DriverProfile $profile,
        string $contactPhone,
    ): void {
        if ($schedule->driver_id && (int) $schedule->driver_id !== (int) $profile->user_id) {
            throw new InvalidArgumentException(
                'Chuyến này đã có tài xế nhận. Chỉ có thể chọn tài xế đang phục vụ chuyến này.'
            );
        }

        $otherPassengerPending = DriverTripRequest::query()
            ->where('schedule_id', $schedule->id)
            ->where('status', 'pending')
            ->where('contact_phone', '!=', $contactPhone)
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->latest('id')
            ->first();

        if ($otherPassengerPending
            && (int) $otherPassengerPending->driver_id !== (int) $profile->user_id) {
            $otherDriver = DriverProfile::query()
                ->with('user')
                ->where('user_id', $otherPassengerPending->driver_id)
                ->first();

            throw new InvalidArgumentException(
                'Chuyến ghép đang chờ tài xế '
                . ($otherDriver?->user?->name ?? 'khác')
                . ' — phải dùng cùng tài xế cho mọi khách trên chuyến.'
            );
        }
    }

    /** Ghép thêm khách vào chuyến tài xế đang phục vụ — schedule đã có driver_id. */
    private function stampAssignedDriverOnBooking(Schedule $schedule, string $contactPhone, int $driverUserId): void
    {
        $booking = Booking::query()
            ->where('schedule_id', $schedule->id)
            ->where('contact_phone', $contactPhone)
            ->latest('id')
            ->first();

        $booking?->stampAssignedDriver($driverUserId);
    }

    /** Gỡ offer pending cũ (TX catalog / auto-assign) trước khi admin mời TX mới. */
    private function replaceStaleManualPendingOffers(
        Schedule $schedule,
        string $contactPhone,
        int $targetDriverUserId,
    ): ?DriverTripRequest {
        $existingForTarget = null;

        DriverTripRequest::query()
            ->where('schedule_id', $schedule->id)
            ->where('contact_phone', $contactPhone)
            ->where('status', 'pending')
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->orderBy('id')
            ->get()
            ->each(function (DriverTripRequest $pending) use ($targetDriverUserId, &$existingForTarget): void {
                if ((int) $pending->driver_id === $targetDriverUserId) {
                    $existingForTarget = $pending;

                    return;
                }

                $pending->update([
                    'status'       => 'cancelled',
                    'responded_at' => now(),
                ]);
                $this->notifyDriverOfferRevoked($pending->fresh(['schedule.route']));
            });

        return $existingForTarget;
    }

    private function notifyDriverOfferRevoked(DriverTripRequest $request): void
    {
        try {
            app(PushNotificationService::class)->onDriverTripRequestExpired($request);
        } catch (\Throwable) {
        }
    }
}
