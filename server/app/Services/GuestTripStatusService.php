<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingAudit;
use App\Models\TripReview;
use App\Support\AuthIdentifier;
use App\Support\GuestWaitProgress;
use App\Support\Money;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class GuestTripStatusService
{
    public function __construct(
        private readonly DuplicateBookingService $duplicateBookings,
        private readonly BookingBrowserGuardService $browserGuard,
        private readonly DriverTripRequestService $driverTripRequests,
        private readonly TripPricingService $pricing,
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
            'pickup_lat'        => $booking->pickup_lat !== null ? (float) $booking->pickup_lat : null,
            'pickup_lng'        => $booking->pickup_lng !== null ? (float) $booking->pickup_lng : null,
            'dropoff_lat'       => $booking->dropoff_lat !== null ? (float) $booking->dropoff_lat : null,
            'dropoff_lng'       => $booking->dropoff_lng !== null ? (float) $booking->dropoff_lng : null,
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
            'price_subtotal'    => (int) ($booking->price_subtotal ?? 0),
            'referral_discount_amount' => (int) ($booking->referral_discount_amount ?? 0),
            'referral_discount_percent' => (float) ($booking->referral_discount_percent ?? 0),
            'surcharge_holiday' => (int) ($booking->surcharge_holiday ?? 0),
            'surcharge_peak'    => (int) ($booking->surcharge_peak ?? 0),
            'surcharge_rain'    => (int) ($booking->surcharge_rain ?? 0),
            'toll_amount'       => (int) ($booking->toll_amount ?? 0),
            'price_breakdown'   => is_array($booking->price_breakdown) ? $booking->price_breakdown : null,
            'distance_km'       => $booking->distance_km ?: $booking->tripDistanceKm(),
            'guest_status_label' => $booking->primaryStatusLabel(),
            'is_active'         => $booking->blocksGuestRebooking(),
            'can_cancel'        => $this->guestCanCancel($booking),
            'cancel_requires_reason' => $this->guestCanCancel($booking) && $booking->hasDriverAccepted(),
            'can_change_dropoff' => $this->guestCanChangeDropoff($booking),
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

    public function guestCanChangeDropoff(Booking $booking): bool
    {
        if (in_array($booking->booking_status, ['cancelled', 'rejected'], true)) {
            return false;
        }

        if (in_array($booking->trip_status, ['completed', 'cancelled'], true)) {
            return false;
        }

        if ($booking->pickup_lat === null || $booking->pickup_lng === null) {
            return false;
        }

        // Cho đổi từ lúc đã có chuyến đến trước khi hoàn thành.
        return $booking->blocksGuestRebooking();
    }

    /**
     * Khách đổi điểm đến — tính lại giá ngay, đồng bộ TX/admin qua poll status.
     */
    public function changeDropoff(
        Booking $booking,
        string $dropoffDetail,
        float $dropoffLat,
        float $dropoffLng,
        ?string $dropoffAddress,
        ?string $browserId,
        ?string $phone,
    ): Booking {
        $preview = $this->previewChangeDropoff(
            $booking,
            $dropoffDetail,
            $dropoffLat,
            $dropoffLng,
            $dropoffAddress,
            $browserId,
            $phone,
        );

        $quote = $preview['quote'];
        $dropoffLabel = $preview['dropoff_detail'];

        $before = [
            'dropoff_address' => $booking->dropoff_address,
            'dropoff_detail'  => $booking->dropoff_detail,
            'dropoff_lat'     => $booking->dropoff_lat,
            'dropoff_lng'     => $booking->dropoff_lng,
            'total_price'     => $booking->total_price,
            'distance_km'     => $booking->distance_km,
        ];

        $booking->fill(array_merge([
            'dropoff_address' => $dropoffAddress !== null && trim($dropoffAddress) !== ''
                ? trim($dropoffAddress)
                : $booking->dropoff_address,
            'dropoff_detail'  => $dropoffLabel,
            'dropoff_lat'     => $dropoffLat,
            'dropoff_lng'     => $dropoffLng,
        ], $quote->toBookingColumns()));
        $booking->save();

        BookingAudit::query()->create([
            'booking_id'   => $booking->id,
            'actor_id'     => null,
            'action'       => 'guest_change_dropoff',
            'before_state' => $before,
            'after_state'  => [
                'dropoff_address' => $booking->dropoff_address,
                'dropoff_detail'  => $booking->dropoff_detail,
                'dropoff_lat'     => $booking->dropoff_lat,
                'dropoff_lng'     => $booking->dropoff_lng,
                'total_price'     => $booking->total_price,
                'distance_km'     => $booking->distance_km,
            ],
            'notes'        => 'Khách đổi điểm đến — giá tính lại tự động',
        ]);

        try {
            app(PushNotificationService::class)->onGuestChangedDropoff($booking->fresh());
        } catch (\Throwable) {
            // Push không chặn đổi điểm đến.
        }

        return $booking->fresh(['schedule.route', 'tripReview']);
    }

    /**
     * Báo giá điểm đến mới (chưa lưu) — dùng cho hộp thoại xác nhận.
     *
     * @return array{
     *     dropoff_detail: string,
     *     current_price: float,
     *     current_price_label: string,
     *     new_price: float,
     *     new_price_label: string,
     *     quote: \App\Support\PriceQuote
     * }
     */
    public function previewChangeDropoff(
        Booking $booking,
        string $dropoffDetail,
        float $dropoffLat,
        float $dropoffLng,
        ?string $dropoffAddress,
        ?string $browserId,
        ?string $phone,
    ): array {
        if (! $this->guestCanView($booking, $browserId, $phone)) {
            throw new InvalidArgumentException('Không xác thực được chuyến đi.');
        }

        if (! $this->guestCanChangeDropoff($booking)) {
            throw new InvalidArgumentException('Chuyến này không thể đổi điểm đến.');
        }

        $booking->loadMissing(['schedule.vehicle', 'schedule.route']);
        $pickupLabel = trim((string) ($booking->pickup_detail ?: $booking->pickup_address));
        $dropoffLabel = trim($dropoffDetail);
        if ($pickupLabel === '' || $dropoffLabel === '') {
            throw new InvalidArgumentException('Thiếu điểm đi hoặc điểm đến.');
        }

        $vehicle = $booking->schedule?->vehicle;
        $capacity = (int) ($vehicle?->capacity ?: 4);
        $vehicleType = $vehicle?->type ? (string) $vehicle->type : ($booking->vehicle_type_key ?: null);

        $quote = $this->pricing->quoteForVehicleType(
            $pickupLabel,
            $dropoffLabel,
            $capacity,
            $vehicleType,
            $booking->pickup_lat !== null ? (float) $booking->pickup_lat : null,
            $booking->pickup_lng !== null ? (float) $booking->pickup_lng : null,
            $dropoffLat,
            $dropoffLng,
        );

        $referralPercent = (float) ($booking->referral_discount_percent ?? 0);
        if ($referralPercent > 0) {
            $quote = $quote->withReferral($referralPercent);
        }

        $currentPrice = (float) $booking->total_price;
        $newPrice = (float) $quote->totalPrice;

        return [
            'dropoff_detail'       => $dropoffLabel,
            'current_price'        => $currentPrice,
            'current_price_label'  => Money::vnd($currentPrice),
            'new_price'            => $newPrice,
            'new_price_label'      => Money::vnd($newPrice),
            'quote'                => $quote,
        ];
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

        $profile = $booking->activeDriverProfile();
        $review = TripReview::query()->create([
            'booking_id'        => $booking->id,
            'schedule_id'       => $booking->schedule_id,
            'driver_id'         => $booking->schedule?->driver_id,
            'driver_profile_id' => $profile?->id,
            'sentiment'         => $sentiment,
            'comment'           => $comment,
            'contact_phone'     => AuthIdentifier::normalizePhone((string) ($phone ?: $booking->contact_phone)),
        ]);

        if ($profile) {
            if ($sentiment === TripReview::SENTIMENT_LIKE) {
                $profile->increment('preference_likes');
            } elseif ($sentiment === TripReview::SENTIMENT_DISLIKE) {
                $profile->increment('preference_dislikes');
                $this->maybeWarnDriverOnDislikes($profile->fresh());
            }
        }

        $this->browserGuard->clearActiveBookingForBooking($booking->fresh());

        return $review;
    }

    /** Ngưỡng dislike gần đây để cảnh báo tài xế. */
    public const DISLIKE_WARNING_THRESHOLD = 3;

    private function maybeWarnDriverOnDislikes(\App\Models\DriverProfile $profile): void
    {
        $recentDislikes = TripReview::query()
            ->where('driver_profile_id', $profile->id)
            ->where('sentiment', TripReview::SENTIMENT_DISLIKE)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        if ($recentDislikes < self::DISLIKE_WARNING_THRESHOLD) {
            return;
        }

        $userId = (int) $profile->user_id;
        if ($userId < 1) {
            return;
        }

        try {
            app(DriverInboxService::class)->notify(
                $userId,
                \App\Models\DriverInboxMessage::CATEGORY_NOTICE,
                'Cảnh báo đánh giá không tốt',
                'Bạn nhận nhiều đánh giá không thích gần đây ('.$recentDislikes.' trong 30 ngày). Vui lòng cải thiện phục vụ để tránh ảnh hưởng nhận chuyến.',
                [
                    'type'            => 'driver_dislike_warning',
                    'dislike_count'   => $recentDislikes,
                    'threshold'       => self::DISLIKE_WARNING_THRESHOLD,
                ],
                null,
                true,
            );
        } catch (\Throwable) {
        }
    }
}