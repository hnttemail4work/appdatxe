<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\DriverTripRequest;
use App\Models\Schedule;
use App\Models\ScheduleMergeRequest;
use App\Models\ScheduleTemplate;
use App\Support\ProvinceCenters;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Gom chuyến ghép xe cùng tuyến / gần giờ / gần điểm đón. */
class TripConsolidationService
{
    /** Chênh lệch giờ khởi hành tối đa để gom chung một chuyến. */
    public const POOL_WINDOW_MINUTES = 45;

    /** Khoảng cách tối đa giữa các điểm đón trên cùng chuyến (km). */
    public const MAX_PICKUP_SPREAD_KM = 10;

    /** Thời gian chờ tài xế xác nhận gom chuyến. */
    public const MERGE_APPROVAL_HOURS = 6;

    public function findPoolableSchedule(
        ScheduleTemplate $template,
        string $serviceDate,
        Carbon $candidateDeparture,
        int $seatsNeeded = 1,
        ?float $pickupLat = null,
        ?float $pickupLng = null,
    ): ?Schedule {
        $template->loadMissing(['vehicle', 'route']);
        $seatsNeeded = max($seatsNeeded, 1);

        $candidates = Schedule::query()
            ->with(['bookings', 'vehicle'])
            ->where('template_id', $template->id)
            ->whereDate('service_date', $serviceDate)
            ->where('status', 'scheduled')
            ->whereNull('driver_id')
            ->where('departure_time', '!=', $candidateDeparture)
            ->orderBy('departure_time')
            ->get();

        $best = null;
        $bestScore = PHP_INT_MAX;

        foreach ($candidates as $schedule) {
            if (! $this->canAcceptSharedBooking($schedule, $candidateDeparture, $seatsNeeded, $pickupLat, $pickupLng)) {
                continue;
            }

            $diff = abs($candidateDeparture->diffInMinutes($schedule->departure_time));
            $fillPenalty = max(0, $schedule->capacity() - $schedule->bookedSeatsCount()) * 2;
            $score = $diff + $fillPenalty;

            if ($score < $bestScore) {
                $bestScore = $score;
                $best = $schedule;
            }
        }

        return $best;
    }

    public function canAcceptSharedBooking(
        Schedule $schedule,
        Carbon $candidateDeparture,
        int $seatsNeeded = 1,
        ?float $pickupLat = null,
        ?float $pickupLng = null,
    ): bool {
        return $this->canAcceptSharedBookingInternal(
            $schedule,
            $candidateDeparture,
            $seatsNeeded,
            $pickupLat,
            $pickupLng,
            allowAssignedDriver: false,
        );
    }

    /** @return Collection<int, Schedule> */
    public function mergeCandidatesFor(Booking $booking): Collection
    {
        $booking->loadMissing(['schedule.route', 'schedule.bookings', 'schedule.vehicle']);

        if (($booking->booking_mode ?? 'shared') !== 'shared') {
            return collect();
        }

        if (in_array($booking->booking_status, ['cancelled', 'rejected'], true)) {
            return collect();
        }

        $schedule = $booking->schedule;

        if (! $schedule) {
            return collect();
        }

        if ($this->hasPendingMergeInvolving($schedule)) {
            return collect();
        }

        $start = $booking->tripStartAt() ?? $schedule->departure_time;

        return Schedule::query()
            ->with(['bookings', 'route'])
            ->where('template_id', $schedule->template_id)
            ->whereDate('service_date', $schedule->service_date)
            ->where('status', 'scheduled')
            ->where('id', '!=', $schedule->id)
            ->orderBy('departure_time')
            ->get()
            ->filter(fn (Schedule $other): bool => ! $this->hasPendingMergeBetween($other, $schedule)
                && $this->canAcceptSharedBookingForMerge(
                    $other,
                    $start,
                    max($booking->seatCount(), 1),
                    $booking->pickup_lat !== null ? (float) $booking->pickup_lat : null,
                    $booking->pickup_lng !== null ? (float) $booking->pickup_lng : null,
                ))
            ->values();
    }

    public function pendingMergeForPair(Schedule $target, Schedule $source): ?ScheduleMergeRequest
    {
        return ScheduleMergeRequest::query()
            ->where('target_schedule_id', $target->id)
            ->where('source_schedule_id', $source->id)
            ->where('status', ScheduleMergeRequest::STATUS_PENDING)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->first();
    }

