<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingAudit;
use App\Models\ReferralCode;
use App\Models\Schedule;
use App\Models\ScheduleTemplate;
use App\Support\PlatformFees;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Phần "tạo booking mới" — tách ra từ BookingWorkflowService (God Service):
 * dựng lịch trình, tính giá, áp dụng referral, tạo/refresh bản ghi Booking.
 * Không đụng tới hủy/hoàn thành/gán lại — các nhóm đó có gọi chéo qua lại
 * với nhau nên vẫn giữ trong BookingWorkflowService.
 */
class BookingCreationService
{
    public function __construct(
        private readonly ScheduleLifecycleService $scheduleLifecycle,
        private readonly TripPricingService $pricing,
        private readonly ReferralCodeService $referralCodes,
        private readonly DuplicateBookingService $duplicateBookings,
        private readonly DriverAvailabilityService $driverAvailability,
        private readonly DriverTripRequestService $driverRequests,
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
        $template->loadMissing(['route', 'vehicle']);

        $pickup = $pickupAddress ?: $template->route?->departure;
        $dropoff = $dropoffAddress ?: $template->route?->destination;

        if (! $pickup || ! $dropoff) {
            throw new InvalidArgumentException('Vui lòng chọn điểm đi và điểm đến.');
        }

        $route = app(BookingRouteService::class)->resolve(
            $pickup,
            $dropoff,
            $pickupLat,
            $pickupLng,
            $dropoffLat,
            $dropoffLng,
        );

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
            if ($autoMatchDriver) {
                $this->driverRequests->autoAssignForBooking($booking->fresh(['schedule.route', 'schedule.vehicle']));
            } else {
                try {
                    $this->driverRequests->assignCatalogBooking($booking->fresh(['schedule.route', 'schedule.vehicle']), $template);
                } catch (InvalidArgumentException) {
                }
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

        if ($autoMatchDriver) {
            $this->driverRequests->autoAssignForBooking($booking->fresh(['schedule.route', 'schedule.vehicle']));
        } else {
            try {
                $this->driverRequests->assignCatalogBooking($booking->fresh(['schedule.route', 'schedule.vehicle']), $template);
            } catch (InvalidArgumentException) {
                // Đơn vẫn hiển thị admin — tài xế có thể nhận thủ công.
            }
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

            return $booking;
        });

        return $booking;
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

    private function applyReferralToTotal(float $subtotal, string $contactPhone, ?int &$appliedReferralCodeId): float
    {
        if (! $appliedReferralCodeId) {
            return (float) PlatformFees::roundDisplayPrice($subtotal);
        }

        $referral = ReferralCode::query()->find($appliedReferralCodeId);
        if (! $referral || ! $referral->isUsable()) {
            $appliedReferralCodeId = null;

            return (float) PlatformFees::roundDisplayPrice($subtotal);
        }

        if ($referral->type === ReferralCode::TYPE_REFERRER) {
            if (! $this->referralCodes->shouldAttributeBooking($referral, $contactPhone)) {
                $appliedReferralCodeId = null;

                return (float) PlatformFees::roundDisplayPrice($subtotal);
            }

            $percent = $this->referralCodes->customerDiscountPercent($referral, $contactPhone);
            if ($percent > 0) {
                return $this->referralCodes->applyDiscount($subtotal, $percent);
            }

            return (float) PlatformFees::roundDisplayPrice($subtotal);
        }

        $appliedReferralCodeId = null;

        return (float) PlatformFees::roundDisplayPrice($subtotal);
    }

    private function syncScheduleAvailability(Schedule $schedule): void
    {
        $driverUserId = (int) ($schedule->driver_id ?? 0);

        if ($driverUserId <= 0) {
            return;
        }

        $this->driverAvailability->syncAfterTripCompleted($driverUserId);
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
