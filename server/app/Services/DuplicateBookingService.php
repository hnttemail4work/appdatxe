<?php

namespace App\Services;

use App\Models\Booking;
use App\Support\AuthIdentifier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class DuplicateBookingService
{
    /** Đơn còn hiệu lực theo SĐT — chưa hủy / chưa hoàn tất (mọi tuyến). */
    public function findActiveBooking(string $contactPhone, ?string $excludeReference = null): ?Booking
    {
        $normalized = $this->normalizeContactPhone($contactPhone);

        if ($normalized === '') {
            return null;
        }

        return $this->activeBookingQuery($excludeReference)
            ->orderByDesc('created_at')
            ->get()
            ->first(fn (Booking $booking): bool => $this->normalizeContactPhone((string) $booking->contact_phone) === $normalized);
    }

    public function assertCanBook(string $contactPhone, ?string $excludeReference = null): void
    {
        if ($this->findActiveBooking($contactPhone, $excludeReference)) {
            throw new InvalidArgumentException(
                'Bạn đang có một cuốc chưa hoàn thành. Vui lòng hoàn tất hoặc hủy cuốc đó trước khi đặt cuốc mới.'
            );
        }
    }

    public function normalizeContactPhone(string $phone): string
    {
        return AuthIdentifier::normalizePhone($phone);
    }

    public function lockKeyForPhone(string $contactPhone): string
    {
        $normalized = $this->normalizeContactPhone($contactPhone);

        return 'guest_active_booking:' . ($normalized !== '' ? $normalized : 'unknown');
    }

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function withPhoneBookingLock(string $contactPhone, callable $callback): mixed
    {
        return Cache::lock($this->lockKeyForPhone($contactPhone), 30)->block(10, $callback);
    }

    /** @return array<string, mixed>|null */
    public function serializeDuplicate(Booking $booking): array
    {
        $booking->loadMissing('schedule.route');

        return [
            'booking_reference' => $booking->booking_reference,
            'trip_code'         => $booking->schedule?->shortTripCode() ?? '—',
            'route'             => $booking->schedule?->route
                ? $booking->schedule->route->departure . ' → ' . $booking->schedule->route->destination
                : '—',
            'service_date'      => $booking->schedule?->departure_time?->format('d/m/Y H:i'),
            'vehicle_capacity'  => $booking->vehicle_capacity,
            'vehicle_count'     => (int) ($booking->vehicle_count ?? 1),
            'booking_mode'      => $booking->booking_mode,
            'progress_label'    => $booking->primaryStatusLabel(),
        ];
    }

    private function activeBookingQuery(?string $excludeReference = null): Builder
    {
        return Booking::query()
            ->with(['schedule.route'])
            ->whereNotIn('booking_status', ['cancelled', 'rejected'])
            ->whereNotIn('trip_status', ['completed', 'cancelled'])
            ->whereNull('expired_at')
            ->when(Booking::supportsOperatorDismiss(), fn ($q) => $q->whereNull('operator_dismissed_at'))
            ->when($excludeReference, fn ($q) => $q->where('booking_reference', '!=', $excludeReference));
    }
}
