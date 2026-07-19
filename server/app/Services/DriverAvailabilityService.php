<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\DriverProfile;
use App\Models\Schedule;
use App\Models\ScheduleTemplate;
use App\Support\DepartureTimeDisplay;
use App\Support\ServiceDate;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class DriverAvailabilityService
{
    public const MIN_PICKUP_LEAD_MINUTES = 30;

    /** Thời gian coi tài xế còn "online" trên web sau lần tương tác cuối. */
    public const WEB_PRESENCE_MINUTES = 15;

    /** Thời gian nghỉ / quay về hub sau khi kết thúc chuyến trước khi nhận chuyến tiếp theo. */
    public const MIN_TURNAROUND_MINUTES = 60;

    /** Số chuyến tài xế đang phục vụ (chưa hoàn tất). */
    public function activeTripCount(int $driverUserId, ?int $excludeScheduleId = null): int
    {
        return $this->activeSchedulesForDriver($driverUserId, $excludeScheduleId)->count();
    }

    /**
     * Trùng khung giờ với chuyến đang phục vụ — từ giờ đón (hoặc khởi hành) đến dự kiến đến.
     * Không gán thêm tuyến khác (vd. Củ Chi + Vũng Tàu) khi tài xế còn chuyến chưa xong.
     */
    public function hasTripTimeConflict(
        int $driverUserId,
        Schedule $candidateSchedule,
        ?Booking $candidateBooking = null,
        ?int $excludeScheduleId = null,
    ): bool {
        $excludeId = $excludeScheduleId ?? (int) $candidateSchedule->id;

        if ($this->hasOtherActiveScheduleConflict($driverUserId, (int) $candidateSchedule->id, $excludeId)) {
            return true;
        }

        $candidateStart = $candidateBooking?->tripStartAt() ?? $candidateSchedule->departure_time;
        $candidateEnd = $this->bookingBusyUntil($candidateBooking, $candidateSchedule);

        if (! $candidateStart || ! $candidateEnd) {
            return false;
        }

        return $this->hasTimeOverlapWithActiveTrips(
            $driverUserId,
            $candidateStart,
            $candidateEnd,
            $excludeScheduleId ?? (int) $candidateSchedule->id,
        );
    }

    public function hasTripTimeConflictForTemplate(
        int $driverUserId,
        ScheduleTemplate $template,
        Carbon $departureTime,
    ): bool {
        $template->loadMissing('route');

        if ($this->assignmentBlockingSchedulesForDriver($driverUserId)->isNotEmpty()) {
            return true;
        }

        $arrival = $this->expectedArrivalFor($departureTime, $template)
            ->copy()
            ->addMinutes(self::MIN_TURNAROUND_MINUTES);

        return $this->hasTimeOverlapWithActiveTrips($driverUserId, $departureTime, $arrival);
    }

    /** Tài xế còn chuyến khác chưa đóng — chỉ phục vụ một chuyến tại một thời điểm. */
    public function hasOtherActiveScheduleConflict(
        int $driverUserId,
        int $candidateScheduleId,
        ?int $excludeScheduleId = null,
    ): bool {
        $exclude = $excludeScheduleId ?? $candidateScheduleId;

        foreach ($this->assignmentBlockingSchedulesForDriver($driverUserId, $exclude) as $schedule) {
            if ((int) $schedule->id !== $candidateScheduleId) {
                return true;
            }
        }

        return false;
    }

    public function assignmentConflictMessage(
        int $driverUserId,
        Schedule $candidateSchedule,
        ?Booking $candidateBooking = null,
        ?int $excludeScheduleId = null,
    ): ?string {
        $excludeId = $excludeScheduleId ?? (int) $candidateSchedule->id;

        if ($this->hasOtherActiveScheduleConflict($driverUserId, (int) $candidateSchedule->id, $excludeId)) {
            return 'Tài xế đang phục vụ chuyến khác — hệ thống sẽ gán tài xế khác.';
        }

        if ($this->hasTripTimeConflict($driverUserId, $candidateSchedule, $candidateBooking, $excludeScheduleId)) {
            return 'Tài xế đang bận chuyến khác trùng khung giờ. Vui lòng chọn tài xế khác.';
        }

        return null;
    }

    public function canAcceptSchedule(
        int $driverUserId,
        Schedule $schedule,
        ?Booking $booking = null,
    ): bool {
        return ! $this->hasTripTimeConflict($driverUserId, $schedule, $booking);
    }

    /** @return Collection<int, Schedule> */
    public function activeSchedulesForDriver(int $driverUserId, ?int $excludeScheduleId = null): Collection
    {
        return Schedule::query()
            ->with(['route', 'bookings'])
            ->where('driver_id', $driverUserId)
            ->whereIn('status', ['scheduled', 'running'])
            ->whereHas('bookings', fn ($q) => $q
                ->whereNotIn('booking_status', ['cancelled', 'rejected'])
                ->where('trip_status', '!=', 'completed'))
            ->whereDoesntHave('bookings', fn ($q) => $q
                ->whereNotIn('booking_status', ['cancelled', 'rejected'])
                ->where('trip_status', 'awaiting_completion'))
            ->when($excludeScheduleId, fn ($q) => $q->where('id', '!=', $excludeScheduleId))
            ->get();
    }

    /** TX còn chuyến thực sự hiện trên dashboard — không tính chuyến đã ẩn (quá giờ đón, v.v.). */
    public function hasVisibleDashboardBlockingTrip(int $driverUserId): bool
    {
        return $this->assignmentBlockingSchedulesForDriver($driverUserId)->isNotEmpty();
    }

    /**
     * Chuyến đang chặn gán cuốc mới — cùng rule visible với dashboard tài xế.
     *
     * @return Collection<int, Schedule>
     */
    public function assignmentBlockingSchedulesForDriver(int $driverUserId, ?int $excludeScheduleId = null): Collection
    {
        if ($driverUserId <= 0) {
            return collect();
        }

        return Schedule::query()
            ->forDriverActiveTrips($driverUserId)
            ->when($excludeScheduleId, fn ($q) => $q->where('id', '!=', $excludeScheduleId))
            ->get()
            ->filter(fn (Schedule $schedule): bool => $schedule->blocksDriverCatalogBooking())
            ->values();
    }

    /** @return Collection<int, DriverProfile> */
    public function availableForBooking(
        ScheduleTemplate $template,
        string $serviceDate,
        ?string $preferredTime,
        string $pickupCity,
        string $dropoffCity,
        ?Schedule $schedule = null,
    ): Collection {
        $template->loadMissing(['vehicle', 'route']);
        $departureTime = $this->resolveDepartureTime($template, $serviceDate, $preferredTime);

        $query = DriverProfile::query()
            ->operational()
            ->with(['user', 'operator'])
            ->orderByRaw("FIELD(availability_status, 'available', 'off_duty', 'on_trip')")
            ->orderByDesc('experience_years');

        $drivers = $query->get();

        if ($schedule?->driver_id && $schedule->bookedSeatsCount() < $schedule->capacity()) {
            return $drivers
                ->filter(fn (DriverProfile $p): bool => (int) $p->user_id === (int) $schedule->driver_id)
                ->values();
        }

        return $drivers
            ->filter(function (DriverProfile $profile) use ($template, $departureTime): bool {
                return ! $this->hasTripTimeConflictForTemplate(
                    (int) $profile->user_id,
                    $template,
                    $departureTime,
                );
            })
            ->values();
    }

    /** @return array<int, array<string, mixed>> */
    public function serializeForGuest(
        Collection $drivers,
        ?Schedule $schedule = null,
    ): array {
        return $drivers->map(function (DriverProfile $profile) use ($schedule) {
            $data = app(DriverTripRequestService::class)->serializeDriver($profile);
            $data['seats_hint'] = null;

            if ($schedule && (int) $schedule->driver_id === (int) $profile->user_id) {
                $booked = $schedule->bookedSeatsCount();
                $cap = $schedule->capacity();
                $data['seats_hint'] = $booked . '/' . $cap . ' ghế, còn nhận thêm';
                $data['already_assigned'] = true;
            } else {
                $data['already_assigned'] = false;
            }

            return $data;
        })->all();
    }

    public function suggestedPickupClock(?Carbon $from = null): string
    {
        $at = ($from ?? now())->copy()
            ->addMinutes(self::MIN_PICKUP_LEAD_MINUTES)
            ->seconds(0);

        $remainder = $at->minute % 5;
        if ($remainder !== 0) {
            $at->addMinutes(5 - $remainder);
        }

        return DepartureTimeDisplay::normalizeForClock($at->format('H:i'));
    }

    public function resolveDepartureTime(
        ScheduleTemplate $template,
        string $serviceDate,
        ?string $preferredTime = null,
    ): Carbon {
        $template->loadMissing('route');
        $date = ServiceDate::parse($serviceDate);

        if (is_string($preferredTime) && trim($preferredTime) !== '') {
            return $this->clockOnDate($date, DepartureTimeDisplay::normalizeForClock($preferredTime))
                ->copy()
                ->startOfMinute();
        }

        if ($template->hasFixedDepartureTime()) {
            return $template->departureAt($date)->copy()->startOfMinute();
        }

        return $this->flexibleDepartureDefault($date)->copy()->startOfMinute();
    }

    public function assertPickupTimeAvailable(string $serviceDate, ?string $pickupTime): void
    {
        if (! is_string($pickupTime) || trim($pickupTime) === '') {
            return;
        }

        $date = ServiceDate::parse($serviceDate);
        $departure = $this->clockOnDate(
            $date,
            DepartureTimeDisplay::normalizeForClock($pickupTime),
        );

        if ($date->isToday() && $departure->lt(now())) {
            throw new InvalidArgumentException('Giờ đón phải sau thời gian hiện tại.');
        }
    }

    private function flexibleDepartureDefault(Carbon $serviceDate): Carbon
    {
        if ($serviceDate->isToday()) {
            return now()->copy()->addMinutes(self::MIN_PICKUP_LEAD_MINUTES)->startOfMinute();
        }

        return $serviceDate->copy()->startOfDay()->setTime(8, 0);
    }

    private function clockOnDate(Carbon $serviceDate, string $clock): Carbon
    {
        return Carbon::parse(
            $serviceDate->toDateString() . ' ' . $clock . ':00',
            config('app.timezone'),
        );
    }

    public function assertDriverSelectable(
        DriverProfile $profile,
        ScheduleTemplate $template,
        string $serviceDate,
        ?string $preferredTime,
        string $pickupCity,
        string $dropoffCity,
        ?Schedule $schedule = null,
    ): void {
        $available = $this->availableForBooking(
            $template,
            $serviceDate,
            $preferredTime,
            $pickupCity,
            $dropoffCity,
            $schedule,
        );

        if (! $available->contains(fn (DriverProfile $p): bool => (int) $p->user_id === (int) $profile->user_id)) {
            throw new InvalidArgumentException('Tài xế không còn rảnh cho khung giờ này (đang phục vụ tuyến khác hoặc trùng giờ). Vui lòng chọn tài xế khác.');
        }
    }

    private function hasTimeOverlapWithActiveTrips(
        int $driverUserId,
        Carbon $candidateStart,
        Carbon $candidateEnd,
        ?int $excludeScheduleId = null,
    ): bool {
        foreach ($this->assignmentBlockingSchedulesForDriver($driverUserId, $excludeScheduleId) as $schedule) {
            $busyStart = $this->scheduleTripStart($schedule);
            $busyEnd = $this->scheduleBusyUntil($schedule);

            if ($this->windowsOverlap($candidateStart, $candidateEnd, $busyStart, $busyEnd)) {
                return true;
            }
        }

        return false;
    }

    private function scheduleTripStart(Schedule $schedule): Carbon
    {
        $schedule->loadMissing('bookings');

        $starts = $schedule->driverRelevantBookings()
            ->map(fn (Booking $booking) => $booking->tripStartAt())
            ->filter()
            ->sort()
            ->values();

        return $starts->first() ?? $schedule->departure_time;
    }

    private function scheduleBusyUntil(Schedule $schedule): Carbon
    {
        $schedule->loadMissing('bookings');

        $ends = $schedule->driverRelevantBookings()
            ->map(fn (Booking $booking): ?Carbon => $this->bookingBusyUntil($booking, $schedule))
            ->filter()
            ->values();

        if ($ends->isNotEmpty()) {
            return $ends->max();
        }

        return $schedule->expectedArrivalAt()->copy()->addMinutes(self::MIN_TURNAROUND_MINUTES);
    }

    private function bookingBusyUntil(?Booking $booking, Schedule $schedule): Carbon
    {
        if ($booking) {
            $completion = $booking->expectedTripCompletionAt();

            if ($completion) {
                return $completion->copy()->addMinutes(self::MIN_TURNAROUND_MINUTES);
            }
        }

        return $schedule->expectedArrivalAt()->copy()->addMinutes(self::MIN_TURNAROUND_MINUTES);
    }

    private function windowsOverlap(Carbon $startA, Carbon $endA, Carbon $startB, Carbon $endB): bool
    {
        return $startA->lt($endB) && $startB->lt($endA);
    }

    private function expectedArrivalFor(Carbon $departureTime, ScheduleTemplate $template): Carbon
    {
        $template->loadMissing('route');

        if ($template->hasFixedDepartureTime() && $template->expected_arrival_time) {
            return $template->expectedArrivalAt($departureTime->copy()->startOfDay());
        }

        $km = (int) ($template->route->distance_km ?? 0);
        $minutes = $km > 0
            ? (int) ceil($km / \App\Services\DriverMovementConfirmService::ASSUMED_SPEED_KMH * 60)
            : 120;

        return $departureTime->copy()->addMinutes($minutes);
    }
    /** GPS mới hoặc tài xế đang mở tab Sẵn sàng — đủ để ghép cuốc. */
    public function hasAssignableLocation(
        DriverProfile $profile,
        int $maxAgeMinutes = DriverProximityService::LOCATION_MAX_AGE_MINUTES,
    ): bool {
        if ($profile->last_lat === null || $profile->last_lng === null || ! $profile->last_location_at) {
            return false;
        }

        if ($profile->hasFreshLocation($maxAgeMinutes)) {
            return true;
        }

        return ($profile->availability_status ?? 'off_duty') === 'available'
            && Cache::has($this->webPresenceKey((int) $profile->user_id));
    }

    public function enforceWebPresenceIdleFor(DriverProfile $profile): void
    {
        if (($profile->availability_status ?? 'off_duty') !== 'available') {
            return;
        }

        if ($this->activeTripCount((int) $profile->user_id) > 0) {
            return;
        }

        if (Cache::has($this->webPresenceKey((int) $profile->user_id))) {
            return;
        }

        if ($profile->hasFreshLocation(self::WEB_PRESENCE_MINUTES)) {
            $this->touchWebPresence((int) $profile->user_id);

            return;
        }

        $this->markOffDuty($profile);
    }

    /** Khách đặt ngay — TX bật Sẵn sàng, còn chia sẻ vị trí, không đang chạy chuyến. */
    public function isBookableNow(DriverProfile $profile): bool
    {
        return $this->catalogBookingButtonState($profile)['book_now'];
    }

    /**
     * Trạng thái nút «Đặt ngay» trên catalog khách.
     *
     * @return array{is_online: bool, has_location: bool, book_now: bool}
     */
    public function catalogBookingButtonState(DriverProfile $profile): array
    {
        // TODO (Fix Catalog Poll Book Now): Chỉ chặn «Đặt ngay» khi còn chuyến visible trên dashboard TX.
        // TX on_trip do chuyến ẩn (ghost) vẫn coi là online — không đổi availability_status khi poll catalog.
        $hasBlockingTrip = $this->hasVisibleDashboardBlockingTrip((int) $profile->user_id);
        $status = $profile->availability_status ?? 'off_duty';
        $isOnline = $status !== 'off_duty' && ! $hasBlockingTrip;
        // TODO (Update Booking Button Logic): Location = GPS mới hoặc TX đang mở app + còn tọa độ.
        $hasLocation = $this->hasAssignableLocation($profile, self::WEB_PRESENCE_MINUTES);

        return [
            'is_online'     => $isOnline,
            'has_location'  => $hasLocation,
            'book_now'      => $isOnline && $hasLocation,
        ];
    }

    /** Đọc catalog — không ghi đè availability_status (tránh poll khách làm lệch TX / ẩn chuyến đang đặt). */
    public function syncCatalogDriverStates(iterable $profiles): void
    {
    }

    /** @return array{active: bool, upcoming: bool} */
    public function driverDashboardTripFlags(int $driverUserId): array
    {
        if ($driverUserId <= 0) {
            return ['active' => false, 'upcoming' => false];
        }

        $schedules = Schedule::query()
            ->with([
                'route',
                'vehicle',
                'tripSettlement',
                'bookings' => fn ($q) => $q->orderByDesc('id'),
            ])
            ->forDriverActiveTrips($driverUserId)
            ->get()
            ->filter(fn (Schedule $schedule): bool => $schedule->driverRelevantBookings()->isNotEmpty()
                && $schedule->driverWorkflowPhase() !== 'settled'
                && $schedule->isVisibleOnDriverDashboard());

        return [
            'active'   => $schedules->contains(
                fn (Schedule $schedule): bool => $schedule->driverWorkflowPhase() === 'active',
            ),
            'upcoming' => $schedules->contains(
                fn (Schedule $schedule): bool => $schedule->driverWorkflowPhase() === 'upcoming',
            ),
        ];
    }

    public function touchWebPresence(int $driverUserId): void
    {
        Cache::put(
            $this->webPresenceKey($driverUserId),
            1,
            now()->addMinutes(self::WEB_PRESENCE_MINUTES),
        );

        // App đang mở lại — cho phép nudge “khách gần” sau lần tắt app tiếp theo.
        app(DriverSystemNotificationService::class)->clearOfflineSession($driverUserId);
    }

    public function hasWebPresence(int $driverUserId): bool
    {
        return Cache::has($this->webPresenceKey($driverUserId));
    }

    /** Admin / khách — TX đang mở app (GPS mới hoặc heartbeat poll tab tài xế). */
    public function isDriverAppActiveForAdmin(DriverProfile $profile): bool
    {
        if ($profile->hasFreshLocation(self::WEB_PRESENCE_MINUTES)) {
            return true;
        }

        return $this->hasWebPresence((int) $profile->user_id);
    }

    public function forgetWebPresence(int $driverUserId): void
    {
        Cache::forget($this->webPresenceKey($driverUserId));
    }

    private function webPresenceKey(int $driverUserId): string
    {
        return 'driver_web_presence:' . $driverUserId;
    }

    public function syncAfterTripAssigned(int $driverUserId): void
    {
        DriverProfile::query()
            ->where('user_id', $driverUserId)
            ->where('availability_status', '!=', 'off_duty')
            ->update(['availability_status' => 'on_trip']);
    }

    /** Sau khi hoàn thành chuyến — về sẵn sàng trừ khi tài xế đang tạm nghỉ. */
    public function syncAfterTripCompleted(int $driverUserId): void
    {
        $profile = DriverProfile::query()->where('user_id', $driverUserId)->first();

        if (! $profile || ($profile->availability_status ?? '') === 'off_duty') {
            return;
        }

        $status = $this->activeTripCount($driverUserId) > 0 ? 'on_trip' : 'available';

        if ($profile->availability_status !== $status) {
            $profile->update(['availability_status' => $status]);
        }
    }

    public function markOffDuty(DriverProfile $profile): void
    {
        // Giữ vị trí / phiên offline TRƯỚC khi xóa GPS — dùng cho nudge khách gần.
        app(DriverSystemNotificationService::class)->armOfflineSession($profile);

        $this->forgetWebPresence((int) $profile->user_id);

        app(DriverTripRequestService::class)->releasePendingRequestsOnOffDuty((int) $profile->user_id);

        $profile->update([
            'availability_status' => 'off_duty',
            'last_lat'            => null,
            'last_lng'            => null,
            'last_location_at'    => null,
            'last_address'        => null,
            'last_province'       => null,
        ]);
    }

    public function markAvailable(DriverProfile $profile): void
    {
        if ($this->activeTripCount((int) $profile->user_id) > 0) {
            $profile->update(['availability_status' => 'on_trip']);
            app(DriverSystemNotificationService::class)->clearOfflineSession((int) $profile->user_id);

            return;
        }

        $profile->update(['availability_status' => 'available']);
        $this->touchWebPresence((int) $profile->user_id);
    }

    /** Đăng nhập lại — bắt bật Sẵn sàng + GPS mới (tránh dùng vị trí cũ). */
    public function resetForWebLogin(DriverProfile $profile): void
    {
        $this->forgetWebPresence((int) $profile->user_id);

        if ($this->activeTripCount((int) $profile->user_id) > 0) {
            return;
        }

        if (($profile->availability_status ?? 'off_duty') !== 'off_duty') {
            $this->markOffDuty($profile);
        }
    }

    /** @param  iterable<DriverProfile>  $profiles */
    public function reconcileMany(iterable $profiles): void
    {
        foreach ($profiles as $profile) {
            if (! $profile instanceof DriverProfile) {
                continue;
            }

            if ($profile->status !== 'active' || $profile->isPendingApproval() || $profile->isRejected()) {
                continue;
            }

            if (($profile->availability_status ?? 'off_duty') === 'off_duty') {
                $this->syncAfterTripCompleted((int) $profile->user_id);

                continue;
            }

            $this->enforceWebPresenceIdleFor($profile);
            $this->syncAfterTripCompleted((int) $profile->user_id);
        }
    }

    /** Đăng xuất / hết phiên — tắt Sẵn sàng và xóa vị trí. */
    public function endWebSession(DriverProfile $profile): void
    {
        if ($this->activeTripCount((int) $profile->user_id) > 0) {
            $this->forgetWebPresence((int) $profile->user_id);

            return;
        }

        $this->markOffDuty($profile);
    }
}
