<?php

namespace App\Services;

use App\Models\DriverProfile;
use App\Models\Schedule;
use App\Models\ScheduleTemplate;
use App\Support\DepartureTimeDisplay;
use App\Support\ServiceDate;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class DriverAvailabilityService
{
    /**
     * Tài xế bận khung giờ khi đã nhận chuyến (driver_id gán) cùng điểm đi/đến + giờ khởi hành và xe đã full ghế.
     * Yêu cầu chờ xác nhận không làm tài xế bận.
     */
    public function isDriverBusyForSlot(
        int $driverUserId,
        string $departureCity,
        string $destinationCity,
        Carbon $departureTime,
    ): bool {
        return Schedule::query()
            ->with(['route', 'vehicle', 'seatReservations'])
            ->where('driver_id', $driverUserId)
            ->whereIn('status', ['scheduled', 'running'])
            ->where('departure_time', $departureTime)
            ->whereHas('route', fn ($q) => $q
                ->where('departure', $departureCity)
                ->where('destination', $destinationCity))
            ->get()
            ->contains(fn (Schedule $schedule): bool => $schedule->bookedSeatsCount() >= $schedule->capacity());
    }

    /** @return Collection<int, DriverProfile> */
    public function availableForBooking(
        ScheduleTemplate $template,
        string $serviceDate,
        ?string $preferredTime,
        string $pickupCity,
        string $dropoffCity,
        ?Schedule $schedule = null,
    ): Collection {
        $template->loadMissing(['vehicle', 'route']);
        $departureTime = $this->resolveDepartureTime($template, $serviceDate, $preferredTime);
        $departureCity = trim($pickupCity) ?: $template->route->departure;
        $destinationCity = trim($dropoffCity) ?: $template->route->destination;

        $query = DriverProfile::query()
            ->operational()
            ->with(['user', 'operator'])
            ->orderByRaw("FIELD(availability_status, 'available', 'off_duty', 'on_trip')")
            ->orderByDesc('experience_years');

        $drivers = $query->get();

        if ($schedule?->driver_id && $schedule->bookedSeatsCount() < $schedule->capacity()) {
            return $drivers
                ->filter(fn (DriverProfile $p): bool => (int) $p->user_id === (int) $schedule->driver_id)
                ->values();
        }

        return $drivers
            ->filter(function (DriverProfile $profile) use ($departureCity, $destinationCity, $departureTime): bool {
                return ! $this->isDriverBusyForSlot(
                    (int) $profile->user_id,
                    $departureCity,
                    $destinationCity,
                    $departureTime,
                );
            })
            ->values();
    }

    /** @return array<int, array<string, mixed>> */
    public function serializeForGuest(
        Collection $drivers,
        ?Schedule $schedule = null,
    ): array {
        return $drivers->map(function (DriverProfile $profile) use ($schedule) {
            $data = app(DriverTripRequestService::class)->serializeDriver($profile);
            $data['seats_hint'] = null;

            if ($schedule && (int) $schedule->driver_id === (int) $profile->user_id) {
                $booked = $schedule->bookedSeatsCount();
                $cap = $schedule->capacity();
                $data['seats_hint'] = $booked . '/' . $cap . ' ghế, còn nhận thêm';
                $data['already_assigned'] = true;
            } else {
                $data['already_assigned'] = false;
            }

            return $data;
        })->all();
    }

    public function resolveDepartureTime(
        ScheduleTemplate $template,
        string $serviceDate,
        ?string $preferredTime = null,
    ): Carbon {
        $template->loadMissing('route');
        $date = ServiceDate::parse($serviceDate);

        if (is_string($preferredTime) && trim($preferredTime) !== '') {
            $departure = $this->clockOnDate($date, DepartureTimeDisplay::normalizeForClock($preferredTime));
        } else {
            $departure = $template->departureAt($date);
        }

        return $departure->copy()->startOfMinute();
    }

    public function assertPickupTimeAvailable(string $serviceDate, ?string $pickupTime): void
    {
        if (! is_string($pickupTime) || trim($pickupTime) === '') {
            throw new InvalidArgumentException('Vui lòng chọn giờ đón.');
        }

        $departure = $this->clockOnDate(
            ServiceDate::parse($serviceDate),
            DepartureTimeDisplay::normalizeForClock($pickupTime),
        );

        if ($departure <= now()) {
            throw new InvalidArgumentException('Giờ đón phải sau thời gian hiện tại.');
        }
    }

    private function clockOnDate(Carbon $serviceDate, string $clock): Carbon
    {
        return Carbon::parse(
            $serviceDate->toDateString() . ' ' . $clock . ':00',
            config('app.timezone'),
        );
    }

    public function assertDriverSelectable(
        DriverProfile $profile,
        ScheduleTemplate $template,
        string $serviceDate,
        ?string $preferredTime,
        string $pickupCity,
        string $dropoffCity,
        ?Schedule $schedule = null,
    ): void {
        $available = $this->availableForBooking(
            $template,
            $serviceDate,
            $preferredTime,
            $pickupCity,
            $dropoffCity,
            $schedule,
        );

        if (! $available->contains(fn (DriverProfile $p): bool => (int) $p->user_id === (int) $profile->user_id)) {
            throw new \InvalidArgumentException('Tài xế không còn rảnh cho khung giờ và tuyến này (có thể đã full ghế). Vui lòng chọn tài xế khác.');
        }
    }
}
