<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\DriverProfile;
use App\Models\TripReview;

class GuestTripWatchService
{
    public const SESSION_KEY = 'guest_review_watchlist';

    public const MAX_WATCHLIST = 10;

    public const REVIEW_WINDOW_DAYS = 2;

    /** Tự reload trang đặt xe khi đang tìm tài xế. */
    public const GUEST_PAGE_RELOAD_SECONDS = 180;

    /** Ẩn khách / quản lý nếu quá lâu không có tài xế. */
    public const STALE_DRIVER_SEARCH_MINUTES = 15;

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
        $this->expireStaleDriverSearches();

        $entries = $this->pruneWatchlist();
        if ($entries === []) {
            return [];
        }

        $refs = collect($entries)->pluck('ref')->filter()->unique()->values()->all();

        $bookings = Booking::query()
            ->with([
                'schedule.route',
                'schedule.driver',
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
        if ($this->isStaleDriverSearch($booking)) {
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

    /** Ẩn đơn tìm tài xế quá hạn khỏi session khách và dashboard quản lý. */
    public function expireStaleDriverSearches(): int
    {
        if (! Booking::supportsOperatorDismiss()) {
            return 0;
        }

        $cutoff = now()->subMinutes(self::STALE_DRIVER_SEARCH_MINUTES);

        return Booking::query()
            ->whereNotIn('booking_status', ['cancelled', 'rejected'])
            ->where('trip_status', '!=', 'completed')
            ->whereNull('operator_dismissed_at')
            ->where(function ($query) use ($cutoff): void {
                $query->where(function ($q) use ($cutoff): void {
                    $q->whereNotNull('driver_search_started_at')
                        ->where('driver_search_started_at', '<=', $cutoff);
                })->orWhere(function ($q) use ($cutoff): void {
                    $q->whereNull('driver_search_started_at')
                        ->where('created_at', '<=', $cutoff);
                });
            })
            ->whereHas('schedule', fn ($q) => $q->whereNull('driver_id'))
            ->update(['operator_dismissed_at' => now()]);
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

        if ($schedule->status === 'running') {
            return 'running';
        }

        if ($schedule->departure_time && $schedule->departure_time <= now()) {
            return 'running';
        }

        if (! $schedule->driver_id) {
            return 'searching_driver';
        }

        return 'driver_assigned';
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
        $booking->loadMissing('schedule.route', 'schedule.driver');
        $schedule = $booking->schedule;
        $progress = $this->progressKey($booking);
        $driverName = $schedule?->driver_name
            ?: $schedule?->driver?->name
            ?: null;

        $driverProfile = null;
        if ($schedule?->driver_id) {
            $driverProfile = DriverProfile::query()
                ->where('user_id', $schedule->driver_id)
                ->first();
        }

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
            'driver_pending'     => $driverName === null,
            'vehicle_type'       => $vehicleLabel,
            'vehicle_seats'      => $vehicleSeats,
            'vehicle_plate'      => $vehiclePlate,
            'progress'           => $progress,
            'progress_label'     => match ($progress) {
                'searching_driver' => 'Đang tìm tài xế',
                'driver_assigned'  => 'Đã có tài xế',
                'running'          => 'Đang chạy',
                'completed'        => 'Hoàn thành',
                default            => 'Đã đặt',
            },
            'can_review'         => $this->canReview($booking),
            'can_cancel'         => $this->canCancel($booking),
            'reviewed'           => $booking->tripReview !== null,
            'expires_review_at'  => $expiresAt,
            'requires_cancel_reason' => app(\App\Services\BookingPhoneGuardService::class)
                ->requiresCancelReason($contactPhone),
        ];
    }

    /** @return list<array{ref: string, phone: string, added_at: string}> */
    private function rawWatchlist(): array
    {
        $list = session(self::SESSION_KEY, []);

        return is_array($list) ? $list : [];
    }
}
