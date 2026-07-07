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

        if ($schedule->needsDriverMovementConfirm()) {
            $deadline = $schedule->driver_movement_deadline_at;
            $assignedAt = $schedule->driver_assigned_at ?? now();

            if ($deadline?->isFuture()) {
                return [
                    'kind'          => 'movement_confirm',
                    'label'         => 'Bấm «Xác nhận» để đi đón khách',
                    'hint'          => 'Hết giờ hệ thống có thể gán tài xế khác cho khách.',
                    'started_at'    => $assignedAt->toIso8601String(),
                    'deadline_at'   => $deadline->toIso8601String(),
                    'total_seconds' => max(30, (int) $assignedAt->diffInSeconds($deadline)),
                    'indeterminate' => false,
                ];
            }

            if ($deadline?->isPast()) {
                return [
                    'kind'          => 'movement_confirm',
                    'label'         => 'Đã quá hạn — bấm «Xác nhận» ngay',
                    'hint'          => 'Xác nhận đi đón để tránh bị gỡ cuốc.',
                    'started_at'    => $deadline->toIso8601String(),
                    'deadline_at'   => null,
                    'total_seconds' => 0,
                    'indeterminate' => true,
                ];
            }
        }

        return null;
    }

    /** Cuốc chờ nhận — đếm ngược giống admin «còn ~X phút». */
    public static function forTripRequest(DriverTripRequest $request): ?array
    {
        if (! $request->isPending() || ! $request->expires_at?->isFuture()) {
            return null;
        }

        $started = $request->created_at ?? now();
        $deadline = $request->expires_at;

        return [
            'kind'          => 'trip_accept',
            'label'         => 'Cuốc chờ bạn xác nhận',
            'hint'          => 'Nhận cuốc trước khi hết giờ để tránh bị gỡ.',
            'started_at'    => $started->toIso8601String(),
            'deadline_at'   => $deadline->toIso8601String(),
            'total_seconds' => max(30, (int) $started->diffInSeconds($deadline)),
            'indeterminate' => false,
        ];
    }
}
