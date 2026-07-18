<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\TripReview;
use App\Support\AuthIdentifier;
use App\Support\GuestWaitProgress;
use App\Support\Money;
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

            if ($booking && ! $booking->shouldHideFromGuestAndOperatorActiveLists()
                && $this->guestCanView($booking, $browserId, $phone)) {
                return $booking;
            }
        }

        if ($browserId !== null && $browserId !== '') {
            $browserActive = $this->browserGuard->findActiveBooking($browserId);
            if ($browserActive && ! $browserActive->shouldHideFromGuestAndOperatorActiveLists()) {
                return $browserActive->loadMissing(['schedule.route', 'tripReview']);
            }

            $cachedReference = Cache::get($this->browserGuard->activeBookingCacheKey($browserId));
            if (is_string($cachedReference) && $cachedReference !== '') {
                $booking = Booking::query()
                    ->with(['schedule.route', 'tripReview'])
                    ->where('booking_reference', $cachedReference)
                    ->first();

                if ($booking && $this->guestCanView($booking, $browserId, $phone)
                    && ! $booking->shouldHideFromGuestAndOperatorActiveLists()) {
                    return $booking;
                }
            }
        }

        if ($phone !== null && $phone !== '') {
            $phoneActive = $this->duplicateBookings->findActiveBooking($phone);
            if ($phoneActive && ! $phoneActive->shouldHideFromGuestAndOperatorActiveLists()) {
                return $phoneActive->loadMissing(['schedule.route', 'tripReview']);
            }

            $phoneMatch = fn (Booking $booking): bool => $booking->matchesContactPhone($phone)
                && ! $booking->shouldHideFromGuestAndOperatorActiveLists();

            $awaitingReview = Booking::query()
                ->with(['schedule.route', 'tripReview'])
                ->where('trip_status', 'completed')
                ->whereNotIn('booking_status', ['cancelled', 'rejected'])
                ->whereDoesntHave('tripReview')
                ->whereNotNull('contact_phone')
                ->orderByDesc('completed_at')
                ->orderByDesc('id')
                ->get()
                ->first($phoneMatch);

            if ($awaitingReview) {
                return $awaitingReview;
            }

            $latest = Booking::query()
                ->with(['schedule.route', 'tripReview'])
                ->whereNotNull('contact_phone')
                ->orderByDesc('created_at')
                ->limit(20)
                ->get()
                ->first(fn (Booking $booking): bool => $phoneMatch($booking)
                    && $booking->blocksGuestRebooking());

            if ($latest) {
                return $latest;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    public function serialize(Booking $booking): array
    {
        $booking->loadMissing('schedule.route', 'tripReview', 'referralCode');
        $base = $this->duplicateBookings->serializeDuplicate($booking);

        return array_merge($base, [
            'passenger_name'    => $booking->passenger_name,
            'contact_phone'     => (string) ($booking->contact_phone ?? ''),
            'pickup_address'    => $booking->pickup_address,
            'pickup_detail'     => $booking->pickup_detail,
            'dropoff_address'   => $booking->dropoff_address,
            'dropoff_detail'    => $booking->dropoff_detail,
            'pickup_time_label' => $booking->isScheduledPickup() ? $booking->pickupTimeLabel() : null,
            'service_date_label' => $booking->isScheduledPickup()
                ? $booking->guestPickupAt()?->format('d/m/Y')
                : null,
            'pickup_mode_label' => $booking->pickupModeLabel(),
            'is_scheduled_pickup' => $booking->isScheduledPickup(),
            'trip_status'       => $booking->trip_status,
            'booking_status'    => $booking->booking_status,
            'total_price'       => (float) $booking->total_price,
            'total_price_label' => Money::vnd((float) $booking->total_price),
            'distance_km'       => $booking->tripDistanceKm(),
            'guest_status_label' => $booking->primaryStatusLabel(),
            'is_active'         => $booking->blocksGuestRebooking(),
            'can_cancel'        => $this->guestCanCancel($booking),
            'can_review'        => $booking->trip_status === 'completed' && ! $booking->tripReview,
            'chat'              => [
                'open'    => app(TripChatService::class)->isOpen($booking),
                'message' => app(TripChatService::class)->statusMessage($booking),
            ],
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

    public function guestCanCancel(Booking $booking): bool
    {
        if (in_array($booking->booking_status, ['cancelled', 'rejected'], true)) {
            return false;
        }

        if (in_array($booking->trip_status, ['completed', 'cancelled'], true)) {
            return false;
        }

        return ! $booking->passengerPickedUp();
    }

    public function guestCanView(Booking $booking, ?string $browserId, ?string $phone): bool
    {
        $user = auth()->user();
        if ($user && $user->role === 'customer' && $user->status === 'active') {
            if ((int) $booking->customer_id === (int) $user->id) {
                return true;
            }

            if ($user->phone && $booking->matchesContactPhone((string) $user->phone)) {
                return true;
            }
        }

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

        $review = TripReview::query()->create([
            'booking_id'        => $booking->id,
            'schedule_id'       => $booking->schedule_id,
            'driver_id'         => $booking->schedule?->driver_id,
            'driver_profile_id' => $booking->activeDriverProfile()?->id,
            'sentiment'         => $sentiment,
            'comment'           => $comment,
            'contact_phone'     => AuthIdentifier::normalizePhone((string) ($phone ?: $booking->contact_phone)),
        ]);

        $this->browserGuard->clearActiveBookingForBooking($booking->fresh());

        return $review;
    }
}