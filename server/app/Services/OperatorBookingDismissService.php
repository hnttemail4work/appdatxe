<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OperatorBookingDismissService
{
    public const RETENTION_DAYS = 30;

    public function dismissStuckTrip(Booking $booking, int $operatorId): void
    {
        if (! Booking::supportsOperatorDismiss()) {
            throw new InvalidArgumentException('Hệ thống chưa cập nhật migration — liên hệ quản trị.');
        }

        $booking->loadMissing('schedule.vehicle');

        if ((int) ($booking->schedule?->vehicle?->operator_id) !== $operatorId) {
            throw new InvalidArgumentException('Không có quyền xử lý đơn này.');
        }

        if (! $booking->isTripOverdueStuck()) {
            throw new InvalidArgumentException('Chỉ có thể ẩn chuyến treo (quá hạn hoàn thành).');
        }

        $booking->update(['operator_dismissed_at' => now()]);
    }

    /** Xóa hẳn đơn đã ẩn quá hạn lưu trữ. */
    public function purgeExpiredDismissals(): int
    {
        if (! Booking::supportsOperatorDismiss()) {
            return 0;
        }

        $cutoff = now()->subDays(self::RETENTION_DAYS);
        $purged = 0;

        Booking::query()
            ->whereNotNull('operator_dismissed_at')
            ->where('operator_dismissed_at', '<=', $cutoff)
            ->orderBy('id')
            ->chunkById(100, function ($bookings) use (&$purged): void {
                foreach ($bookings as $booking) {
                    $this->purgeBooking($booking);
                    $purged++;
                }
            });

        return $purged;
    }

    private function purgeBooking(Booking $booking): void
    {
        DB::transaction(function () use ($booking): void {
            $booking->seatReservations()->delete();
            app(ReferralCodeService::class)->purgeForBooking($booking);
            $booking->delete();
        });
    }
}