    /** @return Collection<int, ScheduleMergeRequest> */
    public function pendingMergeRequestsForDriver(int $driverUserId): Collection
    {
        return ScheduleMergeRequest::query()
            ->with([
                'targetSchedule.route',
                'targetSchedule.bookings',
                'sourceSchedule.route',
                'sourceSchedule.bookings',
            ])
            ->where('driver_id', $driverUserId)
            ->where('status', ScheduleMergeRequest::STATUS_PENDING)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest()
            ->get();
    }

    /**
     * Gom chuyến — nếu đã có tài xế thì tạo yêu cầu chờ xác nhận.
     *
     * @return ScheduleMergeRequest|null Yêu cầu chờ tài xế khi chưa gom ngay.
     */
    public function mergeSchedules(Schedule $target, Schedule $source, ?int $operatorUserId = null): ?ScheduleMergeRequest
    {
        if ((int) $target->id === (int) $source->id) {
            throw new InvalidArgumentException('Không thể gom chuyến với chính nó.');
        }

        $existing = $this->pendingMergeForPair($target, $source);

        if ($existing) {
            return $existing;
        }

        $this->validateMergePair($target, $source, $operatorUserId);

        $driverId = $this->resolveMergeDriverId($target, $source);

        if ($driverId !== null) {
            return $this->proposeMergeToDriver($target, $source, $driverId, $operatorUserId);
        }

        $this->applyMergeSchedules($target, $source, $operatorUserId);

        return null;
    }

    public function acceptMergeRequest(ScheduleMergeRequest $request, int $driverUserId): void
    {
        DB::transaction(function () use ($request, $driverUserId): void {
            $locked = ScheduleMergeRequest::query()->lockForUpdate()->findOrFail($request->id);

            if ((int) $locked->driver_id !== $driverUserId) {
                throw new InvalidArgumentException('Bạn không phải tài xế được yêu cầu xác nhận.');
            }

            if (! $locked->isPending()) {
                throw new InvalidArgumentException('Yêu cầu gom chuyến không còn hiệu lực.');
            }

            $target = Schedule::query()->lockForUpdate()->findOrFail($locked->target_schedule_id);
            $source = Schedule::query()->lockForUpdate()->findOrFail($locked->source_schedule_id);

            $this->validateMergePair($target, $source, null, forDriverAccept: true);

            $this->applyMergeSchedules($target, $source, null);

            $locked->update([
                'status'       => ScheduleMergeRequest::STATUS_ACCEPTED,
                'responded_at' => now(),
            ]);
        });
    }

    public function rejectMergeRequest(ScheduleMergeRequest $request, int $driverUserId, ?string $note = null): void
    {
        DB::transaction(function () use ($request, $driverUserId, $note): void {
            $locked = ScheduleMergeRequest::query()->lockForUpdate()->findOrFail($request->id);

            if ((int) $locked->driver_id !== $driverUserId) {
                throw new InvalidArgumentException('Bạn không phải tài xế được yêu cầu xác nhận.');
            }

            if (! $locked->isPending()) {
                throw new InvalidArgumentException('Yêu cầu gom chuyến không còn hiệu lực.');
            }

            $locked->update([
                'status'       => ScheduleMergeRequest::STATUS_REJECTED,
                'responded_at' => now(),
                'driver_note'  => $note ? trim($note) : null,
            ]);
        });
    }

