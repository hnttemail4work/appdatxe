<?php

namespace App\Models;

use App\Services\DriverAvailabilityService;
use App\Support\DepartureTimeDisplay;
use App\Support\ServiceDate;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class ScheduleTemplate extends Model
{
    protected $fillable = [
        'route_id',
        'vehicle_id',
        'driver_id',
        'driver_name',
        'departure_time',
        'expected_arrival_time',
        'seat_price',
        'whole_car_price',
        'whole_car_round_trip_price',
        'seat_round_trip_price',
        'duration_minutes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'seat_price'                 => 'decimal:2',
            'whole_car_price'            => 'decimal:2',
            'whole_car_round_trip_price' => 'decimal:2',
            'seat_round_trip_price'      => 'decimal:2',
        ];
    }

    public function route()
    {
        return $this->belongsTo(TripRoute::class, 'route_id');
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class, 'template_id');
    }

    public function hasFixedDepartureTime(): bool
    {
        return $this->departure_time !== null && trim((string) $this->departure_time) !== '';
    }

    public function departureAt(Carbon $serviceDate): Carbon
    {
        if (! $this->hasFixedDepartureTime()) {
            return app(DriverAvailabilityService::class)
                ->resolveDepartureTime($this, $serviceDate->toDateString(), null);
        }

        return $this->clockOnDate($serviceDate, $this->departure_time);
    }

    public function expectedArrivalAt(Carbon $serviceDate): Carbon
    {
        if ($this->expected_arrival_time && $this->hasFixedDepartureTime()) {
            $departure = $this->departureAt($serviceDate);
            $arrival = $this->clockOnDate($serviceDate, $this->expected_arrival_time);

            if ($arrival <= $departure) {
                $arrival->addDay();
            }

            return $arrival;
        }

        return $this->departureAt($serviceDate)->copy()->addMinutes((int) ($this->duration_minutes ?? 720));
    }

    public function seatPrice(): float
    {
        if ($this->seat_price !== null) {
            return (float) $this->seat_price;
        }

        return (float) app(\App\Services\TripPricingService::class)
            ->sharedSeatFromWholeCar((int) $this->wholeCarPriceAmount());
    }

    public function wholeCarPriceAmount(): float
    {
        if ($this->whole_car_price !== null) {
            return (float) $this->whole_car_price;
        }

        return (float) app(\App\Services\TripPricingService::class)->wholeCarPrice($this);
    }

    public function capacity(): int
    {
        $this->loadMissing('vehicle');

        return (int) ($this->vehicle?->capacity ?? 0);
    }

    /** Thông tin lịch chuyến theo ngày khách chọn. */
    public function scheduleInfoForDate(Carbon|string $serviceDate): array
    {
        $date = $serviceDate instanceof Carbon
            ? $serviceDate->copy()->startOfDay()
            : ServiceDate::parse($serviceDate);

        $departure = $this->departureAt($date);
        $arrival = $this->expectedArrivalAt($date);
        $hasFixedDeparture = $this->hasFixedDepartureTime();

        $weekdays = ['Chủ nhật', 'Thứ 2', 'Thứ 3', 'Thứ 4', 'Thứ 5', 'Thứ 6', 'Thứ 7'];
        $weekdaysShort = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'];

        return [
            'service_date'       => $date->toDateString(),
            'weekday'            => $weekdays[(int) $date->dayOfWeek],
            'weekday_short'      => $weekdaysShort[(int) $date->dayOfWeek],
            'date_day'           => $date->format('d'),
            'date_month'         => $date->format('m/Y'),
            'date_short'         => $date->format('d/m/Y'),
            'date_label'         => $weekdays[(int) $date->dayOfWeek] . ', ' . $date->format('d/m/Y'),
            'departure_time'     => $hasFixedDeparture
                ? DepartureTimeDisplay::label($this->departure_time)
                : 'Tự chọn',
            'departure_clock'    => $hasFixedDeparture
                ? DepartureTimeDisplay::normalizeForClock($this->departure_time)
                : null,
            'arrival_time'       => $arrival->format('H:i'),
            'arrival_date_short' => $arrival->format('d/m/Y'),
            'same_day'           => $arrival->isSameDay($departure),
            'time_range'         => $hasFixedDeparture
                ? ($arrival->isSameDay($departure)
                    ? $departure->format('H:i') . ' → ' . $arrival->format('H:i')
                    : $departure->format('H:i') . ', ' . $departure->format('d/m')
                    . ' → ' . $arrival->format('H:i') . ', ' . $arrival->format('d/m/Y'))
                : 'Khách chọn giờ đón',
        ];
    }

    private function clockOnDate(Carbon $serviceDate, mixed $time): Carbon
    {
        $timeStr = \App\Support\DepartureTimeDisplay::normalizeForClock($time) . ':00';

        return Carbon::parse($serviceDate->toDateString() . ' ' . $timeStr, config('app.timezone'));
    }
}
