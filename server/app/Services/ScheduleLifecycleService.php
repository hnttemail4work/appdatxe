<?php

namespace App\Services;

use App\Models\Schedule;
use App\Models\ScheduleTemplate;
use App\Models\DriverTripRequest;
use App\Services\DriverMissedTripService;
use App\Services\DriverTripRequestService;
use App\Support\TripCode;
use App\Support\ServiceDate;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use InvalidArgumentException;

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
        $this->purgeEmptyExpiredDaySchedules($now);
        $this->deactivateStaleEmptyOffers($now);
        $this->advanceStatuses($now);
    }

    /** Tạo hoặc tái sử dụng chuyến theo nhu cầu khi khách đặt vé. */
    public function resolveScheduleForBooking(
        ScheduleTemplate $template,
        string $serviceDate,
        ?string $pickupTime = null,
        bool $alwaysCreate = false,
        int $seatsNeeded = 1,
        ?float $pickupLat = null,
        ?float $pickupLng = null,
        ?int $routeId = null,
    ): Schedule {
        $template->loadMissing(['vehicle', 'route']);
        $date = ServiceDate::parse($serviceDate);
        $availability = app(DriverAvailabilityService::class);

        $availability->assertPickupTimeAvailable($serviceDate, $pickupTime);

        $departure = $availability->resolveDepartureTime($template, $serviceDate, $pickupTime);
        $resolvedRouteId = $routeId ?? $template->route_id;

        if (! $alwaysCreate) {
            $schedule = Schedule::query()
                ->where('template_id', $template->id)
                ->whereDate('service_date', $serviceDate)
                ->where('departure_time', $departure)
                ->when($resolvedRouteId, fn ($q) => $q->where('route_id', $resolvedRouteId))
                ->where('status', 'scheduled')
                ->first();

            if ($schedule) {
                return $schedule;
            }
        } else {
            $departure = $this->nextAvailableDeparture($template, $serviceDate, $departure);
        }

        try {
            return Schedule::query()->create([
                'template_id'         => $template->id,
                'route_id'            => $resolvedRouteId,
                'vehicle_id'          => $template->vehicle_id,
                'driver_id'           => null,
                'driver_name'         => 'Chờ phân bổ',
                'departure_time'      => $departure,
                'expected_arrival_at' => $this->expectedArrivalFrom($template, $departure, $date),
                'whole_car_price'     => $template->whole_car_price,
                'service_date'        => $serviceDate,
                'status'              => 'scheduled',
                'trip_code'           => TripCode::generate(),
            ]);
        } catch (UniqueConstraintViolationException) {
            $existing = Schedule::query()
                ->where('template_id', $template->id)
                ->whereDate('service_date', $serviceDate)
                ->where('departure_time', $departure)
                ->where('status', 'scheduled')
                ->first();

            if ($existing) {
                return $existing;
            }

            throw new InvalidArgumentException('Không tạo được chuyến cho khung giờ này. Vui lòng thử lại.');
        }
    }

    /** Đặt cả xe: mỗi đơn một chuyến mới — dịch giờ khởi hành nếu trùng slot. */
    private function nextAvailableDeparture(
        ScheduleTemplate $template,
        string $serviceDate,
        Carbon $departure,
    ): Carbon {
        $candidate = $departure->copy()->startOfMinute();

        for ($attempt = 0; $attempt < 1440; $attempt++) {
            $exists = Schedule::query()
                ->where('template_id', $template->id)
                ->whereDate('service_date', $serviceDate)
                ->where('departure_time', $candidate)
                ->where('status', 'scheduled')
                ->exists();

            if (! $exists) {
                return $candidate;
            }

            $candidate = $candidate->copy()->addMinute();
        }

        throw new InvalidArgumentException('Không tạo được chuyến mới cho khung giờ này. Vui lòng đổi giờ đón.');
    }

    private function expectedArrivalFrom(ScheduleTemplate $template, Carbon $departure, Carbon $serviceDate): Carbon
    {
        if ($template->hasFixedDepartureTime() && $template->expected_arrival_time) {
            $templateDeparture = $template->departureAt($serviceDate);
            $templateArrival = $template->expectedArrivalAt($serviceDate);
            $offsetMinutes = $templateDeparture->diffInMinutes($templateArrival, false);

            if ($offsetMinutes > 0) {
                return $departure->copy()->addMinutes($offsetMinutes);
            }
        }

        return $departure->copy()->addMinutes((int) ($template->duration_minutes ?? 720));
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

                // Chỉ tài xế bấm "Bắt đầu chạy" mới chuyển running — không tự chuyển theo giờ khởi hành.
                if ($schedule->status === 'running'
                    && in_array($schedule->driver_stage, [
                        null,
                        Schedule::DRIVER_STAGE_ASSIGNED,
                        Schedule::DRIVER_STAGE_AT_PICKUP,
                        Schedule::DRIVER_STAGE_PICKED_UP,
                    ], true)) {
                    $schedule->update(['status' => 'scheduled']);
                }
            });
    }

    /** Hủy chuyến không có vé — giữ đến hết ngày chạy (service_date), không hủy ngay khi qua giờ khởi hành. */
    private function cancelEmptyStaleSchedules(Carbon $now): void
    {
        Schedule::query()
            ->whereNull('service_date')
            ->where('status', 'scheduled')
            ->whereDoesntHave('bookings', fn ($q) => $q->whereNotIn('booking_status', ['cancelled', 'rejected']))
            ->where('departure_time', '<', $now)
            ->update(['status' => 'cancelled']);
    }

    /** Xóa chuyến theo ngày đã qua (sau 0h) nếu không có khách đặt. */
    private function purgeEmptyExpiredDaySchedules(Carbon $now): void
    {
        $today = $now->toDateString();

        Schedule::query()
            ->whereNotNull('service_date')
            ->where('service_date', '<', $today)
            ->whereIn('status', ['scheduled', 'cancelled', 'draft'])
            ->whereDoesntHave('bookings', fn ($q) => $q->validForTrip())
            ->each(fn (Schedule $schedule) => $schedule->delete());
    }

    /** Ẩn tuyến đặt vé (template) nếu chỉ còn chuyến ngày cũ và không có vé hợp lệ. */
    private function deactivateStaleEmptyOffers(Carbon $now): void
    {
        $today = $now->toDateString();

        ScheduleTemplate::query()
            ->where('status', 'active')
            ->whereHas('schedules', fn ($q) => $q->whereNotNull('service_date'))
            ->whereDoesntHave('schedules', fn ($q) => $q->whereNotNull('service_date')->where('service_date', '>=', $today))
            ->whereDoesntHave('schedules.bookings', fn ($q) => $q->validForTrip())
            ->each(fn (ScheduleTemplate $template) => $template->update(['status' => 'inactive']));
    }

    private function expireStaleHolds(): void
    {
    }

    private function expireStaleDriverRequests(): void
    {
        $service = app(DriverTripRequestService::class);
        $service->expireStale();
        $service->retryWaitingBookingsWithoutExpire();
    }

    public function purgeInactiveSeatReservations(Schedule $schedule, array $seatNumbers): void
    {
    }

    /** @return array<int, string> */
    public function occupiedSeatMap(Schedule $schedule): array
    {
        return [];
    }
}