    public function expireStaleMergeRequests(): int
    {
        return ScheduleMergeRequest::query()
            ->where('status', ScheduleMergeRequest::STATUS_PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update([
                'status'       => ScheduleMergeRequest::STATUS_EXPIRED,
                'responded_at' => now(),
            ]);
    }

    /** Gom tự động các chuyến ghép cùng tuyến/ngày khi chưa có tài xế. */
    public function autoConsolidateOpenSchedules(): int
    {
        $merged = 0;

        $groups = Schedule::query()
            ->with(['bookings', 'vehicle'])
            ->where('status', 'scheduled')
            ->whereNotNull('service_date')
            ->where('departure_time', '>', now())
            ->whereNull('driver_id')
            ->whereHas('bookings', fn ($q) => $q
                ->whereNotIn('booking_status', ['cancelled', 'rejected'])
                ->where('booking_mode', 'shared'))
            ->orderBy('template_id')
            ->orderBy('service_date')
            ->orderBy('departure_time')
            ->get()
            ->groupBy(fn (Schedule $s): string => $s->template_id . '|' . $s->service_date);

        foreach ($groups as $schedules) {
            /** @var Collection<int, Schedule> $open */
            $open = $schedules
                ->filter(fn (Schedule $s): bool => $this->scheduleHasOnlySharedBookings($s)
                    && ! $this->scheduleHasOperatorQueueBookings($s)
                    && ! $this->hasPendingMergeInvolving($s))
                ->values();

            while ($open->count() > 1) {
                $mergedPair = false;

                for ($i = 0; $i < $open->count(); $i++) {
                    for ($j = $i + 1; $j < $open->count(); $j++) {
                        $a = $open[$i];
                        $b = $open[$j];

                        $target = $a->bookedSeatsCount() >= $b->bookedSeatsCount() ? $a : $b;
                        $source = $target->id === $a->id ? $b : $a;

                        if (! $this->canMergePair($target, $source)) {
                            continue;
                        }

                        try {
                            $this->applyMergeSchedules($target, $source);
                            $merged++;
                            $open = $open
                                ->reject(fn (Schedule $s): bool => (int) $s->id === (int) $source->id)
                                ->values();
                            $mergedPair = true;
                            break 2;
                        } catch (InvalidArgumentException) {
                            continue;
                        }
                    }
                }

                if (! $mergedPair) {
                    break;
                }
            }
        }

        return $merged;
    }

    private function proposeMergeToDriver(
        Schedule $target,
        Schedule $source,
        int $driverId,
        ?int $operatorUserId,
    ): ScheduleMergeRequest {
        return ScheduleMergeRequest::query()->create([
            'target_schedule_id' => $target->id,
            'source_schedule_id' => $source->id,
            'driver_id'          => $driverId,
            'requested_by'       => $operatorUserId,
            'status'             => ScheduleMergeRequest::STATUS_PENDING,
            'expires_at'         => now()->addHours(self::MERGE_APPROVAL_HOURS),
        ]);
    }

    private function applyMergeSchedules(Schedule $target, Schedule $source, ?int $operatorUserId): void
    {
        DB::transaction(function () use ($target, $source, $operatorUserId): void {
            $target = Schedule::query()->lockForUpdate()->with(['bookings', 'vehicle'])->findOrFail($target->id);
            $source = Schedule::query()->lockForUpdate()->with(['bookings', 'vehicle'])->findOrFail($source->id);

            if ($operatorUserId !== null) {
                $this->assertOperatorOwnsSchedule($operatorUserId, $target);
                $this->assertOperatorOwnsSchedule($operatorUserId, $source);
            }

            $moving = $source->bookings()
                ->whereNotIn('booking_status', ['cancelled', 'rejected'])
                ->lockForUpdate()
                ->get();

            if ($moving->isEmpty()) {
                throw new InvalidArgumentException('Chuyến nguồn không còn khách để gom.');
            }

            foreach ($moving as $booking) {
                $booking->update(['schedule_id' => $target->id]);
                $booking->seatReservations()->update(['schedule_id' => $target->id]);
            }

            $driverId = (int) ($target->driver_id ?: $source->driver_id);

            if ($driverId > 0) {
                $driverName = $target->driver_name ?: $source->driver_name;

                $target->update([
                    'driver_id'   => $driverId,
                    'driver_name' => $driverName ?: $target->driver_name,
                ]);

                foreach ($target->fresh(['bookings'])->driverRelevantBookings() as $booking) {
                    $booking->update(['assigned_driver_id' => $driverId]);
                }
            }

            ScheduleMergeRequest::query()
                ->where('target_schedule_id', $target->id)
                ->where('source_schedule_id', $source->id)
                ->where('status', ScheduleMergeRequest::STATUS_PENDING)
                ->update([
                    'status'       => ScheduleMergeRequest::STATUS_CANCELLED,
                    'responded_at' => now(),
                ]);

            DriverTripRequest::query()
                ->where('schedule_id', $source->id)
                ->whereIn('status', ['pending', 'accepted'])
                ->update([
                    'status'       => 'cancelled',
                    'responded_at' => now(),
                ]);

            $this->refreshScheduleDeparture($target->fresh(['bookings']));
            app(BookingWorkflowService::class)->syncScheduleAvailability($target->fresh());
            app(BookingWorkflowService::class)->syncScheduleAvailability($source->fresh());

            if (! $source->bookings()->whereNotIn('booking_status', ['cancelled', 'rejected'])->exists()) {
                $source->update([
                    'status'      => 'cancelled',
                    'driver_id'   => null,
                    'driver_name' => 'Chờ phân bổ',
                ]);
            }
        });
    }

    private function validateMergePair(
        Schedule $target,
        Schedule $source,
        ?int $operatorUserId,
        bool $forDriverAccept = false,
    ): void {
        if ((int) $target->template_id !== (int) $source->template_id) {
            throw new InvalidArgumentException('Hai chuyến không cùng tuyến.');
        }

        if ($operatorUserId !== null) {
            $this->assertOperatorOwnsSchedule($operatorUserId, $target);
            $this->assertOperatorOwnsSchedule($operatorUserId, $source);
        }

        $moving = $source->bookings()
            ->whereNotIn('booking_status', ['cancelled', 'rejected'])
            ->get();

        if ($moving->isEmpty()) {
            throw new InvalidArgumentException('Chuyến nguồn không còn khách để gom.');
        }

        foreach ($moving as $booking) {
            if (($booking->booking_mode ?? 'shared') !== 'shared') {
                throw new InvalidArgumentException('Chỉ gom được đơn ghép xe.');
            }

            $start = $booking->tripStartAt() ?? $source->departure_time;

            if (! $this->canAcceptSharedBookingForMerge(
                $target,
                $start,
                max($booking->seatCount(), 1),
                $booking->pickup_lat !== null ? (float) $booking->pickup_lat : null,
                $booking->pickup_lng !== null ? (float) $booking->pickup_lng : null,
            )) {
                throw new InvalidArgumentException(
                    $forDriverAccept
                        ? 'Chuyến không còn đủ điều kiện gom (giờ, ghế hoặc trạng thái đã thay đổi).'
                        : 'Chuyến đích không đủ điều kiện gom (giờ, ghế, tài xế hoặc khoảng cách đón).',
                );
            }
        }

        $this->resolveMergeDriverId($target, $source);
    }

    private function canAcceptSharedBookingForMerge(
        Schedule $schedule,
        Carbon $candidateDeparture,
        int $seatsNeeded = 1,
        ?float $pickupLat = null,
        ?float $pickupLng = null,
    ): bool {
        return $this->canAcceptSharedBookingInternal(
            $schedule,
            $candidateDeparture,
            $seatsNeeded,
            $pickupLat,
            $pickupLng,
            allowAssignedDriver: true,
        );
    }

    private function canAcceptSharedBookingInternal(
        Schedule $schedule,
        Carbon $candidateDeparture,
        int $seatsNeeded,
        ?float $pickupLat,
        ?float $pickupLng,
        bool $allowAssignedDriver,
    ): bool {
        $schedule->loadMissing(['bookings', 'vehicle']);

        if ($schedule->status !== 'scheduled') {
            return false;
        }

        if ($schedule->departure_time->lte(now())) {
            return false;
        }

        $seatsNeeded = max($seatsNeeded, 1);

        if ($schedule->bookedSeatsCount() + $seatsNeeded > $schedule->capacity()) {
            return false;
        }

        if (! $this->scheduleHasOnlySharedBookings($schedule)) {
            return false;
        }

        if ($this->scheduleHasOperatorQueueBookings($schedule)) {
            return false;
        }

        if ($this->hasPendingMergeInvolving($schedule)) {
            return false;
        }

        if ($allowAssignedDriver) {
            if (! $this->driverStageAllowsMergeApproval($schedule)) {
                return false;
            }
        } elseif (! $this->driverStageAllowsPooling($schedule)) {
            return false;
        }

        if (! $this->withinPoolTimeWindow($schedule, $candidateDeparture)) {
            return false;
        }

        if (! $this->withinPickupSpread($schedule, $pickupLat, $pickupLng)) {
            return false;
        }

        return true;
    }

    private function canMergePair(Schedule $target, Schedule $source): bool
    {
        $source->loadMissing('bookings');

        foreach ($source->bookings()->whereNotIn('booking_status', ['cancelled', 'rejected'])->get() as $booking) {
            $start = $booking->tripStartAt() ?? $source->departure_time;

            if (! $this->canAcceptSharedBooking(
                $target,
                $start,
                max($booking->seatCount(), 1),
                $booking->pickup_lat !== null ? (float) $booking->pickup_lat : null,
                $booking->pickup_lng !== null ? (float) $booking->pickup_lng : null,
            )) {
                return false;
            }
        }

        return true;
    }

    private function resolveMergeDriverId(Schedule $target, Schedule $source): ?int
    {
        $targetDriver = (int) $target->driver_id;
        $sourceDriver = (int) $source->driver_id;

        if ($targetDriver > 0 && $sourceDriver > 0 && $targetDriver !== $sourceDriver) {
            throw new InvalidArgumentException('Hai chuyến đang có hai tài xế khác nhau — không thể gom.');
        }

        $driverId = $targetDriver > 0 ? $targetDriver : ($sourceDriver > 0 ? $sourceDriver : null);

        if ($driverId === null) {
            return null;
        }

        $host = $targetDriver > 0 ? $target : $source;

        if (! $this->driverStageAllowsMergeApproval($host)) {
            throw new InvalidArgumentException('Tài xế đã đón khách hoặc đang chạy — không thể gom thêm.');
        }

        return $driverId;
    }

    private function hasPendingMergeBetween(Schedule $target, Schedule $source): bool
    {
        return $this->pendingMergeForPair($target, $source) !== null;
    }

    private function hasPendingMergeInvolving(Schedule $schedule): bool
    {
        if (! $schedule->id) {
            return false;
        }

        return ScheduleMergeRequest::query()
            ->where('status', ScheduleMergeRequest::STATUS_PENDING)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where(fn ($q) => $q
                ->where('target_schedule_id', $schedule->id)
                ->orWhere('source_schedule_id', $schedule->id))
            ->exists();
    }

    private function scheduleHasOnlySharedBookings(Schedule $schedule): bool
    {
        $active = $schedule->bookings
            ->filter(fn (Booking $b): bool => ! in_array($b->booking_status, ['cancelled', 'rejected'], true));

        if ($active->isEmpty()) {
            return false;
        }

        return $active->every(fn (Booking $b): bool => ($b->booking_mode ?? 'shared') === 'shared');
    }

    private function scheduleHasOperatorQueueBookings(Schedule $schedule): bool
    {
        return $schedule->bookings->contains(
            fn (Booking $b): bool => ! in_array($b->booking_status, ['cancelled', 'rejected'], true)
                && ($b->needs_operator_help_at !== null || $b->trip_status === 'awaiting_completion'),
        );
    }

    /** Tự gom khi đặt vé — chỉ chuyến chưa có tài xế. */
    private function driverStageAllowsPooling(Schedule $schedule): bool
    {
        return ! $schedule->driver_id;
    }

    /** Gom qua quản lý — tài xế còn trước bước đón khách. */
    private function driverStageAllowsMergeApproval(Schedule $schedule): bool
    {
        if (! $schedule->driver_id) {
            return true;
        }

        return in_array($schedule->resolvedDriverStage(), [
            Schedule::DRIVER_STAGE_ASSIGNED,
            Schedule::DRIVER_STAGE_AT_PICKUP,
        ], true);
    }

    private function withinPoolTimeWindow(Schedule $schedule, Carbon $candidateDeparture): bool
    {
        $anchors = collect([$schedule->departure_time->copy()]);

        foreach ($schedule->bookings as $booking) {
            if (in_array($booking->booking_status, ['cancelled', 'rejected'], true)) {
                continue;
            }

            $start = $booking->tripStartAt();

            if ($start) {
                $anchors->push($start->copy());
            }
        }

        return $anchors->contains(
            fn (Carbon $anchor): bool => abs($candidateDeparture->diffInMinutes($anchor)) <= self::POOL_WINDOW_MINUTES,
        );
    }

    private function withinPickupSpread(Schedule $schedule, ?float $pickupLat, ?float $pickupLng): bool
    {
        if ($pickupLat === null || $pickupLng === null) {
            return true;
        }

        $existing = $schedule->bookings
            ->filter(fn (Booking $b): bool => ! in_array($b->booking_status, ['cancelled', 'rejected'], true)
                && $b->pickup_lat !== null
                && $b->pickup_lng !== null);

        if ($existing->isEmpty()) {
            return true;
        }

        foreach ($existing as $booking) {
            $km = ProvinceCenters::distanceKm(
                $pickupLat,
                $pickupLng,
                (float) $booking->pickup_lat,
                (float) $booking->pickup_lng,
            );

            if ($km > self::MAX_PICKUP_SPREAD_KM) {
                return false;
            }
        }

        return true;
    }

    private function refreshScheduleDeparture(Schedule $schedule): void
    {
        $schedule->loadMissing('bookings');

        $earliest = $schedule->bookings
            ->filter(fn (Booking $b): bool => ! in_array($b->booking_status, ['cancelled', 'rejected'], true))
            ->map(fn (Booking $b): ?Carbon => $b->tripStartAt() ?? $schedule->departure_time)
            ->filter()
            ->min();

        if (! $earliest instanceof Carbon) {
            return;
        }

        if ($earliest->equalTo($schedule->departure_time)) {
            return;
        }

        $schedule->update([
            'departure_time' => $earliest->copy()->startOfMinute(),
        ]);
    }

    private function assertOperatorOwnsSchedule(int $operatorUserId, Schedule $schedule): void
    {
        $schedule->loadMissing('vehicle');

        if ((int) ($schedule->vehicle?->operator_id ?? 0) !== $operatorUserId) {
            throw new InvalidArgumentException('Bạn không có quyền thao tác chuyến này.');
        }
    }
}
