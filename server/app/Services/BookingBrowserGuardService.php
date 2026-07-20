<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/** Chặn spam đặt cuốc theo phiên trình duyệt (sessionStorage). */
class BookingBrowserGuardService
{
    public const CANCEL_BLOCK_LIMIT = 3;

    /** Tạm tắt khi test — bật lại `true` để chặn đặt sau khi hủy quá hạn mức. */
    public const ENFORCE_CANCEL_BLOCK = false;

    public const CACHE_TTL_SECONDS = 86400;

    public function blockMessage(): string
    {
        return 'Đã hủy quá nhiều lần trên trình duyệt này, vui lòng thử lại sau hoặc liên hệ tổng đài để biết thêm thông tin chi tiết.';
    }

    public function activeBookingBlockMessage(): string
    {
        return 'Đang có chuyến chưa hoàn thành, vui lòng hoàn tất chuyến.';
    }

    public function missingBrowserIdMessage(): string
    {
        return 'Không xác thực được phiên trình duyệt. Vui lòng tải lại trang và thử lại.';
    }

    public function cancelCacheKey(string $browserSessionId): string
    {
        return 'guest_browser_cancel_count:' . sha1($browserSessionId);
    }

    public function activeBookingCacheKey(string $browserSessionId): string
    {
        return 'guest_browser_active_booking:' . sha1($browserSessionId);
    }

    public function bookingRefCacheKey(string $bookingReference): string
    {
        return 'guest_browser_booking_ref:' . sha1($bookingReference);
    }

    public function cancelCount(string $browserSessionId): int
    {
        if ($browserSessionId === '') {
            return 0;
        }

        return (int) Cache::get($this->cancelCacheKey($browserSessionId), 0);
    }

    public function isCancelBlocked(string $browserSessionId): bool
    {
        if (! self::ENFORCE_CANCEL_BLOCK) {
            return false;
        }

        return $this->cancelCount($browserSessionId) >= self::CANCEL_BLOCK_LIMIT;
    }

    public function hasActiveBooking(string $browserSessionId): bool
    {
        return $this->findActiveBooking($browserSessionId) !== null;
    }

    public function findActiveBooking(string $browserSessionId): ?Booking
    {
        if ($browserSessionId === '') {
            return null;
        }

        $reference = Cache::get($this->activeBookingCacheKey($browserSessionId));

        if (! is_string($reference) || $reference === '') {
            return null;
        }

        $booking = Booking::query()
            ->with(['schedule.route'])
            ->where('booking_reference', $reference)
            ->first();

        if (! $booking || ! $this->bookingStillActive($booking)) {
            $this->clearActiveBooking($browserSessionId, $reference);

            return null;
        }

        return $booking;
    }

    public function recordCancel(string $browserSessionId): int
    {
        if ($browserSessionId === '') {
            return 0;
        }

        $key = $this->cancelCacheKey($browserSessionId);
        $count = $this->cancelCount($browserSessionId) + 1;
        Cache::put($key, $count, self::CACHE_TTL_SECONDS);

        return $count;
    }

    public function recordActiveBooking(string $browserSessionId, Booking $booking): void
    {
        if ($browserSessionId === '') {
            return;
        }

        $reference = (string) $booking->booking_reference;

        Cache::put($this->activeBookingCacheKey($browserSessionId), $reference, self::CACHE_TTL_SECONDS);
        Cache::put($this->bookingRefCacheKey($reference), $browserSessionId, self::CACHE_TTL_SECONDS);
    }

    public function clearActiveBookingForBooking(Booking $booking): void
    {
        $reference = (string) $booking->booking_reference;
        $browserId = Cache::get($this->bookingRefCacheKey($reference));

        if (! is_string($browserId) || $browserId === '') {
            return;
        }

        $this->clearActiveBooking($browserId, $reference);
    }

    public function clearActiveBooking(string $browserSessionId, ?string $bookingReference = null): void
    {
        if ($browserSessionId === '') {
            return;
        }

        if ($bookingReference === null) {
            $bookingReference = Cache::get($this->activeBookingCacheKey($browserSessionId));
        }

        Cache::forget($this->activeBookingCacheKey($browserSessionId));

        if (is_string($bookingReference) && $bookingReference !== '') {
            Cache::forget($this->bookingRefCacheKey($bookingReference));
        }
    }

    public function assertCanBook(?string $browserSessionId): void
    {
        if ($browserSessionId === null || $browserSessionId === '') {
            throw new InvalidArgumentException($this->missingBrowserIdMessage());
        }

        if ($this->isCancelBlocked($browserSessionId)) {
            throw new InvalidArgumentException($this->blockMessage());
        }

        if ($this->hasActiveBooking($browserSessionId)) {
            throw new InvalidArgumentException($this->activeBookingBlockMessage());
        }
    }

    private function bookingStillActive(Booking $booking): bool
    {
        if ($booking->blocksGuestRebooking()) {
            return true;
        }

        if ($booking->trip_status !== 'completed') {
            return false;
        }

        if ($booking->relationLoaded('tripReview')) {
            return $booking->getRelation('tripReview') === null;
        }

        return ! $booking->tripReview()->exists();
    }
}
