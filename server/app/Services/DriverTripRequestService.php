<?php

namespace App\Services;

use App\Support\VehicleDisplay;
use App\Services\DriverCuocOfferHideService;
use App\Models\Booking;
use App\Models\DriverProfile;
use App\Models\DriverTripRequest;
use App\Models\Schedule;
use App\Models\ScheduleTemplate;
use App\Models\TripLedger;
use App\Support\ProvinceCenters;
use App\Support\ProvinceResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DriverTripRequestService
{
    public const ACCEPT_WINDOW_DAYS = 1;

    /** Tài xế tự động được mời — phải nhận trong thời gian này (Đặt Ngay). */
    public const AUTO_ASSIGN_ACCEPT_MINUTES = 1;

    /** Tài xế tự động được mời — Đặt Lịch (> 30 phút tới giờ đón). */
    public const SCHEDULED_ASSIGN_ACCEPT_MINUTES = 10;

    /** Đặt Ngay: sau mốc này treo admin (không hủy đơn). */
    public const ON_DEMAND_SEARCH_MAX_MINUTES = 10;

    /** Đặt Lịch: ngừng tìm TX khi còn X phút tới giờ đón — không có TX thì hủy. */
    public const SCHEDULED_SEARCH_STOP_MINUTES_BEFORE_PICKUP = 30;

    /** Không nhận cuốc tự động — chỉ xoay sang tài xế khác, không tắt Hoạt động. */
    public const AUTO_ASSIGN_MISS_OFF_DUTY_THRESHOLD = 0;

    /** Quản lý mời tài xế thủ công. */
    public const OPERATOR_INVITE_ACCEPT_MINUTES = 15;

    /** Hết hạn nhận cuốc = giờ đón − 15 phút (không hủy đơn). */
    public const PICKUP_INVITE_LEAD_MINUTES = 15;

    /** Sau khi tài xế từ chối — không gán lại cùng SĐT khách trong khoảng này. */
    public const DECLINE_CONTACT_COOLDOWN_MINUTES = 60;

    public function __construct(
        private readonly DriverAvailabilityService $availability,
        private readonly DriverWalletService $wallets,
        private readonly TripLedgerService $tripLedger,
        private readonly DriverProximityService $proximity,
        private readonly DriverMovementConfirmService $movementConfirm,
        private readonly DriverDiscoveryService $discovery,
        private readonly DriverManualAssignmentService $manualAssign,
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

    /** Cùng điều kiện với {@see DriverProximityService::pickBest()} — tránh chọn được TX nhưng không gửi được cuốc. */
    private function assertDriverEligibleForAutoAssign(
        DriverProfile $driver,
        Schedule $schedule,
        Booking $booking,
    ): void {
        if (! $driver->isApproved()) {
            throw new InvalidArgumentException('Tài xế không thể nhận cuốc.');
        }

        if (! $this->availability->catalogBookingButtonState($driver)['is_online']) {
            throw new InvalidArgumentException('Tài xế không sẵn sàng.');
        }

        if (! $this->wallets->canAcceptTrips($driver)) {
            throw new InvalidArgumentException($this->wallets->acceptBlockReason($driver) ?: 'Tài xế không thể nhận cuốc.');
        }

        if ($this->availability->hasTripTimeConflict((int) $driver->user_id, $schedule, $booking)) {
            throw new InvalidArgumentException('Tài xế trùng giờ với chuyến khác.');
        }

        $pickup = $this->proximity->pickupCoordinates($booking);
        if ($pickup === null || ! $this->proximity->withinAssignRadius($driver, $booking, $pickup)) {
            throw new InvalidArgumentException('Tài xế ngoài phạm vi.');
        }
    }

    public function expireStale(): void
    {
        $this->revokePendingOffersWithoutActiveBooking();

        DriverTripRequest::query()
            ->where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->with(['schedule.route', 'schedule.vehicle'])
            ->each(function (DriverTripRequest $request): void {
                $profile = DriverProfile::query()->where('user_id', $request->driver_id)->first();
                $driverUnavailable = ! $profile
                    || ($profile->availability_status ?? 'off_duty') !== 'available';

                if ($driverUnavailable) {
                    $request->update([
                        // TODO (Fix Stuck Offer UI): Request quá hạn nhưng TX đã off-duty/không còn hợp lệ thì vẫn phải thu hồi UI ngay.
                        'status'       => 'cancelled',
                        // TODO (Fix Stuck Offer UI): Ghi responded_at để cooldown/reassign hoạt động đúng sau khi thu hồi offer.
                        'responded_at' => now(),
                    ]);
                    // TODO (Fix Stuck Offer UI): Bắn tín hiệu thu hồi offer để app tài xế ẩn popup/card hết hạn ngay.
                    $this->notifyDriverOfferRevoked($request->fresh(['schedule.route']));

                    try {
                        $booking = $this->bookingForRequest($request)->fresh(['schedule.route', 'schedule.vehicle']);
                        if (! $booking->schedule?->driver_id && ! $booking->hasDriverAccepted()) {
                            app(DriverCuocOfferHideService::class)->recordMissedOffer($request->fresh());
                            $this->tryRotateAfterAssignMiss($booking, (int) $request->driver_id);
                        }
                    } catch (\Throwable) {
                    }

                    return;
                }

                $wasAutoAssign = $this->wasAutoAssignRequest($request);
                $request->update([
                    // TODO (Fix Stuck Offer UI): Pending quá hạn phải chuyển đúng sang expired để không còn hiện trên app TX cũ.
                    'status'       => 'expired',
                    // TODO (Fix Stuck Offer UI): Lưu thời điểm timeout để cooldown/reassign dùng lại ngay.
                    'responded_at' => now(),
                ]);
                // TODO (Fix Stuck Offer UI): Bắn tín hiệu thu hồi offer để app tài xế đang mở tự ẩn card hết hạn.
                $this->notifyDriverOfferRevoked($request->fresh(['schedule.route']));
                if ($wasAutoAssign) {
                    $this->recordAutoAssignMiss((int) $request->driver_id);
                }

                try {
                    $booking = $this->bookingForRequest($request)->fresh(['schedule.route', 'schedule.vehicle']);
                } catch (\Throwable) {
                    return;
                }

                if ($booking->schedule?->driver_id || $booking->hasDriverAccepted()) {
                    return;
                }

                app(DriverCuocOfferHideService::class)->recordMissedOffer($request->fresh());
                // TODO (Fix Flow): xoay TX khác — không gọi flagOperatorInviteExpired ở đây.
                $this->tryRotateAfterAssignMiss($booking, (int) $request->driver_id);
            });

        $this->hangOverdueDriverSearches();

        app(BookingWorkflowService::class)->expireScheduledSearchWithoutDriver();
        app(BookingWorkflowService::class)->expirePastPickupWithoutDriver();

        // TODO (Auto Reassign Late Trip): Gộp cảnh báo trễ → gỡ TX + auto-assign (thay processMovementAlerts).
        app(DriverLatePickupService::class)->processLateAlertAutoReassign();
        app(DriverLatePickupService::class)->processAssignedPastPickup();
        app(DriverLatePickupService::class)->expireOverdueContinue();
        app(DriverLatePickupService::class)->processScheduledDepartReminders();
        app(DriverLatePickupService::class)->processPickupPushReminders();
    }

    // TODO (Fix Flow): Hết hạn nhận cuốc auto-assign theo loại cuốc (1 phút / 10 phút).
    public function acceptExpiresAtForBooking(Booking $booking): \Carbon\Carbon
    {
        $minutes = $booking->isOnDemandPickup()
            ? self::AUTO_ASSIGN_ACCEPT_MINUTES
            : self::SCHEDULED_ASSIGN_ACCEPT_MINUTES;

        return now()->addMinutes($minutes);
    }

    // TODO (Fix Flow): Đặt Lịch — đã tới mốc T-30 mà vẫn chưa có TX.
    public function hasReachedScheduledSearchStop(Booking $booking): bool
    {
        $booking->loadMissing('schedule');

        if ($booking->isOnDemandPickup() || $booking->schedule?->driver_id) {
            return false;
        }

        if (in_array($booking->booking_status, ['cancelled', 'rejected'], true)
            || $booking->trip_status === 'completed') {
            return false;
        }

        $pickupAt = $booking->operationalPickupAt();
        if (! $pickupAt instanceof \Carbon\Carbon) {
            return false;
        }

        return now()->gte(
            $pickupAt->copy()->subMinutes(self::SCHEDULED_SEARCH_STOP_MINUTES_BEFORE_PICKUP),
        );
    }

    // TODO (Fix Flow): Sau timeout/từ chối — thử TX tiếp theo hoặc treo/hủy theo deadline tìm kiếm.
    private function tryRotateAfterAssignMiss(Booking $booking, int $formerDriverUserId = 0): void
    {
        $booking = $booking->fresh(['schedule.route', 'schedule.vehicle', 'schedule.template']);

        if ($booking->needs_operator_help_at || $booking->schedule?->driver_id) {
            return;
        }

        if ($this->hasReachedScheduledSearchStop($booking)) {
            app(BookingWorkflowService::class)->cancelScheduledSearchTimeout($booking);

            return;
        }

        if ($this->hasExceededCustomerSearchDeadline($booking)) {
            $this->hangDriverSearchIfOverdue($booking);

            return;
        }

        if ($formerDriverUserId > 0) {
            $this->tryReassignAfterDriverRelease($booking, $formerDriverUserId);

            return;
        }

        $this->autoAssignForBooking($booking);
    }

    /** Giờ hết hạn nhận cuốc = giờ đón − 15 phút. */
    public function inviteExpiresAtForBooking(Booking $booking): ?\Carbon\Carbon
    {
        $pickupAt = $booking->operationalPickupAt();

        return $pickupAt?->copy()->subMinutes(self::PICKUP_INVITE_LEAD_MINUTES);
    }

    public function resolveInviteExpiresAt(Booking $booking): \Carbon\Carbon
    {
        $expiresAt = $this->inviteExpiresAtForBooking($booking);

        if (! $expiresAt || $expiresAt->lte(now())) {
            return now();
        }

        return $expiresAt;
    }

    /** @return Collection<int, DriverProfile> */
    public function suggestDrivers(?Schedule $schedule = null): Collection
    {
        $query = DriverProfile::query()
            ->operational()
            ->with(['user', 'operator'])
            ->orderByDesc('experience_years');

        return $query->get();
    }

    public function availabilityMeta(string $status): array
    {
        return match ($status) {
            'available' => ['label' => 'Sẵn sàng', 'color' => 'success', 'icon' => '🟢', 'suggested' => true],
            'on_trip'   => ['label' => 'Đang chạy', 'color' => 'primary', 'icon' => '🔵', 'suggested' => false],
            default     => ['label' => 'Nghỉ / Bận', 'color' => 'secondary', 'icon' => '⚫', 'suggested' => false],
        };
    }

    /** Quản lý mời TX thủ công — không áp dụng danh sách loại auto-assign. */
    public function requestDriver(Schedule $schedule, string $driverCode, string $contactPhone): DriverTripRequest
    {
        return $this->manualAssign->requestDriver($schedule, $driverCode, $contactPhone);
    }

    /** Đánh dấu đơn chờ tài xế chủ động dò — không gửi cuốc tự động. */
    public function markBookingAwaitingDriver(Booking $booking): void
    {
        $booking->loadMissing('schedule');

        if (! $booking->schedule
            || $booking->schedule->driver_id
            || in_array($booking->booking_status, ['cancelled', 'rejected'], true)) {
            return;
        }

        if (! $booking->driver_search_started_at) {
            $booking->update([
                'driver_search_started_at' => now(),
                'operator_confirmed_at'    => $booking->operator_confirmed_at ?? now(),
            ]);
        }
    }

    /** Gán tự động tài xế gần nhất — đẩy chuyến thẳng vào tab Chuyến của tài xế. */
    public function autoAssignForBooking(Booking $booking): ?DriverTripRequest
    {
        $this->expireStale();

        $booking->loadMissing(['schedule.route', 'schedule.vehicle']);
        $schedule = $booking->schedule;

        if ($booking->needs_operator_help_at || $this->hasExceededCustomerSearchDeadline($booking)) {
            return null;
        }

        if (! $schedule
            || $schedule->driver_id
            || in_array($booking->booking_status, ['cancelled', 'rejected'], true)
            || $booking->isOperatorDismissed()) {
            return null;
        }

        if ($this->proximity->pickupCoordinates($booking) === null) {
            return null;
        }

        $contactPhone = (string) $booking->contact_phone;
        $exclude = $this->assignmentExcludeDriverIds($schedule, $contactPhone);
        $this->cancelRecoverablePendingOffers($schedule, $contactPhone, $exclude);

        if ($this->hasEligiblePendingOffer($schedule, $contactPhone, $exclude)) {
            return DriverTripRequest::query()
                ->where('schedule_id', $schedule->id)
                ->where('contact_phone', $contactPhone)
                ->where('status', 'pending')
                ->where(function ($query): void {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->when(
                    $exclude->isNotEmpty(),
                    fn ($query) => $query->whereNotIn('driver_id', $exclude->all()),
                )
                ->latest('id')
                ->first();
        }

        if (! $booking->driver_search_started_at) {
            $booking->update([
                'driver_search_started_at' => now(),
                'operator_confirmed_at'    => $booking->operator_confirmed_at ?? now(),
            ]);
        }

        $assigned = $this->autoAssignPass($schedule, $booking, $exclude);
        if ($assigned) {
            return $assigned;
        }

        if (! $this->proximity->pickBest($schedule, $booking, $exclude, true)) {
            $this->shouldDeferDriverSearchForLocation($schedule, $booking);
        }

        return null;
    }

    /** Khách chọn tài xế từ catalog — gửi cuốc thẳng tới tài xế đó (không dò proximity). */
    public function assignCatalogBooking(
        Booking $booking,
        ScheduleTemplate $template,
        ?Collection $excludeDriverUserIds = null,
    ): ?DriverTripRequest {
        $this->expireStale();

        $template->loadMissing('driver');
        $driverUserId = (int) ($template->driver_id ?? 0);

        if ($driverUserId <= 0) {
            return $this->autoAssignForBooking($booking);
        }

        $booking = $booking->fresh(['schedule.route', 'schedule.vehicle', 'schedule.template']);
        $schedule = $booking->schedule;

        if (! $schedule) {
            return null;
        }

        $contactPhone = (string) $booking->contact_phone;
        $exclude = ($excludeDriverUserIds ?? $this->assignmentExcludeDriverIds(
            $schedule,
            $contactPhone,
        ))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if ($exclude->contains($driverUserId)) {
            return null;
        }

        if ($schedule->driver_id
            || in_array($booking->booking_status, ['cancelled', 'rejected'], true)
            || $booking->isOperatorDismissed()) {
            return null;
        }

        $this->ensureBookingPickupCoordinates($booking);
        $this->markCatalogBookingSearching($booking);
        $this->cancelRecoverablePendingOffers($schedule, $contactPhone, $exclude);

        $existing = DriverTripRequest::query()
            ->where('schedule_id', $schedule->id)
            ->where('contact_phone', $contactPhone)
            ->whereIn('status', ['pending', 'accepted'])
            ->first();

        if ($existing) {
            if ($exclude->contains((int) $existing->driver_id)) {
                $existing->update([
                    'status'       => 'cancelled',
                    'responded_at' => now(),
                ]);
            } else {
                return $existing;
            }
        }

        $driver = DriverProfile::query()
            ->where('user_id', $driverUserId)
            ->with('user')
            ->first();

        if (! $driver?->isApproved()) {
            return null;
        }

        if (! $this->availability->isBookableNow($driver)) {
            return null;
        }

        if ($this->wallets->acceptBlockReason($driver)) {
            return null;
        }

        try {
            return $this->pushCatalogBookingToDriver($schedule, $booking, $driver);
        } catch (InvalidArgumentException) {
            return $this->pushCatalogPendingRequest($schedule, $booking, $driver);
        }
    }

    private function markCatalogBookingSearching(Booking $booking): void
    {
        if ($booking->driver_search_started_at && $booking->operator_confirmed_at) {
            return;
        }

        $booking->update([
            'driver_search_started_at' => $booking->driver_search_started_at ?? now(),
            'operator_confirmed_at'    => $booking->operator_confirmed_at ?? now(),
        ]);
    }

    private function ensureBookingPickupCoordinates(Booking $booking): void
    {
        if ($booking->pickup_lat !== null && $booking->pickup_lng !== null) {
            return;
        }

        $coords = ProvinceCenters::forProvince(ProvinceResolver::fromBooking($booking));

        if (! $coords) {
            return;
        }

        $booking->update([
            'pickup_lat' => $coords['lat'],
            'pickup_lng' => $coords['lng'],
        ]);
        $booking->pickup_lat = $coords['lat'];
        $booking->pickup_lng = $coords['lng'];
    }

    private function pushCatalogBookingToDriver(Schedule $schedule, Booking $booking, DriverProfile $driver): DriverTripRequest
    {
        $contactPhone = (string) $booking->contact_phone;

        return DB::transaction(function () use ($schedule, $booking, $driver, $contactPhone): DriverTripRequest {
            $schedule = Schedule::query()->lockForUpdate()->findOrFail($schedule->id);

            if ($schedule->driver_id) {
                throw new InvalidArgumentException('Chuyến đã có tài xế.');
            }

            $driver->loadMissing('user');

            $blockReason = $this->wallets->acceptBlockReason($driver);
            if ($blockReason) {
                throw new InvalidArgumentException($blockReason);
            }

            $this->assertNoAssignmentConflict((int) $driver->user_id, $schedule, $booking);

            DriverTripRequest::query()
                ->where('schedule_id', $schedule->id)
                ->where('status', 'pending')
                ->update([
                    'status'       => 'cancelled',
                    'responded_at' => now(),
                ]);

            $request = DriverTripRequest::query()->create([
                'schedule_id'   => $schedule->id,
                'contact_phone' => $contactPhone,
                'driver_id'     => $driver->user_id,
                'status'        => 'accepted',
                'responded_at'  => now(),
                'expires_at'    => null,
            ]);

            $schedule->update([
                'driver_id'    => $driver->user_id,
                'driver_name'  => $driver->user->name,
                'driver_stage' => Schedule::DRIVER_STAGE_ASSIGNED,
            ]);

            $this->anchorAllBookingsForAcceptedSchedule($schedule->fresh());

            return $request;
        });
    }

    private function pushCatalogPendingRequest(Schedule $schedule, Booking $booking, DriverProfile $driver): DriverTripRequest
    {
        $contactPhone = (string) $booking->contact_phone;

        return DB::transaction(function () use ($schedule, $booking, $driver, $contactPhone): DriverTripRequest {
            $schedule = Schedule::query()->lockForUpdate()->findOrFail($schedule->id);

            if ($schedule->driver_id) {
                throw new InvalidArgumentException('Chuyến đã có tài xế.');
            }

            $blockReason = $this->wallets->acceptBlockReason($driver);
            if ($blockReason) {
                throw new InvalidArgumentException($blockReason);
            }

            $this->assertNoAssignmentConflict((int) $driver->user_id, $schedule, $booking);

            DriverTripRequest::query()
                ->where('schedule_id', $schedule->id)
                ->where('status', 'pending')
                ->update([
                    'status'       => 'cancelled',
                    'responded_at' => now(),
                ]);

            return DriverTripRequest::query()->create([
                'schedule_id'   => $schedule->id,
                'contact_phone' => $contactPhone,
                'driver_id'     => $driver->user_id,
                'status'        => 'pending',
                'expires_at'    => $this->resolveInviteExpiresAt($booking),
            ]);
        });
    }

    /** @param Collection<int, int> $excludeDriverUserIds */
    private function autoAssignPass(Schedule $schedule, Booking $booking, Collection $excludeDriverUserIds): ?DriverTripRequest
    {
        $sessionFailed = collect();

        for ($attempt = 0; $attempt < 15; $attempt++) {
            $exclude = $excludeDriverUserIds
                ->merge($sessionFailed)
                ->unique()
                ->values();

            $driver = $this->proximity->pickBest($schedule, $booking, $exclude, true);

            if (! $driver?->driver_code) {
                return null;
            }

            try {
                return $this->pushBookingToDriver($schedule, $booking, $driver);
            } catch (InvalidArgumentException) {
                $sessionFailed->push((int) $driver->user_id);
            }
        }

        return null;
    }

    /** Thử gán lại các chuyến đang chờ tài xế (sau khi tài xế cập nhật vị trí). */
    public function retryWaitingBookings(): int
    {
        $this->expireStale();

        return $this->retryWaitingBookingsWithoutExpire();
    }

    /** Giống {@see retryWaitingBookings()} nhưng không gọi expireStale — dùng khi đã expire trước đó. */
    public function retryWaitingBookingsWithoutExpire(): int
    {
        $assigned = 0;

        Booking::query()
            ->whereNotIn('booking_status', ['cancelled', 'rejected'])
            ->whereNotNull('pickup_lat')
            ->whereNotNull('pickup_lng')
            ->whereHas('schedule', fn ($q) => $q
                ->whereNull('driver_id')
                ->where('status', 'scheduled')
                ->where('departure_time', '>', now()))
            ->with(['schedule.route', 'schedule.vehicle'])
            ->orderBy('created_at')
            ->each(function (Booking $booking) use (&$assigned): void {
                $booking = $booking->fresh(['schedule.route', 'schedule.vehicle']);

                if ($booking->needs_operator_help_at || $this->hasExceededCustomerSearchDeadline($booking)) {
                    return;
                }

                // Không còn chọn tài xế chỉ định — luôn gán lại theo tài xế gần nhất
                // (schedule.template chỉ còn là dữ liệu giá nội bộ, không phải TX khách chọn).
                if ($this->autoAssignForBooking($booking)) {
                    $assigned++;
                }
            });

        return $assigned;
    }

    /** Sau khi tài xế bật Sẵn sàng + GPS — gỡ chặn tìm tài xế và thử gán lại. */
    public function resumeDriverMatchingAfterAvailability(DriverProfile $profile): int
    {
        if (($profile->availability_status ?? 'off_duty') !== 'available') {
            return 0;
        }

        if (! $this->availability->hasAssignableLocation($profile)) {
            return 0;
        }

        return $this->retryWaitingBookingsWithoutExpire();
    }

    /** Hủy offer pending của TX đã loại — tránh kẹt không gán được TX khác. */
    private function cancelRecoverablePendingOffers(
        Schedule $schedule,
        string $contactPhone,
        Collection $excludeDriverUserIds,
    ): void {
        $phone = trim($contactPhone);
        if ($phone === '' || $excludeDriverUserIds->isEmpty()) {
            return;
        }

        DriverTripRequest::query()
            ->where('schedule_id', $schedule->id)
            ->where('contact_phone', $phone)
            ->where('status', 'pending')
            ->whereIn('driver_id', $excludeDriverUserIds->all())
            ->update([
                'status'       => 'cancelled',
                'responded_at' => now(),
            ]);
    }

    private function hasEligiblePendingOffer(
        Schedule $schedule,
        string $contactPhone,
        Collection $excludeDriverUserIds,
    ): bool {
        $phone = trim($contactPhone);
        if ($phone === '') {
            return false;
        }

        return DriverTripRequest::query()
            ->where('schedule_id', $schedule->id)
            ->where('contact_phone', $phone)
            ->where('status', 'pending')
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->when(
                $excludeDriverUserIds->isNotEmpty(),
                fn ($query) => $query->whereNotIn('driver_id', $excludeDriverUserIds->all()),
            )
            ->exists();
    }

    private function pushBookingToDriver(Schedule $schedule, Booking $booking, DriverProfile $driver): DriverTripRequest
    {
        $contactPhone = (string) $booking->contact_phone;

        return DB::transaction(function () use ($schedule, $booking, $driver, $contactPhone): DriverTripRequest {
            $schedule = Schedule::query()->lockForUpdate()->findOrFail($schedule->id);

            if ($schedule->driver_id) {
                throw new InvalidArgumentException('Chuyến đã có tài xế.');
            }

            $driver->loadMissing('user');

            $this->assertDriverEligibleForAutoAssign($driver, $schedule, $booking);

            $this->assertNoAssignmentConflict((int) $driver->user_id, $schedule, $booking);

            DriverTripRequest::query()
                ->where('schedule_id', $schedule->id)
                ->where('contact_phone', $contactPhone)
                ->where('status', 'pending')
                ->update([
                    'status'       => 'cancelled',
                    'responded_at' => now(),
                ]);

            app(DriverCuocOfferHideService::class)->clearForOffer(
                (int) $driver->user_id,
                $schedule,
                $contactPhone,
            );

            // TODO (Fix Flow): Gửi cuốc chờ TX xác nhận — không gán thẳng schedule.driver_id.
            return DriverTripRequest::query()->create([
                'schedule_id'   => $schedule->id,
                'contact_phone' => $contactPhone,
                'driver_id'     => $driver->user_id,
                'status'        => 'pending',
                'expires_at'    => $this->acceptExpiresAtForBooking($booking),
            ]);
        });
    }

    public function claimBooking(Booking $booking, int $driverUserId): void
    {
        $this->expireStale();

        $profile = DriverProfile::query()
            ->operational()
            ->where('user_id', $driverUserId)
            ->with('user')
            ->firstOrFail();

        $booking->loadMissing(['schedule.route', 'schedule.vehicle']);
        $schedule = $booking->schedule;

        if (! $schedule || $schedule->driver_id) {
            throw new InvalidArgumentException('Chuyến đã có tài xế nhận.');
        }

        if (in_array($booking->booking_status, ['cancelled', 'rejected'], true)) {
            throw new InvalidArgumentException('Đơn không còn hiệu lực.');
        }

        if (($profile->availability_status ?? 'off_duty') !== 'available') {
            throw new InvalidArgumentException('Bật trạng thái Sẵn sàng trước khi nhận cuốc.');
        }

        if (! $profile->hasFreshLocation(DriverProximityService::LOCATION_MAX_AGE_MINUTES)) {
            throw new InvalidArgumentException('Cập nhật vị trí GPS trên bản đồ trước khi nhận cuốc.');
        }

        if (! $this->proximity->withinDiscoveryRadius($profile, $booking)) {
            throw new InvalidArgumentException('Cuốc này ngoài phạm vi của bạn (quá xa điểm đón).');
        }

        $blockReason = $this->wallets->acceptBlockReason($profile);
        if ($blockReason) {
            throw new InvalidArgumentException($blockReason);
        }

        $designated = $schedule->designatedDriverProfile();
        if ($designated && (int) $designated->user_id !== $driverUserId) {
            throw new InvalidArgumentException('Chuyến ghép đã giao cho tài xế khác.');
        }

        $this->assertNoAssignmentConflict($driverUserId, $schedule, $booking);

        if ($this->hasExclusivePendingFromOtherDriver($booking, $driverUserId)) {
            throw new InvalidArgumentException('Cuốc đang chờ tài xế khác xác nhận.');
        }

        DB::transaction(function () use ($booking, $profile): void {
            $schedule = Schedule::query()->lockForUpdate()->findOrFail($booking->schedule_id);

            if ($schedule->driver_id) {
                throw new InvalidArgumentException('Chuyến đã có tài xế nhận.');
            }

            DriverTripRequest::query()
                ->where('schedule_id', $schedule->id)
                ->where('status', 'pending')
                ->update([
                    'status'       => 'cancelled',
                    'responded_at' => now(),
                ]);

            $tripRequest = DriverTripRequest::query()->create([
                'schedule_id'   => $schedule->id,
                'contact_phone' => $booking->contact_phone,
                'driver_id'     => $profile->user_id,
                'status'        => 'pending',
                'expires_at'    => $this->resolveInviteExpiresAt($booking),
            ]);

            $this->accept($tripRequest, (int) $profile->user_id);
        });
    }

    /** @return Collection<int, array<string, mixed>> */
    public function tripCardsForDriver(int $driverUserId): Collection
    {
        return $this->discovery->tripCardsForDriver($driverUserId);
    }

    /** @return Collection<int, array{primary_booking: Booking, schedule: Schedule, passengers: Collection<int, Booking>, distance_km: ?float}> */
    public function discoverOpenTripsForDriver(int $driverUserId): Collection
    {
        return $this->discovery->discoverOpenTripsForDriver($driverUserId);
    }

    private function hasExclusivePendingFromOtherDriver(Booking $booking, int $driverUserId): bool
    {
        return DriverTripRequest::query()
            ->where('schedule_id', $booking->schedule_id)
            ->where('contact_phone', $booking->contact_phone)
            ->where('status', 'pending')
            ->where('driver_id', '!=', $driverUserId)
            ->exists();
    }

    /** Gán thẳng tài xế vào chuyến khi khách đặt xe (gần điểm đón nhất). */
    public function directAssignForBooking(Booking $booking): bool
    {
        $this->expireStale();

        $booking->loadMissing(['schedule.route', 'schedule.vehicle']);
        $schedule = Schedule::query()->find($booking->schedule_id);

        if (! $schedule
            || $schedule->driver_id
            || in_array($booking->booking_status, ['cancelled', 'rejected'], true)) {
            return false;
        }

        if ($this->proximity->pickupCoordinates($booking) === null) {
            return false;
        }

        $contactPhone = (string) ($booking->contact_phone ?? '');
        $exclude = $contactPhone !== ''
            ? $this->assignmentExcludeDriverIds($schedule, $contactPhone)
            : collect();

        return $this->directAssignPass($schedule, $booking, $exclude);
    }

    /** @param Collection<int, int> $excludeDriverUserIds */
    private function directAssignPass(Schedule $schedule, Booking $booking, Collection $excludeDriverUserIds): bool
    {
        $sessionFailed = collect();

        for ($attempt = 0; $attempt < 15; $attempt++) {
            $exclude = $excludeDriverUserIds
                ->merge($sessionFailed)
                ->unique()
                ->values();

            $driver = $this->proximity->pickBest($schedule, $booking, $exclude, true);

            if (! $driver?->user_id) {
                return false;
            }

            if ($this->wallets->acceptBlockReason($driver)) {
                $sessionFailed->push((int) $driver->user_id);

                continue;
            }

            try {
                $this->assignDriverToSchedule($schedule, $driver);

                return true;
            } catch (InvalidArgumentException) {
                $sessionFailed->push((int) $driver->user_id);
            }
        }

        return false;
    }

    /** Treo đơn tìm TX quá 15 phút — báo admin, không hủy; ngừng đẩy cuốc cho tài xế. */
    public function hangOverdueDriverSearches(): int
    {
        $flagged = 0;

        Booking::query()
            ->whereNotIn('booking_status', ['cancelled', 'rejected'])
            ->where('trip_status', '!=', 'completed')
            ->whereHas('schedule', fn ($q) => $q->whereNull('driver_id'))
            ->with('schedule')
            ->orderBy('id')
            ->chunkById(50, function ($bookings) use (&$flagged): void {
                foreach ($bookings as $booking) {
                    if ($this->hangDriverSearchIfOverdue($booking->fresh(['schedule']))) {
                        $flagged++;
                    }
                }
            });

        return $flagged;
    }

    public function hangDriverSearchIfOverdue(Booking $booking): bool
    {
        if (! $this->hasExceededCustomerSearchDeadline($booking)) {
            return false;
        }

        if ($booking->needs_operator_help_at) {
            return false;
        }

        app(BookingWorkflowService::class)->flagOperatorHelpNeeded($booking, 'driver_search_timeout');

        return true;
    }

    public function customerSearchStartedAt(Booking $booking): \Carbon\Carbon
    {
        return $booking->driver_search_started_at ?? $booking->created_at;
    }

    public function hasExceededCustomerSearchDeadline(Booking $booking): bool
    {
        $booking->loadMissing('schedule');

        if ($booking->schedule?->driver_id) {
            return false;
        }

        if (in_array($booking->booking_status, ['cancelled', 'rejected'], true)
            || $booking->trip_status === 'completed') {
            return false;
        }

        // TODO (Fix Flow): Chỉ Đặt Ngay — treo admin sau 10 phút; Đặt Lịch dùng T-30.
        if (! $booking->isOnDemandPickup()) {
            return false;
        }

        return $this->customerSearchStartedAt($booking)
            ->lte(now()->subMinutes(self::ON_DEMAND_SEARCH_MAX_MINUTES));
    }

    public function cancelCustomerSearchIfOverdue(Booking $booking): bool
    {
        return $this->hangDriverSearchIfOverdue($booking);
    }

    /** Chưa có tài xế nào bật GPS — chờ share vị trí. */
    private function shouldDeferDriverSearchForLocation(Schedule $schedule, Booking $booking): bool
    {
        $pickup = $this->proximity->pickupCoordinates($booking);
        if ($pickup === null) {
            return true;
        }

        $awaitingGps = false;
        $inRangeWithGps = false;

        foreach (DriverProfile::query()->operational()->where('availability_status', 'available')->get() as $profile) {
            if (! $profile->isApproved()) {
                continue;
            }

            if (! $this->wallets->canAcceptTrips($profile)) {
                continue;
            }

            if ($this->availability->hasTripTimeConflict((int) $profile->user_id, $schedule, $booking)) {
                continue;
            }

            if (! $profile->hasFreshLocation(DriverProximityService::LOCATION_MAX_AGE_MINUTES)) {
                $awaitingGps = true;

                continue;
            }

            if ($this->proximity->withinAssignRadius($profile, $booking, $pickup)) {
                $inRangeWithGps = true;
            }
        }

        return $awaitingGps && ! $inRangeWithGps;
    }

    public function accept(DriverTripRequest $request, int $driverUserId): void
    {
        $this->expireStale();

        if ($request->driver_id !== $driverUserId) {
            throw new InvalidArgumentException('Không có quyền xử lý yêu cầu này.');
        }

        if (! $request->isPending()) {
            throw new InvalidArgumentException('Yêu cầu không còn hiệu lực.');
        }

        $driver = DriverProfile::query()->where('user_id', $request->driver_id)->firstOrFail();
        $blockReason = $this->wallets->acceptBlockReason($driver);
        if ($blockReason) {
            throw new InvalidArgumentException($blockReason);
        }

        DB::transaction(function () use ($request): void {
            $schedule = Schedule::query()->lockForUpdate()->findOrFail($request->schedule_id);

            if ($schedule->driver_id && $schedule->driver_id !== $request->driver_id) {
                throw new InvalidArgumentException('Chuyến đã được tài xế khác nhận.');
            }

            $driver = DriverProfile::query()->where('user_id', $request->driver_id)->firstOrFail();
            $driverName = $driver->user->name;

            $schedule->update([
                'driver_id'    => $request->driver_id,
                'driver_name'  => $driverName,
                'driver_stage' => Schedule::DRIVER_STAGE_ASSIGNED,
            ]);

            DriverTripRequest::query()
                ->where('schedule_id', $schedule->id)
                ->where('driver_id', $request->driver_id)
                ->where('status', 'pending')
                ->update([
                    'status'       => 'accepted',
                    'responded_at' => now(),
                ]);

            $this->anchorAllBookingsForAcceptedSchedule($schedule->fresh());
        });
    }

    private function assignDriverToSchedule(Schedule $schedule, DriverProfile $driver): void
    {
        DB::transaction(function () use ($schedule, $driver): void {
            $schedule = Schedule::query()->lockForUpdate()->findOrFail($schedule->id);

            if ($schedule->driver_id && (int) $schedule->driver_id !== (int) $driver->user_id) {
                throw new InvalidArgumentException('Chuyến đã được tài xế khác nhận.');
            }

            if ($schedule->driver_id) {
                return;
            }

            $driver->loadMissing('user');
            $schedule->update([
                'driver_id'   => $driver->user_id,
                'driver_name' => $driver->user->name,
                'driver_stage'=> Schedule::DRIVER_STAGE_ASSIGNED,
            ]);

            $this->anchorAllBookingsForAcceptedSchedule($schedule->fresh());
        });
    }

    /** @return Collection<int, array{primary: DriverTripRequest, schedule: Schedule, passengers: Collection<int, Booking>}> */
    public function pendingGroupsForDriver(int $driverUserId): Collection
    {
        return $this->discovery->pendingGroupsForDriver($driverUserId);
    }

    /** Thu hồi cuốc chờ nhận khi khách hủy — khớp SĐT chuẩn hóa, báo app tài xế gỡ card. */
    public function revokePendingOffersForGuestBooking(Booking $booking): void
    {
        $booking->loadMissing('schedule');
        $schedule = $booking->schedule;
        if (! $schedule) {
            return;
        }

        DriverTripRequest::query()
            ->where('schedule_id', $schedule->id)
            ->whereIn('status', ['pending', 'accepted'])
            ->get()
            ->filter(fn (DriverTripRequest $request): bool => $booking->matchesContactPhone((string) $request->contact_phone))
            ->each(function (DriverTripRequest $request): void {
                if ($request->status !== 'pending') {
                    return;
                }

                $request->update([
                    'status'       => 'cancelled',
                    'responded_at' => now(),
                    'expires_at'   => null,
                ]);
                $this->notifyDriverOfferRevoked($request->fresh(['schedule.route']));
            });
    }

    private function revokePendingOffersWithoutActiveBooking(): void
    {
        DriverTripRequest::query()
            ->where('status', 'pending')
            ->with(['schedule.bookings'])
            ->get()
            ->filter(fn (DriverTripRequest $request): bool => ! $request->relatedBooking())
            ->each(function (DriverTripRequest $request): void {
                $request->update([
                    'status'       => 'cancelled',
                    'responded_at' => now(),
                    'expires_at'   => null,
                ]);
                $this->notifyDriverOfferRevoked($request->fresh(['schedule.route']));
            });
    }

    /** Cuốc chờ nhận còn hiển thị trên dashboard — loại cuốc tài xế đã ẩn / bỏ lỡ. */
    public function visiblePendingGroupsForDriver(int $driverUserId): Collection
    {
        return $this->discovery->visiblePendingGroupsForDriver($driverUserId);
    }

    public function tripActionCountForDriver(int $driverUserId): int
    {
        return $this->discovery->tripActionCountForDriver($driverUserId);
    }

    /** @return array<string, mixed> */
    public function serializePendingGroup(array $group): array
    {
        return $this->discovery->serializePendingGroup($group);
    }

    private function anchorAllBookingsForAcceptedSchedule(Schedule $schedule): void
    {
        $workflow = app(BookingWorkflowService::class);
        $driverUserId = (int) $schedule->driver_id;
        $driverProfile = DriverProfile::query()->where('user_id', $driverUserId)->first();

        foreach ($schedule->driverRelevantBookings() as $booking) {
            $distance = $driverProfile
                ? $this->proximity->snapshotPickupDistance($booking, $driverProfile)
                : null;

            $booking->stampAssignedDriver($driverUserId);

            if ($distance !== null) {
                $booking->update(['driver_pickup_distance_km' => $distance]);
            }

            $this->anchorBookingForDriverAccept($schedule, $booking, $workflow);
        }

        $this->movementConfirm->stampAssignmentDeadline(
            $schedule->fresh(),
            $schedule->driverRelevantBookings()->first(),
        );

        $this->availability->syncAfterTripAssigned($driverUserId);

        try {
            $notifyBooking = $schedule->driverRelevantBookings()->first();
            if ($notifyBooking) {
                app(\App\Services\AdminOperatorAlertService::class)
                    ->recordDriverAccepted($notifyBooking->fresh(['schedule.route']));
            }
        } catch (\Throwable) {
        }
    }

    private function anchorBookingForDriverAccept(
        Schedule $schedule,
        Booking $booking,
        BookingWorkflowService $workflow,
    ): void {
        if (in_array($booking->booking_status, ['cancelled', 'rejected'], true)) {
            if (! $booking->expired_at) {
                return;
            }

            $booking->update([
                'booking_status'  => 'pending',
                'trip_status'     => 'pending',
                'expired_at'      => null,
                'cancelled_at'    => null,
                'hold_expires_at' => null,
            ]);

            $workflow->syncScheduleAvailability($schedule);
            $workflow->confirmForDriverAccept($booking->fresh());

            return;
        }

        $booking->update(['hold_expires_at' => null]);

        $workflow->confirmForDriverAccept($booking->fresh());

        try {
            app(\App\Services\PushNotificationService::class)->onDriverAcceptedBooking($booking->fresh());
        } catch (\Throwable) {
        }
    }

    public function reassignScheduleDriver(Schedule $schedule, string $newDriverCode, int $operatorUserId): void
    {
        $this->manualAssign->reassignScheduleDriver($schedule, $newDriverCode, $operatorUserId);
    }

    /** Admin gán / gán lại TX — tạo chuyến mới cho khách, mời TX trong 15 phút. */
    public function assignBookingDriver(Booking $booking, string $driverCode, int $operatorUserId): void
    {
        $this->manualAssign->assignBookingDriver($booking, $driverCode, $operatorUserId);
    }

    public function reassignBookingDriver(Booking $booking, string $newDriverCode, int $operatorUserId): void
    {
        $this->manualAssign->reassignBookingDriver($booking, $newDriverCode, $operatorUserId);
    }

    public function reject(DriverTripRequest $request, int $driverUserId, int $cancellationReasonId): void
    {
        if ($request->driver_id !== $driverUserId) {
            throw new InvalidArgumentException('Không có quyền xử lý yêu cầu này.');
        }

        if (! $request->isPending()) {
            throw new InvalidArgumentException('Yêu cầu không còn hiệu lực.');
        }

        $reason = app(CancellationReasonService::class)->resolveForCancel($cancellationReasonId, 'driver');

        $siblings = DriverTripRequest::query()
            ->where('schedule_id', $request->schedule_id)
            ->where('driver_id', $driverUserId)
            ->where('status', 'pending')
            ->get();

        foreach ($siblings as $sibling) {
            $sibling->update([
                'status'       => 'rejected',
                'responded_at' => now(),
            ]);

            app(DriverCuocOfferHideService::class)->recordMissedOffer($sibling->fresh());

            try {
                $booking = $this->bookingForRequest($sibling)->fresh(['schedule.route', 'schedule.vehicle', 'schedule.template']);
                app(BookingWorkflowService::class)->stampDriverReleaseReason($booking, $reason);
            } catch (\Throwable) {
            }

            $this->tryReassignAfterDecline($sibling);
        }

        $request->loadMissing('schedule.route');
        if ($request->schedule) {
            $profile = DriverProfile::query()->with('user')->where('user_id', $driverUserId)->first();
            $this->tripLedger->recordForSchedule($request->schedule, TripLedger::OUTCOME_CANCELLED_DRIVER, [
                'actor_label' => $profile?->user?->name ?? 'Tài xế',
                'actor_code'  => $profile?->driver_code,
            ]);
        }
    }

    private function tryReassignAfterDecline(DriverTripRequest $request): void
    {
        $request->loadMissing(['schedule.route', 'schedule.vehicle']);
        $schedule = $request->schedule;

        if (! $schedule || $schedule->driver_id || $schedule->departure_time <= now()) {
            return;
        }

        $booking = $this->bookingForRequest($request);
        $this->tryRotateAfterAssignMiss(
            $booking->fresh(['schedule.route', 'schedule.vehicle', 'schedule.template']),
            (int) $request->driver_id,
        );
    }

    /** Tắt hoạt động — trả cuốc đang chờ, không tính là từ chối / bỏ lỡ. */
    public function releasePendingRequestsOnOffDuty(int $driverUserId): void
    {
        DriverTripRequest::query()
            ->where('driver_id', $driverUserId)
            ->where('status', 'pending')
            ->get()
            ->each(function (DriverTripRequest $request): void {
                $request->update([
                    'status'       => 'cancelled',
                    'responded_at' => now(),
                ]);
            });
    }

    /** Gia hạn thêm 15 phút tìm tài xế — mỗi lần mời tài xế mới hoặc tài xế không nhận / hủy. */
    public function refreshCustomerSearchDeadline(Booking $booking): void
    {
        if (in_array($booking->booking_status, ['cancelled', 'rejected'], true)
            || $booking->trip_status === 'completed'
            || $booking->schedule?->driver_id) {
            return;
        }

        $booking->update([
            'driver_search_started_at' => now(),
        ]);
    }

    /** Gỡ tài xế sau timeout đón trễ — tìm lại tài xế gần nhất (auto-assign), loại trừ tài xế cũ. */
    public function tryReassignAfterDriverRelease(Booking $booking, int $formerDriverUserId): void
    {
        $booking = $booking->fresh(['schedule.route', 'schedule.vehicle', 'schedule.template']);
        $schedule = $booking->schedule;

        if (! $schedule || $schedule->driver_id) {
            return;
        }

        if ($this->hasExceededCustomerSearchDeadline($booking)) {
            return;
        }

        $this->refreshCustomerSearchDeadline($booking);
        $booking = $booking->fresh(['schedule.route', 'schedule.vehicle', 'schedule.template']);
        $schedule = $booking->schedule;

        if (! $schedule) {
            return;
        }

        $exclude = $this->assignmentExcludeDriverIds($schedule, (string) $booking->contact_phone, $formerDriverUserId);
        $this->cancelRecoverablePendingOffers($schedule, (string) $booking->contact_phone, $exclude);

        // Không còn chọn tài xế chỉ định — luôn tìm lại tài xế gần nhất
        // (schedule.template chỉ còn là dữ liệu giá nội bộ, không phải TX khách chọn).
        if ($this->autoAssignPass($schedule, $booking, $exclude)) {
            return;
        }

        if ($this->proximity->pickBest($schedule, $booking, $exclude, true)
            && $this->autoAssignPass($schedule, $booking, $exclude)) {
            return;
        }

        $booking = $booking->fresh(['schedule.route', 'schedule.vehicle']);

        if ($this->hasEligiblePendingOffer($schedule, (string) $booking->contact_phone, $exclude)) {
            return;
        }

        if ($booking->isOnDemandPickup() && ! $this->hasExceededCustomerSearchDeadline($booking)) {
            return;
        }

        if ($this->hangDriverSearchIfOverdue($booking)) {
            return;
        }

        app(BookingWorkflowService::class)->flagOperatorHelpNeeded($booking, 'driver_cancelled_trip');
    }

    /** Phân biệt lời mời tự động (2 phút) với quản lý gán tay (15 phút). */
    private function wasAutoAssignRequest(DriverTripRequest $request): bool
    {
        if (! $request->created_at || ! $request->expires_at) {
            return false;
        }

        $minutes = (int) $request->created_at->diffInMinutes($request->expires_at, false);

        return abs($minutes - self::AUTO_ASSIGN_ACCEPT_MINUTES) <= 1
            || abs($minutes - self::SCHEDULED_ASSIGN_ACCEPT_MINUTES) <= 1;
    }

    /** Đếm lần hết hạn cuốc tự động liên tiếp (không tính từ chối / đã nhận). */
    private function consecutiveAutoAssignMissCount(int $driverUserId): int
    {
        $count = 0;

        foreach (DriverTripRequest::query()
            ->where('driver_id', $driverUserId)
            ->whereIn('status', ['expired', 'accepted', 'rejected'])
            ->whereNotNull('responded_at')
            ->orderByDesc('responded_at')
            ->limit(20)
            ->get() as $request) {
            if ($request->status === 'accepted' || $request->status === 'rejected') {
                break;
            }

            if ($request->status === 'expired' && $this->wasAutoAssignRequest($request)) {
                $count++;

                continue;
            }

            break;
        }

        return $count;
    }

    private function recordAutoAssignMiss(int $driverUserId): void
    {
        // 0 = không tự tắt Hoạt động khi bỏ lỡ cuốc — chỉ xoay TX khác.
        if (self::AUTO_ASSIGN_MISS_OFF_DUTY_THRESHOLD < 1) {
            return;
        }

        if ($this->consecutiveAutoAssignMissCount($driverUserId) < self::AUTO_ASSIGN_MISS_OFF_DUTY_THRESHOLD) {
            return;
        }

        $this->pauseDriverAfterMissedAutoAssign($driverUserId);
    }

    /** Tài xế không nhận cuốc tự động trong 2 phút — tắt sẵn sàng, bắt bật lại. */
    private function pauseDriverAfterMissedAutoAssign(int $driverUserId): void
    {
        $profile = DriverProfile::query()
            ->operational()
            ->where('user_id', $driverUserId)
            ->first();

        if (! $profile || ($profile->availability_status ?? 'off_duty') === 'on_trip') {
            return;
        }

        $this->availability->markOffDuty($profile);
    }

    // TODO (Fix Stuck Offer UI): Đồng bộ realtime khi offer không còn hiệu lực để app TX thu hồi card ngay.
    private function notifyDriverOfferRevoked(DriverTripRequest $request): void
    {
        try {
            app(PushNotificationService::class)->onDriverTripRequestExpired($request);
        } catch (\Throwable) {
        }
    }

    private function bookingForRequest(DriverTripRequest $request): Booking
    {
        $booking = $request->relatedBooking();
        if ($booking) {
            return $booking;
        }

        return Booking::query()
            ->where('schedule_id', $request->schedule_id)
            ->where('contact_phone', $request->contact_phone)
            ->latest()
            ->firstOrFail();
    }

    /**
     * TX đã từ chối / hết hạn nhận / hủy cuốc — không gán lại tự động cho cùng đơn.
     * Quản lý gán thủ công qua {@see requestDriver()} thì không áp dụng.
     *
     * @return Collection<int, int>
     */
    public function assignmentExcludeDriverIds(
        Schedule $schedule,
        string $contactPhone,
        int|Collection|null $extraDriverUserIds = null,
    ): Collection {
        $phone = trim($contactPhone);
        if ($phone === '') {
            return collect();
        }

        $fromRequests = DriverTripRequest::query()
            ->where('schedule_id', $schedule->id)
            ->where('contact_phone', $phone)
            ->whereIn('status', ['expired', 'rejected', 'cancelled'])
            ->pluck('driver_id')
            ->map(fn ($id): int => (int) $id);

        $fromHide = app(DriverCuocOfferHideService::class)->hiddenDriverIdsForOffer($schedule, $phone);

        $extra = $extraDriverUserIds instanceof Collection
            ? $extraDriverUserIds
            : collect($extraDriverUserIds !== null && (int) $extraDriverUserIds > 0 ? [(int) $extraDriverUserIds] : []);

        return $fromRequests
            ->merge($fromHide)
            ->merge($extra)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();
    }

    public function cancelByContactPhone(DriverTripRequest $request, string $contactPhone): void
    {
        $stored = preg_replace('/\D+/', '', (string) $request->contact_phone);
        $given = preg_replace('/\D+/', '', $contactPhone);
        if ($stored === '' || $stored !== $given) {
            throw new InvalidArgumentException('Không có quyền hủy yêu cầu này.');
        }

        if (! $request->isPending()) {
            throw new InvalidArgumentException('Yêu cầu không còn hiệu lực.');
        }

        $request->update([
            'status'       => 'cancelled',
            'responded_at' => now(),
        ]);

        app(DriverCuocOfferHideService::class)->recordMissedOffer($request->fresh());
        $this->tryReassignAfterDecline($request->fresh());
    }

    /** @return array<string, mixed> */
    public function serializeDriver(DriverProfile $profile): array
    {
        $meta = $this->availabilityMeta($profile->availability_status ?? 'off_duty');

        return [
            'code'                => $profile->driver_code,
            'name'                => $profile->user->name,
            'license_class'       => $profile->license_class,
            'experience_years'    => $profile->experience_years,
            'operator'            => $profile->operator?->name,
            'availability'        => $profile->availability_status,
            'availability_label'  => $meta['label'],
            'availability_color'  => $meta['color'],
            'availability_icon'   => $meta['icon'],
            'suggested'           => $meta['suggested'],
            'vehicle_type'        => $profile->vehicle_type,
            'vehicle_plate'       => $profile->vehicle_license_plate,
            'vehicle_seats'       => (int) ($profile->vehicle_seats ?? 0),
            'vehicle_label'       => self::vehicleLabel($profile),
        ];
    }

    public static function vehicleLabel(DriverProfile $profile): string
    {
        return VehicleDisplay::compactLabel(
            $profile->vehicle_type ? (string) $profile->vehicle_type : null,
            $profile->vehicle_license_plate,
            $profile->vehicle_seats ? (int) $profile->vehicle_seats : null,
        );
    }

    /** @return array<string, mixed>|null */
    public function serializeRequest(?DriverTripRequest $request): ?array
    {
        if (! $request) {
            return null;
        }

        return [
            'id'           => $request->id,
            'schedule_id'  => $request->schedule_id,
            'status'       => $request->status,
            'status_label' => $request->statusLabel(),
            'driver_name'  => $request->driver?->name,
            'driver_code'  => $request->driverProfile?->driver_code,
            'expires_at'   => $request->expires_at?->toIso8601String(),
            'is_pending'   => $request->isPending(),
        ];
    }
}
