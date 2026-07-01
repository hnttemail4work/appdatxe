<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\DriverTripRequest;
use App\Models\Schedule;
use App\Models\TripReview;
use App\Support\AuthIdentifier;

class GuestTripWatchService
{
    public const SESSION_KEY = 'guest_review_watchlist';

    public const MAX_WATCHLIST = 10;

    public const REVIEW_WINDOW_DAYS = 2;

    /** Tự reload trang đặt xe khi đang tìm tài xế. */
    public const GUEST_PAGE_RELOAD_SECONDS = 180;

    /** Đồng bộ với {@see DriverTripRequestService::OPERATOR_ESCALATION_MINUTES}. */
    public const STALE_DRIVER_SEARCH_MINUTES = 15;

    public function watchlistCount(): int
    {
        return count($this->rawWatchlist());
    }

    public function addToWatchlist(string $bookingReference, string $contactPhone): void
    {
        $ref = trim($bookingReference);
        $phone = trim($contactPhone);

        if ($ref === '' || $phone === '') {
            return;
        }

        $list = $this->rawWatchlist();
        $list = collect($list)
            ->reject(fn (array $item): bool => ($item['ref'] ?? '') === $ref)
            ->prepend([
                'ref'      => $ref,
                'phone'    => $phone,
                'added_at' => now()->toIso8601String(),
            ])
            ->take(self::MAX_WATCHLIST)
            ->values()
            ->all();

        session([self::SESSION_KEY => $list]);
    }

    /** @return list<array<string, mixed>> */
    public function visibleTrips(): array
    {
        app(DriverTripRequestService::class)->expireStale();

        $entries = $this->pruneWatchlist();
        if ($entries === []) {
            return [];
        }

        $refs = collect($entries)->pluck('ref')->filter()->unique()->values()->all();

        $bookings = Booking::query()
            ->with([
                'schedule.route',
                'schedule.driver.driverProfile',
                'tripReview',
            ])
            ->whereIn('booking_reference', $refs)
            ->get()
            ->keyBy('booking_reference');

        $trips = [];

        foreach ($entries as $entry) {
            $ref = (string) ($entry['ref'] ?? '');
            $phone = (string) ($entry['phone'] ?? '');
            $booking = $bookings->get($ref);

            if (! $booking || ! $booking->matchesContactPhone($phone)) {
                continue;
            }

            if (! $this->shouldDisplay($booking)) {
                continue;
            }

            $trips[] = $this->serializeTrip($booking, $phone);
        }

        return $trips;
    }

