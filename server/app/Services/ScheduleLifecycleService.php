<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\ScheduleTemplate;
use App\Models\SeatReservation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ScheduleLifecycleService
{
    public function __construct(private readonly DriverAssignmentService $driverAssignment)
    {
    }

    /** Đồng bộ chuyến theo ngày: tạo instance, cập nhật trạng thái, dọn ghế hết hạn. */
    public function sync(?Carbon $reference = null): void
    {
        $now = $reference ?? now();
        $today = $now->copy()->startOfDay();

        $this->expireStaleHolds();
        $this->ensureDailyInstances($today);
        $this->ensureDailyInstances($today->copy()->addDay());
        $this->completePastDays($today);
        $this->advanceStatuses($now);
        $this->driverAssignment->autoAssignUnassigned();
    }

    public function ensureDailyInstances(Carbon $date): void
    {
        $serviceDate = $date->toDateString();

        ScheduleTemplate::query()
            ->where('status', 'active')
            ->with('vehicle')
            ->each(function (ScheduleTemplate $template) use ($date, $serviceDate): void {
                $vehicle = $template->vehicle;
                if (! $vehicle) {
                    return;
                }

                $timeStr = is_string($template->departure_time)
                    ? substr($template->departure_time, 0, 8)
                    : $template->departure_time->format('H:i:s');

                $departureAt = Carbon::parse($serviceDate . ' ' . $timeStr, config('app.timezone'));

                Schedule::query()->firstOrCreate(
                    [
                        'template_id'  => $template->id,
                        'service_date' => $serviceDate,
                    ],
                    [
                        'route_id'        => $template->route_id,
                        'vehicle_id'      => $template->vehicle_id,
                        'driver_id'       => $template->driver_id,
                        'driver_name'     => $template->driver_name ?: 'Chưa phân công',
                        'departure_time'  => $departureAt,
                        'available_seats' => $vehicle->capacity,
                        'status'          => 'scheduled',
                    ]
                );
            });
    }

    public function advanceStatuses(Carbon $now): void
    {
        Schedule::query()
            ->with('template')
            ->whereIn('status', ['scheduled', 'running'])
            ->where('departure_time', '<=', $now->copy()->addDay())
            ->each(function (Schedule $schedule) use ($now): void {
                if ($schedule->status === 'scheduled' && $schedule->departure_time <= $now) {
                    $schedule->update(['status' => 'running']);
                }

                if ($schedule->fresh()->status !== 'running') {
                    return;
                }

                $durationMinutes = $schedule->template?->duration_minutes ?? 720;
                $completeAt = $schedule->departure_time->copy()->addMinutes($durationMinutes);
                $endOfServiceDay = $schedule->departure_time->copy()->endOfDay();
                $deadline = $completeAt->lessThan($endOfServiceDay) ? $completeAt : $endOfServiceDay;

                if ($now >= $deadline) {
                    $schedule->update(['status' => 'completed']);
                }
            });

        $this->syncLegacySchedules($now);
    }

    public function completePastDays(Carbon $today): void
    {
        Schedule::query()
            ->whereNotNull('service_date')
            ->where('service_date', '<', $today->toDateString())
            ->whereIn('status', ['scheduled', 'running'])
            ->update(['status' => 'completed']);
    }

    private function syncLegacySchedules(Carbon $now): void
    {
        Schedule::query()
            ->whereNull('template_id')
            ->where('status', 'scheduled')
            ->where('departure_time', '<=', $now)
            ->update(['status' => 'running']);

        Schedule::query()
            ->whereNull('template_id')
            ->where('status', 'running')
            ->where('departure_time', '<', $now->copy()->subHours(12))
            ->update(['status' => 'completed']);
    }

    private function expireStaleHolds(): void
    {
        SeatReservation::query()
            ->where('status', 'held')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired']);

        Schedule::query()
            ->whereHas('seatReservations', fn ($q) => $q->where('status', 'expired'))
            ->each(function (Schedule $schedule): void {
                app(BookingWorkflowService::class)->syncScheduleAvailability($schedule);
            });
    }

    /** @return array<int, string> seat_number => status (held|booked) */
    public function occupiedSeatMap(Schedule $schedule): array
    {
        return $schedule->seatReservations()
            ->whereIn('status', ['held', 'booked'])
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->pluck('status', 'seat_number')
            ->all();
    }
}
