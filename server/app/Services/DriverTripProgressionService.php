<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingAudit;
use App\Models\Schedule;
use App\Models\TripLedger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Phần "tài xế tiến trình chuyến" — tách ra từ BookingWorkflowService (God Service):
 * xác nhận nhận khách, chuyển bước (đến điểm đón → đón khách → đang chạy), hoàn thành chuyến.
 * Không đụng tới hủy/hoãn/gán lại — các nhóm đó có gọi chéo qua lại với nhau nên vẫn
 * giữ trong BookingWorkflowService.
 */
class DriverTripProgressionService
{
    public function __construct(
        private readonly DriverWalletService $driverWallet,
        private readonly TripLedgerService $tripLedger,
        private readonly BookingBrowserGuardService $browserGuard,
        private readonly DriverAvailabilityService $driverAvailability,
    ) {
    }

    /** Tài xế nhận cuốc — khách trả trực tiếp, không cần QL xác nhận thanh toán. */
    public function confirmForDriverAccept(Booking $booking): void
    {
        if (in_array($booking->booking_status, ['cancelled', 'rejected'], true)) {
            return;
        }

        if ($booking->booking_status === 'confirmed' && $booking->trip_status === 'confirmed') {
            return;
        }

        DB::transaction(function () use ($booking): void {
            $before = $booking->toArray();

            $booking->update([
                'booking_status' => 'confirmed',
                'trip_status'    => 'confirmed',
                'payment_status' => 'paid',
                'confirmed_at'   => now(),
            ]);

            $this->syncScheduleAvailability($booking->schedule()->with(['vehicle'])->first());
            $this->audit($booking, null, 'driver_accept_confirmed', $before, $booking->fresh()->toArray());
        });
    }

    public function driverCompleteTrip(Booking $booking, int $driverUserId): int
    {
        $booking->loadMissing('schedule');

        return $this->driverCompleteSchedule($booking->schedule, $driverUserId);
    }

    /** Tài xế chuyển bước: đến điểm đón → đón khách → đang chạy. */
    public function driverAdvanceScheduleStage(Schedule $schedule, int $driverUserId): string
    {
        if ((int) $schedule->driver_id !== $driverUserId) {
            abort(403, 'Bạn không được phân công cho chuyến này.');
        }

        if (in_array($schedule->status, ['completed', 'cancelled'], true)) {
            throw new InvalidArgumentException('Chuyến đã kết thúc.');
        }

        $next = $schedule->driverNextStage();
        if (! $next || $next === Schedule::DRIVER_STAGE_COMPLETED) {
            throw new InvalidArgumentException('Không thể chuyển bước tiếp theo.');
        }

        if ($next === Schedule::DRIVER_STAGE_AT_PICKUP && $schedule->needsDriverMovementConfirm()) {
            throw new InvalidArgumentException('Bấm «Xác nhận» trước khi đến điểm đón.');
        }

        DB::transaction(function () use ($schedule, $next): void {
            $schedule = Schedule::query()->lockForUpdate()->findOrFail($schedule->id);
            $bookings = Booking::query()
                ->where('schedule_id', $schedule->id)
                ->whereNotIn('booking_status', ['cancelled', 'rejected'])
                ->lockForUpdate()
                ->get();

            if ($bookings->isEmpty()) {
                throw new InvalidArgumentException('Không còn vé trên chuyến này.');
            }

            if ($next === Schedule::DRIVER_STAGE_PICKED_UP) {
                foreach ($bookings as $booking) {
                    $this->confirmForDriverAccept($booking);
                }
            }

            $payload = ['driver_stage' => $next];

            if ($next === Schedule::DRIVER_STAGE_AT_PICKUP) {
                app(DriverMovementConfirmService::class)->clearDeadline($schedule);
            }

            if ($next === Schedule::DRIVER_STAGE_RUNNING) {
                $payload['status'] = 'running';
            }

            $schedule->update($payload);
        });

        $freshSchedule = $schedule->fresh();
        try {
            app(PushNotificationService::class)->onDriverStageAdvanced($freshSchedule, $next);
        } catch (\Throwable) {
        }

        return $next;
    }

    /** Hoàn thành tất cả vé còn lại trên cùng một chuyến xe. */
    public function driverCompleteSchedule(Schedule $schedule, int $driverUserId): int
    {
        if ((int) $schedule->driver_id !== $driverUserId) {
            abort(403, 'Bạn không được phân công cho chuyến này.');
        }

        $completed = 0;
        $completedBookings = collect();

        DB::transaction(function () use ($schedule, $driverUserId, &$completed, &$completedBookings): void {
            $bookings = Booking::query()
                ->where('schedule_id', $schedule->id)
                ->whereNotIn('booking_status', ['cancelled', 'rejected'])
                ->where('trip_status', '!=', 'completed')
                ->lockForUpdate()
                ->get();

            if ($bookings->isEmpty()) {
                throw new InvalidArgumentException('Không còn vé nào cần hoàn thành trên chuyến này.');
            }

            foreach ($bookings as $booking) {
                $this->completeSingleDriverTrip($booking, $driverUserId);
                $completedBookings->push($booking);
                $completed++;
            }

            $schedule->update([
                'driver_stage' => Schedule::DRIVER_STAGE_COMPLETED,
                'status'       => 'completed',
            ]);
        });

        $schedule = $schedule->fresh(['route', 'bookings']);
        $this->driverWallet->onScheduleCompleted($schedule);
        $this->tripLedger->recordForSchedule($schedule, TripLedger::OUTCOME_COMPLETED);
        $this->syncScheduleAvailability($schedule);

        return $completed;
    }

    private function completeSingleDriverTrip(Booking $booking, int $driverUserId): void
    {
        $this->assertNotTerminal($booking);

        if ($booking->trip_status === 'completed') {
            return;
        }

        $before = $booking->toArray();

        $booking->update([
            'trip_status'  => 'completed',
            'completed_at' => now(),
        ]);

        $this->audit($booking, $driverUserId, 'driver_trip_completed', $before, $booking->fresh()->toArray());
        if ($booking->tripReview) {
            $this->browserGuard->clearActiveBookingForBooking($booking->fresh());
        }
    }

    private function assertNotTerminal(Booking $booking): void
    {
        if (in_array($booking->booking_status, ['cancelled', 'rejected'], true)) {
            throw new InvalidArgumentException('Vé đã bị hủy hoặc từ chối.');
        }
    }

    private function syncScheduleAvailability(Schedule $schedule): void
    {
        $driverUserId = (int) ($schedule->driver_id ?? 0);

        if ($driverUserId <= 0) {
            return;
        }

        $this->driverAvailability->syncAfterTripCompleted($driverUserId);
    }

    private function audit(Booking $booking, ?int $actor, string $action, ?array $before, ?array $after): void
    {
        BookingAudit::query()->create([
            'booking_id'   => $booking->id,
            'actor_id'     => $actor,
            'action'       => $action,
            'before_state' => $before,
            'after_state'  => $after,
        ]);
    }
}
