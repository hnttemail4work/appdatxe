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
use InvalidArgumentException;

class DriverAvailabilityService
{
    public const MIN_PICKUP_LEAD_MINUTES = 30;

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

        if ($this->activeSchedulesForDriver($driverUserId)->isNotEmpty()) {
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

        foreach ($this->activeSchedulesForDriver($driverUserId, $exclude) as $schedule) {
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
                ->where(function ($q2): void {
                    $q2->whereNotNull('needs_operator_help_at')
                        ->orWhere('trip_status', 'awaiting_completion');
                }))
            ->when($excludeScheduleId, fn ($q) => $q->where('id', '!=', $excludeScheduleId))
            ->get();
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
        foreach ($this->activeSchedulesForDriver($driverUserId, $excludeScheduleId) as $schedule) {
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
            ? (int) ceil($km / OperatorTripOverdueService::ASSUMED_SPEED_KMH * 60)
            : 120;

        return $departureTime->copy()->addMinutes($minutes);
    }
}
