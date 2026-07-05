<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\TripReview;
use App\Support\AuthIdentifier;
use App\Support\GuestWaitProgress;
use Illuminate\Support\Facades\Cache;

class GuestTripStatusService
{
    public function __construct(
        private readonly DuplicateBookingService $duplicateBookings,
        private readonly BookingBrowserGuardService $browserGuard,
        private readonly DriverTripRequestService $driverTripRequests,
    ) {
    }

    public function resolve(?string $browserId, ?string $phone, ?string $bookingReference): ?Booking
    {
        $this->driverTripRequests->expireStale();

        if ($bookingReference !== null && $bookingReference !== '') {
            $booking = Booking::query()
                ->with(['schedule.route', 'tripReview'])
                ->where('booking_reference', $bookingReference)
                ->first();

            if ($booking && $this->guestCanView($booking, $browserId, $phone)) {
                return $booking;
            }
        }

        if ($browserId !== null && $browserId !== '') {
            $browserActive = $this->browserGuard->findActiveBooking($browserId);
            if ($browserActive) {
                return $browserActive->loadMissing(['schedule.route', 'tripReview']);
            }

            $cachedReference = Cache::get($this->browserGuard->activeBookingCacheKey($browserId));
            if (is_string($cachedReference) && $cachedReference !== '') {
                $booking = Booking::query()
                    ->with(['schedule.route', 'tripReview'])
                    ->where('booking_reference', $cachedReference)
                    ->first();

                if ($booking && $this->guestCanView($booking, $browserId, $phone)) {
                    return $booking;
                }
            }
        }

        if ($phone !== null && $phone !== '') {
            $phoneActive = $this->duplicateBookings->findActiveBooking($phone);
            if ($phoneActive) {
                return $phoneActive->loadMissing(['schedule.route', 'tripReview']);
            }

            $latest = Booking::query()
                ->with(['schedule.route', 'tripReview'])
                ->whereNotNull('contact_phone')
                ->orderByDesc('created_at')
                ->limit(20)
                ->get()
                ->first(fn (Booking $booking): bool => $booking->matchesContactPhone($phone)
                    && ($booking->blocksGuestRebooking()
                        || ($booking->trip_status === 'completed' && ! $booking->tripReview)));

            if ($latest) {
                return $latest;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    public function serialize(Booking $booking): array
    {
        $booking->loadMissing('schedule.route', 'tripReview');
        $base = $this->duplicateBookings->serializeDuplicate($booking);

        return array_merge($base, [
            'passenger_name'    => $booking->passenger_name,
            'pickup_address'    => $booking->pickup_address,
            'pickup_detail'     => $booking->pickup_detail,
            'dropoff_address'   => $booking->dropoff_address,
            'dropoff_detail'    => $booking->dropoff_detail,
            'pickup_time_label' => $booking->pickupTimeLabel(),
            'trip_status'       => $booking->trip_status,
            'booking_status'    => $booking->booking_status,
            'total_price'       => (float) $booking->total_price,
            'is_active'         => $booking->blocksGuestRebooking(),
            'can_review'        => $booking->trip_status === 'completed' && ! $booking->tripReview,
            'review'            => $this->serializeReview($booking),
            'wait_progress'     => GuestWaitProgress::forBooking($booking),
        ]);
    }

    /** @return array<string, mixed>|null */
    private function serializeReview(Booking $booking): ?array
    {
        $review = $booking->tripReview;
        if (! $review) {
            return null;
        }

        return [
            'sentiment'   => $review->sentiment,
            'comment'     => $review->comment,
            'label'       => $review->driverPreferenceLabel(),
            'icon'        => $review->sentimentIcon(),
            'created_at'  => $review->created_at?->format('d/m/Y H:i'),
        ];
    }

    public function guestCanView(Booking $booking, ?string $browserId, ?string $phone): bool
    {
        if ($phone !== null && $phone !== '' && $booking->matchesContactPhone($phone)) {
            return true;
        }

        if ($browserId !== null && $browserId !== '') {
            $cachedBrowser = Cache::get($this->browserGuard->bookingRefCacheKey((string) $booking->booking_reference));
            if (is_string($cachedBrowser) && hash_equals($cachedBrowser, $browserId)) {
                return true;
            }

            $activeReference = Cache::get($this->browserGuard->activeBookingCacheKey($browserId));
            if (is_string($activeReference) && hash_equals($activeReference, (string) $booking->booking_reference)) {
                return true;
            }
        }

        return false;
    }

    public function storeReview(
        Booking $booking,
        string $sentiment,
        ?string $comment,
        ?string $browserId,
        ?string $phone,
    ): TripReview {
        if (! $this->guestCanView($booking, $browserId, $phone)) {
            throw new \InvalidArgumentException('Không xác thực được chuyến đi.');
        }

        if ($booking->trip_status !== 'completed') {
            throw new \InvalidArgumentException('Chỉ đánh giá được sau khi chuyến hoàn tất.');
        }

        if ($booking->tripReview) {
            throw new \InvalidArgumentException('Bạn đã đánh giá chuyến này.');
        }

        $booking->loadMissing('schedule');

        return TripReview::query()->create([
            'booking_id'        => $booking->id,
            'schedule_id'       => $booking->schedule_id,
            'driver_id'         => $booking->schedule?->driver_id,
            'driver_profile_id' => $booking->activeDriverProfile()?->id,
            'sentiment'         => $sentiment,
            'comment'           => $comment,
            'contact_phone'     => AuthIdentifier::normalizePhone((string) ($phone ?: $booking->contact_phone)),
        ]);
    }
}
