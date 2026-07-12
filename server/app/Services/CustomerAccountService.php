<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\TripReview;
use App\Models\User;
use App\Support\AuthIdentifier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustomerAccountService
{
    /** @return array<string, mixed> */
    public function profileSummary(User $user): array
    {
        $phone = AuthIdentifier::normalizePhone((string) ($user->phone ?? ''));

        return [
            'id'              => $user->id,
            'name'            => $user->name,
            'phone'           => $phone,
            'email'           => $user->emailForForm(),
            'has_biometric'   => app(WebAuthnService::class)->userHasCredentials($user),
            'trip_count'      => $this->bookingsQuery($user)->count(),
            'review_count'    => $this->reviewsQuery($user)->count(),
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
            'total_price_label'   => number_format((float) $booking->total_price, 0, ',', '.') . ' đ',
            'service_date_label'  => $booking->guestPickupAt()?->format('d/m/Y H:i'),
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
