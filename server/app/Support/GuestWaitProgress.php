<?php

namespace App\Support;

use App\Models\Booking;
use App\Models\DriverTripRequest;
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

            return [
                'kind'          => 'review',
                'label'         => 'Chuyến đã hoàn tất',
                'hint'          => 'Hãy đánh giá chuyến đi để giúp chúng tôi phục vụ tốt hơn.',
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

        if (! $booking->hasDriverAccepted()) {
            $pendingRequest = DriverTripRequest::query()
                ->where('schedule_id', $booking->schedule_id)
                ->where('contact_phone', (string) $booking->contact_phone)
                ->where('status', 'pending')
                ->where(function ($query): void {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->latest('id')
                ->first();

            if ($pendingRequest?->expires_at?->isFuture()) {
                $started = $pendingRequest->created_at ?? now();
                $totalSeconds = max(
                    60,
                    (int) $started->diffInSeconds($pendingRequest->expires_at),
                );

                return [
                    'kind'          => 'trip_accept',
                    'label'         => 'Đang chờ tài xế nhận chuyến',
                    'hint'          => 'Tài xế cần xác nhận trong thời gian quy định.',
                    'started_at'    => $started->toIso8601String(),
                    'deadline_at'   => $pendingRequest->expires_at->toIso8601String(),
                    'total_seconds' => $totalSeconds,
                    'indeterminate' => false,
                ];
            }

            $driverRequests = app(DriverTripRequestService::class);
            $pickupLead = $booking->pickupAdminActionStartsAt();

            if ($pickupLead?->isFuture()) {
                $started = $driverRequests->customerSearchStartedAt($booking);

                return [
                    'kind'          => 'driver_search',
                    'label'         => $booking->primaryStatusLabel(),
                    'hint'          => 'Đơn được giữ đến giờ đón. Quản lý sẽ gán tài xế cho bạn.',
                    'started_at'    => $started->toIso8601String(),
                    'deadline_at'   => $pickupLead->toIso8601String(),
                    'total_seconds' => max(60, (int) now()->diffInSeconds($pickupLead)),
                    'indeterminate' => false,
                ];
            }
        }

        if ($booking->hasDriverAccepted()) {
            return [
                'kind'          => 'default',
                'label'         => $booking->primaryStatusLabel(),
                'hint'          => 'Theo dõi tiến trình chuyến đi bên dưới.',
                'started_at'    => now()->toIso8601String(),
                'deadline_at'   => null,
                'total_seconds' => 0,
                'indeterminate' => true,
            ];
        }

        return null;
    }
}
