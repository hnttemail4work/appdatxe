<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\ScheduleTemplate;
use App\Models\SeatReservation;
use App\Models\DriverTripRequest;
use App\Services\DriverMissedTripService;
use App\Services\DriverTripRequestService;
use App\Support\TripCode;
use Carbon\Carbon;

class ScheduleLifecycleService
{
    /** Dọn ghế hết hạn và cập nhật chuyến đã có khách đặt. Không tạo lịch chạy hằng ngày. */
    public function sync(?Carbon $reference = null): void
    {
        $now = $reference ?? now();

        $this->expireStaleHolds();
        app(BookingWorkflowService::class)->expireStaleBookings();
        $this->expireStaleDriverRequests();
        $this->backfillExpectedArrivals();
        $this->cancelEmptyStaleSchedules($now);
        $this->advanceStatuses($now);
    }

    /** Tạo hoặc tái sử dụng chuyến theo nhu cầu khi khách đặt vé. */
    public function resolveScheduleForBooking(
        ScheduleTemplate $template,
        string $serviceDate,
    ): Schedule {
        $template->loadMissing(['vehicle', 'route']);
        $date = Carbon::parse($serviceDate)->startOfDay();

        $departure = $template->departureAt($date);

        if ($departure <= now()) {
            abort(422, 'Thời gian khởi hành phải ở tương lai.');
        }

        $schedule = Schedule::query()
            ->where('template_id', $template->id)
            ->whereDate('service_date', $serviceDate)
            ->where('departure_time', $departure)
            ->where('status', 'scheduled')
            ->where('departure_time', '>', now())
            ->first();

        if ($schedule) {
            return $schedule;
        }

        return Schedule::query()->create([
            'template_id'         => $template->id,
            'route_id'            => $template->route_id,
            'vehicle_id'          => $template->vehicle_id,
            'driver_id'           => null,
            'driver_name'         => 'Chờ phân bổ',
            'departure_time'      => $departure,
            'expected_arrival_at' => $template->expectedArrivalAt($date),
            'seat_price'          => $template->seat_price,
            'whole_car_price'     => $template->whole_car_price,
            'service_date'        => $serviceDate,
            'available_seats'     => $template->vehicle->capacity,
            'status'              => 'scheduled',
            'trip_code'           => TripCode::generate(),
        ]);
    }

    private function backfillExpectedArrivals(): void
    {
        Schedule::query()
            ->whereNull('expected_arrival_at')
            ->whereNotNull('departure_time')
            ->with('template')
            ->each(function (Schedule $schedule): void {
                $schedule->update([
                    'expected_arrival_at' => $schedule->expectedArrivalAt(),
                ]);
            });
    }

    /** Chỉ chuyến đã có khách xác nhận mới tự chuyển trạng thái chạy / hoàn tất. */
    public function advanceStatuses(Carbon $now): void
    {
        Schedule::query()
            ->with('template')
            ->whereIn('status', ['scheduled', 'running'])
            ->whereNotNull('driver_id')
            ->whereHas('bookings', fn ($q) => $q->whereNotIn('booking_status', ['cancelled', 'rejected']))
            ->each(function (Schedule $schedule) use ($now): void {
                if ($now >= $schedule->completesAt()) {
                    if ($schedule->status !== 'completed') {
                        $schedule->update(['status' => 'completed']);
                        app(BookingWorkflowService::class)->finalizeTripsAfterScheduleEnd($schedule);
                    }

                    return;
                }

                if ($schedule->status === 'scheduled' && $schedule->departure_time <= $now) {
                    $schedule->update(['status' => 'running']);
                }
            });
    }

    /** Hủy chuyến tạo theo nhu cầu nhưng không còn vé hợp lệ. */
    private function cancelEmptyStaleSchedules(Carbon $now): void
    {
        Schedule::query()
            ->where('status', 'scheduled')
            ->whereDoesntHave('bookings', fn ($q) => $q->whereNotIn('booking_status', ['cancelled', 'rejected']))
            ->where('departure_time', '<', $now)
            ->update(['status' => 'cancelled']);
    }

    private function expireStaleHolds(): void
    {
    }

    private function expireStaleDriverRequests(): void
    {
        app(DriverTripRequestService::class)->expireStale();
    }

    /** Xóa ghế đã hết hạn / đã nhả để có thể đặt lại cùng schedule + số ghế. */
    public function purgeInactiveSeatReservations(Schedule $schedule, array $seatNumbers): void
    {
        if ($seatNumbers === []) {
            return;
        }

        $normalized = array_map(fn ($seat): string => (string) $seat, $seatNumbers);

        SeatReservation::query()
            ->where('schedule_id', $schedule->id)
            ->whereIn('seat_number', $normalized)
            ->whereIn('status', ['expired', 'released'])
            ->delete();
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