    public function bookingInWatchlist(Booking $booking, string $contactPhone): bool
    {
        foreach ($this->rawWatchlist() as $entry) {
            if (($entry['ref'] ?? '') === $booking->booking_reference
                && $booking->matchesContactPhone((string) ($entry['phone'] ?? ''))
                && $booking->matchesContactPhone($contactPhone)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<array{ref: string, phone: string, added_at: string}> */
    private function pruneWatchlist(): array
    {
        $kept = [];
        $refs = collect($this->rawWatchlist())->pluck('ref')->filter()->unique()->values()->all();

        $bookings = $refs === []
            ? collect()
            : Booking::query()
                ->with(['tripReview'])
                ->whereIn('booking_reference', $refs)
                ->get()
                ->keyBy('booking_reference');

        foreach ($this->rawWatchlist() as $entry) {
            $ref = (string) ($entry['ref'] ?? '');
            $phone = (string) ($entry['phone'] ?? '');

            if ($ref === '' || $phone === '') {
                continue;
            }

            $booking = $bookings->get($ref);
            if (! $booking || ! $booking->matchesContactPhone($phone)) {
                continue;
            }

            if ($this->shouldKeepInWatchlist($booking)) {
                $kept[] = [
                    'ref'      => $ref,
                    'phone'    => $phone,
                    'added_at' => (string) ($entry['added_at'] ?? now()->toIso8601String()),
                ];
            }
        }

        session([self::SESSION_KEY => $kept]);

        return $kept;
    }

    private function shouldKeepInWatchlist(Booking $booking): bool
    {
        if ($booking->isOperatorDismissed()) {
            return false;
        }

        if (in_array($booking->booking_status, ['cancelled', 'rejected'], true)
            || $booking->trip_status === 'cancelled') {
            return false;
        }

        if ($booking->tripReview) {
            return false;
        }

        if ($booking->trip_status === 'completed' && ! $this->withinReviewWindow($booking)) {
            return false;
        }

        return true;
    }

    /** @deprecated Chỉ còn gọi escalate — không ẩn đơn khách sau 15 phút. */
    public function expireStaleDriverSearches(): int
    {
        app(DriverTripRequestService::class)->escalateDriverSearchTimeouts();

        return 0;
    }

    public function isStaleDriverSearch(Booking $booking): bool
    {
        $booking->loadMissing('schedule');

        if (! $booking->schedule || $booking->schedule->driver_id) {
            return false;
        }

        if (in_array($booking->booking_status, ['cancelled', 'rejected'], true)
            || $booking->trip_status === 'completed') {
            return false;
        }

        $startedAt = $booking->driver_search_started_at ?? $booking->created_at;

        return $startedAt && $startedAt->lte(now()->subMinutes(self::STALE_DRIVER_SEARCH_MINUTES));
    }

    public function shouldDisplay(Booking $booking): bool
    {
        return $this->shouldKeepInWatchlist($booking);
    }

    public function canReview(Booking $booking): bool
    {
        if ($booking->tripReview) {
            return false;
        }

        if ($booking->trip_status !== 'completed') {
            return false;
        }

        return $this->withinReviewWindow($booking);
    }

    public function withinReviewWindow(Booking $booking): bool
    {
        if (! $booking->completed_at) {
            return false;
        }

        return $booking->completed_at->gte(now()->subDays(self::REVIEW_WINDOW_DAYS));
    }

    public function progressKey(Booking $booking): string
    {
        $booking->loadMissing('schedule');

        if ($booking->trip_status === 'completed') {
            return 'completed';
        }

        $schedule = $booking->schedule;
        if (! $schedule) {
            return 'booked';
        }

        if ($schedule->driver_id) {
            return match ($schedule->resolvedDriverStage()) {
                Schedule::DRIVER_STAGE_RUNNING   => 'running',
                Schedule::DRIVER_STAGE_PICKED_UP => 'picked_up',
                Schedule::DRIVER_STAGE_AT_PICKUP => 'driver_at_pickup',
                Schedule::DRIVER_STAGE_COMPLETED => 'completed',
                default                          => 'driver_assigned',
            };
        }

        if ($booking->needs_operator_help_at) {
            return 'needs_operator_help';
        }

        return 'searching_driver';
    }

    public function canCancel(Booking $booking): bool
    {
        if (in_array($booking->booking_status, ['cancelled', 'rejected'], true)
            || $booking->trip_status === 'cancelled') {
            return false;
        }

        if ($booking->trip_status === 'completed') {
            return false;
        }

        return true;
    }

    /** @return array<string, mixed> */
    private function serializeTrip(Booking $booking, string $contactPhone): array
    {
        $booking->loadMissing('schedule.route', 'schedule.driver.driverProfile');
        $schedule = $booking->schedule;
        $progress = $this->progressKey($booking);
        $hasDriver = (int) ($schedule?->driver_id ?? 0) > 0;
        $driverName = $hasDriver
            ? ($schedule->driver?->name ?: ($schedule->driver_name ?: null))
            : null;

        $driverProfile = $hasDriver ? $schedule?->driver?->driverProfile : null;

        $vehicleLabel = $driverProfile?->vehicle_type;
        $vehicleSeats = $driverProfile?->vehicle_seats ? (int) $driverProfile->vehicle_seats : null;
        $vehiclePlate = $driverProfile?->vehicle_license_plate;

        $expiresAt = null;
        if ($booking->completed_at && $this->canReview($booking)) {
            $expiresAt = $booking->completed_at->copy()->addDays(self::REVIEW_WINDOW_DAYS)->toIso8601String();
        }

        return [
            'booking_ref'        => $booking->booking_reference,
            'contact_phone'      => $contactPhone,
            'trip_code'          => $schedule?->shortTripCode() ?? '—',
            'route'              => $schedule?->route
                ? $schedule->route->departure . ' → ' . $schedule->route->destination
                : '—',
            'service_date'       => $schedule?->departure_time?->format('d/m/Y H:i'),
            'driver_name'        => $driverName,
            'driver_initial'     => $driverName ? mb_substr($driverName, 0, 1) : null,
            'driver_photo_url'   => $driverProfile?->photoUrl('photo_portrait'),
            'vehicle_photo_url'  => $driverProfile?->firstVehiclePhotoUrl(),
            'driver_pending'     => ! $hasDriver,
            'driver_distance_km' => $booking->driver_pickup_distance_km !== null
                ? (float) $booking->driver_pickup_distance_km
                : null,
            'driver_distance_label' => $booking->driver_pickup_distance_km !== null
                ? \App\Services\DriverProximityService::formatDistanceLabel((float) $booking->driver_pickup_distance_km)
                : null,
            'vehicle_type'       => $vehicleLabel,
            'vehicle_seats'      => $vehicleSeats,
            'vehicle_plate'      => $vehiclePlate,
            'vehicle_count'      => max((int) ($booking->vehicle_count ?? 1), 1),
            'vehicle_capacity'   => (int) ($booking->vehicle_capacity ?? $vehicleSeats ?? 0),
            'vehicle_booking_label' => $booking->vehicleBookingLabel(),
            'progress'           => $progress,
            'progress_label'     => match ($progress) {
                'searching_driver'   => 'Đang tìm tài xế',
                'needs_operator_help'=> 'Đang tìm tài xế',
                'driver_assigned'    => 'Đã có tài xế',
                'driver_at_pickup'   => 'Tài xế đến điểm đón',
                'picked_up'          => 'Đã đón khách',
                'running'            => 'Đang chạy',
                'completed'          => 'Hoàn thành',
                default              => 'Đã đặt',
            },
            'needs_operator_help' => $booking->needs_operator_help_at !== null,
            'can_review'         => $this->canReview($booking),
            'can_cancel'         => $this->canCancel($booking),
            'reviewed'           => $booking->tripReview !== null,
            'expires_review_at'  => $expiresAt,
            'requires_cancel_reason' => app(\App\Services\BookingPhoneGuardService::class)
                ->requiresCancelReason($contactPhone),
            'wait_progress'          => $this->serializeWaitProgress($booking, $progress, $contactPhone),
        ];
    }

    /** @return array<string, mixed>|null */
    private function serializeWaitProgress(Booking $booking, string $progress, string $contactPhone): ?array
    {
        if ($progress === 'completed' && $this->canReview($booking)) {
            if (! $booking->completed_at) {
                return null;
            }

            $deadline = $booking->completed_at->copy()->addDays(self::REVIEW_WINDOW_DAYS);

            return [
                'kind'           => 'review',
                'label'          => 'Thời gian đánh giá',
                'hint'           => 'Gửi phản hồi trước khi hết hạn',
                'started_at'     => $booking->completed_at->toIso8601String(),
                'deadline_at'    => $deadline->toIso8601String(),
                'total_seconds'  => self::REVIEW_WINDOW_DAYS * 86400,
                'indeterminate'  => false,
            ];
        }

        $booking->loadMissing('schedule');
        if (! $booking->schedule || $booking->schedule->driver_id) {
            return null;
        }

        $pendingRequest = DriverTripRequest::query()
            ->where('schedule_id', $booking->schedule_id)
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->get()
            ->first(function (DriverTripRequest $request) use ($contactPhone): bool {
                $phone = trim((string) ($request->contact_phone ?? ''));

                if ($phone === '') {
                    return true;
                }

                return AuthIdentifier::normalizePhone($phone) === AuthIdentifier::normalizePhone($contactPhone);
            });

        if ($pendingRequest?->expires_at?->isFuture()) {
            $started = $pendingRequest->created_at ?? now();
            $totalSeconds = max(60, (int) $started->diffInSeconds($pendingRequest->expires_at));

            return [
                'kind'           => 'driver_accept',
                'label'          => 'Chờ tài xế xác nhận',
                'hint'           => 'Tài xế đang xem thông tin chuyến của bạn',
                'started_at'     => $started->toIso8601String(),
                'deadline_at'    => $pendingRequest->expires_at->toIso8601String(),
                'total_seconds'  => $totalSeconds,
                'indeterminate'  => false,
            ];
        }

        if (! in_array($progress, ['searching_driver', 'needs_operator_help'], true)) {
            return null;
        }

        $searchStarted = $booking->driver_search_started_at ?? $booking->created_at ?? now();
        $escalationMinutes = DriverTripRequestService::OPERATOR_ESCALATION_MINUTES;
        $escalationDeadline = $searchStarted->copy()->addMinutes($escalationMinutes);

        if ($progress === 'searching_driver' && $escalationDeadline->isFuture()) {
            return [
                'kind'           => 'driver_search',
                'label'          => 'Đang tìm tài xế',
                'hint'           => 'Ưu tiên ghép tài xế gần điểm đón',
                'started_at'     => $searchStarted->toIso8601String(),
                'deadline_at'    => $escalationDeadline->toIso8601String(),
                'total_seconds'  => $escalationMinutes * 60,
                'indeterminate'  => false,
            ];
        }

        $extendedStart = $booking->needs_operator_help_at ?? $searchStarted;

        return [
            'kind'           => 'driver_search_extended',
            'label'          => 'Đang tìm tài xế',
            'hint'           => 'Đang tiếp tục tìm phù hợp nhất cho bạn',
            'started_at'     => $extendedStart->toIso8601String(),
            'deadline_at'    => null,
            'total_seconds'  => 0,
            'indeterminate'  => true,
        ];
    }

    /** @return list<array{ref: string, phone: string, added_at: string}> */
    private function rawWatchlist(): array
    {
        $list = session(self::SESSION_KEY, []);

        return is_array($list) ? $list : [];
    }
}
