<?php

namespace App\Support;

use App\Models\DriverTripRequest;
use App\Models\Schedule;
use App\Services\DriverMovementConfirmService;
use App\Services\DriverTripRequestService;

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

        if ($schedule->resolvedDriverStage() !== Schedule::DRIVER_STAGE_ASSIGNED) {
            return null;
        }

        $deadline = $schedule->driver_movement_deadline_at;
        $assignedAt = $schedule->driver_assigned_at;

        if (! $deadline && $assignedAt) {
            $primary = $schedule->driverRelevantBookings()->first();
            if ($primary) {
                $deadline = app(DriverMovementConfirmService::class)
                    ->computeDeadline($schedule, $primary, $assignedAt);
            }
        }

        if (! $deadline || ! $assignedAt) {
            return null;
        }

        if (! $deadline->isFuture()) {
            return [
                'kind'          => 'movement_confirm',
                'label'         => 'Đã quá hạn xác nhận di chuyển',
                'hint'          => 'Bấm «Đến điểm đón» để bắt đầu chuyến.',
                'started_at'    => $assignedAt->toIso8601String(),
                'deadline_at'   => $deadline->toIso8601String(),
                'total_seconds' => max(60, (int) $assignedAt->diffInSeconds($deadline)),
                'indeterminate' => true,
            ];
        }

        return [
            'kind'          => 'movement_confirm',
            'label'         => 'Cần bấm «Đến điểm đón»',
            'hint'          => 'Xác nhận bạn đang di chuyển đến điểm đón trước khi hết giờ.',
            'started_at'    => $assignedAt->toIso8601String(),
            'deadline_at'   => $deadline->toIso8601String(),
            'total_seconds' => max(60, (int) $assignedAt->diffInSeconds($deadline)),
            'indeterminate' => false,
        ];
    }

    /** @return array<string, mixed>|null */
    public static function forTripRequest(DriverTripRequest $request): ?array
    {
        if (! $request->isPending() || ! $request->expires_at?->isFuture()) {
            return null;
        }

        $started = $request->created_at ?? now();
        $totalSeconds = max(
            60,
            DriverTripRequestService::ACCEPT_TIMEOUT_MINUTES * 60,
            (int) $started->diffInSeconds($request->expires_at),
        );

        return [
            'kind'          => 'trip_accept',
            'label'         => 'Khách đang đợi bạn',
            'hint'          => 'Nhận hoặc từ chối — hệ thống sẽ gán chuyến cho tài xế khác nếu hết giờ.',
            'started_at'    => $started->toIso8601String(),
            'deadline_at'   => $request->expires_at->toIso8601String(),
            'total_seconds' => $totalSeconds,
            'indeterminate' => false,
        ];
    }
}
