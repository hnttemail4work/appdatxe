<?php

namespace App\Support;

use App\Models\DriverTripRequest;
use App\Models\Schedule;

class DriverWaitProgress
{
    /** @return array<string, mixed>|null */
    public static function forSchedule(Schedule $schedule): ?array
    {
        if ($schedule->driverPendingClosure()) {
            $booking = $schedule->driverRelevantBookings()
                ->first(fn ($b): bool => $b->trip_status === 'awaiting_completion');

            return [
                'kind'          => 'complete_overdue',
                'label'         => 'Cần xác nhận hoàn thành',
                'hint'          => 'Chuyến đã qua giờ kết thúc dự kiến — bấm hoàn thành để nhận cuốc mới.',
                'started_at'    => ($booking?->completed_at ?? now())->toIso8601String(),
                'deadline_at'   => null,
                'total_seconds' => 0,
                'indeterminate' => true,
            ];
        }

        return null;
    }

    /** Cuốc chờ nhận — hết giờ xử lý ngầm (poll/expireStale), không hiện banner «Khách đang đợi bạn». */
    public static function forTripRequest(DriverTripRequest $request): ?array
    {
        return null;
    }
}
