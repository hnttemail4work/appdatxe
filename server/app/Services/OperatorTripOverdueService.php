<?php

namespace App\Services;

use App\Models\Booking;

class OperatorTripOverdueService
{
    public const ASSUMED_SPEED_KMH = 30;

    /** Đưa chuyến đã có tài xế nhưng quá hạn hoàn thành vào tab Cần xử lý. */
    public function escalateOverdueTrips(): int
    {
        $escalated = 0;

        Booking::query()
            ->whereNotIn('booking_status', ['cancelled', 'rejected'])
            ->where('trip_status', '!=', 'completed')
            ->whereNull('expired_at')
            ->whereHas('schedule', fn ($q) => $q->whereNotNull('driver_id'))
            ->with(['schedule.route'])
            ->each(function (Booking $booking) use (&$escalated): void {
                if (! $booking->isPastExpectedCompletion()) {
                    return;
                }

                if ($booking->operator_help_reason === Booking::HELP_TRIP_OVERDUE) {
                    if ($booking->schedule?->driver_id) {
                        app(BookingWorkflowService::class)->handOffScheduleToOperator(
                            $booking->schedule,
                            Booking::HELP_TRIP_OVERDUE,
                        );
                    }

                    return;
                }

                if (! $booking->hasDriverAccepted()) {
                    return;
                }

                $booking->update([
                    'needs_operator_help_at' => $booking->needs_operator_help_at ?? now(),
                    'operator_help_reason'   => Booking::HELP_TRIP_OVERDUE,
                ]);

                app(BookingWorkflowService::class)->handOffScheduleToOperator(
                    $booking->schedule,
                    Booking::HELP_TRIP_OVERDUE,
                );

                $escalated++;
            });

        return $escalated;
    }
}
