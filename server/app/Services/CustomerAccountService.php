<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\TripReview;
use App\Models\User;
use App\Support\AuthIdentifier;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustomerAccountService
{
    /** @return array<string, mixed> */
    public function profileSummary(User $user): array
    {
        $phone = AuthIdentifier::normalizePhone((string) ($user->phone ?? ''));

        $rawName = trim((string) ($user->name ?? ''));
        $nameLooksLikePhone = $rawName === ''
            || $rawName === $phone
            || (bool) preg_match('/^[\d\s.+()-]+$/', $rawName);

        return [
            'id'                   => $user->id,
            'name'                 => $nameLooksLikePhone ? '' : $rawName,
            'phone'                => $phone,
            'email'                => $user->emailForForm(),
            'gender'               => $user->gender,
            'gender_label'         => $user->genderLabel(),
            'age'                  => $user->age(),
            'date_of_birth'        => $user->date_of_birth?->format('Y-m-d'),
            'address'              => $user->address,
            'id_number'            => $user->id_number,
            'photo_id_card_url'    => $user->idCardPhotoUrl('photo_id_card'),
            'photo_id_card_back_url' => $user->idCardPhotoUrl('photo_id_card_back'),
            'approval_status'      => $user->approval_status,
            'avatar_initial'       => $user->avatarInitial(),
            'trip_count'           => $this->bookingsQuery($user)->count(),
            'review_count'         => $this->reviewsQuery($user)->count(),
        ];
    }

    /** @return array<string, mixed>|null */
    public function bookingPrefill(User $user): ?array
    {
        if (! $user->isCustomer()) {
            return null;
        }

        $phone = AuthIdentifier::normalizePhone((string) ($user->phone ?? ''));

        if ($phone === '') {
            return null;
        }

        $name = trim((string) $user->name);
        $phoneDigits = preg_replace('/\D+/', '', $phone) ?: '';
        $nameDigits = preg_replace('/\D+/', '', $name) ?: '';
        $nameLooksLikePhone = $name === ''
            || $name === $phone
            || ($phoneDigits !== '' && $nameDigits === $phoneDigits)
            || (bool) preg_match('/^[\d\s.+()-]+$/', $name);

        return [
            'passenger_name'   => $nameLooksLikePhone ? '' : $name,
            'contact_phone'    => $phone,
            'passenger_gender' => in_array($user->gender, ['male', 'female'], true) ? $user->gender : '',
            'passenger_age'    => $user->age(),
        ];
    }

    public function linkExistingBookings(User $user): int
    {
        $phone = AuthIdentifier::normalizePhone((string) ($user->phone ?? ''));

        if ($phone === '') {
            return 0;
        }

        $linked = Booking::query()
            ->whereNull('customer_id')
            ->whereNotNull('contact_phone')
            ->get()
            ->filter(fn (Booking $booking): bool => $booking->matchesContactPhone($phone));

        $linked->each(function (Booking $booking) use ($user): void {
            $booking->update(['customer_id' => $user->id]);
        });

        return $linked->count();
    }

    /** @return LengthAwarePaginator<int, Booking> */
    public function tripHistory(User $user, int $page = 1, int $perPage = 10): LengthAwarePaginator
    {
        return $this->bookingsQuery($user)
            ->with(['schedule.route', 'tripReview'])
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /** @return LengthAwarePaginator<int, TripReview> */
    public function reviews(User $user, int $page = 1, int $perPage = 10): LengthAwarePaginator
    {
        return $this->reviewsQuery($user)
            ->with(['booking.schedule.route', 'driverProfile.user'])
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /** @return Collection<int, Booking> */
    public function recentTrips(User $user, int $limit = 5): Collection
    {
        return $this->bookingsQuery($user)
            ->with(['schedule.route', 'tripReview'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /** @return array<string, mixed> */
    public function serializeTrip(Booking $booking): array
    {
        $booking->loadMissing('schedule.route', 'tripReview');

        return [
            'booking_reference'   => $booking->booking_reference,
            'passenger_name'      => $booking->passenger_name,
            'pickup_address'      => $booking->pickup_address,
            'dropoff_address'     => $booking->dropoff_address,
            'trip_status'         => $booking->trip_status,
            'booking_status'      => $booking->booking_status,
            'guest_status_label'  => $booking->primaryStatusLabel(),
            'total_price_label'   => Money::vnd((float) $booking->total_price),
            'service_date_label'  => $booking->isScheduledPickup()
                ? $booking->guestPickupAt()?->format('d/m/Y H:i')
                : $booking->pickupModeLabel(),
            'created_at_label'    => $booking->created_at?->format('d/m/Y H:i'),
            'can_review'          => $booking->trip_status === 'completed' && ! $booking->tripReview,
            'review'              => $booking->tripReview ? [
                'sentiment' => $booking->tripReview->sentiment,
                'label'     => $booking->tripReview->driverPreferenceLabel(),
                'icon'      => $booking->tripReview->sentimentIcon(),
                'comment'   => $booking->tripReview->comment,
            ] : null,
            'trips_url'           => route('booking.trips', ['ref' => $booking->booking_reference], false),
        ];
    }

    /** @return \Illuminate\Database\Eloquent\Builder<Booking> */
    private function bookingsQuery(User $user)
    {
        $this->linkExistingBookings($user);

        return Booking::query()->where('customer_id', $user->id);
    }

    /** @return \Illuminate\Database\Eloquent\Builder<TripReview> */
    private function reviewsQuery(User $user)
    {
        $this->linkExistingBookings($user);

        return TripReview::query()->whereHas(
            'booking',
            fn ($booking) => $booking->where('customer_id', $user->id),
        );
    }
}
