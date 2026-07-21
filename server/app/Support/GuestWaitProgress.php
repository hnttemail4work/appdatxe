<?php

namespace App\Support;

use App\Models\Booking;
use App\Services\DriverTripRequestService;

class GuestWaitProgress
{
    /** @return array<string, mixed>|null */
    public static function forBooking(Booking $booking): ?array
    {
        $booking->loadMissing('schedule', 'tripReview');

        if ($booking->trip_status === 'completed') {
            if ($booking->tripReview) {
                return null;
            }

            $shortCode = trim((string) ($booking->schedule?->shortTripCode() ?? ''));
            $hint = $shortCode !== ''
                ? 'Chuyến '.$shortCode.' đã hoàn tất, cảm ơn bạn đã chọn goz. Đừng quên đánh giá cho tài xế nhé.'
                : 'Chuyến đã hoàn tất, cảm ơn bạn đã chọn goz. Đừng quên đánh giá cho tài xế nhé.';

            return [
                'kind'          => 'review',
                'label'         => 'Thông tin chuyến',
                'hint'          => $hint,
                'started_at'    => ($booking->completed_at ?? now())->toIso8601String(),
                'deadline_at'   => null,
                'total_seconds' => 0,
                'indeterminate' => true,
            ];
        }

        if (in_array($booking->booking_status, ['cancelled', 'rejected'], true)
            || $booking->trip_status === 'cancelled'
            || $booking->isExpired()) {
            return null;
        }

        // Khách chỉ thấy «đã tìm thấy» sau khi TX nhận — lúc còn offer pending vẫn là đang tìm.
        if (! $booking->hasDriverAccepted()) {
            $driverRequests = app(DriverTripRequestService::class);
            $started = $driverRequests->customerSearchStartedAt($booking);

            if ($booking->isOnDemandPickup()) {
                $deadline = $started->copy()->addMinutes(DriverTripRequestService::ON_DEMAND_SEARCH_MAX_MINUTES);

                return [
                    'kind'          => 'driver_search',
                    'label'         => 'Đang tìm tài xế gần bạn…',
                    'hint'          => null,
                    'started_at'    => $started->toIso8601String(),
                    'deadline_at'   => $deadline->toIso8601String(),
                    'total_seconds' => max(60, (int) $started->diffInSeconds($deadline)),
                    'indeterminate' => false,
                ];
            }

            $pickupLead = $booking->pickupAdminActionStartsAt();
            if ($pickupLead?->isFuture()) {
                return [
                    'kind'          => 'driver_search',
                    'label'         => 'Đang tìm tài xế gần bạn…',
                    'hint'          => null,
                    'started_at'    => $started->toIso8601String(),
                    'deadline_at'   => $pickupLead->toIso8601String(),
                    'total_seconds' => max(60, (int) now()->diffInSeconds($pickupLead)),
                    'indeterminate' => false,
                ];
            }

            return [
                'kind'          => 'driver_search',
                'label'         => 'Đang tìm tài xế gần bạn…',
                'hint'          => null,
                'started_at'    => $started->toIso8601String(),
                'deadline_at'   => null,
                'total_seconds' => 0,
                'indeterminate' => true,
            ];
        }

        return [
            'kind'          => 'default',
            'label'         => $booking->primaryStatusLabel(),
            'hint'          => null,
            'started_at'    => now()->toIso8601String(),
            'deadline_at'   => null,
            'total_seconds' => 0,
            'indeterminate' => true,
        ];
    }
}
