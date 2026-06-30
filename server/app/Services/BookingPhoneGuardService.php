<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;

/** Giới hạn đặt/hủy lặp lại theo SĐT khách. */
class BookingPhoneGuardService
{
    public const MAX_CANCEL_CYCLES = 3;

    public const BLOCK_MINUTES = 30;

    public function customerCancelCount(string $contactPhone): int
    {
        return (int) Cache::get($this->cancelKey($this->normalize($contactPhone)), 0);
    }

    /** Lần hủy thứ 4 trở đi cần chọn lý do. */
    public function requiresCancelReason(string $contactPhone): bool
    {
        return $this->customerCancelCount($contactPhone) >= self::MAX_CANCEL_CYCLES;
    }

    public function assertCanBook(string $contactPhone): void
    {
        if ($this->blockedUntil($contactPhone)?->isFuture()) {
            throw new InvalidArgumentException(
                'Đặt xe quá nhiều lần, vui lòng thử lại sau ' . self::BLOCK_MINUTES . ' phút.',
            );
        }
    }

    public function recordCustomerCancel(string $contactPhone): int
    {
        $norm = $this->normalize($contactPhone);
        $count = (int) Cache::get($this->cancelKey($norm), 0) + 1;
        Cache::put($this->cancelKey($norm), $count, now()->addHours(24));

        if ($count >= self::MAX_CANCEL_CYCLES) {
            Cache::put(
                $this->blockKey($norm),
                now()->addMinutes(self::BLOCK_MINUTES),
                now()->addMinutes(self::BLOCK_MINUTES),
            );
        }

        return $count;
    }

    public function isBlocked(string $contactPhone): bool
    {
        return $this->blockedUntil($contactPhone)?->isFuture() ?? false;
    }

    public function shouldLogBlockedAttempt(string $contactPhone): bool
    {
        return $this->isBlocked($contactPhone);
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
            'seat_numbers'      => ['1'],
            'trip_type'         => 'one_way',
            'booking_mode'      => 'shared',
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

    private function blockedUntil(string $contactPhone): ?\Carbon\Carbon
    {
        $value = Cache::get($this->blockKey($this->normalize($contactPhone)));

        return $value instanceof \Carbon\Carbon ? $value : null;
    }

    private function normalize(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?: $phone;
    }

    private function cancelKey(string $normalized): string
    {
        return 'phone_cancel_count:' . $normalized;
    }

    private function blockKey(string $normalized): string
    {
        return 'phone_book_block:' . $normalized;
    }
}
