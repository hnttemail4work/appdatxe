<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;

/** Giới hạn đặt/hủy lặp lại theo SĐT khách + điểm đón. */
class BookingPhoneGuardService
{
    public const MAX_CANCEL_CYCLES = 3;

    public const BLOCK_MINUTES = 30;

    public function customerCancelCount(string $contactPhone, ?string $locationKey = null): int
    {
        return (int) Cache::get($this->cancelKey($this->normalize($contactPhone), $locationKey), 0);
    }

    /** Lần hủy thứ 4 trở đi (cùng SĐT + điểm đón) cần chọn lý do. */
    public function requiresCancelReason(string $contactPhone, ?string $locationKey = null): bool
    {
        return $this->customerCancelCount($contactPhone, $locationKey) >= self::MAX_CANCEL_CYCLES;
    }

    public function assertCanBook(
        string $contactPhone,
        ?string $locationKey = null,
    ): void {
        if ($this->blockedUntil($contactPhone, $locationKey)?->isFuture()) {
            throw new InvalidArgumentException(
                'Đặt xe quá nhiều lần, vui lòng thử lại sau ' . self::BLOCK_MINUTES . ' phút.',
            );
        }
    }

    public function recordCustomerCancel(string $contactPhone, ?string $locationKey = null): int
    {
        $norm = $this->normalize($contactPhone);
        $count = (int) Cache::get($this->cancelKey($norm, $locationKey), 0) + 1;
        Cache::put($this->cancelKey($norm, $locationKey), $count, now()->addHours(24));

        if ($count >= self::MAX_CANCEL_CYCLES) {
            Cache::put(
                $this->blockKey($norm, $locationKey),
                now()->addMinutes(self::BLOCK_MINUTES),
                now()->addMinutes(self::BLOCK_MINUTES),
            );
        }

        return $count;
    }

    public function isBlocked(string $contactPhone, ?string $locationKey = null): bool
    {
        return $this->blockedUntil($contactPhone, $locationKey)?->isFuture() ?? false;
    }

    public function shouldLogBlockedAttempt(string $contactPhone, ?string $locationKey = null): bool
    {
        return $this->isBlocked($contactPhone, $locationKey);
    }

    public function locationFingerprintFromBooking(Booking $booking): string
    {
        return $this->locationFingerprint(
            $booking->pickup_lat !== null ? (float) $booking->pickup_lat : null,
            $booking->pickup_lng !== null ? (float) $booking->pickup_lng : null,
            $booking->pickup_address,
            $booking->pickup_detail,
        );
    }

    public function locationFingerprint(
        ?float $pickupLat,
        ?float $pickupLng,
        ?string $pickupAddress,
        ?string $pickupDetail,
    ): string {
        if ($pickupLat !== null && $pickupLng !== null) {
            return sprintf('%.4f,%.4f', round($pickupLat, 4), round($pickupLng, 4));
        }

        $addr = mb_strtolower(trim((string) $pickupAddress));
        $detail = mb_strtolower(trim((string) $pickupDetail));

        return 'addr:' . hash('xxh128', $addr . '|' . $detail);
    }

    /** Ghi nhận lần đặt bị chặn để quản lý thấy ở tab đặt xe gần đây. */
    public function logBlockedAttempt(
        Schedule $schedule,
        string $contactPhone,
        string $passengerName,
        ?string $pickupAddress = null,
        ?string $pickupDetail = null,
    ): Booking {
        $payload = [
            'contact_phone'     => trim($contactPhone),
            'passenger_name'    => trim($passengerName) ?: 'Khách',
            'passenger_gender'  => 'male',
            'schedule_id'       => $schedule->id,
            'booking_reference' => 'BK-BLOCK-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(4)),
            'total_price'       => 0,
            'payment_status'    => 'unpaid',
            'trip_status'       => 'cancelled',
            'booking_status'    => 'rejected',
            'pickup_address'    => $pickupAddress,
            'pickup_detail'     => $pickupDetail,
            'cancelled_at'      => now(),
            'cancelled_by'      => 'system',
            'notes'             => 'Chặn đặt xe: SĐT đặt/hủy quá ' . self::MAX_CANCEL_CYCLES . ' lần.',
        ];

        if (Schema::hasColumn('bookings', 'repeat_cancel_flag')) {
            $payload['repeat_cancel_flag'] = true;
        }

        return Booking::query()->create($payload);
    }

    private function blockedUntil(string $contactPhone, ?string $locationKey = null): ?\Carbon\Carbon
    {
        $value = Cache::get($this->blockKey($this->normalize($contactPhone), $locationKey));

        return $value instanceof \Carbon\Carbon ? $value : null;
    }

    private function normalize(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?: $phone;
    }

    private function cancelKey(string $normalizedPhone, ?string $locationKey): string
    {
        return 'phone_cancel_count:' . $normalizedPhone . ':' . ($locationKey ?: '_');
    }

    private function blockKey(string $normalizedPhone, ?string $locationKey): string
    {
        return 'phone_book_block:' . $normalizedPhone . ':' . ($locationKey ?: '_');
    }
}
